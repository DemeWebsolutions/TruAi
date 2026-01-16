/**
 * TruAi API Client
 * 
 * Handles all API communication
 * 
 * @package TruAi
 * @version 1.0.0
 */

class TruAiAPI {
    constructor() {
        this.baseURL = window.TRUAI_CONFIG.API_BASE;
        this.csrfToken = window.TRUAI_CONFIG.CSRF_TOKEN;
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;
        const config = {
            method: options.method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        };

        if (options.body) {
            config.body = JSON.stringify(options.body);
        }

        try {
            const response = await fetch(url, config);
            
            // Handle non-JSON responses
            let data;
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                const text = await response.text();
                throw new Error(text || 'Request failed');
            }

            if (!response.ok) {
                throw new Error(data.error || `Request failed with status ${response.status}`);
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            // Re-throw with a user-friendly message if it's a network error
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                throw new Error('Network error: Unable to connect to server');
            }
            throw error;
        }
    }

    // Auth endpoints
    async login(username, password) {
        return this.request('/auth/login', {
            method: 'POST',
            body: { username, password }
        });
    }

    async loginEncrypted(encryptedData, sessionId) {
        return this.request('/auth/login', {
            method: 'POST',
            body: { 
                encrypted_data: encryptedData,
                session_id: sessionId
            }
        });
    }

    async logout() {
        return this.request('/auth/logout', {
            method: 'POST'
        });
    }

    async getAuthStatus() {
        return this.request('/auth/status');
    }

    // Task endpoints
    async createTask(prompt, context = null, preferredTier = 'auto', metadata = null) {
        const body = { 
            prompt, 
            context, 
            preferred_tier: preferredTier 
        };
        
        // Add optional governed metadata if provided
        if (metadata) {
            if (metadata.model) body.model = metadata.model;
            if (metadata.intent) body.intent = metadata.intent;
            if (metadata.risk) body.risk = metadata.risk;
            if (metadata.forensic_id) body.forensic_id = metadata.forensic_id;
        }
        
        return this.request('/task/create', {
            method: 'POST',
            body
        });
    }

    async getTask(taskId) {
        return this.request(`/task/${taskId}`);
    }

    async executeTask(taskId) {
        return this.request('/task/execute', {
            method: 'POST',
            body: { task_id: taskId }
        });
    }

    async approveTask(taskId, action, target = 'production') {
        return this.request('/task/approve', {
            method: 'POST',
            body: { task_id: taskId, action, target }
        });
    }

    // Chat endpoints
    async sendMessage(message, conversationId = null, model = 'auto', metadata = null) {
        const body = { 
            message, 
            conversation_id: conversationId, 
            model 
        };
        
        // Add optional governed metadata if provided
        if (metadata) {
            if (metadata.intent) body.intent = metadata.intent;
            if (metadata.risk) body.risk = metadata.risk;
            if (metadata.forensic_id) body.forensic_id = metadata.forensic_id;
            // Model can also be passed via metadata (takes precedence)
            if (metadata.model) body.model = metadata.model;
        }
        
        return this.request('/chat/message', {
            method: 'POST',
            body
        });
    }

    async getConversations() {
        return this.request('/chat/conversations');
    }

    async getConversation(conversationId) {
        return this.request(`/chat/conversation/${conversationId}`);
    }

    // Audit endpoints
    async getAuditLogs() {
        return this.request('/audit/logs');
    }

    // Settings endpoints
    async getSettings() {
        return this.request('/settings');
    }

    async saveSettings(settings) {
        return this.request('/settings', {
            method: 'POST',
            body: { settings }
        });
    }

    async resetSettings() {
        return this.request('/settings/reset', {
            method: 'POST'
        });
    }

    async clearConversations() {
        return this.request('/settings/clear-conversations', {
            method: 'POST'
        });
    }
}

// Export for use in other scripts
window.TruAiAPI = TruAiAPI;
