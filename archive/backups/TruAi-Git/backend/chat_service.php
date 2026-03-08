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
     */
    public function sendMessage($userId, $conversationId, $message, $model = 'auto') {
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

        // Get AI response
        $aiResponse = $this->getAIResponse($message, $model);
        $this->saveMessage($conversationId, 'assistant', $aiResponse, $model);

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
     * Delete a conversation
     */
    public function deleteConversation($conversationId) {
        $this->db->execute(
            "DELETE FROM messages WHERE conversation_id = :id",
            [':id' => $conversationId]
        );
        
        $this->db->execute(
            "DELETE FROM conversations WHERE id = :id",
            [':id' => $conversationId]
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

    private function getAIResponse($message, $model) {
        // Call actual AI API
        require_once __DIR__ . '/ai_client.php';
        
        // Get API keys from settings if available
        require_once __DIR__ . '/settings_service.php';
        require_once __DIR__ . '/auth.php';
        
        $openaiKey = null;
        $anthropicKey = null;
        
        try {
            $auth = new Auth();
            if ($auth->isAuthenticated()) {
                $settingsService = new SettingsService();
                $settings = $settingsService->getSettings($auth->getUserId());
                
                if (!empty($settings['ai']['openaiApiKey'])) {
                    $openaiKey = $settings['ai']['openaiApiKey'];
                }
                if (!empty($settings['ai']['anthropicApiKey'])) {
                    $anthropicKey = $settings['ai']['anthropicApiKey'];
                }
            }
        } catch (Exception $e) {
            error_log('Could not load API keys from settings: ' . $e->getMessage());
        }
        
        // Determine model if 'auto'
        if ($model === 'auto') {
            // Default to OpenAI GPT-4 if available, otherwise Anthropic
            $model = (!empty($openaiKey) || !empty(OPENAI_API_KEY)) ? 'gpt-4' : 'claude-3-sonnet';
        }
        
        $aiClient = new AIClient($openaiKey, $anthropicKey);
        
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
            
            $response = $aiClient->chat($message, $model, $conversationHistory);
            return $response;
        } catch (Exception $e) {
            error_log('AI chat failed: ' . $e->getMessage());
            // Fallback response if API fails
            return "I apologize, but I'm currently unable to process your request. Please ensure your API keys are configured correctly in Settings.\n\nError: " . $e->getMessage();
        }
    }
}
