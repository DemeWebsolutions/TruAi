#!/bin/bash

echo "╔═══════════════════════════════════════════════════════╗"
echo "║       TruAi Complete Test Suite Execution             ║"
echo "╚═══════════════════════════════════════════════════════╝"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test counter
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Function to run test
run_test() {
    local test_name="$1"
    local test_command="$2"
    
    echo -n "Running $test_name... "
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    if eval "$test_command" > /dev/null 2>&1; then
        echo -e "${GREEN}✅ PASSED${NC}"
        PASSED_TESTS=$((PASSED_TESTS + 1))
    else
        echo -e "${RED}❌ FAILED${NC}"
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi
}

# 1. System Tests
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "1️⃣  SYSTEM TESTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

run_test "Full System Test Suite" "php tests/full-system-test.php"
run_test "Settings Wiring Test" "php test-settings-wiring.php"
run_test "Login Flow Test" "./test-login-flow.sh"

# 2. File Structure Tests
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "2️⃣  FILE STRUCTURE TESTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

required_files=(
    "index.php"
    "login-portal.html"
    "router.php"
    "backend/config.php"
    "backend/database.php"
    "backend/auth.php"
    "backend/router.php"
    "backend/truai_service.php"
    "backend/chat_service.php"
    "backend/ai_client.php"
    "backend/settings_service.php"
    "assets/js/api.js"
    "assets/js/dashboard.js"
    "assets/css/main.css"
)

for file in "${required_files[@]}"; do
    run_test "File: $file" "test -f $file"
done

# 3. Database Tests
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "3️⃣  DATABASE TESTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ -f "database/truai.db" ]; then
    echo -e "${GREEN}✅ Database file exists${NC}"
    
    # Check tables
    tables=("users" "sessions" "conversations" "messages" "tasks" "executions" "artifacts" "audit_logs" "settings")
    for table in "${tables[@]}"; do
        run_test "Table: $table" "sqlite3 database/truai.db \"SELECT name FROM sqlite_master WHERE type='table' AND name='$table';\" | grep -q $table"
    done
else
    echo -e "${RED}❌ Database file missing${NC}"
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi

# 4. Security Tests
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "4️⃣  SECURITY TESTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check for sensitive files in .gitignore
run_test ".gitignore includes database/" "grep -q 'database/' .gitignore"
run_test ".gitignore includes .env" "grep -q '.env' .gitignore"

# Check file permissions (if on Unix)
if [[ "$OSTYPE" == "linux-gnu"* ]] || [[ "$OSTYPE" == "darwin"* ]]; then
    run_test "Database directory writable" "test -w database/"
fi

# 5. Summary
echo ""
echo "╔═══════════════════════════════════════════════════════╗"
echo "║                    Test Summary                       ║"
echo "╚═══════════════════════════════════════════════════════╝"
echo ""
echo "Total Tests: $TOTAL_TESTS"
echo -e "${GREEN}Passed: $PASSED_TESTS${NC}"
echo -e "${RED}Failed: $FAILED_TESTS${NC}"
echo ""

SUCCESS_RATE=$((PASSED_TESTS * 100 / TOTAL_TESTS))
echo "Success Rate: $SUCCESS_RATE%"
echo ""

if [ $FAILED_TESTS -eq 0 ]; then
    echo -e "${GREEN}🎉 All tests passed!${NC}"
    exit 0
else
    echo -e "${YELLOW}⚠️  Some tests failed. Review output above.${NC}"
    exit 1
fi
