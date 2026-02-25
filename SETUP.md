# TruAi HTML Server - Setup Guide

## Prerequisites

### System Requirements
- **Operating System:** macOS 12+ or Linux (Ubuntu 20.04+, Debian 11+)
- **PHP:** 8.2 or higher
- **PHP Extensions:** sqlite3, openssl, mbstring, json, curl
- **Node.js:** 18+ (optional, for Electron wrapper)
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
php -m | grep -E 'sqlite3|openssl|mbstring|json|curl'

# Check Node.js (optional)
node --version  # Should be 18+
```

## Quick Start (5 minutes)

```bash
# 1. Clone repository
git clone https://github.com/DemeWebsolutions/TruAi.git
cd TruAi

# 2. Run automated setup (creates database, admin user, encryption keys)
php scripts/setup_database.php

# 3. Get your generated credentials
cat database/.initial_credentials

# 4. Start the server
./start.sh
```

**Access:** http://127.0.0.1:8001/TruAi/login-portal.html

⚠️ **Change the admin password immediately after first login.**

## Installation

### Step 1: Verify Requirements

```bash
# Check PHP version (requires 8.2+)
php --version

# Check SQLite extension
php -m | grep sqlite3

# Check cURL extension (required for AI APIs)
php -m | grep curl
```

### Step 2: Initialize Database

```bash
# Automated setup: creates database, admin user, and encryption keys
php scripts/setup_database.php

# Get generated credentials
cat database/.initial_credentials
```

### Step 3: Configure AI API Keys (Optional)

**Required for full AI functionality:**

```bash
# OpenAI (recommended - supports GPT-3.5, GPT-4)
export OPENAI_API_KEY="sk-your-openai-key-here"

# Anthropic (optional - supports Claude models)
export ANTHROPIC_API_KEY="sk-ant-your-anthropic-key-here"
```

You can get API keys from:
- OpenAI: https://platform.openai.com/api-keys
- Anthropic: https://console.anthropic.com/

### Step 3: Navigate to TruAi Directory

```bash
cd /home/runner/work/Tru.ai/Tru.ai/TruAi
```

### Step 4: Set Permissions

```bash
# Create necessary directories
mkdir -p database logs

# Set permissions
chmod 755 database logs
```

### Step 5: Start the Server

```bash
# Start PHP built-in server
php -S localhost:8080 router.php
```

**Important:** Use `router.php` instead of `index.php` to ensure:
- API requests are properly routed
- Static files (CSS, JS, images) are served correctly
- All endpoints work as expected

### Step 6: Test AI Connection (Optional but Recommended)

```bash
# Test AI API connectivity
curl http://localhost:8080/api/v1/ai/test
```

Expected response:
```json
{
  "success": true,
  "results": {
    "openai": {
      "status": "success",
      "message": "OpenAI API connected successfully"
    },
    "anthropic": {
      "status": "success",
      "message": "Anthropic API connected successfully"
    }
  }
}
```

### Step 7: Access the Application

Open your browser and navigate to:
```
http://localhost:8080
```

## Default Login Credentials

- **Username:** `admin`
- **Password:** Generated automatically by `php scripts/setup_database.php` — see `database/.initial_credentials`

⚠️ **Security Note:** Change this password immediately after first login and delete `database/.initial_credentials`!

To reset a forgotten password:
```bash
php scripts/reset_admin_password.php admin
```

## Features

### 🔒 Encrypted Login (Phantom.ai Style)
- AES-256-GCM client-side encryption
- SHA-256 password hashing before transmission
- RSA-2048 key exchange
- No plaintext passwords transmitted

### 🎨 Cursor-Style Interface
- 3-column layout (Review | Workspace | Output)
- Familiar development workflow
- Real-time AI interaction

### 🛡️ Security Features
- Localhost-only access (configurable)
- CSRF protection
- Session management
- Comprehensive audit logging
- Encrypted session data

### 🤖 TruAi Core Integration
- Automatic risk evaluation
- Multi-tier AI routing (Cheap/Mid/High)
- Production-by-default deployment
- Manual approval for high-risk tasks

## Testing the Installation

### Test 1: Check Server Status
```bash
curl http://localhost:8080/api/v1/auth/publickey
```

Expected: JSON response with public key

### Test 2: Test Login API
```bash
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'
```

Expected: JSON response with success=true

### Test 3: Access Dashboard
Open `http://localhost:8080` in your browser and verify:
- [ ] Login page loads with TruAi logo
- [ ] Legal notices are displayed
- [ ] Login with encrypted credentials works
- [ ] Dashboard shows 3-column layout
- [ ] Can submit a task prompt

## Troubleshooting

### Problem: PHP version too old
```bash
# Solution: Install PHP 8.0+
# Ubuntu/Debian:
sudo apt install php8.2 php8.2-sqlite3

# macOS (Homebrew):
brew install php@8.2
```

### Problem: SQLite extension not found
```bash
# Ubuntu/Debian:
sudo apt install php8.2-sqlite3

# Enable in php.ini:
extension=sqlite3
```

