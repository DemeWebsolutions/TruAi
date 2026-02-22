# TruAi API Reference

## Base URL

```
http://127.0.0.1:8001/TruAi/api/v1
```

Production: `https://yourdomain.com/TruAi/api/v1`

## Authentication

All protected endpoints require:
- Valid session cookie (`TRUAI_SESSION`)
- CSRF token header `X-CSRF-Token` (for POST/PUT/DELETE/PATCH)

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

---

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
  "error": "Invalid credentials"
}
```

**Rate Limit:** 5 attempts per 5 minutes per username; 10 attempts per 5 minutes per IP.

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

**Rate Limit:** 3 attempts per 24 hours per username.

#### LSRP Recovery
```http
POST /recovery/initiate
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

#### Auth Status
```http
GET /auth/status
```

**Response:**
```json
{
  "authenticated": true,
  "username": "admin",
  "role": "admin"
}
```

#### Change Password
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
- `VERIFIED` – All checks passed
- `UNVERIFIED` – One or more checks failed
- `BLOCKED` – Suspicion threshold exceeded (5 failures in 5 minutes)

---

### Chat

#### Send Message
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
      "created_at": "2026-02-20T10:00:00Z",
      "updated_at": "2026-02-21T11:30:00Z"
    }
  ]
}
```

#### Get Conversation Details
```http
GET /chat/conversation/123
```

**Response:**
```json
{
  "id": 123,
  "title": "Python Recursion Discussion",
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
DELETE /chat/conversation/123
X-CSRF-Token: a1b2c3d4...
```

**Response:**
```json
{
  "success": true
}
```

---

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
  "activity": [],
  "alerts": [],
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

---

### Settings

#### Get Settings
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

#### Save Settings
```http
POST /settings
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

---

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
    "roma": "VERIFIED"
  }
}
```

---

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
| 503 | Service Unavailable |

## Rate Limiting

| Endpoint | Limit |
|----------|-------|
| `POST /auth/login` | 5 per 5 minutes per username; 10 per 5 minutes per IP |
| `POST /recovery/initiate` | 3 per 24 hours per username |
| `POST /auth/masterkey` | 3 per 24 hours per username |

**Rate Limit Response:**
```http
HTTP/1.1 429 Too Many Requests

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

Production: Configure `ALLOWED_HOSTS` in `.env`.

## Security Best Practices

1. **Always use HTTPS in production**
2. **Include CSRF token in all POST/PUT/DELETE/PATCH requests**
3. **Validate session timeout client-side**
4. **Handle 401 responses by redirecting to login**
5. **Never log API keys or passwords**
6. **Implement retry logic with exponential backoff for 429 responses**
