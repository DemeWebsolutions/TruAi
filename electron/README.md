# TruAi Electron Desktop App

Wraps the TruAi web interface in an Electron window with native filesystem access.

## Quick Start

```bash
# 1 — Start the PHP backend (in the project root)
cd /path/to/TruAi
php -S localhost:8001 router.php

# 2 — In a second terminal, launch Electron
cd /path/to/TruAi/electron
npm install
npm start
```

The app loads `http://localhost:8001/TruAi/` in a native window.
If the PHP server is not running, it falls back to loading `public/TruAi/dashboard.html` directly.

## Build for Distribution

```bash
# macOS (universal: Apple Silicon + Intel)
npm run pack

# Windows
npm run pack:win

# Linux (AppImage)
npm run pack:linux
```

Output is placed in `electron/dist/`.

## Electron APIs (window.truaiElectron)

The preload script exposes these APIs to the dashboard:

| Method | Description |
|---|---|
| `openFileBrowser(opts)` | Native open-file / open-folder dialog |
| `readFile(path)` | Read a file from disk by absolute path |
| `writeFile(path, content)` | Write content to disk |
| `getAppInfo()` | App version, platform, project root |
| `platform` | `'darwin'` / `'win32'` / `'linux'` |

### Example (dashboard JS)

```js
if (window.truaiElectron) {
  const files = await window.truaiElectron.openFileBrowser({ multiple: true });
  files.forEach(f => TruAiAddPathToContentPanel(f.path, f.content));
}
```

## File Structure

```
electron/
  main.js       — Electron main process (BrowserWindow, IPC handlers)
  preload.js    — Secure contextBridge API exposed to renderer
  package.json  — Dependencies and electron-builder config
  README.md     — This file
```
