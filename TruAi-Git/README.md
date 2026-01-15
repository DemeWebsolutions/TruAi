# TruAi HTML Server Version

**Version:** 1.0.0  
**Copyright:** My Deme, LLC © 2026  
**Developed by:** DemeWebsolutions.com

## Overview

TruAi HTML Server Version is a self-contained web application that brings the power of TruAi Core to any modern web browser. This implementation features a Cursor-style interface with a 3-column layout for efficient AI-assisted development workflows.

## Features

### Core Capabilities

- **TruAi Core Integration**: Full AI orchestration with risk evaluation and tier routing
- **Cursor-Style Interface**: Familiar 3-column layout (Review | Workspace | Output)
- **Single Admin Authorization**: Secure, single-user system with full audit logging
- **Production-by-Default**: Smart deployment targeting with manual override options
- **Risk-Based Governance**: Automatic risk classification with appropriate safeguards
- **Multi-Tier AI Routing**: Automatic or manual selection of AI model tiers (Cheap/Mid/High)
- **Local Database**: SQLite-based storage for conversations, tasks, and audit logs
- **Legal Compliance**: Comprehensive legal notices and terms of service on login

### Technical Stack

- **Backend**: PHP 8.2+ (no frameworks)
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Database**: SQLite 3
- **Architecture**: REST API with session-based authentication
- **Security**: CSRF protection, localhost-only access, HTTP-only cookies

## Installation

### AI Integration

TruAi HTML Server now includes **full AI functionality** with support for multiple providers:

### Supported AI Providers

1. **OpenAI** (GPT-3.5, GPT-4, GPT-4 Turbo)
   - Set environment variable: `OPENAI_API_KEY`
   - Used for: Task execution, code generation, chat

2. **Anthropic** (Claude, Claude Sonnet)
   - Set environment variable: `ANTHROPIC_API_KEY`
   - Used for: Advanced reasoning, long-context tasks

### Configuration

```bash
# Set API keys before starting the server
export OPENAI_API_KEY="sk-your-openai-key-here"
export ANTHROPIC_API_KEY="sk-ant-your-anthropic-key-here"

# Start server
php -S localhost:8080 index.php
```

### AI Features

- **Real-time AI Code Generation**: Generate actual code using GPT-4 or Claude
- **Intelligent Chat**: Natural language conversations with AI assistants
- **Multi-tier Routing**: Automatic selection of appropriate model based on task complexity
- **Cost Optimization**: Uses cheaper models for simple tasks, advanced models for complex ones
- **Provider Failover**: Automatically falls back if one provider is unavailable

### Testing AI Connection

Test your AI integration:
```bash
curl http://localhost:8080/api/v1/ai/test
```

This will verify connectivity to OpenAI and Anthropic APIs.

## Requirements

- PHP 8.2 or higher
- SQLite3 extension enabled
- Web server (Apache, Nginx, or PHP built-in server)
- Modern web browser (Chrome, Firefox, Safari, Edge)

### Quick Start

1. **Clone or copy the TruAi directory** to your web server's document root

2. **Configure AI API Keys** (required for full functionality):
   ```bash
   export OPENAI_API_KEY="sk-your-openai-key-here"
   export ANTHROPIC_API_KEY="sk-ant-your-anthropic-key-here"  # Optional
   ```

3. **Start the PHP development server**:
   ```bash
   cd /path/to/Tru.ai/TruAi
   ./start.sh
   ```
   
   **Or manually:**
   ```bash
   php init-database.php  # Initialize database (first time only)
   php -S localhost:8080 router.php
   ```
   
   **Note:** 
   - Use `start.sh` for automatic database initialization
   - Use `router.php` instead of `index.php` to ensure proper routing
   - The `database/` directory is auto-created if it doesn't exist (git doesn't track empty directories)

3. **Access the application**:
   Open your browser and navigate to: `http://localhost:8080`

4. **Login with default credentials**:
   - Username: `admin`
   - Password: `admin123`
   - ⚠️ **Important**: Change these credentials immediately in production

### Production Deployment

For production deployment:

1. Configure your web server to point to the `TruAi` directory
2. Ensure PHP has write permissions to `database/` and `logs/` directories
3. Update default admin credentials via database
4. Set environment variables for API keys:
   ```bash
   export TRUAI_API_KEY="your-api-key"
   export OPENAI_API_KEY="your-openai-key"
   export ANTHROPIC_API_KEY="your-anthropic-key"
   ```
5. Set `APP_ENV=production` in environment
6. Configure HTTPS/TLS for secure connections
7. Review and update `ALLOWED_HOSTS` in `backend/config.php`

## Directory Structure

```
TruAi/
├── index.php                  # Main entry point
├── backend/                   # Backend logic
│   ├── config.php            # Configuration and constants
│   ├── database.php          # Database layer
│   ├── auth.php              # Authentication & authorization
│   ├── router.php            # API router
│   ├── truai_service.php     # TruAi Core service
│   └── chat_service.php      # Chat service
├── frontend/                  # Frontend views (future expansion)
├── assets/                    # Static assets
│   ├── css/
│   │   └── main.css          # Main stylesheet
│   ├── js/
│   │   ├── api.js            # API client
│   │   ├── app.js            # Core app logic
│   │   ├── login.js          # Login page
│   │   └── dashboard.js      # Dashboard interface
│   └── images/               # Images and logos
│       ├── TruAi-Logo.png
│       ├── TruAi-icon.png
│       └── TruAi-transparent-bg.png
├── database/                  # SQLite database (auto-created)
│   └── truai.db
├── logs/                      # Application logs (auto-created)
└── docs/                      # Documentation
    └── README.md             # This file
```

