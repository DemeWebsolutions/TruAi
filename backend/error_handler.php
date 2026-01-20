<?php
/**
 * TruAi Error Handler
 * 
 * Centralized error handling and logging with security safeguards
 * 
 * @package TruAi
 * @version 1.0.0
 */

class ErrorHandler {
    private static $db = null;
    
    // Sensitive patterns to redact from error messages
    private static $sensitivePatterns = [
        '/sk-[a-zA-Z0-9]{32,}/',           // OpenAI API keys
        '/sk-ant-[a-zA-Z0-9-]{32,}/',      // Anthropic API keys
        '/api[_-]?key["\']?\s*[:=]\s*["\']?[a-zA-Z0-9-_]+/', // Generic API keys
        '/password["\']?\s*[:=]\s*["\']?[^"\']+/',           // Passwords
        '/token["\']?\s*[:=]\s*["\']?[a-zA-Z0-9-_\.]+/',     // Tokens
        '/Bearer\s+[a-zA-Z0-9-_\.]+/',     // Bearer tokens
        '/secret["\']?\s*[:=]\s*["\']?[^"\']+/',            // Secrets
    ];
    
    /**
     * Sanitize error message by removing sensitive information
     */
    public static function sanitizeError($error, $context = []) {
        $message = is_string($error) ? $error : ($error->getMessage() ?? 'Unknown error');
        
        // Redact sensitive patterns
        foreach (self::$sensitivePatterns as $pattern) {
            $message = preg_replace($pattern, '[REDACTED]', $message);
        }
        
        // Remove full file paths
        $message = preg_replace('/\/[\/\w\-\.]+\//', '[PATH]/', $message);
        
        return $message;
    }
    
    /**
     * Log error to database and error log file
     */
    public static function logError($error, $userId = null, $context = []) {
        try {
            if (self::$db === null) {
                self::$db = Database::getInstance();
            }
            
            $errorType = get_class($error) === 'Exception' ? 'Exception' : get_class($error);
            $errorMessage = self::sanitizeError($error);
            $stackTrace = self::sanitizeError($error->getTraceAsString());
            $requestPath = $_SERVER['REQUEST_URI'] ?? null;
            
            // Log to database
            self::$db->execute(
                "INSERT INTO error_logs (error_type, error_message, stack_trace, user_id, request_path) 
                 VALUES (:type, :message, :trace, :user_id, :path)",
                [
                    ':type' => $errorType,
                    ':message' => $errorMessage,
                    ':trace' => $stackTrace,
                    ':user_id' => $userId,
                    ':path' => $requestPath
                ]
            );
            
            // Log to PHP error log (sanitized)
            error_log("TruAi Error [$errorType]: $errorMessage");
            
        } catch (Exception $e) {
            // Fallback to PHP error log if database logging fails
            error_log('Failed to log error to database: ' . $e->getMessage());
            error_log('Original error: ' . self::sanitizeError($error));
        }
    }
    
    /**
     * Format user-friendly error response
     */
    public static function formatUserError($error, $errorCode = null) {
        $message = self::sanitizeError($error);
        
        // Map specific error types to user-friendly messages
        $userMessage = $message;
        $retryAfter = null;
        
        if ($error instanceof AIConfigurationException) {
            $errorCode = $errorCode ?? 'AI_CONFIGURATION_ERROR';
        } elseif ($error instanceof AIRateLimitException) {
            $errorCode = $errorCode ?? 'AI_RATE_LIMIT';
            $retryAfter = $error->getRetryAfter();
        } elseif ($error instanceof AITimeoutException) {
            $errorCode = $errorCode ?? 'AI_TIMEOUT';
        } elseif ($error instanceof AITransientException) {
            $errorCode = $errorCode ?? 'AI_TRANSIENT_ERROR';
        } elseif ($error instanceof AIResponseException) {
            $errorCode = $errorCode ?? 'AI_RESPONSE_ERROR';
        } elseif ($error instanceof AIException) {
            $errorCode = $errorCode ?? 'AI_ERROR';
        } else {
            $errorCode = $errorCode ?? 'INTERNAL_ERROR';
        }
        
        $response = [
            'success' => false,
            'error' => $userMessage,
            'error_code' => $errorCode
        ];
        
        if ($retryAfter !== null) {
            $response['retry_after'] = $retryAfter;
        }
        
        return $response;
    }
    
