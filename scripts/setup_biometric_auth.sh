#!/usr/bin/env bash
# ============================================================
#  TruAi — Biometric Authentication Setup
#  UBSAS v2.0 — Unified Biometric Sovereign Authentication
#
#  Usage: bash scripts/setup_biometric_auth.sh [--app truai|gemini|phantom|all]
# ============================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")/.." && pwd)"   # repo root
CONFIG_DIR="$HOME/.demewebsolutions"
NATIVE_HOST_ID="com.demewebsolutions.biometric"
APP_FILTER="${1:-all}"

# ── Colours ────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[0;33m'
BLUE='\033[0;34m'; BOLD='\033[1m'; RESET='\033[0m'
ok()   { echo -e "${GREEN}[OK]${RESET}  $*"; }
warn() { echo -e "${YELLOW}[WARN]${RESET} $*"; }
err()  { echo -e "${RED}[ERR]${RESET}  $*" >&2; }
info() { echo -e "${BLUE}[INFO]${RESET} $*"; }

echo -e "${BOLD}"
echo "╔══════════════════════════════════════════════════════════╗"
echo "║   TruAi — Biometric Authentication Setup (UBSAS v2.0)   ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo -e "${RESET}"

# ── Detect OS ──────────────────────────────────────────────────────────────
OS_TYPE="$(uname -s)"
case "$OS_TYPE" in
    Darwin)
        BIOMETRIC_TYPE="Touch ID / Face ID"
        KEYCHAIN_CMD="security"
        ok "Detected macOS"
        ;;
    Linux)
        BIOMETRIC_TYPE="Fingerprint (fprintd / libsecret)"
        KEYCHAIN_CMD="secret-tool"
        ok "Detected Linux"
        ;;
    *)
        err "Unsupported operating system: $OS_TYPE"
        exit 1
        ;;
esac

# ── Prerequisites ──────────────────────────────────────────────────────────
echo ""
info "Checking prerequisites…"

check_biometric_hardware() {
    case "$OS_TYPE" in
        Darwin)
            if bioutil -r 2>/dev/null | grep -q "Touch ID"; then
                ok "Touch ID hardware detected"
                return 0
            else
                warn "Touch ID not detected — biometric will be unavailable"
                return 1
            fi
            ;;
        Linux)
            if command -v fprintd-verify &>/dev/null; then
                ok "fprintd detected"
                return 0
            else
                warn "fprintd not found — install with: sudo apt install fprintd libpam-fprintd"
                return 1
            fi
            ;;
    esac
    return 1
}

BIOMETRIC_AVAILABLE=false
check_biometric_hardware && BIOMETRIC_AVAILABLE=true || true

# Check keychain / libsecret
KEYCHAIN_AVAILABLE=false
case "$OS_TYPE" in
    Darwin)
        command -v security &>/dev/null && { ok "Keychain (security) available"; KEYCHAIN_AVAILABLE=true; } || warn "Keychain CLI not found"
        ;;
    Linux)
        command -v secret-tool &>/dev/null && { ok "libsecret (secret-tool) available"; KEYCHAIN_AVAILABLE=true; } \
            || warn "secret-tool not found — install with: sudo apt install libsecret-tools"
        ;;
esac

# ── Step 1: Configuration directory ───────────────────────────────────────
echo ""
info "Step 1: Creating configuration directory…"
mkdir -p "$CONFIG_DIR"
chmod 700 "$CONFIG_DIR"

cat > "$CONFIG_DIR/config.json" <<JSONEOF
{
  "version": "2.0",
  "apps": {
    "truai":   { "url": "http://127.0.0.1:8001", "enabled": true  },
    "gemini":  { "url": "http://127.0.0.1:5000", "enabled": false },
    "phantom": { "url": "http://127.0.0.1:8080", "enabled": false }
  },
  "biometric_available": ${BIOMETRIC_AVAILABLE},
  "keychain_available":  ${KEYCHAIN_AVAILABLE},
  "os_type": "$OS_TYPE"
}
JSONEOF
chmod 600 "$CONFIG_DIR/config.json"
ok "Configuration written to $CONFIG_DIR/config.json"

# ── Step 2: Store credentials in keychain ─────────────────────────────────
echo ""
info "Step 2: Configure keychain credentials…"

store_credentials_macos() {
    local SERVICE="$1" USERNAME="$2" PASSWORD="$3"
    security add-generic-password \
        -a "$USERNAME" \
        -s "$SERVICE" \
        -w "$PASSWORD" \
        -T "/usr/bin/security" \
        -U 2>/dev/null && ok "Stored $SERVICE in macOS Keychain" || warn "Could not store $SERVICE credentials"
}

