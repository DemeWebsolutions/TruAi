# ROMA Security Contract v2.0

**Classification:** Internal Technical Specification  
**Version:** 2.0.0  
**Date:** February 2026  
**Author:** My Deme, LLC  

---

## Overview

**ROMA** (Recursive Oversight & Monitoring Architecture) is TruAi's trust validation system. It enforces the principle that every sensitive operation must be validated against a known-good trust state before execution.

ROMA is **not** a firewall or intrusion detection system. It is a trust-chain integrity verifier that ensures the system is operating within expected parameters before allowing privileged actions.

---

## Trust States

| State | Value | Meaning |
|-------|-------|---------|
| `VERIFIED` | Nominal | All trust checks pass; system operating normally |
| `WARNING` | Degraded | One or more non-critical checks failed; operations proceed with logging |
| `COMPROMISED` | Blocked | Critical trust failure; all privileged operations blocked |

---

## Trust Validation

### Checks Performed

```php
$checks = [
    'encryption_keys'    => file_exists($keyDir . '/private_key.pem') &&
                            file_exists($keyDir . '/public_key.pem'),
    'session'            => session_status() === PHP_SESSION_ACTIVE,
    'workspace'          => is_dir(BASE_PATH),
    'workspace_writable' => is_writable(DATABASE_PATH),
];
```

### Trust State Resolution

```php
$allPass      = array_reduce($checks, fn($c, $v) => $c && $v, true);
$trust_state  = $allPass ? 'VERIFIED' : 'COMPROMISED';
```

### Suspicion Scoring

ROMA maintains a suspicion score that increases on:
- Failed login attempts
- Invalid CSRF tokens
- Requests from unexpected IP addresses
- Rapid repeated requests (rate-limit threshold approach)

When `suspicion_score >= SUSPICION_THRESHOLD`, ROMA blocks the operation and emits a `ROMA_SUSPICION_BLOCKED` security event.

**Endpoint:** `GET /api/v1/security/roma`

**Response:**
```json
{
  "roma":                true,
  "portal_protected":    true,
  "monitor":             "active",
  "encryption":          "RSA-2048 + AES-256-GCM",
  "local_only":          true,
  "timestamp":           1771826235,
  "trust_state":         "VERIFIED",
  "reason":              null,
  "checks": {
    "encryption_keys":   true,
    "session":           true,
    "workspace":         true,
    "workspace_writable": true
  },
  "suspicion_blocked":   false
}
```

---

## Portal Protection

ROMA enforces the **local-only** contract: privileged endpoints are only accessible from `127.0.0.1` or `::1`. External access results in a `403 Forbidden`.

```php
public static function enforceLocalhost(): void {
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($remoteAddr, ['127.0.0.1', '::1', '::ffff:127.0.0.1'])) {
        // Check production allowlist
        if (!in_array(gethostbyaddr($remoteAddr), ALLOWED_HOSTS)) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied: localhost only']);
            exit;
        }
    }
}
```

---

## ROMA Internal Trust Channel (ITC)

The ITC enables secure machine-to-machine communication between TruAi and subordinate systems (Gemini.ai, Phantom.ai).

### Handshake Protocol

```
[Subordinate]  →  POST /api/v1/itc/handshake  →  [TruAi]
              ←  { session_id, session_key }  ←
              →  POST /api/v1/itc/register     →
              ←  { trust_status: "active" }   ←
```

### ITC Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/v1/itc/handshake` | POST | Initiate ITC session |
| `/api/v1/itc/register` | POST | Register subordinate system |
| `/api/v1/itc/revoke` | POST | Revoke system trust |
| `/api/v1/itc/systems` | GET | List registered systems |

### ITC Session Keys

Each ITC session uses a unique AES-256-GCM session key, negotiated via RSA-2048:

1. Subordinate provides its RSA public key
2. TruAi generates a random 256-bit session key
3. TruAi encrypts the session key with the subordinate's public key
4. Session key stored in `itc_sessions` with expiry

### Database Schema

```sql
CREATE TABLE itc_systems (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    system_id    TEXT UNIQUE NOT NULL,
    public_key   TEXT NOT NULL,
    trust_status TEXT DEFAULT 'active' CHECK (trust_status IN ('active','revoked')),
    revoked_at   DATETIME,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE itc_sessions (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id  TEXT UNIQUE NOT NULL,
    system_id   TEXT NOT NULL,
    session_key TEXT NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at  DATETIME NOT NULL,
    FOREIGN KEY (system_id) REFERENCES itc_systems(system_id)
);
```

---

## Security Events

ROMA emits structured security events to `security_events` and the ROMA security event log:

| Event Type | Severity | Trigger |
|------------|----------|---------|
| `ROMA_SUSPICION_BLOCKED` | critical | Suspicion threshold exceeded |
| `TRUST_COMPROMISED` | critical | Encryption keys missing |
| `SESSION_FIXATION_ATTEMPT` | high | Suspicious session reuse |
| `CSRF_VALIDATION_FAILED` | high | Invalid CSRF token on state-changing request |
| `RATE_LIMIT_EXCEEDED` | medium | Login or recovery rate limit hit |
| `LOCALHOST_BYPASS_ATTEMPT` | critical | External IP attempting local-only endpoint |

**Endpoint:** `POST /api/v1/security/events`  
**Endpoint:** `GET /api/v1/trust/events`

---

## Encryption Standards

| Purpose | Algorithm | Key Size |
|---------|-----------|----------|
| Credential transport | RSA-OAEP + AES-256-GCM | 2048-bit RSA |
| Password storage | Argon2id | 64 MB · 4 iterations |
| Session tokens | HMAC-SHA256 | 256-bit |
| CSRF tokens | `random_bytes(32)` → hex | 256-bit |
| Master recovery key | `random_bytes(32)` → hex | 256-bit |

---

## Compliance Alignment

| Standard | Alignment |
|----------|-----------|
| NIST SP 800-63B | AAL2 (hardware-based biometric) |
| OWASP ASVS | Level 2 (most controls met) |
| GDPR | Data minimization; no external data transmission |
| HIPAA | Audit logging; access controls; encryption at rest |

---

## Implementation Files

| File | Purpose |
|------|---------|
| `backend/roma_trust.php` | `RomaTrust` class — trust validation |
| `backend/roma_itc.php` | `RomaITC` class — inter-trust channel |
| `backend/csrf.php` | `CSRFProtection` class |
| `backend/encryption.php` | `EncryptionService` class |
| `backend/config.php` | `ARGON2ID_OPTIONS`, `PASSWORD_ALGORITHM` constants |
