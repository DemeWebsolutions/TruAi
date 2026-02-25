/**
 * Data Sharing Consent Modal
 * 
 * Cursor IDE style data sharing consent
 * Implements "first day off" via timestamp logic
 * 
 * @package TruAi
 * @version 1.0.0
 */

// Initialize first use tracking and implicit consent on first use
function initializeConsentOnFirstUse() {
    const firstUseTimestamp = localStorage.getItem('truai_first_use_timestamp');
    
    // If this is the first use, set all consent values to enabled by default
    if (!firstUseTimestamp) {
        const now = Date.now();
        localStorage.setItem('truai_first_use_timestamp', now.toString());
        localStorage.setItem('truai_data_sharing_enabled', 'true');
        localStorage.setItem('truai_data_sharing_consent_shown', 'true');
    }
}

// Initialize consent on script load
initializeConsentOnFirstUse();

// Initialize state variables after auto-initialization
let dataSharingConsentShown = localStorage.getItem('truai_data_sharing_consent_shown') === 'true';
let dataSharingEnabled = localStorage.getItem('truai_data_sharing_enabled') === 'true';
let firstUseTimestamp = localStorage.getItem('truai_first_use_timestamp');

function showDataSharingConsent() {
    // First use: set timestamp, grant implicit consent, don't show modal
    if (!firstUseTimestamp) {
        const now = Date.now();
        localStorage.setItem('truai_first_use_timestamp', now.toString());
        localStorage.setItem('truai_data_sharing_enabled', 'true');
        localStorage.setItem('truai_data_sharing_consent_shown', 'true');
        dataSharingConsentShown = true;
        dataSharingEnabled = true;
        firstUseTimestamp = now.toString();
        return false;
    }
    
    // Check if less than 24 hours since first use
    const hoursSinceFirstUse = (Date.now() - parseInt(firstUseTimestamp)) / (1000 * 60 * 60);
    if (hoursSinceFirstUse < 24) {
        // First day - don't show modal
        return false;
    }
    
    // After first day: only show if consent not yet shown
    if (dataSharingConsentShown) {
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
                    TruAi learns from code to help you get the best experience possible.
                </p>
                
                <ul class="data-sharing-points">
                    <li>
                        <strong>You're in control.</strong> Turn off anytime in Settings → Privacy.
                    </li>
                    <li>
                        <strong>Data sharing is off the first day.</strong> After one day of use, TruAi may store and learn from your prompts, codebase, edit history, and other usage data to improve the product.
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
            // Route to Settings → General (Privacy section)
            if (typeof window.renderDashboard === 'function') {
                window.activePanel = 'settings';
                window.activeSettingsTab = 'general';
                window.renderDashboard();
                // Try to focus Privacy section if it exists
                setTimeout(function() {
                    const privacySection = document.querySelector('[data-section="privacy"]') 
                        || document.querySelector('.privacy-section')
                        || document.getElementById('privacySection');
                    if (privacySection && privacySection.scrollIntoView) {
                        privacySection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }, 100);
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
    // No-op in v1 since modal is never shown
    // Kept for compatibility
}

function handleDataSharingConsent(enabled) {
    // This function is available for future use and for Settings toggles
    // It updates the data sharing preference without showing any modal
    dataSharingEnabled = enabled;
    dataSharingConsentShown = true;
    
    localStorage.setItem('truai_data_sharing_consent_shown', 'true');
    localStorage.setItem('truai_data_sharing_enabled', enabled ? 'true' : 'false');
    
    // Note: This function doesn't trigger dashboard initialization anymore
    // since consent is handled implicitly on first use
}

// Make functions globally accessible
window.showDataSharingConsent = showDataSharingConsent;
window.hideDataSharingConsent = hideDataSharingConsent;
window.handleDataSharingConsent = handleDataSharingConsent;
