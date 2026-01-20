-- Learning System Database Schema
-- 
-- This schema adds persistent learning capabilities to TruAi
-- Allows the system to learn from user interactions and improve over time

-- Learning Events Table
-- Records all learning events (corrections, preferences, successes, failures)
CREATE TABLE IF NOT EXISTS learning_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    event_type TEXT NOT NULL CHECK(event_type IN ('correction', 'preference', 'success', 'failure', 'feedback')),
    context TEXT, -- JSON with task context (prompt, model, tier, etc.)
    original_prompt TEXT,
    original_response TEXT,
    corrected_response TEXT,
    feedback_score INTEGER CHECK(feedback_score IN (-1, 0, 1)), -- -1 (negative), 0 (neutral), 1 (positive)
    model_used TEXT,
    risk_level TEXT,
    tier TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Learned Patterns Table
-- Stores patterns learned from user behavior and feedback
CREATE TABLE IF NOT EXISTS learned_patterns (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    pattern_type TEXT NOT NULL CHECK(pattern_type IN ('prompt_template', 'context_preference', 'model_preference', 'correction_pattern')),
    pattern_key TEXT NOT NULL, -- Unique identifier for the pattern
    pattern_value TEXT, -- JSON data for the pattern
    confidence_score REAL DEFAULT 0.5 CHECK(confidence_score >= 0.0 AND confidence_score <= 1.0),
    usage_count INTEGER DEFAULT 0,
    success_rate REAL DEFAULT 0.0 CHECK(success_rate >= 0.0 AND success_rate <= 1.0),
    last_used DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, pattern_type, pattern_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_learning_user ON learning_events(user_id);
CREATE INDEX IF NOT EXISTS idx_learning_type ON learning_events(event_type);
CREATE INDEX IF NOT EXISTS idx_learning_date ON learning_events(created_at);
CREATE INDEX IF NOT EXISTS idx_learning_user_date ON learning_events(user_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_patterns_user ON learned_patterns(user_id);
CREATE INDEX IF NOT EXISTS idx_patterns_type ON learned_patterns(pattern_type);
CREATE INDEX IF NOT EXISTS idx_patterns_score ON learned_patterns(confidence_score DESC);
CREATE INDEX IF NOT EXISTS idx_patterns_user_type ON learned_patterns(user_id, pattern_type);
CREATE INDEX IF NOT EXISTS idx_patterns_success ON learned_patterns(success_rate DESC);
