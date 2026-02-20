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

// Load .env from project root so default API keys are available (even when not started via start.sh)
$configDir = dirname(__DIR__);
$envFile = $configDir . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\"'");
            if ($name !== '') {
                putenv("$name=$value");
                $_ENV[$name] = $value;
            }
        }
    }
}

// Application constants
define('APP_NAME', 'Tru.ai');
define('APP_VERSION', '1.0.0');
define('APP_ENV', getenv('APP_ENV') ?: 'production');

// Paths
define('BASE_PATH', $configDir);
define('BACKEND_PATH', BASE_PATH . '/backend');
define('DATABASE_PATH', BASE_PATH . '/database');
define('LOGS_PATH', BASE_PATH . '/logs');

// Database configuration
define('DB_TYPE', 'sqlite');
define('DB_PATH', DATABASE_PATH . '/truai.db');

// Security
define('SESSION_LIFETIME', 3600); // 1 hour
define('CSRF_TOKEN_NAME', 'csrf_token');
define('TRUAI_DEPLOYMENT', getenv('TRUAI_DEPLOYMENT') ?: 'development');
$allowedHosts = ['localhost', '127.0.0.1', '::1'];
if (TRUAI_DEPLOYMENT === 'production' && ($extra = getenv('ALLOWED_HOSTS'))) {
    $allowedHosts = array_merge($allowedHosts, array_map('trim', explode(',', $extra)));
}
define('ALLOWED_HOSTS', $allowedHosts);

// AI Service Configuration (from .env or environment; default keys in .env)
define('TRUAI_API_ENDPOINT', getenv('TRUAI_API_ENDPOINT') ?: 'https://api.truai.example.com');
define('TRUAI_API_KEY', getenv('TRUAI_API_KEY') ?: '');
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');
define('OPENAI_API_BASE', getenv('OPENAI_API_BASE') ?: 'https://api.openai.com/v1');
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
// For localhost development, allow http://localhost:8001 and http://127.0.0.1:8001 (primary),
// plus legacy ports for backwards compatibility.
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = ['http://localhost:8001', 'http://127.0.0.1:8001', 'http://localhost:8080', 'http://127.0.0.1:8080', 'http://localhost:8765', 'http://127.0.0.1:8765', 'http://localhost:8787', 'http://127.0.0.1:8787', 'http://localhost', 'http://127.0.0.1'];
define('CORS_ORIGIN', in_array($requestOrigin, $allowedOrigins) ? $requestOrigin : 'http://localhost:8001');

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
    // Set cookie parameters before starting session
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',  // Available for entire domain
        'domain' => '',  // Current domain (localhost) - empty allows localhost
        'secure' => false,  // Set to false for localhost development
        'httponly' => true,  // Prevent JavaScript access
        'samesite' => 'Lax'  // Allow cross-site requests from same site
    ]);
    session_start([
        'gc_maxlifetime' => SESSION_LIFETIME,
        'cookie_lifetime' => SESSION_LIFETIME
    ]);
    
    // Ensure session cookie is sent with every request
    if (!isset($_COOKIE['TRUAI_SESSION']) && isset($_SESSION['logged_in'])) {
        // Session exists but cookie wasn't sent - regenerate
        session_regenerate_id(true);
    }
}
