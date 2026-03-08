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
