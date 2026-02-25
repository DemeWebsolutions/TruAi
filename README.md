# TruAi — Super Admin Portal

> **ROMA-secured local AI administration portal**  
> Proprietary software — My Deme, LLC © 2026

TruAi is a local-first super admin portal for managing AI deployments (Gemini.ai, Phantom.ai), featuring enterprise-grade security without cloud dependencies.

---

## ✨ Key Features

| Feature | Description |
|---------|-------------|
| 🔐 **UBSAS** | 4-tier Unified Biometric Sovereign Authentication (Touch ID → Keychain → Password → Master Key) |
| 🛡️ **ROMA** | Recursive Oversight & Monitoring Architecture — real-time trust validation |
| 🔑 **LSRP** | Local Sovereign Recovery Protocol — offline, no email, no SMS |
| 🤖 **Gemini.ai** | AI automation dashboard with server diagnostics & cluster management |
| 🔒 **Encryption** | RSA-2048 key exchange + AES-256-GCM credential transport |
| 🗄️ **SQLite** | Zero-infrastructure local database (no PostgreSQL, no MySQL) |

---

## 🚀 Quick Start

**Prerequisites:** PHP ≥ 8.2 with `sqlite3`, `openssl`, `mbstring`, `json`

```bash
# 1. Clone and enter the repo
git clone https://github.com/DemeWebsolutions/TruAi.git
cd TruAi

# 2. Copy environment template
cp .env.example .env
# (edit .env to add your AI API keys)

# 3. Initialize database and generate encryption keys
php scripts/setup_database.php

# 4. Start the server
./start.sh
```

**First login:**
```bash
# Get your generated credentials
cat database/.initial_credentials

# Open the portal
open http://127.0.0.1:8001/TruAi/ubsas-entrance.html
```

> ⚠️ **Change your admin password immediately after first login.** Delete `database/.initial_credentials` once noted.

---

## 📋 Documentation

| Document | Description |
|----------|-------------|
| [SETUP.md](SETUP.md) | Full installation and configuration guide |
| [QUICKSTART.md](QUICKSTART.md) | Quick start guide |
| [ARCHITECTURE.md](ARCHITECTURE.md) | System architecture overview |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | Common issues and solutions |
| [docs/API.md](docs/API.md) | REST API endpoint reference |
| [docs/SECURITY.md](docs/SECURITY.md) | Security architecture & threat model |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Production deployment (Plesk, Nginx, Apache) |
| [docs/OPERATOR_GUIDE.md](docs/OPERATOR_GUIDE.md) | Operations and administration guide |
| [docs/TESTING.md](docs/TESTING.md) | Test suite documentation |
| [docs/LSRP_SPEC.md](docs/LSRP_SPEC.md) | Local Sovereign Recovery Protocol specification |
| [docs/UBSAS_SPEC.md](docs/UBSAS_SPEC.md) | Unified Biometric Sovereign Authentication specification |
| [docs/ROMA_CONTRACT.md](docs/ROMA_CONTRACT.md) | ROMA security contract & ITC specification |
| [CHANGELOG.md](CHANGELOG.md) | Version history |

---

## 🗂️ Architecture

