<?php
/**
 * Unified Biometric Sovereign Authentication System (UBSAS) v2.0
 *
 * Biometric-first authentication with intelligent fallback chain:
 *   Tier 1 → OS Biometric (Touch ID / Face ID / fprintd)
 *   Tier 2 → Auto-Fill from OS Keychain
 *   Tier 3 → Manual Password Entry
 *   Tier 4 → Master Recovery Key (last resort)
 *
 * @package TruAi
 * @copyright My Deme, LLC © 2026
 */

class UBSASAuthService {
    private const KEYCHAIN_SERVICE = 'com.demewebsolutions.auth';

    // Authentication tier constants
    public const TIER_BIOMETRIC  = 1;
    public const TIER_AUTOFILL   = 2;
    public const TIER_MANUAL     = 3;
    public const TIER_MASTERKEY  = 4;

    /**
     * Return the list of authentication methods available on this host.
     */
    public function getAvailableAuthMethods(): array {
        $methods = [];

        if ($this->isBiometricAvailable()) {
            $methods[] = [
                'tier'        => self::TIER_BIOMETRIC,
                'name'        => 'OS Biometric',
                'type'        => 'biometric',
                'description' => $this->getBiometricDescription(),
                'enabled'     => true,
            ];
        }

        if ($this->isKeychainAvailable()) {
            $methods[] = [
                'tier'        => self::TIER_AUTOFILL,
                'name'        => 'Auto-Fill from Keychain',
                'type'        => 'autofill',
                'description' => 'Retrieve stored credentials from OS keychain',
                'enabled'     => true,
            ];
        }

        // Manual entry is always available
        $methods[] = [
            'tier'        => self::TIER_MANUAL,
            'name'        => 'Manual Password Entry',
            'type'        => 'password',
            'description' => 'Enter username and password manually',
            'enabled'     => true,
        ];

        if ($this->isMasterKeyConfigured()) {
            $methods[] = [
                'tier'        => self::TIER_MASTERKEY,
                'name'        => 'Master Recovery Key',
                'type'        => 'masterkey',
                'description' => 'Use offline 256-bit recovery key (last resort)',
                'enabled'     => true,
            ];
        }

        return $methods;
    }

    /**
     * Attempt biometric authentication and return stored credentials for the app.
     * Returns null when biometric is unavailable or no credentials are stored.
     */
    public function biometricAutoLogin(string $app): ?array {
        if (!$this->detectBiometricUnlock()) {
            return null;
        }
        return $this->retrieveCredentialsFromKeychain($app);
    }

    /**
     * Retrieve credentials from the OS keychain (may prompt biometric).
     */
    public function autofillCredentials(string $app): ?array {
        return $this->retrieveCredentialsFromKeychain($app);
    }

    /**
     * Validate a master recovery key for the given username.
     * On success, generates a 10-minute temporary password and returns it.
     */
    public function validateMasterKey(string $username, string $masterKey): array {
        require_once __DIR__ . '/database.php';
        $db = Database::getInstance();

        $stmt = $db->getConnection()->prepare(
            'SELECT mk.id, mk.user_id, mk.key_hash, mk.use_count
             FROM master_recovery_keys mk
             JOIN users u ON u.id = mk.user_id
             WHERE u.username = ?'
        );
        $stmt->execute([$username]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            $this->logRecovery(null, 'DENIED', 'Master key: user not found or no key configured');
            return ['success' => false, 'error' => 'Invalid username or master key'];
        }

        // Rate-limit: max 3 uses per 24 hours
        if ((int)$record['use_count'] >= 3) {
            $this->logRecovery((int)$record['user_id'], 'DENIED', 'Master key rate-limit exceeded');
            return ['success' => false, 'error' => 'Master key usage limit reached (3 per 24 hours). Wait 24 hours.'];
        }

        $provided = hash('sha256', $masterKey);
        if (!hash_equals($record['key_hash'], $provided)) {
            $this->logRecovery((int)$record['user_id'], 'DENIED', 'Master key: invalid key provided');
            return ['success' => false, 'error' => 'Invalid master key'];
        }

        // Generate Argon2id temporary password (48 hex chars)
        $tempPassword = bin2hex(random_bytes(24));
        $hashedPassword = password_hash($tempPassword, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost'   => 4,
            'threads'     => 2,
        ]);

        $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes

        $db->getConnection()->prepare(
            'UPDATE users
             SET password_hash = ?,
                 temp_password_expires = ?,
                 requires_password_change = 1
             WHERE id = ?'
        )->execute([$hashedPassword, $expiresAt, $record['user_id']]);

        $db->getConnection()->prepare(
            'UPDATE master_recovery_keys
             SET use_count = use_count + 1, last_used = datetime("now")
             WHERE id = ?'
        )->execute([$record['id']]);

        $this->logRecovery((int)$record['user_id'], 'SUCCESS', 'Master key recovery: temporary password issued');

