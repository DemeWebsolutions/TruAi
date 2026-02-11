#!/usr/bin/env bash
#
# Gemini.ai Plesk Deployment — gemini-ai.demewebsolutions.com
# Secure structure: public/ as document root, backend/config/logs/keys isolated
#
# Run from Plesk Terminal. Run as root or with sufficient privileges.
#
# Usage:
#   1. Upload gemini-deploy-plesk.tar.gz to /tmp on Contabo
#   2. cd /tmp && tar -xzf gemini-deploy-plesk.tar.gz && cd gemini-deploy-plesk
#   3. chmod +x deploy/DEPLOY_PLESK_GEMINI_AI.sh
#   4. ./deploy/DEPLOY_PLESK_GEMINI_AI.sh
#
# Or with custom values:
#   DOMAIN=demewebsolutions.com SUBDOMAIN=gemini-ai ./deploy/DEPLOY_PLESK_GEMINI_AI.sh
#

set -e

# ========== CONFIGURATION ==========
DOMAIN="${DOMAIN:-demewebsolutions.com}"
SUBDOMAIN="${SUBDOMAIN:-gemini-ai}"
FQDN="${SUBDOMAIN}.${DOMAIN}"
# Plesk uses full FQDN as folder: Gemini-Ai.demewebsolutions.com (from plesk subdomain --info)
BASE_DIR="${BASE_DIR:-/var/www/vhosts/$DOMAIN/Gemini-Ai.$DOMAIN}"
DOC_ROOT="$BASE_DIR/public"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
BACKUP_DIR="${BACKUP_DIR:-/tmp/gemini-backup-$(date +%Y%m%d-%H%M%S)}"

echo "=========================================="
echo "  Gemini.ai Plesk Deployment"
echo "  https://$FQDN/"
echo "=========================================="
echo "  Base: $BASE_DIR"
echo "  Document root: $DOC_ROOT"
echo "  Source: $SOURCE_DIR"
echo "=========================================="

# ========== STEP 0: Resolve actual Plesk path ==========
if command -v plesk &>/dev/null; then
  echo "[0/8] Resolving Plesk path for $FQDN..."
  PLESK_INFO=$(plesk bin subdomain --info "$FQDN" 2>/dev/null || plesk bin domain --info "Gemini-Ai.$DOMAIN" 2>/dev/null || true)
  if [ -n "$PLESK_INFO" ]; then
    # Parse WWW-Root only (avoids matching main domain's "Document root" line)
    ACTUAL_ROOT=$(echo "$PLESK_INFO" | grep 'WWW-Root' | head -1 | sed 's/.*: *//' | tr -d ' ')
    # Only use if path contains Gemini (subdomain) - avoid main domain's /httpdocs or /public
    if [ -n "$ACTUAL_ROOT" ] && [ -d "$ACTUAL_ROOT" ] && echo "$ACTUAL_ROOT" | grep -qi 'gemini'; then
      BASE_DIR="$ACTUAL_ROOT"
      DOC_ROOT="$BASE_DIR/public"
      echo "    Using Plesk path: $BASE_DIR"
    fi
  fi
  # Fallback: known Gemini-Ai subdomain path
  if [ ! -d "$BASE_DIR" ]; then
    BASE_DIR="/var/www/vhosts/$DOMAIN/Gemini-Ai.$DOMAIN"
    DOC_ROOT="$BASE_DIR/public"
    echo "    Using fallback: $BASE_DIR"
  fi
else
  echo "[0/8] Plesk CLI not found — using default path"
fi

# ========== STEP 1: Create directory structure ==========
echo "[1/8] Creating directory structure..."
mkdir -p "$BASE_DIR"/{public,backend,logs,config,keys,database}
mkdir -p "$BASE_DIR/public"/{assets,dev,TruAi}

