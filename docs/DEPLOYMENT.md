# TruAi Production Deployment Guide

## Overview

This guide covers deploying TruAi to a production environment using Plesk, Nginx, or Apache with PHP-FPM.

---

## Requirements

- **PHP:** 8.2+ with extensions: `sqlite3`, `openssl`, `mbstring`, `json`
- **Web server:** Nginx 1.18+ or Apache 2.4+
- **OS:** Ubuntu 20.04+ / Debian 11+ / CentOS 8+
- **Disk:** 1GB minimum
- **RAM:** 512MB minimum
- **SSL certificate** (Let's Encrypt recommended)

---

## Plesk Deployment (PHP-FPM Pool)

### 1. Create PHP-FPM Pool Configuration

Save as `/etc/php/8.2/fpm/pool.d/truai.conf`:

```ini
[truai]
user = www-data
group = www-data
listen = /run/php/php8.2-fpm-truai.sock
listen.owner = www-data
listen.group = www-data

pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3

; Environment variables
env[TRUAI_DEPLOYMENT] = "production"
env[OPENAI_API_KEY] = "sk-your-key"
env[ANTHROPIC_API_KEY] = "sk-ant-your-key"
env[ALLOWED_HOSTS] = "yourdomain.com,www.yourdomain.com"

; PHP settings
php_admin_value[error_log] = /var/log/truai/php-fpm.log
php_admin_flag[log_errors] = on
php_admin_value[session.save_path] = /var/lib/php/sessions/truai
```

### 2. Restart PHP-FPM

```bash
sudo systemctl restart php8.2-fpm
```

---

## Nginx Configuration

Save as `/etc/nginx/sites-available/truai`:

```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;

    root /var/www/truai;
    index index.php start.html;

    # SSL
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Security headers
    add_header X-Frame-Options DENY;
    add_header X-Content-Type-Options nosniff;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";
    add_header Referrer-Policy strict-origin-when-cross-origin;

    # PHP
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm-truai.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Block sensitive files
    location ~ /\.(env|git|htaccess) { deny all; }
    location ~ /database/ { deny all; }
    location ~ /logs/ { deny all; }

    # Static files
    location ~* \.(css|js|png|jpg|gif|ico|svg|woff2?)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
```

Enable and test:
```bash
sudo ln -s /etc/nginx/sites-available/truai /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## Apache Configuration (.htaccess)

If using Apache, place in project root:

```apache
Options -Indexes
RewriteEngine On

# Force HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Block sensitive directories
RewriteRule ^(database|logs|backend)/.*$ - [F,L]
RewriteRule ^\.env$ - [F,L]

# API routing
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^TruAi/api/(.*)$ router.php [QSA,L]
```

---

## SSL/TLS with Let's Encrypt

```bash
# Install certbot
sudo apt install certbot python3-certbot-nginx

# Obtain certificate
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com

# Auto-renewal (already added by certbot, verify with):
sudo systemctl status certbot.timer
```

---

## Environment Variable Management

For production, set environment variables in the PHP-FPM pool config (see above). Never commit `.env` to version control.

Required variables:
```
TRUAI_DEPLOYMENT=production
OPENAI_API_KEY=sk-your-key
ANTHROPIC_API_KEY=sk-ant-your-key
ALLOWED_HOSTS=yourdomain.com,www.yourdomain.com
```

---

## File Permissions

```bash
# Project root
chmod 755 /var/www/truai
chown -R www-data:www-data /var/www/truai

# Database and keys (restrictive)
chmod 700 /var/www/truai/database
chmod 600 /var/www/truai/database/truai.db
chmod 700 /var/www/truai/database/keys
chmod 600 /var/www/truai/database/keys/private_key.pem
chmod 644 /var/www/truai/database/keys/public_key.pem

# Logs
chmod 750 /var/www/truai/logs
```

---

## Log Rotation

Create `/etc/logrotate.d/truai`:

```
/var/www/truai/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 640 www-data www-data
    postrotate
        systemctl reload php8.2-fpm
    endscript
}
```

---

## Backup Automation

### Using systemd (preferred)

```bash
# Copy units
sudo cp /var/www/truai/scripts/backup_database.service /etc/systemd/system/
sudo cp /var/www/truai/scripts/backup_database.timer /etc/systemd/system/

# Edit paths
sudo nano /etc/systemd/system/backup_database.service
# Update: User=, Group=, ExecStart= paths

# Enable timer
sudo systemctl daemon-reload
sudo systemctl enable backup_database.timer
sudo systemctl start backup_database.timer

# Verify
sudo systemctl list-timers backup_database.timer
```

### Using cron (alternative)

```bash
# Edit crontab for www-data
sudo crontab -u www-data -e

# Add:
0 2 * * * /var/www/truai/scripts/backup_database.sh >> /var/log/truai/backup.log 2>&1
```

---

## Deployment Procedure

### Initial Deployment

```bash
# 1. Clone repository
git clone https://github.com/DemeWebsolutions/TruAi.git /var/www/truai
cd /var/www/truai

# 2. Set permissions
chown -R www-data:www-data .
chmod 700 database

# 3. Initialize database
sudo -u www-data php scripts/setup_database.php

# 4. Get credentials
sudo cat database/.initial_credentials

# 5. Verify health
curl https://yourdomain.com/TruAi/api/v1/health
```

### Updating (Zero-Downtime)

```bash
# 1. Backup
./scripts/backup_database.sh

# 2. Pull code
git pull origin main

# 3. Run migrations (idempotent)
sudo -u www-data php scripts/setup_database.php

# 4. Restart PHP-FPM
sudo systemctl restart php8.2-fpm

# 5. Verify
curl https://yourdomain.com/TruAi/api/v1/health
```

### Rollback

```bash
# 1. Revert code
git reset --hard <previous-commit>

# 2. Restore database
gunzip -c ~/.truai_backups/truai_YYYYMMDD_HHMMSS.db.gz > database/truai.db

# 3. Restart
sudo systemctl restart php8.2-fpm
```

---

## Firewall Rules

```bash
# Allow HTTP, HTTPS, SSH
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Deny direct access to PHP dev server port
sudo ufw deny 8001/tcp

sudo ufw enable
```

---

## Health Monitoring

Test health endpoint regularly:

```bash
# Add to cron
*/5 * * * * curl -sf https://yourdomain.com/TruAi/api/v1/health > /dev/null || echo "TruAi health check failed" | mail -s "TruAi Alert" admin@yourdomain.com
```

---

## Security Hardening Checklist

- [ ] HTTPS enforced (HTTP redirects to HTTPS)
- [ ] SSL certificate valid and auto-renewing
- [ ] `.env` not in repository and not web-accessible
- [ ] `database/` directory blocked from web access
- [ ] `logs/` directory blocked from web access
- [ ] File permissions set correctly (600 for db and keys)
- [ ] PHP-FPM pool isolates process
- [ ] Firewall blocks port 8001 from external access
- [ ] Log rotation configured
- [ ] Backup automation active
- [ ] Health monitoring active
- [ ] API keys in PHP-FPM pool env, not in code
