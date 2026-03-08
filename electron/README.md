# Tru.ai — TruAi Desktop (Electron)

Electron wrapper for **TruAi**, the AI-powered development assistant. This repo contains only the desktop app shell; the full TruAi web app and backend live in [DemeWebsolutions/TruAi](https://github.com/DemeWebsolutions/TruAi).

## What’s in this repo

| Path           | Purpose |
|----------------|--------|
| `main.js`      | Electron main process: window, menu, auto-start PHP server, IPC |
| `preload.js`   | Exposes `window.truaiElectron` (file dialogs, read/write) to the renderer |
| `package.json` | Dependencies and electron-builder config |
| `build/AppIcon.icns` | macOS app icon |

No backend, PHP, or dashboard UI are stored here. Those are in the main TruAi repo.

## Requirements

- **Node.js** 18+
- **TruAi project** on disk (clone [TruAi](https://github.com/DemeWebsolutions/TruAi) and use this app from inside it, or point the app at that path)

## Quick start (from full TruAi clone)

Use this app from the main TruAi repo so the server and assets are available:

```bash
# 1. Clone the full project
git clone https://github.com/DemeWebsolutions/TruAi.git
cd TruAi

# 2. Install and run the desktop app (it will start the PHP server and open the UI)
cd electron
npm install
npm start
```

The app finds the TruAi project root (parent of `electron/`), starts the PHP server on port 8001, and opens the TruAi interface in a native window.

## Build for distribution

From `electron/` inside a full TruAi clone (so `../public`, `../backend`, etc. exist for packaging):

```bash
npm install
npm run dist
```

Output: `dist/` (macOS: `.dmg` / `.zip`; Windows: NSIS; Linux: AppImage).

- **macOS:** `build/AppIcon.icns` is used as the app icon.
- **Windows/Linux:** Icon comes from `../assets/images/TruAi-icon.png` in the TruAi repo.

## Electron APIs (window.truaiElectron)

The preload script exposes:

| Method / property | Description |
|-------------------|-------------|
| `openFileBrowser(opts)` | Native open-file / open-folder dialog |
| `readFile(path)`        | Read a file by absolute path |
| `writeFile(path, content)` | Write content to disk |
| `getAppInfo()`          | App version, platform, project root |
| `platform`              | `'darwin'` / `'win32'` / `'linux'` |

## Syncing with TruAi

This repo is kept in sync with the `electron/` folder of [TruAi](https://github.com/DemeWebsolutions/TruAi). Changes to the desktop app are made in TruAi’s `electron/` and then pushed here (e.g. via subtree or manual copy).

## License

Proprietary — My Deme, LLC.
