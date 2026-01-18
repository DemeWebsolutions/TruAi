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

// Handle /TruAi/ prefix for static files
$requestFile = __DIR__ . $requestUri;
if (strpos($requestUri, '/TruAi/') === 0) {
    // Remove /TruAi prefix for file lookup
    $requestFile = __DIR__ . preg_replace('#^/TruAi#', '', $requestUri);
}

// Check if this is a static file request (CSS, JS, images, etc.)
if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot|json)$/i', $requestUri)) {
    // Check if file exists
    if (file_exists($requestFile) && is_file($requestFile)) {
        // Set appropriate content type
        $ext = strtolower(pathinfo($requestFile, PATHINFO_EXTENSION));
        $mimeTypes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'ico' => 'image/x-icon'
        ];
        if (isset($mimeTypes[$ext])) {
            header('Content-Type: ' . $mimeTypes[$ext]);
        }
        // Serve static file directly
        readfile($requestFile);
        return true;
    }
    // File doesn't exist, return 404
    http_response_code(404);
    echo "File not found: " . htmlspecialchars($requestUri);
    return true;
}

// Check if this is a welcome page request
if (strpos($requestUri, '/welcome') !== false || strpos($requestUri, '/welcome.html') !== false) {
    if (file_exists(__DIR__ . '/welcome.html')) {
        readfile(__DIR__ . '/welcome.html');
        return true;
    }
}

// Check if this is a loading page request
if (strpos($requestUri, '/loading') !== false || strpos($requestUri, '/loading.html') !== false) {
    if (file_exists(__DIR__ . '/loading.html')) {
        readfile(__DIR__ . '/loading.html');
        return true;
    }
}

// Check if this is an access page request
if (strpos($requestUri, '/access-granted') !== false || strpos($requestUri, '/access-granted.html') !== false) {
    if (file_exists(__DIR__ . '/access-granted.html')) {
        readfile(__DIR__ . '/access-granted.html');
        return true;
    }
}

if (strpos($requestUri, '/access-denied') !== false || strpos($requestUri, '/access-denied.html') !== false) {
    if (file_exists(__DIR__ . '/access-denied.html')) {
        readfile(__DIR__ . '/access-denied.html');
        return true;
    }
}

// Check if this is a login portal request
if (strpos($requestUri, '/login-portal') !== false || strpos($requestUri, '/login-portal.html') !== false) {
    // Serve login portal page
    if (file_exists(__DIR__ . '/login-portal.html')) {
        readfile(__DIR__ . '/login-portal.html');
        return true;
    }
}

// Check if this is a gateway request
if (strpos($requestUri, '/gateway') !== false || strpos($requestUri, '/gateway.php') !== false || strpos($requestUri, '/gateway.html') !== false) {
    // Serve gateway page
    if (file_exists(__DIR__ . '/gateway.html')) {
        readfile(__DIR__ . '/gateway.html');
        return true;
    } elseif (file_exists(__DIR__ . '/gateway.php')) {
        require_once __DIR__ . '/gateway.php';
        return true;
    }
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
