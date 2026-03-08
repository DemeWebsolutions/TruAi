Electron & Local — Run, Debug, and Test

Start PHP router (recommended)
1. cd /Users/mydemellc./Desktop/TruAi
2. Stop existing:
   pkill -f \"php -S 127.0.0.1:8080\" 2>/dev/null || true
3. Start router in background:
   nohup php -S 127.0.0.1:8080 router.php > logs/php_server.log 2>&1 &
4. Confirm:
   curl -sI http://127.0.0.1:8080/TruAi/login-portal.html | sed -n '1,4p'

Launch Electron (dev)
1. cd electron
2. npm install
3. npm start

If Electron stalls on startup
- Permission: macOS Full Disk Access may be required for reading project files (see main.js permission prompt).
- Check logs in terminal where Electron was started.

Disable client encryption for testing
1. create public/disable-encryption.js with:
   window.encryptionReady = false;
2. load from Console:
   var s=document.createElement('script'); s.src='/TruAi/disable-encryption.js'; document.head.appendChild(s);

DevTools checks
- Open DevTools (Cmd+Alt+I) → Console: JS errors
- Network: inspect /api/v1/auth/publickey and /api/v1/auth/login calls and responses

