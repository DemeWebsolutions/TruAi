-- TruAi Complete Database Schema
-- Version: 1.0.0
-- Date: 2026-02-21

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    email TEXT,
    role TEXT DEFAULT 'user' CHECK (role IN ('admin', 'user', 'SUPER_ADMIN')),
    account_suspended INTEGER DEFAULT 0,
    requires_password_change INTEGER DEFAULT 0,
    temp_password_expires DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME
);

CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

-- Audit logs
CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    event TEXT NOT NULL,
    actor TEXT,
    details TEXT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_audit_user_time ON audit_logs(user_id, timestamp);
CREATE INDEX IF NOT EXISTS idx_audit_event ON audit_logs(event);

-- LSRP Recovery attempts
CREATE TABLE IF NOT EXISTS recovery_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    ip_address TEXT NOT NULL,
    device_fingerprint TEXT,
    result TEXT NOT NULL CHECK (result IN ('SUCCESS', 'DENIED', 'WARNING')),
    details TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_recovery_user_time ON recovery_attempts(user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_recovery_result ON recovery_attempts(result, created_at);

-- LSRP Trusted devices
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

CREATE INDEX IF NOT EXISTS idx_trusted_user ON trusted_devices(user_id, revoked);

-- LSRP Master recovery keys (hashed)
CREATE TABLE IF NOT EXISTS master_recovery_keys (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER UNIQUE NOT NULL,
    key_hash TEXT NOT NULL,
    issued_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_used DATETIME,
    use_count INTEGER DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- UBSAS Biometric logins
CREATE TABLE IF NOT EXISTS biometric_logins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    ip_address TEXT,
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_biometric_user ON biometric_logins(user_id, created_at);

-- UBSAS Devices (for device fingerprinting)
CREATE TABLE IF NOT EXISTS ubsas_devices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    device_type TEXT CHECK (device_type IN ('biometric', 'manual', 'keychain')),
    device_fingerprint TEXT,
    device_info TEXT,
    trusted INTEGER DEFAULT 0,
    last_used DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- UBSAS Authentication challenges (for rate limiting)
CREATE TABLE IF NOT EXISTS ubsas_challenges (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    challenge_type TEXT,
    attempts INTEGER DEFAULT 0,
    last_attempt DATETIME,
    window_start DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Chat conversations
CREATE TABLE IF NOT EXISTS conversations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_conversations_user ON conversations(user_id, updated_at);

-- Chat messages
CREATE TABLE IF NOT EXISTS messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    conversation_id INTEGER NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('user', 'assistant', 'system')),
    content TEXT NOT NULL,
    model TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_messages_conversation ON messages(conversation_id, created_at);

-- Learning events (AI feedback)
CREATE TABLE IF NOT EXISTS learning_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    event_type TEXT CHECK (event_type IN ('correction', 'preference', 'success', 'failure', 'feedback')),
    context TEXT,
    original_prompt TEXT,
    original_response TEXT,
    corrected_response TEXT,
    feedback_score INTEGER,
    model_used TEXT,
    risk_level TEXT,
    tier TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_learning_user ON learning_events(user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_learning_type ON learning_events(event_type);

-- Learning patterns (extracted from events)
CREATE TABLE IF NOT EXISTS learning_patterns (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    pattern_type TEXT,
    pattern TEXT NOT NULL,
    confidence REAL DEFAULT 0.5,
    usage_count INTEGER DEFAULT 0,
    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_patterns_user ON learning_patterns(user_id, confidence);

-- Settings (key-value store)
CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    key TEXT NOT NULL,
    value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE(user_id, key)
);

CREATE INDEX IF NOT EXISTS idx_settings_user_key ON settings(user_id, key);

-- Gemini.ai automation logs
CREATE TABLE IF NOT EXISTS gemini_automation_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT NOT NULL,
    success INTEGER DEFAULT 0,
    result TEXT,
    execution_time_ms INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_gemini_user ON gemini_automation_logs(user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_gemini_action ON gemini_automation_logs(action);

-- ROMA ITC Systems (inter-system trust)
CREATE TABLE IF NOT EXISTS itc_systems (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    system_id TEXT UNIQUE NOT NULL,
    public_key TEXT NOT NULL,
    trust_status TEXT DEFAULT 'active' CHECK (trust_status IN ('active', 'revoked')),
    revoked_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ROMA ITC Sessions (temporary session keys)
CREATE TABLE IF NOT EXISTS itc_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id TEXT UNIQUE NOT NULL,
    system_id TEXT NOT NULL,
    session_key TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (system_id) REFERENCES itc_systems(system_id)
);

CREATE INDEX IF NOT EXISTS idx_itc_sessions_system ON itc_sessions(system_id);
CREATE INDEX IF NOT EXISTS idx_itc_sessions_expires ON itc_sessions(expires_at);

-- Security events (separate from audit for high-priority)
CREATE TABLE IF NOT EXISTS security_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_type TEXT NOT NULL,
    severity TEXT CHECK (severity IN ('low', 'medium', 'high', 'critical')),
    source TEXT,
    details TEXT,
    resolved INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_security_severity ON security_events(severity, resolved, created_at);

-- File operations (for future file management)
CREATE TABLE IF NOT EXISTS file_operations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    operation TEXT CHECK (operation IN ('upload', 'download', 'delete', 'modify')),
    file_path TEXT NOT NULL,
    file_size INTEGER,
    mime_type TEXT,
    success INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_file_ops_user ON file_operations(user_id, created_at);
