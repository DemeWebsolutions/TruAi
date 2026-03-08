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
     * Attempt biometric/keychain authentication and return stored credentials for the app.
     * Tries to retrieve credentials from OS keychain (and config). On macOS, keychain
     * access may have been unlocked by Touch ID at login; we do not require a recent
     * biometric log entry so login works when credentials are stored and keychain is
     * available.
     * Returns null when no credentials are stored or keychain is unavailable.
     */
    public function biometricAutoLogin(string $app): ?array {
        $credentials = $this->retrieveCredentialsFromKeychain($app);
        if ($credentials !== null) {
            return $credentials;
        }
        // Optional: require recent biometric unlock for extra assurance (e.g. high-security mode).
        // Disabled by default so login works with keychain-only storage.
        return null;
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
            // 1. bioutil -r (requires no SIP bypass, may be empty on some configs)
            $output = shell_exec('bioutil -r 2>&1') ?? '';
            if (strpos($output, 'Touch ID') !== false || strpos($output, 'Face ID') !== false) {
                return true;
            }
            // 2. system_profiler — works even when bioutil is restricted
            $spOut = shell_exec('system_profiler SPiBridgeDataType 2>&1') ?? '';
            if (strpos($spOut, 'Touch ID') !== false || strpos($spOut, 'Face ID') !== false) {
                return true;
            }
            // 3. ioreg — detect Apple T2 / Secure Enclave (biometric-capable hardware)
            $ioreg = shell_exec('ioreg -c AppleCredentialManager 2>/dev/null') ?? '';
            if (strpos($ioreg, 'AppleCredentialManager') !== false) {
                return true;
            }
            // 4. system_profiler for Touch ID status on T2 Macs
            $sp2 = shell_exec('system_profiler SPSecureElementDataType 2>&1') ?? '';
            if (strpos($sp2, 'Biometric') !== false || strpos($sp2, 'Touch') !== false) {
                return true;
            }
            return false;
        }
        if (PHP_OS === 'Linux') {
            $out = shell_exec('which fprintd 2>/dev/null') ?? '';
            if (!empty(trim($out))) return true;
            // Also check fprintd service
            $svc = shell_exec('systemctl is-active fprintd 2>/dev/null') ?? '';
            return trim($svc) === 'active';
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
        $home       = $_SERVER['HOME'] ?? (function_exists('posix_getpwuid')
                        ? (posix_getpwuid(posix_getuid())['dir'] ?? '/tmp')
                        : '/tmp');
        $configFile = $home . '/.demewebsolutions/config.json';
        if (!file_exists($configFile)) {
            return null;
        }
        $config   = json_decode(file_get_contents($configFile), true);
        $username = $config['apps'][$app]['username'] ?? null;
        if (!$username) {
            return null;
        }

        // Tier 1: try the encrypted credential stored directly in config.json
        // (most reliable for web-server contexts where keychain ACLs block shell_exec)
        $encPass = $config['apps'][$app]['credential'] ?? null;
        if ($encPass) {
            $password = $this->decryptLocal($encPass);
            if ($password !== null) {
                return ['username' => $username, 'password' => $password, 'app' => $app];
            }
        }

        // Tier 2: macOS keychain (works when the server process has keychain access)
        if (PHP_OS === 'Darwin') {
            $result = $this->retrieveMacOSKeychain($app, $username);
            if ($result !== null) {
                return $result;
            }
        }

        // Tier 3: Linux secret-service
        if (PHP_OS === 'Linux') {
            $result = $this->retrieveLinuxKeyring($app, $username);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Derive a local encryption key from stable machine identifiers.
     * The key never leaves this machine, making it safe to store credentials
     * encrypted in config.json for web-server access.
     */
    private function localEncryptionKey(): string {
        $hostname = gethostname() ?: 'localhost';
        $uid      = function_exists('posix_getuid') ? (string)posix_getuid() : '0';
        return hash('sha256', 'UBSAS-LOCAL-KEY|' . $hostname . '|' . $uid, true);
    }

    private function encryptLocal(string $plaintext): string {
        $key   = $this->localEncryptionKey();
        $iv    = random_bytes(16);
        $enc   = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $enc);
    }

    private function decryptLocal(string $encoded): ?string {
        try {
            $key  = $this->localEncryptionKey();
            $raw  = base64_decode($encoded, true);
            if ($raw === false || strlen($raw) < 17) {
                return null;
            }
            $iv  = substr($raw, 0, 16);
            $enc = substr($raw, 16);
            $dec = openssl_decrypt($enc, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
            return $dec !== false ? $dec : null;
        } catch (Throwable $e) {
            return null;
        }
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
     * Store credentials in the OS keychain AND update the config.json index file.
     * Called automatically on every successful password login so that biometric
     * login can retrieve them without the user needing a separate setup step.
     */
    public function storeCredentials(string $app, string $username, string $password): bool {
        // Always write the config index file with the encrypted credential so
        // retrieveCredentialsFromKeychain() works in both CLI and web-server contexts.
        $this->writeConfigIndex($app, $username, $password);

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

    /**
     * Write (or update) ~/.demewebsolutions/config.json with the username and
     * locally-encrypted credential for a given app.
     * The 'credential' field is AES-256-CBC encrypted with a machine-derived key
     * so the web server can retrieve it without relying on keychain ACLs.
     */
    private function writeConfigIndex(string $app, string $username, string $password = ''): void {
        $home       = $_SERVER['HOME'] ?? (function_exists('posix_getpwuid')
                        ? (posix_getpwuid(posix_getuid())['dir'] ?? '/tmp')
                        : '/tmp');
        $dir        = $home . '/.demewebsolutions';
        $configFile = $dir . '/config.json';

        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        $config = [];
        if (file_exists($configFile)) {
            $existing = @json_decode(file_get_contents($configFile), true);
            if (is_array($existing)) {
                $config = $existing;
            }
        }

        $config['apps'][$app]['username']   = $username;
        $config['apps'][$app]['updated_at'] = date('c');
        if ($password !== '') {
            $config['apps'][$app]['credential'] = $this->encryptLocal($password);
        }

        @file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        @chmod($configFile, 0600);
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
