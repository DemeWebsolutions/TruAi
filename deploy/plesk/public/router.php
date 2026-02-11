<?php
/**
 * Gemini.ai Router — Plesk/public layout
 * Uses APP_ROOT for backend, PUBLIC_ROOT for web files
 *
 * @package TruAi
 * @copyright My Deme, LLC © 2026
 */

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$publicRoot = defined('PUBLIC_ROOT') ? PUBLIC_ROOT : __DIR__;
$appRoot = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);

$requestFile = $publicRoot . $requestUri;
if (strpos($requestUri, '/TruAi/') === 0) {
    $requestFile = $publicRoot . preg_replace('#^/TruAi#', '', $requestUri);
}

// Static files
if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot|json)$/i', $requestUri)) {
    if (file_exists($requestFile) && is_file($requestFile)) {
        $ext = strtolower(pathinfo($requestFile, PATHINFO_EXTENSION));
        $mimeTypes = [
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'css' => 'text/css',
            'js' => 'application/javascript', 'json' => 'application/json', 'ico' => 'image/x-icon'
        ];
        if (isset($mimeTypes[$ext])) header('Content-Type: ' . $mimeTypes[$ext]);
        readfile($requestFile);
        return;
    }
    http_response_code(404);
    echo "File not found";
    return;
}

$publicFile = function($path) use ($publicRoot) {
    if (file_exists($publicRoot . $path)) {
        readfile($publicRoot . $path);
        return true;
    }
    return false;
};

if (strpos($requestUri, '/welcome') !== false || strpos($requestUri, '/welcome.html') !== false) {
    if ($publicFile('/welcome.html')) return;
}
if (strpos($requestUri, '/start') !== false || strpos($requestUri, '/start.html') !== false) {
    if ($publicFile('/start.html')) { header('Content-Type: text/html; charset=UTF-8'); return; }
}
if (strpos($requestUri, '/loading') !== false || strpos($requestUri, '/loading.html') !== false) {
    if ($publicFile('/loading.html')) return;
}
if (strpos($requestUri, '/access-granted') !== false) {
    if ($publicFile('/access-granted.html')) return;
}
if (strpos($requestUri, '/access-denied') !== false) {
    if ($publicFile('/access-denied.html')) return;
}
if (strpos($requestUri, '/login-portal') !== false || strpos($requestUri, '/login-portal.html') !== false) {
    if ($publicFile('/login-portal.html')) return;
}

// Root: redirect to Gemini portal
if (preg_match('#^/?$#', $requestUri) || $requestUri === '/index.php') {
    header('Location: /TruAi/gemini', true, 302);
    return;
}

if (preg_match('#^/TruAi/gemini(\.html)?/?$#', $requestUri) || preg_match('#^/gemini(\.html)?/?$#', $requestUri)) {
    $p = $publicRoot . '/dev/gemini-portal.html';
    if (file_exists($p)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($p);
        return;
    }
}

if (preg_match('#^/TruAi/phantom#', $requestUri) || preg_match('#^/phantom#', $requestUri)) {
    header('Location: http://127.0.0.1:8787/Phantom.ai.portal.html', true, 302);
    return;
}

if (preg_match('#^/(TruAi/)?monitor/?$#', $requestUri)) {
    header('Location: /TruAi', true, 302);
    return;
}

if (strpos($requestUri, '/api/') !== false) {
    require_once $appRoot . '/backend/config.php';
    require_once $appRoot . '/backend/database.php';
    require_once $appRoot . '/backend/auth.php';
    require_once $appRoot . '/backend/router.php';
    Auth::enforceLocalhost();
    $router = new Router();
    $router->dispatch();
    return;
}

if (preg_match('#^/TruAi/?$#', $requestUri)) {
    $p = $publicRoot . '/TruAi Prototype.html';
    if (file_exists($p)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($p);
        return;
    }
}

require_once $appRoot . '/index.php';
