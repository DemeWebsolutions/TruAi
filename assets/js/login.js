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
    
    // Render login page (Cursor-style modern design)
    const app = document.getElementById('app');
    app.innerHTML = `
        <div class="login-container">
            <div class="login-box">
                <div class="login-header">
                    <div class="login-logo-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                        </svg>
                    </div>
                    <h1 class="login-title">Sign in</h1>
                </div>

                <form id="loginForm" class="login-form">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            placeholder="Your email address"
                            autocomplete="email"
                            required 
                            autofocus
                        >
                    </div>

                    <button type="submit" class="btn btn-primary" id="loginBtn">
                        Continue
                    </button>

                    <div class="login-divider">
                        <span>OR</span>
                    </div>

                    <div class="social-login">
                        <button type="button" class="btn-social btn-google" id="googleLogin">
                            <svg width="18" height="18" viewBox="0 0 18 18">
                                <path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.616z"/>
                                <path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.258c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332C2.438 15.983 5.482 18 9 18z"/>
                                <path fill="#FBBC05" d="M3.964 10.707c-.18-.54-.282-1.117-.282-1.707s.102-1.167.282-1.707V4.961H.957C.348 6.175 0 7.55 0 9s.348 2.825.957 4.039l3.007-2.332z"/>
                                <path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0 5.482 0 2.438 2.017.957 4.961L3.964 7.293C4.672 5.158 6.656 3.58 9 3.58z"/>
                            </svg>
                            Continue with Google
                        </button>
                        <button type="button" class="btn-social btn-github" id="githubLogin">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                            </svg>
                            Continue with GitHub
                        </button>
                        <button type="button" class="btn-social btn-apple" id="appleLogin">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M17.05 20.28c-.98.95-2.05.88-3.08.4-1.09-.5-2.08-.48-3.24 0-1.44.62-2.2.44-3.06-.4C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09l.01-.01zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/>
                            </svg>
                            Continue with Apple
                        </button>
                    </div>

                    <div class="login-footer">
                        <span>Don't have an account?</span>
                        <a href="#" id="signUpLink" class="sign-up-link">Sign up</a>
                    </div>

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
        
        const email = document.getElementById('email').value;
        
        // For now, use email as username (can be enhanced later)
        const username = email.split('@')[0] || email;
        const password = 'default'; // Will be handled by backend for email-only login

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
