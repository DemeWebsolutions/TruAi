/**
 * TruAi Login Page
 * 
 * Handles login interface with legal notices (Phantom.ai style)
 * 
 * @package TruAi
 * @version 1.0.0
 */

document.addEventListener('DOMContentLoaded', function() {
    const api = new TruAiAPI();
    const crypto = new TruAiCrypto();
    
    // Check if encryption is supported
    const encryptionSupported = TruAiCrypto.isSupported();
    
    if (!encryptionSupported) {
        console.warn('Web Crypto API not supported. Using fallback authentication.');
    }
    
    // Initialize encryption
    let encryptionReady = false;
    if (encryptionSupported) {
        crypto.initialize().then(ready => {
            encryptionReady = ready;
            if (ready) {
                console.log('🔒 Encrypted login enabled (Phantom.ai style)');
            }
        });
    }
    
    // Render login page
    const app = document.getElementById('app');
    app.innerHTML = `
        <div class="login-container">
            <div class="login-box">
                <div class="login-header">
                    <img src="/assets/images/TruAi-transparent-bg.png" alt="TruAi Logo" class="login-logo-img">
                    <h1 class="login-title">Tru.ai</h1>
                    <p class="login-subtitle">Super Admin AI Platform</p>
                </div>

                <form id="loginForm" class="login-form">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            autocomplete="username"
                            required 
                            autofocus
                        >
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            autocomplete="current-password"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="acceptTerms" required>
                            <span>I Acknowledge and Agree</span>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary" id="loginBtn">
                        Sign In
                    </button>

                    <div id="errorMessage" class="error-message hidden"></div>
                </form>

                <div class="legal-notice">
                    <h3>⚠️ LEGAL NOTICE & TERMS OF USE</h3>
                    <div class="legal-content">
                        <p><strong>PROPRIETARY SYSTEM NOTICE</strong></p>
                        <p>You are accessing a proprietary system owned and operated by My Deme, LLC.</p>
                        
                        <p><strong>TERMS AND CONDITIONS</strong></p>
                        <ul>
                            <li>This system and all its contents are confidential and proprietary.</li>
                            <li>Unauthorized access, use, disclosure, or distribution is strictly prohibited.</li>
                            <li>All activities are monitored and logged for security purposes.</li>
                            <li>You acknowledge that you have authorized access to this system.</li>
                            <li>You agree to comply with all applicable laws and regulations.</li>
                            <li>Sessions will automatically expire after 30 minutes of inactivity.</li>
                        </ul>
                        
                        <p><strong>LEGAL PROTECTIONS</strong></p>
                        <p>This system is protected under:</p>
                        <ul>
                            <li>Florida State Law</li>
                            <li>United States Federal Law</li>
                            <li>Trade Secret and Intellectual Property Protections</li>
                        </ul>
                        
                        <p><strong>VIOLATIONS & PENALTIES</strong></p>
                        <p>Unauthorized access or use may result in:</p>
                        <ul>
                            <li>Immediate termination of access</li>
                            <li>Civil liability and damages</li>
                            <li>Criminal prosecution under state and federal law</li>
                        </ul>
                        
                        <p><strong>By clicking "I Acknowledge and Agree" below, you confirm that you have read, understood, and agree to abide by these terms.</strong></p>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Handle login form submission
    const loginForm = document.getElementById('loginForm');
    const errorMessage = document.getElementById('errorMessage');
    const loginBtn = document.getElementById('loginBtn');

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        const acceptTerms = document.getElementById('acceptTerms').checked;

        if (!acceptTerms) {
            showError('You must accept the Terms of Service to continue');
            return;
        }

        // Disable form
        loginBtn.disabled = true;
        loginBtn.textContent = encryptionReady ? '🔒 Encrypting & Signing in...' : 'Signing in...';
        errorMessage.classList.add('hidden');

        try {
            let result;
            
            if (encryptionReady) {
                // Encrypted login (Phantom.ai style)
                const encryptedCredentials = await crypto.encryptCredentials(username, password);
                
                result = await api.loginEncrypted(
                    encryptedCredentials.encrypted_data,
                    encryptedCredentials.session_id
                );
            } else {
                // Fallback to standard login
                result = await api.login(username, password);
            }
            
            if (result.success) {
                // Update CSRF token
                window.TRUAI_CONFIG.CSRF_TOKEN = result.csrf_token;
                window.TRUAI_CONFIG.IS_AUTHENTICATED = true;
                window.TRUAI_CONFIG.USERNAME = result.username;
                
                // Show encryption status
                if (result.encryption === 'enabled') {
                    console.log('✅ Logged in with encrypted credentials');
                }
                
                // Redirect to dashboard
                window.location.reload();
            }
        } catch (error) {
            showError(error.message || 'Login failed. Please check your credentials.');
            loginBtn.disabled = false;
            loginBtn.textContent = 'Sign In';
        }
    });

    function showError(message) {
        errorMessage.textContent = message;
        errorMessage.classList.remove('hidden');
    }
});
