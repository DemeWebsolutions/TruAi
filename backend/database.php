<?php
/**
 * TruAi Database Layer
 * 
 * Handles all database operations using SQLite
 * 
 * @package TruAi
 * @version 1.0.0
 */

class Database {
    private static $instance = null;
    private $connection = null;

    private function __construct() {
        try {
            // Ensure database directory exists (git doesn't track empty directories)
            $dbDir = dirname(DB_PATH);
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }
            
            $this->connection = new PDO('sqlite:' . DB_PATH);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->initializeSchema();
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    private function initializeSchema() {
        $schema = "
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT DEFAULT 'SUPER_ADMIN',
            api_key TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME
        );

        CREATE TABLE IF NOT EXISTS conversations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            conversation_id INTEGER NOT NULL,
            role TEXT NOT NULL,
            content TEXT NOT NULL,
            model_used TEXT,
            tokens_used INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (conversation_id) REFERENCES conversations(id)
        );

        CREATE TABLE IF NOT EXISTS tasks (
            id TEXT PRIMARY KEY,
            user_id INTEGER NOT NULL,
            prompt TEXT NOT NULL,
            risk_level TEXT NOT NULL,
            tier TEXT NOT NULL,
            status TEXT DEFAULT 'CREATED',
            context TEXT,
            strategic_context TEXT, -- JSON: execution_bias, approach, suppress_exploration, dependencies, roi_assessment, scope_creep_risk, long_term_cost
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS executions (
            id TEXT PRIMARY KEY,
            task_id TEXT NOT NULL,
            model TEXT NOT NULL,
            output_artifact TEXT,
            status TEXT DEFAULT 'PENDING',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (task_id) REFERENCES tasks(id)
        );

        CREATE TABLE IF NOT EXISTS artifacts (
            id TEXT PRIMARY KEY,
            task_id TEXT NOT NULL,
            type TEXT NOT NULL,
            path TEXT,
            content TEXT,
            checksum TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (task_id) REFERENCES tasks(id)
        );

        CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            event TEXT NOT NULL,
            actor TEXT NOT NULL,
            details TEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            category TEXT NOT NULL,
            key TEXT NOT NULL,
            value TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            UNIQUE(user_id, category, key)
        );

        CREATE TABLE IF NOT EXISTS itc_systems (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            system_id TEXT UNIQUE NOT NULL,
            public_key TEXT NOT NULL,
            trust_status TEXT DEFAULT 'active' CHECK(trust_status IN ('active', 'revoked')),
            revoked_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS itc_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id TEXT UNIQUE NOT NULL,
            system_id TEXT NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (system_id) REFERENCES itc_systems(system_id)
        );

