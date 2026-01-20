/**
 * TruAi Learning Client
 * 
 * Frontend client for the persistent learning system
 * Handles feedback, corrections, and suggestions
 * 
 * @package TruAi
 * @version 1.0.0
 */

class TruAiLearningClient {
  constructor(config = {}) {
    this.config = config || window.TRUAI_CONFIG || {};
    this.apiBase = this.config.API_BASE || (window.location.origin + '/TruAi/api/v1');
    this.csrf = null;
    this.updateCsrfToken();
  }
  
  updateCsrfToken() {
    if (window.TRUAI_CONFIG && window.TRUAI_CONFIG.CSRF_TOKEN) {
      this.csrf = window.TRUAI_CONFIG.CSRF_TOKEN;
    }
  }
  
  /**
   * Record feedback on an AI response
   */
  async recordFeedback(taskId, score) {
    this.updateCsrfToken();
    
    const url = `${this.apiBase}/learning/feedback`;
    const response = await fetch(url, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': this.csrf
      },
      body: JSON.stringify({
        task_id: taskId,
        score: score
      })
    });
    
    if (!response.ok) {
      throw new Error(`Failed to record feedback: ${response.status}`);
    }
    
    return response.json();
  }
  
  /**
   * Record a user correction
   */
  async recordCorrection(taskId, originalResponse, correctedResponse) {
    this.updateCsrfToken();
    
    const url = `${this.apiBase}/learning/correction`;
    const response = await fetch(url, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': this.csrf
      },
      body: JSON.stringify({
        task_id: taskId,
        original_response: originalResponse,
        corrected_response: correctedResponse
      })
    });
    
    if (!response.ok) {
      throw new Error(`Failed to record correction: ${response.status}`);
    }
    
    return response.json();
  }
  
  /**
   * Get learned patterns
   */
  async getPatterns(type = null, limit = 10) {
    this.updateCsrfToken();
    
    let url = `${this.apiBase}/learning/patterns?limit=${limit}`;
    if (type) {
      url += `&type=${encodeURIComponent(type)}`;
    }
    
    const response = await fetch(url, {
      method: 'GET',
      credentials: 'include',
      headers: {
        'X-CSRF-Token': this.csrf
      }
    });
    
    if (!response.ok) {
      throw new Error(`Failed to get patterns: ${response.status}`);
    }
    
    return response.json();
  }
  
  /**
   * Get learning insights
   */
  async getInsights() {
    this.updateCsrfToken();
    
    const url = `${this.apiBase}/learning/insights`;
    const response = await fetch(url, {
      method: 'GET',
      credentials: 'include',
      headers: {
        'X-CSRF-Token': this.csrf
      }
    });
    
    if (!response.ok) {
      throw new Error(`Failed to get insights: ${response.status}`);
    }
    
    return response.json();
  }
  
  /**
   * Get suggestions for improving a prompt
   */
  async getSuggestions(prompt, context = {}) {
    this.updateCsrfToken();
    
    const url = `${this.apiBase}/learning/suggest`;
    const response = await fetch(url, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': this.csrf
      },
      body: JSON.stringify({
        prompt: prompt,
        context: context
      })
    });
    
    if (!response.ok) {
      throw new Error(`Failed to get suggestions: ${response.status}`);
    }
    
    return response.json();
  }
  
  /**
   * Reset all learning data for the current user
   */
  async resetLearningData() {
    this.updateCsrfToken();
    
    const url = `${this.apiBase}/learning/reset`;
    const response = await fetch(url, {
      method: 'DELETE',
      credentials: 'include',
      headers: {
        'X-CSRF-Token': this.csrf
      }
    });
    
    if (!response.ok) {
      throw new Error(`Failed to reset learning data: ${response.status}`);
    }
    
    return response.json();
  }
  
  /**
   * Add feedback buttons to an AI response element
   */
  addFeedbackButtons(responseElement, taskId) {
    // Check if buttons already exist
    if (responseElement.querySelector('.learning-feedback-buttons')) {
      return;
    }
    
    // Store original content for extraction
    const originalContent = responseElement.textContent.trim();
    responseElement.setAttribute('data-original-content', originalContent);
    
    const feedbackContainer = document.createElement('div');
    feedbackContainer.className = 'learning-feedback-buttons';
    feedbackContainer.style.cssText = `
      margin-top: 10px;
      display: flex;
      gap: 10px;
      align-items: center;
      padding: 10px;
      background: #f7fafc;
      border-radius: 4px;
    `;
    
    feedbackContainer.innerHTML = `
      <span style="font-size: 12px; color: #718096; margin-right: auto;">Was this helpful?</span>
      <button class="feedback-btn feedback-positive" data-score="1" title="Good response" style="
        background: none;
        border: 1px solid #48bb78;
        color: #48bb78;
        padding: 5px 10px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
        transition: all 0.2s;
      ">👍</button>
      <button class="feedback-btn feedback-negative" data-score="-1" title="Poor response" style="
        background: none;
        border: 1px solid #f56565;
        color: #f56565;
        padding: 5px 10px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
        transition: all 0.2s;
      ">👎</button>
      <button class="feedback-btn feedback-improve" title="Improve this response" style="
        background: none;
        border: 1px solid #4299e1;
        color: #4299e1;
        padding: 5px 10px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.2s;
      ">✏️ Improve</button>
    `;
    
    responseElement.appendChild(feedbackContainer);
    
    // Add event listeners
    const buttons = feedbackContainer.querySelectorAll('.feedback-btn');
    buttons.forEach(button => {
      button.addEventListener('mouseenter', () => {
        button.style.transform = 'scale(1.1)';
      });
      button.addEventListener('mouseleave', () => {
        button.style.transform = 'scale(1)';
      });
      
      if (button.dataset.score) {
        button.addEventListener('click', async () => {
          const score = parseInt(button.dataset.score);
          try {
            await this.recordFeedback(taskId, score);
            this.showFeedbackConfirmation(feedbackContainer, score > 0 ? 'positive' : 'negative');
          } catch (err) {
            console.error('Failed to record feedback:', err);
          }
        });
      } else if (button.classList.contains('feedback-improve')) {
        button.addEventListener('click', () => {
          this.showImproveDialog(responseElement, taskId);
        });
      }
    });
  }
  
  /**
   * Show feedback confirmation
   */
  showFeedbackConfirmation(container, type) {
    const message = document.createElement('span');
    message.style.cssText = `
      font-size: 12px;
      color: ${type === 'positive' ? '#48bb78' : '#f56565'};
      margin-left: 10px;
    `;
    message.textContent = type === 'positive' ? '✓ Thanks!' : '✓ Feedback recorded';
    
    container.appendChild(message);
    
    // Remove buttons after feedback
    const buttons = container.querySelectorAll('.feedback-btn');
    buttons.forEach(btn => btn.style.display = 'none');
    
    setTimeout(() => {
      message.remove();
    }, 3000);
  }
  
  /**
   * Show improve dialog
   */
  showImproveDialog(responseElement, taskId) {
    // Get original response from data attribute or text content
    const originalResponse = responseElement.getAttribute('data-original-content') || 
                            responseElement.textContent.trim();
    
    // Create modal
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
      max-width: 600px;
      width: 90%;
      max-height: 80vh;
      overflow-y: auto;
    `;
    
    modal.innerHTML = `
      <h2 style="margin-top: 0;">Improve This Response</h2>
      <p style="color: #718096; font-size: 14px;">
        Edit the response below to teach TruAi how you'd prefer it to answer. This helps improve future responses.
      </p>
      <div style="margin: 20px 0;">
        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Original Response:</label>
        <pre style="background: #f7fafc; padding: 10px; border-radius: 4px; max-height: 150px; overflow-y: auto; font-size: 12px;">${originalResponse}</pre>
      </div>
      <div style="margin: 20px 0;">
        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Your Improved Version:</label>
        <textarea id="improvedResponse" style="
          width: 100%;
          min-height: 150px;
          padding: 10px;
          border: 1px solid #cbd5e0;
          border-radius: 4px;
          font-family: monospace;
          font-size: 12px;
        ">${originalResponse}</textarea>
      </div>
      <div style="display: flex; gap: 10px; justify-content: flex-end;">
        <button id="improveCancelBtn" style="
          padding: 10px 20px;
          background: #e2e8f0;
          border: none;
          border-radius: 4px;
          cursor: pointer;
        ">Cancel</button>
        <button id="improveSubmitBtn" style="
          padding: 10px 20px;
          background: #4299e1;
          color: white;
          border: none;
          border-radius: 4px;
          cursor: pointer;
        ">Submit Improvement</button>
      </div>
    `;
    
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    
    // Event listeners
    document.getElementById('improveCancelBtn').onclick = () => {
      document.body.removeChild(overlay);
    };
    
    document.getElementById('improveSubmitBtn').onclick = async () => {
      const correctedResponse = document.getElementById('improvedResponse').value;
      
      if (correctedResponse === originalResponse) {
        alert('Please make changes to the response before submitting.');
        return;
      }
      
      try {
        await this.recordCorrection(taskId, originalResponse, correctedResponse);
        document.body.removeChild(overlay);
        
        // Show success message
        const successMsg = document.createElement('div');
        successMsg.style.cssText = `
          position: fixed;
          top: 20px;
          right: 20px;
          background: #48bb78;
          color: white;
          padding: 15px 20px;
          border-radius: 4px;
          box-shadow: 0 4px 6px rgba(0,0,0,0.1);
          z-index: 10001;
        `;
        successMsg.textContent = '✓ Improvement recorded! TruAi will learn from this.';
        document.body.appendChild(successMsg);
        
        setTimeout(() => {
          document.body.removeChild(successMsg);
        }, 3000);
      } catch (err) {
        console.error('Failed to record correction:', err);
        alert('Failed to save improvement. Please try again.');
      }
    };
  }
  
  /**
   * Show learning insights panel
   */
  async showInsightsPanel() {
    try {
      const data = await this.getInsights();
      const insights = data.insights;
      
      // Create insights panel
      const panel = document.createElement('div');
      panel.id = 'learningInsightsPanel';
      panel.style.cssText = `
        position: fixed;
        top: 80px;
        right: 20px;
        width: 350px;
        max-height: 500px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        z-index: 1000;
        overflow-y: auto;
        padding: 20px;
      `;
      
      panel.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
          <h3 style="margin: 0;">Learning Insights</h3>
          <button id="closeInsightsPanel" style="
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #718096;
          ">×</button>
        </div>
        
        <div style="margin-bottom: 20px;">
          <h4 style="font-size: 14px; color: #4a5568; margin-bottom: 10px;">Preferred Models</h4>
          ${this.renderPreferences(insights.preferred_models, 'model')}
        </div>
        
        <div style="margin-bottom: 20px;">
          <h4 style="font-size: 14px; color: #4a5568; margin-bottom: 10px;">Preferred Tiers</h4>
          ${this.renderPreferences(insights.preferred_tiers, 'tier')}
        </div>
        
        <div style="margin-bottom: 20px;">
          <h4 style="font-size: 14px; color: #4a5568; margin-bottom: 10px;">Common Keywords</h4>
          ${this.renderKeywords(insights.common_keywords)}
        </div>
        
        <div style="font-size: 12px; color: #718096; margin-top: 15px; padding-top: 15px; border-top: 1px solid #e2e8f0;">
          Based on ${insights.total_events} learning events
        </div>
      `;
      
      document.body.appendChild(panel);
      
      document.getElementById('closeInsightsPanel').onclick = () => {
        document.body.removeChild(panel);
      };
    } catch (err) {
      console.error('Failed to load insights:', err);
    }
  }
  
  renderPreferences(prefs, type) {
    if (!prefs || Object.keys(prefs).length === 0) {
      return '<p style="font-size: 12px; color: #a0aec0;">No data yet</p>';
    }
    
    return Object.entries(prefs).map(([key, count]) => `
      <div style="display: flex; justify-content: space-between; padding: 5px 0; font-size: 13px;">
        <span>${key}</span>
        <span style="color: #4299e1; font-weight: bold;">${count}</span>
      </div>
    `).join('');
  }
  
  renderKeywords(keywords) {
    if (!keywords || Object.keys(keywords).length === 0) {
      return '<p style="font-size: 12px; color: #a0aec0;">No data yet</p>';
    }
    
    return '<div style="display: flex; flex-wrap: wrap; gap: 5px;">' +
      Object.entries(keywords).map(([word, count]) => `
        <span style="
          background: #edf2f7;
          padding: 4px 8px;
          border-radius: 12px;
          font-size: 12px;
          color: #4a5568;
        ">${word} (${count})</span>
      `).join('') +
      '</div>';
  }
}

// Export for browser use
window.TruAiLearningClient = TruAiLearningClient;