    /**
     * Handle API error and return appropriate response
     */
    public static function handleApiError($error, $userId = null) {
        // Log the error
        self::logError($error, $userId);
        
        // Format user-friendly response
        $response = self::formatUserError($error);
        
        // Determine HTTP status code
        $statusCode = 500;
        if ($error instanceof AIConfigurationException) {
            $statusCode = 400; // Bad request - configuration issue
        } elseif ($error instanceof AIRateLimitException) {
            $statusCode = 429; // Too many requests
        } elseif ($error instanceof AITimeoutException) {
            $statusCode = 504; // Gateway timeout
        } elseif ($error instanceof AITransientException) {
            $statusCode = 503; // Service unavailable
        }
        
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($response);
    }
    
    /**
     * Track API metrics (success or failure)
     */
    public static function trackApiMetrics($provider, $model, $success = true, $tokensUsed = 0) {
        try {
            if (self::$db === null) {
                self::$db = Database::getInstance();
            }
            
            $today = date('Y-m-d');
            
            // Check if record exists for today
            $existing = self::$db->query(
                "SELECT id FROM api_metrics WHERE date = :date AND provider = :provider AND model = :model LIMIT 1",
                [
                    ':date' => $today,
                    ':provider' => $provider,
                    ':model' => $model
                ]
            );
            
            if (empty($existing)) {
                // Insert new record
                self::$db->execute(
                    "INSERT INTO api_metrics (date, provider, model, requests_count, tokens_total, errors_count) 
                     VALUES (:date, :provider, :model, 1, :tokens, :errors)",
                    [
                        ':date' => $today,
                        ':provider' => $provider,
                        ':model' => $model,
                        ':tokens' => $tokensUsed,
                        ':errors' => $success ? 0 : 1
                    ]
                );
            } else {
                // Update existing record
                self::$db->execute(
                    "UPDATE api_metrics 
                     SET requests_count = requests_count + 1,
                         tokens_total = tokens_total + :tokens,
                         errors_count = errors_count + :errors
                     WHERE date = :date AND provider = :provider AND model = :model",
                    [
                        ':date' => $today,
                        ':provider' => $provider,
                        ':model' => $model,
                        ':tokens' => $tokensUsed,
                        ':errors' => $success ? 0 : 1
                    ]
                );
            }
        } catch (Exception $e) {
            // Don't fail the request if metrics tracking fails
            error_log('Failed to track API metrics: ' . $e->getMessage());
        }
    }
    
    /**
     * Log AI request/response for debugging and audit
     */
    public static function logAiRequest($taskId, $userId, $provider, $model, $prompt, $response, $tokensUsed, $latencyMs, $success = true, $errorMessage = null) {
        try {
            if (self::$db === null) {
                self::$db = Database::getInstance();
            }
            
            // Sanitize prompt and response
            $sanitizedPrompt = self::sanitizeError($prompt);
            $sanitizedResponse = $response ? self::sanitizeError($response) : null;
            $sanitizedError = $errorMessage ? self::sanitizeError($errorMessage) : null;
            
            self::$db->execute(
                "INSERT INTO ai_requests (task_id, user_id, provider, model, prompt, response, tokens_used, latency_ms, success, error_message) 
                 VALUES (:task_id, :user_id, :provider, :model, :prompt, :response, :tokens, :latency, :success, :error)",
                [
                    ':task_id' => $taskId,
                    ':user_id' => $userId,
                    ':provider' => $provider,
                    ':model' => $model,
                    ':prompt' => $sanitizedPrompt,
                    ':response' => $sanitizedResponse,
                    ':tokens' => $tokensUsed,
                    ':latency' => $latencyMs,
                    ':success' => $success ? 1 : 0,
                    ':error' => $sanitizedError
                ]
            );
            
            // Track metrics
            self::trackApiMetrics($provider, $model, $success, $tokensUsed);
            
        } catch (Exception $e) {
            // Don't fail the request if logging fails
            error_log('Failed to log AI request: ' . $e->getMessage());
        }
    }
}