# ========== STEP 2: Backup existing ==========
if [ -d "$DOC_ROOT" ] && [ "$(ls -A $DOC_ROOT 2>/dev/null)" ]; then
  echo "[2/8] Backing up to $BACKUP_DIR..."
  mkdir -p "$BACKUP_DIR"
  cp -a "$BASE_DIR"/* "$BACKUP_DIR/" 2>/dev/null || true
  [ -f "$BASE_DIR/.env" ] && cp "$BASE_DIR/.env" "$BACKUP_DIR/" 2>/dev/null || true
else
  echo "[2/8] No existing deployment to backup"
fi

# ========== STEP 3: Deploy public files (document root) ==========
echo "[3/8] Deploying public files..."
cp -r "$SOURCE_DIR"/assets/* "$BASE_DIR/public/assets/" 2>/dev/null || true
cp -r "$SOURCE_DIR"/dev/* "$BASE_DIR/public/dev/" 2>/dev/null || true
for f in login-portal.html welcome.html start.html loading.html access-granted.html access-denied.html change-password.php reset-admin-password.php access-granted.gif access-denied.gif; do
  [ -f "$SOURCE_DIR/$f" ] && cp "$SOURCE_DIR/$f" "$BASE_DIR/public/" 2>/dev/null || true
done
[ -f "$SOURCE_DIR/TruAi Prototype.html" ] && cp "$SOURCE_DIR/TruAi Prototype.html" "$BASE_DIR/public/" 2>/dev/null || true

# Plesk-specific entry point and router
cp "$SCRIPT_DIR/plesk/public/index.php" "$BASE_DIR/public/"
cp "$SCRIPT_DIR/plesk/public/router.php" "$BASE_DIR/public/"
[ -f "$SCRIPT_DIR/plesk/public/.htaccess" ] && cp "$SCRIPT_DIR/plesk/public/.htaccess" "$BASE_DIR/public/" 2>/dev/null || true

# TruAi paths: /TruAi/assets, /TruAi/dev, /TruAi/gemini
mkdir -p "$BASE_DIR/public/TruAi"
[ -d "$BASE_DIR/public/TruAi/assets" ] || cp -r "$BASE_DIR/public/assets" "$BASE_DIR/public/TruAi/"
[ -d "$BASE_DIR/public/TruAi/dev" ] || cp -r "$BASE_DIR/public/dev" "$BASE_DIR/public/TruAi/"
[ -f "$SOURCE_DIR/TruAi Prototype.html" ] && cp "$SOURCE_DIR/TruAi Prototype.html" "$BASE_DIR/public/TruAi/" 2>/dev/null || true

# ========== STEP 4: Deploy backend (outside document root) ==========
echo "[4/8] Deploying backend..."
cp -r "$SOURCE_DIR"/backend/* "$BASE_DIR/backend/"
cp "$SOURCE_DIR"/router.php "$SOURCE_DIR"/index.php "$BASE_DIR/"
[ -f "$SOURCE_DIR/database/learning_schema.sql" ] && cp "$SOURCE_DIR/database/learning_schema.sql" "$BASE_DIR/database/" 2>/dev/null || true
rm -f "$BASE_DIR/database/truai.db" 2>/dev/null || true

# ========== STEP 5: Configure .env ==========
echo "[5/8] Configuring environment..."
ENV_FILE="$BASE_DIR/.env"
if [ ! -f "$ENV_FILE" ]; then
  cat > "$ENV_FILE" << EOF
# Gemini.ai Production — My Deme, LLC
TRUAI_DEPLOYMENT=production
APP_ENV=production

# Allow gemini-ai subdomain + server IP
ALLOWED_HOSTS=gemini-ai.demewebsolutions.com,154.53.54.169,127.0.0.1,::1

# AI API keys (add for live chat)
# OPENAI_API_KEY=
# ANTHROPIC_API_KEY=
EOF
  echo "    Created .env"
else
  grep -q "TRUAI_DEPLOYMENT=production" "$ENV_FILE" || echo "TRUAI_DEPLOYMENT=production" >> "$ENV_FILE"
  grep -q "ALLOWED_HOSTS=" "$ENV_FILE" || echo "ALLOWED_HOSTS=gemini-ai.demewebsolutions.com,154.53.54.169" >> "$ENV_FILE"
fi

# ========== STEP 6: Set Plesk document root ==========
echo "[6/8] Configuring Plesk document root to public/..."
if command -v plesk &>/dev/null; then
  # Try subdomain (gemini-ai or Gemini-Ai) then domain
  plesk bin subdomain --update "gemini-ai" -domain "$DOMAIN" -www-root "public" 2>/dev/null || \
  plesk bin subdomain --update "Gemini-Ai" -domain "$DOMAIN" -www-root "public" 2>/dev/null || \
  plesk bin domain --update "Gemini-Ai.$DOMAIN" -www-root "public" 2>/dev/null || \
  echo "    Manual: Plesk → $FQDN → Hosting Settings → Document root: public"
else
  echo "    Manual: Plesk → Hosting Settings → Document root: $DOC_ROOT"
fi

# ========== STEP 7: Permissions ==========
echo "[7/8] Setting permissions..."
# Plesk: use subscription user (parent domain httpdocs) so web server can read files
SUB_USER=$(stat -c '%U' /var/www/vhosts/$DOMAIN/httpdocs 2>/dev/null || echo 'demewebsolutions')
SUB_GROUP=$(stat -c '%G' /var/www/vhosts/$DOMAIN/httpdocs 2>/dev/null || echo 'psacln')
chown -R "$SUB_USER:$SUB_GROUP" "$BASE_DIR" 2>/dev/null || true
chmod -R 755 "$BASE_DIR/public"
chmod -R 750 "$BASE_DIR/backend" "$BASE_DIR/config" "$BASE_DIR/keys" 2>/dev/null || true
chmod 755 "$BASE_DIR/database" "$BASE_DIR/logs" 2>/dev/null || true
chmod 600 "$BASE_DIR/.env" 2>/dev/null || true

# ========== STEP 8: Nginx front controller (fix 404) ==========
echo "[8/9] Configuring nginx for PHP front controller..."
if [ -f "$SCRIPT_DIR/FIX_NGINX_404.sh" ]; then
  bash "$SCRIPT_DIR/FIX_NGINX_404.sh" 2>/dev/null || echo "    (Run manually: ./deploy/FIX_NGINX_404.sh)"
else
  echo "    Manual: Add to Plesk → $FQDN → Apache & nginx → Additional nginx:"
  echo "    location / { try_files \$uri \$uri/ /index.php?\$query_string; }"
fi

# ========== STEP 9: Database ==========
echo "[9/9] Database ready (initialized on first request)"

echo ""
echo "=========================================="
echo "  Deployment complete"
echo "=========================================="
echo "  URL:    https://$FQDN/"
echo "  Gemini: https://$FQDN/TruAi/gemini"
echo "  Login:  https://$FQDN/TruAi/login-portal.html"
echo ""
echo "  Document root: $DOC_ROOT"
echo "  If site shows default Plesk page, set Hosting Settings → Document root to: public"
echo "=========================================="
