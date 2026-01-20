// Minimal AI client for TruAi front-end
// Usage:
//  const client = new TruAiAIClient(window.TRUAI_CONFIG);
//  client.attachToUI({ textareaId: 'aiTextEntry', responseId: 'aiResponse' });

class TruAiAIClient {
  constructor(config = {}) {
    this.config = config || window.TRUAI_CONFIG || {};
    this.apiBase = this.config.API_BASE || (window.location.origin + '/TruAi/api/v1');
    // Get CSRF token from config or update from window
    this.updateCsrfToken();
    this.pollIntervalMs = 1000;
    this.pollTimeoutMs = 120000; // 2 minutes
  }

  // Update CSRF token from global config (useful after login)
  updateCsrfToken() {
    if (window.TRUAI_CONFIG && window.TRUAI_CONFIG.CSRF_TOKEN) {
      this.csrf = window.TRUAI_CONFIG.CSRF_TOKEN;
    } else {
      this.csrf = this.config.CSRF_TOKEN || '';
    }
  }

  async createTask(prompt, context = null) {
    // Update CSRF token before each request (critical after login)
    this.updateCsrfToken();
    
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
        // Clear auth state
        if (window.TRUAI_CONFIG) {
          window.TRUAI_CONFIG.IS_AUTHENTICATED = false;
        }
        // Show user-friendly error and suggest login
        const errorMsg = errorData.error || 'Unauthorized';
        console.error('401 Unauthorized - Session may have expired');
        // Don't auto-redirect, let user see the error and decide
        throw new Error(`Session expired. Please refresh the page and log in again. (${errorMsg})`);
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
    while (true) {
      const task = await this.getTask(taskId);
      // expected shape: { status: 'pending'|'running'|'completed'|'failed', output: '...' }
      if (onProgress) onProgress(task);
      
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
        throw new Error(`Polling timed out. Task status: ${task.status}. Try refreshing the page.`);
      }
      
      await new Promise(r => setTimeout(r, this.pollIntervalMs));
    }
  }

  // Single entry: submit a prompt and return final output (or throw on error)
  async submitPrompt(prompt, context = null, onProgress = null) {
    // Create
    const createResp = await this.createTask(prompt, context);

    // Check if backend returned immediate output
    if (createResp.output && createResp.output.trim() !== '') {
      // Task was auto-executed, return immediately
      return {
        ...createResp,
        status: 'completed' // Normalize status for frontend
      };
    }

    // Otherwise expect a task_id to poll
    if (!createResp.task_id) {
      throw new Error('No task_id returned from create endpoint');
    }
    
    // Check if status is already EXECUTED
    if (createResp.status === 'EXECUTED') {
      // No need to poll, task already executed
      return {
        ...createResp,
        status: 'completed'
      };
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
