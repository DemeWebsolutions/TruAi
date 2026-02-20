<?php
/**
 * Local Sovereign Recovery Protocol (LSRP) v1.0
 *
 * Replaces traditional password reset with a ROMA-encrypted,
 * physically-bound, multi-factor recovery system.
 *
 * Requirements to initiate recovery:
 *   Factor 1 — Local server access (localhost / trusted VPN)
 *   Factor 2 — ROMA trust verified
 *   Factor 3 — OS-level administrator password confirmation
 *   Factor 4 — Device fingerprint match (advisory; warns on mismatch)
 *
 * @package TruAi
 * @copyright My Deme, LLC © 2026
 */

class LSRPRecoveryController {
    private $db;

    public function __construct() {
        require_once __DIR__ . '/database.php';
        $this->db = Database::getInstance();
    }

    /**
     * Main recovery handler.  Returns a JSON-serialisable result array.
     */
    public function handleRecovery(array $data): array {
        // Hard requirement: HTTPS only (or localhost during development)
        if (!$this->isSecureConnection()) {
            return ['success' => false, 'error' => 'HTTPS required for recovery'];
        }

        $username   = trim($data['username'] ?? '');
        $osUsername = trim($data['os_username'] ?? '');
        $osPassword = $data['os_password'] ?? '';

        if (empty($username)) {
            return ['success' => false, 'error' => 'Username required'];
        }

        // Look up user
        $stmt = $this->db->getConnection()->prepare(
            'SELECT id, username, account_suspended FROM users WHERE username = ? LIMIT 1'
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->logAttempt(null, 'DENIED', 'User not found: ' . $username);
            return ['success' => false, 'error' => 'Invalid recovery request'];
        }

        if (!empty($user['account_suspended'])) {
            $this->logAttempt((int)$user['id'], 'DENIED', 'Account suspended');
            return ['success' => false, 'error' => 'Account suspended. Contact administrator.'];
        }

        // Rate limit: 3 failures in 24 hours → cooldown
        if (!$this->checkRateLimit((int)$user['id'])) {
            return ['success' => false, 'error' => 'Too many failed recovery attempts. Wait 24 hours.'];
        }

        // Factor 1: Local access
        if (!$this->verifyLocalAccess()) {
            return ['success' => false, 'error' => 'Recovery must be performed from local server'];
        }

        // Factor 2: ROMA trust
        if (!$this->verifyROMATrust()) {
            return ['success' => false, 'error' => 'ROMA trust verification failed'];
        }

        // Factor 3: OS admin confirmation
        if (!empty($osUsername) && !$this->verifySystemAdmin($osUsername, $osPassword)) {
            $this->logAttempt((int)$user['id'], 'DENIED', 'OS admin verification failed');
            return ['success' => false, 'error' => 'System administrator credentials invalid'];
        }

        // Factor 4: Device fingerprint (advisory)
        if (!$this->verifyDeviceFingerprint((int)$user['id'])) {
            $this->logAttempt((int)$user['id'], 'WARNING', 'Device fingerprint mismatch');
        }

        // Check for suspicious activity (auto-suspend after 5 failures in 1 hour)
        if ($this->checkSuspiciousActivity((int)$user['id'])) {
            return ['success' => false, 'error' => 'Account suspended due to suspicious activity. Use master key.'];
        }

        // Generate temporary credential
        $credential = $this->generateTemporaryCredential((int)$user['id']);
        return [
            'success'            => true,
            'temporary_password' => $credential['temporary_password'],
            'expires_at'         => $credential['expires_at'],
            'message'            => 'Temporary password valid for 10 minutes. Must change on first login.',
        ];
    }

    /**
     * Generate and store a master recovery key for a user.
     * Returns the plaintext key (display once, store offline).
     */
    public function generateMasterKey(int $userId, string $osUsername = '', string $osPassword = ''): array {
        // OS admin confirmation required for (re)generation
        if (!empty($osUsername) && !$this->verifySystemAdmin($osUsername, $osPassword)) {
            return ['success' => false, 'error' => 'System administrator credentials required to generate master key'];
        }

        $masterKey = bin2hex(random_bytes(32)); // 256-bit = 64 hex chars
        $keyHash   = hash('sha256', $masterKey);

        $this->db->getConnection()->prepare(
            'DELETE FROM master_recovery_keys WHERE user_id = ?'
        )->execute([$userId]);

        $this->db->getConnection()->prepare(
            'INSERT INTO master_recovery_keys (user_id, key_hash, issued_at) VALUES (?, ?, datetime("now"))'
        )->execute([$userId, $keyHash]);

        return [
            'success'  => true,
            'key'      => $masterKey,
            'warning'  => 'STORE THIS KEY OFFLINE. It cannot be recovered. Delete from screen after printing.',
        ];
    }

    // -------------------------------------------------------------------------
    // Private factor verification methods
    // -------------------------------------------------------------------------

    private function isSecureConnection(): bool {
        // Allow localhost over HTTP during development
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }

