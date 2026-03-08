#!/bin/bash
# TruAi HTML Server - Startup Script with API Key Configuration

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🚀 TruAi HTML Server - Startup"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Check for .env file
if [ -f ".env" ]; then
    echo "📋 Loading API keys from .env file..."
    export $(grep -v '^#' .env | xargs)
    echo "✅ Environment variables loaded"
else
    echo "⚠️  No .env file found"
    echo ""
    echo "📝 To set API keys, you can:"
    echo "   1. Create a .env file in this directory"
    echo "   2. Or set them in your terminal before running this script"
    echo ""
    echo "Example .env file:"
    echo "   OPENAI_API_KEY=sk-your-openai-key"
    echo "   ANTHROPIC_API_KEY=sk-ant-your-anthropic-key"
    echo ""
fi

# Check if API keys are set
if [ -z "$OPENAI_API_KEY" ] && [ -z "$ANTHROPIC_API_KEY" ]; then
    echo "⚠️  WARNING: No API keys configured!"
    echo "   AI features will not work without API keys."
    echo "   Set OPENAI_API_KEY and/or ANTHROPIC_API_KEY"
    echo ""
fi

# Check PHP
if ! command -v php &> /dev/null; then
    echo "❌ PHP not found. Please install PHP 8.0+"
    exit 1
fi

PHP_VERSION=$(php -r 'echo PHP_VERSION;' | cut -d. -f1,2)
echo "✅ PHP version: $(php --version | head -1)"
echo ""

# Create necessary directories (git doesn't track empty directories)
echo "📁 Creating necessary directories..."
mkdir -p database logs
chmod 755 database logs 2>/dev/null || true

# Initialize database if it doesn't exist
if [ ! -f "database/truai.db" ]; then
    echo "🗄️  Initializing database..."
    php -r "
    require_once 'backend/config.php';
    require_once 'backend/database.php';
    \$db = Database::getInstance();
    echo '✅ Database initialized successfully\n';
    "
    if [ $? -eq 0 ]; then
        echo "✅ Database created and initialized"
    else
        echo "⚠️  Database initialization had issues, but continuing..."
    fi
    echo ""
fi

# Start server
echo "🚀 Starting TruAi HTML Server..."
echo "📍 Location: $SCRIPT_DIR"
echo "🌐 Server will be available at: http://localhost:8001"
echo ""
echo "Press Ctrl+C to stop the server"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

php -S localhost:8001 router.php