store_credentials_linux() {
    local SERVICE="$1" USERNAME="$2" PASSWORD="$3"
    echo -n "$PASSWORD" | secret-tool store \
        --label="$SERVICE ($USERNAME)" \
        service "$SERVICE" \
        username "$USERNAME" 2>/dev/null && ok "Stored $SERVICE in libsecret" || warn "Could not store $SERVICE credentials"
}

configure_app() {
    local APP_NAME="$1"
    [[ "$APP_FILTER" != "all" && "$APP_FILTER" != "$APP_NAME" ]] && return

    echo ""
    echo "--- $APP_NAME setup ---"
    read -rp "  Enable biometric/keychain login for $APP_NAME? (y/n): " ENABLE
    [[ "${ENABLE:-n}" != "y" ]] && warn "Skipped $APP_NAME" && return

    read -rp "  Username: " USERNAME
    read -rsp "  Password: " PASSWORD
    echo ""

    local SERVICE="com.demewebsolutions.$APP_NAME"

    if [[ "$KEYCHAIN_AVAILABLE" == "true" ]]; then
        case "$OS_TYPE" in
            Darwin) store_credentials_macos "$SERVICE" "$USERNAME" "$PASSWORD" ;;
            Linux)  store_credentials_linux "$SERVICE" "$USERNAME" "$PASSWORD" ;;
        esac
    else
        warn "Keychain not available — credentials not stored"
    fi
}

configure_app "truai"
configure_app "gemini"
configure_app "phantom"

# ── Step 3: Install native messaging host ─────────────────────────────────
echo ""
info "Step 3: Installing native messaging host for browser extension…"

NATIVE_HOST_SRC="$SCRIPT_DIR/native_host/com.demewebsolutions.biometric.json"
NATIVE_HOST_SCRIPT="$SCRIPT_DIR/native_host/demewebsolutions_biometric_host.php"

if [[ ! -f "$NATIVE_HOST_SRC" ]]; then
    warn "Native host manifest not found at $NATIVE_HOST_SRC — skipping"
else
    case "$OS_TYPE" in
        Darwin)
            # Chrome/Chromium
            NMH_DIR="$HOME/Library/Application Support/Google/Chrome/NativeMessagingHosts"
            mkdir -p "$NMH_DIR"
            cp "$NATIVE_HOST_SRC" "$NMH_DIR/$NATIVE_HOST_ID.json"
            # Update path in manifest
            sed -i '' "s|/path/to/demewebsolutions_biometric_host.php|$NATIVE_HOST_SCRIPT|g" \
                "$NMH_DIR/$NATIVE_HOST_ID.json" 2>/dev/null || true
            ok "Native host installed for Chrome (macOS)"

            # Firefox
            NMH_FF_DIR="$HOME/Library/Application Support/Mozilla/NativeMessagingHosts"
            mkdir -p "$NMH_FF_DIR"
            cp "$NMH_DIR/$NATIVE_HOST_ID.json" "$NMH_FF_DIR/"
            ok "Native host installed for Firefox (macOS)"
            ;;
        Linux)
            NMH_DIR="$HOME/.config/google-chrome/NativeMessagingHosts"
            mkdir -p "$NMH_DIR"
            cp "$NATIVE_HOST_SRC" "$NMH_DIR/$NATIVE_HOST_ID.json"
            sed -i "s|/path/to/demewebsolutions_biometric_host.php|$NATIVE_HOST_SCRIPT|g" \
                "$NMH_DIR/$NATIVE_HOST_ID.json" 2>/dev/null || true
            ok "Native host installed for Chrome (Linux)"

            NMH_FF_DIR="$HOME/.mozilla/native-messaging-hosts"
            mkdir -p "$NMH_FF_DIR"
            cp "$NMH_DIR/$NATIVE_HOST_ID.json" "$NMH_FF_DIR/"
            ok "Native host installed for Firefox (Linux)"
            ;;
    esac
fi

# ── Step 4: Final status summary ───────────────────────────────────────────
echo ""
echo -e "${BOLD}=== Setup Complete ===${RESET}"
echo ""
echo "  Biometric hardware : $([ "$BIOMETRIC_AVAILABLE" = "true" ] && echo "✓ Available ($BIOMETRIC_TYPE)" || echo "✗ Not detected")"
echo "  Keychain storage   : $([ "$KEYCHAIN_AVAILABLE"  = "true" ] && echo "✓ Available" || echo "✗ Not available")"
echo "  Config directory   : $CONFIG_DIR"
echo ""
echo "Next steps:"
echo "  1. Run:  bash scripts/test_biometric.sh"
echo "  2. Load the browser extension from: browser_extension/"
echo "  3. Visit: http://127.0.0.1:8001/TruAi/ubsas-entrance.html"
echo ""
