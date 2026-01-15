/**
 * Data Sharing Consent Modal
 * 
 * Cursor IDE style data sharing consent
 * 
 * @package TruAi
 * @version 1.0.0
 */

let dataSharingConsentShown = localStorage.getItem('truai_data_sharing_consent_shown') === 'true';
let dataSharingEnabled = localStorage.getItem('truai_data_sharing_enabled') === 'true';

function showDataSharingConsent() {
    // Only show once per session or if not yet consented
    if (dataSharingConsentShown && dataSharingEnabled) {
        return false;
    }
    
    const overlay = document.createElement('div');
    overlay.id = 'data-sharing-overlay';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 10000;
    `;
    
    overlay.innerHTML = `
        <div class="data-sharing-modal">
            <div class="data-sharing-header">
                <h1 class="data-sharing-title">Data Sharing</h1>
                <p class="data-sharing-subtitle">Help improve TruAi for everyone</p>
            </div>
            
            <div class="data-sharing-content">
                <p class="data-sharing-intro">
                    By default, TruAi learns from code to help you get the best experience possible.
                </p>
                
                <ul class="data-sharing-points">
                    <li>
                        <strong>You're in control.</strong> Turn off anytime in Settings → Privacy.
                    </li>
                    <li>
                        <strong>Data sharing is off the first day.</strong> After one day of use, TruAi stores and learns from your prompts, codebase, edit history, and other usage data to improve the product.
                    </li>
                </ul>
                
                <a href="#" class="data-sharing-link" id="privacyLink">More on Privacy Policy and Security</a>
            </div>
            
            <div class="data-sharing-consent">
                <label class="data-sharing-checkbox">
                    <input type="checkbox" id="dataSharingConsent" checked>
                    <span>I'm fine with TruAi learning from my code or I'll turn it off in Settings</span>
                </label>
            </div>
            
            <div class="data-sharing-actions">
                <button class="btn-continue-data" id="continueDataSharingBtn">Continue</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(overlay);
    
    // Setup listeners
    const continueBtn = document.getElementById('continueDataSharingBtn');
    const consentCheckbox = document.getElementById('dataSharingConsent');
    const privacyLink = document.getElementById('privacyLink');
    
    if (continueBtn) {
        continueBtn.addEventListener('click', function() {
            const enabled = consentCheckbox.checked;
            handleDataSharingConsent(enabled);
        });
    }
    
    if (privacyLink) {
        privacyLink.addEventListener('click', function(e) {
            e.preventDefault();
            // Open settings to privacy section
            if (typeof renderDashboard === 'function') {
                activePanel = 'settings';
                activeSettingsTab = 'general';
                renderDashboard();
            }
            hideDataSharingConsent();
        });
    }
    
    // Close on overlay click (outside modal)
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            // Don't close on overlay click - require explicit consent
        }
    });
    
    return true;
}

function hideDataSharingConsent() {
    const overlay = document.getElementById('data-sharing-overlay');
    if (overlay) {
        overlay.remove();
    }
}

function handleDataSharingConsent(enabled) {
    dataSharingEnabled = enabled;
    dataSharingConsentShown = true;
    
    localStorage.setItem('truai_data_sharing_consent_shown', 'true');
    localStorage.setItem('truai_data_sharing_enabled', enabled ? 'true' : 'false');
    
    hideDataSharingConsent();
    
    // Continue with welcome screen or dashboard
    if (typeof showWelcomeScreen === 'function') {
        const welcomeShown = showWelcomeScreen();
        if (!welcomeShown && typeof initializeDashboardScript === 'function') {
            initializeDashboardScript();
        }
    } else if (typeof initializeDashboardScript === 'function') {
        initializeDashboardScript();
    }
}

// Make functions globally accessible
window.showDataSharingConsent = showDataSharingConsent;
window.handleDataSharingConsent = handleDataSharingConsent;
