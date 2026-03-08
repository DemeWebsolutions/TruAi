# TruAi HTML Server - Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                        TruAi HTML Server                             │
│                     (Phantom.ai Style Login)                         │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│                          CLIENT LAYER                                │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  ┌────────────────┐              ┌──────────────────┐              │
│  │  Login Page    │              │   Dashboard      │              │
│  │                │              │                  │              │
│  │  • Logo/Brand  │──Login──>   │  3-Column Layout │              │
│  │  • Legal Terms │              │  (Cursor Style)  │              │
│  │  • Encryption  │              │                  │              │
│  └────────────────┘              └──────────────────┘              │
│         │                                  │                         │
│         │                                  │                         │
│         ▼                                  ▼                         │
│  ┌──────────────────────────────────────────────────────┐          │
│  │           JavaScript Frontend Layer                   │          │
│  ├──────────────────────────────────────────────────────┤          │
│  │  crypto.js    │  api.js      │  dashboard.js         │          │
│  │  • AES-256    │  • REST      │  • UI Logic           │          │
│  │  • SHA-256    │  • Fetch API │  • Task Management    │          │
│  │  • Web Crypto │  • Error     │  • File Handling      │          │
│  │  • Key Gen    │    Handling  │  • Event Listeners    │          │
│  └──────────────────────────────────────────────────────┘          │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
                             │
                             │  HTTPS (Encrypted)
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         SERVER LAYER (PHP)                           │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  ┌────────────────────────────────────────────────────────────┐    │
│  │                    index.php (Entry Point)                  │    │
│  │  • Route Detection                                          │    │
│  │  • Localhost Enforcement                                    │    │
│  │  • Session Management                                       │    │
│  └────────────────────────────────────────────────────────────┘    │
│                             │                                        │
│                             ▼                                        │
│  ┌────────────────────────────────────────────────────────────┐    │
│  │                      router.php (API Router)                │    │
│  │  • Route Matching                                           │    │
│  │  • CORS Handling                                            │    │
│  │  • Auth Middleware                                          │    │
│  └────────────────────────────────────────────────────────────┘    │
│           │                    │                    │                │
│           ▼                    ▼                    ▼                │
│  ┌────────────────┐  ┌─────────────────┐  ┌─────────────────┐    │
│  │  auth.php      │  │ truai_service   │  │  chat_service   │    │
│  │                │  │                 │  │                 │    │
│  │  • Login       │  │  • Task Create  │  │  • Messages     │    │
│  │  • Session     │  │  • Risk Eval    │  │  • Conversations│    │
│  │  • CSRF        │  │  • Tier Route   │  │  • History      │    │
│  │  • Audit       │  │  • Execute      │  │                 │    │
│  └────────────────┘  └─────────────────┘  └─────────────────┘    │
│           │                    │                    │                │
│           └────────────────────┴────────────────────┘                │
│                             │                                        │
│                             ▼                                        │
│  ┌────────────────────────────────────────────────────────────┐    │
│  │                    encryption.php                           │    │
│  │  • RSA-2048 Key Generation                                  │    │
│  │  • AES-256-GCM Decryption                                   │    │
│  │  • Session Key Management                                   │    │
│  │  • PBKDF2 Password Hashing                                  │    │
│  └────────────────────────────────────────────────────────────┘    │
│                             │                                        │
│                             ▼                                        │
│  ┌────────────────────────────────────────────────────────────┐    │
│  │                    database.php (SQLite)                    │    │
│  │  • Schema Auto-Init                                         │    │
│  │  • Prepared Statements                                      │    │
│  │  • Connection Pool                                          │    │
│  └────────────────────────────────────────────────────────────┘    │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         DATA LAYER                                   │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  ┌─────────────────────────────────────────────────────────┐       │
│  │                    SQLite Database                       │       │
│  │                    (truai.db)                            │       │
│  ├─────────────────────────────────────────────────────────┤       │
│  │  Tables:                                                 │       │
│  │  • users            - Authentication                     │       │
│  │  • conversations    - Chat history                       │       │
│  │  • messages         - Chat messages                      │       │
│  │  • tasks            - TruAi tasks                        │       │
│  │  • executions       - Task executions                    │       │
│  │  • artifacts        - Generated code                     │       │
│  │  • audit_logs       - Immutable audit trail             │       │
│  └─────────────────────────────────────────────────────────┘       │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────────┐
│                      ENCRYPTION FLOW                                 │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  CLIENT                                    SERVER                    │
│  ──────                                    ──────                    │
│                                                                       │
│  1. Request Public Key          ────────>  Generate RSA-2048        │
│                                                                       │
│  2. Generate Session Key                                             │
│     • Random 32-byte key                                             │
│     • Used for AES-256-GCM                                           │
│                                                                       │
│  3. Hash Password                                                    │
│     • SHA-256(password)                                              │
│     • No plaintext sent                                              │
│                                                                       │
│  4. Encrypt Credentials                                              │
│     • AES-256-GCM with session key                                   │
│     • Includes timestamp                                             │
│                                                                       │
│  5. Send Encrypted Data         ────────>  Decrypt with Session Key │
│                                                                       │
│  6.                                        Verify Timestamp          │
│                                            (Prevent Replay)          │
│                                                                       │
│  7.                                        Hash & Verify Password    │
│                                            (bcrypt + salt)           │
│                                                                       │
│  8.                                        Create Secure Session     │
│                                            (HTTP-only cookie)        │
│                                                                       │
│  9. <────────  Success Response            Generate CSRF Token       │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────────┐
│                      TRUAI CORE WORKFLOW                             │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  User Input                                                          │
│      │                                                                │
│      ▼                                                                │
│  ┌────────────────┐                                                  │
│  │  Task Creation │                                                  │
│  │  • Prompt      │                                                  │
│  │  • Context     │                                                  │
│  │  • Files       │                                                  │
│  └────────────────┘                                                  │
│      │                                                                │
│      ▼                                                                │
│  ┌────────────────┐                                                  │
│  │  Risk Engine   │                                                  │
│  │  Evaluation    │────────> LOW / MEDIUM / HIGH                    │
│  └────────────────┘                                                  │
│      │                                                                │
│      ▼                                                                │
│  ┌────────────────┐                                                  │
│  │  Tier Router   │────────> Cheap / Mid / High                     │
│  │  Assignment    │                                                  │
│  └────────────────┘                                                  │
│      │                                                                │
│      ▼                                                                │
│  ┌────────────────┐                                                  │
│  │  AI Execution  │────────> Generate Output                        │
│  │  (Simulated)   │          (Placeholder for real AI)              │
│  └────────────────┘                                                  │
│      │                                                                │
│      ▼                                                                │
│  ┌────────────────┐                                                  │
│  │  Review Phase  │                                                  │
│  │  (Human Gate)  │────────> Accept / Reject / Save                 │
│  └────────────────┘                                                  │
│      │                                                                │
│      ▼                                                                │
│  ┌────────────────┐                                                  │
│  │  Approval      │────────> Production / Staging                   │
│  │  & Deployment  │                                                  │
│  └────────────────┘                                                  │
│      │                                                                │
│      ▼                                                                │
│  ┌────────────────┐                                                  │
│  │  Audit Log     │────────> Immutable Record                       │
│  └────────────────┘                                                  │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────────┐
│                      SECURITY LAYERS                                 │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  Layer 1: Transport Security                                         │
│  • AES-256-GCM encryption                                            │
│  • HTTPS/TLS ready                                                   │
│  • No plaintext transmission                                         │
│                                                                       │
│  Layer 2: Authentication                                             │
│  • SHA-256 client hashing                                            │
│  • bcrypt server hashing                                             │
│  • PBKDF2 with 100K iterations                                       │
│  • Salt-based password storage                                       │
│                                                                       │
│  Layer 3: Session Security                                           │
│  • HTTP-only cookies                                                 │
│  • Secure session tokens                                             │
│  • 1-hour expiration                                                 │
│  • Automatic cleanup                                                 │
│                                                                       │
│  Layer 4: API Security                                               │
│  • CSRF token protection                                             │
│  • Rate limiting ready                                               │
│  • Input validation                                                  │
│  • Output sanitization                                               │
│                                                                       │
│  Layer 5: Database Security                                          │
│  • Prepared statements                                               │
│  • SQL injection prevention                                          │
│  • Encrypted storage support                                         │
│  • Read-only permissions where applicable                            │
│                                                                       │
│  Layer 6: Access Control                                             │
│  • Localhost-only enforcement                                        │
│  • Single admin authorization                                        │
│  • Comprehensive audit logging                                       │
│  • Immutable audit trail                                             │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────────┐
│                      FILE STRUCTURE                                  │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  TruAi/                                                              │
│  ├── index.php              (Entry point)                           │
│  ├── README.md              (Full documentation)                    │
│  ├── SETUP.md               (Quick start guide)                     │
│  ├── IMPLEMENTATION_SUMMARY.md  (This document)                     │
│  ├── .gitignore             (Ignore patterns)                       │
│  │                                                                    │
│  ├── backend/               (PHP Backend)                           │
│  │   ├── config.php         (Configuration)                         │
│  │   ├── database.php       (DB layer)                              │
│  │   ├── auth.php           (Authentication)                        │
│  │   ├── encryption.php     (Encryption service)                    │
│  │   ├── router.php         (API router)                            │
│  │   ├── truai_service.php  (Core logic)                            │
│  │   └── chat_service.php   (Chat functionality)                    │
│  │                                                                    │
│  ├── assets/                (Frontend Assets)                       │
│  │   ├── css/                                                        │
│  │   │   └── main.css       (Complete styling)                      │
│  │   ├── js/                                                         │
│  │   │   ├── crypto.js      (Encryption utils)                      │
│  │   │   ├── api.js         (API client)                            │
│  │   │   ├── app.js         (Core app)                              │
│  │   │   ├── login.js       (Login page)                            │
│  │   │   └── dashboard.js   (Dashboard)                             │
│  │   └── images/            (Logos)                                 │
│  │       ├── TruAi-Logo.png                                          │
│  │       ├── TruAi-icon.png                                          │
│  │       ├── TruAi-transparent-bg.png                                │
│  │       └── Tru.png                                                 │
│  │                                                                    │
│  ├── database/              (Auto-created)                          │
│  │   └── truai.db           (SQLite)                                │
│  │                                                                    │
│  └── logs/                  (Auto-created)                          │
│      └── (application logs)                                         │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘


Copyright My Deme, LLC © 2026
Developed by DemeWebsolutions.com
```
