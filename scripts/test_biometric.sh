#!/usr/bin/env bash
# ============================================================
#  TruAi — Biometric Authentication Verification
#  UBSAS v2.0
#
#  Verifies that biometric setup is complete and functional.
#  Usage: bash scripts/test_biometric.sh
# ============================================================
set -euo pipefail

CONFIG_DIR="$HOME/.demewebsolutions"
NATIVE_HOST_ID="com.demewebsolutions.biometric"
OS_TYPE="$(uname -s)"

# ── Colours ────────────────────────────────────────────────────────────────
GREEN='\033[0;32m'; RED='\033[0;31m'; YELLOW='\033[0;33m'
BOLD='\033[1m'; RESET='\033[0m'
pass() { echo -e "  ${GREEN}[PASS]${RESET} $*"; PASSED=$((PASSED+1)); }
fail() { echo -e "  ${RED}[FAIL]${RESET} $*"; FAILED=$((FAILED+1)); }
warn() { echo -e "  ${YELLOW}[WARN]${RESET} $*"; WARNED=$((WARNED+1)); }

PASSED=0; FAILED=0; WARNED=0

echo -e "${BOLD}"
echo "╔══════════════════════════════════════════════════════════╗"
echo "║    TruAi — Biometric Verification (UBSAS v2.0)          ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo -e "${RESET}"

# ── 1. Configuration directory ─────────────────────────────────────────────
echo "1. Configuration"

if [[ -d "$CONFIG_DIR" ]]; then
    pass "Config directory exists: $CONFIG_DIR"
else
    fail "Config directory missing: $CONFIG_DIR — run scripts/setup_biometric_auth.sh"
fi

if [[ -f "$CONFIG_DIR/config.json" ]]; then
    pass "config.json present"
    if command -v python3 &>/dev/null; then
        python3 -c "import json,sys; json.load(open('$CONFIG_DIR/config.json'))" 2>/dev/null \
            && pass "config.json is valid JSON" \
            || fail "config.json is malformed"
    fi
else
    fail "config.json missing — run scripts/setup_biometric_auth.sh"
fi

# ── 2. Biometric hardware ──────────────────────────────────────────────────
echo ""
echo "2. Biometric Hardware"

case "$OS_TYPE" in
    Darwin)
        if bioutil -r 2>/dev/null | grep -q "Touch ID"; then
            pass "Touch ID hardware present"
        else
            warn "Touch ID not detected (device may not have Touch ID)"
        fi
        ;;
    Linux)
        if command -v fprintd-verify &>/dev/null; then
            pass "fprintd installed"
        else
            warn "fprintd not installed — biometric unavailable (apt install fprintd)"
        fi
        ;;
    *)
        warn "Unknown OS ($OS_TYPE) — biometric check skipped"
        ;;
esac

# ── 3. Keychain ────────────────────────────────────────────────────────────
echo ""
echo "3. Keychain Storage"

case "$OS_TYPE" in
    Darwin)
        if command -v security &>/dev/null; then
            pass "Keychain CLI (security) present"
            # Check if TruAi entry exists
            security find-generic-password -s "com.demewebsolutions.truai" &>/dev/null \
                && pass "TruAi keychain entry found" \
                || warn "TruAi keychain entry not found — run scripts/setup_biometric_auth.sh"
        else
            fail "Keychain CLI not found"
        fi
        ;;
    Linux)
        if command -v secret-tool &>/dev/null; then
            pass "libsecret (secret-tool) present"
            secret-tool lookup service "com.demewebsolutions.truai" username "" &>/dev/null \
                && pass "TruAi libsecret entry found" \
                || warn "TruAi libsecret entry not found — run scripts/setup_biometric_auth.sh"
        else
            warn "secret-tool not installed — keychain unavailable (apt install libsecret-tools)"
        fi
        ;;
esac

# ── 4. Native messaging host ──────────────────────────────────────────────
echo ""
echo "4. Native Messaging Host"

check_nmh() {
    local DIR="$1" BROWSER="$2"
    local MANIFEST="$DIR/$NATIVE_HOST_ID.json"
    if [[ -f "$MANIFEST" ]]; then
        pass "NMH manifest found for $BROWSER"
        if command -v python3 &>/dev/null; then
            python3 -c "import json,sys; json.load(open('$MANIFEST'))" 2>/dev/null \
                && pass "$BROWSER manifest is valid JSON" \
                || fail "$BROWSER manifest is malformed"
        fi
    else
        warn "NMH manifest not found for $BROWSER ($MANIFEST)"
    fi
}

case "$OS_TYPE" in
    Darwin)
        check_nmh "$HOME/Library/Application Support/Google/Chrome/NativeMessagingHosts" "Chrome"
        check_nmh "$HOME/Library/Application Support/Mozilla/NativeMessagingHosts" "Firefox"
        check_nmh "$HOME/Library/Application Support/Microsoft Edge/NativeMessagingHosts" "Edge"
        ;;
    Linux)
        check_nmh "$HOME/.config/google-chrome/NativeMessagingHosts" "Chrome"
        check_nmh "$HOME/.mozilla/native-messaging-hosts" "Firefox"
        ;;
esac

# ── 5. Backend API reachable ──────────────────────────────────────────────
echo ""
echo "5. Backend API"

if command -v curl &>/dev/null; then
    HEALTH=$(curl -s --max-time 3 http://127.0.0.1:8001/TruAi/api/v1/health 2>/dev/null || echo "")
    if [[ -n "$HEALTH" ]]; then
        pass "Backend API reachable at http://127.0.0.1:8001"
        echo "$HEALTH" | grep -q '"ready":true' && pass "Health endpoint reports ready" \
            || warn "Health endpoint did not report ready"
    else
        warn "Backend API not reachable — is the server running? (./start.sh)"
    fi

    METHODS=$(curl -s --max-time 3 http://127.0.0.1:8001/TruAi/api/v1/auth/methods 2>/dev/null || echo "")
    if [[ -n "$METHODS" ]]; then
        pass "/auth/methods endpoint reachable"
        echo "$METHODS" | grep -q '"methods"' && pass "Methods response is valid" \
            || warn "Methods response may be unexpected: $METHODS"
    else
        warn "/auth/methods not reachable"
    fi
else
    warn "curl not available — backend API check skipped"
fi

# ── Summary ────────────────────────────────────────────────────────────────
echo ""
echo -e "${BOLD}=== Verification Summary ===${RESET}"
echo ""
echo "  Passed  : $PASSED"
echo "  Warnings: $WARNED"
echo "  Failed  : $FAILED"
echo ""

if [[ $FAILED -eq 0 ]]; then
    echo -e "${GREEN}✓ Biometric setup verification complete${RESET}"
    echo ""
    echo "  Next: http://127.0.0.1:8001/TruAi/ubsas-entrance.html"
else
    echo -e "${RED}✗ $FAILED check(s) failed — run scripts/setup_biometric_auth.sh${RESET}"
    exit 1
fi
