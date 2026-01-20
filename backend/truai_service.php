<?php
/**
 * TruAi Core Service
 * 
 * Main AI orchestration service implementing TruAi Core logic
 * 
 * @package TruAi
 * @version 1.0.0
 */

class TruAiService {
    private $db;
    private $riskEngine;
    private $tierRouter;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->riskEngine = new RiskEngine();
        $this->tierRouter = new TierRouter();
    }

    /**
     * Create a new task with personality governance
     */
    public function createTask($userId, $prompt, $context = null, $preferredTier = 'auto') {
        if (empty($prompt)) {
            throw new Exception('Prompt is required');
        }

        // NEW: Context Assembly with Dependency Inference
        $inferredDependencies = $this->inferDependencies($prompt, $context);
        
        // Evaluate risk with production-safe bias
        $riskLevel = $this->riskEngine->evaluate($prompt, $context);
        
        // NEW: Strategic Evaluation - ROI, scope, long-term cost
        $strategicDecision = $this->evaluateStrategy($prompt, $riskLevel, $inferredDependencies);
        
        // Assign tier with governance-first approach
        $tier = ($preferredTier === 'auto') 
            ? $this->tierRouter->assign($riskLevel) 
            : $preferredTier;

        // Generate task ID
        $taskId = 'task_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 8);

        // Store task with strategic context
        $this->db->execute(
            "INSERT INTO tasks (id, user_id, prompt, risk_level, tier, status, context, strategic_context) 
             VALUES (:id, :user_id, :prompt, :risk, :tier, :status, :context, :strategic_context)",
            [
                ':id' => $taskId,
                ':user_id' => $userId,
                ':prompt' => $prompt,
                ':risk' => $riskLevel,
                ':tier' => $tier,
                ':status' => 'CREATED',
                ':context' => $context ? json_encode($context) : null,
                ':strategic_context' => json_encode($strategicDecision)
            ]
        );

        // Audit log
        $this->auditLog($userId, 'TASK_CREATED', 'SYSTEM', [
            'task_id' => $taskId,
            'risk_level' => $riskLevel,
            'tier' => $tier,
            'inferred_dependencies' => $inferredDependencies
        ]);

        // Auto-execute low-risk and medium-risk tasks immediately
        $autoExecute = ($riskLevel === RISK_LOW || $riskLevel === RISK_MEDIUM);
        
        if ($autoExecute) {
            try {
                // Execute immediately
                $execution = $this->executeTask($taskId);
                
                // Return with immediate output
                return [
                    'task_id' => $taskId,
                    'risk_level' => $riskLevel,
                    'assigned_tier' => $tier,
                    'status' => 'EXECUTED',
                    'output' => $execution['output'],
                    'execution_id' => $execution['execution_id'],
                    'auto_executed' => true
                ];
            } catch (Exception $e) {
                error_log('Auto-execution failed: ' . $e->getMessage());
                // Fall through to return CREATED status
            }
        }

        return [
            'task_id' => $taskId,
            'risk_level' => $riskLevel,
            'assigned_tier' => $tier,
            'status' => 'CREATED'
        ];
        // Auto-execute based on risk tier personality rules
        return $this->applyPersonalityExecution($taskId, $riskLevel, $tier);
    }

    /**
     * Get task details
     */
    public function getTask($taskId) {
        $result = $this->db->query(
            "SELECT * FROM tasks WHERE id = :id LIMIT 1",
            [':id' => $taskId]
        );

        if (empty($result)) {
            return null;
        }

        $task = $result[0];
        
        // Get executions
        $executions = $this->db->query(
            "SELECT * FROM executions WHERE task_id = :task_id ORDER BY created_at DESC",
            [':task_id' => $taskId]
        );

        $task['executions'] = $executions;
        
        // Include latest execution output for convenience
        if (!empty($executions)) {
            $latestExecution = $executions[0];
            
            // Get artifact content if output_artifact exists
            if (!empty($latestExecution['output_artifact'])) {
                $artifact = $this->db->query(
                    "SELECT content FROM artifacts WHERE id = :id LIMIT 1",
                    [':id' => $latestExecution['output_artifact']]
                );
                
                if (!empty($artifact)) {
                    $task['output'] = $artifact[0]['content'];
                }
            }
        }
        
        return $task;
    }

    /**
     * Execute a task using AI
     */
    public function executeTask($taskId) {
        $task = $this->getTask($taskId);
        
        if (!$task) {
            throw new Exception('Task not found');
        }

        if ($task['status'] !== 'CREATED' && $task['status'] !== 'APPROVED') {
            throw new Exception('Task cannot be executed in current state');
        }

        // Generate execution ID
        $execId = 'exec_' . time() . '_' . substr(md5(uniqid()), 0, 6);

        // Simulate AI execution (placeholder for actual AI integration)
        $model = $this->getModelForTier($task['tier']);
        $output = $this->simulateAIExecution($task['prompt'], $model);

        // Store execution
        $artifactId = 'artifact_' . date('Ymd_His');
        
        $this->db->execute(
            "INSERT INTO executions (id, task_id, model, output_artifact, status) 
             VALUES (:id, :task_id, :model, :artifact, :status)",
            [
                ':id' => $execId,
                ':task_id' => $taskId,
                ':model' => $model,
                ':artifact' => $artifactId,
                ':status' => 'COMPLETED'
            ]
        );

        // Store artifact
        $this->db->execute(
            "INSERT INTO artifacts (id, task_id, type, content) 
             VALUES (:id, :task_id, :type, :content)",
            [
                ':id' => $artifactId,
                ':task_id' => $taskId,
                ':type' => 'CODE',
                ':content' => $output
            ]
        );

        // Update task status
        $this->db->execute(
            "UPDATE tasks SET status = :status WHERE id = :id",
            [':status' => 'EXECUTED', ':id' => $taskId]
        );

        return [
            'execution_id' => $execId,
            'model_used' => $model,
            'output_artifact' => $artifactId,
            'output' => $output,
            'status' => 'COMPLETED'
        ];
    }

    /**
     * Approve or reject a task
     */
    public function approveTask($taskId, $action, $target = 'production') {
        $task = $this->getTask($taskId);
        
        if (!$task) {
            throw new Exception('Task not found');
        }

        $newStatus = match($action) {
            'APPROVE' => 'APPROVED',
            'REJECT' => 'REJECTED',
            'SAVE_ONLY' => 'SAVED',
            default => throw new Exception('Invalid action')
        };

        $this->db->execute(
            "UPDATE tasks SET status = :status WHERE id = :id",
            [':status' => $newStatus, ':id' => $taskId]
        );

        $this->auditLog(null, 'TASK_' . $action, 'ADMIN', [
            'task_id' => $taskId,
            'target' => $target
        ]);

        return [
            'task_id' => $taskId,
            'action' => $action,
            'status' => $newStatus,
            'target' => $target
        ];
    }

    private function getModelForTier($tier) {
        return match($tier) {
            AI_TIER_CHEAP => 'gpt-3.5-turbo',
            AI_TIER_MID => 'gpt-4',
            AI_TIER_HIGH => 'gpt-4-turbo',
            default => 'gpt-3.5-turbo'
        };
    }

    private function simulateAIExecution($prompt, $model) {
        // Call actual AI API with proper exception handling
        require_once __DIR__ . '/ai_client.php';
        require_once __DIR__ . '/ai_exceptions.php';
        $aiClient = new AIClient();
        
        try {
            $response = $aiClient->generateCode($prompt, $model);
            return $response;
        } catch (AIConfigurationException $e) {
            error_log('AI Configuration Error: ' . $e->getMessage());
            throw new Exception('AI service not configured. Please check API keys in Settings.');
        } catch (AIRateLimitException $e) {
            error_log('AI Rate Limit: ' . $e->getMessage());
            $retryAfter = $e->getRetryAfter();
            $message = 'AI service rate limit exceeded. Please try again';
            if ($retryAfter) {
                $message .= " in {$retryAfter} seconds";
            } else {
                $message .= ' later';
            }
            throw new Exception($message . '.');
        } catch (AITimeoutException $e) {
            error_log('AI Timeout: ' . $e->getMessage());
            throw new Exception('AI service request timed out. Please try again.');
        } catch (AITransientException $e) {
            error_log('AI Transient Error: ' . $e->getMessage());
            throw new Exception('AI service temporarily unavailable. Please try again.');
        } catch (AIResponseException $e) {
            error_log('AI Response Error: ' . $e->getMessage());
            throw new Exception('AI service returned invalid response. Please try again or contact support.');
        } catch (AIException $e) {
            error_log('AI Error: ' . $e->getMessage());
            throw new Exception('AI service error: ' . $e->getMessage());
        } catch (Exception $e) {
            error_log('Unexpected error in AI execution: ' . $e->getMessage());
            throw new Exception('Unexpected error occurred. Please try again or contact support.');
        }
    }

    private function auditLog($userId, $event, $actor, $details) {
        $this->db->execute(
            "INSERT INTO audit_logs (user_id, event, actor, details) 
             VALUES (:user_id, :event, :actor, :details)",
            [
                ':user_id' => $userId,
                ':event' => $event,
                ':actor' => $actor,
                ':details' => json_encode($details)
            ]
        );
    }

    /**
     * Infer next dependencies before execution
     */
    private function inferDependencies($prompt, $context) {
        // Analyze prompt for implicit dependencies
        $dependencies = [];
        
        $patterns = [
            '/create\s+(\w+)/i' => 'May require schema/model definition',
            '/deploy/i' => 'Requires build, test, staging validation',
            '/update\s+(\w+)/i' => 'May affect dependent modules',
            '/refactor/i' => 'May require test updates',
            '/add\s+feature/i' => 'May require documentation, tests'
        ];
        
        foreach ($patterns as $pattern => $dependency) {
            if (preg_match($pattern, $prompt)) {
                $dependencies[] = $dependency;
            }
        }
        
        return $dependencies;
    }

    /**
     * Strategic evaluation with production-safe bias
     */
    private function evaluateStrategy($prompt, $riskLevel, $dependencies) {
        return [
            'execution_bias' => 'production-safe',
            'approach' => 'one_optimal_path', // No alternatives unless materially different
            'suppress_exploration' => true,
            'dependencies' => $dependencies,
            'roi_assessment' => $this->assessROI($prompt),
            'scope_creep_risk' => $this->assessScopeCreep($prompt),
            'long_term_cost' => $this->assessLongTermCost($prompt, $riskLevel)
        ];
    }

    /**
     * Apply personality-driven execution rules
     */
    private function applyPersonalityExecution($taskId, $riskLevel, $tier) {
        // SAFE tier (🟢) - Silent execution
        if ($riskLevel === RISK_LOW) {
            try {
                $execution = $this->executeTask($taskId);
                return [
                    'task_id' => $taskId,
                    'status' => 'EXECUTED',
                    'output' => $execution['output'],
                    'execution_mode' => 'silent_auto',
                    'ui_interruption' => false, // No UI noise
                    'explanation' => null // Suppress explanation
                ];
            } catch (Exception $e) {
                error_log('Silent execution failed: ' . $e->getMessage());
                // Return task info if execution fails
                return [
                    'task_id' => $taskId,
                    'risk_level' => $riskLevel,
                    'assigned_tier' => $tier,
                    'status' => 'CREATED'
                ];
            }
        }
        
        // ELEVATED tier (🟡) - Speak once, concisely
        if ($riskLevel === RISK_MEDIUM) {
            return [
                'task_id' => $taskId,
                'status' => 'CREATED',
                'risk_level' => $riskLevel,
                'assigned_tier' => $tier,
                'ui_interruption' => 'side_panel', // Side panel, not modal
                'requires_approval' => true,
                'explanation' => $this->generateMinimalExplanation($tier), // ONE short rationale
                'approval_prompt' => 'Approve to execute. Reject to cancel.'
            ];
        }
        
        // LOCKED tier (🔴) - Halt execution, require admin
        if ($riskLevel === RISK_HIGH) {
            return [
                'task_id' => $taskId,
                'status' => 'LOCKED',
                'risk_level' => $riskLevel,
                'assigned_tier' => $tier,
                'ui_interruption' => 'modal_blocking', // Modal interrupt
                'halt_reason' => $this->generateStopReason($riskLevel),
                'requires_admin' => true,
                'kill_switch_visible' => true
            ];
        }
        
        // Default fallback
        return [
            'task_id' => $taskId,
            'risk_level' => $riskLevel,
            'assigned_tier' => $tier,
            'status' => 'CREATED'
        ];
    }

    /**
     * Generate minimal explanation (ELEVATED tier only)
     */
    private function generateMinimalExplanation($tier) {
        // ONE sentence max - generic but appropriate for medium risk
        return "Multi-file change detected. Review before execution.";
    }

    /**
     * Generate clear stop reason (LOCKED tier only)
     */
    private function generateStopReason($riskLevel) {
        // Clear reason for high-risk halt
        return "Production secrets or policy violation detected. Admin approval required.";
    }

    private function assessROI($prompt) {
        // Placeholder - can be enhanced
        return 'medium';
    }

    private function assessScopeCreep($prompt) {
        // Check for overly broad requests
        $broad_keywords = ['redesign', 'rewrite', 'overhaul', 'complete'];
        foreach ($broad_keywords as $keyword) {
            if (stripos($prompt, $keyword) !== false) {
                return 'high';
            }
        }
        return 'low';
    }

    private function assessLongTermCost($prompt, $riskLevel) {
        // Higher risk = higher long-term cost
        // Using switch for PHP 7 compatibility
        switch ($riskLevel) {
            case RISK_LOW:
                return 'minimal';
            case RISK_MEDIUM:
                return 'moderate';
            case RISK_HIGH:
                return 'significant';
            default:
                return 'unknown';
        }
    }
}

