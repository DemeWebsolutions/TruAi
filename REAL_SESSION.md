# TruAi Real Session — Step-by-Step

Testing is over. All logins use **real backend authentication**. Test/default passwords no longer work.

---

## 1. Get initial credentials (first-time or reset)

- **From setup:** If you ran `php scripts/setup_database.php`, one-time credentials were written to `database/.initial_credentials` (username and random password).
- **From reset:** Run:
  ```bash
  php scripts/reset_admin_password.php admin
  ```
  New temporary password is written to `database/.initial_credentials`. The user is flagged to **change password on first login**.

---

## 2. Start the server

```bash
./start.sh
# or: php -S localhost:8001 router.php
```

Open: **http://localhost:8001/**

---

## 3. Log in

- Enter the **username** and **password** from `database/.initial_credentials` (or your existing account).
- If the account has **requires_password_change** (e.g. after a reset), you are redirected to **Set your password** instead of the loading/welcome flow.

---

## 4. First-time: set your password

- At **Set your password** (`/TruAi/first-time-setup.html`):
  - Enter your **current (temporary) password**.
  - Enter a **new password** (at least 12 characters; must include uppercase, lowercase, a number, and a special character).
  - Confirm the new password and submit.
- On success you are sent to the **loading** → **welcome** → **start** → **dashboard** flow.

---

## 5. Optional: biometric setup

- After logging in (and changing password if required), you can enroll Touch ID / Face ID or other methods:
  - Open **http://localhost:8001/TruAi/ubsas-enroll.html** (or use the “Enroll Device” link from the first-time-setup page).
- Follow the UBSAS enrollment steps (choose method, verify OS credentials, enroll).
- See `docs/UBSAS_SPEC.md` and `scripts/setup_biometric_auth.sh` for server-side biometric setup if needed.

---

## 6. Normal login (after first-time setup)

- Once your password is set (and optionally biometric enrolled), use **http://localhost:8001/** to log in with your **real** username and password.
- Flow: **Login** → **Loading (GIF)** → **Welcome (GIF)** → **Start** → **Dashboard**. No test credentials; session is created only after successful backend auth.

---

## Summary

| Step | Action |
|------|--------|
| 1 | Get credentials from `database/.initial_credentials` or create/reset user (e.g. `reset_admin_password.php`). |
| 2 | Start server; open http://localhost:8001/ |
| 3 | Log in with those credentials (real API only). |
| 4 | If prompted, complete **Set your password** (first-time-setup). |
| 5 | Optionally enroll biometric at `/TruAi/ubsas-enroll.html`. |
| 6 | Use the app; all API calls use the real session. |

Test/default passwords are disabled. Only backend-validated credentials create a session.
