# Gemini.ai Contabo Server Management — Plesk Terminal Deployment

Deploy Gemini.ai to a Contabo VPS using Plesk Terminal.

## Prerequisites

- **Contabo VPS** with Plesk installed
- **PHP 8.0+** with extensions: `sqlite3`, `curl`, `json`, `mbstring`
- **SSH** or **Plesk File Manager** access

## Quick Deploy (Plesk Terminal)

### 1. Upload the deployment package

Upload the `gemini-deploy-contabo.tar.gz` (or the full TruAi folder) to your server, e.g.:

```
/tmp/gemini-deploy/    (staging directory)
```

**Via Plesk File Manager:** Upload the tar.gz to `/tmp/` or your home directory, then extract in Terminal.

**Via SCP/SFTP:**
```bash
scp gemini-deploy-contabo.tar.gz username@your-server-ip:/tmp/
```

### 2. Open Plesk Terminal

Plesk → **Tools & Settings** → **Terminal** (or SSH into your server)

### 3. Extract and run deployment

```bash
cd /tmp
tar -xzf gemini-deploy-contabo.tar.gz
cd gemini-deploy-contabo
chmod +x deploy/DEPLOY_CONTABO_PLESK.sh
./deploy/DEPLOY_CONTABO_PLESK.sh
```

### 4. Custom deployment path (optional)

For a specific domain or subdomain document root:

```bash
DEPLOY_DIR=/var/www/vhosts/yourdomain.com/httpdocs/gemini ./deploy/DEPLOY_CONTABO_PLESK.sh
```

Or for a dedicated subdomain:

```bash
DEPLOY_DIR=/var/www/vhosts/gemini.yourdomain.com/httpdocs ./deploy/DEPLOY_CONTABO_PLESK.sh
```

## Default paths

| Variable | Default | Description |
|----------|---------|-------------|
| `DEPLOY_DIR` | `/var/www/vhosts/system/gemini.YourDomain.com/htdocs` | Target deployment directory |
| `PORT` | `5000` | PHP built-in server port |
| `BACKUP_DIR` | `/tmp/gemini-backup-YYYYMMDD-HHMMSS` | Backup location |

## Post-deployment

1. **Access Gemini.ai:** `http://YOUR_SERVER_IP:5000/TruAi/gemini`
2. **Default credentials:** Create via `reset-admin-password.php` or first login
3. **API keys:** Add to `.env` in `DEPLOY_DIR` for OpenAI/Sonnet chat:
   ```
   OPENAI_API_KEY=sk-your-key
   ANTHROPIC_API_KEY=sk-ant-your-key
   ```

## Plesk Apache (alternative)

If you prefer Apache instead of PHP built-in server:

1. Deploy to your domain's `httpdocs` (e.g. `gemini.yourdomain.com`)
2. Set `index.php` or create a symlink to `router.php`
3. Add `.htaccess` for URL rewriting (see `deploy/.htaccess.example`)

## Troubleshooting

- **Port in use:** Change `PORT` or stop the existing process
- **Permission denied:** Ensure `chmod +x` on the deploy script
- **PHP not found:** Use full path, e.g. `/usr/bin/php`
- **Logs:** Check `$DEPLOY_DIR/logs/server.log`

## Stopping the server

```bash
kill $(cat /path/to/deploy/logs/server.pid)
```
