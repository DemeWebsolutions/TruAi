#!/usr/bin/env bash
#
# Fix 404 on Gemini-Ai — add nginx try_files for PHP front controller
# Plesk uses nginx; .htaccess is ignored. Run as root on Contabo.
#
# Usage: ./deploy/FIX_NGINX_404.sh
#

set -e

DOMAIN="gemini-ai.demewebsolutions.com"
CONF_DIR="/var/www/vhosts/system/${DOMAIN}/conf"
NGINX_CONF="${CONF_DIR}/vhost_nginx.conf"

echo "Fixing 404 for $DOMAIN..."
echo "  Config: $NGINX_CONF"

# Ensure conf dir exists
mkdir -p "$CONF_DIR"

# Add try_files if not present (Plesk vhost_nginx.conf is for additional directives)
if [ -f "$NGINX_CONF" ] && grep -q "try_files" "$NGINX_CONF"; then
  echo "  try_files already present"
else
  [ -f "$NGINX_CONF" ] && cp "$NGINX_CONF" "${NGINX_CONF}.bak"
  echo "" >> "$NGINX_CONF"
  echo "# Gemini.ai front controller" >> "$NGINX_CONF"
  echo "location / {" >> "$NGINX_CONF"
  echo "    try_files \$uri \$uri/ /index.php?\$query_string;" >> "$NGINX_CONF"
  echo "}" >> "$NGINX_CONF"
  echo "  Added try_files directive"
fi

# Regenerate Plesk config and reload nginx
plesk repair web "$DOMAIN" 2>/dev/null || true
nginx -t 2>/dev/null && systemctl reload nginx 2>/dev/null || service nginx reload 2>/dev/null || true

echo ""
echo "Done. Test: https://$DOMAIN/"
echo "If still 404, add via Plesk GUI:"
echo "  Websites → $DOMAIN → Apache & nginx Settings"
echo "  Additional nginx directives:"
echo "    location / { try_files \$uri \$uri/ /index.php?\$query_string; }"
echo ""
