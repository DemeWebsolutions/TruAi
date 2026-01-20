#!/bin/bash
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🧪 TRUAI LOGIN TEST SUITE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Test 1: Server Status
echo "1️⃣  Testing Server Status..."
if curl -s http://localhost:8080/ > /dev/null; then
    echo "   ✅ Server is running"
else
    echo "   ❌ Server is not responding"
    exit 1
fi
echo ""

# Test 2: API Base Configuration
echo "2️⃣  Testing API Configuration..."
API_BASE=$(curl -s http://localhost:8080/ | grep -o "API_BASE:.*" | head -1)
if echo "$API_BASE" | grep -q "/api/v1"; then
    echo "   ✅ API_BASE configured correctly: $API_BASE"
else
    echo "   ❌ API_BASE misconfigured: $API_BASE"
fi
echo ""

# Test 3: Login Endpoint
echo "3️⃣  Testing Login Endpoint..."
LOGIN_RESPONSE=$(curl -X POST -s http://localhost:8080/api/v1/auth/login \
    -H "Content-Type: application/json" \
    -d '{"username":"admin","password":"admin123"}')

if echo "$LOGIN_RESPONSE" | grep -q '"success":true'; then
    echo "   ✅ Login endpoint working"
    echo "   Response: $(echo $LOGIN_RESPONSE | cut -c1-80)..."
else
    echo "   ❌ Login endpoint failed"
    echo "   Response: $LOGIN_RESPONSE"
fi
echo ""

# Test 4: Invalid Credentials
echo "4️⃣  Testing Invalid Credentials..."
INVALID_RESPONSE=$(curl -X POST -s http://localhost:8080/api/v1/auth/login \
    -H "Content-Type: application/json" \
    -d '{"username":"admin","password":"wrong"}')

if echo "$INVALID_RESPONSE" | grep -q '"error"'; then
    echo "   ✅ Invalid credentials properly rejected"
else
    echo "   ⚠️  Unexpected response: $INVALID_RESPONSE"
fi
echo ""

# Test 5: Static Assets
echo "5️⃣  Testing Static Assets..."
ASSETS=("/assets/css/main.css" "/assets/js/login.js" "/assets/js/api.js" "/assets/images/TruAi-transparent-bg.png")
for asset in "${ASSETS[@]}"; do
    STATUS=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost:8080$asset")
    if [ "$STATUS" = "200" ]; then
        echo "   ✅ $asset (HTTP $STATUS)"
    else
        echo "   ❌ $asset (HTTP $STATUS)"
    fi
done
echo ""

# Test 6: Database
echo "6️⃣  Testing Database..."
if [ -f "database/truai.db" ]; then
    USER_COUNT=$(sqlite3 database/truai.db "SELECT COUNT(*) FROM users;" 2>/dev/null)
    if [ "$USER_COUNT" -gt 0 ]; then
        echo "   ✅ Database exists with $USER_COUNT user(s)"
    else
        echo "   ⚠️  Database exists but no users found"
    fi
else
    echo "   ❌ Database file not found"
fi
echo ""

# Test 7: API.js Endpoints
echo "7️⃣  Testing API.js Endpoint Paths..."
API_JS=$(curl -s http://localhost:8080/assets/js/api.js)
if echo "$API_JS" | grep -q "request('/auth/login'"; then
    echo "   ✅ Login endpoint path correct (no double /api/v1)"
else
    echo "   ❌ Login endpoint path may have double /api/v1"
fi
echo ""

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ TEST SUITE COMPLETE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
