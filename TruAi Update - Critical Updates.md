# TruAi v1.0 Repository Update Instructions

## Document Version
- **Version:** 1.0.0
- **Date:** February 21, 2026
- **Status:** APPROVED FOR IMPLEMENTATION
- **Target Completion:** 4 days

---

## Executive Summary

Transform TruAi from 65% backend-complete to 100% production-ready by implementing:
1. Complete frontend UI suite (5 HTML pages)
2. Security hardening layer (session, CSRF, validation, rate limiting)
3. Operational automation (database setup, backups, monitoring)
4. Comprehensive documentation (SETUP.md, API.md, SECURITY.md, DEPLOYMENT.md)
5. CI/CD pipeline with automated testing
6. Production deployment templates

---

## Phase 1: Critical Frontend & Core Documentation

### 1.1 Update `public/TruAi/login-portal.html`

**Purpose:** Unified login interface implementing UBSAS 4-tier authentication

**Requirements:**
- Modern gradient background (similar to Gemini.ai design from `truAi update.rtf`)
- 4 authentication method cards with icons and descriptions:
  - **Tier 1:** OS Biometric (👆 Touch ID/Face ID) - blue gradient card
  - **Tier 2:** Auto-Fill (🔑 Keychain) - purple gradient card
  - **Tier 3:** Manual Entry (⌨️ Password) - gray card, always available
  - **Tier 4:** Master Key (🔐 Recovery) - red/orange card, emergency use
- Dynamic method availability detection on page load
- Status message area for success/error/info feedback
- ROMA security indicator in footer
- Responsive CSS Grid layout (mobile-friendly)

**Key JavaScript Functions:**
```javascript
// Fetch available methods from backend
async function init() {
  const response = await fetch('/TruAi/api/v1/auth/methods');
  const data = await response.json();
  updateMethodAvailability(data.methods);
}

// Biometric authentication attempt
async function attemptBiometric() {
  showStatus('info', '👆 Touch sensor to authenticate...');
  const response = await fetch('/TruAi/api/v1/auth/biometric', {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ app: 'truai' })
  });
  // Handle success → redirect to dashboard
  // Handle failure → show fallback methods
}

// Manual login
async function manualLogin(username, password) {
  const response = await fetch('/TruAi/api/v1/auth/login', {
    method: 'POST',
    credentials: 'include',
    headers: { 
      'Content-Type': 'application/json',
      'X-CSRF-Token': await getCSRFToken()
    },
    body: JSON.stringify({ username, password })
  });
}

// Master key recovery
async function masterKeyRecovery(username, masterKey) {
  const response = await fetch('/TruAi/api/v1/auth/masterkey', {
    method: 'POST',
    credentials: 'include',
    body: JSON.stringify({ username, master_key: masterKey })
  });
  // Display temporary password in modal
  // Show 10-minute countdown timer
}
```

**Design Specifications:**
- Font: `-apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif`
- Primary color: `#667eea` (blue)
- Background: `linear-gradient(135deg, #667eea 0%, #764ba2 100%)`
- Card background: `rgba(255, 255, 255, 0.95)` with `backdrop-filter: blur(10px)`
- Border radius: `12px` for cards, `8px` for inputs/buttons
- Shadow: `0 20px 60px rgba(0, 0, 0, 0.3)` for login container

---

### 1.3 Create `public/TruAi/secure-recovery.html`

**Purpose:** LSRP (Local Sovereign Recovery Protocol) interface for password recovery

**Multi-Step Wizard:**

**Step 1: Username Entry**
```html
<div class="recovery-step" id="step-username">
  <h2>🔒 Account Recovery</h2>
  <p class="warning">⚠️ This process requires local server access</p>
  <input type="text" id="recovery-username" placeholder="Enter username">
  <button onclick="nextStep('os-admin')">Continue</button>
</div>
```

**Step 2: OS Administrator Verification**
```html
<div class="recovery-step" id="step-os-admin" hidden>
  <h2>🖥️ OS Administrator Confirmation</h2>
  <p>Enter your macOS/Linux administrator credentials</p>
  <input type="text" id="os-username" placeholder="System username">
  <input type="password" id="os-password" placeholder="System password">
  <p class="info">This is NOT your TruAi password</p>
  <button onclick="submitRecovery()">Verify & Generate</button>
</div>
```

**Step 3: Factor Verification Status**
```html
<div class="factor-checklist">
  <div class="factor" id="factor-local">
    <span class="icon">✓</span> Local Server Access
  </div>
  <div class="factor" id="factor-roma">
    <span class="icon">⏳</span> ROMA Trust Validation
  </div>
  <div class="factor" id="factor-os">
    <span class="icon">⏳</span> OS Admin Verification
  </div>
  <div class="factor" id="factor-device">
    <span class="icon">⚠️</span> Device Fingerprint (Mismatch)
  </div>
</div>
```

**Step 4: Temporary Password Display**
```html
<div class="recovery-step" id="step-complete" hidden>
  <h2>✅ Recovery Successful</h2>
  <div class="temp-password-box">
    <label>Temporary Password (valid 10 minutes):</label>
    <input type="text" id="temp-password" readonly>
    <button onclick="copyPassword()">📋 Copy</button>
  </div>
  <div class="countdown">
    ⏱️ Expires in: <span id="countdown">10:00</span>
  </div>
  <p class="warning">You MUST change this password immediately after login</p>
  <button onclick="redirectToLogin()">Go to Login</button>
</div>
```

**JavaScript Implementation:**
```javascript
async function submitRecovery() {
  const data = {
    username: document.getElementById('recovery-username').value,
    os_username: document.getElementById('os-username').value,
    os_password: document.getElementById('os-password').value
  };
  
  updateFactorStatus('roma', 'checking');
  updateFactorStatus('os', 'checking');
  
  const response = await fetch('/TruAi/api/v1/auth/recovery', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  });
  
  const result = await response.json();
  
  if (result.success) {
    updateFactorStatus('roma', 'success');
    updateFactorStatus('os', 'success');
    displayTempPassword(result.temporary_password, result.expires_at);
    startCountdown(600); // 10 minutes
  } else {
    showError(result.error);
  }
}

function startCountdown(seconds) {
  const display = document.getElementById('countdown');
  let remaining = seconds;
  
  const interval = setInterval(() => {
    const minutes = Math.floor(remaining / 60);
    const secs = remaining % 60;
    display.textContent = `${minutes}:${secs.toString().padStart(2, '0')}`;
    
    if (remaining <= 0) {
      clearInterval(interval);
      showError('Temporary password expired. Start recovery again.');
    }
    remaining--;
  }, 1000);
}
```
---

### 1.6 Create `SETUP.md`

**Purpose:** Primary setup and installation documentation

**Table of Contents:**
1. Prerequisites
2. Quick Start (5 minutes)
3. Detailed Installation
4. Configuration
5. First Login
6. Biometric Setup (Optional)
7. Security Checklist
8. Troubleshooting
9. Production Deployment

**Key Sections to Include:**

**Prerequisites:**
```markdown
## Prerequisites

### System Requirements
- **Operating System:** macOS 12+ or Linux (Ubuntu 20.04+, Debian 11+)
- **PHP:** 8.2 or higher
- **PHP Extensions:** sqlite3, openssl, mbstring, json
- **Node.js:** 18+ (for Electron wrapper)
- **Disk Space:** 500MB minimum
- **RAM:** 512MB minimum

### Optional (for biometric auth)
- macOS Touch ID or Face ID enabled
- Linux: fprintd installed and configured

### Check Prerequisites
```bash
# Check PHP version
php --version  # Should be 8.2+

# Check PHP extensions
php -m | grep -E 'sqlite3|openssl|mbstring|json'

# Check Node.js
node --version  # Should be 18+
```
```

**Quick Start:**
```markdown
## Quick Start (5 minutes)

```bash
# 1. Clone repository
git clone https://github.com/DemeWebsolutions/TruAi.git
cd TruAi

# 2. Run automated setup
php scripts/setup_database.php

# 3. Start local server
./start.sh
```

**Access:** http://127.0.0.1:8001/TruAi/login-portal.html

**Default Credentials:** See `database/.initial_credentials` (⚠️ change immediately)
```

**Detailed Installation:**
```markdown
## Detailed Installation

### Step 1: Database Initialization

The setup script creates:
- SQLite database at `database/truai.db`
- Default admin user with secure random password
- Encryption keys for ROMA
- Initial schema (users, conversations, audit_logs, etc.)

```bash
php scripts/setup_database.php
```

**Output:**
```
✓ Database initialized: database/truai.db
✓ Encryption keys generated: database/keys/
✓ Admin user created: admin
✓ Credentials written: database/.initial_credentials (chmod 600)

⚠ IMPORTANT: 
  1. Login and change admin password immediately
  2. Delete .initial_credentials after first login
  3. Set file permissions: chmod 600 database/truai.db

Next steps:
  ./start.sh
```

### Step 2: Environment Configuration

Create `.env` file in project root:

```bash
cat > .env <<EOF
# Deployment mode (development | production)
TRUAI_DEPLOYMENT=development

# AI Service API Keys (optional, can also set in web UI)
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...

# Allowed hosts (production only)
ALLOWED_HOSTS=127.0.0.1,localhost
EOF
```

### Step 3: Start Server

**Option A: PHP Built-in Server (development)**
```bash
./start.sh
# Server starts on http://127.0.0.1:8001
```

**Option B: Electron Desktop App**
```bash
npm install
npm start
# Launches Electron window with TruAi
```

**Option C: Production (Plesk/Nginx)**
See `docs/DEPLOYMENT.md`

### Step 4: First Login

1. Open http://127.0.0.1:8001/TruAi/login-portal.html
2. Get credentials: `cat database/.initial_credentials`
3. Login using username/password
4. **Immediately go to Settings → Security → Change Password**
5. Delete credentials file: `rm database/.initial_credentials`

### Step 5: Verify ROMA Security

Check ROMA status indicator at bottom of page:
- ✅ **"Roma • Portal protected • Monitor active"** = Good
- ⚠️ **"Roma • Unverified"** = Check logs: `tail -f logs/truai.log`
- ❌ **"Roma • Blocked"** = Too many failures, restart: `./start.sh`

Test ROMA endpoint:
```bash
curl http://127.0.0.1:8001/TruAi/api/v1/security/roma
```

Expected response:
```json
{
  "roma": true,
  "portal_protected": true,
  "monitor": "active",
  "encryption": "RSA-2048 + AES-256-GCM",
  "trust_state": "VERIFIED"
}
```
```

