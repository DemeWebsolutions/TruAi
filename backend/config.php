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
// NOTE: Cannot use '*' with credentials. Must specify exact origin.
define('CORS_ENABLED', true);
// For localhost development, allow both http://localhost:8080 and http://127.0.0.1:8080
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = ['http://localhost:8080', 'http://127.0.0.1:8080', 'http://localhost', 'http://127.0.0.1'];
define('CORS_ORIGIN', in_array($requestOrigin, $allowedOrigins) ? $requestOrigin : 'http://localhost:8080');

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
// NOTE: Session will be started in router.php before Auth instantiation
// This config ensures it's also started if accessed directly
if (session_status() === PHP_SESSION_NONE) {
    session_name('TRUAI_SESSION');
    // Set cookie parameters before starting session
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => (APP_ENV === 'production'),  // HTTPS only in production
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start([
        'gc_maxlifetime' => SESSION_LIFETIME
    ]);
}
