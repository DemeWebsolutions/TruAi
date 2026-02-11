#!/usr/bin/env bash
#
# Gemini.ai Contabo Server Management — Plesk Terminal Deployment
# Run from a STAGING directory (not from DEPLOY_DIR).
#
# Prerequisites: PHP 8.0+ with sqlite3, curl, json
#
# Usage:
#   cd /tmp/gemini-deploy-contabo
#   chmod +x deploy/DEPLOY_CONTABO_PLESK.sh
#   ./deploy/DEPLOY_CONTABO_PLESK.sh
#
# Or with custom path:
#   DEPLOY_DIR=/var/www/vhosts/gemini.yourdomain.com/httpdocs ./deploy/DEPLOY_CONTABO_PLESK.sh
#

set -e

# ========== CONFIGURATION ==========
DEPLOY_DIR="${DEPLOY_DIR:-/var/www/vhosts/system/gemini.YourDomain.com/htdocs}"
PORT="${PORT:-5000}"
BACKUP_DIR="${BACKUP_DIR:-/tmp/gemini-backup-$(date +%Y%m%d-%H%M%S)}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

# Safety: never deploy over the source
SRC_REAL=$(realpath "$SOURCE_DIR" 2>/dev/null || echo "$SOURCE_DIR")
DST_REAL=$(realpath "$DEPLOY_DIR" 2>/dev/null || echo "$DEPLOY_DIR")
if [ "$SRC_REAL" = "$DST_REAL" ] || [[ "$DST_REAL" == "$SRC_REAL"/* ]]; then
  echo "ERROR: DEPLOY_DIR must not be the same as or inside SOURCE_DIR."
  echo "  SOURCE: $SOURCE_DIR"
  echo "  DEPLOY: $DEPLOY_DIR"
  echo "  Use: DEPLOY_DIR=/path/to/live ./deploy/DEPLOY_CONTABO_PLESK.sh"
  exit 1
fi

echo "=========================================="
echo "  Gemini.ai Contabo Deployment"
echo "=========================================="
echo "  Source: $SOURCE_DIR"
echo "  Target: $DEPLOY_DIR"
echo "  Port:   $PORT"
echo "=========================================="

# ========== STEP 1: Stop existing process ==========
echo "[1/6] Stopping existing Gemini.ai server (port $PORT)..."
if command -v lsof &>/dev/null; then
  PID=$(lsof -ti :$PORT 2>/dev/null || true)
  if [ -n "$PID" ]; then
    kill $PID 2>/dev/null || true
    sleep 2
    kill -9 $PID 2>/dev/null || true
    echo "    Stopped process $PID"
  else
    echo "    No process on port $PORT"
  fi
else
  echo "    (lsof not found — ensure no process is using port $PORT)"
fi

# ========== STEP 2: Backup ==========
if [ -d "$DEPLOY_DIR" ] && [ "$(ls -A $DEPLOY_DIR 2>/dev/null)" ]; then
  echo "[2/6] Backing up current deployment to $BACKUP_DIR..."
  mkdir -p "$BACKUP_DIR"
  cp -a "$DEPLOY_DIR"/* "$BACKUP_DIR/" 2>/dev/null || true
  [ -f "$DEPLOY_DIR/.env" ] && cp "$DEPLOY_DIR/.env" "$BACKUP_DIR/" 2>/dev/null || true
  echo "    Backup complete"
else
  echo "[2/6] No existing deployment to backup"
  mkdir -p "$DEPLOY_DIR"
fi

# ========== STEP 3: Remove old files ==========
echo "[3/6] Removing old deployment..."
rm -rf "$DEPLOY_DIR"/*
rm -rf "$DEPLOY_DIR"/.[!.]* 2>/dev/null || true
if [ -f "$BACKUP_DIR/.env" ]; then
  cp "$BACKUP_DIR/.env" "$DEPLOY_DIR/.env" 2>/dev/null || true
fi
echo "    Old files removed"

# ========== STEP 4: Deploy new files ==========
echo "[4/6] Deploying new files..."
if command -v rsync &>/dev/null; then
  rsync -a \
    --exclude='.git' --exclude='node_modules' --exclude='electron' \
    --exclude='TruAi-Git' --exclude='TruAi-Update' --exclude='*.zip' \
    --exclude='visuals' --exclude='tests' --exclude='*.md' --exclude='*.patch' \
    --exclude='*.svg' --exclude='TruAi Prototype Original.html' \
    --exclude='TruAi Prototype Done.html' --exclude='TruAi copy.html' \
    --exclude='TruAi.Gatewaycopy.html' --exclude='truai-dashboard-examples.html' \
    --exclude='preview-new-design.html' --exclude='test-*.html' --exclude='og-index.php' \
    "$SOURCE_DIR/" "$DEPLOY_DIR/"
else
  cp -r "$SOURCE_DIR"/router.php "$SOURCE_DIR"/index.php "$DEPLOY_DIR/"
  cp -r "$SOURCE_DIR"/backend "$DEPLOY_DIR/"
  cp -r "$SOURCE_DIR"/assets "$DEPLOY_DIR/"
  cp -r "$SOURCE_DIR"/dev "$DEPLOY_DIR/"
  cp -r "$SOURCE_DIR"/database "$DEPLOY_DIR/"
  cp "$SOURCE_DIR"/login-portal.html "$SOURCE_DIR"/welcome.html "$SOURCE_DIR"/start.html "$SOURCE_DIR"/loading.html "$DEPLOY_DIR/" 2>/dev/null || true
  cp "$SOURCE_DIR"/access-granted.html "$SOURCE_DIR"/access-denied.html "$DEPLOY_DIR/" 2>/dev/null || true
  cp "$SOURCE_DIR"/change-password.php "$SOURCE_DIR"/reset-admin-password.php "$DEPLOY_DIR/" 2>/dev/null || true
  cp "$SOURCE_DIR"/"TruAi Prototype.html" "$DEPLOY_DIR/" 2>/dev/null || true
  cp "$SOURCE_DIR"/access-granted.gif "$SOURCE_DIR"/access-denied.gif "$DEPLOY_DIR/" 2>/dev/null || true
fi

mkdir -p "$DEPLOY_DIR/database" "$DEPLOY_DIR/logs" "$DEPLOY_DIR/backend"
chmod 755 "$DEPLOY_DIR/database" "$DEPLOY_DIR/logs" 2>/dev/null || true
chmod 666 "$DEPLOY_DIR/database/truai.db" 2>/dev/null || true
echo "    Deployment complete"

# ========== STEP 5: Configure production ==========
echo "[5/6] Configuring production environment..."
ENV_FILE="$DEPLOY_DIR/.env"
if [ ! -f "$ENV_FILE" ]; then
  cat > "$ENV_FILE" << 'EOF'
# Gemini.ai Production — My Deme, LLC
TRUAI_DEPLOYMENT=production
APP_ENV=production

# Optional: AI API keys (add if Gemini Chat should use live AI)
# OPENAI_API_KEY=
# ANTHROPIC_API_KEY=
EOF
  echo "    Created .env with TRUAI_DEPLOYMENT=production"
else
  if ! grep -q "TRUAI_DEPLOYMENT=production" "$ENV_FILE" 2>/dev/null; then
    echo "TRUAI_DEPLOYMENT=production" >> "$ENV_FILE"
    echo "APP_ENV=production" >> "$ENV_FILE"
    echo "    Appended TRUAI_DEPLOYMENT=production to .env"
  fi
fi

# ========== STEP 6: Start server ==========
echo "[6/6] Starting Gemini.ai server..."
cd "$DEPLOY_DIR"
export TRUAI_DEPLOYMENT=production
export APP_ENV=production

if [ -f ".env" ]; then
  set -a
  source .env 2>/dev/null || true
  set +a
fi

PHP_CMD=$(command -v php 2>/dev/null || echo "php")
nohup $PHP_CMD -S 0.0.0.0:$PORT router.php > "$DEPLOY_DIR/logs/server.log" 2>&1 &
echo $! > "$DEPLOY_DIR/logs/server.pid"
sleep 2

if command -v lsof &>/dev/null && lsof -ti :$PORT >/dev/null 2>&1; then
  echo ""
  echo "=========================================="
  echo "  Gemini.ai deployment complete"
  echo "=========================================="
  echo "  URL:    http://YOUR_SERVER_IP:$PORT/TruAi/gemini"
  echo "  Login:  http://YOUR_SERVER_IP:$PORT/TruAi/login-portal.html"
  echo "  Logs:   $DEPLOY_DIR/logs/server.log"
  echo "  PID:    $(cat $DEPLOY_DIR/logs/server.pid 2>/dev/null)"
  echo "=========================================="
else
  echo "WARNING: Server may not have started. Check: $DEPLOY_DIR/logs/server.log"
  tail -20 "$DEPLOY_DIR/logs/server.log" 2>/dev/null || true
fi
