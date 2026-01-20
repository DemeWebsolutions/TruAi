// Minimal AI client for TruAi front-end
// Usage:
//  const client = new TruAiAIClient(window.TRUAI_CONFIG);
//  client.attachToUI({ textareaId: 'aiTextEntry', responseId: 'aiResponse' });

class TruAiAIClient {
  // Timeout configuration
  static MIN_POLL_TIMEOUT = 30000;  // 30 seconds minimum
  static MAX_POLL_TIMEOUT = 600000; // 10 minutes maximum
  static DEFAULT_POLL_TIMEOUT = 180000; // 3 minutes default
  
  constructor(config = {}) {
    this.config = config || window.TRUAI_CONFIG || {};
    this.apiBase = this.config.API_BASE || (window.location.origin + '/TruAi/api/v1');
    // Get CSRF token from config or update from window
    this.updateCsrfToken();
    this.pollIntervalMs = 1000;
    this.pollTimeoutMs = TruAiAIClient.DEFAULT_POLL_TIMEOUT;
    this.pollMaxBackoffMs = 5000; // Max backoff for exponential polling
    this.failedRequests = []; // Store failed requests for retry
    this.sessionRenewalInProgress = false;
    this.timeoutWarningThreshold = 30000; // Warn 30s before timeout
  }

  // Update CSRF token from global config (useful after login)
  updateCsrfToken() {
    if (window.TRUAI_CONFIG && window.TRUAI_CONFIG.CSRF_TOKEN) {
      this.csrf = window.TRUAI_CONFIG.CSRF_TOKEN;
    } else {
      this.csrf = this.config.CSRF_TOKEN || '';
    }
  }
  
  /**
   * Refresh CSRF token from server
   */
  async refreshCsrfToken() {
    try {
      const url = `${this.apiBase}/auth/refresh-token`;
      const res = await fetch(url, {
        method: 'GET',
        credentials: 'include'
      });
      
      if (res.ok) {
        const data = await res.json();
        if (data.csrf_token) {
          this.csrf = data.csrf_token;
          // Update global config
          if (window.TRUAI_CONFIG) {
            window.TRUAI_CONFIG.CSRF_TOKEN = data.csrf_token;
          }
          console.log('✅ CSRF token refreshed successfully');
          return true;
        }
      }
      return false;
    } catch (err) {
      console.error('Failed to refresh CSRF token:', err);
      return false;
    }
  }
  
  /**
   * Convert milliseconds to seconds
   */
  toSeconds(ms) {
    return Math.floor(ms / 1000);
  }
  
  /**
   * Set configurable timeout for polling
   */
  setPollTimeout(timeoutMs) {
    this.pollTimeoutMs = Math.max(
      TruAiAIClient.MIN_POLL_TIMEOUT, 
      Math.min(timeoutMs, TruAiAIClient.MAX_POLL_TIMEOUT)
    );
  }
  
  /**
   * Handle session expiration with user-friendly re-authentication flow
   */
  async handleSessionExpiration(failedRequest = null) {
    // Store failed request for retry
    if (failedRequest) {
      this.failedRequests.push(failedRequest);
    }
    
    // Clear auth state
    if (window.TRUAI_CONFIG) {
      window.TRUAI_CONFIG.IS_AUTHENTICATED = false;
    }
    
    // Show user-friendly modal with options
    const shouldRedirect = await this.showSessionExpiredDialog();
    
    if (shouldRedirect) {
      // Redirect to login page
      window.location.href = '/TruAi/login-portal.html';
    }
    
    return false;
  }
  
