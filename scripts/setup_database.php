#!/usr/bin/env php
<?php
/**
 * TruAi Database Setup Script
 *
 * Initializes database with schema, default admin user, and encryption keys
 * Safe to run multiple times (idempotent)
 *
 * Usage: php scripts/setup_database.php
 *
 * @package TruAi
 * @version 1.0.0
 * @copyright My Deme, LLC © 2026
 */

// Load configuration
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/database.php';

class DatabaseSetup {
    private $db;
    private $verbose = true;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    private function log(string $message, string $type = 'info'): void {
        if (!$this->verbose) return;

        $icons = [
            'info'    => 'i',
            'success' => '[OK]',
            'warning' => '[WARN]',
            'error'   => '[ERR]'
        ];

        $icon = $icons[$type] ?? 'i';
        echo "$icon $message\n";
    }

    /**
     * Run all setup steps
     */
    public function run(): bool {
        $this->log("Starting TruAi database setup...", 'info');

        try {
            $this->createDirectories();
            $this->runMigrations();
            $this->generateEncryptionKeys();
            $this->createDefaultAdmin();
            $this->verifySetup();

            $this->log("\n=== Setup Complete ===", 'success');
            $this->printNextSteps();

            return true;
        } catch (Exception $e) {
            $this->log("Setup failed: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Create necessary directories
     */
    private function createDirectories(): void {
        $dirs = [
            DATABASE_PATH,
            DATABASE_PATH . '/keys',
            LOGS_PATH,
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0700, true);
                $this->log("Created directory: $dir", 'success');
            }
        }
    }

    /**
     * Run database migrations
     */
    private function runMigrations(): void {
        $this->log("Running database migrations...", 'info');

        $migrationFile = __DIR__ . '/../database/migrations/001_initial_schema.sql';

        if (file_exists($migrationFile)) {
            $sql = file_get_contents($migrationFile);

            // Remove single-line SQL comments before splitting
            $sql = preg_replace('/--[^\n]*/', '', $sql);

            // Split by semicolon and execute each statement
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                fn($s) => !empty($s)
            );

            foreach ($statements as $statement) {
                try {
                    $this->db->getConnection()->exec($statement);
                } catch (PDOException $e) {
                    // Ignore "table already exists" errors
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        throw $e;
                    }
                }
            }

            $this->log("Database schema initialized", 'success');
        } else {
            // Fallback: create basic schema inline
            $this->createBasicSchema();
        }
    }

    /**
     * Create basic schema if migration file doesn't exist
     */
    private function createBasicSchema(): void {
        $schema = "
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT DEFAULT 'user',
            account_suspended INTEGER DEFAULT 0,
            requires_password_change INTEGER DEFAULT 0,
            temp_password_expires DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME
        );

        CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            event TEXT NOT NULL,
            actor TEXT,
            details TEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS recovery_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            ip_address TEXT,
            device_fingerprint TEXT,
            result TEXT NOT NULL,
            details TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS master_recovery_keys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER UNIQUE NOT NULL,
            key_hash TEXT NOT NULL,
            issued_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_used DATETIME,
            use_count INTEGER DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS biometric_logins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            ip_address TEXT,
            user_agent TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS conversations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            conversation_id INTEGER NOT NULL,
            role TEXT NOT NULL,
            content TEXT NOT NULL,
            model TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            key TEXT NOT NULL,
            value TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        ";

        $this->db->getConnection()->exec($schema);
        $this->log("Basic schema created", 'success');
    }

    /**
     * Generate RSA encryption keys for ROMA
     */
    private function generateEncryptionKeys(): void {
        $keyDir = DATABASE_PATH . '/keys';
        $privateKeyPath = $keyDir . '/private_key.pem';
        $publicKeyPath = $keyDir . '/public_key.pem';

        if (file_exists($privateKeyPath) && file_exists($publicKeyPath)) {
            $this->log("Encryption keys already exist", 'info');
            return;
        }

        $this->log("Generating RSA-2048 encryption keys...", 'info');

        $config = [
            'digest_alg'       => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $privateKey = openssl_pkey_new($config);
        if (!$privateKey) {
            throw new Exception('Failed to generate private key');
        }

        openssl_pkey_export($privateKey, $privateKeyPem);
        file_put_contents($privateKeyPath, $privateKeyPem);
        chmod($privateKeyPath, 0600);

        $publicKeyDetails = openssl_pkey_get_details($privateKey);
        file_put_contents($publicKeyPath, $publicKeyDetails['key']);
        chmod($publicKeyPath, 0644);

        $this->log("Encryption keys generated: $keyDir", 'success');
    }

    /**
     * Create default admin user if doesn't exist
     */
    private function createDefaultAdmin(): void {
        $result = $this->db->query(
            "SELECT id FROM users WHERE username = :username LIMIT 1",
            [':username' => 'admin']
        );

        if (!empty($result)) {
            $this->log("Admin user already exists", 'info');
            return;
        }

        $this->log("Creating default admin user...", 'info');

        $password = $this->generateSecurePassword();
        $passwordHash = password_hash($password, PASSWORD_ARGON2ID, ARGON2ID_OPTIONS);

        $this->db->execute(
            "INSERT INTO users (username, password_hash, role, created_at) VALUES (:username, :hash, 'admin', datetime('now'))",
            [
                ':username' => 'admin',
                ':hash'     => $passwordHash
            ]
        );

        $credentialsFile = DATABASE_PATH . '/.initial_credentials';
        $credentials = json_encode([
            'username'   => 'admin',
            'password'   => $password,
            'created_at' => date('c')
        ], JSON_PRETTY_PRINT);

        file_put_contents($credentialsFile, $credentials);
        chmod($credentialsFile, 0600);

        $this->log("Admin user created: admin", 'success');
        $this->log("Credentials written: $credentialsFile (chmod 600)", 'success');
    }

    /**
     * Generate secure random password
     */
    private function generateSecurePassword(): string {
        $length = 16;
        $chars  = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $password;
    }

    /**
     * Verify setup completed successfully
     */
    private function verifySetup(): void {
        $this->log("\nVerifying setup...", 'info');

        $dbPath = DB_PATH;
        if (!file_exists($dbPath)) {
            throw new Exception("Database file not created: $dbPath");
        }
        if (!is_writable($dbPath)) {
            throw new Exception("Database file not writable: $dbPath");
        }
        $this->log("[OK] Database file exists and is writable", 'success');

        $keyDir = DATABASE_PATH . '/keys';
        if (!file_exists($keyDir . '/private_key.pem') || !file_exists($keyDir . '/public_key.pem')) {
            throw new Exception("Encryption keys not found in $keyDir");
        }
        $this->log("[OK] Encryption keys exist", 'success');

        $result = $this->db->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
        if (empty($result)) {
            throw new Exception("Admin user not created");
        }
        $this->log("[OK] Admin user exists", 'success');
    }

    /**
     * Print next steps for user
     */
    private function printNextSteps(): void {
        $credentialsFile = DATABASE_PATH . '/.initial_credentials';

        echo "\n";
        echo "+----------------------------------------------------------+\n";
        echo "|              Next Steps                                  |\n";
        echo "+----------------------------------------------------------+\n";
        echo "\n";
        echo "1. Start the server:\n";
        echo "   ./start.sh\n";
        echo "\n";
        echo "2. Get your credentials:\n";
        echo "   cat $credentialsFile\n";
        echo "\n";
        echo "3. Login:\n";
        echo "   http://127.0.0.1:8001/TruAi/login-portal.html\n";
        echo "\n";
        echo "4. IMPORTANT: Change admin password immediately\n";
        echo "   Go to: Settings -> Security -> Change Password\n";
        echo "\n";
        echo "5. Delete credentials file after first login:\n";
        echo "   rm $credentialsFile\n";
        echo "\n";
        echo "[WARN] WARNING:\n";
        echo "   - Keep database/.initial_credentials secure (chmod 600)\n";
        echo "   - Change the admin password on first login\n";
        echo "   - Delete .initial_credentials after setup\n";
        echo "\n";
    }
}

// Run setup
$setup = new DatabaseSetup();
exit($setup->run() ? 0 : 1);
