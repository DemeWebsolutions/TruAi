# Changelog

All notable changes to TruAi will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-02-21

### Added
- Complete frontend UI suite (login-portal, secure-recovery)
- LSRP (Local Sovereign Recovery Protocol) v1.0 with 4-factor authentication
- UBSAS (Unified Biometric Sovereign Auth) v2.0 with OS-native biometric support
- ROMA Trust validation with real-time monitoring
- ROMA ITC (Internal Trust Channel) v1 for inter-system communication
- Argon2id password hashing (64MB memory-hard, LSRP-spec compliant)
- CSRF protection layer (`backend/csrf.php`) with token rotation
- Input validation service (`backend/validator.php`) covering username, password, path, HTML sanitization
- Rate limiting on authentication endpoints (5 attempts/5min for login, 3/24h for recovery)
- Session security hardening (HttpOnly, Secure, SameSite, idle/absolute timeout tracking)
- Session ID regeneration on login (prevents fixation attacks)
- Session metadata tracking (user agent, IP address)
- Automated database setup script (`scripts/setup_database.php`)
- Emergency password reset script (`scripts/reset_admin_password.php`)
- Automated backup script with compression and retention (`scripts/backup_database.sh`)
- Systemd backup service and timer (`scripts/backup_database.service`, `scripts/backup_database.timer`)
- Complete database migration (`database/migrations/001_initial_schema.sql`)
- CSRF token endpoint (`GET /api/v1/auth/csrf-token`)
- Comprehensive documentation (`docs/API.md`, `docs/DEPLOYMENT.md`, `docs/SECURITY.md`)
- CI/CD pipeline with PHP syntax validation, unit tests, integration tests, security scans (`.github/workflows/ci.yml`)
- Unit test suite (`tests/run_tests.php`) covering validator, auth, CSRF, database, encryption
- Gemini.ai automation service with 6 actions
- AI exception handling hierarchy
- Chat service with context-aware conversations
- Learning service with pattern extraction and user feedback tracking

### Changed
- Primary port changed from 8080 to 8001 (8080 retained for backwards compatibility)
- Updated CORS configuration to include port 8001 as primary
- Enhanced session management with idle timeout (30 minutes) and absolute timeout (1 hour)
- Improved error logging with structured messages including user ID, username, and IP
- Login handler in `router.php` now applies rate limiting per username and per IP

### Security
- CSRF token enforcement: `GET /api/v1/auth/csrf-token` endpoint added
- Input validation: `backend/validator.php` prevents injection attacks (SQL, XSS, path traversal)
- Session token regeneration on login prevents fixation attacks
- Rate limiting prevents brute force attacks (5/5min per username, 10/5min per IP)
- Audit logging for all authentication and security events
- LSRP 4-factor recovery (local access, ROMA trust, OS admin, device fingerprint)
- Biometric authentication support (Touch ID, Face ID, Linux fprintd)
- ROMA suspicion threshold protection (5 failures → BLOCKED state)
- Master recovery key system (256-bit offline backup)

### Fixed
- Router parse errors (escape sequences)
- Session initialization race conditions
- CORS configuration for port 8001
- Database locking issues with SQLite (online backup in backup script)

## [Unreleased]

### Planned for 1.1.0
- Windows Hello biometric support
- Browser extension for Chrome, Firefox, Safari, Edge
- Two-factor authentication (TOTP)
- Hardware security key support (YubiKey, WebAuthn)
- Advanced AI model routing (cost optimization)
- File upload and management UI
- Mobile-responsive dashboard improvements
- Kubernetes deployment templates
- Prometheus metrics exporter

### Planned for 2.0.0
- Multi-tenancy support
- RBAC (Role-Based Access Control)
- API key management for third-party integrations
- Webhook system
- Federated authentication (SAML, OAuth2)
- Cloud deployment options (AWS, Azure, GCP)
- Horizontal scaling support

## Version History

- [1.0.0] - 2026-02-21: Initial production release
