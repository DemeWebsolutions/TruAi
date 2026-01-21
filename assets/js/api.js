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
        // Handle case where TRUAI_CONFIG might not be set (e.g., on login page)
        this.baseURL = window.TRUAI_CONFIG?.API_BASE || (window.location.origin + '/TruAi/api/v1');
        this.csrfToken = window.TRUAI_CONFIG?.CSRF_TOKEN || '';
    }

    async updateCsrfToken() {
        try {
            // Fetch fresh token from server
            const response = await fetch(`${this.baseURL}/auth/refresh-token`, {
                method: 'GET',
                credentials: 'include' // Send session cookie
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.csrf_token) {
                    this.csrfToken = data.csrf_token;
                    if (window.TRUAI_CONFIG) {
                        window.TRUAI_CONFIG.CSRF_TOKEN = data.csrf_token;
                    }
                    return true;
                }
            } else if (response.status === 401) {
                // Session expired - redirect to login
                window.location.href = '/TruAi/login-portal.html';
                return false;
            }
        } catch (error) {
            // Log error for debugging while avoiding sensitive data exposure
            if (error && error.message) {
                console.error('Failed to update CSRF token');
            }
        }
        
        // Fallback to window config
        if (window.TRUAI_CONFIG?.CSRF_TOKEN) {
            this.csrfToken = window.TRUAI_CONFIG.CSRF_TOKEN;
        }
        
        return !!this.csrfToken;
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;
        const config = {
            method: options.method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            credentials: 'include', // CRITICAL: Send cookies with every request
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
                // Handle 401 Unauthorized
                if (response.status === 401) {
                    // Check if this is an authentication-related endpoint
                    const isAuthEndpoint = endpoint.startsWith('/auth/');
                    
                    if (!isAuthEndpoint) {
                        // Try to refresh session token
                        const tokenRefreshed = await this.updateCsrfToken();
                        
                        if (!tokenRefreshed) {
                            // Session recovery failed - redirect to login
                            window.location.href = '/TruAi/login-portal.html';
                            return;
                        }
                        
                        // If we're still here, the session is valid but may have had a CSRF issue
                        // For now, just fall through to the error
                    } else if (isAuthEndpoint && endpoint !== '/auth/refresh-token') {
                        // Authentication failed on auth endpoint - redirect to login
                        // Clear any existing auth state
                        if (window.TRUAI_CONFIG) {
                            window.TRUAI_CONFIG.IS_AUTHENTICATED = false;
                        }
                        // Redirect to login portal
                        window.location.href = '/TruAi/login-portal.html';
                        return; // Don't throw error, redirect is happening
                    }
                    
                    // For all cases, still throw the error after recovery attempts
                    const errorMsg = data.error || data.message || 'Unauthorized: Check your credentials';
                    console.error(`API Error [${options.method || 'GET'} ${endpoint}]: 401 - ${errorMsg}`);
                    throw new Error(errorMsg);
                }
                
                // Safe error logging - do not log response data which may contain sensitive info
                const sanitizedError = data.error || data.message || `Request failed with status ${response.status}`;
                console.error(`API Error [${options.method || 'GET'} ${endpoint}]: ${response.status}`);
                throw new Error(sanitizedError);
            }

            return data;
        } catch (error) {
            // Safe error logging - do not log full request/response which may contain PII or secrets
            console.error(`API Error [${options.method || 'GET'} ${endpoint}]:`, error.message);
            
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
    async sendMessage(message, conversationId = null, model = 'auto', metadata = null, signal = null) {
        const body = { 
            message, 
            conversation_id: conversationId, 
            model 
        };
        
        // Add optional governed metadata if provided
        // ALLOWLIST: Only these metadata keys are forwarded to the API
        if (metadata) {
            // Governance metadata
            if (metadata.intent) body.intent = metadata.intent;
            if (metadata.risk) body.risk = metadata.risk;
            if (metadata.forensic_id) body.forensic_id = metadata.forensic_id;
            if (metadata.scope) body.scope = metadata.scope;
            
            // Context metadata
            if (metadata.selection_length !== undefined && 
                typeof metadata.selection_length === 'number' && 
                !isNaN(metadata.selection_length) && 
                metadata.selection_length >= 0) {
                body.selection_length = metadata.selection_length;
            }
            
            // Allow conversation_id override via metadata if needed
            // This allows callers to pass conversation_id in metadata for consistency
            // with other metadata fields, but the direct parameter takes precedence
            if (metadata.conversation_id && !conversationId) {
                body.conversation_id = metadata.conversation_id;
            }
            
            // NOTE: Model routing should NOT be exposed in production UI
            // This is for internal/dev use only. Production code should use the model parameter.
            if (metadata.model) body.model = metadata.model;
        }
        
        // Add signal to options if provided
        const options = {
            method: 'POST',
            body: body
        };
        
        if (signal) {
            options.signal = signal;
        }
        
        return this.request('/chat/message', options);
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
