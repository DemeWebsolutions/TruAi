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

        // Set session with encrypted data
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Generate secure session token
        $_SESSION['session_token'] = bin2hex(random_bytes(32));

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
     * Enforce localhost access only
     */
    public static function enforceLocalhost() {
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
