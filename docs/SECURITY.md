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
11. [Incident Response](#incident-response)
12. [Vulnerability Disclosure](#vulnerability-disclosure)
13. [Security Checklist](#security-checklist)
14. [Compliance](#compliance)

---

## **Security Philosophy**

TruAi is designed with **self-sovereign security** as its core principle:

1. **Local-First Control** — All sensitive data stored locally; no cloud dependencies for authentication
2. **Zero-Trust by Default** — Every request authenticated and validated; session timeout enforcement
3. **Defense in Depth** — Multiple authentication layers (UBSAS 4-tier); encryption at rest and in transit
4. **Transparent Security** — ROMA status indicator on every page; audit logging for all security events

---

## **Threat Model**

### **Attack Vectors Addressed**

| Threat | Mitigation |
|--------|------------|
| **Password Brute Force** | Rate limiting (5 attempts/5min), Argon2id hashing (64MB memory-hard) |
| **Session Hijacking** | HttpOnly/Secure/SameSite cookies, session regeneration on login |
| **CSRF** | Token validation on all state-changing requests, token rotation |
| **XSS** | Input sanitization, HTML entity encoding |
| **SQL Injection** | Parameterized queries (PDO prepared statements), input validation |
| **Path Traversal** | Path sanitization, whitelist validation |
| **Credential Theft** | Keychain storage (macOS), encrypted credentials, biometric auth |
| **Replay Attacks** | Timestamp validation, CSRF tokens, session timeout |
| **Man-in-the-Middle** | HTTPS enforcement (production) |
| **Privilege Escalation** | Role-based access control (RBAC), audit logging |

### **Attack Vectors NOT Addressed (Out of Scope)**

| Threat | Rationale |
|--------|-----------|
| **Physical Access** | Assumes trusted device |
| **Keylogger Malware** | OS-level security responsibility |
| **Supply Chain Attacks** | Trust in OS vendor and package managers |
| **Coercion** | Cannot protect against forced disclosure |

---

## **Authentication Architecture**

### **UBSAS (Unified Biometric Sovereign Auth System)**

4-tier authentication hierarchy:

#### **Tier 1: OS Biometric (Recommended)** 👆

- Touch ID or Face ID on macOS 12+
- fprintd on Linux (experimental)
- Credentials stored in OS Keychain (never transmitted)
- Native messaging host for browser integration
- Biometric data stored in Secure Enclave (hardware-isolated)

#### **Tier 2: Auto-Fill (Convenient)** 🔑

- macOS Keychain Auto-Fill
- Linux libsecret integration
- Requires device unlock

#### **Tier 3: Manual Entry (Always Available)** ⌨️

- Username + password form
- Argon2id hashing (64MB memory-hard, 4 iterations, 1 parallelism)
- Rate limiting (5 attempts per 5 minutes)

```php
$passwordHash = password_hash(
    $password,
    PASSWORD_ARGON2ID,
    ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]
);
```

#### **Tier 4: Master Key (Emergency Recovery)** 🔐

- 64-character hex key (256 bits)
- Generated on first login (Settings → Security)
- Stored offline (printed or password manager)
- Rate limited (3 attempts per 24 hours)
- SHA-256 hashed before storage
- Generates 10-minute temporary password

---

### **LSRP (Local Sovereign Recovery Protocol)**

4-factor authentication for password recovery:

1. **Local Access** — Request must originate from `localhost` or trusted VPN
2. **ROMA Trust** — ROMA status must be `VERIFIED`
3. **OS Administrator** — Valid OS admin credentials (macOS/Linux)
4. **Device Fingerprint** — Browser + OS + hardware fingerprint (warning if mismatch)

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

| Use | Algorithm | Parameters |
|-----|-----------|------------|
| Password hashing | Argon2id | 64MB memory, 4 iterations, 1 thread |
| RSA encryption | RSA-OAEP-SHA256 | 2048-bit |
| Symmetric encryption | AES-256-GCM | 256-bit |
| Session tokens | CSPRNG (`random_bytes`) | 256-bit |
| CSRF tokens | CSPRNG (`random_bytes`) | 256-bit |

### **Why Argon2id?**

Argon2id won the Password Hashing Competition (2015) and is recommended by OWASP. With `memory_cost = 65536` (64MB), GPU/ASIC attacks are impractical — 16,000× more memory required than bcrypt.

### **Key Storage**

```
database/keys/ (chmod 700)
  ├── private_key.pem  (RSA-2048, chmod 600)
  └── public_key.pem   (RSA-2048, chmod 644)
```

---

## **ROMA Trust Protocol**

**ROMA** = **R**eal-time **O**perational **M**onitoring & **A**uthentication

ROMA validates on every sensitive operation:

1. ✅ Encryption keys exist and are valid
2. ✅ Session is active and valid
3. ✅ Workspace (database) is writable
4. ✅ Local-only access (no remote requests)

**Trust States:**
- `VERIFIED` — All checks passed
- `UNVERIFIED` — One or more checks failed
- `BLOCKED` — Suspicion threshold exceeded (5 failures in 5 minutes)

**ROMA UI Indicator:**
```javascript
fetch('/TruAi/api/v1/security/roma')
  .then(r => r.json())
  .then(data => {
    const el = document.getElementById('romaIndicator');
    el.textContent = data.trust_state === 'VERIFIED'
      ? 'Roma • Portal protected • Monitor active'
      : 'Roma • Unverified';
  });
```

---

## **Session Management**

| Parameter | Value | Purpose |
|-----------|-------|---------|
| `session.cookie_httponly` | 1 | Prevent JavaScript access |
| `session.cookie_secure` | 1 (production) | HTTPS only |
| `session.cookie_samesite` | Strict | CSRF mitigation |
| Session name | `TRUAI_SESSION` | Custom name |
| Absolute timeout | 3600s (1 hour) | Limits stolen session exposure |
| Idle timeout | 1800s (30 minutes) | Clears inactive sessions |
| ID regeneration | On every login | Prevents session fixation |

```php
// Session regeneration on login (backend/auth.php)
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['login_time'] = time();
$_SESSION['last_activity'] = time();
```

---

## **CSRF Protection**

All `POST`, `PUT`, `DELETE` requests to protected endpoints require a valid CSRF token.

**Token flow:**
1. Client fetches `GET /api/v1/auth/csrf-token` after login
2. Token stored in PHP session (`$_SESSION['csrf_token']`)
3. Client includes token in `X-CSRF-Token` header
4. Server validates with `hash_equals()` (timing-safe comparison)
5. Token rotated after sensitive operations

```javascript
// Client-side usage
const { csrf_token } = await fetch('/TruAi/api/v1/auth/csrf-token').then(r => r.json());

fetch('/TruAi/api/v1/settings/save', {
  method: 'POST',
  headers: { 'X-CSRF-Token': csrf_token, 'Content-Type': 'application/json' },
  body: JSON.stringify(settings)
});
```

---

## **Input Validation**

All user-supplied input is validated through `backend/validator.php`:

| Input | Validation |
|-------|-----------|
| Username | 3-32 chars, `[a-zA-Z0-9_-]` only |
| Password | 8+ chars, upper/lower/digit/special required |
| File paths | Directory traversal stripped (`..`, `~`) |
| HTML output | `htmlspecialchars(ENT_QUOTES \| ENT_HTML5)` |
| SQL LIKE | `%` and `_` escaped |
| Conversation IDs | Numeric only |

---

## **Rate Limiting**

| Endpoint | Limit | Window |
|----------|-------|--------|
| Login (per username) | 5 attempts | 5 minutes |
| Login (per IP) | 10 attempts | 5 minutes |
| Recovery (per username) | 3 attempts | 24 hours |
| Master key (per username) | 3 attempts | 24 hours |

Rate limit counters are stored in the session and reset on successful authentication.

---

## **Audit Logging**

All authentication and security events are logged to the `audit_logs` table:

| Event | Trigger |
|-------|---------|
| `USER_LOGIN` | Successful login |
| `USER_LOGOUT` | Session logout |
| `PASSWORD_CHANGE` | Password successfully changed |
| `LOGIN_FAILED` | Failed authentication attempt |
| `ROMA_SUSPICION_BLOCKED` | Too many failures |
| `ROMA_VALIDATION_FAILURE` | ROMA trust check failed |
| `RECOVERY_ATTEMPT` | LSRP or master key recovery |
| `SETTINGS_CHANGE` | Settings modified |

---

## **Incident Response**

### **Suspected Brute Force**
1. Check `audit_logs` for repeated `LOGIN_FAILED` events
2. Review ROMA status: `curl http://127.0.0.1:8001/TruAi/api/v1/security/roma`
3. If `trust_state: BLOCKED`, restart server to reset suspicion score
4. Consider blocking source IP at firewall level

### **Compromised Account**
1. Reset password: `php scripts/reset_admin_password.php admin`
2. Review `audit_logs` for suspicious activity
3. Rotate encryption keys if needed (delete `database/keys/` and re-run setup)

### **Data Breach**
1. Rotate all encryption keys: delete `database/keys/` and run `php scripts/setup_database.php`
2. Force password reset for all users
3. Rotate API keys (OpenAI, Anthropic)
4. Review logs for data exfiltration indicators

---

## **Vulnerability Disclosure**

**Contact:** security@demewebsolutions.com

**Do NOT** open public GitHub issues for security vulnerabilities.

**Response Timeline:**
- **Critical:** Patch within 48 hours
- **High:** Patch within 7 days
- **Medium:** Patch within 30 days
- **Low:** Patch in next release

---

## **Security Checklist**

### **Initial Setup**
- [ ] Change default admin password
- [ ] Delete `database/.initial_credentials`
- [ ] Set database permissions: `chmod 600 database/truai.db`
- [ ] Set encryption key permissions: `chmod 700 database/keys/`
- [ ] Generate master recovery key (Settings → Security)
- [ ] Store master recovery key offline

### **Configuration**
- [ ] `.env` file created (not in repository)
- [ ] API keys set (if using AI features)
- [ ] `TRUAI_DEPLOYMENT` set to `production` (if production)
- [ ] HTTPS enforced (if production)

### **Monitoring**
- [ ] Verified ROMA status: `curl http://127.0.0.1:8001/TruAi/api/v1/security/roma`
- [ ] Tested login flow
- [ ] Confirmed session timeout works
- [ ] Reviewed audit logs

---

## **Compliance**

TruAi is designed for **self-hosted deployment** and does not store user data on third-party servers.

| Standard | Status | Notes |
|----------|--------|-------|
| **GDPR** | ✅ Compliant | Data stored locally; user can export/delete all data |
| **CCPA** | ✅ Compliant | No third-party data sharing |
| **SOC 2** | ⚠️ Partial | Architecture supports SOC 2; formal audit not included |

---

**Last Updated:** 2026-02-25  
**Version:** 1.0.0  
**Contact:** security@demewebsolutions.com
