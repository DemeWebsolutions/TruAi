/**
 * Data Sharing Consent Modal
 * 
 * Cursor IDE style data sharing consent
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

let dataSharingConsentShown = localStorage.getItem('truai_data_sharing_consent_shown') === 'true';
let dataSharingEnabled = localStorage.getItem('truai_data_sharing_enabled') === 'true';

function showDataSharingConsent() {
    // In v1, never show the consent modal - consent is implicit on first use
    // This function is kept for compatibility with existing callers
    return false;
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
window.handleDataSharingConsent = handleDataSharingConsent;
