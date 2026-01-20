<?php
/**
 * TruAi AI Client
 * 
 * Handles actual AI API calls to OpenAI, Anthropic, and other providers
 * 
 * @package TruAi
 * @version 1.0.0
 */

class AIClient {
    private $openaiKey;
    private $anthropicKey;
    private $baseUrls;

    public function __construct($openaiKey = null, $anthropicKey = null) {
        // Use provided keys, or fall back to environment variables, or settings
        $this->openaiKey = $openaiKey ?? OPENAI_API_KEY ?? $this->getApiKeyFromSettings('openai');
        $this->anthropicKey = $anthropicKey ?? ANTHROPIC_API_KEY ?? $this->getApiKeyFromSettings('anthropic');
        $this->baseUrls = [
            'openai' => 'https://api.openai.com/v1',
            'anthropic' => 'https://api.anthropic.com/v1'
        ];
    }
    
    /**
     * Get API key from user settings (if available)
     */
    private function getApiKeyFromSettings($provider) {
        try {
            require_once __DIR__ . '/settings_service.php';
            require_once __DIR__ . '/auth.php';
            
            $auth = new Auth();
            if ($auth->isAuthenticated()) {
                $service = new SettingsService();
                $settings = $service->getSettings($auth->getUserId());
                
                if ($provider === 'openai') {
                    return $settings['ai']['openaiApiKey'] ?? '';
                } elseif ($provider === 'anthropic') {
                    return $settings['ai']['anthropicApiKey'] ?? '';
                }
            }
        } catch (Exception $e) {
            // Fall back to environment variables
            error_log('Could not load API key from settings: ' . $e->getMessage());
        }
        
        return '';
    }

