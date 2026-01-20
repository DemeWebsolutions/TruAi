<?php
/**
 * TruAi Core Router
 * 
 * Routes API requests to appropriate controllers
 * 
 * @package TruAi
 * @version 1.0.0
 */

class Router {
    private $auth;
    private $routes = [];

    public function __construct() {
        $this->auth = new Auth();
        $this->registerRoutes();
    }

    private function registerRoutes() {
        // Public routes
        $this->routes['GET']['/api/v1/auth/publickey'] = [$this, 'handleGetPublicKey'];
        $this->routes['POST']['/api/v1/auth/login'] = [$this, 'handleLogin'];
        $this->routes['POST']['/api/v1/auth/logout'] = [$this, 'handleLogout'];
        $this->routes['GET']['/api/v1/auth/status'] = [$this, 'handleAuthStatus'];

        // Protected routes (require authentication)
        $this->routes['POST']['/api/v1/task/create'] = [$this, 'handleTaskCreate'];
        $this->routes['GET']['/api/v1/task/{id}'] = [$this, 'handleTaskGet'];
        $this->routes['POST']['/api/v1/task/execute'] = [$this, 'handleTaskExecute'];
        $this->routes['POST']['/api/v1/task/approve'] = [$this, 'handleTaskApprove'];
        
        $this->routes['POST']['/api/v1/chat/message'] = [$this, 'handleChatMessage'];
        $this->routes['GET']['/api/v1/chat/conversations'] = [$this, 'handleGetConversations'];
        $this->routes['GET']['/api/v1/chat/conversation/{id}'] = [$this, 'handleGetConversation'];
        
        $this->routes['GET']['/api/v1/audit/logs'] = [$this, 'handleGetAuditLogs'];
        
        // AI test endpoint
        $this->routes['GET']['/api/v1/ai/test'] = [$this, 'handleTestAI'];
        
        // Settings endpoints
        $this->routes['GET']['/api/v1/settings'] = [$this, 'handleGetSettings'];
        $this->routes['POST']['/api/v1/settings'] = [$this, 'handleSaveSettings'];
        $this->routes['POST']['/api/v1/settings/reset'] = [$this, 'handleResetSettings'];
        $this->routes['POST']['/api/v1/settings/clear-conversations'] = [$this, 'handleClearConversations'];
        
        // Learning system endpoints
        $this->routes['POST']['/api/v1/learning/event'] = [$this, 'handleLearningEvent'];
        $this->routes['POST']['/api/v1/learning/feedback'] = [$this, 'handleLearningFeedback'];
        $this->routes['POST']['/api/v1/learning/correction'] = [$this, 'handleLearningCorrection'];
        $this->routes['GET']['/api/v1/learning/patterns'] = [$this, 'handleLearningPatterns'];
        $this->routes['GET']['/api/v1/learning/insights'] = [$this, 'handleLearningInsights'];
        $this->routes['POST']['/api/v1/learning/suggest'] = [$this, 'handleLearningSuggest'];
        $this->routes['DELETE']['/api/v1/learning/reset'] = [$this, 'handleLearningReset'];
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
            $allowedOrigins = ['http://localhost:8080', 'http://127.0.0.1:8080', 'http://localhost', 'http://127.0.0.1'];
            
            // Allow credentials only from allowed origins
            if (in_array($requestOrigin, $allowedOrigins)) {
                header('Access-Control-Allow-Origin: ' . $requestOrigin);
                header('Access-Control-Allow-Credentials: true'); // CRITICAL: Allows cookies to be sent
            } else {
                // Fallback to default for same-origin requests
                header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
            }
            
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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
                    $route !== '/api/v1/ai/test') {
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

    private function handleAuthStatus() {
        if ($this->auth->isAuthenticated()) {
            echo json_encode([
                'authenticated' => true,
                'username' => $this->auth->getUsername()
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
        
        $result = $service->sendMessage(
            $this->auth->getUserId(),
            $data['conversation_id'] ?? null,
            $data['message'] ?? '',
            $data['model'] ?? 'auto'
        );
        
        echo json_encode($result);
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
    
    // Learning System Handlers
    
    private function handleLearningEvent() {
        require_once __DIR__ . '/learning_service.php';
        $service = new LearningService();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['event_type']) || !isset($data['context'])) {
            http_response_code(400);
            echo json_encode(['error' => 'event_type and context required']);
            return;
        }
        
        try {
            $service->recordEvent(
                $this->auth->getUserId(),
                $data['event_type'],
                $data['context']
            );
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    private function handleLearningFeedback() {
        require_once __DIR__ . '/learning_service.php';
        $service = new LearningService();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['task_id']) || !isset($data['score'])) {
            http_response_code(400);
            echo json_encode(['error' => 'task_id and score required']);
            return;
        }
        
        try {
            $service->recordFeedback(
                $this->auth->getUserId(),
                $data['task_id'],
                intval($data['score'])
            );
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    private function handleLearningCorrection() {
        require_once __DIR__ . '/learning_service.php';
        $service = new LearningService();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['task_id']) || !isset($data['original_response']) || !isset($data['corrected_response'])) {
            http_response_code(400);
            echo json_encode(['error' => 'task_id, original_response, and corrected_response required']);
            return;
        }
        
        try {
            $service->recordCorrection(
                $this->auth->getUserId(),
                $data['task_id'],
                $data['original_response'],
                $data['corrected_response']
            );
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    private function handleLearningPatterns() {
        require_once __DIR__ . '/learning_service.php';
        $service = new LearningService();
        
        $patternType = $_GET['type'] ?? null;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
        
        try {
            $patterns = $service->getLearnedPatterns(
                $this->auth->getUserId(),
                $patternType,
                $limit
            );
            echo json_encode(['patterns' => $patterns]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    private function handleLearningInsights() {
        require_once __DIR__ . '/learning_service.php';
        $service = new LearningService();
        
        try {
            $insights = $service->analyzeUserPreferences($this->auth->getUserId());
            echo json_encode(['insights' => $insights]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    private function handleLearningSuggest() {
        require_once __DIR__ . '/learning_service.php';
        $service = new LearningService();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['prompt'])) {
            http_response_code(400);
            echo json_encode(['error' => 'prompt required']);
            return;
        }
        
        try {
            $suggestions = $service->suggestImprovement(
                $this->auth->getUserId(),
                $data['prompt'],
                $data['context'] ?? []
            );
            echo json_encode(['suggestions' => $suggestions]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    private function handleLearningReset() {
        require_once __DIR__ . '/learning_service.php';
        $service = new LearningService();
        
        try {
            // Delete all learning data for user
            $db = Database::getInstance();
            $db->execute(
                "DELETE FROM learning_events WHERE user_id = :user_id",
                [':user_id' => $this->auth->getUserId()]
            );
            $db->execute(
                "DELETE FROM learned_patterns WHERE user_id = :user_id",
                [':user_id' => $this->auth->getUserId()]
            );
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
