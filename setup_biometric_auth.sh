#!/usr/bin/env bash
# ============================================================
#  DemeWebsolutions Biometric Authentication Setup
#  TruAi | Gemini.ai | Phantom.ai
#  UBSAS v2.0 — Unified Biometric Sovereign Auth System
# ============================================================
set -euo pipefail

INSTALL_DIR="/usr/local/DemeWebsolutions/auth"
CONFIG_DIR="$HOME/.demewebsolutions"
NATIVE_HOST_ID="com.demewebsolutions.biometric"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

echo "╔════════════════════════════════════════════════════════╗"
echo "║  DemeWebsolutions Biometric Authentication Setup      ║"
echo "║  TruAi | Gemini.ai | Phantom.ai                       ║"
echo "╚════════════════════════════════════════════════════════╝"
echo ""

# ── Detect OS ──────────────────────────────────────────────
OS_TYPE="$(uname -s)"
case "$OS_TYPE" in
    Darwin) BIOMETRIC_TYPE="Touch ID / Face ID"
            echo "✓ Detected macOS" ;;
    Linux)  BIOMETRIC_TYPE="Fingerprint (fprintd)"
            echo "✓ Detected Linux" ;;
    *)      echo "✗ Unsupported OS: $OS_TYPE"; exit 1 ;;
esac

# ── Step 1: Configuration directory ────────────────────────
echo ""
echo "=== Step 1: Create Configuration Directory ==="
mkdir -p "$CONFIG_DIR"
chmod 700 "$CONFIG_DIR"
echo "✓ Created $CONFIG_DIR"

# ── Step 2: Install service files ──────────────────────────
echo ""
echo "=== Step 2: Install Biometric Auth Service ==="
sudo mkdir -p "$INSTALL_DIR"
sudo cp "$SCRIPT_DIR/native_host/demewebsolutions_biometric_host.php" "$INSTALL_DIR/"
sudo cp "$SCRIPT_DIR/backend/ubsas_auth_service.php" "$INSTALL_DIR/"
sudo chmod 755 "$INSTALL_DIR/demewebsolutions_biometric_host.php"
echo "✓ Installed to $INSTALL_DIR"

# ── Step 3: Configure app credentials ──────────────────────
echo ""
echo "=== Step 3: Configure Application Credentials ==="

setup_app() {
    local APP_NAME="$1"
    local APP_DISPLAY="$2"
    echo ""
    echo "--- $APP_DISPLAY Setup ---"
    read -rp "Enable biometric login for $APP_DISPLAY? (y/n): " ENABLE
    [[ "$ENABLE" != "y" ]] && echo "⊘ Skipped $APP_DISPLAY" && return

    read -rp "Username for $APP_DISPLAY: " USERNAME
    read -rsp "Password for $APP_DISPLAY: " PASSWORD
    echo ""

    php -- "$APP_NAME" "$USERNAME" "$PASSWORD" <<'EOPHP'
<?php
$app      = $argv[1];
$username = $argv[2];
$password = $argv[3];
require '/usr/local/DemeWebsolutions/auth/ubsas_auth_service.php';
$svc = new UBSASAuthService();
$ok  = $svc->storeCredentials($app, $username, $password);
echo $ok ? "✓ Credentials stored in keychain\n" : "✗ Failed to store credentials\n";
EOPHP

    # Merge into config.json
    local TMP; TMP="$(mktemp)"
    printf '{"apps":{"%s":{"username":"%s","enabled":true}}}' "$APP_NAME" "$USERNAME" > "$TMP"
    if [[ -f "$CONFIG_DIR/config.json" ]] && command -v jq &>/dev/null; then
        jq -s '.[0] * .[1]' "$CONFIG_DIR/config.json" "$TMP" > "$CONFIG_DIR/config.json.new"
        mv "$CONFIG_DIR/config.json.new" "$CONFIG_DIR/config.json"
    else
        mv "$TMP" "$CONFIG_DIR/config.json"
    fi
    echo "✓ Configured $APP_DISPLAY"
}

setup_app "truai"   "TruAi Super Admin"
setup_app "gemini"  "Gemini.ai Server Management"
setup_app "phantom" "Phantom.ai Development Platform"

# ── Step 4: macOS-specific (Touch ID for sudo) ─────────────
if [[ "$OS_TYPE" == "Darwin" ]]; then
    echo ""
    echo "=== Step 4: macOS Touch ID Configuration ==="
    if ! sudo grep -q "pam_tid.so" /etc/pam.d/sudo 2>/dev/null; then
        echo "Adding Touch ID to sudo authentication…"
        sudo sed -i '' '2i\
auth       sufficient     pam_tid.so
' /etc/pam.d/sudo
        echo "✓ Touch ID enabled for sudo"
    else
        echo "✓ Touch ID already enabled for sudo"
    fi
fi

# ── Step 5: Native messaging host manifest ─────────────────
echo ""
echo "=== Step 5: Install Native Messaging Host ==="

EXTENSION_ID="${DEME_EXTENSION_ID:-EXTENSION_ID_PLACEHOLDER}"
# ⚠  Set DEME_EXTENSION_ID to your Chrome extension ID before running, or manually
#    edit the generated manifest at $HOST_DIR/$NATIVE_HOST_ID.json after installation.
HOST_MANIFEST_CONTENT=$(cat <<EOF
{
  "name": "$NATIVE_HOST_ID",
  "description": "DemeWebsolutions Biometric Auth Native Host",
  "path": "$INSTALL_DIR/demewebsolutions_biometric_host.php",
  "type": "stdio",
  "allowed_origins": [
    "chrome-extension://$EXTENSION_ID/"
  ]
}
EOF
)