## Usage Guide

### Login Process

1. Navigate to the application URL
2. Read and accept the legal notices and terms of service
3. Enter your credentials
4. Click "Sign In"

### Creating a Task

1. **Enter Prompt**: Type your task description in the "Task Prompt" textarea
2. **Add Context** (optional): Upload relevant files by clicking the file upload area
3. **Select Tier** (optional): Choose AI tier (Auto/Cheap/Mid/High) in the right column
4. **Submit**: Click "Submit to TruAi Core"

### Task Workflow

```
Submit → TruAi Core Evaluation → AI Execution → Review → Approve/Reject → Deploy
```

1. **Automatic Risk Evaluation**: TruAi Core analyzes the prompt and assigns a risk level
2. **Tier Assignment**: Appropriate AI tier is selected based on risk and preferences
3. **AI Execution**: Task is executed and output is generated
4. **Review Phase**: Admin reviews the generated output
5. **Decision**: Accept, Reject, or Save as draft
6. **Deployment**: Deploy to production or staging (if approved)

### Risk Levels

- **LOW**: Simple tasks (formatting, documentation, refactoring)
  - Auto-approved
  - Uses cheap AI tier
  
- **MEDIUM**: Code changes, configuration edits
  - Review required
  - Uses mid AI tier
  
- **HIGH**: Deployments, security changes, production data
  - Manual approval mandatory
  - Uses high AI tier

### AI Tier System

- **Cheap Tier**: `gpt-3.5-turbo` - Fast, cost-effective for simple tasks
- **Mid Tier**: `gpt-4` - Balanced performance for most tasks
- **High Tier**: `gpt-4-turbo` - Maximum capability for complex tasks
- **Auto**: TruAi Core automatically selects appropriate tier

## API Documentation

### Authentication

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "admin123"
}
```

```http
POST /api/v1/auth/logout
```

```http
GET /api/v1/auth/status
```

### Tasks

```http
POST /api/v1/task/create
Content-Type: application/json

{
  "prompt": "Refactor authentication logic",
  "context": { "files": ["auth.php"] },
  "preferred_tier": "auto"
}
```

```http
GET /api/v1/task/{id}
```

```http
POST /api/v1/task/execute
Content-Type: application/json

{
  "task_id": "task_20260115_001"
}
```

```http
POST /api/v1/task/approve
Content-Type: application/json

{
  "task_id": "task_20260115_001",
  "action": "APPROVE",
  "target": "production"
}
```

### Chat

```http
POST /api/v1/chat/message
Content-Type: application/json

{
  "message": "Explain this code",
  "conversation_id": null,
  "model": "auto"
}
```

```http
GET /api/v1/chat/conversations
```

```http
GET /api/v1/chat/conversation/{id}
```

### Audit

```http
GET /api/v1/audit/logs
```

## Security

### Built-in Security Features

- **Localhost-Only Access**: Enforced by default (configurable)
- **Session-Based Authentication**: Secure HTTP-only cookies
- **CSRF Protection**: Token-based protection for all state-changing operations
- **Password Hashing**: bcrypt with automatic salt generation
- **SQL Injection Prevention**: Prepared statements throughout
- **Input Validation**: Server-side validation for all inputs
- **Audit Logging**: Immutable logs of all actions

### Security Best Practices

1. **Change Default Credentials**: Update admin password immediately
2. **Use HTTPS**: Always use TLS in production
3. **Regular Backups**: Backup the SQLite database regularly
4. **Monitor Logs**: Review audit logs for suspicious activity
5. **Update Dependencies**: Keep PHP and extensions updated
6. **Limit Access**: Use firewall rules to restrict access if needed

## Troubleshooting

### Database Connection Errors

```bash
# Ensure database directory exists and is writable
chmod 755 /path/to/TruAi/database
```

### Session Issues

```bash
# Clear PHP sessions
rm -rf /tmp/sess_*
# Or clear browser cookies and try again
```

### Permission Errors

```bash
# Grant write permissions
chmod 755 /path/to/TruAi/logs
chmod 755 /path/to/TruAi/database
```

### API Not Responding

- Verify PHP is running: `php -v`
- Check error logs: `tail -f /path/to/TruAi/logs/error.log`
- Ensure SQLite extension is enabled: `php -m | grep sqlite`

## Development

### Adding New Features

1. **Backend**: Add new routes in `backend/router.php`
2. **Services**: Create service classes in `backend/`
3. **Frontend**: Add UI components in `assets/js/`
4. **Styling**: Update `assets/css/main.css`

### Database Schema

The database is automatically initialized on first run. Schema includes:

- **users**: User accounts and authentication
- **conversations**: Chat conversation metadata
- **messages**: Individual chat messages
- **tasks**: TruAi Core tasks
- **executions**: Task execution records
- **artifacts**: Generated code/output artifacts
- **audit_logs**: Immutable audit trail

## Legal & Licensing

**Copyright Notice**  
Tru.ai | TruAi Core | TruAi - Proprietary and intellectual property  
My Deme, LLC © 2026 All rights reserved.  
Developed by DemeWebsolutions.com

This software is proprietary and confidential. Unauthorized copying, distribution, or use is strictly prohibited.

## Support

For issues, questions, or support:
- Review this documentation
- Check the audit logs for error details
- Contact: DemeWebsolutions.com

## Version History

### v1.0.0 (2026-01-15)
- Initial release
- Core TruAi functionality
- Cursor-style interface
- Risk-based governance
- Multi-tier AI routing
- Comprehensive legal notices
- SQLite database integration
- Full audit logging

---

**TruAi HTML Server Version** - Super Admin AI Platform
