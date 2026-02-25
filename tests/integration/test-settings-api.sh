#!/bin/bash
# Settings API Endpoint Test
# Tests the API endpoints via HTTP requests

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🌐 SETTINGS API ENDPOINT TEST"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

BASE_URL="http://localhost:8001/api/v1"

# Check if server is running
echo "1. Checking if server is running..."
if curl -s "$BASE_URL/auth/status" > /dev/null 2>&1; then
    echo "   ✅ Server is running"
else
    echo "   ❌ Server is not running. Please start with: ./start.sh"
    exit 1
fi

# Test authentication (login first)
echo ""
echo "2. Testing Authentication..."
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
    -H "Content-Type: application/json" \
    -d '{"username":"admin","password":"admin123"}')

if echo "$LOGIN_RESPONSE" | grep -q "success"; then
    echo "   ✅ Login successful"
    # Extract CSRF token if present
    CSRF_TOKEN=$(echo "$LOGIN_RESPONSE" | grep -o '"csrf_token":"[^"]*"' | cut -d'"' -f4)
else
    echo "   ❌ Login failed"
    echo "   Response: $LOGIN_RESPONSE"
    exit 1
fi

# Test GET settings
echo ""
echo "3. Testing GET /api/v1/settings..."
SETTINGS_RESPONSE=$(curl -s -X GET "$BASE_URL/settings" \
    -H "Content-Type: application/json" \
    -b "PHPSESSID=$(php -r 'session_start(); echo session_id();')")

if echo "$SETTINGS_RESPONSE" | grep -q "settings"; then
    echo "   ✅ GET settings successful"
    echo "   Response preview: $(echo "$SETTINGS_RESPONSE" | head -c 100)..."
else
    echo "   ❌ GET settings failed"
    echo "   Response: $SETTINGS_RESPONSE"
fi

# Test POST settings
echo ""
echo "4. Testing POST /api/v1/settings..."
SAVE_RESPONSE=$(curl -s -X POST "$BASE_URL/settings" \
    -H "Content-Type: application/json" \
    -d '{
        "settings": {
            "editor": {
                "fontSize": 16,
                "fontFamily": "Menlo",
                "tabSize": 2
            },
            "ai": {
                "model": "gpt-3.5-turbo",
                "temperature": 0.5
            }
        }
    }' \
    -b "PHPSESSID=$(php -r 'session_start(); echo session_id();')")

if echo "$SAVE_RESPONSE" | grep -q "success"; then
    echo "   ✅ POST settings successful"
else
    echo "   ⚠️  POST settings response: $SAVE_RESPONSE"
fi

# Test reset settings
echo ""
echo "5. Testing POST /api/v1/settings/reset..."
RESET_RESPONSE=$(curl -s -X POST "$BASE_URL/settings/reset" \
    -H "Content-Type: application/json" \
    -b "PHPSESSID=$(php -r 'session_start(); echo session_id();')")

if echo "$RESET_RESPONSE" | grep -q "success"; then
    echo "   ✅ Reset settings successful"
else
    echo "   ⚠️  Reset settings response: $RESET_RESPONSE"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ API ENDPOINT TESTS COMPLETE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Note: Full authentication requires session cookies."
echo "For complete testing, use browser DevTools Network tab."