        -- LSRP: Recovery Attempts Audit Log
        CREATE TABLE IF NOT EXISTS recovery_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            ip_address TEXT NOT NULL,
            device_fingerprint TEXT,
            result TEXT NOT NULL,
            details TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );

        -- LSRP: Trusted Devices
        CREATE TABLE IF NOT EXISTS trusted_devices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            device_fingerprint TEXT NOT NULL,
            device_name TEXT,
            first_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            revoked INTEGER DEFAULT 0,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );

        -- LSRP: Master Recovery Keys (hashed)
        CREATE TABLE IF NOT EXISTS master_recovery_keys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL UNIQUE,
            key_hash TEXT NOT NULL,
            issued_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_used DATETIME,
            use_count INTEGER DEFAULT 0,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );

        -- UBSAS: Biometric Login Audit
        CREATE TABLE IF NOT EXISTS biometric_logins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            ip_address TEXT,
            user_agent TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );

        CREATE INDEX IF NOT EXISTS idx_recovery_attempts_user ON recovery_attempts(user_id, created_at);
        CREATE INDEX IF NOT EXISTS idx_recovery_attempts_result ON recovery_attempts(result, created_at);
        CREATE INDEX IF NOT EXISTS idx_trusted_devices_user ON trusted_devices(user_id);
        CREATE INDEX IF NOT EXISTS idx_biometric_logins_user ON biometric_logins(user_id, created_at);

        CREATE INDEX IF NOT EXISTS idx_conversations_user ON conversations(user_id);
        CREATE INDEX IF NOT EXISTS idx_messages_conversation ON messages(conversation_id);
        CREATE INDEX IF NOT EXISTS idx_tasks_user ON tasks(user_id);
        CREATE INDEX IF NOT EXISTS idx_executions_task ON executions(task_id);
        CREATE INDEX IF NOT EXISTS idx_artifacts_task ON artifacts(task_id);
        CREATE INDEX IF NOT EXISTS idx_audit_logs_user ON audit_logs(user_id);
        CREATE INDEX IF NOT EXISTS idx_settings_user ON settings(user_id);
        CREATE INDEX IF NOT EXISTS idx_itc_systems_id ON itc_systems(system_id);
        CREATE INDEX IF NOT EXISTS idx_itc_sessions_id ON itc_sessions(session_id);
        CREATE INDEX IF NOT EXISTS idx_itc_sessions_expires ON itc_sessions(expires_at);
        ";

        $this->connection->exec($schema);
        $this->migrateSchema();
        $this->createDefaultUser();
    }

    /**
     * Apply incremental schema migrations (idempotent).
     * SQLite does not support ADD COLUMN IF NOT EXISTS, so we catch errors.
     */
    private function migrateSchema() {
        $migrations = [
            "ALTER TABLE users ADD COLUMN temp_password_expires DATETIME",
            "ALTER TABLE users ADD COLUMN requires_password_change INTEGER DEFAULT 0",
            "ALTER TABLE users ADD COLUMN account_suspended INTEGER DEFAULT 0",
            "ALTER TABLE users ADD COLUMN hash_algorithm TEXT DEFAULT 'bcrypt'",
        ];
        foreach ($migrations as $sql) {
            try {
                $this->connection->exec($sql);
            } catch (PDOException $e) {
                // Column already exists — silently skip
            }
        }
    }

    private function createDefaultUser() {
        $stmt = $this->connection->prepare("SELECT COUNT(*) as count FROM users");
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            // Secure default: use env or generate crypto-random password
            $initialPassword = getenv('TRUAI_INITIAL_PASSWORD');
            if ($initialPassword === false || $initialPassword === '') {
                $initialPassword = bin2hex(random_bytes(12)); // 24 alphanumeric chars
            }
            
            $defaultPassword = password_hash($initialPassword, PASSWORD_DEFAULT);
            $apiKey = bin2hex(random_bytes(32));
            
            $stmt = $this->connection->prepare(
                "INSERT INTO users (username, password_hash, role, api_key) 
                 VALUES (:username, :password, :role, :api_key)"
            );
            $stmt->execute([
                ':username' => 'Deme',
                ':password' => $defaultPassword,
                ':role' => 'SUPER_ADMIN',
                ':api_key' => $apiKey
            ]);
            
            $this->writeInitialCredentials('Deme', $initialPassword);
            error_log('Default user created. Username: Deme. One-time credentials written to database/.initial_credentials — change password on first login.');
        }
    }

    /**
     * Write one-time initial credentials to secure file (ROMA-aligned).
     * Caller must change password on first login.
     */
    private function writeInitialCredentials($username, $password) {
        $file = DATABASE_PATH . '/.initial_credentials';
        $content = json_encode([
            'username' => $username,
            'password' => $password,
            'created' => date('c'),
            'warning' => 'ONE-TIME USE. Change password immediately. Delete this file after first login.'
        ], JSON_PRETTY_PRINT);
        if (file_put_contents($file, $content) !== false) {
            @chmod($file, 0600);
        }
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Query error: ' . $e->getMessage());
            throw new Exception('Database query failed');
        }
    }

    public function execute($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log('Execute error: ' . $e->getMessage());
            throw new Exception('Database execution failed');
        }
    }

    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
}