### Problem: Permission denied on database directory
```bash
chmod 755 database
chmod 755 logs
```

### Problem: Port 8080 already in use
```bash
# Use a different port
php -S localhost:8081 index.php
```

### Problem: Encryption not working
- Check browser console for errors
- Verify Web Crypto API support (modern browsers only)
- Fallback to standard login will occur automatically

### Problem: AI not responding or returning errors
```bash
# Check if API keys are set
echo $OPENAI_API_KEY
echo $ANTHROPIC_API_KEY

# Test AI connection
curl http://localhost:8080/api/v1/ai/test

# Verify cURL extension is enabled
php -m | grep curl

# Check PHP error logs
tail -f logs/error.log
```

**Common AI API errors:**
- "API key not configured" - Set environment variables before starting server
- "API request failed" - Check internet connectivity and API key validity
- "Invalid response" - API may be temporarily unavailable, try again

### Problem: cURL extension not found
```bash
# Ubuntu/Debian:
sudo apt install php8.2-curl

# macOS (Homebrew):
brew install php@8.2

# Verify installation
php -m | grep curl
```

### Problem: "Invalid credentials" Error

This is one of the most common issues, especially on fresh repository clones. Here's a comprehensive troubleshooting guide:

#### Root Causes

1. **Database not initialized yet** - The database auto-creates on first server start
2. **Wrong server startup command** - Must use `router.php`, not `index.php`
3. **Database directory missing** - Git doesn't track empty directories (now fixed with `.gitkeep` files)
4. **Incorrect credentials** - Default credentials are case-sensitive

#### Solution 1: Verify Server Startup Command

**CRITICAL:** You must use `router.php`, not `index.php`:

```bash
# ✅ CORRECT:
cd TruAi
php -S localhost:8080 router.php

# ❌ WRONG (will cause routing issues):
php -S localhost:8080 index.php
```

**Why?** `router.php` properly routes:
- API requests to `/api/v1/*` endpoints
- Static files (CSS, JS, images) directly
- Frontend requests to `index.php`

#### Solution 2: Verify Database Exists

Check if the database was created:

```bash
# Check if database file exists
ls -la database/truai.db

# Should show:
# -rw-r--r-- 1 user group 8192 Jan 15 11:00 database/truai.db
```

If the database doesn't exist, the server will create it on first start. Make sure:
1. The `database/` directory exists (it should, thanks to `.gitkeep`)
2. The directory is writable: `chmod 755 database`
3. You've started the server at least once

#### Solution 3: Verify Default Credentials

**Default credentials are case-sensitive:**

- **Username:** `admin` (all lowercase)
- **Password:** `admin123` (all lowercase, no spaces)

Common mistakes:
- ❌ `Admin` (capital A)
- ❌ `ADMIN` (all caps)
- ❌ `admin123 ` (trailing space)
- ❌ `Admin123` (capital A)

#### Solution 4: Check Database Directly

Verify the admin user exists in the database:

```bash
# Check if admin user exists
sqlite3 database/truai.db "SELECT username, role FROM users WHERE username='admin';"

# Expected output:
# admin|SUPER_ADMIN
```

If no user is returned, the database needs to be initialized:

```bash
# Re-initialize database (will recreate admin user)
rm database/truai.db
php -S localhost:8080 router.php
# Wait a few seconds, then stop with Ctrl+C
# The database will be recreated with default admin user
```

#### Solution 5: Reset Corrupted Database

If the database exists but login still fails:

```bash
# Backup existing database (optional)
cp database/truai.db database/truai.db.backup

# Remove corrupted database
rm database/truai.db

# Restart server (will recreate database)
php -S localhost:8080 router.php
# Wait a few seconds, then stop with Ctrl+C
```

Then try logging in again with:
- Username: `admin`
- Password: `admin123`

#### Solution 6: Browser Console Debugging

Open browser Developer Tools (F12) and check:

1. **Console Tab:**
   - Look for JavaScript errors
   - Check for API errors
   - Verify encryption initialization messages

2. **Network Tab:**
   - Try to login
   - Find the `/api/v1/auth/login` request
   - Check:
     - **Request Method:** Should be `POST`
     - **Request Payload:** Should show `{"username":"admin","password":"admin123"}`
     - **Response Status:** 
       - `200` = Success (check response body for `"success":true`)
       - `401` = Invalid credentials (check if password is correct)
       - `400` = Bad request (check if username/password are in payload)
     - **Response Body:** Should be JSON with either success or error message

3. **Common Network Tab Issues:**
   - Request shows `404` → Server not using `router.php`
   - Request shows `CORS error` → Check server is running on `localhost:8080`
   - Request shows `Network error` → Server not running or wrong URL

#### Solution 7: Test API Directly

Test the login API endpoint directly with curl:

```bash
# Test login API
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'

# Expected success response:
# {"success":true,"username":"admin","csrf_token":"...","encryption":"standard"}

# Expected error response (wrong password):
# {"error":"Invalid credentials"}
```

