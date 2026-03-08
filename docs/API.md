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
9. [CORS](#cors)
10. [Security Best Practices](#security-best-practices)

---

## **Authentication**

All protected endpoints require:
- Valid session cookie (`TRUAI_SESSION`)
- CSRF token header `X-CSRF-Token` (for POST/PUT/DELETE/PATCH)

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

**Rate Limit:** 5 attempts per 5 minutes per username; 10 attempts per 5 minutes per IP.

---

### **Biometric Login**

```http
POST /auth/biometric
Content-Type: application/json

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

```http
POST /auth/masterkey
Content-Type: application/json

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

**Rate Limit:** 3 attempts per 24 hours per username.

---

### **LSRP Recovery**

```http
POST /auth/recovery
Content-Type: application/json

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

**Rate Limit:** 3 attempts per 24 hours per username.

---

### **Logout**

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

---

### **Check Authentication Status**

```http
GET /auth/status
```

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

### **Change Password**

```http
POST /auth/password/change
X-CSRF-Token: a1b2c3d4...
Content-Type: application/json

{
  "current_password": "OldPassword123!",
  "new_password": "NewPassword456!"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Password changed successfully"
}
```

---

## **ROMA Security**

### **Get ROMA Status**

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
- `VERIFIED` — All security checks passed
- `UNVERIFIED` — One or more checks failed
- `BLOCKED` — Suspicion threshold exceeded (5 failures in 5 minutes)

---

## **Chat**

### **Send Message**

```http
POST /chat/message
X-CSRF-Token: a1b2c3d4...
Content-Type: application/json

{
  "conversation_id": 123,
  "message": "Explain recursion in Python",
  "model": "auto"
}
```

**Parameters:**
- `conversation_id` (optional): Existing conversation ID, or omit for new conversation
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

**Rate Limit:** 20 requests per minute per user.

---

### **Get Conversations**

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

---

### **Get Conversation Details**

```http
GET /chat/conversation/{id}
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
      "content": "Recursion is a programming technique...",
      "model": "gpt-4o",
      "created_at": "2026-02-20T10:00:05Z"
    }
  ]
}
```

---

### **Delete Conversation**

```http
DELETE /chat/conversation/{id}
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

```http
GET /settings
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

---

### **Save Settings**

```http
POST /settings
X-CSRF-Token: a1b2c3d4...
Content-Type: application/json

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

```http
POST /settings/test-keys
X-CSRF-Token: a1b2c3d4...
Content-Type: application/json

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

---

### **Execute Automation**

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

---

## **Health**

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
- `ok` — All checks passed
- `degraded` — Some checks failed but system operational
- `error` — Critical failure

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
| `POST /auth/login` | 5 attempts | 5 minutes per username |
| `POST /auth/login` | 10 attempts | 5 minutes per IP |
| `POST /auth/recovery` | 3 attempts | 24 hours per username |
| `POST /auth/masterkey` | 3 attempts | 24 hours per username |
| `POST /chat/message` | 20 requests | 1 minute per user |
| All other endpoints | 100 requests | 1 minute per user |

**Rate Limit Response:**
```http
HTTP/1.1 429 Too Many Requests
Retry-After: 300

{
  "error": "Too many login attempts. Please wait 5 minutes.",
  "retry_after": 300
}
```

---

## **CORS**

**Allowed Origins (Development):**
- `http://localhost:8001`
- `http://127.0.0.1:8001`
- `http://localhost:8080` (legacy)
- `http://127.0.0.1:8080` (legacy)

**Production:** Configure `ALLOWED_HOSTS` in `.env`.

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

**Last Updated:** 2026-02-25  
**API Version:** 1.0.0  
**Support:** security@demewebsolutions.com