**Biometric Setup (Optional):**
```markdown
## Biometric Authentication Setup

⚠️ **macOS only** (Touch ID / Face ID). Linux support coming soon.

### Prerequisites
- Touch ID or Face ID enabled in System Preferences
- macOS 12+ (Monterey or later)

### Installation

```bash
./scripts/setup_biometric_auth.sh
```

The script will:
1. Detect Touch ID/Face ID availability
2. Configure macOS Keychain entries for TruAi
3. Install native messaging host for browser extension
4. Create LaunchAgent for auto-login daemon
5. Test biometric detection

### Manual Configuration

If automated setup fails:

```bash
# 1. Add to Keychain
security add-generic-password \
  -s com.demewebsolutions.auth.truai \
  -a admin \
  -w "YourPassword" \
  -T "" \
  -U

# 2. Create config directory
mkdir -p ~/.demewebsolutions
cat > ~/.demewebsolutions/config.json <<EOF
{
  "apps": {
    "truai": {
      "username": "admin",
      "enabled": true
    }
  }
}
EOF

# 3. Install native host
mkdir -p ~/Library/Application\ Support/Google/Chrome/NativeMessagingHosts/
cp native_host/com.demewebsolutions.biometric.json ~/Library/Application\ Support/Google/Chrome/NativeMessagingHosts/
```

### Testing

```bash
./scripts/test_biometric.sh
```

Expected output:
```
✓ Touch ID hardware detected
✓ Keychain entry exists
✓ Native host installed
✓ Config valid
✓ Biometric unlock detection working
```

### Browser Extension

1. Open Chrome → Extensions → Developer mode
2. Click "Load unpacked"
3. Select `TruAi/browser_extension/` directory
4. Lock your Mac (Cmd+Ctrl+Q)
5. Unlock with Touch ID
6. Visit http://127.0.0.1:8001/TruAi/login-portal.html
7. Should auto-authenticate

### Troubleshooting

**Issue:** "Biometric not available"
```bash
# Check hardware
bioutil -r
# Should show Touch ID or Face ID status
```

**Issue:** "Keychain entry not found"
```bash
# Verify entry exists
security find-generic-password -s com.demewebsolutions.auth.truai
```

**Issue:** "Native host disconnected"
```bash
# Check manifest exists
cat ~/Library/Application\ Support/Google/Chrome/NativeMessagingHosts/com.demewebsolutions.biometric.json

# Verify path is correct
which php  # Should match path in manifest
```
```

**Security Checklist:**
```markdown
## Security Checklist

Before using TruAi in any environment, verify:

### Initial Setup
- [ ] Changed default admin password
- [ ] Deleted `database/.initial_credentials`
- [ ] Set restrictive permissions: `chmod 600 database/truai.db`
- [ ] Set restrictive permissions: `chmod 700 database/keys/`
- [ ] Reviewed generated password for admin (strong: 16+ chars, mixed case, numbers, symbols)

### Configuration
- [ ] `.env` file created (not in repository)
- [ ] API keys set (OpenAI, Anthropic) if using AI features
- [ ] `TRUAI_DEPLOYMENT` set to correct environment
- [ ] Reviewed `backend/config.php` CORS settings

### Network
- [ ] Firewall configured (port 8001 localhost-only for development)
- [ ] HTTPS enforced (if production - see DEPLOYMENT.md)
- [ ] Allowed hosts configured (if production)

### Monitoring
- [ ] Checked startup logs: `logs/truai.log`
- [ ] Verified ROMA status: http://127.0.0.1:8001/TruAi/api/v1/security/roma
- [ ] Tested login flow (manual password entry)
- [ ] Confirmed session timeout works (default 1 hour)

### Backup
- [ ] Database backup script configured: `scripts/backup_database.sh`
- [ ] Backup directory exists: `~/.truai_backups/`
- [ ] Cron job or systemd timer scheduled (see DEPLOYMENT.md)

### Optional (Enhanced Security)
- [ ] Biometric authentication configured
- [ ] Master recovery key generated and stored offline
- [ ] Trusted devices registered
- [ ] Audit log review scheduled (weekly)
```

**Troubleshooting:**
```markdown
## Troubleshooting

### Issue: "Login failed" even with correct password

**Causes:**
1. `.initial_credentials` file missing
2. Database not initialized
3. Session errors

**Solutions:**
```bash
# 1. Check credentials file exists
ls -la database/.initial_credentials
cat database/.initial_credentials

# 2. Verify database exists and has users table
sqlite3 database/truai.db "SELECT username FROM users;"

# 3. Reset admin password
php scripts/reset_admin_password.php admin
cat database/.initial_credentials
```

### Issue: "ROMA not verified"

**Causes:**
1. Encryption keys missing
2. Database not writable
3. Session not started

**Solutions:**
```bash
# 1. Check encryption keys
ls -la database/keys/
# Should show: private_key.pem, public_key.pem

# 2. Check database permissions
ls -la database/truai.db
# Should be: -rw------- (600)

# 3. Check writable
touch database/test_write && rm database/test_write
# Should succeed without errors

# 4. Check session directory
php -i | grep session.save_path
# Verify directory exists and is writable

# 5. Restart server
./start.sh
```

### Issue: "Port 8001 already in use"

**Solution:**
```bash
# 1. Find process using port
lsof -i :8001
# or
netstat -an | grep 8001

# 2. Kill process
kill <PID>

# 3. Change port (edit start.sh)
# Change: php -S 127.0.0.1:8001
# To:     php -S 127.0.0.1:8002

# 4. Update CORS in backend/config.php
# Add port 8002 to allowed origins
```

### Issue: "Biometric authentication not working"

See **Biometric Setup → Troubleshooting** section above.

### Issue: "AI chat not responding"

**Causes:**
1. API keys not configured
2. Network issues
3. Rate limits exceeded

**Solutions:**
```bash
# 1. Check API keys configured
curl http://127.0.0.1:8001/TruAi/api/v1/settings/get \
  -H "Cookie: TRUAI_SESSION=<your-session-id>"
# Should show ai.openaiApiKey or ai.anthropicApiKey

# 2. Test API keys
curl http://127.0.0.1:8001/TruAi/api/v1/settings/test-keys \
  -X POST \
  -H "Cookie: TRUAI_SESSION=<your-session-id>"

# 3. Check logs
tail -f logs/truai.log
# Look for API errors
```

### Issue: "Database locked"

**Cause:** Multiple PHP processes accessing SQLite simultaneously

**Solution:**
```bash
# 1. Stop all PHP processes
pkill -f "php -S"

# 2. Remove lock file if exists
rm -f database/truai.db-wal
rm -f database/truai.db-shm

# 3. Restart single server instance
./start.sh
```

### Getting Help

1. **Check logs:** `logs/truai.log`
2. **Enable debug mode:** Edit `backend/config.php`, set `ini_set('display_errors', 1);`
3. **Test health:** `curl http://127.0.0.1:8001/TruAi/api/v1/health`
4. **GitHub Issues:** https://github.com/DemeWebsolutions/TruAi/issues
5. **Security issues:** security@demewebsolutions.com (private disclosure)
```

**Production Deployment:**
```markdown
## Production Deployment

For production setup with Plesk, Nginx, or Apache, see:

📖 **[DEPLOYMENT.md](docs/DEPLOYMENT.md)**

Key differences from development:
- HTTPS enforced (no HTTP access)
- Environment variables in PHP-FPM pool config
- Database permissions: `chmod 600`
- Log rotation configured
- Automated backups (daily)
- Health monitoring
- Firewall rules (restrict to specific IPs)
- Rate limiting on auth endpoints
```

---

## Phase 2: Security Hardening

### 2.1 Update `backend/router.php` - Session Security

**Location:** Line ~150, in `startSession()` method

**Current Code:**
```php
private function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}
```

**Replace With:**
```php
private function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Security: Prevent session fixation and hijacking
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', TRUAI_DEPLOYMENT === 'production' ? '1' : '0');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
        session_name('TRUAI_SESSION');
        session_start();
        
        // Check session timeout
        if (isset($_SESSION['login_time'])) {
            $elapsed = time() - $_SESSION['login_time'];
            if ($elapsed > SESSION_LIFETIME) {
                session_unset();
                session_destroy();
                session_start();
            }
        }
        
        // Check session last activity (additional timeout)
        if (isset($_SESSION['last_activity'])) {
            $idle = time() - $_SESSION['last_activity'];
            if ($idle > 1800) { // 30 minutes idle timeout
                session_unset();
                session_destroy();
                session_start();
            }
        }
        $_SESSION['last_activity'] = time();
    }
}
```

**Purpose:**
- Prevent session fixation attacks
- Enforce HTTPS-only cookies in production
- Enable strict SameSite policy (CSRF protection)
- Implement idle timeout (30 minutes)
- Implement absolute timeout (SESSION_LIFETIME)

---

### 2.2 Update `backend/auth.php` - Session Regeneration

**Location:** Line ~95, in `setUserSession()` method

**Add After Session Variable Assignments:**
```php
// Regenerate session ID to prevent fixation attacks
session_regenerate_id(true);

// Set session timeout tracking
$_SESSION['login_time'] = time();
$_SESSION['last_activity'] = time();
$_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
$_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';

// Log successful login
error_log(sprintf(
    '[AUTH] User %d (%s) logged in from %s',
    $user['id'],
    $user['username'],
    $_SESSION['ip_address']
));
```

**Purpose:**
- Regenerate session ID on login (prevents fixation)
- Track session metadata (user agent, IP)
- Enable session timeout enforcement
- Log authentication events

---

### 2.3 Create `backend/csrf.php`

**Purpose:** CSRF token generation and validation

**Full File Content:**
```php
<?php
/**
 * CSRF Protection Service
 * 
 * Generates and validates CSRF tokens for state-changing requests
 * 
 * @package TruAi
 * @version 1.0.0
 * @copyright My Deme, LLC © 2026
 */

class CSRFProtection {
    private const TOKEN_LENGTH = 32;
    
