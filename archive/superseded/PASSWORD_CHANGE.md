# Password Change Guide

## Quick Password Change

### Method 1: Interactive Script (Recommended)

```bash
cd ~/Desktop/Tru.ai/TruAi
php change-password.php
```

The script will:
1. Prompt for username (defaults to `admin`)
2. Prompt for new password (hidden input)
3. Ask you to confirm the password
4. Update the password in the database

### Method 2: Command Line

```bash
cd ~/Desktop/Tru.ai/TruAi
php change-password.php admin your-new-password
```

**Note:** This method shows the password in command history. Use interactive method for better security.

### Method 3: Reset to Default

```bash
cd ~/Desktop/Tru.ai/TruAi
php reset-admin-password.php
```

This resets the admin password back to `admin123`.

## Password Requirements

- **Minimum length:** 8 characters (recommended)
- **No maximum length** (but keep it reasonable)
- **Case-sensitive** - passwords are case-sensitive

## Security Notes

1. **Change default password immediately** after first login
2. **Use strong passwords** - mix of letters, numbers, and symbols
3. **Don't share passwords** - each user should have their own account
4. **Password is hashed** - stored securely using bcrypt

## Examples

### Change Admin Password

```bash
# Interactive (most secure)
php change-password.php

# Direct (less secure - visible in history)
php change-password.php admin MySecurePassword123!
```

### Change Password for Different User

```bash
php change-password.php username newpassword
```

### Verify Password Change

After changing password, test login:

```bash
# Test login API
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"your-new-password"}'
```

Expected response:
```json
{"success":true,"username":"admin","csrf_token":"...","encryption":"standard"}
```

## Troubleshooting

### Error: "User not found"

The username doesn't exist in the database. Check available users:

```bash
sqlite3 database/truai.db "SELECT username, role FROM users;"
```

### Error: "Password cannot be empty"

You must provide a password. Try again with a non-empty password.

### Error: "Passwords do not match"

The password and confirmation didn't match. Try again.

### Error: "Database connection failed"

1. Check database exists: `ls -la database/truai.db`
2. Check permissions: `chmod 644 database/truai.db`
3. Verify SQLite extension: `php -m | grep sqlite3`

## Direct Database Method (Advanced)

If the script doesn't work, you can change the password directly:

```bash
cd ~/Desktop/Tru.ai/TruAi
sqlite3 database/truai.db
```

Then in SQLite:

```sql
-- Generate password hash (run this in PHP first)
-- php -r "echo password_hash('your-new-password', PASSWORD_DEFAULT);"

-- Update password (replace HASH_HERE with output from above)
UPDATE users SET password_hash = 'HASH_HERE' WHERE username = 'admin';

-- Verify
SELECT username FROM users WHERE username = 'admin';
.quit
```

**Better method - use PHP to generate hash:**

```bash
# Generate hash
php -r "echo password_hash('your-new-password', PASSWORD_DEFAULT) . PHP_EOL;"

# Copy the hash, then update database
sqlite3 database/truai.db "UPDATE users SET password_hash = 'PASTE_HASH_HERE' WHERE username = 'admin';"
```

## Files

- **`change-password.php`** - Interactive password change utility
- **`reset-admin-password.php`** - Quick reset to default password

## Best Practices

1. ✅ Use the interactive script for security
2. ✅ Use strong, unique passwords
3. ✅ Change default password immediately
4. ✅ Don't reuse passwords
5. ✅ Keep passwords secure and private
