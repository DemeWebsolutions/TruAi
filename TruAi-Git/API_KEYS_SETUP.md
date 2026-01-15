# API Keys Configuration Guide

## Location for API Keys

The API keys are read from **environment variables** by the PHP application.

## Two Ways to Set API Keys

### Option 1: Environment Variables (Terminal Session)

**Location:** Set in your terminal **before** starting the server

```bash
# Navigate to TruAi directory
cd ~/Desktop/Tru.ai/TruAi

# Set API keys
export OPENAI_API_KEY="sk-your-openai-key-here"
export ANTHROPIC_API_KEY="sk-ant-your-anthropic-key-here"

# Start server
php -S localhost:8080 index.php
```

**Note:** These environment variables only last for the current terminal session.

### Option 2: .env File (Persistent)

**Location:** `~/Desktop/Tru.ai/TruAi/.env`

1. Create a `.env` file in the TruAi directory:
   ```bash
   cd ~/Desktop/Tru.ai/TruAi
   nano .env
   ```

2. Add your API keys:
   ```
   OPENAI_API_KEY=sk-your-openai-key-here
   ANTHROPIC_API_KEY=sk-ant-your-anthropic-key-here
   APP_ENV=development
   ```

3. Use the startup script (automatically loads .env):
   ```bash
   ./start.sh
   ```

## How the Application Reads API Keys

The application reads API keys from environment variables in `backend/config.php`:

```php
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');
define('ANTHROPIC_API_KEY', getenv('ANTHROPIC_API_KEY') ?: '');
```

## Quick Start with API Keys

### Method 1: Using start.sh (Recommended)
```bash
cd ~/Desktop/Tru.ai/TruAi

# Create .env file with your keys
echo "OPENAI_API_KEY=sk-your-key" > .env
echo "ANTHROPIC_API_KEY=sk-ant-your-key" >> .env

# Start server (automatically loads .env)
./start.sh
```

### Method 2: Manual Export
```bash
cd ~/Desktop/Tru.ai/TruAi

# Export keys
export OPENAI_API_KEY="sk-your-openai-key"
export ANTHROPIC_API_KEY="sk-ant-your-anthropic-key"

# Start server
php -S localhost:8080 index.php
```

## Testing API Keys

After starting the server, test if API keys are working:

```bash
curl http://localhost:8080/api/v1/ai/test
```

Expected response:
```json
{
  "success": true,
  "results": {
    "openai": {
      "status": "success",
      "message": "OpenAI API connected successfully"
    },
    "anthropic": {
      "status": "success",
      "message": "Anthropic API connected successfully"
    }
  }
}
```

## Security Notes

- ⚠️ **Never commit .env file to git** (it's in .gitignore)
- ⚠️ **Never share your API keys**
- ⚠️ **Use different keys for development and production**
- ✅ **Keep .env file permissions restricted** (chmod 600 .env)

## File Locations Summary

- **Application Directory:** `~/Desktop/Tru.ai/TruAi/`
- **.env File Location:** `~/Desktop/Tru.ai/TruAi/.env`
- **Config File:** `~/Desktop/Tru.ai/TruAi/backend/config.php`
- **Startup Script:** `~/Desktop/Tru.ai/TruAi/start.sh`
