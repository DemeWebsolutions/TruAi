TruAi — Troubleshooting Quick Reference (ordered)

1) Server not responding (localhost:8080)
- Check process:
  ps aux | rg \"php -S 127.0.0.1:8080\" || pgrep -a php
- Start router:
  cd /path/to/TruAi && php -S 127.0.0.1:8080 router.php > logs/php_server.log 2>&1 &
- Check logs:
  tail -n 200 logs/php_server.log

2) Login stalls "Encrypting & Signing in..."
- Quick check: public/disable-encryption.js shim
  mkdir -p public
  cat > public/disable-encryption.js <<'JS'
  window.encryptionReady = false;
  console.warn('Encryption forced OFF');
  JS
- Load from browser console:
  var s=document.createElement('script'); s.src='/TruAi/disable-encryption.js'; document.head.appendChild(s);

3) Invalid credentials
- Read one-time creds:
  cat database/.initial_credentials
- Reset admin:
  php reset-admin-password.php admin
- Verify DB:
  sqlite3 database/truai.db \"SELECT username,role FROM users;\"

4) 404s on public routes
- Ensure nginx try_files present:
  CONF=/var/www/vhosts/system/gemini-ai.demewebsolutions.com/conf/vhost_nginx.conf
  grep -q try_files \"$CONF\" || printf 'location / { try_files $uri $uri/ /index.php?$query_string; }' >> \"$CONF\"
  plesk repair web gemini-ai.demewebsolutions.com && nginx -t && systemctl reload nginx

5) ROMA handshake failures
- Check keys & perms:
  ls -la /opt/roma/keys
  openssl rsa -in /opt/roma/keys/gemini_private.pem -check -noout
- Test handshake:
  curl -k -X POST https://<FQDN>:8443/itc -d '{\"action\":\"handshake\",\"client_id\":\"truai\"}'

6) AI test failures
- Run adapter test:
  curl -s -X POST http://127.0.0.1:8080/TruAi/api/v1/ai/test -H \"Content-Type: application/json\" -d '{\"prompt\":\"hello\"}' | jq .

7) Permissions & ownership
- Set webowner (Plesk example):
  chown -R psaadm:psaadm /var/www/vhosts/demewebsolutions.com/Gemini-Ai.demewebsolutions.com
  chmod 750 backend config keys

