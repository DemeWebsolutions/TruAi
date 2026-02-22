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
            'json' => 'application/json',
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

// Serve HTML pages from public/TruAi/ directory first (new UBSAS/LSRP pages take precedence)
if (strpos($requestUri, '/TruAi/') === 0 && preg_match('/\.html$/i', $requestUri)) {
    $publicHtmlPath = __DIR__ . '/public' . $requestUri;
    if (file_exists($publicHtmlPath) && is_file($publicHtmlPath)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($publicHtmlPath);
        return true;
    }
}

// Also serve public/TruAi/ HTML pages when requested without the .html extension
if (strpos($requestUri, '/TruAi/') === 0 && !preg_match('/\.\w+$/', $requestUri)) {
    $slug = rtrim($requestUri, '/');
    $publicHtmlPath = __DIR__ . '/public' . $slug . '.html';
    if (file_exists($publicHtmlPath) && is_file($publicHtmlPath)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($publicHtmlPath);
        return true;
    }
}

// Check if this is a welcome page request
if (strpos($requestUri, '/welcome') !== false || strpos($requestUri, '/welcome.html') !== false) {
    if (file_exists(__DIR__ . '/welcome.html')) {
        readfile(__DIR__ . '/welcome.html');
        return true;
    }
}

// Check if this is a start page request (after welcome.html, before /TruAi/ dashboard)
if (strpos($requestUri, '/start') !== false || strpos($requestUri, '/start.html') !== false) {
    if (file_exists(__DIR__ . '/start.html')) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile(__DIR__ . '/start.html');
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

// Serve Gemini.ai portal at /TruAi/gemini or /TruAi/gemini.html
if (preg_match('#^/TruAi/gemini(\.html)?/?$#', $requestUri) || preg_match('#^/gemini(\.html)?/?$#', $requestUri)) {
    $geminiPath = __DIR__ . '/dev/gemini-portal.html';
    if (file_exists($geminiPath)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($geminiPath);
        return true;
    }
}

// Redirect Phantom.ai to external portal (http://127.0.0.1:8787/Phantom.ai.portal.html)
if (preg_match('#^/TruAi/phantom(\.html)?/?$#', $requestUri) || preg_match('#^/phantom(\.html)?/?$#', $requestUri)) {
    header('Location: http://127.0.0.1:8787/Phantom.ai.portal.html', true, 302);
    return true;
}

// Redirect /monitor and /TruAi/monitor to main TruAi dashboard
if (preg_match('#^/(TruAi/)?monitor/?$#', $requestUri)) {
    header('Location: /TruAi', true, 302);
    return true;
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
    
    // Handle API request
    $router = new Router();
    $router->dispatch();
    return true;
}

// Serve TruAi Prototype as live dashboard at /TruAi/ or /TruAi
if (preg_match('#^/TruAi/?$#', $requestUri)) {
    $prototypePath = __DIR__ . '/TruAi Prototype.html';
    if (file_exists($prototypePath)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($prototypePath);
        return true;
    }
}

// All other requests go to index.php for frontend
require_once __DIR__ . '/index.php';
return true;
