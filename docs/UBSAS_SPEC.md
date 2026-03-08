# Unified Biometric Sovereign Authentication System (UBSAS) v2.0

**Classification:** Internal Technical Specification  
**Version:** 2.0.0  
**Date:** February 2026  
**Author:** My Deme, LLC  

---

## Overview

UBSAS is a 4-tier authentication system that prioritizes high-assurance biometric methods while maintaining a reliable password fallback and an emergency master key. All tiers are available through a single entrance portal (`ubsas-entrance.html`).

**Design principles:**
- Prefer hardware-backed authentication (Touch ID, Face ID)
- Graceful degradation — always at least one method available
- OS-native credential storage (no third-party vaults)
- ROMA-validated at every tier

---

## Authentication Tiers

### Tier 1 — OS Biometric (`biometric`)

**Method:** Touch ID (macOS) / Face ID (macOS) / fprintd (Linux)  
**Assurance level:** Highest  
**Availability:** Hardware-dependent

**Flow:**
1. Frontend calls `GET /api/v1/auth/methods` — detects `biometric` in response
2. User selects biometric card
3. Frontend posts to `POST /api/v1/auth/biometric` with `{ "app": "TruAi" }`
4. Backend invokes `UBSASAuthService::biometricAutoLogin()`:
   - macOS: reads from Keychain via `security find-generic-password`; Touch ID prompt appears
   - Linux: invokes `fprintd-verify`
5. On success: session established, audit log entry created

**macOS Keychain storage:**
```bash
security add-generic-password \
  -a "admin" \
  -s "com.demewebsolutions.truai" \
  -w "password123" \
  -T "/usr/bin/security"
```

**Setup:** `bash scripts/setup_biometric_auth.sh`

---

### Tier 2 — Keychain Auto-Fill (`keychain`)

**Method:** macOS Keychain / Linux libsecret  
**Assurance level:** High  
**Availability:** Keychain software present

**Flow:**
1. Frontend detects `keychain` in `/api/v1/auth/methods`
2. Frontend posts to `POST /api/v1/auth/autofill` with `{ "app": "TruAi" }`
3. Backend invokes `UBSASAuthService::autofillCredentials()`:
   - macOS: `security find-generic-password -a username -s service -w`
   - Linux: `secret-tool lookup service truai username username`
4. Retrieved credentials are validated against the database

**Difference from Tier 1:** No biometric hardware required; any user with the keychain entry can authenticate.

---

### Tier 3 — Manual Password (`password`)

**Method:** Username + Argon2id password  
**Assurance level:** Standard  
**Availability:** Always

**Flow:**
1. Always available (no hardware check needed)
2. Frontend shows username + password form
3. Login attempt:
   - **Encrypted path**: `crypto.js` generates a session key, encrypts credentials with server RSA public key, posts `{ encrypted_data, session_id }` to `POST /api/v1/auth/login`
   - **Plain fallback**: posts `{ username, password }` if WebCrypto unavailable
4. Backend decrypts (if encrypted) and validates with `password_verify()` against Argon2id hash

**Password requirements:**
- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one digit
- At least one special character

**Hashing parameters:**
```php
password_hash($password, PASSWORD_ARGON2ID, [
    'memory_cost' => 65536,  // 64 MB
    'time_cost'   => 4,
    'threads'     => 2,
]);
```

---

### Tier 4 — Master Key (`masterkey`)

**Method:** 256-bit offline recovery key  
**Assurance level:** Emergency only  
**Availability:** Always (if generated)

**Flow:**
1. User selects Master Key card and enters username + 64-hex-character key
2. Frontend posts to `POST /api/v1/auth/masterkey`
3. Backend validates with `password_verify($key, $storedHash)`
4. On success: redirects to LSRP recovery flow for password reset

**Key generation:** `POST /api/v1/recovery/masterkey/generate` (auth required)

The key is returned **once** in plaintext and must be stored offline.

**Security note:** After any use of the master key, generate a new one immediately.

---

## Enrollment

New devices are registered through the enrollment wizard (`ubsas-enroll.html`).

**Endpoint:** `POST /api/v1/auth/enroll`

**Request:**
```json
{
  "username":  "admin",
  "os_user":   "localadmin",
  "method":    "biometric",
  "device_fp": "abc123def456..."
}
```

**Response:**
```json
{
  "success": true,
  "message": "Enrollment recorded",
  "method":  "biometric"
}
```

The enrollment stores a device fingerprint in `ubsas_devices` and logs the event in `biometric_logins`.

---

## Method Availability Detection

**Endpoint:** `GET /api/v1/auth/methods`

**Response:**
```json
{
  "methods": ["biometric", "keychain", "password", "masterkey"],
  "biometric_type": "Touch ID",
  "os": "Darwin"
}
```

Frontend renders availability badges based on the `methods` array:
- Present → green "Available" badge
- Absent → gray "Not detected" badge

---

## Device Fingerprinting

UBSAS uses a lightweight client-side fingerprint:

```javascript
btoa([
  navigator.platform,
  navigator.language,
  screen.colorDepth,
  screen.width + 'x' + screen.height,
  Intl.DateTimeFormat().resolvedOptions().timeZone
].join('|')).slice(0, 32)
```

**Important:** This fingerprint is a heuristic only. It is supplemented by server-side factors (IP address, user agent) for device recognition. Do not rely on it as a sole security control.

Fingerprints are stored in `ubsas_devices` and `trusted_devices`.

---

## Database Schema

```sql
CREATE TABLE biometric_logins (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL,
    ip_address  TEXT,
    user_agent  TEXT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE ubsas_devices (
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id            INTEGER NOT NULL,
    device_type        TEXT CHECK (device_type IN ('biometric','manual','keychain')),
    device_fingerprint TEXT,
    device_info        TEXT,
    trusted            INTEGER DEFAULT 0,
    last_used          DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE ubsas_challenges (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id      INTEGER,
    challenge_type TEXT,
    attempts     INTEGER DEFAULT 0,
    last_attempt DATETIME,
    window_start DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

---

## Implementation Files

| File | Purpose |
|------|---------|
| `backend/ubsas_auth_service.php` | Core biometric auth service |
| `backend/router.php` | UBSAS API endpoints |
| `public/TruAi/ubsas-entrance.html` | 4-tier selection portal |
| `public/TruAi/ubsas-enroll.html` | Device enrollment wizard |
| `native_host/demewebsolutions_biometric_host.php` | PHP native messaging host |
| `native_host/com.demewebsolutions.biometric.json` | NMH manifest |
| `browser_extension/manifest.json` | Browser extension Manifest v3 |
| `scripts/setup_biometric_auth.sh` | Automated setup |
| `scripts/test_biometric.sh` | Setup verification |

---

## Browser Extension

The browser extension provides auto-fill capabilities when the portal is open in a browser tab.

**Architecture:**
- `manifest.json` — Manifest v3 with `nativeMessaging` permission
- `content.js` — Detects login forms and injects credentials
- `background.js` — Native messaging host communication

**Install:** Load `browser_extension/` as an unpacked extension in Chrome/Firefox developer mode.
