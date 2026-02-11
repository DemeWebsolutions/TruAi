<?php
/**
 * TruAi Core Router
 * 
 * Routes API requests to appropriate controllers
 * 
 * @package TruAi
 * @version 1.0.0
 */

// Ensure config is loaded (which starts session)
require_once __DIR__ . '/config.php';

class Router {
    private $auth;
    private $routes = [];

    public function __construct() {
        // Ensure session is started before creating Auth
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->auth = new Auth();
        $this->registerRoutes();
    }

    private function registerRoutes() {
        // Public routes
        $this->routes['GET']['/api/v1/auth/publickey'] = [$this, 'handleGetPublicKey'];
        $this->routes['POST']['/api/v1/auth/login'] = [$this, 'handleLogin'];
        $this->routes['POST']['/api/v1/auth/logout'] = [$this, 'handleLogout'];
        $this->routes['GET']['/api/v1/auth/status'] = [$this, 'handleAuthStatus'];
        $this->routes['POST']['/api/v1/auth/password/change'] = [$this, 'handlePasswordChange'];
        $this->routes['GET']['/api/v1/security/roma'] = [$this, 'handleRomaStatus'];
        $this->routes['POST']['/api/v1/security/events'] = [$this, 'handleSecurityEvents'];
        $this->routes['GET']['/api/v1/health'] = [$this, 'handleHealth'];
        $this->routes['GET']['/api/v1/monitor/probe'] = [$this, 'handleMonitorProbe'];
        $this->routes['POST']['/api/v1/itc/handshake'] = [$this, 'handleItcHandshake'];

        // Protected routes (require authentication)
        $this->routes['GET']['/api/v1/operations/status'] = [$this, 'handleOperationsStatus'];
        $this->routes['GET']['/api/v1/trust/snapshot'] = [$this, 'handleTrustSnapshot'];
        $this->routes['GET']['/api/v1/trust/events'] = [$this, 'handleTrustEvents'];
        $this->routes['POST']['/api/v1/task/create'] = [$this, 'handleTaskCreate'];
        $this->routes['GET']['/api/v1/task/list'] = [$this, 'handleTaskList'];
        $this->routes['GET']['/api/v1/task/{id}'] = [$this, 'handleTaskGet'];
        $this->routes['POST']['/api/v1/task/execute'] = [$this, 'handleTaskExecute'];
        $this->routes['POST']['/api/v1/task/approve'] = [$this, 'handleTaskApprove'];
        
        $this->routes['POST']['/api/v1/chat/message'] = [$this, 'handleChatMessage'];
        $this->routes['POST']['/api/v1/rewrite'] = [$this, 'handleRewrite'];
        $this->routes['GET']['/api/v1/workspace/tree'] = [$this, 'handleWorkspaceTree'];
        $this->routes['POST']['/api/v1/workspace/file/write'] = [$this, 'handleWorkspaceFileWrite'];
        $this->routes['POST']['/api/v1/workspace/xcode/open'] = [$this, 'handleWorkspaceXcodeOpen'];
        $this->routes['POST']['/api/v1/audit/terminal'] = [$this, 'handleAuditTerminal'];
        $this->routes['POST']['/api/v1/terminal/execute'] = [$this, 'handleTerminalExecute'];
        $this->routes['GET']['/api/v1/url/fetch'] = [$this, 'handleUrlFetch'];
        $this->routes['POST']['/api/v1/url/suggest-edits'] = [$this, 'handleUrlSuggestEdits'];
        $this->routes['GET']['/api/v1/chat/conversations'] = [$this, 'handleGetConversations'];
        $this->routes['GET']['/api/v1/chat/conversation/{id}'] = [$this, 'handleGetConversation'];
        $this->routes['DELETE']['/api/v1/chat/conversation/{id}'] = [$this, 'handleDeleteConversation'];
        $this->routes['PATCH']['/api/v1/chat/conversation/{id}'] = [$this, 'handlePatchConversation'];
        $this->routes['PATCH']['/api/v1/chat/message/{id}'] = [$this, 'handlePatchMessage'];
        
        $this->routes['GET']['/api/v1/audit/logs'] = [$this, 'handleGetAuditLogs'];
        
        // AI test endpoint
        $this->routes['GET']['/api/v1/ai/test'] = [$this, 'handleTestAI'];
        $this->routes['POST']['/api/v1/ai/test'] = [$this, 'handleTestAIKeys'];
        
        // Settings endpoints
        $this->routes['GET']['/api/v1/settings'] = [$this, 'handleGetSettings'];
        $this->routes['POST']['/api/v1/settings'] = [$this, 'handleSaveSettings'];
        $this->routes['POST']['/api/v1/settings/reset'] = [$this, 'handleResetSettings'];
        $this->routes['POST']['/api/v1/settings/clear-conversations'] = [$this, 'handleClearConversations'];
        
        // Learning & Adaptation endpoints
        $this->routes['GET']['/api/v1/learning/stats'] = [$this, 'handleGetLearningStats'];
        $this->routes['GET']['/api/v1/learning/high-roi'] = [$this, 'handleGetHighROI'];
        $this->routes['GET']['/api/v1/learning/adaptations'] = [$this, 'handleGetAdaptations'];
        $this->routes['POST']['/api/v1/itc/register'] = [$this, 'handleItcRegister'];
        $this->routes['POST']['/api/v1/itc/revoke'] = [$this, 'handleItcRevoke'];
        $this->routes['GET']['/api/v1/itc/systems'] = [$this, 'handleItcSystems'];

        // Subordinate system outcome reporting (Gemini.ai, Phantom.ai)
        $this->routes['POST']['/api/v1/subordinate/outcome'] = [$this, 'handleSubordinateOutcome'];

        // Gemini.ai dashboard: stats, insights, usage
        $this->routes['GET']['/api/v1/gemini/stats'] = [$this, 'handleGeminiStats'];
        $this->routes['GET']['/api/v1/gemini/insights'] = [$this, 'handleGeminiInsights'];
        $this->routes['POST']['/api/v1/gemini/chat/feedback'] = [$this, 'handleGeminiChatFeedback'];
    }

    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Strip /TruAi prefix if present
        $path = preg_replace('#^/TruAi#', '', $path);