/**
 * Risk Engine - Evaluates task risk level with personality governance
 */
class RiskEngine {
    public function evaluate($prompt, $context = null) {
        $prompt = strtolower($prompt);
        
        // LOCKED tier (🔴) - Halt execution
        $lockedKeywords = [
            'deploy to production', 'delete production', 'drop production',
            'remove production', 'production secret', 'api key', 'password',
            'credential', 'token', 'private key', 'production database',
            'license violation', 'copyright', 'proprietary'
        ];
        
        foreach ($lockedKeywords as $keyword) {
            if (strpos($prompt, $keyword) !== false) {
                return RISK_HIGH;
            }
        }
        
        // ELEVATED tier (🟡) - Speak once, require approval
        $elevatedKeywords = [
            'modify', 'update', 'change', 'refactor', 'config',
            'dependency', 'multi-file', 'security', 'auth'
        ];
        
        foreach ($elevatedKeywords as $keyword) {
            if (strpos($prompt, $keyword) !== false) {
                return RISK_MEDIUM;
            }
        }
        
        // SAFE tier (🟢) - Silent execution
        // Default to SAFE for formatting, tests, known patterns
        return RISK_LOW;
    }
}

/**
 * Tier Router - Assigns appropriate AI tier based on risk
 */
class TierRouter {
    public function assign($riskLevel) {
        return match($riskLevel) {
            RISK_LOW => AI_TIER_CHEAP,
            RISK_MEDIUM => AI_TIER_MID,
            RISK_HIGH => AI_TIER_HIGH,
            default => AI_TIER_CHEAP
        };
    }
}