```
TruAi/
├── backend/           # PHP 8.2 API backend
│   ├── auth.php           # Authentication (UBSAS, session management)
│   ├── config.php         # Configuration & constants (PASSWORD_ARGON2ID)
│   ├── csrf.php           # CSRF protection
│   ├── database.php       # SQLite wrapper
│   ├── encryption.php     # RSA-2048 + AES-256-GCM
│   ├── gemini_service.php # Gemini.ai automation
│   ├── lsrp_recovery.php  # LSRP recovery controller
│   ├── roma_itc.php       # ROMA Internal Trust Channel
│   ├── roma_trust.php     # ROMA trust validation
│   ├── router.php         # API router (60+ endpoints)
│   ├── ubsas_auth_service.php  # Biometric auth service
│   └── validator.php      # Input validation
│
├── public/TruAi/      # Frontend HTML pages
│   ├── login-portal.html      # Classic login (background image)
│   ├── ubsas-entrance.html    # 4-tier auth selection portal
│   ├── ubsas-enroll.html      # Biometric enrollment wizard
│   └── secure-recovery.html   # LSRP recovery wizard
│
├── assets/            # JavaScript & CSS
│   ├── js/api.js          # API client (auto CSRF injection)
│   ├── js/crypto.js       # Client-side RSA/AES encryption
│   └── ...
│
├── database/
│   ├── migrations/        # SQL schema migrations
│   └── keys/              # RSA key pair (gitignored)
│
├── scripts/           # Setup & maintenance
│   ├── setup_database.php
│   ├── reset_admin_password.php
│   ├── run_migrations.php
│   ├── backup_database.sh
│   ├── setup_biometric_auth.sh
│   └── test_biometric.sh
│
├── docs/              # Documentation
│   ├── API.md             # REST API reference
│   ├── SECURITY.md        # Security architecture
│   ├── DEPLOYMENT.md      # Production deployment
│   ├── OPERATOR_GUIDE.md  # Operations guide
│   └── TESTING.md         # Test suite docs
│
├── design/            # Design assets (SVG, PDF mockups)
├── dev/               # Development & test HTML files
├── tests/             # Test suite
│   └── integration/       # Integration tests
├── browser_extension/ # Chrome/Firefox native messaging
├── native_host/       # PHP native host for biometric
└── deploy/            # Deployment scripts (Plesk, Nginx)
```

---

## 🔐 Security Architecture

### Authentication Tiers (UBSAS v2.0)
1. **Tier 1 — Biometric**: Touch ID / Face ID via OS native APIs
2. **Tier 2 — Keychain**: macOS Keychain / Linux libsecret auto-fill
3. **Tier 3 — Password**: Argon2id-hashed (64 MB memory, 4 iterations, 2 threads)
4. **Tier 4 — Master Key**: 256-bit offline recovery key (emergency only)

### ROMA Trust Model
- Real-time trust state validation on every operation
- Suspicion scoring with automatic lockout
- RSA-2048 + AES-256-GCM session encryption
- CSRF protection on all state-changing API calls
- Rate limiting: 5 login attempts/5 min · 3 recovery attempts/24 hr

### Recovery (LSRP v1.0)
Local-only recovery — no email, no SMS, no cloud:
1. Local network presence required
2. OS administrator password confirmation
3. ROMA trust verification
4. Device fingerprint validation

---

## 🤖 Gemini.ai Integration

- **Real-time Diagnostics**: CPU, memory, disk via `/proc/meminfo` and `sys_getloadavg()`
- **Security Hardening**: Automated configuration checks
- **Log Collection**: Aggregated log viewer
- **Key Rotation**: Credential rotation workflow
- **Cluster Management**: Node provisioning

Access: `http://127.0.0.1:8001/TruAi/gemini`

---

## 🧪 Testing

```bash
# Initialize (required before first test run)
php scripts/setup_database.php

# Unit tests (48 tests)
php tests/run_tests.php

# Biometric setup verification
bash scripts/test_biometric.sh

# Database migrations
php scripts/run_migrations.php
```

---

## 📦 Requirements

| Requirement | Version |
|-------------|---------|
| PHP | ≥ 8.2 |
| Extensions | `sqlite3`, `openssl`, `mbstring`, `json` |
| OS | macOS 12+ (Touch ID) · Linux (fprintd) · Windows |
| Browser | Chrome 88+ or Firefox 78+ |

---

## 🔒 Security Disclosure

Report vulnerabilities to: **security@demewebsolutions.com**

Please do not create public GitHub issues for security vulnerabilities.

---

## 📄 License

Proprietary — All rights reserved.  
© 2026 [My Deme, LLC](https://demewebsolutions.com)
