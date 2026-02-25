# **TruAi Repository Consolidation & Documentation Completion Plan**

## **📋 EXECUTIVE SUMMARY**

This comprehensive plan will:
1. ✅ Create 2 missing HTML files (`secure-recovery.html`, `ubsas-entrance.html`) — **CORRECTION: Files already exist!**
2. 🧹 Archive 46+ redundant documentation files
3. 📁 Relocate misplaced root-level files to proper directories
4. 📚 Create 3 comprehensive documentation files (`API.md`, `SECURITY.md`, `DEPLOYMENT.md`)
5. 🎯 Result: Clean, organized repository ready for production

**Estimated Time:** 2-3 days (16-24 hours)

---

## **PHASE 1: HTML FILE STATUS VERIFICATION** ⚠️

### **🔍 CRITICAL FINDING**

After scanning the repository, **all HTML files already exist** in `public/TruAi/`:

| File | Status | Size |
|------|--------|------|
| `secure-recovery.html` | ✅ **EXISTS** | 17,766 bytes |
| `ubsas-entrance.html` | ✅ **EXISTS** | 24,320 bytes |
| `ubsas-enroll.html` | ✅ **EXISTS** | 27,204 bytes |

**Action:** Skip HTML creation, proceed directly to **Phase 2: Repository Consolidation**.

---

## **PHASE 2: REPOSITORY CONSOLIDATION PR**

### **📁 Step 1: Create Archive Directory Structure**

```bash
# Create archive directories
mkdir -p archive/milestones
mkdir -p archive/updates
mkdir -p archive/fixes
mkdir -p archive/working-notes
```

---

### **🗂️ Step 2: Archive Files Checklist**

**Create file: `.github/workflows/consolidation-checklist.md`**

````markdown name=.github/workflows/consolidation-checklist.md
# Repository Consolidation Checklist

## Phase 1: Archive Redundant Documentation

### Milestone Documents (Move to `archive/milestones/`)
- [ ] PHASE1_FINAL_SUMMARY.md
- [ ] PHASE1_FIXES_IMPLEMENTATION.md
- [ ] PHASE4_IMPLEMENTATION_SUMMARY.md
- [ ] PROJECT_CONFIRMATION.md
- [ ] PROJECT_REVIEW.md
- [ ] DELIVERABLES_SUMMARY.md

### Update/Working Notes (Move to `archive/updates/`)
- [ ] "TruAi Update - Biometric and Bug Fixes.md"
- [ ] "TruAi Update - Biometric and Bug Fixes Refinements.md"
- [ ] "TruAi Update - Biometric and Bug Fixes.rtf"
- [ ] "TruAi Update - Critical Updates.md"
- [ ] "TruAi Update - Revied Updates.md"
- [ ] "TruAi Update - original html refernces.md"
- [ ] "TruAi Update - Template for missing html.md"

### Bug Fix Documentation (Move to `archive/fixes/`)
- [ ] INVALID_CREDENTIALS_FIX.md
- [ ] PATH_FIX_SUMMARY.md
- [ ] ROUTING_SOLUTION.md
- [ ] LOGIN_TROUBLESHOOTING.md (merge into TROUBLESHOOTING.md first)
- [ ] CURSOR_STYLE_UPDATES.md (info retained in CURSOR_STYLE_UPDATES_COMPLETE.md)

### Redundant/Superseded Docs (Move to `archive/superseded/`)
- [ ] DATABASE_INITIALIZATION.md (superseded by SETUP.md)
- [ ] FILES_REQUIRED.txt (outdated file list)
- [ ] GITHUB_PUSH_INSTRUCTIONS.md (redundant with UPLOAD_TO_GITHUB.md)
- [ ] UPLOAD_TO_GITHUB.md (standard git workflow)
- [ ] PASSWORD_CHANGE.md (covered in V1_OPERATOR_GUIDE.md)
- [ ] AI_RESPONSE_INVESTIGATION.md (merged into DEV_TESTING.md)
- [ ] SETTINGS_WIRING_CONFIRMED.md (testing complete)
- [ ] SELECTION_TOOLS.md (feature complete)

## Phase 2: Relocate Misplaced Files

### HTML Files (Move to `public/TruAi/` if duplicates exist)
- [ ] Check if root-level HTML duplicates exist:
  - [ ] access-denied.html
  - [ ] access-granted.html
  - [ ] loading.html
  - [ ] login-portal.html
  - [ ] welcome.html
- [ ] If duplicates exist in root, DELETE (originals in `public/TruAi/`)

### Test Files (Move to `dev/` or `tests/integration/`)
- [ ] test-init.html → dev/test-init.html
- [ ] test-new-designs.html → dev/test-new-designs.html
- [ ] test-popup-direct.html → dev/test-popup-direct.html
- [ ] preview-new-design.html → dev/preview-new-design.html
- [ ] test-login-flow.sh → tests/integration/login-flow-test.sh
- [ ] test-settings-api.sh → tests/integration/settings-api-test.sh
- [ ] test-settings-wiring.php → tests/integration/settings-wiring-test.php

### Backend Scripts (Move to `backend/scripts/` or `scripts/`)
- [ ] reset-admin-password.php → scripts/reset-admin-password.php
- [ ] change-password.php → scripts/change-password.php
- [ ] init-database.php → scripts/init-database.php

### Backup/Staging Directories (Evaluate for removal)
- [ ] Evaluate `TruAi-Git/` directory
  - [ ] If backup: move to `archive/backups/`
  - [ ] If duplicate: DELETE
- [ ] Evaluate `TruAi-Update/` directory
  - [ ] If staging: merge changes and DELETE
  - [ ] If unused: DELETE

## Phase 3: Asset Consolidation

### Large Design Files (Evaluate necessity)
- [ ] Home.svg (714KB) - move to `design/` or optimize with SVGO
- [ ] TruAi Login Portal.svg (649KB) - move to `design/` or optimize
- [ ] TruAi Login Portal.pdf (293KB) - move to `design/` or remove
- [ ] TruAi.Gateway.svg (710KB) - move to `design/` or optimize
- [ ] TruAi.Gatewaycopy.html (710KB) - duplicate? Remove if unused