    /**
     * Generate CSRF token (or return existing)
     */
    public static function generateToken(): string {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    /**
     * Validate CSRF token from request
     */
    public static function validateToken(string $token): bool {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            return false;
        }
        return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
    
    /**
     * Regenerate CSRF token (call after sensitive operations)
     */
    public static function regenerateToken(): string {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    /**
     * Get token from request headers or POST data
     */
    public static function getTokenFromRequest(): ?string {
        // Check X-CSRF-Token header (preferred)
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        
        // Check POST data (form fallback)
        if (isset($_POST['csrf_token'])) {
            return $_POST['csrf_token'];
        }
        
        // Check JSON body
        $json = json_decode(file_get_contents('php://input'), true);
        if (isset($json['csrf_token'])) {
            return $json['csrf_token'];
        }
        
        return null;
    }
}
```

---

### 2.4 Update `backend/router.php` - CSRF Enforcement

**Location:** In `requireAuth()` method, after authentication check

**Add CSRF Validation:**
```php
private function requireAuth() {
    // Existing auth check
    if (!$this->auth->isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }
    
    // CSRF check for state-changing requests
    if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE', 'PATCH'])) {
        require_once __DIR__ . '/csrf.php';
        
        $token = CSRFProtection::getTokenFromRequest();
        
        if (!$token || !CSRFProtection::validateToken($token)) {
            error_log('[CSRF] Token validation failed for ' . $_SERVER['REQUEST_URI']);
            http_response_code(403);
            echo json_encode(['error' => 'CSRF validation failed']);
            exit;
        }
    }
}
```

**Add New Route for Token Generation:**
```php
// In constructor, add route registration
$this->routes['GET']['/api/v1/auth/csrf-token'] = [$this, 'handleGetCSRFToken'];

// Add handler method
private function handleGetCSRFToken() {
    require_once __DIR__ . '/csrf.php';
    $this->sendJson([
        'csrf_token' => CSRFProtection::generateToken(),
        'expires_in' => SESSION_LIFETIME
    ]);
}
```

**Add Token Refresh Endpoint:**
```php
// In constructor
$this->routes['POST']['/api/v1/auth/csrf-refresh'] = [$this, 'handleRefreshCSRFToken'];

// Handler
private function handleRefreshCSRFToken() {
    $this->requireAuth();
    require_once __DIR__ . '/csrf.php';
    $this->sendJson([
        'csrf_token' => CSRFProtection::regenerateToken()
    ]);
}
```

---

### 2.5 Create `backend/validator.php`

**Purpose:** Input validation and sanitization layer

**Full File Content:**
```php
<?php
/**
 * Input Validation Service
 * 
 * Validates and sanitizes user input to prevent injection attacks
 * 
 * @package TruAi
 * @version 1.0.0
 * @copyright My Deme, LLC © 2026
 */

class Validator {
    /**
     * Validate username
     * 
     * Rules:
     * - 3-32 characters
     * - Alphanumeric, hyphens, underscores only
     * - No spaces
     */
    public static function username(string $username): array {
        $username = trim($username);
        $errors = [];
        
        if (strlen($username) < 3) {
            $errors[] = 'Username must be at least 3 characters';
        }
        
        if (strlen($username) > 32) {
            $errors[] = 'Username must be less than 32 characters';
        }
        
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            $errors[] = 'Username can only contain letters, numbers, hyphens, and underscores';
        }
        
        return [
            'valid' => empty($errors),
            'value' => $username,
            'errors' => $errors
        ];
    }
    
    /**
     * Validate password strength
     * 
     * Rules:
     * - 8+ characters
     * - At least 1 uppercase letter
     * - At least 1 lowercase letter
     * - At least 1 number
     * - At least 1 special character
     */
    public static function password(string $password): array {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate email address
     */
    public static function email(string $email): array {
        $email = trim(strtolower($email));
        $valid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        
        return [
            'valid' => $valid,
            'value' => $email,
            'errors' => $valid ? [] : ['Invalid email address format']
        ];
    }
    
    /**
     * Sanitize file path (prevent directory traversal)
     */
    public static function sanitizeFilePath(string $path): string {
        // Remove directory traversal attempts
        $path = str_replace(['..', '~'], '', $path);
        
        // Allow only alphanumeric, forward slash, hyphen, underscore, dot
        $path = preg_replace('/[^a-zA-Z0-9\/_.-]/', '', $path);
        
        // Remove multiple slashes
        $path = preg_replace('#/+#', '/', $path);
        
        // Remove leading/trailing slashes
        return trim($path, '/');
    }
    
    /**
     * Sanitize HTML output (prevent XSS)
     */
    public static function sanitizeHTML(string $html): string {
        return htmlspecialchars($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Sanitize for SQL LIKE queries (prevent LIKE injection)
     */
    public static function sanitizeLike(string $input): string {
        return str_replace(['%', '_'], ['\\%', '\\_'], $input);
    }
    
    /**
     * Validate conversation ID (numeric only)
     */
    public static function conversationId($id): array {
        $valid = is_numeric($id) && $id > 0;
        
        return [
            'valid' => $valid,
            'value' => $valid ? (int)$id : null,
            'errors' => $valid ? [] : ['Invalid conversation ID']
        ];
    }
    
    /**
     * Validate JSON string
     */
    public static function json(string $json): array {
        json_decode($json);
        $valid = json_last_error() === JSON_ERROR_NONE;
        
        return [
            'valid' => $valid,
            'value' => $valid ? json_decode($json, true) : null,
            'errors' => $valid ? [] : ['Invalid JSON: ' . json_last_error_msg()]
        ];
    }
}
```

---

### 2.6 Update `backend/auth.php` - Add Input Validation

**Location:** In `login()` method, before database query

**Add Validation:**
```php
public function login($username, $password, $isEncrypted = false, $encryptedData = null, $sessionId = null) {
    require_once __DIR__ . '/validator.php';
    
    // Validate username format
    $usernameValidation = Validator::username($username);
    if (!$usernameValidation['valid']) {
        error_log('[AUTH] Invalid username format: ' . implode(', ', $usernameValidation['errors']));
        return false;
    }
    $username = $usernameValidation['value'];
    
    // Handle encrypted login (Phantom.ai style)
    if ($isEncrypted && $encryptedData && $sessionId) {
        // ... existing encrypted login logic
    }
    
    // Validate password length (basic check)
    if (!$isEncrypted && strlen($password) < 8) {
        error_log('[AUTH] Password too short for username: ' . $username);
        return false;
    }
    
    // Continue with existing logic...
}
```

---

### 2.7 Update `backend/router.php` - Rate Limiting

**Add Rate Limiter Method:**
```php
/**
 * Rate limiting implementation
 * 
 * @param string $key Unique identifier (e.g., 'login_username' or 'api_ip')
 * @param int $maxAttempts Maximum attempts allowed
 * @param int $windowSeconds Time window in seconds
 * @return bool True if request allowed, false if rate limited
 */
private function checkRateLimit(string $key, int $maxAttempts = 5, int $windowSeconds = 300): bool {
    $cacheKey = 'ratelimit_' . hash('sha256', $key);
    
    // Get existing attempts from session (or use cache/database in production)
    $attempts = $_SESSION[$cacheKey] ?? ['count' => 0, 'window_start' => time()];
    
    // Reset if window expired
    if (time() - $attempts['window_start'] > $windowSeconds) {
        $attempts = ['count' => 0, 'window_start' => time()];
    }
    
    // Increment
    $attempts['count']++;
    $_SESSION[$cacheKey] = $attempts;
    
    // Check threshold
    if ($attempts['count'] > $maxAttempts) {
        error_log(sprintf(
            '[RATE_LIMIT] Threshold exceeded for key: %s (%d attempts in %d seconds)',
            $key,
            $attempts['count'],
            time() - $attempts['window_start']
        ));
        return false;
    }
    
    return true;
}

/**
 * Reset rate limit (call on successful operation)
 */
private function resetRateLimit(string $key): void {
    $cacheKey = 'ratelimit_' . hash('sha256', $key);
    unset($_SESSION[$cacheKey]);
}
```

**Apply to Login Endpoint:**
```php
private function handleLogin() {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    
    // Rate limit by username (5 attempts per 5 minutes)
    if (!$this->checkRateLimit('login_' . $username, 5, 300)) {
        http_response_code(429);
        $this->sendJson([
            'error' => 'Too many login attempts. Please wait 5 minutes and try again.',
            'retry_after' => 300
        ]);
        return;
    }
    
    // Rate limit by IP (10 attempts per 5 minutes)
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!$this->checkRateLimit('login_ip_' . $clientIP, 10, 300)) {
        http_response_code(429);
        $this->sendJson([
            'error' => 'Too many requests from your IP address. Please wait 5 minutes.',
            'retry_after' => 300
        ]);
        return;
    }
    
    // Attempt login
    $isEncrypted = $data['encrypted'] ?? false;
    $encryptedData = $data['encrypted_data'] ?? null;
    $sessionId = $data['session_id'] ?? null;
    
    $result = $this->auth->login($username, $password, $isEncrypted, $encryptedData, $sessionId);
    
    if ($result) {
        // Reset rate limits on successful login
        $this->resetRateLimit('login_' . $username);
        $this->resetRateLimit('login_ip_' . $clientIP);
        
        $this->sendJson([
            'success' => true,
            'username' => $this->auth->getUsername(),
            'csrf_token' => CSRFProtection::generateToken()
        ]);
    } else {
        // Login failed (rate limit counter already incremented)
        $this->sendJson(['success' => false, 'error' => 'Invalid username or password']);
    }
}
```

**Apply to Recovery Endpoint:**
```php
private function handleRecovery() {
    $this->requireAuth(); // Optional: may want recovery to work without auth
    
    $data = json_decode(file_get_contents('php://input'), true);
    $username = $data['username'] ?? '';
    
    // Rate limit by username (3 attempts per 24 hours)
    if (!$this->checkRateLimit('recovery_' . $username, 3, 86400)) {
        http_response_code(429);
        $this->sendJson([
            'error' => 'Too many recovery attempts. Wait 24 hours or contact administrator.',
            'retry_after' => 86400
        ]);
        return;
    }
    
    // Delegate to LSRP controller
    require_once __DIR__ . '/lsrp_recovery.php';
    $controller = new LSRPRecoveryController();
    $result = $controller->handleRecovery($data);
    
    if ($result['success']) {
        $this->resetRateLimit('recovery_' . $username);
    }
    
    $this->sendJson($result);
}
```

---

## Phase 3: Database Management & Automation

### 3.1 Create `scripts/setup_database.php`

**Purpose:** Automated, idempotent database initialization

**Full File Content:**
```php
#!/usr/bin/env php
<?php
/**
 * TruAi Database Setup Script
 * 
 * Initializes database with schema, default admin user, and encryption keys
 * Safe to run multiple times (idempotent)
 * 
 * Usage: php scripts/setup_database.php
 * 
 * @package TruAi
 * @version 1.0.0
 * @copyright My Deme, LLC © 2026
 */

// Load configuration
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/database.php';

class DatabaseSetup {
    private $db;
    private $verbose = true;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    private function log(string $message, string $type = 'info'): void {
        if (!$this->verbose) return;
        
        $icons = [
            'info' => 'ℹ️',
            'success' => '✓',
            'warning' => '⚠️',
            'error' => '✗'
        ];
        
        $icon = $icons[$type] ?? 'ℹ️';
        echo "$icon $message\n";
    }
    
    /**
     * Run all setup steps
     */
    public function run(): bool {
        $this->log("Starting TruAi database setup...", 'info');
        
        try {
            $this->createDirectories();
            $this->runMigrations();
            $this->generateEncryptionKeys();
            $this->createDefaultAdmin();
            $this->verifySetup();
            
            $this->log("\n=== Setup Complete ===", 'success');
            $this->printNextSteps();
            
            return true;
        } catch (Exception $e) {
            $this->log("Setup failed: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Create necessary directories
     */
    private function createDirectories(): void {
        $dirs = [
            DATABASE_PATH,
            DATABASE_PATH . '/keys',
            LOGS_PATH,
            BASE_PATH . '/.truai_backups'
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0700, true);
                $this->log("Created directory: $dir", 'success');
            }
        }
    }
    
    /**
     * Run database migrations
     */
    private function runMigrations(): void {
        $this->log("Running database migrations...", 'info');
        
        $migrationFile = __DIR__ . '/../database/migrations/001_initial_schema.sql';
        
        if (file_exists($migrationFile)) {
            $sql = file_get_contents($migrationFile);
            
            // Split by semicolon and execute each statement
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                fn($s) => !empty($s) && !str_starts_with($s, '--')
            );
            
            foreach ($statements as $statement) {
                try {
                    $this->db->getConnection()->exec($statement);
                } catch (PDOException $e) {
                    // Ignore "table already exists" errors
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        throw $e;
                    }
                }
            }
            
            $this->log("Database schema initialized", 'success');
        } else {
            // Fallback: create basic schema inline
            $this->createBasicSchema();
        }
    }
    
    /**
     * Create basic schema if migration file doesn't exist
     */
    private function createBasicSchema(): void {
        $schema = "
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT DEFAULT 'user',
            account_suspended INTEGER DEFAULT 0,
            requires_password_change INTEGER DEFAULT 0,
            temp_password_expires DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME
        );
        
        CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            event TEXT NOT NULL,
            actor TEXT,
            details TEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS recovery_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            ip_address TEXT,
            device_fingerprint TEXT,
            result TEXT NOT NULL,
            details TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS master_recovery_keys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER UNIQUE NOT NULL,
            key_hash TEXT NOT NULL,
            issued_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_used DATETIME,
            use_count INTEGER DEFAULT 0
        );
        