  /**
   * Show session expired dialog with user confirmation
   */
  async showSessionExpiredDialog() {
    return new Promise((resolve) => {
      // Create modal overlay
      const overlay = document.createElement('div');
      overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
      `;
      
      const modal = document.createElement('div');
      modal.style.cssText = `
        background: white;
        padding: 30px;
        border-radius: 8px;
        max-width: 400px;
        text-align: center;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
      `;
      
      modal.innerHTML = `
        <h2 style="margin-top: 0; color: #e53e3e;">Session Expired</h2>
        <p style="color: #4a5568; margin: 20px 0;">
          Your session has expired. Please log in again to continue.
        </p>
        <div style="display: flex; gap: 10px; justify-content: center;">
          <button id="sessionExpiredLogin" style="
            padding: 10px 20px;
            background: #3182ce;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
          ">Log In</button>
          <button id="sessionExpiredCancel" style="
            padding: 10px 20px;
            background: #718096;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
          ">Cancel</button>
        </div>
      `;
      
      overlay.appendChild(modal);
      document.body.appendChild(overlay);
      
      document.getElementById('sessionExpiredLogin').onclick = () => {
        document.body.removeChild(overlay);
        resolve(true);
      };
      
      document.getElementById('sessionExpiredCancel').onclick = () => {
        document.body.removeChild(overlay);
        resolve(false);
      };
    });
  }
  
  /**
   * Retry failed requests after successful re-authentication
   */
  async retryFailedRequests() {
    const requests = [...this.failedRequests];
    this.failedRequests = [];
    
    for (const request of requests) {
      try {
        await request.retry();
      } catch (err) {
        console.error('Failed to retry request:', err);
      }
    }
  }

  async createTask(prompt, context = null) {
    // Update CSRF token before each request (critical after login)
    this.updateCsrfToken();
    
    // If no CSRF token, try to refresh it
    if (!this.csrf) {
      console.warn('⚠️ No CSRF token available, attempting refresh...');
      await this.refreshCsrfToken();
    }
    
    // Debug logging
    console.log('Creating task with:', {
      hasCsrf: !!this.csrf,
      csrfLength: this.csrf?.length || 0,
      apiBase: this.apiBase,
      credentials: 'include'
    });
    
    const url = `${this.apiBase}/task/create`;
    const payload = { prompt: prompt, context: context };
    
    const headers = {
      'Content-Type': 'application/json'
    };
    
    // Only add CSRF token if available
    if (this.csrf) {
      headers['X-CSRF-Token'] = this.csrf;
    } else {
      console.warn('⚠️ No CSRF token available for request');
    }
    
    const res = await fetch(url, {
      method: 'POST',
      credentials: 'include', // Use 'include' for better cookie handling - CRITICAL for session
      headers: headers,
      body: JSON.stringify(payload)
    });
    
    if (!res.ok) {
      // Handle 401 Unauthorized specifically
      if (res.status === 401) {
        const errorData = await res.json().catch(() => ({ error: 'Unauthorized' }));
        console.error('401 Unauthorized - Session expired or invalid CSRF token');
        
        // Try refreshing CSRF token first
        const refreshed = await this.refreshCsrfToken();
        if (refreshed) {
          console.log('CSRF token refreshed, retrying request...');
          // Retry the request with new token
          return this.createTask(prompt, context);
        }
        
        // If refresh failed, handle session expiration with user-friendly dialog
        await this.handleSessionExpiration({
          retry: () => this.createTask(prompt, context)
        });
        
        throw new Error('Session expired. Please log in to continue.');
      }
      const txt = await res.text();
      throw new Error(`Create task failed: ${res.status} ${txt}`);
    }
    return res.json(); // expect { success: true, task_id: '...', ... } or immediate result
  }

  async getTask(taskId) {
    const url = `${this.apiBase}/task/${encodeURIComponent(taskId)}`;
    const res = await fetch(url, { 
      credentials: 'include', // Use 'include' for better cookie handling
      headers: {
        'X-CSRF-Token': this.csrf
      }
    });
    if (!res.ok) {
      throw new Error(`Get task failed: ${res.status}`);
    }
    return res.json();
  }

  // Poll until the task has a result or times out.
  async pollForResult(taskId, onProgress = null) {
    const start = Date.now();
    let pollInterval = this.pollIntervalMs;
    let warningShown = false;
    let timeoutExtended = false;
    
    while (true) {
      const elapsed = Date.now() - start;
      const remaining = this.pollTimeoutMs - elapsed;
      
      // Show warning when approaching timeout
      if (!warningShown && remaining <= this.timeoutWarningThreshold && remaining > 0) {
        warningShown = true;
        const shouldExtend = await this.showTimeoutWarning(this.toSeconds(remaining));
        if (shouldExtend) {
          // Extend timeout by 2 minutes
          this.pollTimeoutMs += 120000;
          timeoutExtended = true;
          if (onProgress) {
            onProgress({ 
              status: 'extended',
              message: 'Timeout extended by 2 minutes'
            });
          }
        }
      }
      
      try {
        const task = await this.getTask(taskId);
        // expected shape: { status: 'pending'|'running'|'completed'|'failed', output: '...' }
        
        // Provide progress with time remaining
        if (onProgress) {
          onProgress({
            ...task,
            elapsed: this.toSeconds(elapsed),
            remaining: this.toSeconds(remaining)
          });
        }
        
        // Recognize both 'completed' and 'EXECUTED' as done
        if (task.status === 'completed' || task.status === 'EXECUTED') {
          return {
            ...task,
            status: 'completed' // Normalize to 'completed'
          };
        }
        
        if (task.status === 'failed' || task.status === 'REJECTED') {
          throw new Error(task.error || 'Task failed');
        }
        
        if (Date.now() - start > this.pollTimeoutMs) {
          throw new Error(`Polling timed out after ${this.toSeconds(this.pollTimeoutMs)}s. Task status: ${task.status}. Please try again.`);
        }
        
        // Exponential backoff with max limit
        pollInterval = Math.min(pollInterval * 1.2, this.pollMaxBackoffMs);
        await new Promise(r => setTimeout(r, pollInterval));
        
      } catch (err) {
        // Handle session expiration during polling
        if (err.message && err.message.includes('401')) {
          await this.handleSessionExpiration({
            retry: () => this.pollForResult(taskId, onProgress)
          });
          throw new Error('Session expired during polling');
        }
        throw err;
      }
    }
  }
  
  /**
   * Show timeout warning with option to extend or cancel
   */
  async showTimeoutWarning(secondsRemaining) {
    return new Promise((resolve) => {
      // Create modal overlay
      const overlay = document.createElement('div');
      overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
      `;
      
      const modal = document.createElement('div');
      modal.style.cssText = `
        background: white;
        padding: 30px;
        border-radius: 8px;
        max-width: 400px;
        text-align: center;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
      `;
      
      modal.innerHTML = `
        <h2 style="margin-top: 0; color: #f59e0b;">Timeout Warning</h2>
        <p style="color: #4a5568; margin: 20px 0;">
          Your task is still processing. Approximately ${secondsRemaining} seconds remaining before timeout.
        </p>
        <p style="color: #4a5568; margin: 20px 0; font-size: 14px;">
          Would you like to extend the timeout by 2 minutes?
        </p>
        <div style="display: flex; gap: 10px; justify-content: center;">
          <button id="timeoutExtend" style="
            padding: 10px 20px;
            background: #3182ce;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
          ">Extend Timeout</button>
          <button id="timeoutContinue" style="
            padding: 10px 20px;
            background: #718096;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
          ">Continue Waiting</button>
        </div>
      `;
      
      overlay.appendChild(modal);
      document.body.appendChild(overlay);
      
      document.getElementById('timeoutExtend').onclick = () => {
        document.body.removeChild(overlay);
        resolve(true);
      };
      
      document.getElementById('timeoutContinue').onclick = () => {
        document.body.removeChild(overlay);
        resolve(false);
      };
    });
  }

