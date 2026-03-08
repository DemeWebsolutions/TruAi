/**
 * DemeWebsolutions Biometric Auth — Content Script
 * Auto-fills login forms using OS keychain credentials (via native host).
 */

(function () {
    'use strict';

    const APP_MAP = {
        '/TruAi/'   : 'truai',
        '/Gemini/'  : 'gemini',
        '/Phantom/' : 'phantom',
    };

    // Delay to allow page JavaScript to finish initializing login form handlers
    const AUTO_FILL_DELAY_MS = 600;

    function detectApp() {
        const path = window.location.pathname;
        for (const [prefix, app] of Object.entries(APP_MAP)) {
            if (path.includes(prefix)) return app;
        }
        return null;
    }

    function showIndicator(message) {
        const el = document.createElement('div');
        el.id = 'demewebsolutions-biometric-indicator';
        el.style.cssText = [
            'position:fixed', 'top:16px', 'right:16px',
            'background:linear-gradient(135deg,#667eea 0%,#764ba2 100%)',
            'color:white', 'padding:12px 18px', 'border-radius:10px',
            'box-shadow:0 4px 15px rgba(0,0,0,0.25)',
            'z-index:2147483647',
            'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif',
            'font-size:13px', 'font-weight:600',
        ].join(';');
        el.textContent = message;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 4000);
    }

    async function autoFillLogin(app) {
        try {
            const response = await chrome.runtime.sendMessage({ action: 'getCredentials', app });
            if (!response || !response.success) return;

            const { username, password } = response.credentials;

            const usernameField = document.querySelector('input[name="username"],input[type="text"],input[id*="user"]');
            if (usernameField) {
                usernameField.value = username;
                usernameField.dispatchEvent(new Event('input', { bubbles: true }));
                usernameField.dispatchEvent(new Event('change', { bubbles: true }));
            }

            const passwordField = document.querySelector('input[name="password"],input[type="password"]');
            if (passwordField) {
                passwordField.value = password;
                passwordField.dispatchEvent(new Event('input', { bubbles: true }));
                passwordField.dispatchEvent(new Event('change', { bubbles: true }));
            }

            showIndicator('🔐 Biometric credentials loaded');

            // Auto-submit after short delay
            setTimeout(() => {
                const submit = document.querySelector('button[type="submit"],input[type="submit"],#loginSubmitButton');
                if (submit) submit.click();
            }, 1200);
        } catch (err) {
            console.log('[DemeWebsolutions] Auto-fill error:', err);
        }
    }

    function init() {
        const app = detectApp();
        if (app && document.querySelector('input[type="password"]')) {
            console.log('[DemeWebsolutions] Login page detected for app:', app);
            setTimeout(() => autoFillLogin(app), AUTO_FILL_DELAY_MS);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
