# Quick Start Guide

## Fixed Issues

✅ **Permission Issue Fixed** - `start.sh` is now executable

## Starting the Server

### Method 1: Using start.sh (Recommended)
```bash
cd ~/Desktop/Tru.ai/TruAi
./start.sh
```

### Method 2: Direct PHP Command
```bash
cd ~/Desktop/Tru.ai/TruAi

# Load API keys from .env
export $(grep -v '^#' .env | xargs)

# Start server
php -S localhost:8080 index.php
```

## API Keys Setup

Your API keys are already in `.env` file:
- Location: `~/Desktop/Tru.ai/TruAi/.env`
- The `start.sh` script automatically loads them

## Access the Application

Once the server starts:
- Open browser: `http://localhost:8080`
- Login with:
  - Username: `admin`
  - Password: `admin123`

## Troubleshooting

If you get "permission denied":
```bash
chmod +x start.sh
```

If stuck in quote prompt:
- Press `Ctrl+C` to cancel
- Start a new command

---

╔══════════════════════════════════════════════════════════════════════════════╗
║                    TruAi HTML Server - Quick Start                           ║
║                    Version 1.0.0 - Production Ready                          ║
╚══════════════════════════════════════════════════════════════════════════════╝

📦 WHAT'S INCLUDED
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ Complete HTML server version of Tru.ai
✅ **FULL AI INTEGRATION** - Real OpenAI & Anthropic API connectivity
✅ Phantom.ai-style encrypted login (AES-256-GCM + SHA-256)
✅ Comprehensive legal notices and terms of service
✅ TruAi branding with repository logos
✅ Cursor-style 3-column dashboard interface
✅ TruAi Core with risk evaluation and tier routing
✅ SQLite database with auto-initialization
✅ Complete security implementation (6 layers)
✅ Full documentation (README, SETUP, ARCHITECTURE, AI_INTEGRATION)

🚀 ONE-COMMAND START
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Set your AI API keys first (required for AI functionality)
$ export OPENAI_API_KEY="sk-your-openai-key"
$ export ANTHROPIC_API_KEY="sk-ant-your-anthropic-key"  # Optional

$ cd TruAi
$ ./start.sh

# OR manually initialize database first:
$ php init-database.php
$ php -S localhost:8080 router.php

Then open: http://localhost:8080

🔑 DEFAULT LOGIN
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Username: admin
Password: admin123

⚠️  IMPORTANT: Change these credentials immediately in production!

📁 FILE STRUCTURE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
TruAi/
├── index.php                    # Main entry point
├── README.md                    # Full documentation
├── SETUP.md                     # Setup & troubleshooting
├── ARCHITECTURE.md              # Architecture diagrams
├── IMPLEMENTATION_SUMMARY.md   # Feature summary
├── backend/                     # PHP backend (7 files)
├── assets/                      # Frontend (CSS, JS, images)
├── database/                    # SQLite (auto-created)
└── logs/                        # Application logs (auto-created)

🔒 SECURITY FEATURES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔐 AES-256-GCM encryption for login
🔑 RSA-2048 key exchange
🔨 SHA-256 + bcrypt password hashing
🛡️  CSRF token protection
🚪 Localhost-only access (configurable)
📝 Comprehensive audit logging
🔒 HTTP-only secure session cookies

📊 STATISTICS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Total Files:       22
Total Lines:       4,057
Backend (PHP):     7 files
Frontend (JS):     5 files
Documentation:     4 files
Database Tables:   7

🎯 KEY FEATURES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
• **REAL AI Integration** - OpenAI GPT-4 & Anthropic Claude
• **Code Generation** - Actual AI-powered code creation
• **Intelligent Chat** - Natural language conversations
• Encrypted Login (Phantom.ai style)
• Legal Notices & Terms of Service
• TruAi Core Risk Evaluation (LOW/MEDIUM/HIGH)
• Multi-Tier AI Routing (Cheap/Mid/High)
• 3-Column Cursor-Style Interface
• Task Management Workflow
• Chat/Conversation System
• Audit Logging & Compliance
• Production-by-Default Deployment

📖 DOCUMENTATION
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
README.md                 - Complete user & developer guide
SETUP.md                  - Installation & troubleshooting
ARCHITECTURE.md          - Visual architecture diagrams
AI_INTEGRATION.md        - AI setup & configuration guide
IMPLEMENTATION_SUMMARY.md - Feature list & implementation details

🧪 VERIFICATION
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ PHP 8.3.6 verified
✅ SQLite extension available
✅ OpenSSL extension available
✅ Database initialization tested
✅ All PHP files syntax-checked
✅ Default admin user created
✅ All 7 database tables created

🔗 API ENDPOINTS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Authentication:
  GET  /api/v1/auth/publickey    - Get encryption public key
  POST /api/v1/auth/login         - Login (encrypted/standard)
  POST /api/v1/auth/logout        - Logout
  GET  /api/v1/auth/status        - Check auth status

AI Testing:
  GET  /api/v1/ai/test            - Test AI API connectivity

Tasks:
  POST /api/v1/task/create        - Create new task
  GET  /api/v1/task/{id}          - Get task details
  POST /api/v1/task/execute       - Execute task (calls real AI)
  POST /api/v1/task/approve       - Approve/reject task

Chat:
  POST /api/v1/chat/message       - Send message (uses real AI)
  GET  /api/v1/chat/conversations - List conversations
  GET  /api/v1/chat/conversation/{id} - Get conversation

Audit:
  GET  /api/v1/audit/logs         - Get audit logs

📜 COPYRIGHT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Tru.ai | TruAi Core | TruAi
Proprietary and intellectual property
My Deme, LLC © 2026 All rights reserved
Developed by DemeWebsolutions.com

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Ready to deploy! Start the server and begin using TruAi HTML Server.
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
