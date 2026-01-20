# TruAi - AI-Powered Development Assistant

## Overview
TruAi is an AI-powered development assistant with secure authentication, multi-provider AI support, and a modern web interface.

## Project Structure

```
TruAi Git/
├── backend/           # PHP backend services
│   ├── auth.php      # Authentication & session management
│   ├── router.php    # API routing
│   ├── ai_client.php # AI provider integration
│   ├── settings_service.php
│   └── ...
├── assets/           # Frontend assets
│   ├── js/          # JavaScript files
│   ├── css/         # Stylesheets
│   └── images/      # Images and icons
├── index.php         # Main dashboard
├── login-portal.html # Login page
├── router.php        # PHP built-in server router
└── ...
```

## Quick Start

### 1. Start Server
```bash
cd "TruAi Git"
php -S localhost:8080 router.php
```

### 2. Access Application
- **Dashboard**: http://localhost:8080/TruAi/
- **Login**: http://localhost:8080/TruAi/login-portal.html
- **Default Credentials**: `admin` / `admin123`

### 3. Configure API Keys
After logging in:
- Click "Settings" in the center panel
- Enter your OpenAI and/or Anthropic API keys
- Select models and default provider
- Click "Save Settings"

Or use the terminal script:
```bash
./update_api_keys.sh
```

## Features

- ✅ Secure authentication with session management
- ✅ Multi-provider AI support (OpenAI, Anthropic)
- ✅ CSRF protection
- ✅ CORS configured for credentials
- ✅ Modern responsive UI
- ✅ Settings management
- ✅ Task-based AI execution

## Security

- Session-based authentication (1-hour timeout)
- HttpOnly cookies
- CSRF token protection
- CORS with specific origins (credentials enabled)
- Localhost-only access enforcement

## API Endpoints

- `POST /api/v1/auth/login` - User login
- `GET /api/v1/auth/status` - Check authentication
- `POST /api/v1/task/create` - Create AI task
- `GET /api/v1/task/{id}` - Get task status
- `POST /api/v1/task/execute` - Execute task
- `GET /api/v1/settings` - Get user settings
- `POST /api/v1/settings` - Save user settings

## Development

### Requirements
- PHP 7.4+ with built-in server
- SQLite (database/truai.db)
- Modern browser with JavaScript enabled

### File Organization
- All backend logic in `backend/`
- Frontend assets in `assets/`
- Entry points: `index.php`, `router.php`
- Configuration: `backend/config.php`

## License
Copyright My Deme, LLC © 2026