        CREATE TABLE IF NOT EXISTS biometric_logins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            ip_address TEXT,
            user_agent TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS conversations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            conversation_id INTEGER NOT NULL,
            role TEXT NOT NULL,
            content TEXT NOT NULL,
            model TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            key TEXT NOT NULL,
            value TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        ";
        
        $this->db->getConnection()->exec($schema);
        $this->log("Basic schema created", 'success');
    }
    
    /**
     * Generate RSA encryption keys for ROMA
     */
    private function generateEncryptionKeys(): void {
        $keyDir = DATABASE_PATH . '/keys';
        $privateKeyPath = $keyDir . '/private_key.pem';
        $publicKeyPath = $keyDir . '/public_key.pem';
        
        if (file_exists($privateKeyPath) && file_exists($publicKeyPath)) {
            $this->log("Encryption keys already exist", 'info');
            return;
        }
        
        $this->log("Generating RSA-2048 encryption keys...", 'info');
        
        // Generate private key
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        
        $privateKey = openssl_pkey_new($config);
        if (!$privateKey) {
            throw new Exception('Failed to generate private key');
        }
        
        // Export private key
        openssl_pkey_export($privateKey, $privateKeyPem);
        file_put_contents($privateKeyPath, $privateKeyPem);
        chmod($privateKeyPath, 0600);
        
        // Export public key
        $publicKeyDetails = openssl_pkey_get_details($privateKey);
        file_put_contents($publicKeyPath, $publicKeyDetails['key']);
        chmod($publicKeyPath, 0644);
        
        $this->log("Encryption keys generated: $keyDir", 'success');
    }
    
    /**
     * Create default admin user if doesn't exist
     */
    private function createDefaultAdmin(): void {
        // Check if admin already exists
        $result = $this->db->query(
            "SELECT id FROM users WHERE username = :username LIMIT 1",
            [':username' => 'admin']
        );
        
        if (!empty($result)) {
            $this->log("Admin user already exists", 'info');
            return;
        }
        
        $this->log("Creating default admin user...", 'info');
        
        // Generate secure random password
        $password = $this->generateSecurePassword();
        
        // Hash with Argon2id
        $passwordHash = password_hash($password, PASSWORD_ARGON2ID, ARGON2ID_OPTIONS);
        
        // Insert admin user
        $this->db->execute(
            "INSERT INTO users (username, password_hash, role, created_at) VALUES (:username, :hash, 'admin', datetime('now'))",
            [
                ':username' => 'admin',
                ':hash' => $passwordHash
            ]
        );
        
        // Write credentials to file
        $credentialsFile = DATABASE_PATH . '/.initial_credentials';
        $credentials = json_encode([
            'username' => 'admin',
            'password' => $password,
            'created_at' => date('c')
        ], JSON_PRETTY_PRINT);
        
        file_put_contents($credentialsFile, $credentials);
        chmod($credentialsFile, 0600);
        
        $this->log("Admin user created: admin", 'success');
        $this->log("Credentials written: $credentialsFile (chmod 600)", 'success');
    }
    
    /**
     * Generate secure random password
     */
    private function generateSecurePassword(): string {
        $length = 16;
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $password;
    }
    
    /**
     * Verify setup completed successfully
     */
    private function verifySetup(): void {
        $this->log("\nVerifying setup...", 'info');
        
        // Check database exists and is writable
        $dbPath = DB_PATH;
        if (!file_exists($dbPath)) {
            throw new Exception("Database file not created: $dbPath");
        }
        if (!is_writable($dbPath)) {
            throw new Exception("Database file not writable: $dbPath");
        }
        $this->log("✓ Database file exists and is writable", 'success');
        
        // Check encryption keys exist
        $keyDir = DATABASE_PATH . '/keys';
        if (!file_exists($keyDir . '/private_key.pem') || !file_exists($keyDir . '/public_key.pem')) {
            throw new Exception("Encryption keys not found in $keyDir");
        }
        $this->log("✓ Encryption keys exist", 'success');
        
        // Check admin user exists
        $result = $this->db->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
        if (empty($result)) {
            throw new Exception("Admin user not created");
        }
        $this->log("✓ Admin user exists", 'success');
        
        // Check credentials file exists
        $credentialsFile = DATABASE_PATH . '/.initial_credentials';
        if (!file_exists($credentialsFile)) {
            throw new Exception("Credentials file not created: $credentialsFile");
        }
        $this->log("✓ Credentials file exists", 'success');
    }
    
    /**
     * Print next steps for user
     */
    private function printNextSteps(): void {
        $credentialsFile = DATABASE_PATH . '/.initial_credentials';
        
        echo "\n";
        echo "┌─────────────────────────────────────────────────────────┐\n";
        echo "│              Next Steps                                 │\n";
        echo "└─────────────────────────────────────────────────────────┘\n";
        echo "\n";
        echo "1. Start the server:\n";
        echo "   ./start.sh\n";
        echo "\n";
        echo "2. Get your credentials:\n";
        echo "   cat $credentialsFile\n";
        echo "\n";
        echo "3. Login:\n";
        echo "   http://127.0.0.1:8001/TruAi/login-portal.html\n";
        echo "\n";
        echo "4. IMPORTANT: Change admin password immediately\n";
        echo "   Go to: Settings → Security → Change Password\n";
        echo "\n";
        echo "5. Delete credentials file after first login:\n";
        echo "   rm $credentialsFile\n";
        echo "\n";
        echo "⚠️  WARNING:\n";
        echo "   - Keep database/.initial_credentials secure (chmod 600)\n";
        echo "   - Change the admin password on first login\n";
        echo "   - Delete .initial_credentials after setup\n";
        echo "\n";
    }
}

// Run setup
$setup = new DatabaseSetup();
exit($setup->run() ? 0 : 1);
```

---

### 3.2 Create `scripts/reset_admin_password.php`

**Purpose:** Emergency password reset for locked-out admin

**Full File Content:**
```php
#!/usr/bin/env php
<?php
/**
 * Reset Admin Password Script
 * 
 * Emergency tool to reset a user's password
 * 
 * Usage: php scripts/reset_admin_password.php <username>
 * 
 * @package TruAi
 * @version 1.0.0
 */

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/database.php';

if ($argc < 2) {
    echo "Usage: php scripts/reset_admin_password.php <username>\n";
    echo "Example: php scripts/reset_admin_password.php admin\n";
    exit(1);
}

$username = $argv[1];

$db = Database::getInstance();

// Check user exists
$result = $db->query(
    "SELECT id FROM users WHERE username = :username LIMIT 1",
    [':username' => $username]
);

if (empty($result)) {
    echo "✗ User not found: $username\n";
    exit(1);
}

$userId = $result[0]['id'];

// Generate new password
$newPassword = bin2hex(random_bytes(8)); // 16 chars

// Hash with Argon2id
$passwordHash = password_hash($newPassword, PASSWORD_ARGON2ID, ARGON2ID_OPTIONS);

// Update database
$db->execute(
    "UPDATE users SET password_hash = :hash, requires_password_change = 1 WHERE id = :id",
    [
        ':hash' => $passwordHash,
        ':id' => $userId
    ]
);

// Write to credentials file
$credentialsFile = DATABASE_PATH . '/.initial_credentials';
$credentials = json_encode([
    'username' => $username,
    'password' => $newPassword,
    'reset_at' => date('c'),
    'note' => 'Password reset by admin. Change immediately after login.'
], JSON_PRETTY_PRINT);

file_put_contents($credentialsFile, $credentials);
chmod($credentialsFile, 0600);

echo "✓ Password reset for user: $username\n";
echo "✓ New credentials written: $credentialsFile\n";
echo "\n";
echo "Temporary password: $newPassword\n";
echo "\n";
echo "⚠️  IMPORTANT:\n";
echo "  1. Login immediately: http://127.0.0.1:8001/TruAi/login-portal.html\n";
echo "  2. Change this password in Settings → Security\n";
echo "  3. Delete $credentialsFile after login\n";
echo "\n";

exit(0);
```

---

### 3.3 Create `database/migrations/001_initial_schema.sql`

**Purpose:** Complete database schema (all tables)

**Structure:**
```sql
-- TruAi Complete Database Schema
-- Version: 1.0.0
-- Date: 2026-02-21

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    email TEXT,
    role TEXT DEFAULT 'user' CHECK (role IN ('admin', 'user')),
    account_suspended INTEGER DEFAULT 0,
    requires_password_change INTEGER DEFAULT 0,
    temp_password_expires DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME
);

CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

-- Audit logs
CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    event TEXT NOT NULL,
    actor TEXT,
    details TEXT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_audit_user_time ON audit_logs(user_id, timestamp);
CREATE INDEX IF NOT EXISTS idx_audit_event ON audit_logs(event);