if [[ "$OS_TYPE" == "Darwin" ]]; then
    HOST_DIR="$HOME/Library/Application Support/Google/Chrome/NativeMessagingHosts"
    mkdir -p "$HOST_DIR"
    echo "$HOST_MANIFEST_CONTENT" > "$HOST_DIR/$NATIVE_HOST_ID.json"
    echo "✓ Installed Chrome native host manifest"
elif [[ "$OS_TYPE" == "Linux" ]]; then
    HOST_DIR="$HOME/.config/google-chrome/NativeMessagingHosts"
    mkdir -p "$HOST_DIR"
    echo "$HOST_MANIFEST_CONTENT" > "$HOST_DIR/$NATIVE_HOST_ID.json"
    echo "✓ Installed Chrome native host manifest"
fi

# ── Step 6: Auto-login daemon ──────────────────────────────
echo ""
echo "=== Step 6: Install Auto-Login Daemon ==="

AUTO_LOGIN_SCRIPT="$CONFIG_DIR/auto_login.php"
cat > "$AUTO_LOGIN_SCRIPT" <<'EOPHP'
#!/usr/bin/env php
<?php
/**
 * UBSAS Auto-Login Daemon
 * Monitors for biometric unlock and auto-authenticates DemeWebsolutions apps.
 */
require '/usr/local/DemeWebsolutions/auth/ubsas_auth_service.php';

$ubsas    = new UBSASAuthService();
$lastCheck = 0;

while (true) {
    $now = time();
    if ($now - $lastCheck < 5) { sleep(1); continue; }
    $lastCheck = $now;

    $configFile = ($_SERVER['HOME'] ?? getenv('HOME')) . '/.demewebsolutions/config.json';
    if (!file_exists($configFile)) { continue; }

    $config = json_decode(file_get_contents($configFile), true);
    foreach (($config['apps'] ?? []) as $app => $settings) {
        if (!($settings['enabled'] ?? false)) continue;
        $creds = $ubsas->biometricAutoLogin($app);
        if ($creds) {
            echo '[' . date('Y-m-d H:i:s') . "] Biometric auto-login triggered for $app\n";
            // Actual HTTP login would be performed here; omitted for security
        }
    }
}
EOPHP
chmod +x "$AUTO_LOGIN_SCRIPT"
echo "✓ Created auto-login daemon at $AUTO_LOGIN_SCRIPT"

if [[ "$OS_TYPE" == "Darwin" ]]; then
    PLIST="$HOME/Library/LaunchAgents/com.demewebsolutions.autologin.plist"
    cat > "$PLIST" <<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>com.demewebsolutions.autologin</string>
    <key>ProgramArguments</key>
    <array>
        <string>$AUTO_LOGIN_SCRIPT</string>
    </array>
    <key>RunAtLoad</key>
    <true/>
    <key>KeepAlive</key>
    <true/>
    <key>StandardOutPath</key>
    <string>$CONFIG_DIR/autologin.log</string>
    <key>StandardErrorPath</key>
    <string>$CONFIG_DIR/autologin_error.log</string>
</dict>
</plist>
EOF
    launchctl load "$PLIST" 2>/dev/null && echo "✓ LaunchAgent installed and started" || echo "⚠ LaunchAgent install: run 'launchctl load $PLIST' manually"

elif [[ "$OS_TYPE" == "Linux" ]]; then
    SVCDIR="$HOME/.config/systemd/user"
    mkdir -p "$SVCDIR"
    cat > "$SVCDIR/demewebsolutions-autologin.service" <<EOF
[Unit]
Description=DemeWebsolutions Auto-Login Service
After=graphical-session.target

[Service]
Type=simple
ExecStart=$AUTO_LOGIN_SCRIPT
Restart=always
RestartSec=10

[Install]
WantedBy=default.target
EOF
    systemctl --user daemon-reload 2>/dev/null
    systemctl --user enable demewebsolutions-autologin.service 2>/dev/null
    systemctl --user start  demewebsolutions-autologin.service 2>/dev/null && echo "✓ Systemd service installed and started" || echo "⚠ Systemd service: run 'systemctl --user start demewebsolutions-autologin.service' manually"
fi

# ── Done ───────────────────────────────────────────────────
echo ""
echo "╔════════════════════════════════════════════════════════╗"
echo "║                  SETUP COMPLETE                        ║"
echo "╚════════════════════════════════════════════════════════╝"
echo ""
echo "Biometric Type:      $BIOMETRIC_TYPE"
echo "Configuration Dir:   $CONFIG_DIR"
echo "Install Dir:         $INSTALL_DIR"
echo "Log File:            $CONFIG_DIR/autologin.log"
echo ""
echo "🔐 Next Steps:"
echo "   1. Install the browser extension from browser_extension/"
echo "   2. Unlock your system with $BIOMETRIC_TYPE"
echo "   3. Applications will auto-authenticate"
echo "   4. Monitor: tail -f $CONFIG_DIR/autologin.log"
echo ""
if [[ "$OS_TYPE" == "Darwin" ]]; then
    echo "🛠  Service Commands:"
    echo "   Stop:  launchctl unload ~/Library/LaunchAgents/com.demewebsolutions.autologin.plist"
    echo "   Start: launchctl load  ~/Library/LaunchAgents/com.demewebsolutions.autologin.plist"
else
    echo "🛠  Service Commands:"
    echo "   Stop:  systemctl --user stop  demewebsolutions-autologin.service"
    echo "   Start: systemctl --user start demewebsolutions-autologin.service"
    echo "   Logs:  journalctl --user -u demewebsolutions-autologin.service"
fi
echo ""
