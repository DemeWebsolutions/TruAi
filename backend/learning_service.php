<?php
/**
 * TruAi Learning Service
 * 
 * Implements persistent learning system for TruAi
 * Learns from user interactions, corrections, and feedback
 * 
 * @package TruAi
 * @version 1.0.0
 */

require_once __DIR__ . '/database.php';

class LearningService {
    private $db;
    private $maxPatternsPerUser = 1000;
    private $pruneAgeThreshold = 90; // days
    private $maxPatternLength = 200; // characters for pattern truncation
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Record a learning event
     * 
     * @param int $userId User ID
     * @param string $eventType Event type (correction, preference, success, failure, feedback)
     * @param array $context Event context including prompt, response, model, etc.
     * @return bool Success status
     */
    public function recordEvent($userId, $eventType, $context) {
        $validTypes = ['correction', 'preference', 'success', 'failure', 'feedback'];
        if (!in_array($eventType, $validTypes)) {
            throw new Exception('Invalid event type');
        }
        
        $this->db->execute(
            "INSERT INTO learning_events (
                user_id, event_type, context, original_prompt, original_response,
                corrected_response, feedback_score, model_used, risk_level, tier
            ) VALUES (
                :user_id, :event_type, :context, :original_prompt, :original_response,
                :corrected_response, :feedback_score, :model_used, :risk_level, :tier
            )",
            [
                ':user_id' => $userId,
                ':event_type' => $eventType,
                ':context' => isset($context['context']) ? json_encode($context['context']) : null,
                ':original_prompt' => $context['original_prompt'] ?? null,
                ':original_response' => $context['original_response'] ?? null,
                ':corrected_response' => $context['corrected_response'] ?? null,
                ':feedback_score' => $context['feedback_score'] ?? null,
                ':model_used' => $context['model_used'] ?? null,
                ':risk_level' => $context['risk_level'] ?? null,
                ':tier' => $context['tier'] ?? null
            ]
        );
        
        // Extract patterns from the event
        $this->extractPatterns($userId, $eventType, $context);
        
