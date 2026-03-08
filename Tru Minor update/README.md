# Tru Minor Update — Import Reference

This folder contains all edited files and required assets from the TruAi minor update. Copy contents into your project (or repo) preserving the paths below.

## Folder structure (mirrors project root)

```
Tru Minor update/
├── README.md
├── TruAi_Login_Initialization_Restore.md   → project root
├── router.php                              → project root
├── start.html                              → project root
├── public/
│   ├── TruAi Prototype.html                → public/
│   └── TruAi/
│       ├── login-portal.html
│       ├── welcome.html
│       ├── start.html
│       ├── dashboard.html
│       ├── TruHybrid.html
│       └── loading.html
└── assets/
    ├── js/
    │   └── legal-notice-popup.js           → assets/js/
    └── images/                             → assets/images/
        (all required PNG, JPG, GIF, SVG)
```

## Import steps

1. **Project root:** Copy `router.php`, `start.html`, and `TruAi_Login_Initialization_Restore.md` into your TruAi project root (overwrite existing if updating).

2. **public/:** Copy `public/TruAi Prototype.html` to `public/` and everything inside `public/TruAi/` to `public/TruAi/`.

3. **assets/js/:** Copy `legal-notice-popup.js` to `assets/js/`.

4. **assets/images/:** Copy all files from `assets/images/` into your project’s `assets/images/` (merge; add or overwrite as needed).

## Files included

| Path | Description |
|------|-------------|
| `router.php` | Router: / → login-portal, /TruAi/ → dashboard |
| `TruAi_Login_Initialization_Restore.md` | Doc for login flow and verification |
| `start.html` | Start page (root copy) |
| `public/TruAi/*.html` | login-portal, welcome, start, dashboard, TruHybrid, loading |
| `public/TruAi Prototype.html` | Prototype dashboard |
| `assets/js/legal-notice-popup.js` | Legal popup logo path |
| `assets/images/*` | All images/GIFs used by the above HTML and router |

After copying, run from project root: `./start.sh` or `php -S localhost:8001 router.php` and open http://localhost:8001/ then http://localhost:8001/TruAi/ to verify.
