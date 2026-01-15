<?php
/**
 * TruAi Settings Service
 * 
 * Handles user settings and preferences
 * 
 * @package TruAi
 * @version 1.0.0
 */

class SettingsService {
    private $db;
    
    // Default settings
    private $defaults = [
        'editor' => [
            'fontSize' => 14,
            'fontFamily' => 'Monaco',
            'tabSize' => 4,
            'wordWrap' => true,
            'minimapEnabled' => true
        ],
        'ai' => [
            'openaiApiKey' => '',
            'anthropicApiKey' => '',
            'model' => 'gpt-4',
            'temperature' => 0.7
        ],
        'appearance' => [
            'theme' => 'dark'
        ],
        'git' => [
            'autoFetch' => false,
            'confirmSync' => true
        ],
        'terminal' => [
            'shell' => 'zsh'
        ]
    ];

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get all settings for a user
     */
    public function getSettings($userId) {
        $settings = $this->db->query(
            "SELECT category, key, value FROM settings WHERE user_id = :user_id",
            [':user_id' => $userId]
        );

        $result = [];
        foreach ($this->defaults as $category => $keys) {
            $result[$category] = [];
            foreach ($keys as $key => $defaultValue) {
                $result[$category][$key] = $defaultValue;
            }
        }

        // Override with saved values
        foreach ($settings as $setting) {
            $category = $setting['category'];
            $key = $setting['key'];
            $value = $setting['value'];
            
            // Parse value based on type
            if (isset($result[$category][$key])) {
                $defaultValue = $result[$category][$key];
                if (is_bool($defaultValue)) {
                    $result[$category][$key] = $value === '1' || $value === 'true';
                } elseif (is_int($defaultValue)) {
                    $result[$category][$key] = (int)$value;
                } elseif (is_float($defaultValue)) {
                    $result[$category][$key] = (float)$value;
                } else {
                    $result[$category][$key] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Get a specific setting
     */
    public function getSetting($userId, $category, $key) {
        $settings = $this->getSettings($userId);
        return $settings[$category][$key] ?? null;
    }

    /**
     * Save a setting
     */
    public function saveSetting($userId, $category, $key, $value) {
        // Validate category and key
        if (!isset($this->defaults[$category]) || !isset($this->defaults[$category][$key])) {
            throw new Exception("Invalid setting: {$category}.{$key}");
        }

        // Convert value to string for storage
        $valueStr = is_bool($value) ? ($value ? '1' : '0') : (string)$value;

        // Use INSERT OR REPLACE for upsert
        $this->db->execute(
            "INSERT OR REPLACE INTO settings (user_id, category, key, value, updated_at) 
             VALUES (:user_id, :category, :key, :value, CURRENT_TIMESTAMP)",
            [
                ':user_id' => $userId,
                ':category' => $category,
                ':key' => $key,
                ':value' => $valueStr
            ]
        );

        return true;
    }

    /**
     * Save multiple settings at once
     */
    public function saveSettings($userId, $settings) {
        foreach ($settings as $category => $categorySettings) {
            if (!is_array($categorySettings)) {
                continue;
            }
            foreach ($categorySettings as $key => $value) {
                try {
                    $this->saveSetting($userId, $category, $key, $value);
                } catch (Exception $e) {
                    error_log("Failed to save setting {$category}.{$key}: " . $e->getMessage());
                }
            }
        }
        return true;
    }

    /**
     * Reset settings to defaults
     */
    public function resetSettings($userId) {
        $this->db->execute(
            "DELETE FROM settings WHERE user_id = :user_id",
            [':user_id' => $userId]
        );
        return true;
    }

    /**
     * Clear all conversations (data management)
     */
    public function clearConversations($userId) {
        // Get all conversation IDs for this user
        $conversations = $this->db->query(
            "SELECT id FROM conversations WHERE user_id = :user_id",
            [':user_id' => $userId]
        );

        foreach ($conversations as $conv) {
            // Delete messages first (foreign key constraint)
            $this->db->execute(
                "DELETE FROM messages WHERE conversation_id = :id",
                [':id' => $conv['id']]
            );
        }

        // Delete conversations
        $this->db->execute(
            "DELETE FROM conversations WHERE user_id = :user_id",
            [':user_id' => $userId]
        );

        return true;
    }
}