        return true;
    }
    
    /**
     * Record a user correction
     * 
     * @param int $userId User ID
     * @param string $taskId Task ID
     * @param string $originalResponse Original AI response
     * @param string $correctedResponse User's corrected version
     * @return bool Success status
     */
    public function recordCorrection($userId, $taskId, $originalResponse, $correctedResponse) {
        // Get task details
        $task = $this->db->query(
            "SELECT * FROM tasks WHERE id = :id LIMIT 1",
            [':id' => $taskId]
        );
        
        if (empty($task)) {
            throw new Exception('Task not found');
        }
        
        $taskData = $task[0];
        
        return $this->recordEvent($userId, 'correction', [
            'original_prompt' => $taskData['prompt'],
            'original_response' => $originalResponse,
            'corrected_response' => $correctedResponse,
            'model_used' => $this->getModelUsedForTask($taskId),
            'risk_level' => $taskData['risk_level'],
            'tier' => $taskData['tier'],
            'context' => [
                'task_id' => $taskId,
                'timestamp' => time()
            ]
        ]);
    }
    
    /**
     * Record user feedback on AI response
     * 
     * @param int $userId User ID
     * @param string $taskId Task ID
     * @param int $score Feedback score (-1, 0, 1)
     * @return bool Success status
     */
    public function recordFeedback($userId, $taskId, $score) {
        if (!in_array($score, [-1, 0, 1])) {
            throw new Exception('Invalid feedback score. Must be -1, 0, or 1.');
        }
        
        // Get task details
        $task = $this->db->query(
            "SELECT * FROM tasks WHERE id = :id LIMIT 1",
            [':id' => $taskId]
        );
        
        if (empty($task)) {
            throw new Exception('Task not found');
        }
        
        $taskData = $task[0];
        
        // Get output from latest execution
        $output = $this->getTaskOutput($taskId);
        
        $eventType = $score > 0 ? 'success' : ($score < 0 ? 'failure' : 'feedback');
        
        return $this->recordEvent($userId, $eventType, [
            'original_prompt' => $taskData['prompt'],
            'original_response' => $output,
            'feedback_score' => $score,
            'model_used' => $this->getModelUsedForTask($taskId),
            'risk_level' => $taskData['risk_level'],
            'tier' => $taskData['tier'],
            'context' => [
                'task_id' => $taskId,
                'timestamp' => time()
            ]
        ]);
    }
    
    /**
     * Get learned patterns for a user
     * 
     * @param int $userId User ID
     * @param string $patternType Optional pattern type filter
     * @param int $limit Maximum number of patterns to return
     * @return array Learned patterns
     */
    public function getLearnedPatterns($userId, $patternType = null, $limit = 10) {
        $sql = "SELECT * FROM learned_patterns WHERE user_id = :user_id";
        $params = [':user_id' => $userId];
        
        if ($patternType) {
            $sql .= " AND pattern_type = :pattern_type";
            $params[':pattern_type'] = $patternType;
        }
        
        $sql .= " ORDER BY confidence_score DESC, success_rate DESC LIMIT :limit";
        $params[':limit'] = $limit;
        
        $patterns = $this->db->query($sql, $params);
        
        // Decode JSON pattern values
        foreach ($patterns as &$pattern) {
            if ($pattern['pattern_value']) {
                $pattern['pattern_value'] = json_decode($pattern['pattern_value'], true);
            }
        }
        
        return $patterns;
    }
    
    /**
     * Update pattern confidence based on success/failure
     * 
     * @param int $patternId Pattern ID
     * @param bool $success Whether the pattern led to success
     * @return bool Success status
     */
    public function updatePatternConfidence($patternId, $success) {
        // Get current pattern
        $pattern = $this->db->query(
            "SELECT * FROM learned_patterns WHERE id = :id LIMIT 1",
            [':id' => $patternId]
        );
        
        if (empty($pattern)) {
            throw new Exception('Pattern not found');
        }
        
        $patternData = $pattern[0];
        
        // Calculate new confidence and success rate
        $usageCount = $patternData['usage_count'] + 1;
        $successCount = $patternData['success_rate'] * $patternData['usage_count'];
        
        if ($success) {
            $successCount += 1;
        }
        
        $newSuccessRate = $successCount / $usageCount;
        
        // Confidence is weighted by usage count and success rate
        // More usage = more confidence, higher success rate = more confidence
        $usageFactor = min($usageCount / 10.0, 1.0); // Cap at 10 uses
        $newConfidence = ($newSuccessRate * 0.7) + ($usageFactor * 0.3);
        
        $this->db->execute(
            "UPDATE learned_patterns SET 
                usage_count = :usage_count,
                success_rate = :success_rate,
                confidence_score = :confidence_score,
                last_used = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = :id",
            [
                ':usage_count' => $usageCount,
                ':success_rate' => $newSuccessRate,
                ':confidence_score' => $newConfidence,
                ':id' => $patternId
            ]
        );
        
        return true;
    }
    
    /**
     * Suggest improvements to a prompt based on learned patterns
     * 
     * @param int $userId User ID
     * @param string $prompt Original prompt
     * @param array $context Additional context
     * @return array Suggestions
     */
    public function suggestImprovement($userId, $prompt, $context = []) {
        $suggestions = [];
        
        // Get relevant patterns
        $patterns = $this->getLearnedPatterns($userId, 'prompt_template', 20);
        
        // Analyze prompt for matching patterns
        foreach ($patterns as $pattern) {
            $patternValue = $pattern['pattern_value'];
            
            if (isset($patternValue['keywords'])) {
                $matchScore = 0;
                foreach ($patternValue['keywords'] as $keyword) {
                    if (stripos($prompt, $keyword) !== false) {
                        $matchScore++;
                    }
                }
                
                // If we have matches, suggest this pattern
                if ($matchScore > 0) {
                    $suggestions[] = [
                        'pattern_id' => $pattern['id'],
                        'suggestion' => $patternValue['template'] ?? null,
                        'confidence' => $pattern['confidence_score'],
                        'match_score' => $matchScore,
                        'description' => $patternValue['description'] ?? 'Learned from previous successful prompts'
                    ];
                }
            }
        }
        
        // Sort by match score and confidence
        usort($suggestions, function($a, $b) {
            $scoreA = $a['match_score'] * $a['confidence'];
            $scoreB = $b['match_score'] * $b['confidence'];
            return $scoreB <=> $scoreA;
        });
        
        // Return top 3 suggestions
        return array_slice($suggestions, 0, 3);
    }
    
    /**
     * Analyze user preferences
     * 
     * @param int $userId User ID
     * @return array User preferences analysis
     */
    public function analyzeUserPreferences($userId) {
        // Get recent successful events
        $events = $this->db->query(
            "SELECT * FROM learning_events 
             WHERE user_id = :user_id 
             AND event_type IN ('success', 'feedback')
             AND feedback_score >= 0
             ORDER BY created_at DESC 
             LIMIT 100",
            [':user_id' => $userId]
        );
        
        // Analyze patterns
        $modelPreferences = [];
        $tierPreferences = [];
        $promptPatterns = [];
        
        foreach ($events as $event) {
            if ($event['model_used']) {
                $modelPreferences[$event['model_used']] = 
                    ($modelPreferences[$event['model_used']] ?? 0) + 1;
            }
            
            if ($event['tier']) {
                $tierPreferences[$event['tier']] = 
                    ($tierPreferences[$event['tier']] ?? 0) + 1;
            }
            
            // Extract prompt patterns (simple keyword extraction)
            if ($event['original_prompt']) {
                $words = str_word_count(strtolower($event['original_prompt']), 1);
                foreach ($words as $word) {
                    if (strlen($word) > 4) { // Only meaningful words
                        $promptPatterns[$word] = 
                            ($promptPatterns[$word] ?? 0) + 1;
                    }
                }
            }
        }
        
        // Sort by frequency
        arsort($modelPreferences);
        arsort($tierPreferences);
        arsort($promptPatterns);
        
        return [
            'preferred_models' => array_slice($modelPreferences, 0, 3, true),
            'preferred_tiers' => array_slice($tierPreferences, 0, 3, true),
            'common_keywords' => array_slice($promptPatterns, 0, 10, true),
            'total_events' => count($events)
        ];
    }
    
    /**
     * Prune old and unused patterns
     * 
     * @param int $maxAgeDays Maximum age in days (default 90)
     * @return int Number of patterns pruned
     */
    public function pruneOldPatterns($maxAgeDays = null) {
        // Use provided age or default
        $ageDays = $maxAgeDays ?? $this->pruneAgeThreshold;
        
        // Delete patterns that are old AND have low confidence OR low usage
        $result = $this->db->execute(
            "DELETE FROM learned_patterns 
             WHERE (
                (updated_at < datetime('now', '-' || :max_age || ' days')
                 AND confidence_score < 0.3)
                OR
                (updated_at < datetime('now', '-180 days')
                 AND usage_count < 2)
             )",
            [':max_age' => $ageDays]
        );
        
        // Also prune old learning events (keep last 6 months)
        $this->db->execute(
            "DELETE FROM learning_events 
             WHERE created_at < datetime('now', '-180 days')"
        );
        
        // Enforce max patterns per user
        $users = $this->db->query("SELECT DISTINCT user_id FROM learned_patterns");
        
        foreach ($users as $user) {
            $userId = $user['user_id'];
            
            // Count patterns for this user
            $count = $this->db->query(
                "SELECT COUNT(*) as count FROM learned_patterns WHERE user_id = :user_id",
                [':user_id' => $userId]
            )[0]['count'];
            
            if ($count > $this->maxPatternsPerUser) {
                // Delete lowest confidence patterns
                $toDelete = $count - $this->maxPatternsPerUser;
                $this->db->execute(
                    "DELETE FROM learned_patterns 
                     WHERE id IN (
                         SELECT id FROM learned_patterns 
                         WHERE user_id = :user_id 
                         ORDER BY confidence_score ASC, usage_count ASC 
                         LIMIT :limit
                     )",
                    [
                        ':user_id' => $userId,
                        ':limit' => $toDelete
                    ]
                );
            }
        }
        
        return true;
    }
    
    /**
     * Extract patterns from a learning event
     * Private helper method
     */
    private function extractPatterns($userId, $eventType, $context) {
        // Extract prompt patterns for successful events
        if (($eventType === 'success' || $eventType === 'feedback') && 
            isset($context['feedback_score']) && $context['feedback_score'] > 0 &&
            isset($context['original_prompt'])) {
            
            $this->extractPromptPattern($userId, $context);
        }
        
        // Extract correction patterns
        if ($eventType === 'correction' && 
            isset($context['original_response']) && 
            isset($context['corrected_response'])) {
            
            $this->extractCorrectionPattern($userId, $context);
        }
        
        // Extract model preferences
        if (isset($context['model_used']) && 
            isset($context['feedback_score']) && 
            $context['feedback_score'] >= 0) {
            
            $this->extractModelPreference($userId, $context);
        }
    }
    
    /**
     * Extract prompt pattern from successful interaction
     */
    private function extractPromptPattern($userId, $context) {
        $prompt = $context['original_prompt'];
        
        // Extract keywords (simple word frequency)
        $words = str_word_count(strtolower($prompt), 1);
        $keywords = array_filter($words, function($word) {
            return strlen($word) > 4; // Only meaningful words
        });
        $keywords = array_values(array_unique($keywords));
        
        // Create pattern key from keywords
        $patternKey = md5(implode('_', array_slice($keywords, 0, 5)));
        
        // Check if pattern exists
        $existing = $this->db->query(
            "SELECT id FROM learned_patterns 
             WHERE user_id = :user_id 
             AND pattern_type = 'prompt_template' 
             AND pattern_key = :pattern_key 
             LIMIT 1",
            [
                ':user_id' => $userId,
                ':pattern_key' => $patternKey
            ]
        );
        
        if (empty($existing)) {
            // Create new pattern
            $this->db->execute(
                "INSERT INTO learned_patterns (
                    user_id, pattern_type, pattern_key, pattern_value, confidence_score
                ) VALUES (
                    :user_id, 'prompt_template', :pattern_key, :pattern_value, 0.5
                )",
                [
                    ':user_id' => $userId,
                    ':pattern_key' => $patternKey,
                    ':pattern_value' => json_encode([
                        'keywords' => $keywords,
                        'template' => $prompt,
                        'description' => 'Successful prompt pattern'
                    ])
                ]
            );
        } else {
            // Update existing pattern confidence
            $this->updatePatternConfidence($existing[0]['id'], true);
        }
    }
    
    /**
     * Extract correction pattern
     */
    private function extractCorrectionPattern($userId, $context) {
        // Analyze what was corrected (simplified)
        $patternKey = md5($context['original_response'] . $context['corrected_response']);
        
        // Store the correction as a pattern
        $existing = $this->db->query(
            "SELECT id FROM learned_patterns 
             WHERE user_id = :user_id 
             AND pattern_type = 'correction_pattern' 
             AND pattern_key = :pattern_key 
             LIMIT 1",
            [
                ':user_id' => $userId,
                ':pattern_key' => $patternKey
            ]
        );
        
        if (empty($existing)) {
            $this->db->execute(
                "INSERT INTO learned_patterns (
                    user_id, pattern_type, pattern_key, pattern_value, confidence_score
                ) VALUES (
                    :user_id, 'correction_pattern', :pattern_key, :pattern_value, 0.6
                )",
                [
                    ':user_id' => $userId,
                    ':pattern_key' => $patternKey,
                    ':pattern_value' => json_encode([
                        'original' => substr($context['original_response'], 0, $this->maxPatternLength),
                        'corrected' => substr($context['corrected_response'], 0, $this->maxPatternLength),
                        'description' => 'User correction pattern'
                    ])
                ]
            );
        }
    }
    
    /**
     * Extract model preference
     */
    private function extractModelPreference($userId, $context) {
        $model = $context['model_used'];
        $patternKey = 'model_' . $model;
        
        $existing = $this->db->query(
            "SELECT id FROM learned_patterns 
             WHERE user_id = :user_id 
             AND pattern_type = 'model_preference' 
             AND pattern_key = :pattern_key 
             LIMIT 1",
            [
                ':user_id' => $userId,
                ':pattern_key' => $patternKey
            ]
        );
        
        $success = isset($context['feedback_score']) && $context['feedback_score'] > 0;
        
        if (empty($existing)) {
            $this->db->execute(
                "INSERT INTO learned_patterns (
                    user_id, pattern_type, pattern_key, pattern_value, 
                    confidence_score, usage_count, success_rate
                ) VALUES (
                    :user_id, 'model_preference', :pattern_key, :pattern_value, 
                    0.5, 1, :success_rate
                )",
                [
                    ':user_id' => $userId,
                    ':pattern_key' => $patternKey,
                    ':pattern_value' => json_encode(['model' => $model]),
                    ':success_rate' => $success ? 1.0 : 0.0
                ]
            );
        } else {
            $this->updatePatternConfidence($existing[0]['id'], $success);
        }
    }
    
    /**
     * Get model used for a task
     */
    private function getModelUsedForTask($taskId) {
        $execution = $this->db->query(
            "SELECT model FROM executions WHERE task_id = :task_id ORDER BY created_at DESC LIMIT 1",
            [':task_id' => $taskId]
        );
        
        return !empty($execution) ? $execution[0]['model'] : null;
    }
    
    /**
     * Get task output
     */
    private function getTaskOutput($taskId) {
        $execution = $this->db->query(
            "SELECT output_artifact FROM executions WHERE task_id = :task_id ORDER BY created_at DESC LIMIT 1",
            [':task_id' => $taskId]
        );
        
        if (empty($execution) || !$execution[0]['output_artifact']) {
            return null;
        }
        
        $artifact = $this->db->query(
            "SELECT content FROM artifacts WHERE id = :id LIMIT 1",
            [':id' => $execution[0]['output_artifact']]
        );
        
        return !empty($artifact) ? $artifact[0]['content'] : null;
    }
}
