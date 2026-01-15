/**
 * TruAi Legal Notice Popup
 * 
 * Shows legal notice as a modal popup before accessing the dashboard
 * 
 * @package TruAi
 * @version 1.0.0
 */

console.log('Legal notice popup script loaded, DOM state:', document.readyState);

function initializeLegalNotice() {
    console.log('Initializing legal notice...');
    // Check if user has already acknowledged
    const acknowledged = sessionStorage.getItem('truai_legal_acknowledged');
    console.log('Acknowledged:', acknowledged);
    
    if (!acknowledged) {
        console.log('Showing legal notice popup...');
        showLegalNoticePopup();
    } else {
        console.log('Already acknowledged, initializing dashboard...');
        // Already acknowledged, proceed to dashboard
        initializeDashboard();
    }
}

function showLegalNoticePopup() {
    console.log('Creating legal notice popup...');
    // Create overlay
    const overlay = document.createElement('div');
    overlay.id = 'legal-notice-overlay';
    overlay.style.cssText = `
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        height: 100% !important;
        background: rgba(0, 0, 0, 0.85) !important;
        z-index: 99999 !important;
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        padding: 20px !important;
    `;

    // Create popup
    const popup = document.createElement('div');
    popup.id = 'legal-notice-popup';
    popup.style.cssText = `
        background: #1a1d24 !important;
        border: 2px solid #2d3139 !important;
        border-radius: 12px !important;
        max-width: 800px !important;
        max-height: 90vh !important;
        width: 100% !important;
        overflow-y: auto !important;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5) !important;
        color: #fff !important;
        z-index: 100000 !important;
        position: relative !important;
    `;

    popup.innerHTML = `
        <div style="padding: 30px;">
            <div style="text-align: center; margin-bottom: 25px;">
                <img src="/assets/images/TruAi-transparent-bg.png" alt="TruAi Logo" style="max-width: 150px; margin-bottom: 15px;" onerror="this.style.display='none'">
                <h1 style="font-size: 28px; margin-bottom: 10px; color: #fff;">Tru.ai</h1>
                <p style="color: #888; font-size: 16px;">Super Admin AI Platform</p>
            </div>

            <div style="border-top: 1px solid #2d3139; border-bottom: 1px solid #2d3139; padding: 25px 0; margin: 25px 0;">
                <h2 style="font-size: 22px; margin-bottom: 20px; color: #ff6b6b;">⚠️ LEGAL NOTICE & TERMS OF USE</h2>
                
                <div style="line-height: 1.8; font-size: 14px;">
                    <p style="margin-bottom: 15px;"><strong style="color: #fff;">PROPRIETARY SYSTEM NOTICE</strong></p>
                    <p style="margin-bottom: 20px; color: #ccc;">You are accessing a proprietary system owned and operated by My Deme, LLC.</p>
                    
                    <p style="margin-bottom: 15px;"><strong style="color: #fff;">TERMS AND CONDITIONS</strong></p>
                    <ul style="margin-left: 20px; margin-bottom: 20px; color: #ccc;">
                        <li style="margin-bottom: 8px;">This system and all its contents are confidential and proprietary.</li>
                        <li style="margin-bottom: 8px;">Unauthorized access, use, disclosure, or distribution is strictly prohibited.</li>
                        <li style="margin-bottom: 8px;">All activities are monitored and logged for security purposes.</li>
                        <li style="margin-bottom: 8px;">You acknowledge that you have authorized access to this system.</li>
                        <li style="margin-bottom: 8px;">You agree to comply with all applicable laws and regulations.</li>
                        <li style="margin-bottom: 8px;">Sessions will automatically expire after 30 minutes of inactivity.</li>
                    </ul>
                    
                    <p style="margin-bottom: 15px;"><strong style="color: #fff;">LEGAL PROTECTIONS</strong></p>
                    <p style="margin-bottom: 10px; color: #ccc;">This system is protected under:</p>
                    <ul style="margin-left: 20px; margin-bottom: 20px; color: #ccc;">
                        <li style="margin-bottom: 8px;">Florida State Law</li>
                        <li style="margin-bottom: 8px;">United States Federal Law</li>
                        <li style="margin-bottom: 8px;">Trade Secret and Intellectual Property Protections</li>
                    </ul>
                    
                    <p style="margin-bottom: 15px;"><strong style="color: #fff;">VIOLATIONS & PENALTIES</strong></p>
                    <p style="margin-bottom: 10px; color: #ccc;">Unauthorized access or use may result in:</p>
                    <ul style="margin-left: 20px; margin-bottom: 20px; color: #ccc;">
                        <li style="margin-bottom: 8px;">Immediate termination of access</li>
                        <li style="margin-bottom: 8px;">Civil liability and damages</li>
                        <li style="margin-bottom: 8px;">Criminal prosecution under state and federal law</li>
                    </ul>
                    
                    <p style="margin-top: 25px; padding: 15px; background: #2d3139; border-radius: 6px; color: #fff; font-weight: 500;">
                        <strong>By clicking "I Acknowledge and Agree" below, you confirm that you have read, understood, and agree to abide by these terms.</strong>
                    </p>
                </div>
            </div>

            <div style="display: flex; gap: 15px; justify-content: center; margin-top: 30px;">
                <button id="acknowledgeBtn" type="button" style="
                    background: #4a9eff !important;
                    color: #fff !important;
                    border: none !important;
                    padding: 12px 40px !important;
                    border-radius: 6px !important;
                    font-size: 16px !important;
                    font-weight: 600 !important;
                    cursor: pointer !important;
                    transition: background 0.2s !important;
                    pointer-events: auto !important;
                    z-index: 100001 !important;
                    position: relative !important;
                " onmouseover="this.style.background='#3a8eef'" onmouseout="this.style.background='#4a9eff'" onclick="
                    console.log('Button onclick handler fired');
                    if (typeof window.handleAcknowledge === 'function') {
                        window.handleAcknowledge();
                    } else {
                        console.error('handleAcknowledge function not found!');
                    }
                ">
                    I Acknowledge and Agree
                </button>
            </div>
        </div>
    `;

    // Define handle function BEFORE creating popup (so it's available for onclick)
    function handleButtonClick(e) {
        if (e) {
            if (e.preventDefault) e.preventDefault();
            if (e.stopPropagation) e.stopPropagation();
        }
        
        console.log('Acknowledge button clicked!');
        
        // Store acknowledgment in session storage
        sessionStorage.setItem('truai_legal_acknowledged', 'true');
        console.log('Acknowledgment stored in sessionStorage');
        
        // Remove popup
        if (overlay && overlay.parentNode) {
            document.body.removeChild(overlay);
            console.log('Popup removed from DOM');
        }
        
        // Initialize dashboard
        console.log('Initializing dashboard...');
        initializeDashboard();
    }
    
    // Global function for onclick handler - MUST be set before popup is added to DOM
    window.handleAcknowledge = function() {
        console.log('handleAcknowledge() called via onclick');
        handleButtonClick(null);
    };
    
    // Add popup to overlay, then overlay to body
    overlay.appendChild(popup);
    document.body.appendChild(overlay);
    console.log('Legal notice popup created and added to DOM');

    // Wait for button to be in DOM, then attach event listener
    setTimeout(function() {
        const acknowledgeBtn = document.getElementById('acknowledgeBtn');
        if (!acknowledgeBtn) {
            console.error('Acknowledge button not found!');
            return;
        }
        
        console.log('Acknowledge button found, attaching listener...');
        
        // Attach event listener
        acknowledgeBtn.addEventListener('click', handleButtonClick);
        
        // Also attach to mousedown as backup
        acknowledgeBtn.addEventListener('mousedown', function(e) {
            console.log('Button mousedown event');
            e.preventDefault();
        });
        
        console.log('Event listeners attached to acknowledge button');
    }, 100);

    // Prevent overlay from intercepting button clicks
    overlay.addEventListener('click', function(e) {
        // If clicking on the popup itself (not overlay), don't prevent
        if (e.target.closest('#legal-notice-popup')) {
            return;
        }
        // If clicking outside popup, optionally prevent closing
        if (e.target === overlay) {
            // Allow closing by clicking outside (optional)
            // Uncomment to require button click:
            // e.stopPropagation();
            // return;
        }
    });
    
    // Also add direct onclick as fallback
    popup.addEventListener('click', function(e) {
        if (e.target.id === 'acknowledgeBtn' || e.target.closest('#acknowledgeBtn')) {
            e.stopPropagation();
            const btn = document.getElementById('acknowledgeBtn');
            if (btn) {
                console.log('Fallback: Button clicked via popup click handler');
                btn.click();
            }
        }
    });
}

function initializeDashboard() {
    console.log('Initializing dashboard...');
    // Store acknowledgment
    sessionStorage.setItem('truai_legal_acknowledged', 'true');
    
    // Trigger dashboard render
    const event = new Event('dashboard-ready');
    document.dispatchEvent(event);
    
    console.log('Legal notice acknowledged, dashboard access granted');
}

// Wait for DOM and config to be ready
(function() {
    function startInit() {
        // Ensure TRUAI_CONFIG exists
        if (typeof window.TRUAI_CONFIG === 'undefined') {
            console.warn('TRUAI_CONFIG not ready, waiting...');
            setTimeout(startInit, 100);
            return;
        }
        console.log('TRUAI_CONFIG ready:', window.TRUAI_CONFIG);
        initializeLegalNotice();
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOMContentLoaded fired');
            setTimeout(startInit, 100);
        });
    } else {
        // DOM already loaded
        console.log('DOM already loaded, starting init...');
        setTimeout(startInit, 100);
    }
})();