  // Single entry: submit a prompt and return final output (or throw on error)
  async submitPrompt(prompt, context = null, onProgress = null) {
    // Create
    const createResp = await this.createTask(prompt, context);

    // Check if task was auto-executed (has output or EXECUTED status)
    if ((createResp.output && createResp.output.trim() !== '') || createResp.status === 'EXECUTED') {
      // Task was auto-executed, return immediately with normalized status
      return {
        ...createResp,
        status: 'completed' // Normalize status for frontend
      };
    }

    // Otherwise expect a task_id to poll
    if (!createResp.task_id) {
      throw new Error('No task_id returned from create endpoint');
    }
    
    const final = await this.pollForResult(createResp.task_id, onProgress);
    return final;
  }

  // Attach UI behavior to textarea + response area
  attachToUI({ textareaId = 'aiTextEntry', responseId = 'aiResponse', submitHotkey = {meta: true, key: 'Enter'} } = {}) {
    const textarea = document.getElementById(textareaId);
    const responseEl = document.getElementById(responseId);
    if (!textarea || !responseEl) {
      console.warn('TruAiAIClient: missing elements', textareaId, responseId);
      return;
    }

    const disableUI = (disabled) => {
      textarea.disabled = !!disabled;
      if (disabled) {
        textarea.classList.add('disabled');
      } else {
        textarea.classList.remove('disabled');
      }
    };

    const setLoading = (msg) => {
      responseEl.classList.remove('empty');
      responseEl.textContent = msg || 'Processing...';
    };

    const appendResponse = (text) => {
      responseEl.classList.remove('empty');
      responseEl.textContent = text;
    };

    textarea.addEventListener('keydown', async (e) => {
      const metaPressed = e.metaKey || e.ctrlKey;
      if (e.key === 'Enter' && (metaPressed)) {
        e.preventDefault();
        const prompt = textarea.value.trim();
        if (!prompt) return;
        try {
          disableUI(true);
          setLoading('Submitting prompt...');
          const result = await this.submitPrompt(prompt, null, (progress) => {
            // optional: reflect progress.status
            if (progress && progress.status && progress.status !== 'pending') {
              setLoading(`Status: ${progress.status}...`);
            }
          });
          // Try common properties: result.output, result.results, result.text
          const output = result.output ?? result.results ?? result.text ?? JSON.stringify(result);
          appendResponse(output);
        } catch (err) {
          appendResponse(`Error: ${err.message}`);
        } finally {
          disableUI(false);
          textarea.value = '';
          textarea.focus();
        }
      }
    });
  }
}

// Export for direct use in browser
window.TruAiAIClient = TruAiAIClient;
