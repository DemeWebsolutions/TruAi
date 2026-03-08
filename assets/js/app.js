/**
 * TruAi Main Application
 * 
 * Core application logic
 * 
 * @package TruAi
 * @version 1.0.0
 */

// Global application state
window.TruAiApp = {
    currentTask: null,
    currentConversation: null,
    selectedTier: 'auto',
    
    init() {
        console.log('TruAi v' + window.TRUAI_CONFIG.APP_VERSION + ' initialized');
    }
};

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    window.TruAiApp.init();
});