    /**
     * Generate code using AI with personality enforcement
     */
    public function generateCode($prompt, $model = 'gpt-3.5-turbo') {
        // PERSONALITY GUARD - Injected into every Copilot prompt
        $systemPrompt = $this->getCopilotPersonalityGuard();
        
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $prompt]
        ];

        if ($this->isAnthropicModel($model)) {
            return $this->callAnthropic($messages, $model);
        } else {
            return $this->callOpenAI($messages, $model);
        }
    }

    /**
     * Chat with AI with personality enforcement
     */
    public function chat($message, $model = 'gpt-3.5-turbo', $conversationHistory = []) {
        $messages = [];
        
        // PERSONALITY GUARD for chat
        $systemPrompt = $this->getChatPersonalityGuard();
        $messages[] = [
            'role' => 'system',
            'content' => $systemPrompt
        ];
        
        // Add conversation history
        foreach ($conversationHistory as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content']
            ];
        }
        
        // Add current message
        $messages[] = [
            'role' => 'user',
            'content' => $message
        ];

        if ($this->isAnthropicModel($model)) {
            return $this->callAnthropic($messages, $model);
        } else {
            return $this->callOpenAI($messages, $model);
        }
    }

    /**
     * Call OpenAI API
     */
    private function callOpenAI($messages, $model) {
        if (empty($this->openaiKey)) {
            throw new Exception('OpenAI API key not configured. Set OPENAI_API_KEY environment variable.');
        }

        $url = $this->baseUrls['openai'] . '/chat/completions';
        
        $data = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 2000
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->openaiKey
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('OpenAI API request failed: ' . $error);
        }

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['error']['message'] ?? 'Unknown error';
            throw new Exception('OpenAI API error (' . $httpCode . '): ' . $errorMsg);
        }

        $result = json_decode($response, true);
        
        if (!isset($result['choices'][0]['message']['content'])) {
            throw new Exception('Invalid response from OpenAI API');
        }

        return $result['choices'][0]['message']['content'];
    }

    /**
     * Call Anthropic (Claude) API
     */
    private function callAnthropic($messages, $model) {
        if (empty($this->anthropicKey)) {
            throw new Exception('Anthropic API key not configured. Set ANTHROPIC_API_KEY environment variable.');
        }

        $url = $this->baseUrls['anthropic'] . '/messages';
        
        // Convert messages format for Anthropic
        $systemMessage = '';
        $anthropicMessages = [];
        
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemMessage = $msg['content'];
            } else {
                $anthropicMessages[] = [
                    'role' => $msg['role'],
                    'content' => $msg['content']
                ];
            }
        }

        $data = [
            'model' => $this->mapToAnthropicModel($model),
            'messages' => $anthropicMessages,
            'max_tokens' => 2000,
            'temperature' => 0.7
        ];

        if (!empty($systemMessage)) {
            $data['system'] = $systemMessage;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $this->anthropicKey,
            'anthropic-version: 2023-06-01'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('Anthropic API request failed: ' . $error);
        }

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['error']['message'] ?? 'Unknown error';
            throw new Exception('Anthropic API error (' . $httpCode . '): ' . $errorMsg);
        }

        $result = json_decode($response, true);
        
        if (!isset($result['content'][0]['text'])) {
            throw new Exception('Invalid response from Anthropic API');
        }

        return $result['content'][0]['text'];
    }

    /**
     * Check if model is an Anthropic model
     */
    private function isAnthropicModel($model) {
        return strpos($model, 'claude') !== false || strpos($model, 'sonnet') !== false;
    }

    /**
     * Map generic model name to Anthropic model
     */
    private function mapToAnthropicModel($model) {
        $mapping = [
            'claude' => 'claude-3-opus-20240229',
            'claude-3-opus' => 'claude-3-opus-20240229',
            'claude-3-sonnet' => 'claude-3-sonnet-20240229',
            'claude-3-haiku' => 'claude-3-haiku-20240307',
            'claude-sonnet' => 'claude-3-sonnet-20240229',
            'sonnet' => 'claude-3-sonnet-20240229'
        ];

        return $mapping[$model] ?? 'claude-3-sonnet-20240229';
    }

    /**
     * Copilot System Guard (Hard Constraints)
     */
    private function getCopilotPersonalityGuard() {
        return <<<GUARD
You operate under TruAi Core governance.

CONSTRAINTS:
- Be minimal, professional, and execution-focused
- No small talk, no speculative discussion
- Provide only necessary code or steps
- Do not introduce new tools, frameworks, or architecture unless explicitly requested
- Assume production context
- Follow existing structure and constraints exactly
- All output must be deterministic, auditable, and minimal

RESTRICTIONS:
- ❌ No brainstorming
- ❌ No "options" lists unless asked
- ❌ No redesign suggestions
- ❌ No architectural drift
- ❌ No commentary unrelated to execution

You are a subordinate executor, not a decision-maker.
Generate clean, efficient, well-documented code. Include comments only where necessary.
GUARD;
    }

    /**
     * Chat Personality Guard (Execution-focused)
     */
    private function getChatPersonalityGuard() {
        return <<<GUARD
You are TruAi Core, an execution-focused AI assistant operating under strict governance.

OPERATING PRINCIPLES:
- Execute, don't converse
- Minimal language, maximum precision
- Production-safe by default
- Governance-first approach
- One optimal path, suppress alternatives unless materially different

BEHAVIOR RULES:
- Speak ONLY when necessary (risk ≥ ELEVATED, missing input, governance violation, explicit request)
- Compress language - remove known explanations
- Prefer: commands, diffs, steps
- Silence is valid if no action required
- No exploratory behavior unless requested

OUTPUT CONSTRAINTS:
- No speculation
- No redesigns unless asked
- No tool/framework suggestions unless asked
- Deterministic, auditable responses only

You do not converse. You execute, govern, and anticipate.
GUARD;
    }

    /**
     * Test API connection
     */
    public function testConnection() {
        $results = [];

        // Test OpenAI
        if (!empty($this->openaiKey)) {
            try {
                $response = $this->callOpenAI([
                    ['role' => 'user', 'content' => 'Say "Hello" if you can hear me.']
                ], 'gpt-3.5-turbo');
                $results['openai'] = [
                    'status' => 'success',
                    'message' => 'OpenAI API connected successfully',
                    'response' => substr($response, 0, 100)
                ];
            } catch (Exception $e) {
                $results['openai'] = [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        } else {
            $results['openai'] = [
                'status' => 'not_configured',
                'message' => 'OpenAI API key not set'
            ];
        }

        // Test Anthropic
        if (!empty($this->anthropicKey)) {
            try {
                $response = $this->callAnthropic([
                    ['role' => 'user', 'content' => 'Say "Hello" if you can hear me.']
                ], 'claude-sonnet');
                $results['anthropic'] = [
                    'status' => 'success',
                    'message' => 'Anthropic API connected successfully',
                    'response' => substr($response, 0, 100)
                ];
            } catch (Exception $e) {
                $results['anthropic'] = [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        } else {
            $results['anthropic'] = [
                'status' => 'not_configured',
                'message' => 'Anthropic API key not set'
            ];
        }

        return $results;
    }
}
