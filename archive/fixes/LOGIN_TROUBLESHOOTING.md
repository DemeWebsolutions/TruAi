# TruAi Login Troubleshooting Guide

## ✅ Current Status

All login components are **working correctly**:

- ✅ Server running on `http://localhost:8080`
- ✅ API endpoints configured correctly (`/api/v1`)
- ✅ Database exists with admin user
- ✅ Static assets loading (CSS, JS, images)
- ✅ Login API responding correctly
- ✅ Error handling in place

## 🧪 Test Results

### API Tests
- **Valid Login**: ✅ Returns success with CSRF token
- **Invalid Credentials**: ✅ Properly rejected with error message
- **Missing Fields**: ✅ Returns 400 error
- **Auth Status**: ✅ Returns authentication state

### Frontend Tests
- **API Base Config**: ✅ Correctly set to `/api/v1`
- **Endpoint Paths**: ✅ No double `/api/v1` prefix
- **Static Assets**: ✅ All loading (200 status)
- **Error Display**: ✅ Error messages shown

## 🔧 How to Test Login

### 1. Open Browser
Navigate to: `http://localhost:8080`

### 2. Check Browser Console
Open Developer Tools (F12) and check for:
- ✅ No JavaScript errors
- ✅ API calls being made
- ✅ Responses received

### 3. Test Login
**Valid Credentials:**
- Username: `admin`
- Password: `admin123`

**Expected Behavior:**
1. Form submission prevents default
2. Button shows "Signing in..." or "🔒 Encrypting & Signing in..."
3. API call to `/api/v1/auth/login`
4. Success response with CSRF token
5. Page reloads to dashboard

### 4. Test Error Cases
**Invalid Credentials:**
- Username: `admin`
- Password: `wrongpassword`
- Expected: Error message displayed

**Missing Terms Acceptance:**
- Don't check the checkbox
- Expected: "You must accept the Terms of Service to continue"

## 🐛 Common Issues & Solutions

### Issue 1: "Not Found" Error
**Symptoms:** Browser shows 404 or "not found"
**Solution:** 
- Verify server is running: `ps aux | grep "php -S localhost:8080"`
- Check router.php is being used: `php -S localhost:8080 router.php`
- Verify API endpoints don't have double `/api/v1` prefix

### Issue 2: Login Button Not Responding
**Symptoms:** Clicking login does nothing
**Solution:**
- Check browser console for JavaScript errors
- Verify `login.js` is loaded: Check Network tab
- Verify form has `id="loginForm"` and button has `id="loginBtn"`

### Issue 3: "Invalid credentials" Always Shows
**Symptoms:** Even with correct credentials, login fails
**Solution:**
- Check database: `sqlite3 database/truai.db "SELECT * FROM users;"`
- Verify password hash is correct
- Check API response in Network tab

### Issue 4: API Calls Return 404
**Symptoms:** Network tab shows 404 for API calls
**Solution:**
- Verify `API_BASE` in `index.php` is `/api/v1`
- Check `api.js` endpoints don't include `/api/v1` prefix
- Verify `router.php` is routing API requests correctly

### Issue 5: CORS Errors
**Symptoms:** Browser console shows CORS errors
**Solution:**
- Verify `CORS_ENABLED` is `true` in `backend/config.php`
- Check `CORS_ORIGIN` is set correctly
- Ensure server is running on `localhost:8080`

## 🔍 Debugging Steps

### Step 1: Check Server Logs
```bash
# Server should be running with router.php
ps aux | grep "php -S localhost:8080"
```

### Step 2: Test API Directly
```bash
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'
```

### Step 3: Check Browser Network Tab
1. Open Developer Tools (F12)
2. Go to Network tab
3. Try to login
4. Check:
   - Request URL is correct
   - Request method is POST
   - Response status is 200
   - Response body contains JSON

### Step 4: Check Browser Console
1. Open Developer Tools (F12)
2. Go to Console tab
3. Look for:
   - JavaScript errors
   - API errors
   - Network errors

### Step 5: Verify Database
```bash
cd ~/Desktop/Tru.ai/TruAi
sqlite3 database/truai.db "SELECT username, role FROM users;"
```

## 📋 Quick Test Script

Run the test suite:
```bash
cd ~/Desktop/Tru.ai/TruAi
./test-login-flow.sh
```

## ✅ Verification Checklist

- [ ] Server running on port 8080
- [ ] Router.php is being used
- [ ] Database exists with admin user
- [ ] API endpoints respond correctly
- [ ] Static assets load (200 status)
- [ ] Browser console has no errors
- [ ] Login form submits correctly
- [ ] Error messages display properly
- [ ] Success redirects to dashboard

## 🚀 Next Steps

If login is still not working:

1. **Check Browser Console** - Look for specific error messages
2. **Check Network Tab** - Verify API calls are being made
3. **Run Test Suite** - Execute `./test-login-flow.sh`
4. **Check Server Logs** - Look for PHP errors
5. **Verify Database** - Ensure admin user exists

## 📞 Support

If issues persist:
- Check `truai-debug.log` for errors
- Review server terminal output
- Verify all files are in correct locations
- Ensure PHP version is 8.0+
