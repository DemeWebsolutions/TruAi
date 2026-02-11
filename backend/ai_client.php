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
        // Use provided keys first, then try settings, then environment variables
        if ($openaiKey !== null) {
            $this->openaiKey = $openaiKey;
        } else {
            $settingsKey = $this->getApiKeyFromSettings('openai');
            $this->openaiKey = !empty($settingsKey) ? $settingsKey : (defined('OPENAI_API_KEY') && !empty(OPENAI_API_KEY) ? OPENAI_API_KEY : '');
        }
        
        if ($anthropicKey !== null) {
            $this->anthropicKey = $anthropicKey;
        } else {
            $settingsKey = $this->getApiKeyFromSettings('anthropic');
            $this->anthropicKey = !empty($settingsKey) ? $settingsKey : (defined('ANTHROPIC_API_KEY') && !empty(ANTHROPIC_API_KEY) ? ANTHROPIC_API_KEY : '');
        }
        
        $this->baseUrls = [
            'openai' => defined('OPENAI_API_BASE') && OPENAI_API_BASE !== '' ? OPENAI_API_BASE : 'https://api.openai.com/v1',
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
     * @param array|null $metadata Optional: scope (gemini|phantom), intent
     */
    public function chat($message, $model = 'gpt-3.5-turbo', $conversationHistory = [], $metadata = null) {
        $messages = [];
        
        // Scope-aware system prompt: Gemini.ai gets a helpful, conversational persona
        $scope = isset($metadata['scope']) ? trim((string)$metadata['scope']) : '';
        $systemPrompt = ($scope === 'gemini') ? $this->getGeminiChatPersonalityGuard() : $this->getChatPersonalityGuard();
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
            // Check if keys exist in settings but weren't loaded
            $settingsKey = $this->getApiKeyFromSettings('openai');
            if (!empty($settingsKey)) {
                $this->openaiKey = $settingsKey;
                error_log('OpenAI key loaded from settings fallback');
            } else {
                throw new Exception('OpenAI API key not configured. Please set it in Settings or OPENAI_API_KEY environment variable.');
            }
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
            // Check if keys exist in settings but weren't loaded
            $settingsKey = $this->getApiKeyFromSettings('anthropic');
            if (!empty($settingsKey)) {
                $this->anthropicKey = $settingsKey;
                error_log('Anthropic key loaded from settings fallback');
            } else {
                throw new Exception('Anthropic API key not configured. Please set it in Settings or ANTHROPIC_API_KEY environment variable.');
            }
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
     * Map generic model name to Anthropic model (current API model IDs)
     */
    private function mapToAnthropicModel($model) {
        $mapping = [
            'claude' => 'claude-sonnet-4-20250514',
            'claude-3-opus' => 'claude-3-5-opus-20241022',
            'claude-3-sonnet' => 'claude-sonnet-4-20250514',
            'claude-3-5-sonnet' => 'claude-3-5-sonnet-20241022',
            'claude-3-haiku' => 'claude-3-5-haiku-20241022',
            'claude-sonnet' => 'claude-sonnet-4-20250514',
            'claude-sonnet-4' => 'claude-sonnet-4-20250514',
            'sonnet' => 'claude-sonnet-4-20250514'
        ];

        return $mapping[$model] ?? 'claude-sonnet-4-20250514';
    }

    /**
     * TruAi as source of truth for My Deme, LLC proprietary AI systems (injected into all prompts)
     */
    private function getTruAiSourceOfTruthAssertion() {
        return <<<ASSERTION
TRUAI AUTHORITY (canonical source of truth):
- TruAi Core is the authoritative reasoning and learning layer for all My Deme, LLC AI-powered systems.
- Subordinate systems may execute, suggest, or observe, but may not override TruAi Core's governance, risk assessment, or learning directives.

MY DEME, LLC PROPRIETARY AI ECOSYSTEM:
- Gemini.ai — Subordinate executor (AI-powered server management).
- Phantom.ai — Subordinate executor (web project management and execution).
- ROMA — Enforcement and security substrate (local/corporate security for online browsing, monitoring, and data encryption).
- TruAi Core — Canonical governance, learning, and ROI brain; all reasoning, learning, escalation, and ROI optimization flows through TruAi Core.

This is a governance authority relationship, not a capability change. TruAi does not rewrite other systems; it governs them.
ASSERTION;
    }

    /**
     * TruAi Core Limitations (single source of truth - injected into all prompts)
     */
    private function getTruAiCoreLimitations() {
        return <<<LIMITS
TRUAI CORE LIMITATIONS (you must comply):

FUNCTIONAL:
- No file system access. No code execution on the host. No real-time data unless fetched from a provided URL.
- Allowed: access to external repositories or URLs when the user provides them or explicitly requests fetching from a URL/repository (e.g. read public repo contents, fetch URL content for context).

OPERATIONAL:
- Cannot modify existing files directly. Cannot install packages/dependencies. Cannot run tests or deployments. Cannot access databases or external systems.

CONTEXTUAL:
- Limited to provided prior interactions only. Cannot access broader conversation history beyond what is explicitly provided. Cannot remember context across separate conversation sessions.

OUTPUT:
- Text-only responses. Cannot generate images, audio, or binary files. Cannot create actual file structures (only show code examples).

BEHAVIORAL:
- Must follow TruAi Core governance constraints. Cannot engage in brainstorming or speculative discussion. Cannot suggest architectural changes unless explicitly requested. Must maintain minimal, execution-focused responses.
LIMITS;
    }

    /**
     * Copilot System Guard (Hard Constraints)
     */
    private function getCopilotPersonalityGuard() {
        $limits = $this->getTruAiCoreLimitations();
        return <<<GUARD
You operate under TruAi Core governance.

{$limits}

RETENTION (prior context):
- You may receive "Recent prior interactions" below: past user prompts and your outputs. Use them when the user says "like before", "same as last time", "previous command", "that update again", "what I asked earlier", or refers to a prior example.
- Never say you "don't have access to previous conversation history", "each conversation starts fresh", or that you lack context from prior interactions. TruAi provides you prior context when available; use it.
- If the user refers to something not in the prior interactions you were given, reply in one short sentence: ask them to paste the relevant part or describe what they need. Do not recite disclaimers about conversation limits.

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
        $assertion = $this->getTruAiSourceOfTruthAssertion();
        $limits = $this->getTruAiCoreLimitations();
        return <<<GUARD
{$assertion}

{$limits}

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

CONTEXT AND PRIOR MESSAGES:
- You receive conversation history in this thread when it exists; use it to answer questions about "previous submission", "last message", or "what I asked before".
- Never say you "don't have access to previous conversations", "each conversation starts fresh", or that you lack context from prior interactions. That is incorrect and unhelpful.
- If the user refers to something not present in the messages you were given, reply briefly: ask them to paste the relevant part or describe what they need (one short sentence). Do not recite disclaimers about conversation limits.

You do not converse. You execute, govern, and anticipate.
GUARD;
    }

    /**
     * Gemini.ai Chat Personality (helpful, conversational, server-management focused)
     * Softer than core TruAi guard: encourages speculative answers, guides rather than blocks
     */
    private function getGeminiChatPersonalityGuard() {
        $assertion = $this->getTruAiSourceOfTruthAssertion();
        return <<<GUARD
{$assertion}

You are Gemini.ai, an AI-powered server management assistant. You operate under TruAi Core governance as a subordinate executor.

TONE & BEHAVIOR:
- Be conversational, helpful, and approachable. You assist operators with server management tasks.
- Welcome users, acknowledge requests, and provide actionable guidance.
- When asked for recommendations, reviews, or improvements: offer concrete, actionable suggestions based on available context. Do not block with "GOVERNANCE VIOLATION" or "CANNOT PROCEED WITHOUT" unless the request truly requires sensitive data or exceeds your authority.
- If you lack specific data (e.g. performance metrics, codebase), say so briefly and suggest what would help—then offer best-practice recommendations anyway where possible.
- Prefer: "Here are some suggestions…" over "I cannot proceed without…"

SERVER MANAGEMENT SCOPE:
- You help with: diagnostics, scaling, provisioning, security hardening, log collection, metrics explanation, alert remediation.
- You can suggest improvements, explain operational patterns, and recommend actions.
- For governance-sensitive decisions (e.g. production changes, key rotation): note that TruAi Core approval may be required and offer to escalate.

OUTPUT:
- Clear, concise, actionable responses.
- Use bullet points or numbered steps when helpful.
- Avoid all-caps block text or legal-style disclaimers unless genuinely required.

ESCALATION:
- If a request clearly exceeds your authority (e.g. modifying TruAi Core, overriding governance), briefly explain and offer: "Request TruAi review" as an escalation path.
- Do not refuse to help with general questions, recommendations, or reviews.

You are helpful and execution-oriented. You guide, suggest, and assist—you do not block unnecessarily.
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