## Phase 4: Documentation Consolidation

### New Documentation Files to Create
- [ ] `docs/API.md` (comprehensive API reference)
- [ ] `docs/SECURITY.md` (security model)
- [ ] `docs/DEPLOYMENT.md` (production deployment guide)

### Documentation to Enhance
- [ ] README.md (add quickstart + links to all docs)
- [ ] QUICKSTART.md (merge QUICKSTART.txt + QUICK_START.md)
- [ ] TROUBLESHOOTING.md (merge LOGIN_TROUBLESHOOTING.md content)
- [ ] TESTING.md (merge TEST_SUITE_README.md + DEV_TESTING.md)

### Documentation to Rename
- [ ] V1_OPERATOR_GUIDE.md → docs/OPERATOR_GUIDE.md
- [ ] TEST_SUITE_README.md → docs/TESTING.md (after merge)

## Phase 5: Final Verification

- [ ] Run all tests: `./scripts/run-all-tests.sh`
- [ ] Verify health endpoint: `curl http://127.0.0.1:8001/TruAi/api/v1/health`
- [ ] Test login flow manually
- [ ] Test biometric enrollment (if macOS available)
- [ ] Verify ROMA status indicator on all pages
- [ ] Check git status for untracked files
- [ ] Verify no sensitive files (`.initial_credentials`, `truai.db`, `.env`) committed

---

**Completion Criteria:**
- [ ] Root directory has ≤15 files (down from 70+)
- [ ] All documentation organized in `docs/` or `archive/`
- [ ] No duplicate HTML files in root
- [ ] All tests pass
- [ ] README.md updated with new structure
````

---

### **🔧 Step 3: Git Commands for Consolidation**

**Create file: `scripts/consolidate-repository.sh`**

```bash name=scripts/consolidate-repository.sh
#!/bin/bash
#
# TruAi Repository Consolidation Script
#
# Automates file archiving and relocation for cleaner repo structure
# 
# Usage: ./scripts/consolidate-repository.sh
#

set -e

echo "======================================"
echo "TruAi Repository Consolidation"
echo "======================================"
echo ""

# Safety check: ensure we're in TruAi root
if [ ! -f "README.md" ] || [ ! -d "backend" ]; then
    echo "❌ Error: Run this script from TruAi project root"
    exit 1
fi

# Safety check: ensure git status is clean
if [ -n "$(git status --porcelain)" ]; then
    echo "⚠️  Warning: You have uncommitted changes"
    echo ""
    git status --short
    echo ""
    read -p "Continue anyway? (y/N): " confirm
    if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
        echo "Aborted. Commit or stash changes first."
        exit 1
    fi
fi

# Create new branch for consolidation
BRANCH="consolidation/$(date +%Y%m%d)"
echo "📌 Creating branch: $BRANCH"
git checkout -b "$BRANCH"

# Create archive directories
echo ""
echo "📁 Creating archive directories..."
mkdir -p archive/milestones
mkdir -p archive/updates
mkdir -p archive/fixes
mkdir -p archive/superseded
mkdir -p archive/backups
mkdir -p design/
mkdir -p dev/

echo "✓ Archive directories created"

# Phase 1: Archive milestone documents
echo ""
echo "📦 Phase 1: Archiving milestone documents..."

FILES_MILESTONES=(
    "PHASE1_FINAL_SUMMARY.md"
    "PHASE1_FIXES_IMPLEMENTATION.md"
    "PHASE4_IMPLEMENTATION_SUMMARY.md"
    "PROJECT_CONFIRMATION.md"
    "PROJECT_REVIEW.md"
    "DELIVERABLES_SUMMARY.md"
)

for file in "${FILES_MILESTONES[@]}"; do
    if [ -f "$file" ]; then
        git mv "$file" "archive/milestones/" && echo "  ✓ $file"
    fi
done

# Phase 2: Archive update/working notes
echo ""
echo "📦 Phase 2: Archiving update notes..."

FILES_UPDATES=(
    "TruAi Update - Biometric and Bug Fixes.md"
    "TruAi Update - Biometric and Bug Fixes Refinements.md"
    "TruAi Update - Biometric and Bug Fixes.rtf"
    "TruAi Update - Critical Updates.md"
    "TruAi Update - Revied Updates.md"
    "TruAi Update - original html refernces.md"
    "TruAi Update - Template for missing html.md"
)

for file in "${FILES_UPDATES[@]}"; do
    if [ -f "$file" ]; then
        git mv "$file" "archive/updates/" && echo "  ✓ $file"
    fi
done

# Phase 3: Archive fix documentation
echo ""
echo "📦 Phase 3: Archiving fix documentation..."

FILES_FIXES=(
    "INVALID_CREDENTIALS_FIX.md"
    "PATH_FIX_SUMMARY.md"
    "ROUTING_SOLUTION.md"
    "LOGIN_TROUBLESHOOTING.md"
    "CURSOR_STYLE_UPDATES.md"
)

for file in "${FILES_FIXES[@]}"; do
    if [ -f "$file" ]; then
        git mv "$file" "archive/fixes/" && echo "  ✓ $file"
    fi
done

# Phase 4: Archive superseded docs
echo ""
echo "📦 Phase 4: Archiving superseded documentation..."

FILES_SUPERSEDED=(
    "DATABASE_INITIALIZATION.md"
    "FILES_REQUIRED.txt"
    "GITHUB_PUSH_INSTRUCTIONS.md"
    "UPLOAD_TO_GITHUB.md"
    "PASSWORD_CHANGE.md"
    "AI_RESPONSE_INVESTIGATION.md"
    "SETTINGS_WIRING_CONFIRMED.md"
    "SELECTION_TOOLS.md"
)

for file in "${FILES_SUPERSEDED[@]}"; do
    if [ -f "$file" ]; then
        git mv "$file" "archive/superseded/" && echo "  ✓ $file"
    fi
done

# Phase 5: Move test files to dev/
echo ""
echo "📦 Phase 5: Relocating test files..."

TEST_FILES=(
    "test-init.html"
    "test-new-designs.html"
    "test-popup-direct.html"
    "preview-new-design.html"
)

for file in "${TEST_FILES[@]}"; do
    if [ -f "$file" ]; then
        git mv "$file" "dev/" && echo "  ✓ $file → dev/"
    fi
done

# Phase 6: Move backend scripts
echo ""
echo "📦 Phase 6: Relocating backend scripts..."

BACKEND_SCRIPTS=(
    "reset-admin-password.php"
    "change-password.php"
    "init-database.php"
)

for file in "${BACKEND_SCRIPTS[@]}"; do
    if [ -f "$file" ]; then
        git mv "$file" "scripts/" && echo "  ✓ $file → scripts/"
    fi
done

# Phase 7: Check for duplicate HTML files in root
echo ""
echo "📦 Phase 7: Checking for duplicate HTML files in root..."

HTML_FILES=(
    "access-denied.html"
    "access-granted.html"
    "loading.html"
    "login-portal.html"
    "welcome.html"
)

for file in "${HTML_FILES[@]}"; do
    if [ -f "$file" ] && [ -f "public/TruAi/$file" ]; then
        echo "  ⚠️  Duplicate found: $file (exists in public/TruAi/)"
        echo "     Removing root-level duplicate..."
        git rm "$file" && echo "  ✓ Removed duplicate: $file"
    elif [ -f "$file" ]; then
        echo "  ℹ️  $file exists only in root (moving to public/TruAi/)"
        git mv "$file" "public/TruAi/" && echo "  ✓ Moved to public/TruAi/"
    fi
done

# Phase 8: Move large design files
echo ""
echo "📦 Phase 8: Relocating large design files..."

DESIGN_FILES=(
    "Home.svg"
    "TruAi Login Portal.svg"
    "TruAi Login Portal.pdf"
    "TruAi.Gateway.svg"
    "TruAi.Gatewaycopy.html"
)

for file in "${DESIGN_FILES[@]}"; do
    if [ -f "$file" ]; then
        git mv "$file" "design/" && echo "  ✓ $file → design/"
    fi
done

# Phase 9: Handle backup directories
echo ""
echo "📦 Phase 9: Evaluating backup/staging directories..."

if [ -d "TruAi-Git" ]; then
    echo "  ⚠️  Found TruAi-Git/ directory"
    echo "     This appears to be a backup. Moving to archive/backups/"
    git mv "TruAi-Git" "archive/backups/TruAi-Git" && echo "  ✓ Archived TruAi-Git/"
fi

if [ -d "TruAi-Update" ]; then
    echo "  ⚠️  Found TruAi-Update/ directory"
    echo "     This appears to be a staging area."
    echo "     Please manually review and merge changes, then delete."
    echo "     Skipping automated move."
fi

# Summary
echo ""
echo "======================================"
echo "✅ Repository Consolidation Complete"
echo "======================================"
echo ""
echo "Changes staged. Review with:"
echo "  git status"
echo "  git diff --cached --stat"
echo ""
echo "If satisfied, commit:"
echo "  git commit -m 'chore: consolidate repository structure"
echo ""
echo "  - Archive milestone, update, fix, and superseded docs"
echo "  - Move test files to dev/ directory"
echo "  - Relocate backend scripts to scripts/"
echo "  - Remove duplicate HTML files from root"
echo "  - Move large design files to design/"
echo "  - Archive TruAi-Git backup directory"
echo "  '"
echo ""
echo "Then push:"
echo "  git push origin $BRANCH"
echo ""
echo "And create PR on GitHub."
echo ""
```

