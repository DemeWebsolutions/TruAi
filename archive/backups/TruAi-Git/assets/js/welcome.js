/**
 * TruAi Welcome / Quick Start Screen
 * 
 * Onboarding screen matching Cursor IDE style
 * 
 * @package TruAi
 * @version 1.0.0
 */

let welcomeCompleted = localStorage.getItem('truai_welcome_completed') === 'true';

function showWelcomeScreen() {
    if (welcomeCompleted) {
        return false;
    }
    
    const app = document.getElementById('app');
    app.innerHTML = `
        <div class="welcome-container">
            <div class="welcome-content">
                <div class="welcome-header">
                    <div class="welcome-logo">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                        </svg>
                    </div>
                    <h1 class="welcome-title">Welcome to Tru.ai</h1>
                    <p class="welcome-subtitle">The AI Code Editor</p>
                </div>

                <div class="welcome-section">
                    <h2 class="section-title">Quick Start</h2>
                    <p class="section-subtitle">Use keybindings from:</p>
                    <div class="keybinding-options">
                        <button class="keybinding-btn active" data-keybinding="vscode">VS Code</button>
                        <button class="keybinding-btn" data-keybinding="vim">Vim</button>
                        <button class="keybinding-btn" data-keybinding="emacs">Emacs</button>
                        <button class="keybinding-btn" data-keybinding="sublime">Sublime Text</button>
                    </div>
                </div>

                <div class="welcome-features">
                    <div class="feature-item">
                        <div class="feature-icon">∞</div>
                        <div class="feature-content">
                            <h3>Agent</h3>
                            <p>Plan, build anything</p>
                            <div class="feature-shortcut">Ctrl+I</div>
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">→</div>
                        <div class="feature-content">
                            <h3>Cursor Tab</h3>
                            <p>Predict your next moves</p>
                            <div class="feature-shortcut">Tab</div>
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">✓</div>
                        <div class="feature-content">
                            <h3>Inline Edit</h3>
                            <p>Edit code with AI</p>
                            <div class="feature-shortcut">Ctrl+K</div>
                        </div>
                    </div>
                </div>

                <div class="welcome-actions">
                    <button class="btn-continue" id="continueBtn">Continue</button>
                    <a href="#" class="skip-link" id="skipLink">Skip and continue</a>
                </div>

                <div class="welcome-footer">
                    <p>AI features require log in</p>
                </div>
            </div>
        </div>
    `;

    setupWelcomeListeners();
    return true;
}

function setupWelcomeListeners() {
    // Keybinding selection
    document.querySelectorAll('.keybinding-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.keybinding-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const keybinding = this.dataset.keybinding;
            localStorage.setItem('truai_keybinding', keybinding);
        });
    });

    // Continue button
    const continueBtn = document.getElementById('continueBtn');
    if (continueBtn) {
        continueBtn.addEventListener('click', function() {
            completeWelcome();
        });
    }

    // Skip link
    const skipLink = document.getElementById('skipLink');
    if (skipLink) {
        skipLink.addEventListener('click', function(e) {
            e.preventDefault();
            completeWelcome();
        });
    }
}

async function completeWelcome() {
    welcomeCompleted = true;
    localStorage.setItem('truai_welcome_completed', 'true');
    
    // Initialize dashboard
    if (typeof initializeDashboardScript === 'function') {
        await initializeDashboardScript();
    } else if (typeof renderDashboard === 'function') {
        // Load settings first if available
        if (typeof loadSettings === 'function') {
            await loadSettings();
        }
        renderDashboard();
        if (typeof setupDashboardListeners === 'function') {
            setupDashboardListeners();
        }
    } else {
        // Fallback: reload page
        window.location.reload();
    }
}

// Make functions globally accessible
window.showWelcomeScreen = showWelcomeScreen;
window.completeWelcome = completeWelcome;
