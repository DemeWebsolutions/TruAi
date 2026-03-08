#!/usr/bin/env bash
# Fix 403 on Gemini-Ai — Plesk requires subscription user ownership
# Run as root on Contabo: chmod +x deploy/FIX_403_PERMISSIONS.sh && ./deploy/FIX_403_PERMISSIONS.sh

set -e
BASE_DIR="/var/www/vhosts/demewebsolutions.com/Gemini-Ai.demewebsolutions.com"
SUB_USER=$(stat -c '%U' /var/www/vhosts/demewebsolutions.com/httpdocs 2>/dev/null || echo 'demewebsolutions')
SUB_GROUP=$(stat -c '%G' /var/www/vhosts/demewebsolutions.com/httpdocs 2>/dev/null || echo 'psacln')
echo "chown -R $SUB_USER:$SUB_GROUP $BASE_DIR"
chown -R "$SUB_USER:$SUB_GROUP" "$BASE_DIR"
chmod -R 755 "$BASE_DIR/public"
chmod 755 "$BASE_DIR/database" "$BASE_DIR/logs" 2>/dev/null || true
chmod 644 "$BASE_DIR/public/index.php" "$BASE_DIR/public/router.php" 2>/dev/null || true
echo "Done. Try https://gemini-ai.demewebsolutions.com/"