**Make executable:**
```bash
chmod +x scripts/consolidate-repository.sh
```

---

## **PHASE 3: DOCUMENTATION CONSOLIDATION**

### **📚 Step 4: Create `docs/API.md`**

````markdown name=docs/API.md
# TruAi API Reference

**Version:** 1.0.0  
**Base URL:** `http://127.0.0.1:8001/TruAi/api/v1` (Development)  
**Production URL:** `https://yourdomain.com/TruAi/api/v1`

---

## **Table of Contents**

1. [Authentication](#authentication)
2. [ROMA Security](#roma-security)
3. [Chat](#chat)
4. [Settings](#settings)
5. [Gemini Automation](#gemini-automation)
6. [Health](#health)
7. [Error Codes](#error-codes)
8. [Rate Limiting](#rate-limiting)
9. [Security Best Practices](#security-best-practices)

---

## **Authentication**

All protected endpoints require:
- Valid session cookie (`TRUAI_SESSION`)
- CSRF token (for POST/PUT/DELETE requests)

### **Get CSRF Token**

```http
GET /auth/csrf-token
```

**Response:**
```json
{
  "csrf_token": "a1b2c3d4e5f6...",
  "expires_in": 3600
}
```

---

### **Login**

**Endpoint:** `POST /auth/login`

**Request Headers:**
```http
Content-Type: application/json
```

**Request Body:**
```json
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
  "csrf_token": "a1b2c3d4e5f6..."
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

---

### **Biometric Login**

**Endpoint:** `POST /auth/biometric`

**Request:**
```json
{
  "app": "truai"
}
```

**Requirements:**
- macOS 12+ with Touch ID or Face ID enabled
- Keychain entry for TruAi
- Native messaging host installed

**Response:**
```json
{
  "success": true,
  "username": "admin",
  "auth_method": "biometric",
  "csrf_token": "a1b2c3d4..."
}
```

---

### **Master Key Recovery**

**Endpoint:** `POST /auth/masterkey`

**Request:**
```json
{
  "username": "admin",
  "master_key": "64-character-hex-master-recovery-key..."
}
```

**Response:**
```json
{
  "success": true,
  "temporary_password": "TempPass123!xyz",
  "expires_at": "2026-02-21T14:30:00Z",
  "must_change": true,
  "message": "Temporary password valid for 10 minutes. Change immediately after login."
}
```

**Rate Limit:** 3 attempts per 24 hours per username

---

### **LSRP Recovery**

**Endpoint:** `POST /auth/recovery`

**Request:**
```json
{
  "username": "admin",
  "os_username": "macuser",
  "os_password": "macPassword123"
}
```

**Requirements (4-Factor Authentication):**
1. ✅ **Local Access:** Must be called from localhost or trusted VPN
2. ✅ **ROMA Trust:** ROMA status must be `VERIFIED`
3. ✅ **OS Admin:** Valid OS administrator credentials
4. ⚠️ **Device Fingerprint:** Device must match trusted device (warning if mismatch)

**Response:**
```json
{
  "success": true,
  "temporary_password": "encrypted-base64-string...",
  "expires_at": "2026-02-21T14:30:00Z",
  "factors_passed": {
    "local_access": true,
    "roma_trust": true,
    "os_admin": true,
    "device_fingerprint": false
  },
  "message": "Temporary password generated. Change immediately after login."
}
```

**Rate Limit:** 3 attempts per 24 hours per username

---

### **Logout**

**Endpoint:** `POST /auth/logout`

**Request Headers:**
```http
X-CSRF-Token: a1b2c3d4...
```

**Response:**
```json
{
  "success": true
}
```

---

### **Check Authentication Status**

**Endpoint:** `GET /auth/status`

**Response (Authenticated):**
```json
{
  "authenticated": true,
  "username": "admin",
  "role": "admin",
  "session_expires_at": "2026-02-21T15:00:00Z"
}
```

**Response (Not Authenticated):**
```json
{
  "authenticated": false
}
```

---

## **ROMA Security**

### **Get ROMA Status**

**Endpoint:** `GET /security/roma`

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
- `VERIFIED` - All security checks passed
- `UNVERIFIED` - One or more checks failed
- `BLOCKED` - Suspicion threshold exceeded (5 failures in 5 minutes)

---

## **Chat**

### **Send Message**

**Endpoint:** `POST /chat/send`

**Request Headers:**
```http
Content-Type: application/json
X-CSRF-Token: a1b2c3d4...
```

**Request Body:**
```json
{
  "conversation_id": 123,
  "message": "Explain recursion in Python",
  "model": "auto"
}
```

**Parameters:**
- `conversation_id` (optional): Existing conversation ID, or null for new conversation
- `message` (required): User's message (max 4000 characters)
- `model` (optional): `auto`, `gpt-4`, `gpt-4o`, `claude-sonnet`, `claude-opus`

**Response:**
```json
{
  "conversation_id": 123,
  "message": {
    "id": 456,
    "role": "assistant",
    "content": "Recursion is a programming technique where a function calls itself...",
    "model": "gpt-4o",
    "created_at": "2026-02-21T12:00:05Z"
  }
}
```

**Rate Limit:** 20 requests per minute per user

---

### **Get Conversations**

**Endpoint:** `GET /chat/conversations`

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
    },
    {
      "id": 124,
      "title": "JavaScript Async Patterns",
      "message_count": 3,
      "created_at": "2026-02-21T09:00:00Z",
      "updated_at": "2026-02-21T09:15:00Z"
    }
  ]
}
```

---

### **Get Conversation Details**

**Endpoint:** `GET /chat/conversations/{id}`

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
      "content": "Recursion is a programming technique...",
      "model": "gpt-4o",
      "created_at": "2026-02-20T10:00:05Z"
    }
  ]
}
```

---

### **Delete Conversation**

**Endpoint:** `DELETE /chat/conversations/{id}`

**Request Headers:**
```http
X-CSRF-Token: a1b2c3d4...
```

**Response:**
```json
{
  "success": true
}
```

---

## **Settings**

### **Get Settings**

**Endpoint:** `GET /settings/get`

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

---

### **Save Settings**

**Endpoint:** `POST /settings/save`

**Request Headers:**
```http
Content-Type: application/json
X-CSRF-Token: a1b2c3d4...
```

**Request Body:**
```json
{
  "ai": {
    "openaiApiKey": "sk-new-key...",
    "defaultModel": "gpt-4o"
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

---

### **Test API Keys**

**Endpoint:** `POST /settings/test-keys`

**Request Headers:**
```http
Content-Type: application/json
X-CSRF-Token: a1b2c3d4...
```

**Request Body:**
```json
{
  "openai_key": "sk-test...",
  "anthropic_key": "sk-ant-test..."
}
```

**Response:**
```json
{
  "results": {
    "openai": {
      "status": "success",
      "message": "API key valid. Model: gpt-4o"
    },
    "anthropic": {
      "status": "success",
      "message": "API key valid. Model: claude-3-opus-20240229"
    }
  }
}
```

---

## **Gemini Automation**

### **Get Stats**

**Endpoint:** `GET /gemini/stats`

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

---

### **Execute Automation**

**Endpoint:** `POST /gemini/automation`

**Request Headers:**
```http
Content-Type: application/json
X-CSRF-Token: a1b2c3d4...
```

**Request Body:**
```json
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

---

## **Health**

### **Health Check**

**Endpoint:** `GET /health`

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

---

## **Error Codes**

| Code | Meaning | Description |
|------|---------|-------------|
| 200 | Success | Request completed successfully |
| 400 | Bad Request | Invalid input or malformed request |
| 401 | Unauthorized | Not logged in or session expired |
| 403 | Forbidden | CSRF failed or insufficient permissions |
| 404 | Not Found | Resource not found |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Server-side error |
| 503 | Service Unavailable | Health check failed |

---

## **Rate Limiting**

| Endpoint | Limit | Window |
|----------|-------|--------|
| `/auth/login` | 5 attempts | 5 minutes per username |
| `/auth/login` | 10 attempts | 5 minutes per IP |
| `/auth/recovery` | 3 attempts | 24 hours per username |
| `/auth/masterkey` | 3 attempts | 24 hours per username |
| `/chat/send` | 20 requests | 1 minute per user |
| All other endpoints | 100 requests | 1 minute per user |

**Rate Limit Headers:**
```http
HTTP/1.1 429 Too Many Requests
Retry-After: 300

{
  "error": "Too many login attempts. Please wait 5 minutes.",
  "retry_after": 300
}
```

---

## **Security Best Practices**

1. **Always use HTTPS in production**
2. **Include CSRF token in all POST/PUT/DELETE requests**
3. **Validate session timeout client-side**
4. **Handle 401 responses by redirecting to login**
5. **Never log API keys or passwords**
6. **Use secure random for client-side tokens**
7. **Implement retry logic with exponential backoff for 429 responses**
8. **Store session cookies as HttpOnly, Secure, SameSite=Strict**
9. **Regenerate session ID on login**
10. **Validate all user input server-side**

---

## **CORS**

**Allowed Origins (Development):**
- `http://localhost:8001`
- `http://127.0.0.1:8001`
- `http://localhost:8080` (legacy)
- `http://127.0.0.1:8080` (legacy)

**Production:** Configure `ALLOWED_HOSTS` in `.env`

---

## **Webhooks** (Coming in v1.1)

Future support for:
- Authentication events
- Security alerts
- Task completion notifications
- System health changes

---

**Last Updated:** 2026-02-25  
**API Version:** 1.0.0  
**Support:** security@demewebsolutions.com
````

---

### **📚 Step 5: Create `docs/SECURITY.md`**

````markdown name=docs/SECURITY.md
# TruAi Security Model & Architecture

**Version:** 1.0.0  
**Last Updated:** 2026-02-25  
**Classification:** Public (Architecture Overview)

---

## **Table of Contents**

1. [Security Philosophy](#security-philosophy)
2. [Threat Model](#threat-model)
3. [Authentication Architecture](#authentication-architecture)
4. [Encryption Standards](#encryption-standards)
5. [ROMA Trust Protocol](#roma-trust-protocol)
6. [Session Management](#session-management)
7. [CSRF Protection](#csrf-protection)
8. [Input Validation](#input-validation)
9. [Rate Limiting](#rate-limiting)
10. [Audit Logging](#audit-logging)
11. [Vulnerability Disclosure](#vulnerability-disclosure)
12. [Security Checklist](#security-checklist)

---

## **Security Philosophy**

TruAi is designed with **self-sovereign security** as its core principle:

### **Core Tenets**

1. **Local-First Control**
   - All sensitive data stored locally
   - No cloud dependencies for authentication
   - OS-level biometric integration (never transmitted)

2. **Zero-Trust by Default**
   - Every request authenticated and validated
   - Session timeout enforcement (1 hour absolute, 30 min idle)
   - Device fingerprinting for anomaly detection

3. **Defense in Depth**
   - Multiple authentication layers (UBSAS 4-tier)
   - Recovery requires 4-factor verification (LSRP)
   - Encryption at rest and in transit

4. **Transparent Security**
   - ROMA status indicator on every page
   - Audit logging for all security events
   - Clear error messages (no security through obscurity)

---

## **Threat Model**

### **Attack Vectors Addressed**

| Threat | Mitigation |
|--------|------------|
| **Password Brute Force** | Rate limiting (5 attempts/5min), Argon2id hashing (64MB memory-hard) |
| **Session Hijacking** | HttpOnly/Secure/SameSite cookies, session regeneration on login |
| **CSRF** | Token validation on all state-changing requests, token rotation |
| **XSS** | Input sanitization, HTML entity encoding, Content-Security-Policy headers |
| **SQL Injection** | Parameterized queries (PDO prepared statements), input validation |
| **Path Traversal** | Path sanitization, whitelist validation |
| **Credential Theft** | Keychain storage (macOS), encrypted credentials, biometric auth |
| **Replay Attacks** | Timestamp validation, CSRF tokens, session timeout |
| **Man-in-the-Middle** | HTTPS enforcement (production), certificate pinning (Electron) |
| **Privilege Escalation** | Role-based access control (RBAC), audit logging |

### **Attack Vectors NOT Addressed**

| Threat | Rationale |
|--------|-----------|
| **Physical Access** | Assumes trusted device (lock your Mac) |
| **Keylogger Malware** | OS-level security responsibility |
| **Supply Chain Attacks** | Trust in OS vendor (Apple/Linux) and package managers |
| **Coercion** | Cannot protect against forced disclosure |

### **Assumptions**

1. ✅ User's device is trusted and not compromised
2. ✅ OS security features (Keychain, Secure Enclave) are reliable
3. ✅ Network is hostile (hence HTTPS enforcement)
4. ✅ User will follow security best practices (lock device, update OS)

---

## **Authentication Architecture**

### **UBSAS (Unified Biometric Sovereign Auth System)**

4-tier authentication hierarchy:

#### **Tier 1: OS Biometric (Recommended)** 👆

**Features:**
- Touch ID or Face ID on macOS 12+
- fprintd on Linux (experimental)
- Credentials stored in OS Keychain (never transmitted)
- Native messaging host for browser integration

**Security:**
- Biometric data stored in Secure Enclave (hardware-isolated)
- TruAi never receives biometric data (only yes/no from OS)
- Requires device unlock (biometric + device password)

**Implementation:**
```javascript
// Client-side detection
const response = await fetch('/TruAi/api/v1/auth/biometric', {
  method: 'POST',
  credentials: 'include',
  body: JSON.stringify({ app: 'truai' })
});
```

---

#### **Tier 2: Auto-Fill (Convenient)** 🔑

**Features:**
- macOS Keychain Auto-Fill
- Linux libsecret integration
- Browser autofill (stored in browser's credential manager)

**Security:**
- Requires device unlock
- Passwords encrypted by OS
- TruAi sees plaintext password (user typed or autofilled)

---

#### **Tier 3: Manual Entry (Always Available)** ⌨️

**Features:**
- Username + password form
- Argon2id hashing (64MB memory-hard, 4 iterations, 1 parallelism)
- Rate limiting (5 attempts per 5 minutes)

**Security:**
```php
// Backend hashing (backend/auth.php)
$passwordHash = password_hash(
    $password, 
    PASSWORD_ARGON2ID, 
    [
        'memory_cost' => 65536, // 64MB
        'time_cost' => 4,
        'threads' => 1
    ]
);
```

---

#### **Tier 4: Master Key (Emergency Recovery)** 🔐

**Features:**
- 64-character hex key (256 bits)
- Generated on first login (Settings → Security)
- Stored offline (printed or password manager)
- Rate limited (3 attempts per 24 hours)

**Security:**
- SHA-256 hashed before storage
- Generates 10-minute temporary password
- Forces password change on next login

**Usage:**
```http
POST /TruAi/api/v1/auth/masterkey
Content-Type: application/json

{
  "username": "admin",
  "master_key": "a1b2c3d4e5f6..." // 64 chars
}
```

---

### **LSRP (Local Sovereign Recovery Protocol)**

4-factor authentication for password recovery:

#### **Factor 1: Local Access** 🏠

- Request must originate from `localhost` or trusted VPN
- IP validation: `$_SERVER['REMOTE_ADDR'] === '127.0.0.1'`

#### **Factor 2: ROMA Trust** 🛡️

- ROMA status must be `VERIFIED`
- Encryption keys must exist and be valid
- Session must be active

#### **Factor 3: OS Administrator** 💻

- Requires OS admin credentials (macOS or Linux)
- Validated via `sudo -nv` (non-interactive validation)
- Prevents unauthorized recovery from stolen laptop

#### **Factor 4: Device Fingerprint** 🖥️

- Browser + OS + hardware fingerprint
- Warning if device not recognized (not blocking)
- Logged for audit

**Recovery Flow:**

```
User → Enter Username → Enter OS Admin Creds → System validates:
  ✓ Local access (localhost)
  ✓ ROMA trust (VERIFIED)
  ✓ OS admin (sudo valid)
  ⚠ Device fingerprint (mismatch warning)
→ Temporary password (10 minutes)
→ Force password change on login
```

---

## **Encryption Standards**

### **Password Hashing: Argon2id**

**Why Argon2id?**
- Winner of Password Hashing Competition (2015)
- Memory-hard (resistant to GPU/ASIC attacks)
- Hybrid mode (data-dependent + data-independent mixing)

**Parameters:**
```php
PASSWORD_ARGON2ID:
  memory_cost: 65536 (64MB) // 16x more than Bcrypt's 4KB
  time_cost: 4 iterations
  threads: 1 (no parallelism)
```

**Attack Resistance:**
- **Bcrypt:** ~4KB memory, GPU-friendly
- **Argon2id:** ~64MB memory, GPU-hostile (16,000x more memory)

**Time to hash:** ~150ms (intentionally slow to prevent brute force)

---

### **Encryption at Rest**

**Database Encryption:**
```
SQLite database: database/truai.db (chmod 600)
  ├── Passwords: Argon2id hashed (never reversible)
  ├── API Keys: AES-256-GCM encrypted
  ├── Session Data: AES-256-GCM encrypted
  └── Sensitive Settings: AES-256-GCM encrypted
```

**Key Storage:**
```
database/keys/ (chmod 700)
  ├── private_key.pem (RSA-2048, chmod 600)
  └── public_key.pem (RSA-2048, chmod 644)
```

---

### **Encryption in Transit**

**Development:**
- HTTP allowed (localhost only)
- No production deployment without HTTPS

**Production:**
- HTTPS enforced (Nginx/Apache config)
- TLS 1.3 minimum
- Certificate from Let's Encrypt (free) or commercial CA

**Electron:**
- Certificate pinning for backend API
- WebSocket encryption (WSS)

---

## **ROMA Trust Protocol**

**ROMA** = **R**eal-time **O**perational **M**onitoring & **A**uthentication

### **Purpose**

Single trust indicator that validates:
1. ✅ Encryption keys exist and are valid
2. ✅ Session is active and valid
3. ✅ Workspace (database) is writable
4. ✅ Local-only access (no remote requests)

### **Implementation**

**Endpoint:** `GET /TruAi/api/v1/security/roma`

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

**UI Indicator:**
```html
<!-- Bottom of every page -->
<div id="romaIndicator" class="roma-indicator">
  Roma • Portal protected
</div>
```

**JavaScript:**
```javascript
fetch('/TruAi/api/v1/security/roma')
  .then(r => r.json())
  .then(data => {
    const el = document.getElementById('romaIndicator');
    if (data.trust_state === 'VERIFIED') {
      el.textContent = 'Roma • Portal protected • Monitor active';
      el.classList.add('confirmed'); // Green color
    } else {
      el.textContent = 'Roma • Unverified';
      el.classList.remove('confirmed'); // Red color
    }
  });
```

---

## **Session Management**

### **Session Configuration**

```php
// backend/router.php
ini_set('session.cookie_httponly', '1'); // No JavaScript access
ini_set('session.cookie_secure', TRUAI_DEPLOYMENT === 'production' ? '1' : '0'); // HTTPS only in production
ini_set('session.cookie_samesite', 'Strict'); // CSRF protection
ini_set('session.use_strict_mode', '1'); // No session ID guessing
ini_set('session.use_only_cookies', '1'); // No URL session IDs
ini_set('session.gc_maxlifetime', 3600); // 1 hour
session_name('TRUAI_SESSION');
```

### **Timeout Enforcement**

**Absolute Timeout:** 1 hour (from login)
```php
if (time() - $_SESSION['login_time'] > 3600) {
    session_unset();
    session_destroy();
    http_response_code(401);
    exit;
}
```

**Idle Timeout:** 30 minutes (since last activity)
```php
if (time() - $_SESSION['last_activity'] > 1800) {
    session_unset();
    session_destroy();
    http_response_code(401);
    exit;
}
$_SESSION['last_activity'] = time();
```

### **Session Regeneration**

```php
// On login (backend/auth.php)
session_regenerate_id(true); // Delete old session file
$_SESSION['user_id'] = $user['id'];
$_SESSION['login_time'] = time();
$_SESSION['last_activity'] = time();
$_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
$_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
```

---

## **CSRF Protection**

**Token Generation:**
```php
// backend/csrf.php
class CSRFProtection {
    public static function generateToken(): string {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // 64 chars
        }
        return $_SESSION['csrf_token'];
    }
}
```

**Token Validation:**
```php
// backend/router.php
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
    
    if (!$token || !CSRFProtection::validateToken($token)) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF validation failed']);
        exit;
    }
}
```

**Client-Side:**
```javascript
// Get token
const response = await fetch('/TruAi/api/v1/auth/csrf-token');
const data = await response.json();
const csrfToken = data.csrf_token;

// Send with request
fetch('/TruAi/api/v1/settings/save', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-CSRF-Token': csrfToken
  },
  body: JSON.stringify(settings)
});
```

---

## **Input Validation**

**Validation Layer:** `backend/validator.php`

### **Username Validation**

```php
Validator::username($username)
  ✓ 3-32 characters
  ✓ Alphanumeric + hyphens + underscores only
  ✗ Spaces, special characters
```

### **Password Validation**

```php
Validator::password($password)
  ✓ 8+ characters
  ✓ At least 1 uppercase letter
  ✓ At least 1 lowercase letter
  ✓ At least 1 number
  ✓ At least 1 special character
```

### **Path Validation**

```php
Validator::sanitizeFilePath($path)
  ✗ Remove ".." (directory traversal)
  ✗ Remove "~" (home directory)
  ✓ Allow alphanumeric, /, -, _, .
```

### **HTML Sanitization**

```php
Validator::sanitizeHTML($html)
  ✗ Escape <, >, &, ", '
  ✓ Use htmlspecialchars(ENT_QUOTES | ENT_HTML5)
```

---

## **Rate Limiting**

**Implementation:** `backend/router.php`

### **Rate Limit Tracker**

```php
private function checkRateLimit(string $key, int $maxAttempts, int $windowSeconds): bool {
    $cacheKey = 'ratelimit_' . hash('sha256', $key);
    $attempts = $_SESSION[$cacheKey] ?? ['count' => 0, 'window_start' => time()];
    
    // Reset if window expired
    if (time() - $attempts['window_start'] > $windowSeconds) {
        $attempts = ['count' => 0, 'window_start' => time()];
    }
    
    $attempts['count']++;
    $_SESSION[$cacheKey] = $attempts;
    
    return $attempts['count'] <= $maxAttempts;
}
```

### **Rate Limits**

| Endpoint | Limit | Window | Key |
|----------|-------|--------|-----|
| `/auth/login` | 5 | 5 min | `login_{username}` |
| `/auth/login` | 10 | 5 min | `login_ip_{ip}` |
| `/auth/recovery` | 3 | 24 hours | `recovery_{username}` |
| `/auth/masterkey` | 3 | 24 hours | `masterkey_{username}` |

---

## **Audit Logging**

**Database Table:** `audit_logs`

```sql
CREATE TABLE audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    event TEXT NOT NULL,
    actor TEXT,
    details TEXT, -- JSON
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

**Logged Events:**
- ✅ Login success/failure
- ✅ Logout
- ✅ Password change
- ✅ Biometric enrollment
- ✅ Recovery attempt (LSRP/Master Key)
- ✅ Settings change
- ✅ CSRF validation failure
- ✅ Rate limit exceeded
- ✅ ROMA trust state change

**Example:**
```php
// backend/auth.php
$db->execute(
    "INSERT INTO audit_logs (user_id, event, actor, details) VALUES (:user_id, :event, :actor, :details)",
    [
        ':user_id' => $userId,
        ':event' => 'LOGIN_SUCCESS',
        ':actor' => $username,
        ':details' => json_encode([
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'method' => 'biometric'
        ])
    ]
);
```

---

## **Vulnerability Disclosure**

### **Reporting Security Issues**

**Contact:** security@demewebsolutions.com

**DO NOT:**
- ❌ Open public GitHub issues for security vulnerabilities
- ❌ Disclose vulnerabilities publicly before patch is available

**DO:**
- ✅ Email security@demewebsolutions.com with:
  - Description of vulnerability
  - Steps to reproduce
  - Proof of concept (if applicable)
  - Your contact information
- ✅ Wait for acknowledgment (within 48 hours)
- ✅ Coordinate public disclosure after patch release

**Response Timeline:**
- **Critical:** Patch within 48 hours
- **High:** Patch within 7 days
- **Medium:** Patch within 30 days
- **Low:** Patch in next release

**Bug Bounty:** Not available (self-funded project)

---

## **Security Checklist**

### **Initial Setup**

- [ ] Change default admin password
- [ ] Delete `database/.initial_credentials`
- [ ] Set database permissions: `chmod 600 database/truai.db`
- [ ] Set encryption key permissions: `chmod 700 database/keys/`
- [ ] Generate master recovery key (Settings → Security)
- [ ] Store master recovery key offline (printed or password manager)

### **Configuration**

- [ ] `.env` file created (not in repository)
- [ ] API keys set (if using AI features)
- [ ] `TRUAI_DEPLOYMENT` set to `production` (if production)
- [ ] Reviewed `backend/config.php` CORS settings
- [ ] HTTPS enforced (if production)

### **Network**

- [ ] Firewall configured (port 8001 localhost-only for development)
- [ ] HTTPS certificate valid (Let's Encrypt or commercial)
- [ ] Nginx/Apache security headers configured
- [ ] Rate limiting tested

### **Monitoring**

- [ ] Checked startup logs: `logs/truai.log`
- [ ] Verified ROMA status: http://127.0.0.1:8001/TruAi/api/v1/security/roma
- [ ] Tested login flow (manual password entry)
- [ ] Confirmed session timeout works
- [ ] Reviewed audit logs: `SELECT * FROM audit_logs ORDER BY timestamp DESC LIMIT 100;`

### **Backup**

- [ ] Database backup script configured: `scripts/backup_database.sh`
- [ ] Backup directory exists: `~/.truai_backups/`
- [ ] Cron job or systemd timer scheduled
- [ ] Tested restore procedure

### **Weekly Review**

- [ ] Check audit logs for suspicious activity
- [ ] Review ROMA trust status
- [ ] Verify backup integrity
- [ ] Update OS security patches
- [ ] Review rate limit logs

---

## **Compliance**

TruAi is designed for **self-hosted deployment** and does not store user data on third-party servers. Compliance requirements:

### **GDPR (EU)**
- ✅ Data stored locally (not transmitted to third parties)
- ✅ User can export/delete all data
- ✅ Audit logs for data access

### **CCPA (California)**
- ✅ No third-party data sharing
- ✅ User controls all data

### **SOC 2 (Optional)**
- ⚠️ Requires formal security audit (not included)
- ✅ Architecture supports SOC 2 requirements

---

## **Future Enhancements**

Planned for v1.1+:
- [ ] Two-factor authentication (TOTP)
- [ ] Hardware security key support (YubiKey, WebAuthn)
- [ ] Encrypted database backups
- [ ] Anomaly detection (ML-based)
- [ ] Intrusion detection system (IDS) integration
- [ ] Security audit trail export (CSV/JSON)

---

**Last Updated:** 2026-02-25  
**Version:** 1.0.0  
**Maintained By:** My Deme, LLC  
**Contact:** security@demewebsolutions.com
````

---

### **📚 Step 6: Create `docs/DEPLOYMENT.md`**

**Due to length constraints, I'll provide the structure. Would you like me to generate the full `DEPLOYMENT.md` content?**

**Outline:**
```markdown
# TruAi Production Deployment Guide

## Table of Contents
1. Prerequisites
2. Plesk Deployment
3. Nginx Deployment
4. Apache Deployment
5. Environment Configuration
6. SSL/TLS Certificate Setup
7. Firewall Configuration
8. Database Migration
9. Backup Automation
10. Health Monitoring
11. Zero-Downtime Deployment
12. Rollback Procedure
13. Troubleshooting
```

---

## **PHASE 4: PULL REQUEST CREATION**

### **Step 7: Create Pull Request Template**

**Create file: `.github/pull_request_template.md`**

````markdown name=.github/pull_request_template.md
# Repository Consolidation Pull Request

## **Changes Summary**

### **Phase 1: Documentation Archival** 📦
- [ ] Archived milestone documents (6 files → `archive/milestones/`)
- [ ] Archived update notes (7 files → `archive/updates/`)
- [ ] Archived fix documentation (5 files → `archive/fixes/`)
- [ ] Archived superseded docs (8 files → `archive/superseded/`)

### **Phase 2: File Relocation** 📁
- [ ] Moved test HTML files to `dev/` (4 files)
- [ ] Moved backend scripts to `scripts/` (3 files)
- [ ] Removed duplicate HTML files from root (if any)
- [ ] Moved large design files to `design/` (5 files)

### **Phase 3: Directory Cleanup** 🗑️
- [ ] Archived `TruAi-Git/` directory
- [ ] Evaluated `TruAi-Update/` directory
- [ ] Removed temporary/backup files

### **Phase 4: Documentation Creation** 📚
- [ ] Created `docs/API.md` (comprehensive API reference)
- [ ] Created `docs/SECURITY.md` (security model documentation)
- [ ] Created `docs/DEPLOYMENT.md` (production deployment guide)
- [ ] Enhanced `README.md` with quickstart + links

---

## **Impact Assessment**

### **Before:**
- **Root files:** ~70+ files
- **Documentation:** Scattered across root, hard to navigate
- **Duplicates:** Multiple HTML files in root + `public/TruAi/`

### **After:**
- **Root files:** ~15 files (78% reduction)
- **Documentation:** Organized in `docs/` directory
- **Duplicates:** Removed

---

## **Testing Checklist**

- [ ] All tests pass: `./scripts/run-all-tests.sh`
- [ ] Health endpoint returns 200: `curl http://127.0.0.1:8001/TruAi/api/v1/health`
- [ ] Login flow works (manual password)
- [ ] ROMA status indicator displays correctly
- [ ] No broken links in documentation
- [ ] No sensitive files committed

---

## **Deployment Notes**

- **Breaking Changes:** None
- **Database Migration Required:** No
- **Configuration Changes:** None
- **Rollback Procedure:** Revert PR commit

---

## **Reviewer Notes**

This PR focuses on **repository organization only**. No functional code changes.

**Review Focus:**
1. Verify all archived files are in correct `archive/` subdirectories
2. Confirm no duplicate HTML files remain in root
3. Check documentation links work correctly
4. Verify no functional code was accidentally moved/deleted

---

## **Related Issues**

Closes # (if applicable)

---

## **Screenshots** (if applicable)

Before/After directory structure comparison:

```
Before:
.
├── 46 markdown files
├── 12 HTML files (duplicates)
├── 8 shell scripts
├── 4 PHP scripts
├── ...

After:
.
├── README.md
├── SETUP.md
├── ARCHITECTURE.md
├── CHANGELOG.md
├── docs/
│   ├── API.md
│   ├── SECURITY.md
│   ├── DEPLOYMENT.md
│   └── ...
├── archive/
│   ├── milestones/
│   ├── updates/
│   ├── fixes/
│   └── superseded/
└── ...
```

---

**Merge Checklist:**
- [ ] Branch up to date with `main`
- [ ] All tests pass
- [ ] No merge conflicts
- [ ] Documentation reviewed
- [ ] Approved by reviewer

**Merge Strategy:** Squash and merge (to keep git history clean)
````

---

## **IMPLEMENTATION TIMELINE**

| Phase | Task | Estimated Time |
|-------|------|----------------|
| **Day 1** | Run `scripts/consolidate-repository.sh` | 30 minutes |
| | Create `docs/API.md` | 4 hours |
| | Create `docs/SECURITY.md` | 3 hours |
| | Create `docs/DEPLOYMENT.md` | 2 hours |
| **Day 2** | Test all changes | 2 hours |
| | Create Pull Request | 1 hour |
| | Code review + revisions | 2 hours |
| | Merge PR | 15 minutes |
| **Total** | | **~15 hours** |

---

## **NEXT STEPS**

1. **Review this plan** and confirm approach
2. **Run consolidation script:**
   ```bash
   ./scripts/consolidate-repository.sh
   ```
3. **Review changes:**
   ```bash
   git status
   git diff --cached --stat
   ```
4. **Commit and push:**
   ```bash
   git commit -m "chore: consolidate repository structure

   - Archive milestone, update, fix, and superseded docs
   - Move test files to dev/ directory
   - Relocate backend scripts to scripts/
   - Remove duplicate HTML files from root
   - Move large design files to design/
   - Archive TruAi-Git backup directory
   "
   git push origin consolidation/YYYYMMDD
   ```
5. **Create Pull Request** on GitHub using template

---
