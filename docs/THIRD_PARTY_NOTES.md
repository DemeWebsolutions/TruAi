TruAi / Gemini.ai — Third‑party Notes (concise, actionable)

Goal
- Help a contractor or AI copilot validate, fix, and hand back a working deployment: local (Electron), staging (localhost:8080), and production (Plesk/Contabo).

Priority checklist (order to run)
1. Confirm application locations
   - PHP app root: /var/www/vhosts/demewebsolutions.com/Gemini-Ai.demewebsolutions.com
   - Local dev root: /Users/mydemellc./Desktop/TruAi
   - Electron: /Users/mydemellc./Desktop/TruAi/electron

2. Credentials & access
   - Read one‑time admin: database/.initial_credentials
     - cat database/.initial_credentials
   - If missing or unknown, run:
     - php reset-admin-password.php admin
     - cat database/.initial_credentials
   - Store creds securely; delete after first UI password change.

3. Quick API checks (run on server or locally with PHP router running)
   - GET public key:
     curl -s http://127.0.0.1:8080/TruAi/api/v1/auth/publickey
   - POST login (standard):
     curl -s -X POST http://127.0.0.1:8080/TruAi/api/v1/auth/login -H "Content-Type: application/json" -d '{"username":"<user>","password":"<pwd>"}'

4. ROMA / ITC checks (production)
   - Keys: /opt/roma/keys (owner root:root, dirs 700, files 600)
   - Test handshake from HQ: curl -k -X POST https://<FQDN>:8443/itc -d '{"action":"handshake","client_id":"truai"}'

5. Nginx / Plesk
   - Ensure vhost_nginx.conf contains:
     location / { try_files $uri $uri/ /index.php?$query_string; }
   - Run: plesk repair web gemini-ai.demewebsolutions.com && nginx -t && systemctl reload nginx

6. Electron / local app
   - Start local router: php -S 127.0.0.1:8080 router.php
   - Launch Electron (or open http://localhost:8080/TruAi/login-portal.html)
   - If encryption stalls, force standard by injecting /TruAi/disable-encryption.js (see ELECTRON_INSTRUCTIONS.md)

7. AI model integration
   - Validate model adapter: /api/v1/ai/test returns expected schema for 5 prompts
   - Place model outside webroot: /opt/models/gemini-vX/
   - Provide model.tar.gz.sha256 and signature

8. Post‑fix acceptance
   - Login (UI) success
   - AI test endpoint success
   - ITC handshake success
   - Logs show no repeated failures (24–72h)

Contact & context
- See NOTES_ROMA_TRUAI.md (Roma_TruAi/) and Gemini.Ai Milestone files for full scripts and deploy artifacts.

