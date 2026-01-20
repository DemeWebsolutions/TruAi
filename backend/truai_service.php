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
            "SELECT * FROM executions WHERE task_id = :task_id",
            [':task_id' => $taskId]
        );

        $task['executions'] = $executions;
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
        // Call actual AI API
        require_once __DIR__ . '/ai_client.php';
        $aiClient = new AIClient();
        
        try {
            $response = $aiClient->generateCode($prompt, $model);
            return $response;
        } catch (Exception $e) {
            error_log('AI execution failed: ' . $e->getMessage());
            // Fallback to simulated response if API fails
            return "// AI execution failed. Please check API configuration.\n" .
                   "// Error: " . $e->getMessage() . "\n" .
                   "// Prompt: " . substr($prompt, 0, 50) . "...\n";
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
        $task = $this->getTask($taskId);
        
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
                'explanation' => $this->generateMinimalExplanation($task), // ONE short rationale
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
                'halt_reason' => $this->generateStopReason($task),
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
    private function generateMinimalExplanation($task) {
        // ONE sentence max
        return sprintf(
            "Multi-file change detected. Review before execution."
        );
    }

    /**
     * Generate clear stop reason (LOCKED tier only)
     */
    private function generateStopReason($task) {
        return sprintf(
            "Production secrets or policy violation detected. Admin approval required."
        );
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
        return match($riskLevel) {
            RISK_LOW => 'minimal',
            RISK_MEDIUM => 'moderate',
            RISK_HIGH => 'significant',
            default => 'unknown'
        };
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
