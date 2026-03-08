# TruAi Login Initialization Restore

## Description of the Original Issue

The TruAi application had diverged from the intended startup behavior:

1. **Root URL** was not consistently serving the login portal from `public/TruAi/login-portal.html`.
2. **Post-login flow** sent users directly to the dashboard (`/TruAi/`) instead of the welcome screen and animation sequence.
3. **Router** in backups served `/TruAi/` as `TruAi Prototype.html`; the active project needed to serve `dashboard.html` at that path.
4. **Legacy routes** for `/welcome`, `/start`, `/loading`, and `/login-portal` pointed at the project root instead of `public/TruAi/`, where the actual HTML files live.

## Restored Startup Behavior

The application now follows this sequence:

| Step | URL / Action | Result |
|------|----------------|--------|
| 1 | User opens `http://localhost:8001/` | **Login portal** (`public/TruAi/login-portal.html`) is served. |
| 2 | User submits valid credentials | Login portal redirects to **welcome** with optional `?username=...`. |
| 3 | `http://localhost:8001/TruAi/welcome.html` | **Welcome screen** with animated GIF (e.g. Welcome.gif); click or keypress or timer (25s) advances. |
| 4 | Auto or user advance | Redirect to **start** (`/TruAi/start.html`) — selection/transition screen. |
| 5 | start.html flow (e.g. project selection + 10s) | Redirect to **dashboard** (`/TruAi/`). |
| 6 | `http://localhost:8001/TruAi/` | **Dashboard workspace** (`public/TruAi/dashboard.html`) is served. |

**Final sequence:**  
**Login Portal → Welcome Screen → Animation Sequence (welcome → start) → Dashboard Workspace**

## router.php Routing Configuration

### Root route

- **Request:** `http://localhost:8001/` or `http://localhost:8001` (empty path).
- **Action:** Serve `public/TruAi/login-portal.html`.
- **Implementation:** If `$requestUri === '/' || $requestUri === ''`, `readfile(__DIR__ . '/public/TruAi/login-portal.html')` and return.

### TruAi application route

- **Request:** `http://localhost:8001/TruAi/` or `http://localhost:8001/TruAi`.
- **Action:** Serve `public/TruAi/dashboard.html` (replacing any previous “TruAi Prototype” mapping).
- **Implementation:** If `preg_match('#^/TruAi/?$#', $requestUri)`, `readfile(__DIR__ . '/public/TruAi/dashboard.html')` and return.

### Other HTML routes (public/TruAi/)

- `/TruAi/*.html` — Served from `public/TruAi/` (e.g. `welcome.html`, `start.html`, `login-portal.html`).
- Bare paths such as `/welcome`, `/start`, `/loading`, `/login-portal` (with or without `.html`) are served from `public/TruAi/<name>.html` when that file exists.

### Static files

- **Paths:** CSS, JS, images, GIFs, fonts (e.g. `.css`, `.js`, `.png`, `.jpg`, `.jpeg`, `.gif`, `.svg`, `.ico`, `.woff`, `.woff2`, `.ttf`, `.eot`, `.json`).
- **Behavior:** Resolved under the project directory; requests under `/TruAi/` are mapped by stripping the `/TruAi` prefix so that `/TruAi/assets/...` resolves to project `assets/...`.
- **Location:** Static assets are not required to live under `public/`; they are served from the project root (e.g. `assets/`) as configured in the router.

## Files Modified or Added

### Modified

| File | Change |
|------|--------|
| `public/TruAi/login-portal.html` | On successful login, redirect to `WELCOME_URL` (`/TruAi/welcome.html`) instead of `DASHBOARD_URL`; optional `?username=...`; kept `DASHBOARD_URL` for reference. |
| `public/TruAi/welcome.html` | Comment updated to describe flow: welcome.gif → start.html → dashboard. |
| `public/TruAi/start.html` | Final redirect changed from `http://localhost:8001/TruAi/` to `'/TruAi/'` so it works on any host/port. |
| `router.php` | (1) Root `/` explicitly serves `public/TruAi/login-portal.html`. (2) `/TruAi/` serves `public/TruAi/dashboard.html`. (3) Docblock updated with required flow. (4) Single consolidated block for `/welcome`, `/start`, `/loading`, `/access-granted`, `/access-denied`, `/login-portal` serving from `public/TruAi/<name>.html` when present. |

### Added

| File | Purpose |
|------|--------|
| `TruAi_Login_Initialization_Restore.md` | This documentation. |

### Unchanged (reference)

- Backups analyzed: `TruAi_backup_20260212054105`, `TruAi-backups/TruAi-backup-20260212_0532` (gemini-deploy-plesk-20260208). Backup router served `/TruAi/` as `TruAi Prototype.html`; the active project correctly uses `dashboard.html` at `/TruAi/`.

## Dependencies / Scripts Used

- **Frontend:** No new scripts. Login portal uses inline JS and `/TruAi/assets/js/api.js`; welcome and start use inline JS.
- **Backend:** Existing `router.php` and `backend/` (e.g. auth, config, database) for API and auth; no changes to API or auth logic.
- **Assets:** Welcome GIF and other images under `assets/images/` (e.g. `Welcome.gif`) referenced by `/TruAi/assets/...` are served by the existing static-file handling in `router.php`.

## Verification Procedure

1. **Start server** (from project root):  
   `php -S localhost:8001 router.php`  
   (or use `start.sh` if it invokes this.)

2. **Root → Login portal**  
   - Open `http://localhost:8001/`.  
   - Expected: Login portal page (TruAi login UI).  
   - If you see a different page or 404, root routing is wrong.

3. **Login → Welcome**  
   - On the login portal, submit valid credentials (e.g. DEV_MODE: admin / password123).  
   - Expected: Redirect to `http://localhost:8001/TruAi/welcome.html` (with optional `?username=...`).  
   - Page shows welcome content (e.g. welcome GIF).

4. **Welcome → Start**  
   - Wait for the welcome timer (e.g. 25s), or click/keypress.  
   - Expected: Redirect to `http://localhost:8001/TruAi/start.html`.  
   - Start/transition screen (e.g. project selection, then 10s delay).

5. **Start → Dashboard**  
   - After start flow completes (e.g. 10s or selection).  
   - Expected: Redirect to `http://localhost:8001/TruAi/`.  
   - Dashboard workspace loads (`dashboard.html`).

6. **Direct dashboard**  
   - Open `http://localhost:8001/TruAi/`.  
   - Expected: Dashboard workspace only (no login required by router; auth may still be enforced by app logic).

7. **Static assets**  
   - Load a page that uses CSS/JS/images (e.g. login portal, welcome, dashboard).  
   - Expected: No 404s for `/TruAi/assets/...` or other static URLs; GIF and images load if referenced correctly.

If all steps behave as above, the restored login initialization and router configuration are working as intended.
