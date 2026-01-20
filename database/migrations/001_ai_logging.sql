-- AI Request/Response Logging Migration
-- Created: 2026-01-20
-- Purpose: Add tables for AI request logging, error tracking, and API usage metrics

-- AI Request/Response Logging
CREATE TABLE IF NOT EXISTS ai_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id TEXT,
    user_id INTEGER,
    provider TEXT, -- 'openai' or 'anthropic'
    model TEXT,
    prompt TEXT,
    response TEXT,
    tokens_used INTEGER,
    latency_ms INTEGER,
    success BOOLEAN DEFAULT 1,
    error_message TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Error Tracking
CREATE TABLE IF NOT EXISTS error_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    error_type TEXT,
    error_message TEXT,
    stack_trace TEXT,
    user_id INTEGER,
    request_path TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- API Usage Metrics (aggregated daily)
CREATE TABLE IF NOT EXISTS api_metrics (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    date DATE NOT NULL,
    provider TEXT NOT NULL,
    model TEXT NOT NULL,
    requests_count INTEGER DEFAULT 0,
    tokens_total INTEGER DEFAULT 0,
    errors_count INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(date, provider, model)
);

-- Migration Tracking
CREATE TABLE IF NOT EXISTS migrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    migration_name TEXT UNIQUE NOT NULL,
    applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_ai_requests_user ON ai_requests(user_id);
CREATE INDEX IF NOT EXISTS idx_ai_requests_task ON ai_requests(task_id);
CREATE INDEX IF NOT EXISTS idx_ai_requests_created ON ai_requests(created_at);
CREATE INDEX IF NOT EXISTS idx_error_logs_user ON error_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_error_logs_created ON error_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_api_metrics_date ON api_metrics(date);

-- Record this migration
INSERT OR IGNORE INTO migrations (migration_name) VALUES ('001_ai_logging');
