/**
 * TruAi API Test Harness - DEV ONLY
 * 
 * Lightweight test utility for validating AI API payloads
 * DO NOT INCLUDE IN PRODUCTION BUILDS
 * 
 * @package TruAi
 * @version 1.0.0
 */

// Mock TRUAI_CONFIG for standalone testing if not already defined
if (typeof window.TRUAI_CONFIG === 'undefined') {
    window.TRUAI_CONFIG = {
        API_BASE: '/api/v1',
        APP_VERSION: '1.0.0',
        CSRF_TOKEN: ''
    };
}

/**
 * Send test message to AI API
 */
async function sendTestMessage() {
    // Get form values
    const message = document.getElementById('message').value.trim();
    const conversationId = document.getElementById('conversationId').value.trim() || null;
    const model = document.getElementById('model').value;
    
    // Metadata fields
    const intent = document.getElementById('intent').value.trim();
    const risk = document.getElementById('risk').value;
    const forensicId = document.getElementById('forensicId').value.trim();
    const scope = document.getElementById('scope').value.trim();
    const selectionLength = document.getElementById('selectionLength').value.trim();

    // Validation
    if (!message) {
        showStatus('error', 'Message is required');
        return;
    }

    // Build metadata object (only include non-empty values)
    const metadata = {};
    if (intent) metadata.intent = intent;
    if (risk) metadata.risk = risk;
    if (forensicId) metadata.forensic_id = forensicId;
    if (scope) metadata.scope = scope;
    if (selectionLength) metadata.selection_length = parseInt(selectionLength, 10);

    // Build request payload for preview
    const requestPayload = {
        message,
        conversation_id: conversationId,
        model
    };

    // Merge metadata into payload (this is what the API client will do)
    if (Object.keys(metadata).length > 0) {
        Object.assign(requestPayload, metadata);
    }

    // Show payload preview
    showPayloadPreview(requestPayload);

    // Disable send button
    const sendBtn = document.getElementById('sendBtn');
    sendBtn.disabled = true;
    sendBtn.textContent = 'Sending...';

    const startTime = performance.now();

    try {
        // Create API client and send message
        const api = new TruAiAPI();
        
        // Use the sendMessage method with metadata parameter
        const response = await api.sendMessage(
            message,
            conversationId,
            model,
            Object.keys(metadata).length > 0 ? metadata : null
        );

        const endTime = performance.now();
        const duration = Math.round(endTime - startTime);

        // Show response
        showResponse('success', response, duration);

    } catch (error) {
        const endTime = performance.now();
        const duration = Math.round(endTime - startTime);

        // Show error
        showResponse('error', { error: error.message }, duration);
    } finally {
        // Re-enable send button
        sendBtn.disabled = false;
        sendBtn.textContent = 'Send Message';
    }
}

/**
 * Show payload preview
 */
function showPayloadPreview(payload) {
    const section = document.getElementById('payloadSection');
    const preview = document.getElementById('payloadPreview');
    
    preview.textContent = JSON.stringify(payload, null, 2);
    section.style.display = 'block';
}

/**
 * Show response
 */
function showResponse(type, data, duration) {
    const section = document.getElementById('responseSection');
    const status = document.getElementById('responseStatus');
    const responseData = document.getElementById('responseData');
    const timing = document.getElementById('timingInfo');

    // Status message
    if (type === 'success') {
        status.innerHTML = '<div class="status success">✓ Request successful</div>';
    } else {
        status.innerHTML = '<div class="status error">✗ Request failed</div>';
    }

    // Response data
    responseData.textContent = JSON.stringify(data, null, 2);

    // Timing info
    timing.textContent = `Request completed in ${duration}ms`;

    // Show section
    section.style.display = 'block';

    // Scroll to response
    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/**
 * Show status message
 */
function showStatus(type, message) {
    const statusEl = document.getElementById('statusMessage');
    statusEl.innerHTML = `<div class="status ${type}">${message}</div>`;
    
    // Auto-clear after 5 seconds
    setTimeout(() => {
        statusEl.innerHTML = '';
    }, 5000);
}

/**
 * Clear form
 */
function clearForm() {
    document.getElementById('message').value = 'Test message';
    document.getElementById('conversationId').value = '';
    document.getElementById('model').value = 'auto';
    document.getElementById('intent').value = '';
    document.getElementById('risk').value = '';
    document.getElementById('forensicId').value = '';
    document.getElementById('scope').value = '';
    document.getElementById('selectionLength').value = '';
    
    showStatus('info', 'Form cleared');
}

/**
 * Clear results
 */
function clearResults() {
    document.getElementById('payloadSection').style.display = 'none';
    document.getElementById('responseSection').style.display = 'none';
    document.getElementById('payloadPreview').textContent = '';
    document.getElementById('responseData').textContent = '';
    
    showStatus('info', 'Results cleared');
}

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    console.log('TruAi API Test Harness loaded - DEV ONLY');
    showStatus('info', 'Test harness ready. This tool is for development use only.');
});
