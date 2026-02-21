<?php
/**
 * TruAi Authentication and Authorization
 * 
 * Handles user authentication, session management, and security
 * 
 * @package TruAi
 * @version 1.0.0
 */

class Auth {
    private $db;
    private $encryption;

    public function __construct() {
        $this->db = Database::getInstance();
        require_once __DIR__ . '/encryption.php';
        $this->encryption = new EncryptionService();
    }

    /**
     * Authenticate user with username and password (supports encrypted login)
     */
    public function login($username, $password, $isEncrypted = false, $encryptedData = null, $sessionId = null) {
        // Handle encrypted login (Phantom.ai style)
        if ($isEncrypted && $encryptedData && $sessionId) {
            try {
                $credentials = $this->encryption->decryptCredentials($encryptedData, $sessionId);
                $username = $credentials['username'];
                $passwordHash = $credentials['password_hash'];

                return $this->loginWithHash($username, $passwordHash);
            } catch (Exception $e) {
                error_log('Encrypted login failed: ' . $e->getMessage());
                return false;
            }
        }

        // Validate username format before querying the database
        require_once __DIR__ . '/validator.php';
        $usernameValidation = Validator::username($username ?? '');
        if (!$usernameValidation['valid']) {
            error_log('[AUTH] Invalid username format: ' . implode(', ', $usernameValidation['errors']));
            return false;
        }
        $username = $usernameValidation['value'];

        // Basic password length check
        if (strlen($password ?? '') < 8) {
            error_log('[AUTH] Password too short for username: ' . $username);
            return false;
        }

        // Standard login
        return $this->loginStandard($username, $password);
    }

    /**
     * Standard password-based login
     */
    private function loginStandard($username, $password) {
        $result = $this->db->query(
            "SELECT * FROM users WHERE username = :username LIMIT 1",
            [':username' => $username]
        );

        if (empty($result)) {
            return false;
        }

        $user = $result[0];
        if (password_verify($password, $user['password_hash'])) {
            $this->setUserSession($user);
            return true;
        }

        return false;
    }

    /**
     * Login with pre-hashed password (from encrypted credentials)
     */
    private function loginWithHash($username, $clientHash) {
        $result = $this->db->query(
            "SELECT * FROM users WHERE username = :username LIMIT 1",
            [':username' => $username]
        );

        if (empty($result)) {
            return false;
        }

        $user = $result[0];
        
        // For encrypted login, verify against double-hashed password
        // Client sends SHA-256(password), we hash again and compare
        $serverHash = hash('sha256', $user['password_hash']);
        
        // Also support direct password_hash comparison for compatibility
        if (hash_equals($clientHash, $serverHash) || password_verify($clientHash, $user['password_hash'])) {
            $this->setUserSession($user);
            return true;
        }

        return false;
    }

    /**
     * Set user session data
     */
    private function setUserSession($user) {
        // Update last login
        $this->db->execute(
            "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :id",
            [':id' => $user['id']]
        );

        // Regenerate session ID to prevent fixation attacks
        session_regenerate_id(true);

        // Set session with encrypted data
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';

        // Generate secure session token
        $_SESSION['session_token'] = bin2hex(random_bytes(32));

        error_log(sprintf(
            '[AUTH] User %d (%s) logged in from %s',
            $user['id'],
            $user['username'],
            $_SESSION['ip_address']
        ));

        $this->auditLog('USER_LOGIN', $user['username']);
    }

    /**
     * Get encryption service
     */
    public function getEncryptionService() {
        return $this->encryption;
    }

    /**
     * Logout current user
     */
    public function logout() {
        if (isset($_SESSION['username'])) {
            $this->auditLog('USER_LOGOUT', $_SESSION['username']);
        }
        session_unset();
        session_destroy();
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated() {
        if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
            return false;
        }

        // Check session timeout
        if (isset($_SESSION['login_time']) && 
            (time() - $_SESSION['login_time'] > SESSION_LIFETIME)) {
            $this->logout();
            return false;
        }

        return true;
    }

    /**
     * Get current user ID
     */
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get current username
     */
    public function getUsername() {
        return $_SESSION['username'] ?? null;
    }

    /**
     * Enforce localhost access only (skipped in production)
     */
    public static function enforceLocalhost() {
        if (defined('TRUAI_DEPLOYMENT') && TRUAI_DEPLOYMENT === 'production') {
            return;
        }
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!in_array($remoteAddr, ALLOWED_HOSTS)) {
            http_response_code(403);
            die(json_encode(['error' => 'Access denied. Localhost only.']));
        }
    }

    /**
     * Require admin authentication
     */
    public function requireAdmin() {
        if (!$this->isAuthenticated()) {
            http_response_code(401);
            die(json_encode(['error' => 'Unauthorized']));
        }

        if ($_SESSION['role'] !== 'SUPER_ADMIN') {
            http_response_code(403);
            die(json_encode(['error' => 'Forbidden. Admin access required.']));
        }
    }

    /**
     * Validate username format (alphanumeric, underscore, hyphen; 3–32 chars).
     */
    public static function validateUsername(string $username): bool {
        return preg_match('/^[a-zA-Z0-9_-]{3,32}$/', $username) === 1;
    }

    /**
     * Validate password complexity.
     * Returns ['valid' => bool, 'errors' => string[]].
     */
    public static function validatePasswordComplexity(string $password): array {
        $errors = [];
        if (strlen($password) < 12) {
            $errors[] = 'Must be at least 12 characters';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Must contain an uppercase letter';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Must contain a lowercase letter';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Must contain a digit';
        }
        if (!preg_match('/[!@#$%^&*()\-_=+\[\]{};:\'",.<>?\/\\\\|`~]/', $password)) {
            $errors[] = 'Must contain a special character (e.g. !@#$%^&*)';
        }
        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Generate CSRF token
     */
    public static function generateCsrfToken() {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    /**
     * Verify CSRF token
     */
    public static function verifyCsrfToken($token) {
        return isset($_SESSION[CSRF_TOKEN_NAME]) && 
               hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }

    /**
     * Change password for current user (requires current password verification).
     * Returns true on success.
     */
    public function changePassword($currentPassword, $newPassword) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        $user = $this->db->query(
            "SELECT id, username, password_hash FROM users WHERE id = :id LIMIT 1",
            [':id' => $this->getUserId()]
        );
        if (empty($user) || !password_verify($currentPassword, $user[0]['password_hash'])) {
            return false;
        }
        $validation = self::validatePasswordComplexity($newPassword);
        if (!$validation['valid']) {
            throw new InvalidArgumentException(implode('; ', $validation['errors']));
        }
        $hash = password_hash($newPassword, PASSWORD_ARGON2ID, ARGON2ID_OPTIONS);
        $this->db->execute(
            "UPDATE users SET password_hash = :hash WHERE id = :id",
            [':hash' => $hash, ':id' => $this->getUserId()]
        );
        $this->auditLog('PASSWORD_CHANGE', $this->getUsername());
        return true;
    }

    /**
     * Log audit event
     */
    private function auditLog($event, $actor, $details = null) {
        $userId = $this->getUserId();
        $this->db->execute(
            "INSERT INTO audit_logs (user_id, event, actor, details) 
             VALUES (:user_id, :event, :actor, :details)",
            [
                ':user_id' => $userId,
                ':event' => $event,
                ':actor' => $actor,
                ':details' => $details ? json_encode($details) : null
            ]
        );
    }
}
