<?php
/**
 * TruAi Chat Service
 * 
 * Handles chat conversations and message history
 * 
 * @package TruAi
 * @version 1.0.0
 */

class ChatService {
    private $db;
    private $currentConversationId = null;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Send a message and get AI response
     * @param array|null $metadata Optional: intent, scope, forensic_id, file_path, context_files (array of {path, content})
     */
    public function sendMessage($userId, $conversationId, $message, $model = 'auto', $metadata = null) {
        if (empty($message)) {
            throw new Exception('Message is required');
        }

        // Create conversation if not exists
        if (!$conversationId) {
            $conversationId = $this->createConversation($userId, $this->generateTitle($message));
        }
        
        // Store conversation ID for history
        $this->currentConversationId = $conversationId;

        // Save user message
        $this->saveMessage($conversationId, 'user', $message);

        // Build context from metadata (hard token budget ~4k chars for context)
        $contextFiles = isset($metadata['context_files']) && is_array($metadata['context_files']) ? $metadata['context_files'] : [];
        $effectiveMessage = $this->buildMessageWithContext($message, $contextFiles);

        // Get AI response
        $aiResponse = $this->getAIResponse($effectiveMessage, $model, $metadata);
        $this->saveMessage($conversationId, 'assistant', $aiResponse, $model);

        // Audit inline_rewrite
        if ($metadata && isset($metadata['intent']) && $metadata['intent'] === 'inline_rewrite') {
            $this->auditLog($userId, 'INLINE_REWRITE', $metadata['forensic_id'] ?? 'unknown', [
                'file_path' => $metadata['file_path'] ?? null,
                'selection_length' => $metadata['selection_length'] ?? 0
            ]);
        }

        // Update conversation timestamp
        $this->db->execute(
            "UPDATE conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = :id",
            [':id' => $conversationId]
        );

        return [
            'conversation_id' => $conversationId,
            'message' => [
                'role' => 'assistant',
                'content' => $aiResponse,
                'model' => $model
            ]
        ];
    }

    /**
     * Get all conversations for a user
     */
    public function getConversations($userId) {
        return $this->db->query(
            "SELECT c.*, 
                    (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id) as message_count
             FROM conversations c
             WHERE c.user_id = :user_id
             ORDER BY c.updated_at DESC",
            [':user_id' => $userId]
        );
    }

    /**
     * Get a specific conversation with messages
     */
    public function getConversation($conversationId) {
        $result = $this->db->query(
            "SELECT * FROM conversations WHERE id = :id LIMIT 1",
            [':id' => $conversationId]
        );

        if (empty($result)) {
            return null;
        }

        $conversation = $result[0];
        $conversation['messages'] = $this->db->query(
            "SELECT * FROM messages WHERE conversation_id = :id ORDER BY created_at ASC",
            [':id' => $conversationId]
        );

        return $conversation;
    }

    /**
     * Update a message (editable chat - Cursor parity)
     */
    public function updateMessage($messageId, $conversationId, $userId, $content) {
        $conv = $this->db->query(
            "SELECT id FROM conversations WHERE id = :id AND user_id = :user_id LIMIT 1",
            [':id' => $conversationId, ':user_id' => $userId]
        );
        if (empty($conv)) {
            throw new Exception('Conversation not found');
        }
        $this->db->execute(
            "UPDATE messages SET content = :content WHERE id = :id AND conversation_id = :conv_id",
            [':content' => $content, ':id' => $messageId, ':conv_id' => $conversationId]
        );
        return true;
    }

    /**
     * Update conversation title (editable, reviewable - Cursor parity)
     */
    public function updateConversationTitle($conversationId, $userId, $title) {
        $conv = $this->db->query(
            "SELECT id FROM conversations WHERE id = :id AND user_id = :user_id LIMIT 1",
            [':id' => $conversationId, ':user_id' => $userId]
        );
        if (empty($conv)) {
            throw new Exception('Conversation not found');
        }
        $this->db->execute(
            "UPDATE conversations SET title = :title, updated_at = CURRENT_TIMESTAMP WHERE id = :id",
            [':title' => $title, ':id' => $conversationId]
        );
        return true;
    }

    /**
     * Delete a conversation (only if owned by user)
     */
    public function deleteConversation($conversationId, $userId) {
        $this->db->execute(
            "DELETE FROM messages WHERE conversation_id = :id",
            [':id' => $conversationId]
        );
        $this->db->execute(
            "DELETE FROM conversations WHERE id = :id AND user_id = :user_id",
            [':id' => $conversationId, ':user_id' => $userId]
        );
    }

