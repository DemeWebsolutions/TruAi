# Local Sovereign Recovery Protocol (LSRP) v1.0

**Classification:** Internal Technical Specification  
**Version:** 1.0.0  
**Date:** February 2026  
**Author:** My Deme, LLC  

---

## Overview

The **Local Sovereign Recovery Protocol (LSRP)** replaces traditional email/SMS-based password recovery with a local multi-factor recovery system. Recovery requires physical access to the trusted server environment and cannot be initiated remotely.

**Design principles:**
- No email dependency
- No SMS / third-party dependency
- No cloud recovery surface
- Physical presence required
- ROMA trust chain integrity maintained throughout

---

## Recovery Chain of Trust

```
[User] → [Local Network Access]
       → [OS Admin Password]
       → [ROMA Trust Verification]
       → [Device Fingerprint Check]
       → [Temporary Password]
       → [Mandatory Password Change on Login]
```

---

## Factor Requirements

| Factor | Description | Failure Mode |
|--------|-------------|--------------|
| **F1 — Local Access** | Request must originate from `127.0.0.1` or `::1` | Hard deny |
| **F2 — ROMA Trust** | ROMA trust state must be `VERIFIED` | Hard deny |
| **F3 — OS Admin** | Valid OS administrator username + password | Hard deny after 3 failures |
| **F4 — Device Fingerprint** | Known device fingerprint (optional, warning on mismatch) | Warning only |

---

## Recovery Flow

### Step 1: Initiate Recovery

**Endpoint:** `POST /api/v1/recovery/initiate`

**Request body:**
```json
{
  "username": "admin",
  "os_username": "localadmin",
  "os_password": "...",
  "device_fingerprint": "abc123..."
}
```

**Rate limit:** 3 attempts per 24 hours per username

### Step 2: Factor Verification

The server verifies all 4 factors sequentially. Any hard-deny failure returns `403` with an audit log entry.

### Step 3: Temporary Password

On success, the server:
1. Generates a cryptographically secure 16-character temporary password
2. Hashes it with Argon2id and stores in the `users` table
3. Sets `temp_password_expires` to `NOW() + 10 minutes`
4. Sets `requires_password_change = 1`
5. Returns the temporary password in the response **once** (never stored in plaintext)

**Response:**
```json
{
  "success": true,
  "temp_password": "Kj9mX2pL8nQr4wYt",
  "expires_in": 600,
  "message": "Use this password to log in. You will be required to change it immediately."
}
```

### Step 4: Forced Password Change

On first login with the temporary password, the system:
1. Detects `requires_password_change = 1`
2. Redirects to password change screen
3. Validates new password meets complexity requirements
4. Hashes with Argon2id and updates the record
5. Clears `temp_password_expires` and `requires_password_change`

---

## Master Recovery Key System

The Master Recovery Key is a 256-bit offline backup for emergency scenarios where the OS admin password is also unknown.

### Key Generation

```php
$masterKey = bin2hex(random_bytes(32)); // 64 hex characters
$keyHash   = password_hash($masterKey, PASSWORD_ARGON2ID, ARGON2ID_OPTIONS);
```

**Endpoint:** `POST /api/v1/recovery/masterkey/generate`

The plain key is returned **once** and must be stored offline (printed and placed in a physical safe, or stored in a hardware security module).

### Key Validation

**Endpoint:** `POST /api/v1/auth/masterkey`

The server verifies the key hash with `password_verify()`. A successful match grants access to the LSRP recovery flow without requiring OS admin credentials.

### Key Rotation

After any use of the master key, a new key should be generated immediately. The old key is invalidated.

---

## Database Schema

```sql
-- Recovery attempt audit trail
CREATE TABLE recovery_attempts (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id         INTEGER,
    ip_address      TEXT NOT NULL,
    device_fingerprint TEXT,
    result          TEXT NOT NULL CHECK (result IN ('SUCCESS','DENIED','WARNING')),
    details         TEXT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Master recovery keys (hashed)
CREATE TABLE master_recovery_keys (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER UNIQUE NOT NULL,
    key_hash   TEXT NOT NULL,
    issued_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_used  DATETIME,
    use_count  INTEGER DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Trusted devices for fingerprint matching
CREATE TABLE trusted_devices (
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id            INTEGER NOT NULL,
    device_fingerprint TEXT NOT NULL,
    device_name        TEXT,
    first_seen         DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_seen          DATETIME DEFAULT CURRENT_TIMESTAMP,
    revoked            INTEGER DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

The `users` table also requires:
```sql
ALTER TABLE users ADD COLUMN temp_password_expires      DATETIME;
ALTER TABLE users ADD COLUMN requires_password_change   INTEGER DEFAULT 0;
```

---

## Threat Model

### Threats Eliminated

| Threat | How LSRP Eliminates It |
|--------|------------------------|
| Email account compromise | No email used |
| SIM swap / SMS hijacking | No SMS used |
| Phishing for reset link | No links sent externally |
| Remote brute force | Local access required |
| Social engineering support | No human support channel |

### Threats Mitigated

| Threat | Mitigation |
|--------|------------|
| Physical attacker with server access | OS admin password required |
| Malware on local network | ROMA trust validation detects anomalies |
| Insider threat | All attempts are audit-logged |
| Stolen master key | Rate-limited; triggers mandatory re-generation |

### Residual Risks

| Risk | Notes |
|------|-------|
| Physical access + known OS admin password | Accepted — physical security is a prerequisite |
| Compromised ROMA trust chain | ROMA alerts on suspicion; hard-deny on compromise |

---

## Implementation Files

| File | Purpose |
|------|---------|
| `backend/lsrp_recovery.php` | `LSRPRecoveryController` class |
| `public/TruAi/secure-recovery.html` | Multi-step recovery UI |
| `database/migrations/001_initial_schema.sql` | Schema with all LSRP tables |

---

## Audit Requirements

Every recovery attempt (success, denial, warning) must be recorded in `recovery_attempts` with:
- `user_id` (if resolved)
- `ip_address`
- `device_fingerprint`
- `result` (SUCCESS | DENIED | WARNING)
- `details` (JSON with factor results)

Administrators should review `recovery_attempts` regularly for suspicious patterns.
