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
     * Create a new task
     */
    public function createTask($userId, $prompt, $context = null, $preferredTier = 'auto') {
        if (empty($prompt)) {
            throw new Exception('Prompt is required');
        }

        // Evaluate risk
        $riskLevel = $this->riskEngine->evaluate($prompt, $context);
        
        // Assign tier
        $tier = ($preferredTier === 'auto') 
            ? $this->tierRouter->assign($riskLevel) 
            : $preferredTier;

        // Generate task ID
        $taskId = 'task_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 8);

        // Store task
        $this->db->execute(
            "INSERT INTO tasks (id, user_id, prompt, risk_level, tier, status, context) 
             VALUES (:id, :user_id, :prompt, :risk, :tier, :status, :context)",
            [
                ':id' => $taskId,
                ':user_id' => $userId,
                ':prompt' => $prompt,
                ':risk' => $riskLevel,
                ':tier' => $tier,
                ':status' => 'CREATED',
                ':context' => $context ? json_encode($context) : null
            ]
        );

        // Audit log
        $this->auditLog($userId, 'TASK_CREATED', 'SYSTEM', [
            'task_id' => $taskId,
            'risk_level' => $riskLevel,
            'tier' => $tier
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
            
            // Get artifact content
            $artifact = $this->db->query(
                "SELECT content FROM artifacts WHERE id = :id LIMIT 1",
                [':id' => $latestExecution['output_artifact']]
            );
            
            if (!empty($artifact)) {
                $task['output'] = $artifact[0]['content'];
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
}

/**
 * Risk Engine - Evaluates task risk level
 */
class RiskEngine {
    public function evaluate($prompt, $context = null) {
        $prompt = strtolower($prompt);
        
        // High-risk keywords
        $highRiskKeywords = [
            'deploy', 'delete', 'drop', 'remove', 'production',
            'database', 'security', 'password', 'credential', 'api key'
        ];
        
        foreach ($highRiskKeywords as $keyword) {
            if (strpos($prompt, $keyword) !== false) {
                return RISK_HIGH;
            }
        }
        
        // Medium-risk keywords
        $mediumRiskKeywords = [
            'modify', 'update', 'change', 'refactor', 'config'
        ];
        
        foreach ($mediumRiskKeywords as $keyword) {
            if (strpos($prompt, $keyword) !== false) {
                return RISK_MEDIUM;
            }
        }
        
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