    private function createConversation($userId, $title) {
        $this->db->execute(
            "INSERT INTO conversations (user_id, title) VALUES (:user_id, :title)",
            [':user_id' => $userId, ':title' => $title]
        );
        
        return $this->db->lastInsertId();
    }

    private function saveMessage($conversationId, $role, $content, $model = null) {
        $this->db->execute(
            "INSERT INTO messages (conversation_id, role, content, model_used) 
             VALUES (:conv_id, :role, :content, :model)",
            [
                ':conv_id' => $conversationId,
                ':role' => $role,
                ':content' => $content,
                ':model' => $model
            ]
        );
    }

    private function generateTitle($message) {
        $title = substr($message, 0, 50);
        if (strlen($message) > 50) {
            $title .= '...';
        }
        return $title;
    }

    /**
     * Build user message with context files (deterministic order, hard budget)
     */
    private function buildMessageWithContext($message, $contextFiles) {
        $CONTEXT_BUDGET = 4000; // chars
        if (empty($contextFiles)) {
            return $message;
        }
        $parts = ["Context (AI sees):"];
        $used = 0;
        foreach ($contextFiles as $ctx) {
            $path = $ctx['path'] ?? '';
            $content = isset($ctx['content']) ? substr((string)$ctx['content'], 0, $CONTEXT_BUDGET - $used - 200) : '';
            if ($used + strlen($content) + strlen($path) + 50 > $CONTEXT_BUDGET) {
                break;
            }
            $parts[] = "--- {$path} ---";
            $parts[] = $content;
            $used += strlen($content) + strlen($path) + 50;
        }
        $parts[] = "--- End context ---";
        $parts[] = "";
        $parts[] = $message;
        return implode("\n", $parts);
    }

    private function auditLog($userId, $event, $actor, $details = null) {
        $this->db->execute(
            "INSERT INTO audit_logs (user_id, event, actor, details) VALUES (:user_id, :event, :actor, :details)",
            [
                ':user_id' => $userId,
                ':event' => $event,
                ':actor' => $actor,
                ':details' => $details ? json_encode($details) : null
            ]
        );
    }

    private function getAIResponse($message, $model, $metadata = null) {
        // Call actual AI API
        require_once __DIR__ . '/ai_client.php';
        
        // Note: AIClient constructor now handles precedence:
        // 1. Provided keys (passed here as null, so it will check settings)
        // 2. User settings (loaded automatically by AIClient)
        // 3. Environment variables (fallback)
        
        // Determine model if 'auto'
        if ($model === 'auto') {
            // Try to load user settings to determine which model to use
            require_once __DIR__ . '/settings_service.php';
            require_once __DIR__ . '/auth.php';
            
            try {
                $auth = new Auth();
                $openaiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';
                $isTruAiKey = (strpos($openaiKey, 'sk-svcacct-') === 0);
                if ($auth->isAuthenticated()) {
                    $settingsService = new SettingsService();
                    $settings = $settingsService->getSettings($auth->getUserId());
                    $settingsOpenai = $settings['ai']['openaiApiKey'] ?? '';
                    $isTruAiKey = $isTruAiKey || (strpos($settingsOpenai, 'sk-svcacct-') === 0);
                    $hasRealOpenAi = (!empty($settingsOpenai) || !empty($openaiKey)) && !$isTruAiKey;
                    if ($hasRealOpenAi) {
                        $model = 'gpt-4';
                    } else {
                        $model = 'claude-3-sonnet';
                    }
                } else {
                    $model = (!empty($openaiKey) && !$isTruAiKey) ? 'gpt-4' : 'claude-3-sonnet';
                }
            } catch (Exception $e) {
                error_log('Could not determine auto model: ' . $e->getMessage());
                $model = 'claude-3-sonnet'; // Prefer Claude as fallback (key often valid)
            }
        }
        
        $aiClient = new AIClient();
        
        try {
            // Get conversation history for context
            $conversationHistory = [];
            if (isset($this->currentConversationId)) {
                $history = $this->db->query(
                    "SELECT role, content FROM messages 
                     WHERE conversation_id = :conv_id 
                     ORDER BY created_at DESC 
                     LIMIT 10",
                    [':conv_id' => $this->currentConversationId]
                );
                $conversationHistory = array_reverse($history);
            }
            
            $response = $aiClient->chat($message, $model, $conversationHistory, $metadata);
            return $response;
        } catch (Exception $e) {
            error_log('AI chat failed: ' . $e->getMessage());
            // Fallback response if API fails
            return "I apologize, but I'm currently unable to process your request. Please ensure your API keys are configured correctly in Settings.\n\nError: " . $e->getMessage();
        }
    }
}
