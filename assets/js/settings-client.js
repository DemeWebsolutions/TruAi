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
      apiKeyOpenAI: document.getElementById('aiApiKeyOpenAI'),
      apiKeySonnet: document.getElementById('aiApiKeySonnet'),
      modelOpenAI: document.getElementById('aiModelOpenAI'),
      modelSonnet: document.getElementById('aiModelSonnet'),
      defaultProvider: document.getElementById('defaultProvider'),
      streaming: document.getElementById('enableStreaming'),
      saveButton: document.getElementById('saveSettings'),
      resetButton: document.getElementById('resetSettings'),
      status: document.getElementById('settingsStatus'),
      revealButtonOpenAI: document.getElementById('revealApiKeyOpenAI'),
      revealButtonSonnet: document.getElementById('revealApiKeySonnet')
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

    // API key reveal toggles
    if (state.elements.revealButtonOpenAI) {
      state.elements.revealButtonOpenAI.addEventListener('click', () => toggleApiKeyVisibility('openai'));
    }
    if (state.elements.revealButtonSonnet) {
      state.elements.revealButtonSonnet.addEventListener('click', () => toggleApiKeyVisibility('sonnet'));
    }
  }

  /**
   * Load settings from backend
   */
  async function loadSettings() {
    try {
      setStatus('Loading settings...', 'info');
      
      // Update CSRF token from global config if available
      if (window.TRUAI_CONFIG?.CSRF_TOKEN) {
        state.csrfToken = window.TRUAI_CONFIG.CSRF_TOKEN;
      }
      
      const headers = {
        'Content-Type': 'application/json'
      };
      
      // Add CSRF token if available
      if (state.csrfToken) {
        headers['X-CSRF-Token'] = state.csrfToken;
      }
      
      const response = await fetch(`${state.apiBase}/settings`, {
        method: 'GET',
        credentials: 'include', // Important: sends cookies for session auth (include works better than same-origin)
        headers: headers,
        cache: 'no-cache' // Ensure fresh request
      });

      // Handle 401 Unauthorized - redirect to login
      if (response.status === 401) {
        console.warn('Unauthorized - redirecting to login');
        if (window.TRUAI_CONFIG) {
          window.TRUAI_CONFIG.IS_AUTHENTICATED = false;
        }
        window.location.href = '/TruAi/login-portal.html';
        return;
      }

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
    // Handle both old format (ai.openaiApiKey) and new format (providers)
    const aiSettings = settings.ai || {};
    const providers = settings.providers || {};
    
    // Load OpenAI settings
    if (state.elements.apiKeyOpenAI) {
      state.elements.apiKeyOpenAI.value = providers.openai?.api_key || aiSettings.openaiApiKey || '';
    }
    if (state.elements.modelOpenAI) {
      state.elements.modelOpenAI.value = providers.openai?.default_model || aiSettings.openaiModel || 'gpt-4o';
    }
    
    // Load Sonnet/Anthropic settings
    if (state.elements.apiKeySonnet) {
      state.elements.apiKeySonnet.value = providers.sonnet?.api_key || aiSettings.anthropicApiKey || '';
    }
    if (state.elements.modelSonnet) {
      state.elements.modelSonnet.value = providers.sonnet?.default_model || aiSettings.anthropicModel || 'sonnet-1';
    }
    
    // Load default provider
    if (state.elements.defaultProvider) {
      state.elements.defaultProvider.value = settings.default_provider || aiSettings.provider || 'openai';
    }
    
    // Load streaming setting
    if (state.elements.streaming) {
      state.elements.streaming.checked = settings.enable_streaming !== undefined ? settings.enable_streaming : (aiSettings.enableStreaming || false);
    }
  }

  /**
   * Save settings to backend
   */
  async function saveSettings() {
    try {
      setStatus('Saving settings...', 'info');
      
      // Gather form data in the providers format
      const settingsData = {
        settings: {
          providers: {
            openai: {
              api_key: state.elements.apiKeyOpenAI?.value || '',
              default_model: state.elements.modelOpenAI?.value || 'gpt-4o'
            },
            sonnet: {
              api_key: state.elements.apiKeySonnet?.value || '',
              default_model: state.elements.modelSonnet?.value || 'sonnet-1'
            }
          },
          default_provider: state.elements.defaultProvider?.value || 'openai',
          enable_streaming: state.elements.streaming?.checked || false
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

      // Handle 401 Unauthorized - redirect to login
      if (response.status === 401) {
        console.warn('Unauthorized - redirecting to login');
        window.location.href = '/TruAi/login-portal.html';
        return;
      }

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
        credentials: 'include', // Important: sends cookies for session auth
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': state.csrfToken
        },
        cache: 'no-cache'
      });

      // Handle 401 Unauthorized - redirect to login
      if (response.status === 401) {
        console.warn('Unauthorized - redirecting to login');
        window.location.href = '/TruAi/login-portal.html';
        return;
      }

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
  function toggleApiKeyVisibility(provider) {
    let input, button;
    
    if (provider === 'openai') {
      input = state.elements.apiKeyOpenAI;
      button = state.elements.revealButtonOpenAI;
    } else if (provider === 'sonnet') {
      input = state.elements.apiKeySonnet;
      button = state.elements.revealButtonSonnet;
    } else {
      return;
    }
    
    if (!input) return;
    
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