        // Handle CORS
        if (CORS_ENABLED) {
            // Get request origin
            $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
            $allowedOrigins = ['http://localhost:8080', 'http://127.0.0.1:8080', 'http://localhost:8765', 'http://127.0.0.1:8765', 'http://localhost:8787', 'http://127.0.0.1:8787', 'http://localhost:5000', 'http://127.0.0.1:5000', 'http://154.53.54.169:5000', 'http://localhost', 'http://127.0.0.1'];
            
            // Allow credentials only from allowed origins
            if (in_array($requestOrigin, $allowedOrigins)) {
                header('Access-Control-Allow-Origin: ' . $requestOrigin);
                header('Access-Control-Allow-Credentials: true'); // CRITICAL: Allows cookies to be sent
            } else {
                // Fallback to default for same-origin requests
                header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
            }
            
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
            header('Access-Control-Expose-Headers: X-CSRF-Token'); // Allow client to read CSRF token
            
            if ($method === 'OPTIONS') {
                http_response_code(200);
                exit;
            }
        }

        header('Content-Type: application/json');

        // Find matching route
        foreach ($this->routes[$method] ?? [] as $route => $handler) {
            $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $route);
            $pattern = '#^' . $pattern . '$#';
            
            if (preg_match($pattern, $path, $matches)) {
                array_shift($matches); // Remove full match
                
                // Check authentication for protected routes
                if ($route !== '/api/v1/auth/login' && 
                    $route !== '/api/v1/auth/status' &&
                    $route !== '/api/v1/auth/publickey' &&
                    $route !== '/api/v1/security/roma' &&
                    $route !== '/api/v1/health' &&
                    $route !== '/api/v1/monitor/probe' &&
                    $route !== '/api/v1/itc/handshake' &&
                    $route !== '/api/v1/ai/test' &&
                    $route !== '/api/v1/gemini/stats' &&
                    $route !== '/api/v1/gemini/insights') {
                    // Settings endpoints require authentication (correct behavior)
                    if (!$this->auth->isAuthenticated()) {
                        http_response_code(401);
                        // Log authentication failure for debugging
                        error_log('Auth failed for route: ' . $route . ' - Session: ' . (isset($_SESSION['logged_in']) ? 'exists' : 'missing'));
                        echo json_encode(['error' => 'Unauthorized', 'message' => 'Session expired or invalid. Please log in again.']);
                        return;
                    }
                }
                
                call_user_func_array($handler, $matches);
                return;
            }
        }

        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
    }

    private function handleGetPublicKey() {
        $encryption = $this->auth->getEncryptionService();
        echo json_encode([
            'public_key' => $encryption->getPublicKey(),
            'algorithm' => 'RSA-2048 + AES-256-GCM',
            'timestamp' => time()
        ]);
    }

    private function handleLogin() {
        require_once __DIR__ . '/roma_trust.php';
        if (RomaTrust::isSuspicionBlocked()) {
            RomaTrust::emitSecurityEvent('ROMA_SUSPICION_BLOCKED', [
                'operation' => 'login',
                'score' => RomaTrust::getSuspicionScore()
            ]);
            http_response_code(429);
            echo json_encode(['error' => 'Too many failed attempts', 'message' => 'Please try again later.']);
            return;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Check for encrypted login (Phantom.ai style)
        if (isset($data['encrypted_data']) && isset($data['session_id'])) {
            // Encrypted login
            if ($this->auth->login(null, null, true, $data['encrypted_data'], $data['session_id'])) {
                echo json_encode([
                    'success' => true,
                    'username' => $this->auth->getUsername(),
                    'csrf_token' => Auth::generateCsrfToken(),
                    'encryption' => 'enabled'
                ]);
            } else {
                require_once __DIR__ . '/roma_trust.php';
                RomaTrust::incrementSuspicion();
                http_response_code(401);
                echo json_encode(['error' => 'Invalid credentials']);
            }
            return;
        }
        
        // Standard login (fallback)
        if (!isset($data['username']) || !isset($data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Username and password required']);
            return;
        }

        if ($this->auth->login($data['username'], $data['password'])) {
            echo json_encode([
                'success' => true,
                'username' => $data['username'],
                'csrf_token' => Auth::generateCsrfToken(),
                'encryption' => 'standard'
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
        }
    }

    private function handleLogout() {
        $this->auth->logout();
        echo json_encode(['success' => true]);
    }

    /**
     * ROMA-gated password change. Requires ROMA verified + auth + CSRF.
     * Supports plain or encrypted payload (RSA + AES-256-GCM).
     */
    private function handlePasswordChange() {
        require_once __DIR__ . '/roma_trust.php';
        $encryption = $this->auth->getEncryptionService();
        $romaStatus = RomaTrust::getStatus($encryption);

        if (!$romaStatus['roma'] || !$romaStatus['portal_protected'] || $romaStatus['monitor'] !== 'active') {
            RomaTrust::emitSecurityEvent('PASSWORD_CHANGE_BLOCKED', [
                'reason' => 'ROMA not verified',
                'trust_state' => $romaStatus['trust_state'] ?? 'UNVERIFIED'
            ]);
            http_response_code(503);
            echo json_encode(['error' => 'Password change requires ROMA verification', 'message' => 'Portal protection or monitor not active.']);
            return;
        }

        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Auth::verifyCsrfToken($csrfToken)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            return;
        }

        $currentPassword = null;
        $newPassword = null;

        if (isset($data['encrypted_data']) && isset($data['session_id'])) {
            try {
                if (!empty($data['encrypted_session_key'])) {
                    $encryption->storeSessionKeyFromRSA($data['encrypted_session_key'], $data['session_id']);
                }
                $payload = $encryption->decryptCredentials($data['encrypted_data'], $data['session_id']);
                $currentPassword = $payload['current_password'] ?? null;
                $newPassword = $payload['new_password'] ?? null;
            } catch (Exception $e) {
                RomaTrust::incrementSuspicion();
                http_response_code(400);
                echo json_encode(['error' => 'Decryption failed']);
                return;
            }
        } else {
            $currentPassword = $data['current_password'] ?? null;
            $newPassword = $data['new_password'] ?? null;
        }

        if (empty($currentPassword) || empty($newPassword)) {
            http_response_code(400);
            echo json_encode(['error' => 'current_password and new_password required']);
            return;
        }

        try {
            if ($this->auth->changePassword($currentPassword, $newPassword)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Password changed successfully',
                    'roma' => ['encryption' => 'RSA-2048 + AES-256-GCM', 'portal_protected' => true]
                ]);
            } else {
                RomaTrust::incrementSuspicion();
                http_response_code(401);
                echo json_encode(['error' => 'Current password incorrect']);
            }
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function handleRomaStatus() {
        require_once __DIR__ . '/roma_trust.php';
        $encryption = $this->auth->getEncryptionService();
        $status = RomaTrust::getStatus($encryption);
        if (!$status['roma']) {
            RomaTrust::emitSecurityEvent('ROMA_VALIDATION_FAILURE', [
                'reason' => $status['reason'],
                'checks' => $status['checks']
            ]);
        }
        echo json_encode($status);
    }

    private function handleOperationsStatus() {
        require_once __DIR__ . '/roma_trust.php';
        $encryption = $this->auth->getEncryptionService();
        $romaStatus = RomaTrust::getStatus($encryption);
        echo json_encode([
            'status_created' => [
                'tasks' => ['CREATED', 'EXECUTED', 'APPROVED', 'REJECTED', 'SAVED', 'LOCKED'],
                'executions' => ['PENDING', 'COMPLETED']
            ],
            'roma' => [
                'active' => $romaStatus['roma'],
                'portal_protected' => $romaStatus['portal_protected'],
                'monitor' => $romaStatus['monitor'],
                'encryption' => $romaStatus['encryption'],
                'trust_state' => $romaStatus['trust_state']
            ],
            'timestamp' => time()
        ]);
    }

    private function handleTrustSnapshot() {
        require_once __DIR__ . '/roma_trust.php';
        require_once __DIR__ . '/roma_itc.php';
        $encryption = $this->auth->getEncryptionService();
        $romaStatus = RomaTrust::getStatus($encryption);
        $global = $romaStatus['trust_state'] ?? 'UNKNOWN';
        if ($global === 'BLOCKED') $global = 'UNVERIFIED';
        $itcSystems = RomaItc::listSystems();
        $systems = ['truai' => $romaStatus['roma'] ? 'VERIFIED' : 'UNVERIFIED'];
        foreach ($itcSystems as $s) {
            $key = str_replace(['.', '-'], '', strtolower(explode('.', $s['system_id'])[0]));
            $systems[$key] = $s['trust_status'] === 'active' ? 'VERIFIED' : 'DEGRADED';
        }
        $systemsOk = count(array_filter($systems, fn($v) => $v === 'VERIFIED'));
        $systemsTotal = count($systems) ?: 1;
        $escalation = RomaTrust::isSuspicionBlocked() ? 'HOLD' : 'NONE';
        if ($global === 'UNVERIFIED') $escalation = 'HOLD';
        $roiScore = $romaStatus['roma'] ? 0.92 : 0.5;
        $roiSecurityCost = $romaStatus['roma'] ? 'LOW' : 'ELEVATED';
        echo json_encode([
            'global' => $global,
            'systems' => $systems,
            'systems_summary' => $systemsOk . '/' . $systemsTotal . ' OK',
            'encryption' => [
                'active' => !empty($romaStatus['encryption']),
                'algorithm' => $romaStatus['encryption'] ?? 'AES-256-GCM'
            ],
            'roi' => [
                'score' => $roiScore,
                'securityCost' => $roiSecurityCost,
                'modelCost' => 'LOW',
                'executionScope' => 'Local',
                'trustMultiplier' => $romaStatus['roma'] ? 1.0 : 0.5
            ],
            'escalation' => $escalation,
            'timestamp' => time()
        ]);
    }

    private function handleTrustEvents() {
        $limit = min((int)($_GET['limit'] ?? 10), 50);
        $db = Database::getInstance();
        $rows = $db->query(
            "SELECT event, actor, details, timestamp FROM audit_logs WHERE actor = 'ROMA' OR event LIKE 'ITC_%' ORDER BY id DESC LIMIT " . (int)$limit
        );
        $events = [];
        foreach ($rows as $r) {
            $details = json_decode($r['details'] ?? '{}', true);
            $events[] = [
                'event' => $r['event'],
                'actor' => $r['actor'],
                'details' => $details,
                'timestamp' => $r['timestamp']
            ];
        }
        echo json_encode(['events' => $events]);
    }

    private function handleHealth() {
        require_once __DIR__ . '/roma_trust.php';
        $encryption = $this->auth->getEncryptionService();
        $romaStatus = RomaTrust::getStatus($encryption);
        $ready = $romaStatus['roma'];
        http_response_code($ready ? 200 : 503);
        echo json_encode([
            'ready' => $ready,
            'roma' => $romaStatus,
            'timestamp' => time()
        ]);
    }

    /**
     * Proxy health probe for monitor — bypasses CORS for localhost URLs.
     * GET ?url=http://127.0.0.1:8787/ returns { ok: true } if reachable.
     */
    private function handleMonitorProbe() {
        $url = $_GET['url'] ?? '';
        $url = trim($url);
        if (empty($url) || !preg_match('#^https?://#i', $url)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Valid http(s) URL required']);
            return;
        }
        $parsed = parse_url($url);
        $host = strtolower($parsed['host'] ?? '');
        $allowed = ['localhost', '127.0.0.1', '::1'];
        if (!in_array($host, $allowed)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Only localhost URLs allowed for monitor probe']);
            return;
        }
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 5,
                'follow_location' => 1,
                'ignore_errors' => true
            ]
        ]);
        $result = @file_get_contents($url, false, $ctx);
        $ok = $result !== false;
        echo json_encode(['ok' => $ok, 'url' => $url]);
    }

    private function handleSecurityEvents() {
        $data = json_decode(file_get_contents('php://input'), true);
        $event = trim($data['event'] ?? '');
        $details = $data['details'] ?? [];
        if ($event === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Event type required']);
            return;
        }
        require_once __DIR__ . '/roma_trust.php';
        RomaTrust::emitSecurityEvent($event, is_array($details) ? $details : ['raw' => $details]);
        echo json_encode(['success' => true]);
    }

    private function handleSubordinateOutcome() {
        $data = json_decode(file_get_contents('php://input'), true);
        $system = trim($data['system'] ?? '');
        $taskId = trim($data['task_id'] ?? '');
        $outcome = trim($data['outcome'] ?? '');
        $userAction = trim($data['user_action'] ?? '');
        if (empty($system) || empty($outcome)) {
            http_response_code(400);
            echo json_encode(['error' => 'system and outcome required']);
            return;
        }
        if (!in_array($system, ['gemini', 'phantom'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid system; must be gemini or phantom']);
            return;
        }
        if (!in_array($outcome, ['success', 'failure'])) {
            http_response_code(400);
            echo json_encode(['error' => 'outcome must be success or failure']);
            return;
        }
        $userId = $this->auth->getUserId();
        $db = Database::getInstance();
        $db->execute(
            "INSERT INTO audit_logs (user_id, event, actor, details) VALUES (:user_id, 'SUBORDINATE_OUTCOME', :actor, :details)",
            [
                ':user_id' => $userId,
                ':actor' => strtoupper($system),
                ':details' => json_encode([
                    'system' => $system,
                    'task_id' => $taskId,
                    'outcome' => $outcome,
                    'user_action' => $userAction ?: null,
                    'latency_ms' => (int)($data['latency_ms'] ?? 0),
                    'target' => $data['target'] ?? null,
                    'project_id' => $data['project_id'] ?? null,
                ])
            ]
        );
        echo json_encode(['success' => true]);
    }

    /**
     * Gemini.ai dashboard stats — nodes, alerts, cpu, uptime, activity, usage
     */
    private function handleGeminiStats() {
        $userId = $this->auth->getUserId();
        $activity = [];
        $usage = ['api_calls' => 0, 'tokens_estimate' => 0, 'cost_estimate' => '0.00'];
        if ($userId) {
            try {
                $db = Database::getInstance();
                $rows = $db->query("SELECT event, details, timestamp FROM audit_logs WHERE user_id = :uid AND (event LIKE '%CHAT%' OR event LIKE '%TASK%' OR event LIKE '%SUBORDINATE%') ORDER BY timestamp DESC LIMIT 10", [':uid' => $userId]);
                foreach ($rows as $r) {
                    $d = json_decode($r['details'] ?? '{}', true);
                    $activity[] = [
                        'event' => $r['event'],
                        'summary' => $d['target'] ?? $d['system'] ?? $r['event'],
                        'timestamp' => (int)($r['timestamp'] ?? 0)
                    ];
                }
                $usageRows = $db->query("SELECT SUM(1) as cnt FROM audit_logs WHERE user_id = :uid AND event = 'CHAT_MESSAGE' AND timestamp > :since", [':uid' => $userId, ':since' => time() - 86400 * 30]);
                $usage['api_calls'] = (int)($usageRows[0]['cnt'] ?? 0);
                $usage['tokens_estimate'] = $usage['api_calls'] * 500;
                $usage['cost_estimate'] = number_format($usage['api_calls'] * 0.002, 2);
            } catch (Throwable $e) {
                // fallback to defaults
            }
        }
        echo json_encode([
            'provisioned_nodes' => 42,
            'active_alerts' => 3,
            'avg_cpu_load' => 27,
            'uptime_percent' => 99.98,
            'activity' => !empty($activity) ? $activity : [
                ['event' => 'REMEDIATION', 'summary' => 'Auto-remediation applied to node gmn-07', 'timestamp' => time() - 3600],
                ['event' => 'FAILOVER', 'summary' => 'Failover tested successfully for region eu-west', 'timestamp' => time() - 7200],
                ['event' => 'UPDATES', 'summary' => 'Package updates completed on 12 hosts', 'timestamp' => time() - 14400],
            ],
            'usage' => $usage,
            'alerts' => [
                ['id' => 'a1', 'severity' => 'medium', 'node' => 'gmn-07', 'message' => 'CPU trending above 80% threshold', 'remediation' => 'Run Diagnostics', 'timestamp' => time() - 1800],
                ['id' => 'a2', 'severity' => 'low', 'node' => 'gmn-12', 'message' => 'Disk usage at 75%', 'remediation' => 'Collect Logs', 'timestamp' => time() - 3600],
                ['id' => 'a3', 'severity' => 'high', 'node' => 'eu-west-1', 'message' => 'Latency spike detected', 'remediation' => 'Run Diagnostics', 'timestamp' => time() - 900],
            ],
            'timestamp' => time()
        ]);
    }

    /**
     * Gemini.ai AI insights — recommendations, suggested actions
     */
    private function handleGeminiInsights() {
        echo json_encode([
            'recommendations' => [
                ['id' => 'r1', 'type' => 'capacity', 'title' => 'Consider scaling node gmn-07', 'detail' => 'CPU trending up over last 24h. Projected 85% in 7 days.', 'action' => 'Scale Cluster', 'priority' => 'medium'],
                ['id' => 'r2', 'type' => 'security', 'title' => 'Apply security hardening', 'detail' => '2 security patches pending for critical dependencies.', 'action' => 'Apply Security Hardening', 'priority' => 'high'],
                ['id' => 'r3', 'type' => 'anomaly', 'title' => 'Unusual load pattern in eu-west', 'detail' => 'Traffic spike detected. Consider running diagnostics.', 'action' => 'Run Diagnostics', 'priority' => 'medium'],
                ['id' => 'r4', 'type' => 'capacity', 'title' => 'Projected capacity in 7 days', 'detail' => 'Based on current trends, capacity may reach ~85%.', 'action' => 'Provision Node', 'priority' => 'low'],
            ],
            'suggested_actions' => ['Run Diagnostics', 'Apply Security Hardening', 'Scale Cluster', 'Collect Logs'],
            'timestamp' => time()
        ]);
    }

    /**
     * Gemini.ai chat feedback (thumbs up/down)
     */
    private function handleGeminiChatFeedback() {
        $data = json_decode(file_get_contents('php://input'), true);
        $messageId = trim($data['message_id'] ?? '');
        $feedback = trim($data['feedback'] ?? '');
        if (empty($messageId) || !in_array($feedback, ['thumbs_up', 'thumbs_down'])) {
            http_response_code(400);
            echo json_encode(['error' => 'message_id and feedback (thumbs_up|thumbs_down) required']);
            return;
        }
        $userId = $this->auth->getUserId();
        $db = Database::getInstance();
        $db->execute(
            "INSERT INTO audit_logs (user_id, event, actor, details) VALUES (:user_id, 'GEMINI_CHAT_FEEDBACK', 'user', :details)",
            [':user_id' => $userId ?: 0, ':details' => json_encode(['message_id' => $messageId, 'feedback' => $feedback])]
        );
        echo json_encode(['success' => true]);
    }

    private function handleItcHandshake() {
        $data = json_decode(file_get_contents('php://input'), true);
        $systemId = trim($data['system_id'] ?? '');
        $nonce = $data['nonce'] ?? '';
        $timestamp = $data['timestamp'] ?? '';
        $signature = $data['signature'] ?? '';
        if (empty($systemId) || empty($nonce) || empty($timestamp) || empty($signature)) {
            http_response_code(400);
            echo json_encode(['error' => 'system_id, nonce, timestamp, signature required']);
            return;
        }
        try {
            require_once __DIR__ . '/roma_itc.php';
            $result = RomaItc::handshake($systemId, $nonce, $timestamp, $signature);
            require_once __DIR__ . '/roma_trust.php';
            RomaTrust::emitSecurityEvent('ITC_HANDSHAKE', ['system_id' => $systemId]);
            echo json_encode($result);
        } catch (Exception $e) {
            require_once __DIR__ . '/roma_trust.php';
            RomaTrust::emitSecurityEvent('ITC_HANDSHAKE_FAILED', ['system_id' => $systemId, 'reason' => $e->getMessage()]);
            http_response_code(401);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function handleItcRegister() {
        $this->auth->requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        $systemId = trim($data['system_id'] ?? '');
        $publicKey = $data['public_key'] ?? '';
        if (empty($systemId) || empty($publicKey)) {
            http_response_code(400);
            echo json_encode(['error' => 'system_id and public_key required']);
            return;
        }
        try {
            require_once __DIR__ . '/roma_itc.php';
            RomaItc::registerSystem($systemId, $publicKey);
            echo json_encode(['success' => true, 'system_id' => $systemId]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function handleItcRevoke() {
        $this->auth->requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        $systemId = trim($data['system_id'] ?? '');
        if (empty($systemId)) {
            http_response_code(400);
            echo json_encode(['error' => 'system_id required']);
            return;
        }
        require_once __DIR__ . '/roma_itc.php';
        RomaItc::revokeSystem($systemId);
        require_once __DIR__ . '/roma_trust.php';
        RomaTrust::emitSecurityEvent('ITC_REVOKE', ['system_id' => $systemId]);
        echo json_encode(['success' => true, 'system_id' => $systemId]);
    }

    private function handleItcSystems() {
        $this->auth->requireAdmin();
        require_once __DIR__ . '/roma_itc.php';
        $systems = RomaItc::listSystems();
        echo json_encode(['systems' => $systems]);
    }

    /**
     * Check ROMA trust state; block protected operations if UNVERIFIED or suspicion blocked.
     * Returns true if allowed, false and sends response if blocked.
     */
    private function requireRomaVerified($operation) {
        require_once __DIR__ . '/roma_trust.php';
        if (RomaTrust::isSuspicionBlocked()) {
            RomaTrust::emitSecurityEvent('ROMA_SUSPICION_BLOCKED', [
                'operation' => $operation,
                'score' => RomaTrust::getSuspicionScore()
            ]);
            http_response_code(429);
            echo json_encode([
                'error' => 'ROMA suspicion blocked',
                'message' => 'Too many security events. Please try again later.',
                'roma' => ['trust_state' => 'BLOCKED', 'reason' => 'suspicion_threshold']
            ]);
            return false;
        }
        $encryption = $this->auth->getEncryptionService();
        $validation = RomaTrust::validate($encryption);
        if (!$validation['verified']) {
            RomaTrust::emitSecurityEvent('ROMA_BLOCKED_OPERATION', [
                'operation' => $operation,
                'reason' => $validation['reason']
            ]);
            http_response_code(503);
            echo json_encode([
                'error' => 'ROMA unverified',
                'message' => 'Security layer not ready. ' . $validation['reason'],
                'roma' => ['trust_state' => 'UNVERIFIED', 'reason' => $validation['reason']]
            ]);
            return false;
        }
        return true;
    }

    private function handleAuthStatus() {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Regenerate CSRF token if authenticated
        if ($this->auth->isAuthenticated()) {
            $csrfToken = Auth::generateCsrfToken();
            header('X-CSRF-Token: ' . $csrfToken);
            echo json_encode([
                'authenticated' => true,
                'username' => $this->auth->getUsername(),
                'csrf_token' => $csrfToken
            ]);
        } else {
            echo json_encode(['authenticated' => false]);
        }
    }

    private function handleTaskCreate() {
        require_once __DIR__ . '/truai_service.php';
        $service = new TruAiService();
        $data = json_decode(file_get_contents('php://input'), true);
        
        $result = $service->createTask(
            $this->auth->getUserId(),
            $data['prompt'] ?? '',
            $data['context'] ?? null,
            $data['preferred_tier'] ?? 'auto'
        );
        
        echo json_encode($result);
    }

    private function handleTaskList() {
        require_once __DIR__ . '/truai_service.php';
        $service = new TruAiService();
        $userId = $this->auth->getUserId();
        $limit = isset($_GET['limit']) ? min(100, (int) $_GET['limit']) : 20;
        $statusFilter = isset($_GET['status']) ? array_values(array_filter(array_map('trim', explode(',', $_GET['status'])))) : null;
        $tasks = $service->listTasks($userId, $limit, $statusFilter);
        echo json_encode(['tasks' => $tasks]);
    }

    private function handleTaskGet($taskId) {
        require_once __DIR__ . '/truai_service.php';
        $service = new TruAiService();
        $task = $service->getTask($taskId);
        
        if ($task) {
            echo json_encode($task);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Task not found']);
        }
    }

    private function handleTaskExecute() {
        require_once __DIR__ . '/truai_service.php';
        $service = new TruAiService();
        $data = json_decode(file_get_contents('php://input'), true);
        
        $result = $service->executeTask($data['task_id'] ?? '');
        echo json_encode($result);
    }

    private function handleTaskApprove() {
        require_once __DIR__ . '/truai_service.php';
        $service = new TruAiService();
        $data = json_decode(file_get_contents('php://input'), true);
        
        $result = $service->approveTask(
            $data['task_id'] ?? '',
            $data['action'] ?? 'APPROVE',
            $data['target'] ?? 'production'
        );
        echo json_encode($result);
    }

    private function handleChatMessage() {
        require_once __DIR__ . '/chat_service.php';
        $service = new ChatService();
        $data = json_decode(file_get_contents('php://input'), true);
        
        $metadata = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [];
        if (isset($data['scope'])) $metadata['scope'] = $data['scope'];
        if (isset($data['intent'])) $metadata['intent'] = $data['intent'];
        if (isset($data['risk'])) $metadata['risk'] = $data['risk'];
        if (isset($data['forensic_id'])) $metadata['forensic_id'] = $data['forensic_id'];
        if (isset($data['context_files'])) $metadata['context_files'] = $data['context_files'];
        if (empty($metadata)) $metadata = null;
        
        $result = $service->sendMessage(
            $this->auth->getUserId(),
            $data['conversation_id'] ?? null,
            $data['message'] ?? '',
            $data['model'] ?? 'auto',
            $metadata
        );
        
        echo json_encode($result);
    }

    /**
     * Inline rewrite: selection + instruction -> rewritten text. Audit every rewrite.
     */
    private function handleRewrite() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['selection']) || empty($data['instruction'])) {
            http_response_code(400);
            echo json_encode(['error' => 'selection and instruction required']);
            return;
        }
        $selection = $data['selection'];
        $instruction = $data['instruction'];
        $filePath = $data['file_path'] ?? '';

        require_once __DIR__ . '/ai_client.php';
        $aiClient = new AIClient();
        $userId = $this->auth->getUserId();

        $prompt = "Rewrite the following code according to these instructions. Provide ONLY the rewritten code, no explanations or markdown.\n\nInstruction: " . $instruction . "\n\nCode:\n```\n" . $selection . "\n```";
        $conversationHistory = [];
        try {
            $rewritten = $aiClient->chat($prompt, 'gpt-4', $conversationHistory);
            $rewritten = preg_replace('/^```[\w]*\n?|\n?```$/s', '', trim($rewritten));
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
            return;
        }

        $forensicId = 'TRUAI_' . time() . '_' . bin2hex(random_bytes(4));
        $db = Database::getInstance();
        $db->execute(
            "INSERT INTO audit_logs (user_id, event, actor, details) VALUES (:user_id, 'INLINE_REWRITE', :actor, :details)",
            [
                ':user_id' => $userId,
                ':actor' => $forensicId,
                ':details' => json_encode(['file_path' => $filePath, 'selection_length' => strlen($selection)])
            ]
        );

        echo json_encode([
            'rewritten' => $rewritten,
            'forensic_id' => $forensicId,
            'risk_level' => 'SAFE'
        ]);
    }

    /**
     * Workspace file tree (read-only). Deterministic ordering. Optional root from config.
     */
    private function handleWorkspaceTree() {
        $root = defined('WORKSPACE_ROOT') && WORKSPACE_ROOT ? WORKSPACE_ROOT : (__DIR__ . '/..');
        $root = realpath($root);
        if (!$root || !is_dir($root)) {
            echo json_encode(['tree' => [], 'root' => '']);
            return;
        }
        $maxDepth = 2;
        $maxFiles = 100;
        $tree = $this->scanDirForTree($root, $root, 0, $maxDepth, $maxFiles);
        echo json_encode(['tree' => $tree, 'root' => $root]);
    }

    private function scanDirForTree($basePath, $currentPath, $depth, $maxDepth, &$maxFiles) {
        if ($depth >= $maxDepth || $maxFiles <= 0) {
            return [];
        }
        $items = @scandir($currentPath);
        if ($items === false) {
            return [];
        }
        $result = [];
        $rel = substr($currentPath, strlen($basePath)) ?: '/';
        foreach ($items as $name) {
            if ($name === '.' || $name === '..' || $name === '.git' || $name === 'node_modules') {
                continue;
            }
            if ($maxFiles <= 0) {
                break;
            }
            $full = $currentPath . DIRECTORY_SEPARATOR . $name;
            $isDir = is_dir($full);
            $result[] = [
                'name' => $name,
                'path' => $rel . (str_replace('\\', '/', $rel) === '/' ? '' : '') . $name,
                'isDirectory' => $isDir,
                'children' => $isDir && $depth + 1 < $maxDepth ? $this->scanDirForTree($basePath, $full, $depth + 1, $maxDepth, $maxFiles) : []
            ];
            $maxFiles--;
        }
        usort($result, function ($a, $b) {
            if ($a['isDirectory'] !== $b['isDirectory']) {
                return $a['isDirectory'] ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });
        return $result;
    }

    /**
     * Write content to a workspace file (Xcode integration). Roma-congruent: auth required, path scoped to workspace, audit-logged.
     */
    private function handleWorkspaceFileWrite() {
        if (!$this->requireRomaVerified('workspace_file_write')) {
            return;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $path = isset($data['path']) ? trim((string) $data['path']) : '';
        $content = isset($data['content']) ? (string) $data['content'] : '';

        if ($path === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Path required']);
            return;
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path);
        $path = trim($path, '/');
        if ($path === '' || strpos($path, '..') !== false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid path']);
            return;
        }

        $allowedExtensions = ['swift', 'm', 'h', 'mm', 'c', 'cpp', 'hpp', 'cc', 'cxx', 'js', 'ts', 'jsx', 'tsx', 'py', 'php', 'rb', 'go', 'rs', 'java', 'kt', 'kts', 'cs', 'html', 'htm', 'css', 'scss', 'less', 'json', 'xml', 'plist', 'storyboard', 'xib', 'md', 'sh', 'yaml', 'yml', 'sql', 'r'];
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'File type not allowed for direct write']);
            return;
        }

        $maxSize = 2 * 1024 * 1024; // 2MB
        if (strlen($content) > $maxSize) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Content too large']);
            return;
        }

        $root = defined('WORKSPACE_ROOT') && WORKSPACE_ROOT ? WORKSPACE_ROOT : (__DIR__ . '/..');
        $root = realpath($root);
        if (!$root || !is_dir($root)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Workspace not available']);
            return;
        }

        $full = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $dir = dirname($full);
        $dirReal = @realpath($dir);
        if (!$dirReal && !@is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Cannot create directory']);
                return;
            }
            $dirReal = realpath($dir);
        }
        if (!$dirReal || strpos($dirReal . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR) !== 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Path outside workspace']);
            return;
        }

        if (@file_put_contents($full, $content, LOCK_EX) === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Write failed']);
            return;
        }

        $db = Database::getInstance();
        $db->execute(
            "INSERT INTO audit_logs (user_id, event, actor, details) VALUES (:user_id, 'WORKSPACE_FILE_WRITE', 'USER', :details)",
            [
                ':user_id' => $this->auth->getUserId(),
                ':details' => json_encode(['path' => $path])
            ]
        );

        require_once __DIR__ . '/roma_trust.php';
        $romaStatus = RomaTrust::getStatus($this->auth->getEncryptionService());
        echo json_encode([
            'success' => true,
            'path' => $path,
            'roma' => [
                'applied' => $romaStatus['roma'],
                'encryption' => $romaStatus['encryption'],
                'portal_protected' => $romaStatus['portal_protected'],
                'monitor' => $romaStatus['monitor']
            ]
        ]);
    }

    /**
     * Open a workspace file in Xcode (macOS only). Path must be under workspace; allowlisted extensions.
     */
    private function handleWorkspaceXcodeOpen() {
        $data = json_decode(file_get_contents('php://input'), true);
        $path = isset($data['path']) ? trim((string) $data['path']) : '';

        if ($path === '') {
            http_response_code(400);
            echo json_encode(['opened' => false, 'error' => 'Path required']);
            return;
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path);
        $path = trim($path, '/');
        if ($path === '' || strpos($path, '..') !== false) {
            http_response_code(400);
            echo json_encode(['opened' => false, 'error' => 'Invalid path']);
            return;
        }

        $allowedExtensions = ['swift', 'm', 'h', 'mm', 'c', 'cpp', 'hpp', 'cc', 'cxx', 'js', 'ts', 'jsx', 'tsx', 'py', 'php', 'rb', 'go', 'rs', 'java', 'kt', 'kts', 'cs', 'html', 'htm', 'css', 'scss', 'less', 'json', 'xml', 'plist', 'storyboard', 'xib', 'md', 'sh', 'yaml', 'yml', 'sql', 'r'];
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            http_response_code(400);
            echo json_encode(['opened' => false, 'error' => 'File type not allowed']);
            return;
        }

        $root = defined('WORKSPACE_ROOT') && WORKSPACE_ROOT ? WORKSPACE_ROOT : (__DIR__ . '/..');
        $root = realpath($root);
        if (!$root || !is_dir($root)) {
            http_response_code(500);
            echo json_encode(['opened' => false, 'error' => 'Workspace not available']);
            return;
        }

        $full = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $full = realpath($full) ?: $full;
        if (!is_file($full)) {
            http_response_code(404);
            echo json_encode(['opened' => false, 'error' => 'File not found']);
            return;
        }
        $fullReal = realpath($full);
        if (!$fullReal || strpos($fullReal . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR) !== 0) {
            http_response_code(403);
            echo json_encode(['opened' => false, 'error' => 'Path outside workspace']);
            return;
        }

        if (PHP_OS_FAMILY !== 'Darwin' && strtoupper(substr(PHP_OS, 0, 3)) !== 'DAR') {
            http_response_code(501);
            echo json_encode(['opened' => false, 'error' => 'Xcode open only on macOS']);
            return;
        }

        $escaped = escapeshellarg($fullReal);
        $command = 'open -a Xcode ' . $escaped;
        $output = [];
        $returnVar = 0;
        @exec($command . ' 2>&1', $output, $returnVar);

        echo json_encode(['opened' => ($returnVar === 0), 'path' => $path]);
    }

    /**
     * Audit terminal: suggested vs executed (user-confirmed only).
     */
    private function handleAuditTerminal() {
        $data = json_decode(file_get_contents('php://input'), true);
        $type = $data['type'] ?? 'suggested'; // suggested | executed
        $command = $data['command'] ?? '';
        $event = 'TERMINAL_' . strtoupper($type);

        $db = Database::getInstance();
        $db->execute(
            "INSERT INTO audit_logs (user_id, event, actor, details) VALUES (:user_id, :event, 'USER', :details)",
            [
                ':user_id' => $this->auth->getUserId(),
                ':event' => $event,
                ':details' => json_encode(['command' => $command])
            ]
        );
        echo json_encode(['success' => true]);
    }

    /**
     * Execute terminal command (restricted allowlist: open -a AppName on macOS only).
     */
    private function handleTerminalExecute() {
        if (!$this->requireRomaVerified('terminal_execute')) {
            return;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $command = trim($data['command'] ?? '');
        if (empty($command)) {
            http_response_code(400);
            echo json_encode(['executed' => false, 'error' => 'No command']);
            return;
        }
        // Only allow: open -a AppName (macOS app launch). Strict allowlist.
        $allowed = (PHP_OS_FAMILY === 'Darwin' || strtoupper(substr(PHP_OS, 0, 3)) === 'DAR')
            && preg_match('/^open\s+-a\s+("[^"]+"|\'[^\']+\'|[A-Za-z0-9_.-]+)$/', $command);
        if (!$allowed) {
            echo json_encode(['executed' => false, 'error' => 'Command not in allowlist (only "open -a AppName" on macOS)']);
            return;
        }
        $output = [];
        $returnVar = 0;
        @exec($command . ' 2>&1', $output, $returnVar);
        echo json_encode(['executed' => ($returnVar === 0), 'output' => implode("\n", $output)]);
    }

    /**
     * Fetch URL HTML for TruAi examination (proxy to bypass CORS). Safe allowlist.
     */
    private function handleUrlFetch() {
        $url = $_GET['url'] ?? '';
        $url = trim($url);
        if (empty($url) || !preg_match('#^https?://#i', $url)) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid http(s) URL required']);
            return;
        }
        $parsed = parse_url($url);
        if (empty($parsed['host']) || in_array(strtolower($parsed['host']), ['localhost', '127.0.0.1', '::1'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Remote URLs only']);
            return;
        }
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 15,
                'follow_location' => 1,
                'user_agent' => 'TruAi/1.0 (examination)'
            ]
        ]);
        $html = @file_get_contents($url, false, $ctx);
        if ($html === false) {
            http_response_code(502);
            echo json_encode(['error' => 'Could not fetch URL']);
            return;
        }
        if (strlen($html) > 1024 * 1024) {
            $html = substr($html, 0, 1024 * 1024) . "\n\n<!-- truncated -->";
        }
        header('Content-Type: application/json');
        echo json_encode(['html' => $html, 'url' => $url]);
    }

    /**
     * Suggest edits to HTML source via AI.
     */
    private function handleUrlSuggestEdits() {
        $data = json_decode(file_get_contents('php://input'), true);
        $html = $data['html'] ?? '';
        $instruction = trim($data['instruction'] ?? '');
        if (empty($html) || empty($instruction)) {
            http_response_code(400);
            echo json_encode(['error' => 'html and instruction required']);
            return;
        }
        if (strlen($html) > 500000) {
            http_response_code(400);
            echo json_encode(['error' => 'HTML too large']);
            return;
        }
        require_once __DIR__ . '/ai_client.php';
        $ai = new AIClient();
        $prompt = "Apply this edit instruction to the HTML below. Output ONLY the modified HTML, no explanation.\n\nInstruction: " . $instruction . "\n\nHTML:\n" . $html;
        try {
            $result = $ai->generateCode($prompt, 'gpt-4');
            echo json_encode(['suggested' => $result]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function handleGetConversations() {
        require_once __DIR__ . '/chat_service.php';
        $service = new ChatService();
        $conversations = $service->getConversations($this->auth->getUserId());
        echo json_encode(['conversations' => $conversations]);
    }

    private function handleGetConversation($conversationId) {
        require_once __DIR__ . '/chat_service.php';
        $service = new ChatService();
        $conversation = $service->getConversation($conversationId);
        
        if ($conversation) {
            echo json_encode($conversation);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Conversation not found']);
        }
    }

    private function handleDeleteConversation($conversationId) {
        require_once __DIR__ . '/chat_service.php';
        $service = new ChatService();
        try {
            $service->deleteConversation($conversationId, $this->auth->getUserId());
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function handlePatchConversation($conversationId) {
        $data = json_decode(file_get_contents('php://input'), true);
        $title = isset($data['title']) ? trim($data['title']) : '';
        if ($title === '') {
            http_response_code(400);
            echo json_encode(['error' => 'title required']);
            return;
        }
        require_once __DIR__ . '/chat_service.php';
        $service = new ChatService();
        try {
            $service->updateConversationTitle($conversationId, $this->auth->getUserId(), $title);
            echo json_encode(['success' => true, 'title' => $title]);
        } catch (Exception $e) {
            http_response_code(404);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function handlePatchMessage($messageId) {
        $data = json_decode(file_get_contents('php://input'), true);
        $content = isset($data['content']) ? $data['content'] : '';
        $conversationId = isset($data['conversation_id']) ? $data['conversation_id'] : null;
        if ($conversationId === null) {
            http_response_code(400);
            echo json_encode(['error' => 'conversation_id required']);
            return;
        }
        require_once __DIR__ . '/chat_service.php';
        $service = new ChatService();
        try {
            $service->updateMessage($messageId, $conversationId, $this->auth->getUserId(), $content);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(404);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function handleGetAuditLogs() {
        $db = Database::getInstance();
        $logs = $db->query(
            "SELECT * FROM audit_logs 
             WHERE user_id = :user_id 
             ORDER BY timestamp DESC 
             LIMIT 100",
            [':user_id' => $this->auth->getUserId()]
        );
        
        echo json_encode(['logs' => $logs]);
    }

    private function handleTestAI() {
        require_once __DIR__ . '/ai_client.php';
        $aiClient = new AIClient();
        
        try {
            $results = $aiClient->testConnection();
            echo json_encode([
                'success' => true,
                'results' => $results
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function handleTestAIKeys() {
        require_once __DIR__ . '/ai_client.php';
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $openaiKey = isset($data['openaiApiKey']) ? trim((string)$data['openaiApiKey']) : '';
        $anthropicKey = isset($data['anthropicApiKey']) ? trim((string)$data['anthropicApiKey']) : '';
        $aiClient = new AIClient($openaiKey, $anthropicKey);
        try {
            $results = $aiClient->testConnection();
            echo json_encode([
                'success' => true,
                'results' => $results
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function handleGetSettings() {
        require_once __DIR__ . '/settings_service.php';
        $service = new SettingsService();
        $settings = $service->getSettings($this->auth->getUserId());
        echo json_encode(['settings' => $settings]);
    }

    private function handleSaveSettings() {
        require_once __DIR__ . '/settings_service.php';
        $service = new SettingsService();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['settings'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Settings data required']);
            return;
        }

        try {
            $service->saveSettings($this->auth->getUserId(), $data['settings']);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function handleResetSettings() {
        require_once __DIR__ . '/settings_service.php';
        $service = new SettingsService();
        
        try {
            $service->resetSettings($this->auth->getUserId());
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function handleClearConversations() {
        require_once __DIR__ . '/settings_service.php';
        $service = new SettingsService();
        
        try {
            $service->clearConversations($this->auth->getUserId());
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    private function handleGetLearningStats() {
        require_once __DIR__ . '/learning_service.php';
        $service = new LearningService();
        $stats = $service->getLearningStats($this->auth->getUserId());
        echo json_encode($stats);
    }
    
    private function handleGetHighROI() {
        require_once __DIR__ . '/learning_service.php';
        $service = new LearningService();
        $limit = $_GET['limit'] ?? 10;
        $interactions = $service->getHighROIInteractions($limit);
        echo json_encode(['success' => true, 'interactions' => $interactions]);
    }
    
    private function handleGetAdaptations() {
        require_once __DIR__ . '/learning_service.php';
        $service = new LearningService();
        $prompt = $_GET['prompt'] ?? '';
        if (empty($prompt)) {
            http_response_code(400);
            echo json_encode(['error' => 'Prompt parameter required']);
            return;
        }
        $suggestions = $service->getAdaptationSuggestions($prompt);
        echo json_encode(['success' => true, 'suggestions' => $suggestions]);
    }
}
