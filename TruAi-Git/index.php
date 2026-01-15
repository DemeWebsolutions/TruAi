<?php
/**
 * TruAi Server - Main Entry Point
 * 
 * HTML Server Version of Tru.ai
 * 
 * @package TruAi
 * @version 1.0.0
 * @copyright My Deme, LLC © 2026
 */

// Load configuration and dependencies
require_once __DIR__ . '/backend/config.php';
require_once __DIR__ . '/backend/database.php';
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/router.php';

// Enforce localhost access
Auth::enforceLocalhost();

// Check if this is an API request
$requestUri = $_SERVER['REQUEST_URI'];
if (strpos($requestUri, '/api/') !== false) {
    // Handle API request
    $router = new Router();
    $router->dispatch();
    exit;
}

// Check if this is a static asset request (CSS, JS, images)
if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot)$/i', $requestUri)) {
    // Let PHP built-in server handle static files
    return false;
}

// Serve frontend
// TEMPORARY: Skip authentication, always show dashboard
$page = 'dashboard';
$auth = new Auth();

// TEMPORARY: Auto-authenticate as admin if not already authenticated
if (!$auth->isAuthenticated()) {
    // Auto-login as admin for temporary bypass
    $db = Database::getInstance();
    $users = $db->query("SELECT * FROM users WHERE username = 'admin' LIMIT 1");
    if (!empty($users)) {
        $user = $users[0];
        // Set session manually for temporary bypass
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['session_token'] = bin2hex(random_bytes(32));
    }
}

?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - HTML Server Version</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/ide.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #0f1115;
            color: #fff;
            overflow: hidden;
            height: 100vh;
        }

        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            font-size: 18px;
            color: #888;
        }
    </style>
</head>
<body>
    <div id="app">
        <div class="loading">Loading Tru.ai...</div>
    </div>

    <script>
        // Global configuration
        window.TRUAI_CONFIG = {
            APP_NAME: '<?= APP_NAME ?>',
            APP_VERSION: '<?= APP_VERSION ?>',
            API_BASE: window.location.origin + '/api/v1',
            CSRF_TOKEN: '<?= Auth::generateCsrfToken() ?>',
            IS_AUTHENTICATED: true, // TEMPORARY: Always authenticated
            USERNAME: '<?= $auth->getUsername() ?? 'admin' ?>' // TEMPORARY: Default username
        };
        
        // Debug: Log config
        console.log('TruAi Config:', window.TRUAI_CONFIG);
    </script>
    
    <script src="/assets/js/crypto.js"></script>
    <script src="/assets/js/api.js"></script>
    <script src="/assets/js/app.js"></script>
    
    <!-- TEMPORARY: Skip login, show legal notice popup then dashboard -->
    <script src="/assets/js/legal-notice-popup.js"></script>
    <script src="/assets/js/dashboard.js"></script>
    
    <!-- Debug: Force initialization if scripts don't run -->
    <script>
        setTimeout(function() {
            // Check if popup already exists
            if (document.getElementById('legal-notice-overlay')) {
                console.log('Popup already exists, skipping fallback');
                return;
            }
            
            if (document.getElementById('app').innerHTML.includes('Loading Tru.ai...')) {
                console.warn('Still loading after 2 seconds, checking scripts...');
                if (typeof showLegalNoticePopup === 'function') {
                    console.log('Forcing popup display...');
                    showLegalNoticePopup();
                } else {
                    console.error('showLegalNoticePopup not available!');
                    // Fallback: show dashboard directly
                    if (typeof renderDashboard === 'function') {
                        renderDashboard();
                    }
                }
            }
        }, 2000);
    </script>
    
    <!-- Original login flow (commented out temporarily) -->
    <?php /* if ($auth->isAuthenticated()): ?>
        <script src="/assets/js/dashboard.js"></script>
    <?php else: ?>
        <script src="/assets/js/login.js"></script>
    <?php endif; */ ?>
</body>
</html>