If curl works but browser doesn't:
- Check browser console for JavaScript errors
- Verify API_BASE configuration in browser
- Check for CORS issues

#### Solution 8: Verify Database Initialization

The database should auto-initialize on first server start. To verify:

```bash
# Check database tables exist
sqlite3 database/truai.db ".tables"

# Should show:
# audit_logs  conversations  messages  tasks  task_approvals  users

# Check users table
sqlite3 database/truai.db "SELECT COUNT(*) FROM users;"

# Should return: 1 (the admin user)
```

If tables don't exist, the database initialization failed. Check:
- PHP SQLite extension: `php -m | grep sqlite3`
- Directory permissions: `ls -ld database`
- PHP error logs: `tail -f logs/error.log`

#### Solution 9: Manual Database Initialization

If automatic initialization fails:

```bash
# Run manual initialization script
php init-database.php

# This will:
# - Create database directory if missing
# - Initialize database schema
# - Create default admin user
# - Verify everything works
```

#### Solution 10: Complete Fresh Start

If nothing else works, do a complete fresh start:

```bash
# 1. Stop the server (Ctrl+C)

# 2. Remove existing database
rm -f database/truai.db

# 3. Ensure directories exist and are writable
mkdir -p database logs
chmod 755 database logs

# 4. Start server with router.php
php -S localhost:8080 router.php

# 5. Wait a few seconds for initialization

# 6. In another terminal, verify database was created
ls -la database/truai.db

# 7. Verify admin user exists
sqlite3 database/truai.db "SELECT username FROM users;"
# Should return: admin

# 8. Try logging in at http://localhost:8080
# Username: admin
# Password: admin123
```

#### Quick Verification Checklist

Before reporting an issue, verify:

- [ ] Server is running: `ps aux | grep "php -S localhost:8080"`
- [ ] Using correct startup command: `php -S localhost:8080 router.php`
- [ ] Database exists: `ls -la database/truai.db`
- [ ] Database has admin user: `sqlite3 database/truai.db "SELECT username FROM users;"`
- [ ] Using correct credentials: `admin` / `admin123` (case-sensitive, lowercase)
- [ ] No typos or extra spaces in credentials
- [ ] Browser console shows no JavaScript errors
- [ ] Network tab shows API request with status 200 or 401
- [ ] API test with curl works: `curl -X POST http://localhost:8080/api/v1/auth/login ...`

#### Still Having Issues?

If you've tried all the above and still can't login:

1. **Check PHP Error Logs:**
   ```bash
   tail -f logs/error.log
   ```

2. **Verify PHP Extensions:**
   ```bash
   php -m | grep -E "sqlite3|pdo|session"
   ```

3. **Test Database Connection:**
   ```bash
   php -r "require 'backend/config.php'; require 'backend/database.php'; \$db = Database::getInstance(); echo 'Database OK\n';"
   ```

4. **Check File Permissions:**
   ```bash
   ls -la database/
   chmod 755 database
   chmod 644 database/truai.db
   ```

5. **Review Documentation:**
   - See `DATABASE_INITIALIZATION.md` for detailed database setup
   - See `INVALID_CREDENTIALS_FIX.md` for additional troubleshooting

## Directory Structure

```
TruAi/
├── index.php                 # Main entry point
├── backend/                  # PHP backend
│   ├── config.php           # Configuration
│   ├── database.php         # Database layer
│   ├── auth.php             # Authentication
│   ├── encryption.php       # Encryption service
│   ├── router.php           # API router
│   ├── truai_service.php    # TruAi Core logic
│   └── chat_service.php     # Chat functionality
├── assets/
│   ├── css/main.css         # Styles
│   ├── js/
│   │   ├── crypto.js        # Encryption utilities
│   │   ├── api.js           # API client
│   │   ├── login.js         # Login page
│   │   └── dashboard.js     # Dashboard
│   └── images/              # TruAi logos
├── database/                 # SQLite database (auto-created)
└── logs/                     # Application logs (auto-created)
```

## Production Deployment

1. **Change Default Credentials**
   - Access database: `sqlite3 database/truai.db`
   - Update password: `UPDATE users SET password_hash = ? WHERE username = 'admin';`

2. **Set Environment Variables**
   ```bash
   export APP_ENV=production
   export TRUAI_API_KEY="your-key"
   export OPENAI_API_KEY="your-key"
   export ANTHROPIC_API_KEY="your-key"
   ```

3. **Use Production Web Server**
   - Configure Apache/Nginx
   - Enable HTTPS/TLS
   - Set appropriate PHP-FPM settings

4. **Security Checklist**
   - [ ] Changed default credentials
   - [ ] HTTPS enabled
   - [ ] Firewall configured
   - [ ] File permissions set correctly
   - [ ] Error logging enabled
   - [ ] Regular backups configured

## Support

For issues or questions:
- Check logs: `tail -f logs/error.log`
- Review README.md
- Contact: DemeWebsolutions.com

---

**TruAi HTML Server Version**  
Copyright My Deme, LLC © 2026
