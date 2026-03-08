# Invalid Credentials - Troubleshooting Guide

## ✅ Current Status

The login system is **working correctly** when tested:
- ✅ API endpoint responds correctly
- ✅ Database has admin user
- ✅ Password hash is valid (bcrypt)
- ✅ Authentication logic works

## 🔍 Diagnosis

### Test Results:
```bash
# Direct API test - WORKS
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'
# Returns: {"success":true,...}

# PHP direct test - WORKS
php -r "require 'backend/auth.php'; \$auth = new Auth(); \$auth->login('admin', 'admin123');"
# Returns: SUCCESS
```

### Correct Credentials:
- **Username:** `admin` (case-sensitive)
- **Password:** `admin123` (case-sensitive, lowercase)

## 🐛 Common Causes of "Invalid Credentials"

### 1. Wrong Password
**Solution:** Ensure you're using exactly:
- Username: `admin` (all lowercase)
- Password: `admin123` (all lowercase, no spaces)

### 2. Encrypted Login Failing
If the browser is trying encrypted login and it fails:
- Check browser console for encryption errors
- The system falls back to standard login automatically
- If encryption fails, standard login should still work

### 3. Session Issues
**Solution:** Clear browser cookies and try again:
```javascript
// In browser console:
document.cookie.split(";").forEach(c => {
    document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/");
});
location.reload();
```

### 4. API Request Not Reaching Backend
**Check:**
- Open browser Developer Tools (F12)
- Go to Network tab
- Try to login
- Check the `/api/v1/auth/login` request:
  - Status should be 200 (success) or 401 (invalid)
  - Request payload should show `{"username":"admin","password":"admin123"}`
  - Response should show JSON

### 5. CORS or Routing Issues
**Check:**
- Verify server is running: `ps aux | grep "php -S localhost:8080"`
- Verify router.php is being used
- Check browser console for CORS errors

## 🔧 Solutions

### Solution 1: Reset Admin Password
If you need to reset the password:

```bash
cd ~/Desktop/Tru.ai/TruAi
php reset-admin-password.php
```

This will reset the admin password to `admin123`.

### Solution 2: Create New Admin User
If admin user doesn't exist:

```bash
cd ~/Desktop/Tru.ai/TruAi
sqlite3 database/truai.db
```

Then in SQLite:
```sql
INSERT INTO users (username, password_hash, role) 
VALUES ('admin', '$2y$10$...', 'SUPER_ADMIN');
```

Or use PHP:
```php
require_once 'backend/config.php';
require_once 'backend/database.php';

$db = Database::getInstance();
$passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
$db->execute(
    "INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)",
    ['admin', $passwordHash, 'SUPER_ADMIN']
);
```

### Solution 3: Check Browser Console
1. Open `http://localhost:8080`
2. Press F12 to open Developer Tools
3. Go to Console tab
4. Try to login
5. Look for:
   - JavaScript errors
   - API errors
   - Network errors

### Solution 4: Test API Directly
Test the API endpoint directly:

```bash
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'
```

Expected response:
```json
{"success":true,"username":"admin","csrf_token":"...","encryption":"standard"}
```

## 📋 Verification Checklist

- [ ] Server is running on port 8080
- [ ] Using correct username: `admin` (lowercase)
- [ ] Using correct password: `admin123` (lowercase)
- [ ] No typos or extra spaces
- [ ] Browser console shows no errors
- [ ] Network tab shows API request
- [ ] API request has correct payload
- [ ] Database has admin user
- [ ] Password hash is valid

## 🧪 Debug Steps

### Step 1: Check Database
```bash
cd ~/Desktop/Tru.ai/TruAi
sqlite3 database/truai.db "SELECT username, role FROM users;"
```

### Step 2: Test Password Verification
```bash
cd ~/Desktop/Tru.ai/TruAi
php -r "
require 'backend/config.php';
require 'backend/database.php';
require 'backend/auth.php';
\$auth = new Auth();
echo \$auth->login('admin', 'admin123') ? 'SUCCESS' : 'FAILED';
"
```

### Step 3: Check Browser Network Request
1. Open Developer Tools (F12)
2. Network tab
3. Try login
4. Click on `/api/v1/auth/login` request
5. Check:
   - Request Method: POST
   - Request Payload: `{"username":"admin","password":"admin123"}`
   - Response Status: 200 or 401
   - Response Body: JSON

### Step 4: Check Browser Console
Look for:
- `API Error: ...`
- `Login failed: ...`
- Network errors
- CORS errors

## 🚀 Quick Fix

If nothing else works, reset the password:

```bash
cd ~/Desktop/Tru.ai/TruAi
php reset-admin-password.php
```

Then try logging in again with:
- Username: `admin`
- Password: `admin123`

## 📞 Still Having Issues?

1. **Check the exact error message** in browser console
2. **Check Network tab** for the API request/response
3. **Verify credentials** are exactly `admin` / `admin123`
4. **Clear browser cache and cookies**
5. **Try in incognito/private window**