        return [
            'success'            => true,
            'temporary_password' => $tempPassword,
            'expires_at'         => $expiresAt,
            'must_change'        => true,
            'message'            => 'Temporary password valid for 10 minutes. Change immediately after login.',
        ];
    }

    /**
     * Log a biometric login event.
     */
    public function logBiometricLogin(int $userId): void {
        require_once __DIR__ . '/database.php';
        $db = Database::getInstance();
        $db->getConnection()->prepare(
            'INSERT INTO biometric_logins (user_id, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, datetime("now"))'
        )->execute([
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function isBiometricAvailable(): bool {
        if (PHP_OS === 'Darwin') {
            $output = shell_exec('bioutil -r 2>&1') ?? '';
            if (empty($output)) {
                $output = shell_exec('system_profiler SPiBridgeDataType 2>&1') ?? '';
            }
            return strpos($output, 'Touch ID') !== false || strpos($output, 'Face ID') !== false;
        }
        if (PHP_OS === 'Linux') {
            $out = shell_exec('which fprintd 2>/dev/null') ?? '';
            return !empty(trim($out));
        }
        return false;
    }

    private function getBiometricDescription(): string {
        if (PHP_OS === 'Darwin') {
            $output = shell_exec('system_profiler SPiBridgeDataType 2>&1') ?? '';
            if (strpos($output, 'Face ID') !== false) {
                return 'Face ID';
            }
            return 'Touch ID';
        }
        if (PHP_OS === 'Linux') {
            return 'Fingerprint (fprintd)';
        }
        return 'OS Biometric';
    }

    private function detectBiometricUnlock(): bool {
        if (PHP_OS === 'Darwin') {
            $cmd = "log show --predicate 'subsystem == \"com.apple.loginwindow\" AND eventMessage CONTAINS \"authenticated\"' --last 5m --style compact 2>/dev/null | tail -1";
            $out = shell_exec($cmd) ?? '';
            return strpos($out, 'touchid') !== false || strpos($out, 'opticid') !== false;
        }
        if (PHP_OS === 'Linux') {
            $out = shell_exec("journalctl -u fprintd --since '5 minutes ago' 2>/dev/null | grep -i 'verify-match' | tail -1") ?? '';
            return strpos($out, 'verify-match') !== false;
        }
        return false;
    }

    private function isKeychainAvailable(): bool {
        if (PHP_OS === 'Darwin') {
            return true; // macOS always has Keychain
        }
        if (PHP_OS === 'Linux') {
            $out = shell_exec('which secret-tool 2>/dev/null') ?? '';
            return !empty(trim($out));
        }
        return false;
    }

    private function isMasterKeyConfigured(): bool {
        try {
            require_once __DIR__ . '/database.php';
            $db = Database::getInstance();
            $stmt = $db->getConnection()->prepare('SELECT COUNT(*) FROM master_recovery_keys');
            $stmt->execute();
            return (int)$stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    private function retrieveCredentialsFromKeychain(string $app): ?array {
        $configFile = ($_SERVER['HOME'] ?? '/root') . '/.demewebsolutions/config.json';
        if (!file_exists($configFile)) {
            return null;
        }
        $config = json_decode(file_get_contents($configFile), true);
        $username = $config['apps'][$app]['username'] ?? null;
        if (!$username) {
            return null;
        }

        if (PHP_OS === 'Darwin') {
            return $this->retrieveMacOSKeychain($app, $username);
        }
        if (PHP_OS === 'Linux') {
            return $this->retrieveLinuxKeyring($app, $username);
        }
        return null;
    }

    private function retrieveMacOSKeychain(string $app, string $username): ?array {
        $service  = self::KEYCHAIN_SERVICE . '.' . $app;
        $cmd      = sprintf(
            'security find-generic-password -s %s -a %s -w 2>/dev/null',
            escapeshellarg($service),
            escapeshellarg($username)
        );
        $password = trim(shell_exec($cmd) ?? '');
        if (empty($password)) {
            return null;
        }
        return ['username' => $username, 'password' => $password, 'app' => $app];
    }

    private function retrieveLinuxKeyring(string $app, string $username): ?array {
        $service  = self::KEYCHAIN_SERVICE . '.' . $app;
        $cmd      = sprintf(
            'secret-tool lookup service %s username %s 2>/dev/null',
            escapeshellarg($service),
            escapeshellarg($username)
        );
        $password = trim(shell_exec($cmd) ?? '');
        if (empty($password)) {
            return null;
        }
        return ['username' => $username, 'password' => $password, 'app' => $app];
    }

    /**
     * Store credentials in the OS keychain (used by setup script).
     */
    public function storeCredentials(string $app, string $username, string $password): bool {
        if (PHP_OS === 'Darwin') {
            $service   = self::KEYCHAIN_SERVICE . '.' . $app;
            $deleteCmd = sprintf(
                'security delete-generic-password -s %s -a %s 2>/dev/null',
                escapeshellarg($service),
                escapeshellarg($username)
            );
            exec($deleteCmd);
            $addCmd = sprintf(
                'security add-generic-password -s %s -a %s -w %s -T "" -U',
                escapeshellarg($service),
                escapeshellarg($username),
                escapeshellarg($password)
            );
            exec($addCmd, $out, $rc);
            return $rc === 0;
        }
        if (PHP_OS === 'Linux') {
            $service = self::KEYCHAIN_SERVICE . '.' . $app;
            $cmd     = sprintf(
                'secret-tool store --label=%s service %s username %s',
                escapeshellarg("DemeWebsolutions $app"),
                escapeshellarg($service),
                escapeshellarg($username)
            );
            $handle = popen($cmd, 'w');
            if (!$handle) {
                return false;
            }
            fwrite($handle, $password);
            return pclose($handle) === 0;
        }
        return false;
    }

    private function logRecovery(?int $userId, string $result, string $details): void {
        try {
            require_once __DIR__ . '/database.php';
            $db = Database::getInstance();
            $fingerprint = hash('sha256', implode('|', [
                php_uname('n'),
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $_SERVER['REMOTE_ADDR'] ?? '',
            ]));
            $db->getConnection()->prepare(
                'INSERT INTO recovery_attempts (user_id, ip_address, device_fingerprint, result, details, created_at)
                 VALUES (?, ?, ?, ?, ?, datetime("now"))'
            )->execute([$userId, $_SERVER['REMOTE_ADDR'] ?? 'localhost', $fingerprint, $result, $details]);
        } catch (Exception $e) {
            error_log('UBSAS recovery log failed: ' . $e->getMessage());
        }
    }
}
