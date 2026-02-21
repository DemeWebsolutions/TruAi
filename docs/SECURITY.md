# TruAi Security Architecture

## Philosophy

TruAi is built on a **self-sovereign, zero-trust** security model:
- All authentication is local (no third-party SSO)
- Trust must be actively verified, never assumed
- Every action is audited
- Secrets never leave the local system

---

## Threat Model

### Attack Vectors Addressed

| Threat | Mitigation |
|--------|-----------|
| Brute force login | Rate limiting (5/5min per username, 10/5min per IP) |
| Session fixation | Session ID regenerated on every login |
| Session hijacking | HttpOnly, Secure, SameSite cookies; idle timeout |
| CSRF attacks | CSRF token required for all state-changing requests |
| SQL injection | Prepared statements (PDO) throughout |
| XSS | HTML output sanitized via `htmlspecialchars()` |
| Path traversal | File path sanitization in `Validator::sanitizeFilePath()` |
| Credential exposure | Argon2id hashing; no plaintext passwords stored |
| Key compromise | RSA-2048 private key stored at `chmod 600` |
| Unauthorized recovery | LSRP requires 4 independent factors |

### Attack Vectors NOT Addressed (Out of Scope)

- Physical access to the server
- Compromise of the OS administrator account
- Supply-chain attacks on dependencies
- Nation-state level cryptographic attacks

---

## Authentication Architecture (UBSAS)

**Unified Biometric Sovereign Authentication System (UBSAS)** provides 4-tier auth:

| Tier | Method | Description |
|------|--------|-------------|
| 1 | OS Biometric | Touch ID / Face ID via native OS APIs |
| 2 | Auto-Fill | macOS/Linux Keychain stored credentials |
| 3 | Manual Entry | Username + password (always available) |
| 4 | Master Key | 256-bit offline recovery key (emergency only) |

All tiers funnel into the same session establishment process.

---

## Recovery Architecture (LSRP)

**Local Sovereign Recovery Protocol (LSRP)** requires 4 factors:

1. **Local Server Access** – Request must originate from localhost or trusted VPN
2. **ROMA Trust Verification** – ROMA security monitor must be in VERIFIED state
3. **OS Administrator Verification** – Valid macOS/Linux admin credentials required
4. **Device Fingerprint** – Known device preferred; warnings issued for unknown devices

Recovery generates a temporary password valid for **10 minutes** that must be changed immediately.

---

## Encryption Standards

| Use | Algorithm | Key Size |
|-----|-----------|----------|
| Password hashing | Argon2id | 64MB memory, 4 iterations, 2 threads |
| RSA encryption | RSA-OAEP-SHA256 | 2048-bit |
| Symmetric encryption | AES-256-GCM | 256-bit |
| Session tokens | CSPRNG (random_bytes) | 256-bit |
| CSRF tokens | CSPRNG (random_bytes) | 256-bit |

### Why Argon2id?

Argon2id is the winner of the Password Hashing Competition (PHC) and is recommended by OWASP. The `memory_cost = 65536` (64MB) parameter makes GPU/ASIC attacks impractical.

---

## ROMA Trust Protocol

**Resilient Operations Monitoring Architecture (ROMA)** provides continuous trust verification:

- Checks encryption keys exist and are valid
- Verifies database is accessible and writable
- Monitors session health
- Tracks suspicion score (failed auth attempts)
- **BLOCKED** state triggered after 5 failures in 5 minutes

ROMA status is checked on every sensitive operation (login, password change, recovery).

---

## Session Management

| Parameter | Value | Purpose |
|-----------|-------|---------|
| `session.cookie_httponly` | 1 | Prevent JavaScript access to cookie |
| `session.cookie_secure` | 1 (production) | HTTPS only |
| `session.cookie_samesite` | Lax | CSRF mitigation |
| Session name | `TRUAI_SESSION` | Custom name (obscures tech stack) |
| Absolute timeout | 3600s (1 hour) | Limits exposure of stolen sessions |
| Idle timeout | 1800s (30 minutes) | Clears inactive sessions |
| ID regeneration | On every login | Prevents fixation attacks |

---

## CSRF Protection

All `POST`, `PUT`, `DELETE`, and `PATCH` requests to protected endpoints must include a valid CSRF token.

**Token flow:**
1. Client fetches `GET /api/v1/auth/csrf-token` after login
2. Token stored in PHP session (`$_SESSION['csrf_token']`)
3. Client includes token in `X-CSRF-Token` header
4. Server validates with `hash_equals()` (timing-safe comparison)
5. Token rotated after sensitive operations

---

## Input Validation

All user-supplied input is validated through `backend/validator.php`:

| Input | Validation |
|-------|-----------|
| Username | 3-32 chars, `[a-zA-Z0-9_-]` only |
| Password | 8+ chars, upper/lower/digit/special required |
| File paths | Directory traversal stripped, special chars removed |
| HTML output | `htmlspecialchars()` with ENT_QUOTES |
| SQL LIKE | `%` and `_` escaped |
| Conversation IDs | Numeric only |

---

## Rate Limiting

| Endpoint | Limit | Window |
|----------|-------|--------|
| Login (per username) | 5 attempts | 5 minutes |
| Login (per IP) | 10 attempts | 5 minutes |
| Recovery (per username) | 3 attempts | 24 hours |
| Master key (per username) | 3 attempts | 24 hours |

Rate limit counters are stored in the session and reset on successful authentication.

---

## Audit Logging

All authentication and security events are logged to `audit_logs` table:

- `USER_LOGIN` – Successful login with IP and user agent
- `USER_LOGOUT` – Session logout
- `PASSWORD_CHANGE` – Password successfully changed
- `LOGIN_FAILED` – Failed authentication attempt
- `ROMA_SUSPICION_BLOCKED` – Too many failures
- `ROMA_VALIDATION_FAILURE` – ROMA trust check failed
- `PASSWORD_CHANGE_BLOCKED` – Blocked by ROMA

---

## Incident Response

### Suspected Brute Force

1. Check `audit_logs` for repeated `LOGIN_FAILED` events
2. Review ROMA status: `curl http://127.0.0.1:8001/TruAi/api/v1/security/roma`
3. If `trust_state: BLOCKED`, restart server to reset suspicion score
4. Consider blocking source IP at firewall level

### Compromised Account

1. Reset password immediately: `php scripts/reset_admin_password.php admin`
2. Review `audit_logs` for suspicious activity
3. Rotate encryption keys if needed (delete `database/keys/` and re-run setup)
4. Review and revoke any trusted devices

### Data Breach

1. Rotate all encryption keys: delete `database/keys/` and run `php scripts/setup_database.php`
2. Force password reset for all users
3. Rotate API keys (OpenAI, Anthropic)
4. Review logs for data exfiltration indicators

---

## Vulnerability Disclosure

Security issues should be reported privately to:

**Email:** security@demewebsolutions.com

Please do not create public GitHub issues for security vulnerabilities.

---

## Security Checklist for Administrators

- [ ] Default admin password changed on first login
- [ ] `database/.initial_credentials` deleted after setup
- [ ] `database/truai.db` permissions: `chmod 600`
- [ ] `database/keys/` permissions: `chmod 700`
- [ ] HTTPS enforced in production
- [ ] Firewall blocks external access to port 8001
- [ ] API keys not committed to repository
- [ ] Audit log review scheduled (weekly)
- [ ] Backup automation verified
- [ ] ROMA status monitored