-- LSRP Recovery attempts
CREATE TABLE IF NOT EXISTS recovery_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    ip_address TEXT NOT NULL,
    device_fingerprint TEXT,
    result TEXT NOT NULL CHECK (result IN ('SUCCESS', 'DENIED', 'WARNING')),
    details TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_recovery_user_time ON recovery_attempts(user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_recovery_result ON recovery_attempts(result, created_at);

-- LSRP Trusted devices
CREATE TABLE IF NOT EXISTS trusted_devices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    device_fingerprint TEXT NOT NULL,
    device_name TEXT,
    first_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
    revoked INTEGER DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_trusted_user ON trusted_devices(user_id, revoked);

-- LSRP Master recovery keys (hashed)
CREATE TABLE IF NOT EXISTS master_recovery_keys (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER UNIQUE NOT NULL,
    key_hash TEXT NOT NULL,
    issued_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_used DATETIME,
    use_count INTEGER DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- UBSAS Biometric logins
CREATE TABLE IF NOT EXISTS biometric_logins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    ip_address TEXT,
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_biometric_user ON biometric_logins(user_id, created_at);

-- UBSAS Devices (for device fingerprinting)
CREATE TABLE IF NOT EXISTS ubsas_devices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    device_type TEXT CHECK (device_type IN ('biometric', 'manual', 'keychain')),
    device_fingerprint TEXT,
    device_info TEXT, -- JSON: OS, browser, etc.
    trusted INTEGER DEFAULT 0,
    last_used DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- UBSAS Authentication challenges (for rate limiting)
CREATE TABLE IF NOT EXISTS ubsas_challenges (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    challenge_type TEXT,
    attempts INTEGER DEFAULT 0,
    last_attempt DATETIME,
    window_start DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Chat conversations
CREATE TABLE IF NOT EXISTS conversations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_conversations_user ON conversations(user_id, updated_at DESC);

-- Chat messages
CREATE TABLE IF NOT EXISTS messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    conversation_id INTEGER NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('user', 'assistant', 'system')),
    content TEXT NOT NULL,
    model TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_messages_conversation ON messages(conversation_id, created_at);

-- Learning events (AI feedback)
CREATE TABLE IF NOT EXISTS learning_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    event_type TEXT CHECK (event_type IN ('correction', 'preference', 'success', 'failure', 'feedback')),
    context TEXT, -- JSON
    original_prompt TEXT,
    original_response TEXT,
    corrected_response TEXT,
    feedback_score INTEGER,
    model_used TEXT,
    risk_level TEXT,
    tier TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_learning_user ON learning_events(user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_learning_type ON learning_events(event_type);

-- Learning patterns (extracted from events)
CREATE TABLE IF NOT EXISTS learning_patterns (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    pattern_type TEXT,
    pattern TEXT NOT NULL,
    confidence REAL DEFAULT 0.5,
    usage_count INTEGER DEFAULT 0,
    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_patterns_user ON learning_patterns(user_id, confidence DESC);

-- Settings (key-value store)
CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER, -- NULL = global setting
    key TEXT NOT NULL,
    value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE(user_id, key)
);

CREATE INDEX IF NOT EXISTS idx_settings_user_key ON settings(user_id, key);

-- Gemini.ai automation logs
CREATE TABLE IF NOT EXISTS gemini_automation_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT NOT NULL,
    success INTEGER DEFAULT 0,
    result TEXT, -- JSON
    execution_time_ms INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_gemini_user ON gemini_automation_logs(user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_gemini_action ON gemini_automation_logs(action);

-- ROMA ITC Systems (inter-system trust)
CREATE TABLE IF NOT EXISTS itc_systems (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    system_id TEXT UNIQUE NOT NULL,
    public_key TEXT NOT NULL,
    trust_status TEXT DEFAULT 'active' CHECK (trust_status IN ('active', 'revoked')),
    revoked_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ROMA ITC Sessions (temporary session keys)
CREATE TABLE IF NOT EXISTS itc_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id TEXT UNIQUE NOT NULL,
    system_id TEXT NOT NULL,
    session_key TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (system_id) REFERENCES itc_systems(system_id)
);

CREATE INDEX IF NOT EXISTS idx_itc_sessions_system ON itc_sessions(system_id);
CREATE INDEX IF NOT EXISTS idx_itc_sessions_expires ON itc_sessions(expires_at);

-- Security events (separate from audit for high-priority)
CREATE TABLE IF NOT EXISTS security_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_type TEXT NOT NULL,
    severity TEXT CHECK (severity IN ('low', 'medium', 'high', 'critical')),
    source TEXT,
    details TEXT, -- JSON
    resolved INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_security_severity ON security_events(severity, resolved, created_at);

-- File operations (for future file management)
CREATE TABLE IF NOT EXISTS file_operations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    operation TEXT CHECK (operation IN ('upload', 'download', 'delete', 'modify')),
    file_path TEXT NOT NULL,
    file_size INTEGER,
    mime_type TEXT,
    success INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_file_ops_user ON file_operations(user_id, created_at);
```

---

### 3.4 Create `scripts/backup_database.sh`

**Purpose:** Automated database backup with compression

**Full Script:**
```bash
#!/bin/bash
#
# TruAi Database Backup Script
#
# Backs up SQLite database with compression and retention policy
#
# Usage: ./scripts/backup_database.sh
# Or schedule via cron: 0 2 * * * /path/to/TruAi/scripts/backup_database.sh

set -e

# Configuration
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="$HOME/.truai_backups"
DB_PATH="$PROJECT_ROOT/database/truai.db"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/truai_${TIMESTAMP}.db"
RETENTION_DAYS=30

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Check database exists
if [ ! -f "$DB_PATH" ]; then
    echo "✗ Database not found: $DB_PATH"
    exit 1
fi

echo "Starting TruAi database backup..."
echo "Source: $DB_PATH"
echo "Destination: $BACKUP_FILE"

# SQLite online backup (handles locks gracefully)
sqlite3 "$DB_PATH" ".backup '$BACKUP_FILE'"

# Verify backup
if [ ! -f "$BACKUP_FILE" ]; then
    echo "✗ Backup failed"
    exit 1
fi

# Compress
gzip "$BACKUP_FILE"
echo "✓ Backup compressed: ${BACKUP_FILE}.gz"

# Calculate size
BACKUP_SIZE=$(du -h "${BACKUP_FILE}.gz" | cut -f1)
echo "✓ Backup size: $BACKUP_SIZE"

# Cleanup old backups (keep last 30 days)
find "$BACKUP_DIR" -name "truai_*.db.gz" -mtime +$RETENTION_DAYS -delete
OLD_COUNT=$(find "$BACKUP_DIR" -name "truai_*.db.gz" -mtime +$RETENTION_DAYS | wc -l)
if [ $OLD_COUNT -gt 0 ]; then
    echo "✓ Cleaned up $OLD_COUNT old backups (>$RETENTION_DAYS days)"
fi

# List recent backups
echo ""
echo "Recent backups:"
ls -lh "$BACKUP_DIR" | tail -5

echo ""
echo "✓ Backup complete: ${BACKUP_FILE}.gz"
```

**Make executable:**
```bash
chmod +x scripts/backup_database.sh
```

---

### 3.5 Create `scripts/backup_database.service` (systemd unit)

**Purpose:** Systemd service for automated backups

**File: `scripts/backup_database.service`**
```ini
[Unit]
Description=TruAi Database Backup
After=network.target

[Service]
Type=oneshot
User=www-data
Group=www-data
ExecStart=/path/to/TruAi/scripts/backup_database.sh
StandardOutput=journal
StandardError=journal
```

**File: `scripts/backup_database.timer`**
```ini
[Unit]
Description=TruAi Daily Backup Timer
Requires=backup_database.service

[Timer]
OnCalendar=daily
OnCalendar=02:00
Persistent=true

[Install]
WantedBy=timers.target
```

**Installation Instructions (add to DEPLOYMENT.md):**
```bash
# Copy units to systemd
sudo cp scripts/backup_database.service /etc/systemd/system/
sudo cp scripts/backup_database.timer /etc/systemd/system/

# Update paths in service file
sudo nano /etc/systemd/system/backup_database.service
# Change User/Group and ExecStart path

# Enable and start timer
sudo systemctl daemon-reload
sudo systemctl enable backup_database.timer
sudo systemctl start backup_database.timer

# Check status
sudo systemctl list-timers backup_database.timer
```

---

## Phase 4: Testing & CI/CD

### 4.1 Create `.github/workflows/ci.yml`

**Purpose:** Automated CI pipeline with PHP validation and tests

**Full File:**
```yaml
name: TruAi CI

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  lint:
    name: PHP Syntax Validation
    runs-on: ubuntu-latest
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v3
    
    - name: Setup PHP 8.2
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
        extensions: sqlite3, openssl, mbstring, json
        coverage: none
    
    - name: Validate PHP syntax
      run: |
        echo "Checking PHP syntax in backend/..."
        find backend -name "*.php" -exec php -l {} \; || exit 1
        
        echo "Checking PHP syntax in scripts/..."
        find scripts -name "*.php" -exec php -l {} \; || exit 1
    
    - name: Check for common security issues
      run: |
        echo "Checking for exposed secrets..."
        ! grep -r "sk-[a-zA-Z0-9]\{48\}" backend/ scripts/ || (echo "OpenAI API key found in code!" && exit 1)
        ! grep -r "sk-ant-[a-zA-Z0-9]\{48\}" backend/ scripts/ || (echo "Anthropic API key found in code!" && exit 1)
        
        echo "Checking file permissions..."
        [ ! -f database/.initial_credentials ] || [ "$(stat -c %a database/.initial_credentials 2>/dev/null || echo 600)" = "600" ]

  test:
    name: Unit Tests
    runs-on: ubuntu-latest
    needs: lint
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v3
    
    - name: Setup PHP 8.2
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
        extensions: sqlite3, openssl, mbstring, json
    
    - name: Create .env file
      run: |
        cat > .env <<EOF
        TRUAI_DEPLOYMENT=testing
        OPENAI_API_KEY=sk-test-key-not-real
        ANTHROPIC_API_KEY=sk-ant-test-key-not-real
        EOF
    
    - name: Initialize database
      run: |
        php scripts/setup_database.php
    
    - name: Run unit tests
      run: |
        php tests/run_tests.php
    
    - name: Check database integrity
      run: |
        sqlite3 database/truai.db "PRAGMA integrity_check;" | grep -q "ok" || exit 1
        echo "✓ Database integrity check passed"

  integration:
    name: Integration Tests
    runs-on: ubuntu-latest
    needs: test
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v3
    
    - name: Setup PHP 8.2
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
        extensions: sqlite3, openssl, mbstring, json
    
    - name: Initialize database
      run: php scripts/setup_database.php
    
    - name: Start PHP dev server
      run: |
        php -S 127.0.0.1:8001 -t public > /tmp/php_server.log 2>&1 &
        echo $! > /tmp/php_server.pid
        sleep 2
    
    - name: Health check
      run: |
        curl -f http://127.0.0.1:8001/TruAi/api/v1/health || (cat /tmp/php_server.log && exit 1)
    
    - name: Test ROMA endpoint
      run: |
        curl -s http://127.0.0.1:8001/TruAi/api/v1/security/roma | grep -q '"roma"' || exit 1
        echo "✓ ROMA endpoint responsive"
    
    - name: Test login endpoint (expect failure without creds)
      run: |
        HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
          -X POST http://127.0.0.1:8001/TruAi/api/v1/auth/login \
          -H "Content-Type: application/json" \
          -d '{"username":"test","password":"test"}')
        
        [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "401" ] || [ "$HTTP_CODE" = "400" ] || exit 1
        echo "✓ Login endpoint responsive"
    
    - name: Cleanup
      if: always()
      run: |
        [ -f /tmp/php_server.pid ] && kill $(cat /tmp/php_server.pid) || true

  security:
    name: Security Scan
    runs-on: ubuntu-latest
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v3
    
    - name: Check for sensitive files
      run: |
        echo "Checking for sensitive files in repository..."
        [ ! -f database/.initial_credentials ] || (echo ".initial_credentials should not be committed!" && exit 1)
        [ ! -f database/truai.db ] || (echo "Database should not be committed!" && exit 1)
        [ ! -f .env ] || (echo ".env should not be committed!" && exit 1)
        echo "✓ No sensitive files found"
    
    - name: Check CORS configuration
      run: |
        grep -q "localhost:8001" backend/config.php || (echo "Port 8001 not in CORS config!" && exit 1)
        grep -q "localhost:8001" backend/router.php || (echo "Port 8001 not in router CORS!" && exit 1)
        echo "✓ CORS configuration includes port 8001"
    
    - name: Verify Argon2id usage
      run: |
        grep -q "PASSWORD_ARGON2ID" backend/config.php || (echo "Argon2id not configured!" && exit 1)
        grep -q "ARGON2ID_OPTIONS" backend/config.php || (echo "Argon2id options not set!" && exit 1)
        echo "✓ Argon2id password hashing configured"
```

---

### 4.2 Create `tests/run_tests.php`

**Purpose:** Basic unit test runner

**Full File:**
```php
#!/usr/bin/env php
<?php
/**
 * TruAi Test Runner
 * 
 * Executes unit tests for core functionality
 * 
 * Usage: php tests/run_tests.php
 * 
 * @package TruAi
 * @version 1.0.0
 */

require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/database.php';
require_once __DIR__ . '/../backend/auth.php';
require_once __DIR__ . '/../backend/validator.php';
require_once __DIR__ . '/../backend/csrf.php';

class TestRunner {
    private $passed = 0;
    private $failed = 0;
    private $tests = [];
    
    public function assert(bool $condition, string $message): void {
        if ($condition) {
            echo "  ✓ $message\n";
            $this->passed++;
        } else {
            echo "  ✗ $message\n";
            $this->failed++;
        }
    }
    
    public function assertEqual($expected, $actual, string $message): void {
        $this->assert($expected === $actual, $message . " (expected: $expected, got: $actual)");
    }
    
    public function testValidator(): void {
        echo "\n=== Validator Tests ===\n";
        
        // Valid username
        $result = Validator::username('admin');
        $this->assert($result['valid'] === true, 'Valid username accepted');
        $this->assertEqual('admin', $result['value'], 'Username value preserved');
        
        // Short username
        $result = Validator::username('ab');
        $this->assert($result['valid'] === false, 'Short username rejected');
        $this->assert(count($result['errors']) > 0, 'Error message provided for short username');
        
        // Invalid characters
        $result = Validator::username('admin@test');
        $this->assert($result['valid'] === false, 'Username with @ symbol rejected');
        
        // Long username
        $result = Validator::username(str_repeat('a', 33));
        $this->assert($result['valid'] === false, 'Username over 32 chars rejected');
        
        // Valid password
        $result = Validator::password('Password123!');
        $this->assert($result['valid'] === true, 'Strong password accepted');
        
        // Weak passwords
        $result = Validator::password('weak');
        $this->assert($result['valid'] === false, 'Weak password rejected');
        
        $result = Validator::password('NoNumbersOrSymbols');
        $this->assert($result['valid'] === false, 'Password without numbers rejected');
        
        $result = Validator::password('nonumbers123!');
        $this->assert($result['valid'] === false, 'Password without uppercase rejected');
        
        // File path sanitization
        $clean = Validator::sanitizeFilePath('../../../etc/passwd');
        $this->assert(strpos($clean, '..') === false, 'Directory traversal removed');
        
        $clean = Validator::sanitizeFilePath('path/to/file.txt');
        $this->assertEqual('path/to/file.txt', $clean, 'Valid path preserved');
        
        // HTML sanitization
        $clean = Validator::sanitizeHTML('<script>alert("xss")</script>');
        $this->assert(strpos($clean, '<script>') === false, 'Script tags escaped');
        $this->assert(strpos($clean, '&lt;') !== false, 'HTML entities used');
    }
    
    public function testAuth(): void {
        echo "\n=== Authentication Tests ===\n";
        
        // Create test user
        $db = Database::getInstance();
        $testPassword = 'TestPass123!';
        $hash = password_hash($testPassword, PASSWORD_ARGON2ID, ARGON2ID_OPTIONS);
        
        $db->execute(
            "INSERT OR REPLACE INTO users (id, username, password_hash, role) VALUES (999, 'testuser', :hash, 'user')",
            [':hash' => $hash]
        );
        
        $auth = new Auth();
        
        // Test valid login
        session_start();
        $result = $auth->login('testuser', $testPassword);
        $this->assert($result === true, 'Valid login successful');
        $this->assert(isset($_SESSION['user_id']), 'Session user_id set after login');
        $this->assert(isset($_SESSION['login_time']), 'Session login_time set');
        
        // Test invalid password
        session_unset();
        $result = $auth->login('testuser', 'WrongPassword123!');
        $this->assert($result === false, 'Invalid password rejected');
        
        // Test non-existent user
        $result = $auth->login('nonexistent', $testPassword);
        $this->assert($result === false, 'Non-existent user rejected');
        
        // Test input validation integration
        $result = $auth->login('ab', $testPassword); // Too short
        $this->assert($result === false, 'Short username rejected by auth');
        
        $result = $auth->login('admin@test', $testPassword); // Invalid chars
        $this->assert($result === false, 'Invalid characters rejected by auth');
        
        // Cleanup
        $db->execute("DELETE FROM users WHERE id = 999");
    }
    
    public function testCSRF(): void {
        echo "\n=== CSRF Protection Tests ===\n";
        
        session_start();
        session_unset(); // Clear any existing token
        
        // Generate token
        $token1 = CSRFProtection::generateToken();
        $this->assert(!empty($token1), 'CSRF token generated');
        $this->assert(strlen($token1) === 64, 'Token is 64 characters (32 bytes hex)');
        
        // Same token returned on subsequent calls
        $token2 = CSRFProtection::generateToken();
        $this->assertEqual($token1, $token2, 'Same token returned without regeneration');
        
        // Validate correct token
        $valid = CSRFProtection::validateToken($token1);
        $this->assert($valid === true, 'Correct token validates');
        
        // Validate incorrect token
        $valid = CSRFProtection::validateToken('invalid_token');
        $this->assert($valid === false, 'Incorrect token rejected');
        
        // Regenerate token
        $token3 = CSRFProtection::regenerateToken();
        $this->assert($token3 !== $token1, 'New token generated on regeneration');
        
        // Old token no longer valid
        $valid = CSRFProtection::validateToken($token1);
        $this->assert($valid === false, 'Old token invalidated after regeneration');
        
        // New token valid
        $valid = CSRFProtection::validateToken($token3);
        $this->assert($valid === true, 'New token validates');
    }
    
    public function testDatabase(): void {
        echo "\n=== Database Tests ===\n";
        
        $db = Database::getInstance();
        
        // Check database file exists
        $this->assert(file_exists(DB_PATH), 'Database file exists');
        $this->assert(is_writable(DB_PATH), 'Database file is writable');
        
        // Check required tables exist
        $tables = [
            'users', 'audit_logs', 'recovery_attempts', 'master_recovery_keys',
            'biometric_logins', 'conversations', 'messages', 'settings'
        ];
        
        foreach ($tables as $table) {
            $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name=:table", [':table' => $table]);
            $this->assert(!empty($result), "Table '$table' exists");
        }
        
        // Test basic CRUD
        $testData = 'test_' . time();
        $db->execute(
            "INSERT INTO settings (user_id, key, value) VALUES (NULL, :key, :value)",
            [':key' => $testData, ':value' => 'test_value']
        );
        
        $result = $db->query(
            "SELECT value FROM settings WHERE key = :key",
            [':key' => $testData]
        );
        
        $this->assert(!empty($result), 'INSERT and SELECT work');
        $this->assertEqual('test_value', $result[0]['value'], 'Data retrieved correctly');
        
        // Cleanup
        $db->execute("DELETE FROM settings WHERE key = :key", [':key' => $testData]);
    }
    
    public function testEncryption(): void {
        echo "\n=== Encryption Tests ===\n";
        
        // Check encryption keys exist
        $keyDir = DATABASE_PATH . '/keys';
        $this->assert(file_exists($keyDir . '/private_key.pem'), 'Private key exists');
        $this->assert(file_exists($keyDir . '/public_key.pem'), 'Public key exists');
        
        // Test encryption service
        require_once __DIR__ . '/../backend/encryption.php';
        $enc = new EncryptionService();
        
        $publicKey = $enc->getPublicKey();
        $this->assert(!empty($publicKey), 'Public key retrieved');
        $this->assert(strlen($publicKey) > 100, 'Public key has reasonable length');
        
        // Test ROMA trust (basic check)
        require_once __DIR__ . '/../backend/roma_trust.php';
        $status = RomaTrust::getStatus($enc);
        
        $this->assert(isset($status['roma']), 'ROMA status includes "roma" key');
        $this->assert(isset($status['trust_state']), 'ROMA status includes "trust_state"');
        $this->assert(in_array($status['trust_state'], ['VERIFIED', 'UNVERIFIED', 'BLOCKED']), 'Valid trust state');
    }
    
    public function run(): void {
        echo "╔════════════════════════════════════════╗\n";
        echo "║       TruAi Unit Test Suite           ║\n";
        echo "╚════════════════════════════════════════╝\n";
        
        $this->testValidator();
        $this->testCSRF();
        $this->testDatabase();
        $this->testEncryption();
        $this->testAuth();
        
        echo "\n╔════════════════════════════════════════╗\n";
        echo "║            Test Summary                ║\n";
        echo "╚════════════════════════════════════════╝\n";
        echo "\n";
        printf("  Passed: %d\n", $this->passed);
        printf("  Failed: %d\n", $this->failed);
        printf("  Total:  %d\n", $this->passed + $this->failed);
        echo "\n";
        
        if ($this->failed > 0) {
            echo "❌ Tests failed\n";
            exit(1);
        } else {
            echo "✅ All tests passed\n";
            exit(0);
        }
    }
}

// Run tests
$runner = new TestRunner();
$runner->run();
```

---

## Phase 5: Documentation (Remaining)

### 5.1 Create `docs/API.md`

**Purpose:** Complete API reference documentation

**Structure:**
```markdown
# TruAi API Reference

## Base URL
```
http://127.0.0.1:8001/TruAi/api/v1
```

Production: `https://yourdomain.com/TruAi/api/v1`

## Authentication

All protected endpoints require:
- Valid session cookie (`TRUAI_SESSION`)
- CSRF token (for POST/PUT/DELETE)

### Get CSRF Token
```http
GET /auth/csrf-token
```

**Response:**
```json
{
  "csrf_token": "a1b2c3d4...",
  "expires_in": 3600
}
```

## Endpoints

### Authentication

#### Login
```http
POST /auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "YourPassword123!"
}
```

**Response (Success):**
```json
{
  "success": true,
  "username": "admin",
  "csrf_token": "a1b2c3d4..."
}
```

**Response (Failure):**
```json
{
  "success": false,
  "error": "Invalid username or password"
}
```

**Rate Limit:** 5 attempts per 5 minutes per username

#### Biometric Login
```http
POST /auth/biometric
Content-Type: application/json

{
  "app": "truai"
}
```

**Response:**
```json
{
  "success": true,
  "username": "admin",
  "auth_method": "biometric"
}
```

#### Master Key Recovery
```http
POST /auth/masterkey
Content-Type: application/json

{
  "username": "admin",
  "master_key": "64-character-hex-key..."
}
```

**Response:**
```json
{
  "success": true,
  "temporary_password": "TempPass123!xyz",
  "expires_at": "2026-02-21T14:30:00Z",
  "must_change": true,
  "message": "Temporary password valid for 10 minutes"
}
```

**Rate Limit:** 3 attempts per 24 hours per username

#### LSRP Recovery
```http
POST /auth/recovery
Content-Type: application/json

{
  "username": "admin",
  "os_username": "macuser",
  "os_password": "macPassword123"
}
```

**Requirements:**
- Must be called from localhost or trusted VPN
- ROMA trust must be verified
- OS admin credentials required

**Response:**
```json
{
  "success": true,
  "temporary_password": "encrypted-base64-string...",
  "expires_at": "2026-02-21T14:30:00Z",
  "message": "Temporary password generated. Change immediately after login."
}
```

#### Logout
```http
POST /auth/logout
X-CSRF-Token: a1b2c3d4...
```

**Response:**
```json
{
  "success": true
}
```

### Security

#### ROMA Status
```http
GET /security/roma
```

**Response:**
```json
{
  "roma": true,
  "portal_protected": true,
  "monitor": "active",
  "encryption": "RSA-2048 + AES-256-GCM",
  "local_only": true,
  "timestamp": 1708531200,
  "trust_state": "VERIFIED",
  "checks": {
    "encryption_keys": true,
    "session": true,
    "workspace": true,
    "workspace_writable": true
  }
}
```

**Trust States:**
- `VERIFIED` - All checks passed
- `UNVERIFIED` - One or more checks failed
- `BLOCKED` - Suspicion threshold exceeded (5 failures in 5 minutes)

### Chat

#### Send Message
```http
POST /chat/send
X-CSRF-Token: a1b2c3d4...
Content-Type: application/json

{
  "conversation_id": 123,
  "message": "Explain recursion in Python",
  "model": "auto"
}
```

**Parameters:**
- `conversation_id` (optional): Existing conversation ID, or null for new conversation
- `message` (required): User's message
- `model` (optional): `auto`, `gpt-4`, `claude-sonnet`

**Response:**
```json
{
  "conversation_id": 123,
  "message": {
    "role": "assistant",
    "content": "Recursion is a programming technique...",
    "model": "gpt-4"
  }
}
```

#### Get Conversations
```http
GET /chat/conversations
```

**Response:**
```json
{
  "conversations": [
    {
      "id": 123,
      "title": "Python Recursion Discussion",
      "message_count": 5,
      "created_at": "2026-02-20T10:00:00Z",
      "updated_at": "2026-02-21T11:30:00Z"
    }
  ]
}
```

#### Get Conversation Details
```http
GET /chat/conversations/123
```

**Response:**
```json
{
  "id": 123,
  "title": "Python Recursion Discussion",
  "created_at": "2026-02-20T10:00:00Z",
  "updated_at": "2026-02-21T11:30:00Z",
  "messages": [
    {
      "id": 456,
      "role": "user",
      "content": "Explain recursion in Python",
      "created_at": "2026-02-20T10:00:00Z"
    },
    {
      "id": 457,
      "role": "assistant",
      "content": "Recursion is...",
      "model": "gpt-4",
      "created_at": "2026-02-20T10:00:05Z"
    }
  ]
}
```

#### Delete Conversation
```http
DELETE /chat/conversations/123
X-CSRF-Token: a1b2c3d4...
```

**Response:**
```json
{
  "success": true
}
```

### Gemini.ai Automation

#### Get Stats
```http
GET /gemini/stats
```

**Response:**
```json
{
  "provisioned_nodes": 42,
  "active_alerts": 3,
  "avg_cpu_load": 27.5,
  "uptime_percent": 99.98,
  "activity": [
    {
      "event": "Auto-remediation applied to node gmn-07",
      "timestamp": "2026-02-21T11:00:00Z"
    }
  ],
  "alerts": [
    {
      "id": 1,
      "severity": "high",
      "message": "Disk usage critical on gmn-07",
      "node": "gmn-07",
      "remediation": "Run Diagnostics",
      "timestamp": 1708531200
    }
  ],
  "usage": {
    "api_calls": 1523,
    "tokens_estimate": 456789,
    "cost_estimate": 12.34
  }
}
```

#### Execute Automation
```http
POST /gemini/automation
X-CSRF-Token: a1b2c3d4...
Content-Type: application/json

{
  "action": "Run Diagnostics"
}
```

**Valid Actions:**
- `Run Diagnostics`
- `Apply Security Hardening`
- `Scale Cluster`
- `Provision Node`
- `Collect Logs`
- `Rotate Keys`

**Response:**
```json
{
  "success": true,
  "action": "Run Diagnostics",
  "results": {
    "cpu_usage": 27.5,
    "memory_usage": 45.2,
    "disk_usage": 68.1,
    "network_latency": 12,
    "timestamp": "2026-02-21T12:00:00Z"
  },
  "message": "Diagnostics completed successfully"
}
```

### Settings

#### Get Settings
```http
GET /settings/get
```

**Response:**
```json
{
  "settings": {
    "ai": {
      "openaiApiKey": "sk-...masked",
      "anthropicApiKey": "sk-ant-...masked",
      "defaultModel": "auto"
    },
    "appearance": {
      "theme": "dark",
      "fontSize": "medium"
    },
    "security": {
      "sessionTimeout": 3600
    }
  }
}
```

#### Save Settings
```http
POST /settings/save
X-CSRF-Token: a1b2c3d4...
Content-Type: application/json

{
  "ai": {
    "openaiApiKey": "sk-new-key...",
    "defaultModel": "gpt-4"
  }
}
```

**Response:**
```json
{
  "success": true,
  "message": "Settings saved successfully"
}
```

### Health

#### Health Check
```http
GET /health
```

**Response:**
```json
{
  "status": "ok",
  "timestamp": 1708531200,
  "checks": {
    "database": "ok",
    "encryption": "ok",
    "roma": "VERIFIED",
    "disk_space": "85% free"
  }
}
```

**Status Values:**
- `ok` - All checks passed
- `degraded` - Some checks failed but system operational
- `error` - Critical failure

## Error Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 400 | Bad Request (invalid input) |
| 401 | Unauthorized (not logged in) |
| 403 | Forbidden (CSRF failed or insufficient permissions) |
| 404 | Not Found |
| 429 | Too Many Requests (rate limited) |
| 500 | Internal Server Error |
| 503 | Service Unavailable (health check failed) |

## Rate Limiting

| Endpoint | Limit |
|----------|-------|
| `/auth/login` | 5 per 5 minutes per username |
| `/auth/recovery` | 3 per 24 hours per username |
| `/auth/masterkey` | 3 per 24 hours per username |
| `/chat/send` | 20 per minute per user |

**Rate Limit Headers:**
```http
HTTP/1.1 429 Too Many Requests
Retry-After: 300

{
  "error": "Too many login attempts. Please wait 5 minutes.",
  "retry_after": 300
}
```

## CORS

Allowed origins (development):
- `http://localhost:8001`
- `http://127.0.0.1:8001`
- `http://localhost:8080` (legacy)
- `http://127.0.0.1:8080` (legacy)

Production: Configure `ALLOWED_HOSTS` in `.env`

## Security Best Practices

1. **Always use HTTPS in production**
2. **Include CSRF token in all POST/PUT/DELETE requests**
3. **Validate session timeout client-side**
4. **Handle 401 responses by redirecting to login**
5. **Never log API keys or passwords**
6. **Use secure random for client-side tokens**
7. **Implement retry logic with exponential backoff for 429 responses**
```

---

### 5.2 Create `docs/DEPLOYMENT.md`

**Purpose:** Production deployment guide for Plesk/Nginx/Apache

**Outline:**
- Production environment requirements
- Plesk PHP-FPM pool configuration
- Nginx reverse proxy setup
- Apache .htaccess alternative
- Environment variable management
- SSL/TLS certificate setup (Let's Encrypt)
- Firewall rules
- Log rotation
- Backup automation (cron jobs)
- Health monitoring setup
- Zero-downtime deployment procedure
- Rollback procedure

(Full content similar to SETUP.md but production-focused)

---

### 5.3 Create `docs/SECURITY.md`

**Purpose:** Security architecture and threat model documentation

**Outline:**
- Security philosophy (self-sovereign, zero-trust)
- Threat model
  - Attack vectors addressed
  - Attack vectors NOT addressed
  - Assumptions
- Authentication architecture (UBSAS, LSRP)
- Encryption standards (Argon2id, RSA-2048, AES-256-GCM)
- ROMA trust protocol
- Session management
- CSRF protection
- Input validation
- Rate limiting
- Audit logging
- Incident response procedures
- Vulnerability disclosure policy
- Security checklist for administrators

---

### 5.4 Create `CHANGELOG.md`

**Purpose:** Version history and release notes

**Initial Content:**
```markdown
# Changelog

All notable changes to TruAi will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-02-21

### Added
- Complete frontend UI suite (login-portal, dashboard, secure-recovery, gemini-portal)
- LSRP (Local Sovereign Recovery Protocol) v1.0 with 4-factor authentication
- UBSAS (Unified Biometric Sovereign Auth) v2.0 with OS-native biometric support
- ROMA Trust validation with real-time monitoring
- ROMA ITC (Internal Trust Channel) v1 for inter-system communication
- Argon2id password hashing (64MB memory-hard, LSRP-spec compliant)
- CSRF protection layer with token rotation
- Input validation service (username, password, path, HTML sanitization)
- Rate limiting on authentication endpoints (5 attempts/5min for login, 3/24h for recovery)
- Session security hardening (HttpOnly, Secure, SameSite, timeout tracking)
- Automated database setup script (`scripts/setup_database.php`)
- Emergency password reset script (`scripts/reset_admin_password.php`)
- Automated backup script with compression and retention (`scripts/backup_database.sh`)
- Health monitoring endpoint (`/api/v1/health`)
- Gemini.ai automation service with 6 actions (diagnostics, security, scaling, provisioning, logs, keys)
- AI exception handling hierarchy (AIException, AIRateLimitException, AITimeoutException, etc.)
- Chat service with context-aware conversations and file context injection
- Learning service with pattern extraction and user feedback tracking
- Comprehensive documentation (SETUP.md, API.md, SECURITY.md, DEPLOYMENT.md)
- CI/CD pipeline with PHP syntax validation, unit tests, integration tests, security scans
- Production deployment templates (Plesk PHP-FPM, Nginx, systemd units)
- Unit test suite with validator, auth, CSRF, database, encryption tests
- Browser extension infrastructure (manifest, native host, content scripts)

### Changed
- Migrated from Bcrypt to Argon2id for password hashing (16x more memory-hard)
- Primary port changed from 8080 to 8001 (8080 retained for backwards compatibility)
- Updated CORS configuration to include port 8001 as primary
- Enhanced session management with idle timeout (30 minutes) and absolute timeout (1 hour)
- Improved error logging with structured messages

### Security
- Implemented 4-factor LSRP recovery (local access, ROMA trust, OS admin, device fingerprint)
- Added biometric authentication support (Touch ID, Face ID, Linux fprintd)
- ROMA suspicion threshold protection (5 failures in 5 minutes → BLOCKED state)
- Session token regeneration on login (prevents fixation attacks)
- CSRF token enforcement on all state-changing requests
- Input validation layer prevents injection attacks (SQL, XSS, path traversal)
- Rate limiting prevents brute force attacks
- Audit logging for all authentication and security events
- Encrypted credential storage in OS keychain (macOS Keychain, Linux libsecret)
- Master recovery key system (256-bit offline backup)

### Fixed
- Router parse errors (escape sequences removed)
- Session initialization race conditions
- CORS configuration for port 8001
- Database locking issues with SQLite (implemented online backup)

## [Unreleased]

### Planned for 1.1.0
- Windows Hello biometric support
- Browser extension for all major browsers (Chrome, Firefox, Safari, Edge)
- Two-factor authentication (TOTP)
- Hardware security key support (YubiKey, WebAuthn)
- Advanced AI model routing (cost optimization)
- File upload and management UI
- Real-time collaboration features
- Mobile-responsive dashboard improvements
- Kubernetes deployment templates
- Terraform infrastructure-as-code
- Prometheus metrics exporter
- Grafana dashboard templates

### Planned for 2.0.0
- Multi-tenancy support
- RBAC (Role-Based Access Control) with fine-grained permissions
- API key management for third-party integrations
- Webhook system for event notifications
- Advanced analytics dashboard
- Machine learning model fine-tuning
- Custom AI agent builder
- Federated authentication (SAML, OAuth2)
- Cloud deployment options (AWS, Azure, GCP)
- Horizontal scaling support

## Version History

- [1.0.0] - 2026-02-21: Initial production release
```

---

## Phase 6: Final Checklist

### Pre-Deployment Verification

**Before marking repository as production-ready, verify:**

1. **Frontend Complete:**
   - [ ] `public/TruAi/login-portal.html` exists and functional
   - [ ] `public/TruAi/secure-recovery.html` exists and functional
   - [ ] All pages load without JavaScript errors
   - [ ] ROMA indicator displays correctly on all pages
   - [ ] Responsive design works on mobile devices

2. **Security Hardened:**
   - [ ] Session security implemented (HttpOnly, Secure, SameSite)
   - [ ] Session regeneration on login
   - [ ] CSRF protection active on all POST/PUT/DELETE endpoints
   - [ ] Input validation applied to all user inputs
   - [ ] Rate limiting active on login and recovery endpoints
   - [ ] No plaintext credentials in repository
   - [ ] No API keys in repository
   - [ ] Database file permissions set to 0600
   - [ ] Encryption keys permissions set to 0600

3. **Database & Automation:**
   - [ ] `scripts/setup_database.php` runs successfully
   - [ ] Database schema complete (all tables from schema.sql)
   - [ ] Default admin user created with secure password
   - [ ] `.initial_credentials` file generated and secured (chmod 600)
   - [ ] Encryption keys generated automatically
   - [ ] Backup script (`backup_database.sh`) works
   - [ ] Backup retention policy active (30 days)

4. **Documentation:**
   - [ ] `SETUP.md` complete and tested (follow step-by-step)
   - [ ] `docs/API.md` complete with all endpoints
   - [ ] `docs/DEPLOYMENT.md` complete with production steps
   - [ ] `docs/SECURITY.md` complete with threat model
   - [ ] `CHANGELOG.md` up to date
   - [ ] `README.md` includes project overview and quick start
   - [ ] Code comments (PHPDoc) added to new functions

5. **Testing:**
   - [ ] `tests/run_tests.php` passes all tests
   - [ ] CI pipeline passes (GitHub Actions)
   - [ ] Manual login flow tested (username/password)
   - [ ] Manual recovery flow tested (LSRP)
   - [ ] Biometric flow tested (if macOS available)
   - [ ] Gemini automation actions tested
   - [ ] Chat functionality tested
   - [ ] Health endpoint returns 200

6. **Operational:**
   - [ ] Start script (`start.sh`) works
   - [ ] Electron wrapper works (`npm start`)
   - [ ] Logs rotate properly
   - [ ] Health monitoring active
   - [ ] Backup automation scheduled (cron/systemd)

7. **Production Templates:**
   - [ ] Plesk PHP-FPM pool config created (`deployment/plesk/php-fpm-pool.conf`)
   - [ ] Nginx config created (`deployment/nginx/truai.conf`)
   - [ ] Systemd units created for backup (`scripts/*.service`, `scripts/*.timer`)

8. **Code Quality:**
   - [ ] All PHP files pass syntax check (`php -l`)
   - [ ] No `TODO` or `FIXME` comments in critical paths
   - [ ] Error handling added to all API endpoints
   - [ ] Logging added for authentication and security events
   - [ ] Input sanitization applied everywhere user data is used

---

## Implementation Notes

### Coding Standards

**PHP:**
- PSR-12 coding style
- PHPDoc comments for all classes and public methods
- Strict types enabled (`declare(strict_types=1);`)
- Error handling with try-catch blocks
- Meaningful variable names (no single letters except loop counters)

**JavaScript:**
- ES6+ syntax (arrow functions, async/await, const/let)
- Strict mode (`'use strict';`)
- JSDoc comments for functions
- Descriptive function names (verb + noun)
- Error handling with try-catch
- No `var` keyword (use `const` or `let`)

**HTML/CSS:**
- Semantic HTML5 elements
- BEM naming convention for CSS classes
- Mobile-first responsive design
- Accessibility attributes (ARIA labels, roles)
- Valid HTML (passes W3C validator)

### File Headers

All new PHP files should include:
```php
<?php
/**
 * [File Description]
 * 
 * [Detailed purpose and functionality]
 * 
 * @package TruAi
 * @version 1.0.0
 * @copyright My Deme, LLC © 2026
 */
```

### Git Commit Messages

Format:
```
<type>: <short summary>

<detailed description>

<issue reference>
```

Types:
- `feat:` New feature
- `fix:` Bug fix
- `docs:` Documentation update
- `style:` Code style change (formatting, no logic change)
- `refactor:` Code refactor (no feature change)
- `test:` Test addition or update
- `chore:` Build process, tooling, dependencies

Example:
```
feat: Add UBSAS biometric authentication

Implement 4-tier authentication system with OS-native biometric support
(Touch ID, Face ID, Linux fprintd). Includes keychain integration, device
fingerprinting, and fallback to manual password entry.

Resolves #42
```

### Testing Requirements

Before committing:
```bash
# 1. PHP syntax validation
find backend scripts -name "*.php" -exec php -l {} \;

# 2. Run unit tests
php tests/run_tests.php

# 3. Manual smoke test
./start.sh
curl http://127.0.0.1:8001/TruAi/api/v1/health

# 4. Check for secrets
grep -r "sk-[a-zA-Z0-9]\{48\}" backend/ scripts/ || echo "No secrets found"
```

### Deployment Procedure

1. **Staging Deployment:**
   ```bash
   # Pull latest code
   git pull origin main
   
   # Run database migrations
   php scripts/setup_database.php
   
   # Test
   php tests/run_tests.php
   curl http://staging.truai.local/TruAi/api/v1/health
   ```

2. **Production Deployment:**
   ```bash
   # Backup database
   ./scripts/backup_database.sh
   
   # Pull code
   git pull origin main
   
   # Run migrations (idempotent)
   php scripts/setup_database.php
   
   # Restart PHP-FPM
   sudo systemctl restart php8.2-fpm
   
   # Verify
   curl https://truai.yourdomain.com/TruAi/api/v1/health
   ```

3. **Rollback Procedure:**
   ```bash
   # Revert code
   git reset --hard <previous-commit>
   
   # Restore database
   gunzip -c ~/.truai_backups/truai_YYYYMMDD_HHMMSS.db.gz > database/truai.db
   
   # Restart
   sudo systemctl restart php8.2-fpm
   ```

---

## Summary

This document provides **complete, step-by-step instructions** for implementing all missing components to bring TruAi from 65% to 100% production-ready.

**Priority Implementation Order:**
1. Frontend UI (5 HTML files) - Day 1
2. Security hardening (session, CSRF, validation, rate limiting) - Day 1-2
3. Database automation (setup, reset, backup scripts) - Day 2
4. Documentation (SETUP.md, API.md, SECURITY.md) - Day 2-3
5. CI/CD pipeline and tests - Day 3
6. Production deployment templates - Day 4

**Total Estimated Effort:** 4 days (32 hours)

**Success Criteria:**
- All 5 frontend pages functional
- All security layers implemented
- CI pipeline green
- Documentation complete
- Health endpoint returns 200
- Manual testing passes (login, chat, recovery, biometric)

---

**END OF IMPLEMENTATION INSTRUCTIONS**
