/**
 * TruAi Settings Client
 * 
 * Handles settings panel UI and API interactions for AI configuration
 * 
 * @package TruAi
 * @version 1.0.0
 */

(function() {
  'use strict';

  // Settings client state
  const state = {
    apiBase: null,
    csrfToken: null,
    elements: {},
    isInitialized: false
  };

  /**
   * Initialize the settings client
   */
  function init(config) {
    if (state.isInitialized) {
      console.warn('TruAiSettingsClient already initialized');
      return;
    }

    // Get configuration
    state.apiBase = config?.API_BASE || (window.location.origin + '/TruAi/api/v1');
    state.csrfToken = config?.CSRF_TOKEN || '';

    // Get DOM elements
    state.elements = {
      settingsLink: document.getElementById('settingsLink'),
      centerPanel: document.getElementById('centerPanel'),
      settingsPanel: document.querySelector('.settings-panel'),
      form: document.getElementById('aiSettingsForm'),
      provider: document.getElementById('aiProvider'),
      apiKey: document.getElementById('aiApiKey'),
      model: document.getElementById('aiModel'),
      streaming: document.getElementById('enableStreaming'),
      saveButton: document.getElementById('saveSettings'),
      resetButton: document.getElementById('resetSettings'),
      status: document.getElementById('settingsStatus'),
      revealButton: document.getElementById('revealApiKey')
    };

    // Check if settings elements exist (they may not on all pages)
    if (!state.elements.settingsPanel || !state.elements.form) {
      console.log('Settings panel not found on this page');
      return;
    }

    // Attach event listeners
    attachEventListeners();

    state.isInitialized = true;
    console.log('TruAiSettingsClient initialized');
  }

  /**
   * Attach event listeners
   */
  function attachEventListeners() {
    // Settings link toggle - listen for panel expansion
    if (state.elements.settingsLink && state.elements.centerPanel) {
      state.elements.settingsLink.addEventListener('click', function(e) {
        // Panel will be expanded after the existing handler runs
        setTimeout(() => {
          if (state.elements.centerPanel.classList.contains('expanded')) {
            loadSettings();
          }
        }, 0);
      });
    }

    // Save button
    if (state.elements.saveButton) {
      state.elements.saveButton.addEventListener('click', saveSettings);
    }

    // Reset button
    if (state.elements.resetButton) {
      state.elements.resetButton.addEventListener('click', resetSettings);
    }

    // API key reveal toggle
    if (state.elements.revealButton) {
      state.elements.revealButton.addEventListener('click', toggleApiKeyVisibility);
    }
  }

  /**
   * Load settings from backend
   */
  async function loadSettings() {
    try {
      setStatus('Loading settings...', 'info');
      
      const response = await fetch(`${state.apiBase}/settings`, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json'
        }
      });

      if (!response.ok) {
        throw new Error(`Failed to load settings: ${response.status} ${response.statusText}`);
      }

      const data = await response.json();
      
      // Expected format: { settings: { ai: { ... }, editor: { ... }, ... } }
      if (data.settings) {
        populateForm(data.settings);
        setStatus('Settings loaded', 'success');
      } else {
        throw new Error('Invalid settings response format');
      }
    } catch (error) {
      console.error('Error loading settings:', error);
      setStatus(`Error: ${error.message}`, 'error');
    }
  }

  /**
   * Populate form with settings data
   */
  function populateForm(settings) {
    // Map backend settings to form fields
    // Backend has nested structure: { ai: { openaiApiKey, model, ... }, ... }
    const aiSettings = settings.ai || {};
    
    if (state.elements.provider) {
      // Default to 'openai' if not specified
      state.elements.provider.value = aiSettings.provider || 'openai';
    }
    
    if (state.elements.apiKey) {
      // Show the API key (masked by default in input type="password")
      state.elements.apiKey.value = aiSettings.openaiApiKey || '';
    }
    
    if (state.elements.model) {
      state.elements.model.value = aiSettings.model || 'gpt-4';
    }
    
    if (state.elements.streaming) {
      state.elements.streaming.checked = aiSettings.enableStreaming || false;
    }
  }

  /**
   * Save settings to backend
   */
  async function saveSettings() {
    try {
      setStatus('Saving settings...', 'info');
      
      // Gather form data
      const settingsData = {
        settings: {
          ai: {
            provider: state.elements.provider?.value || 'openai',
            openaiApiKey: state.elements.apiKey?.value || '',
            model: state.elements.model?.value || 'gpt-4',
            enableStreaming: state.elements.streaming?.checked || false
          }
        }
      };

      const response = await fetch(`${state.apiBase}/settings`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': state.csrfToken
        },
        body: JSON.stringify(settingsData)
      });

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.error || `Failed to save settings: ${response.status}`);
      }

      const data = await response.json();
      
      if (data.success) {
        setStatus('Settings saved successfully!', 'success');
      } else {
        throw new Error('Save operation did not return success');
      }
    } catch (error) {
      console.error('Error saving settings:', error);
      setStatus(`Error: ${error.message}`, 'error');
    }
  }

  /**
   * Reset settings to defaults
   */
  async function resetSettings() {
    if (!confirm('Are you sure you want to reset all settings to defaults?')) {
      return;
    }

    try {
      setStatus('Resetting settings...', 'info');
      
      const response = await fetch(`${state.apiBase}/settings/reset`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': state.csrfToken
        }
      });

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.error || `Failed to reset settings: ${response.status}`);
      }

      const data = await response.json();
      
      if (data.success) {
        setStatus('Settings reset to defaults', 'success');
        // Reload settings to show defaults
        await loadSettings();
      } else {
        throw new Error('Reset operation did not return success');
      }
    } catch (error) {
      console.error('Error resetting settings:', error);
      setStatus(`Error: ${error.message}`, 'error');
    }
  }

  /**
   * Toggle API key visibility
   */
  function toggleApiKeyVisibility() {
    if (!state.elements.apiKey) return;
    
    const input = state.elements.apiKey;
    const button = state.elements.revealButton;
    
    if (input.type === 'password') {
      input.type = 'text';
      if (button) button.textContent = 'Hide';
    } else {
      input.type = 'password';
      if (button) button.textContent = 'Reveal';
    }
  }

  /**
   * Set status message
   */
  function setStatus(message, type = 'info') {
    if (!state.elements.status) return;
    
    state.elements.status.textContent = message;
    state.elements.status.className = `status-${type}`;
    
    // Auto-clear success messages after 3 seconds
    if (type === 'success') {
      setTimeout(() => {
        if (state.elements.status.textContent === message) {
          state.elements.status.textContent = '';
          state.elements.status.className = '';
        }
      }, 3000);
    }
  }

  // Export to window for testing
  window.TruAiSettingsClient = {
    init: init,
    loadSettings: loadSettings,
    saveSettings: saveSettings,
    resetSettings: resetSettings
  };

  // Auto-initialize on DOMContentLoaded if TRUAI_CONFIG is available
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      if (window.TRUAI_CONFIG) {
        init(window.TRUAI_CONFIG);
      }
    });
  } else {
    // DOM already loaded
    if (window.TRUAI_CONFIG) {
      init(window.TRUAI_CONFIG);
    }
  }
})();
