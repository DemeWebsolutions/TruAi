# Database Initialization Guide

## Problem

On fresh repository clones, users may experience "Invalid credentials" errors because:

1. **Git doesn't track empty directories** - The `database/` directory doesn't exist in the repository
2. **Database not initialized** - The database file (`truai.db`) is only created when the server runs for the first time
3. **Wrong startup command** - Users might start the server incorrectly

## Solution

The system now automatically handles database initialization:

### Automatic Initialization

1. **On Server Start** (`start.sh`):
   - Creates `database/` directory if it doesn't exist
   - Initializes database if `database/truai.db` doesn't exist
   - Creates default admin user automatically

2. **In Database Class** (`backend/database.php`):
   - Automatically creates `database/` directory if missing
   - Handles database creation gracefully

### Manual Initialization

If you need to manually initialize the database:

```bash
cd ~/Desktop/Tru.ai/TruAi
php init-database.php
```

This script will:
- Create the `database/` directory
- Initialize the database schema
- Create the default admin user
- Verify everything is working

## Default Credentials

After initialization, use these credentials to log in:

- **Username:** `admin`
- **Password:** `admin123`

## Verification

To verify the database is initialized:

```bash
# Check if database exists
ls -la database/truai.db

# Check admin user
sqlite3 database/truai.db "SELECT username, role FROM users WHERE username='admin';"
```

Expected output:
```
admin|SUPER_ADMIN
```

## Troubleshooting

### Issue: "Database connection failed"

**Solution:**
1. Ensure `database/` directory exists and is writable:
   ```bash
   mkdir -p database
   chmod 755 database
   ```

2. Check PHP SQLite support:
   ```bash
   php -m | grep sqlite
   ```

3. Run manual initialization:
   ```bash
   php init-database.php
   ```

### Issue: "Invalid credentials" after initialization

**Solution:**
1. Verify admin user exists:
   ```bash
   sqlite3 database/truai.db "SELECT * FROM users;"
   ```

2. Reset admin password:
   ```bash
   php reset-admin-password.php
   ```

### Issue: Database directory not created

**Solution:**
The `start.sh` script now automatically creates the directory. If it doesn't:

```bash
mkdir -p database logs
chmod 755 database logs
php init-database.php
```

## Files Modified

1. **`start.sh`** - Added database initialization check
2. **`backend/database.php`** - Auto-creates database directory
3. **`init-database.php`** - New manual initialization script

## Best Practices

1. **Always use `start.sh`** to start the server (not `php -S` directly)
2. **Run `init-database.php`** after fresh repository clones
3. **Check database exists** before troubleshooting login issues
4. **Verify admin user** exists if login fails

## Git Repository Note

The `database/` directory is intentionally not tracked by git (see `.gitignore`):
- Database files are user-specific
- Prevents conflicts between different installations
- Database is auto-created on first run

To ensure the directory structure is clear, consider adding a `.gitkeep` file:

```bash
touch database/.gitkeep
git add database/.gitkeep
```

However, the current solution (auto-creation) is preferred as it's automatic.
