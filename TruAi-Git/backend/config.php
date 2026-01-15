<?php
/**
 * TruAi Server Configuration
 * 
 * Core configuration for the TruAi HTML server version
 * 
 * @package TruAi
 * @version 1.0.0
 * @copyright My Deme, LLC © 2026
 */

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 for development
ini_set('log_errors', 1);

// Application constants
define('APP_NAME', 'Tru.ai');
define('APP_VERSION', '1.0.0');
define('APP_ENV', getenv('APP_ENV') ?: 'production');

// Paths
define('BASE_PATH', dirname(__DIR__));
define('BACKEND_PATH', BASE_PATH . '/backend');
define('DATABASE_PATH', BASE_PATH . '/database');
define('LOGS_PATH', BASE_PATH . '/logs');

// Database configuration
define('DB_TYPE', 'sqlite');
define('DB_PATH', DATABASE_PATH . '/truai.db');

// Security
define('SESSION_LIFETIME', 3600); // 1 hour
define('CSRF_TOKEN_NAME', 'csrf_token');
define('ALLOWED_HOSTS', ['localhost', '127.0.0.1', '::1']);

// AI Service Configuration
define('TRUAI_API_ENDPOINT', getenv('TRUAI_API_ENDPOINT') ?: 'https://api.truai.example.com');
define('TRUAI_API_KEY', getenv('TRUAI_API_KEY') ?: '');
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');
define('ANTHROPIC_API_KEY', getenv('ANTHROPIC_API_KEY') ?: '');

// AI Model tiers
define('AI_TIER_CHEAP', 'cheap');
define('AI_TIER_MID', 'mid');
define('AI_TIER_HIGH', 'high');

// Risk levels
define('RISK_LOW', 'LOW');
define('RISK_MEDIUM', 'MEDIUM');
define('RISK_HIGH', 'HIGH');

// Execution modes
define('EXEC_MODE_AUTO', 'auto');
define('EXEC_MODE_MANUAL', 'manual');

// CORS configuration for API
define('CORS_ENABLED', true);
define('CORS_ORIGIN', '*'); // Change to specific domain in production

// Create necessary directories
$directories = [LOGS_PATH, DATABASE_PATH];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Timezone
date_default_timezone_set('UTC');

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_name('TRUAI_SESSION');
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => (APP_ENV === 'production'),
        'cookie_samesite' => 'Lax',
        'gc_maxlifetime' => SESSION_LIFETIME
    ]);
}
