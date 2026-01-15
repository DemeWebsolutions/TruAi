<?php
/**
 * TruAi Server Router
 * 
 * Main entry point for PHP built-in server
 * Routes all requests (API and static files) correctly
 * 
 * @package TruAi
 * @version 1.0.0
 * @copyright My Deme, LLC © 2026
 */

// Get the requested file path
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestFile = __DIR__ . $requestUri;

// Check if this is a static file request (CSS, JS, images, etc.)
if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot|json)$/i', $requestUri)) {
    // Check if file exists
    if (file_exists($requestFile) && is_file($requestFile)) {
        // Serve static file directly
        return false; // Let PHP built-in server handle it
    }
    // File doesn't exist, return 404
    http_response_code(404);
    echo "File not found";
    return true;
}

// Check if this is an API request
if (strpos($requestUri, '/api/') !== false) {
    // Load backend and route API request
    require_once __DIR__ . '/backend/config.php';
    require_once __DIR__ . '/backend/database.php';
    require_once __DIR__ . '/backend/auth.php';
    require_once __DIR__ . '/backend/router.php';
    
    // Enforce localhost access
    Auth::enforceLocalhost();
    
    // TEMPORARY: Auto-authenticate as admin for API requests if not authenticated
    $tempAuth = new Auth();
    if (!$tempAuth->isAuthenticated()) {
        $db = Database::getInstance();
        $users = $db->query("SELECT * FROM users WHERE username = 'admin' LIMIT 1");
        if (!empty($users)) {
            $user = $users[0];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            $_SESSION['session_token'] = bin2hex(random_bytes(32));
        }
    }
    
    // Handle API request
    $router = new Router();
    $router->dispatch();
    return true;
}

// All other requests go to index.php for frontend
require_once __DIR__ . '/index.php';
return true;
