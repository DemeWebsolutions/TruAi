// Minimal AI client for TruAi front-end
// Usage:
//  const client = new TruAiAIClient(window.TRUAI_CONFIG);
//  client.attachToUI({ textareaId: 'aiTextEntry', responseId: 'aiResponse' });

class TruAiAIClient {
  constructor(config = {}) {
    this.config = config || window.TRUAI_CONFIG || {};
    this.apiBase = this.config.API_BASE || (window.location.origin + '/TruAi/api/v1');
    this.csrf = this.config.CSRF_TOKEN || '';
    this.pollIntervalMs = 1000;
    this.pollTimeoutMs = 120000; // 2 minutes
  }

  async createTask(prompt, context = null) {
    const url = `${this.apiBase}/task/create`;
    const payload = { prompt: prompt, context: context };
    const res = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': this.csrf
      },
      body: JSON.stringify(payload)
    });
    if (!res.ok) {
      const txt = await res.text();
      throw new Error(`Create task failed: ${res.status} ${txt}`);
    }
    return res.json(); // expect { success: true, task_id: '...', ... } or immediate result
  }

  async getTask(taskId) {
    const url = `${this.apiBase}/task/${encodeURIComponent(taskId)}`;
    const res = await fetch(url, { credentials: 'same-origin' });
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
      if (task.status === 'completed') {
        return task;
      }
      if (task.status === 'failed') {
        throw new Error(task.error || 'Task failed');
      }
      if (Date.now() - start > this.pollTimeoutMs) {
        throw new Error('Polling timed out');
      }
      await new Promise(r => setTimeout(r, this.pollIntervalMs));
    }
  }

  // Single entry: submit a prompt and return final output (or throw on error)
  async submitPrompt(prompt, context = null, onProgress = null) {
    // Create
    const createResp = await this.createTask(prompt, context);

    // If backend returned an immediate output in createResp, show it
    if (createResp.output && createResp.output.trim() !== '') {
      return createResp;
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
