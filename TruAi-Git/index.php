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

// Check if this is a gateway request
$requestUri = $_SERVER['REQUEST_URI'];
if (strpos($requestUri, '/gateway') !== false || strpos($requestUri, '/gateway.php') !== false) {
    // Serve gateway page
    require_once __DIR__ . '/gateway.php';
    exit;
}

// Check if this is an API request
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
$page = $_GET['page'] ?? 'login';
$auth = new Auth();

// Check authentication - redirect to login portal if not authenticated
if (!$auth->isAuthenticated() && $page !== 'login') {
    // Redirect to login portal
    header('Location: /TruAi/login-portal.html');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TruAi - Start New Project</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Helvetica', 'Arial', sans-serif;
            background: url('/TruAi/assets/images/TruAi-Background.jpg') center center / cover no-repeat fixed;
            background-color: #1a1d23; /* Fallback color */
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 0;
            margin: 0;
        }

        .header-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 20px 20px;
        }

        .logo-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 16px;
        }

        .logo-container img {
            width: 64px;
            height: auto;
            display: block;
        }

        .project-title {
            font-size: 18px;
            font-weight: 500;
            color: #e8e9eb;
        }

        /* Full-width AI response area */
        .ai-response-area {
            width: 100%;
            flex: 1;
            padding: 20px 40px;
            overflow-y: auto;
            min-height: 300px;
        }

        .ai-response-content {
            max-width: 1400px;
            margin: 0 auto;
            color: #e8e9eb;
            font-size: 14px;
            line-height: 1.6;
        }

        .ai-response-content.empty {
            color: #8b8d98;
            text-align: center;
            padding: 60px 20px;
        }

        /* Bottom panels container - 100% width */
        .panels-container {
            display: flex;
            gap: 24px;
            width: 100%;
            padding: 20px 40px 40px;
            align-items: flex-start;
        }

        .panel {
            background: none;
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 12px;
            padding: 20px;
        }

        .panel-label {
            font-size: 12px;
            color: #8b8d98;
            margin-bottom: 12px;
            font-weight: 500;
        }

        /* Left panel - Mini content view */
        .content-panel {
            flex: 0 0 200px;
            min-height: 200px;
        }

        .file-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .file-item {
            padding: 8px 0;
            font-size: 13px;
            color: #e8e9eb;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .file-item:last-child {
            border-bottom: none;
        }

        .file-icon {
            width: 16px;
            height: 16px;
            color: #6b6d78;
        }

        /* Center panel - Text entry */
        .center-panel {
            flex: 1;
            min-width: 400px;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            position: relative;
        }

        .center-panel.expanded {
            align-self: flex-start;
            margin-top: auto;
        }

        .settings-panel {
            display: none;
            width: 100%;
            padding: 20px;
            background: rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            margin-bottom: 16px;
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        .center-panel.expanded .settings-panel {
            display: block;
        }


        .text-entry {
            width: 100%;
            min-height: 120px;
            background: none;
            border-radius: 0px;
            padding: 16px;
            font-size: 14px;
            font-family: inherit;
            text-align: center;
            resize: vertical;
            margin-bottom: 16px;
            border: none;
            color: #008ed6;
            outline: none;
        }

        .text-entry:focus {
            width: 100%;
            min-height: 120px;
            background: none;
            border-radius: 8px;
            padding: 16px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            margin-bottom: 16px;
            border: none;
            color: #008ed6;
            outline: none;
        }

        .text-entry::placeholder {
            color: #8b8d98;
        }

        .icon-row {
            display: flex;
            gap: 16px;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
        }

        .icon-group {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .icon {
            width: 24px;
            height: 24px;
            color: #6b6d78;
            opacity: 0.7;
            cursor: pointer;
            transition: opacity 0.2s, color 0.2s;
        }

        .icon:hover {
            opacity: 1;
            color: #8b8d98;
        }

        .icon-button {
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .settings-link {
            color: #6b6d78;
            text-decoration: none;
            font-size: 13px;
            transition: color 0.2s;
            cursor: pointer;
        }

        .settings-link:hover,
        .settings-link.active {
            color: #008ed6;
        }

        .divider {
            width: 1px;
            height: 24px;
            background: rgba(255, 255, 255, 0.1);
        }

        /* Right panel - Mini code review */
        .code-review-panel {
            flex: 0 0 200px;
            min-height: 200px;
        }

        .code-review-content {
            font-size: 12px;
            color: #8b8d98;
            line-height: 1.5;
        }

        .code-snippet {
            background: rgba(20, 23, 29, 0.6);
            border-radius: 4px;
            padding: 8px;
            margin-top: 8px;
            font-family: 'Monaco', 'Menlo', 'Courier New', monospace;
            font-size: 11px;
            color: #6b6d78;
        }

        @media (max-width: 1024px) {
            .panels-container {
                flex-direction: column;
                align-items: stretch;
            }

            .content-panel,
            .center-panel,
            .code-review-panel {
                flex: 1;
                width: 100%;
                max-width: 100%;
            }

            .ai-response-area {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Header: Logo and Title -->
    <div class="header-section">
        <div class="logo-container">
            <img src="/TruAi/assets/images/TruAi-dashboard-logo.png" alt="TruAi Logo" />
        </div>
        <h1 class="project-title">Start New Project</h1>
    </div>

    <!-- Full-width AI Response Area -->
    <div class="ai-response-area">
        <div class="ai-response-content empty" id="aiResponse">
            <!-- AI responses will appear here -->
        </div>
    </div>

    <!-- Bottom Panels Container - Centered -->
    <div class="panels-container">
        <!-- Left Panel: Mini Content View (Files being worked on) -->
        <div class="panel content-panel">
            <div class="panel-label">Content</div>
            <ul class="file-list" id="fileList">
                <li class="file-item">
                    <svg class="file-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                    <span>No files open</span>
                </li>
            </ul>
        </div>

        <!-- Center Panel: Text Entry for AI -->
        <div class="panel center-panel" id="centerPanel" style="background: rgba(0, 0, 0, 0.07);">
            <textarea class="text-entry" id="aiTextEntry" placeholder="Standing by for instructions..." rows="4"></textarea>
            
            <div class="icon-row">
                <div class="icon-group">
                    <!-- Add Photos icon -->
                    <button class="icon-button" title="Add Photos">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                    </button>
                    <!-- Browser / Add URL icon -->
                    <button class="icon-button" title="Add URL">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="2" y1="12" x2="22" y2="12"></line>
                            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                        </svg>
                    </button>
                    <!-- Add File / Folder icon -->
                    <button class="icon-button" title="Add File / Folder">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </button>
                </div>

                <div class="divider"></div>

                <!-- Settings link (center) -->
                <a href="#" class="settings-link" id="settingsLink" title="Settings">Settings</a>

                <div class="divider"></div>

                <div class="icon-group">
                    <!-- Terminal icon -->
                    <button class="icon-button" title="Terminal">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="4 17 10 11 4 5"></polyline>
                            <line x1="12" y1="19" x2="20" y2="19"></line>
                        </svg>
                    </button>
                    <!-- GitHub icon -->
                    <button class="icon-button" title="GitHub">
                        <svg class="icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.17 6.839 9.49.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.463-1.11-1.463-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.578 9.578 0 0112 6.836c.85.004 1.705.114 2.504.336 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.578.688.48C19.138 20.167 22 16.418 22 12c0-5.523-4.477-10-10-10z"/>
                        </svg>
                    </button>
                    <!-- Code View icon -->
                    <button class="icon-button" title="Code View">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="9" y1="3" x2="9" y2="21"></line>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Right Panel: Mini Code Review Section -->
        <div class="panel code-review-panel">
            <div class="panel-label">Code Review</div>
            <div class="code-review-content" id="codeReview">
                <div>No code review available</div>
                <div class="code-snippet" style="display: none;">
                    <!-- Code snippets will appear here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global configuration (preserved for API access if needed)
        window.TRUAI_CONFIG = {
            APP_NAME: '<?= APP_NAME ?>',
            APP_VERSION: '<?= APP_VERSION ?>',
            API_BASE: window.location.origin + '/TruAi/api/v1',
            CSRF_TOKEN: '<?= Auth::generateCsrfToken() ?>',
            IS_AUTHENTICATED: <?= $auth->isAuthenticated() ? 'true' : 'false' ?>,
            USERNAME: '<?= $auth->getUsername() ?? '' ?>'
        };

        // Handle text entry for AI
        const textEntry = document.getElementById('aiTextEntry');
        const aiResponse = document.getElementById('aiResponse');

        textEntry.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
                e.preventDefault();
                const prompt = textEntry.value.trim();
                if (prompt) {
                    // TODO: Send to AI API
                    console.log('Sending prompt to AI:', prompt);
                    aiResponse.classList.remove('empty');
                    aiResponse.textContent = 'Processing your request...';
                    textEntry.value = '';
                }
            }
        });

        // Icon button handlers
        document.querySelectorAll('.icon-button').forEach(button => {
            button.addEventListener('click', function() {
                const title = this.getAttribute('title');
                console.log('Clicked:', title);
                // TODO: Implement functionality for each icon
            });
        });

        // Settings toggle handler
        const settingsLink = document.getElementById('settingsLink');
        const centerPanel = document.getElementById('centerPanel');
        
        settingsLink.addEventListener('click', function(e) {
            e.preventDefault();
            centerPanel.classList.toggle('expanded');
            settingsLink.classList.toggle('active');
        });
    </script>
</body>
</html>// TEMPORARY: Skip authentication, always show dashboard
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
    <link rel="stylesheet" href="/assets/css/welcome.css">
    <link rel="stylesheet" href="/assets/css/settings.css">
    <link rel="stylesheet" href="/assets/css/data-sharing.css">
    <link rel="stylesheet" href="/assets/css/theme-preview.css">
    <link rel="stylesheet" href="/assets/css/inline-rewrite.css">
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
    <script src="/assets/js/data-sharing-consent.js"></script>
    <script src="/assets/js/welcome.js"></script>
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