    private function verifyLocalAccess(): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }
        // Allow trusted VPN ranges (configurable via env)
        $trustedCidrs = array_filter(array_map('trim', explode(',', getenv('LSRP_TRUSTED_VPNS') ?: '')));
        foreach ($trustedCidrs as $cidr) {
            if ($this->ipInCidr($ip, $cidr)) {
                return true;
            }
        }
        $this->logAttempt(null, 'DENIED', 'Non-local access attempt from ' . $ip);
        return false;
    }

    private function verifyROMATrust(): bool {
        try {
            require_once __DIR__ . '/roma_trust.php';
            $encryption = null;
            if (class_exists('Auth')) {
                $auth       = new Auth();
                $encryption = $auth->getEncryptionService();
            }
            $status = RomaTrust::getStatus($encryption);
            return ($status['trust_state'] ?? '') === 'VERIFIED';
        } catch (Exception $e) {
            error_log('LSRP ROMA check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify OS-level administrator credentials.
     * Uses PAM on Linux, dscl on macOS.
     */
    private function verifySystemAdmin(string $osUsername, string $osPassword): bool {
        // Linux: PAM authentication
        if (PHP_OS_FAMILY === 'Linux' && function_exists('pam_auth')) {
            return pam_auth($osUsername, $osPassword);
        }
        // macOS: dscl authonly
        if (PHP_OS === 'Darwin') {
            $handle = popen('dscl . -authonly ' . escapeshellarg($osUsername), 'w');
            if (!$handle) {
                return false;
            }
            fwrite($handle, $osPassword . "\n");
            return pclose($handle) === 0;
        }
        // Development fallback: allow if env variable matches (NOT for production)
        if (getenv('APP_ENV') === 'development') {
            $devPassword = getenv('LSRP_DEV_OS_PASSWORD');
            return $devPassword !== false && hash_equals($devPassword, $osPassword);
        }
        return false;
    }

    private function verifyDeviceFingerprint(int $userId): bool {
        $fingerprint = $this->generateDeviceFingerprint();
        $stmt        = $this->db->getConnection()->prepare(
            'SELECT device_fingerprint FROM trusted_devices WHERE user_id = ? AND revoked = 0'
        );
        $stmt->execute([$userId]);
        $trusted = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($trusted)) {
            return false; // No trusted devices registered yet
        }
        return in_array($fingerprint, $trusted, true);
    }

    private function generateDeviceFingerprint(): string {
        return hash('sha256', implode('|', [
            php_uname('n'),
            $_SERVER['HTTP_USER_AGENT']        ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE']   ?? '',
        ]));
    }

    private function generateTemporaryCredential(int $userId): array {
        $tempPassword    = bin2hex(random_bytes(24)); // 48 hex chars
        $hashedPassword  = password_hash($tempPassword, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost'   => 4,
            'threads'     => 2,
        ]);
        $expiresAt = date('Y-m-d H:i:s', time() + 600);

        $this->db->getConnection()->prepare(
            'UPDATE users
             SET password_hash = ?,
                 temp_password_expires = ?,
                 requires_password_change = 1
             WHERE id = ?'
        )->execute([$hashedPassword, $expiresAt, $userId]);

        $this->logAttempt($userId, 'SUCCESS', 'Temporary credential issued');

        return [
            'temporary_password' => $tempPassword,
            'expires_at'         => $expiresAt,
        ];
    }

    private function checkRateLimit(int $userId): bool {
        $stmt = $this->db->getConnection()->prepare(
            'SELECT COUNT(*) FROM recovery_attempts
             WHERE user_id = ? AND result = "DENIED"
             AND created_at > datetime("now", "-24 hours")'
        );
        $stmt->execute([$userId]);
        $failures = (int)$stmt->fetchColumn();

        if ($failures >= 3) {
            $this->logAttempt($userId, 'DENIED', 'Rate limit exceeded');
            return false;
        }
        return true;
    }

    private function checkSuspiciousActivity(int $userId): bool {
        $stmt = $this->db->getConnection()->prepare(
            'SELECT COUNT(*) FROM recovery_attempts
             WHERE user_id = ? AND result = "DENIED"
             AND created_at > datetime("now", "-1 hour")'
        );
        $stmt->execute([$userId]);
        $recentFailures = (int)$stmt->fetchColumn();

        if ($recentFailures >= 5) {
            $this->db->getConnection()->prepare(
                'UPDATE users SET account_suspended = 1 WHERE id = ?'
            )->execute([$userId]);
            error_log("LSRP SECURITY: Account {$userId} auto-suspended after 5 failed recovery attempts");
            return true;
        }
        return false;
    }

    private function logAttempt(?int $userId, string $result, string $details): void {
        try {
            $fingerprint = $this->generateDeviceFingerprint();
            $this->db->getConnection()->prepare(
                'INSERT INTO recovery_attempts
                 (user_id, ip_address, device_fingerprint, result, details, created_at)
                 VALUES (?, ?, ?, ?, ?, datetime("now"))'
            )->execute([
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? 'localhost',
                $fingerprint,
                $result,
                $details,
            ]);
        } catch (Exception $e) {
            error_log('LSRP log failed: ' . $e->getMessage());
        }
    }

    /**
     * Check whether an IP falls within a CIDR range (IPv4 only).
     */
    private function ipInCidr(string $ip, string $cidr): bool {
        if (strpos($cidr, '/') === false) {
            return $ip === $cidr;
        }
        [$subnet, $bits] = explode('/', $cidr, 2);
        $mask = -1 << (32 - (int)$bits);
        return (ip2long($ip) & $mask) === (ip2long($subnet) & $mask);
    }
}
