/**
 * TruAi Electron — Main Process
 * Auto-starts the PHP server, then opens the TruAi interface.
 * Port 8001 · IPC bridge · Preload security · Menu
 */
'use strict';

const { app, BrowserWindow, nativeImage, ipcMain, dialog, shell, Menu } = require('electron');
const path   = require('path');
const { spawn } = require('child_process');
const http   = require('http');
const fs     = require('fs');

const supervisor = require('./supervisor');
const { PATHS, DEFAULT_PORTS, getUrls } = require('./runtimeContract');

const PORT            = DEFAULT_PORTS.TRUAI_PORT;
const TRUAI_LOGIN_URL = `http://127.0.0.1:${PORT}/TruAi/login-portal.html`;
const DASHBOARD_URL   = `file://${path.join(__dirname, 'dashboard.html')}`;

let phpProcess  = null;
let mainWindow  = null;
let TRUAI_ROOT  = null;
let permErrShown = false;

// ── Locate TruAi project root ─────────────────────────────────────────────────
function getTruAiRoot() {
  if (TRUAI_ROOT) return TRUAI_ROOT;
  const marker = 'router.php';
  function has(dir) { return dir && fs.existsSync(path.join(dir, marker)); }

  // Dev mode: parent of electron/
  if (!app.isPackaged) {
    const dev = path.join(__dirname, '..');
    if (has(dev)) { TRUAI_ROOT = dev; return TRUAI_ROOT; }
  }

  const candidates = [
    process.env.TRUAI_PROJECT_PATH,
    path.join(app.getPath('home'), 'TruAi'),
    path.join(app.getPath('home'), 'Desktop', 'TruAi'),
    path.join(app.getPath('documents'), 'TruAi'),
    '/Users/' + (process.env.USER || '') + '/Desktop/TruAi',
  ].filter(Boolean);

  for (const dir of candidates) {
    if (has(dir)) { TRUAI_ROOT = dir; return TRUAI_ROOT; }
  }

  // Last resort: saved path from previous launch
  const saved = path.join(app.getPath('userData'), 'truai-project-path.txt');
  if (fs.existsSync(saved)) {
    try {
      const dir = fs.readFileSync(saved, 'utf8').trim();
      if (has(dir)) { TRUAI_ROOT = dir; return TRUAI_ROOT; }
    } catch {}
  }
  return null;
}

// ── macOS Dock icon ───────────────────────────────────────────────────────────
function setDockIcon() {
  if (process.platform !== 'darwin') return;
  const root = getTruAiRoot();
  if (!root) return;
  const iconPath = path.join(root, 'assets', 'images', 'TruAi-icon.png');
  if (fs.existsSync(iconPath)) {
    app.dock.setIcon(nativeImage.createFromPath(iconPath));
  }
}

// ── Load .env into environment ────────────────────────────────────────────────
function loadEnv(root) {
  if (!root) return { ...process.env };
  const envPath = path.join(root, '.env');
  const env = { ...process.env };
  if (!fs.existsSync(envPath)) return env;
  try {
    fs.readFileSync(envPath, 'utf8').split('\n').forEach(line => {
      line = line.trim();
      if (!line || line.startsWith('#')) return;
      const idx = line.indexOf('=');
      if (idx < 1) return;
      let key = line.slice(0, idx).trim();
      let val = line.slice(idx + 1).trim();
      if ((val.startsWith('"') && val.endsWith('"')) || (val.startsWith("'") && val.endsWith("'")))
        val = val.slice(1, -1);
      env[key] = val;
    });
  } catch (e) { console.warn('Could not load .env:', e.message); }
  return env;
}

// ── Server health check ───────────────────────────────────────────────────────
function isServerRunning() {
  return new Promise(resolve => {
    const req = http.get(`http://127.0.0.1:${PORT}/`, res => resolve(true));
    req.on('error', () => resolve(false));
    req.setTimeout(1500, () => { req.destroy(); resolve(false); });
  });
}

function waitForServer(maxAttempts = 40) {
  return new Promise((resolve, reject) => {
    let attempts = 0;
    const tryConnect = () => {
      http.get(`http://127.0.0.1:${PORT}/`, () => resolve())
        .on('error', () => {
          if (++attempts >= maxAttempts) return reject(new Error('Server did not start in time'));
          setTimeout(tryConnect, 300);
        });
    };
    tryConnect();
  });
}

// ── Start PHP built-in server (legacy, used by supervisor too) ─────────────────
function startPhpServer(root) {
  const routerPath = path.join(root, 'router.php');
  if (!fs.existsSync(routerPath)) return false;

  const env = loadEnv(root);
  phpProcess = spawn('php', ['-S', `127.0.0.1:${PORT}`, 'router.php'], {
    cwd: root,
    env,
    stdio: ['ignore', 'pipe', 'pipe'],
  });

  phpProcess.stdout?.on('data', d => process.stdout.write(d.toString()));
  phpProcess.stderr?.on('data', d => process.stderr.write(d.toString()));
  phpProcess.on('error', err => { console.error('PHP start failed:', err.message); app.quit(); });
  phpProcess.on('exit', code => { if (code != null && code !== 0) console.error('PHP exited:', code); });
  return true;
}

// ── Create BrowserWindow ──────────────────────────────────────────────────────
function createWindow() {
  const root = getTruAiRoot();
  const preloadPath = path.join(__dirname, 'preload.js');
  const iconPath = root ? path.join(root, 'assets', 'images', 'TruAi-icon.png') : null;

  mainWindow = new BrowserWindow({
    width:     1440,
    height:    900,
    minWidth:  960,
    minHeight: 600,
    title:     'TruAi',
    show:      false,
    backgroundColor: '#0b0d11',
    ...(iconPath && fs.existsSync(iconPath) ? { icon: iconPath } : {}),
    webPreferences: {
      nodeIntegration:      false,
      contextIsolation:     true,
      sandbox:              false,
      preload:              fs.existsSync(preloadPath) ? preloadPath : undefined,
    },
    titleBarStyle: process.platform === 'darwin' ? 'hiddenInset' : 'default',
  });

  // Show window after content loads to avoid white flash
  mainWindow.once('ready-to-show', () => { mainWindow.show(); mainWindow.focus(); });
  // Safety: show after 3 s even if ready-to-show doesn't fire
  const showTimer = setTimeout(() => { if (mainWindow && !mainWindow.isDestroyed() && !mainWindow.isVisible()) mainWindow.show(); }, 3000);
  mainWindow.once('show', () => clearTimeout(showTimer));

  mainWindow.loadURL(TRUAI_LOGIN_URL).catch(err => {
    console.error('Failed to load TruAi:', err.message);
    app.quit();
  });

  // Open external links in the system browser instead of a new Electron window
  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    if (!url.startsWith('http://127.0.0.1') && !url.startsWith('http://localhost')) shell.openExternal(url);
    return { action: 'deny' };
  });

  // Intercept navigation to Phantom.ai (port 8080) — open in a dedicated window
  mainWindow.webContents.on('will-navigate', (event, url) => {
    if (/^https?:\/\/(127\.0\.0\.1|localhost):8080\b/.test(url)) {
      event.preventDefault();
      openPhantomWindow(url);
    }
  });

  mainWindow.on('closed', () => {
    mainWindow = null;
    stopPhpServer();
  });
}

function stopPhpServer() {
  if (phpProcess) { phpProcess.kill(); phpProcess = null; }
}

// ── Platform Dashboard (optional; does not alter main window UX) ─────────────────
let dashboardWindow = null;

function openDashboardWindow() {
  if (!fs.existsSync(path.join(__dirname, 'dashboard.html'))) return;
  if (dashboardWindow && !dashboardWindow.isDestroyed()) {
    dashboardWindow.focus();
    return;
  }
  dashboardWindow = new BrowserWindow({
    width: 900,
    height: 700,
    title: 'TruAi Platform',
    webPreferences: { nodeIntegration: false, contextIsolation: true, preload: path.join(__dirname, 'preload.js') },
  });
  dashboardWindow.loadFile(path.join(__dirname, 'dashboard.html'));
  dashboardWindow.on('closed', () => { dashboardWindow = null; });
}

// ── Phantom.ai Window ────────────────────────────────────────────────────────
let phantomWindow = null;

function openPhantomWindow(url) {
  if (phantomWindow && !phantomWindow.isDestroyed()) {
    phantomWindow.loadURL(url);
    phantomWindow.focus();
    return;
  }
  const root = getTruAiRoot();
  const iconPath = root ? path.join(root, 'assets', 'images', 'TruAi-icon.png') : null;

  phantomWindow = new BrowserWindow({
    width:     1280,
    height:    850,
    minWidth:  800,
    minHeight: 500,
    title:     'Phantom.ai',
    show:      false,
    backgroundColor: '#ffffff',
    ...(iconPath && fs.existsSync(iconPath) ? { icon: iconPath } : {}),
    webPreferences: {
      nodeIntegration:  false,
      contextIsolation: true,
      sandbox:          true,
    },
    titleBarStyle: process.platform === 'darwin' ? 'hiddenInset' : 'default',
  });

  phantomWindow.once('ready-to-show', () => { phantomWindow.show(); phantomWindow.focus(); });
  phantomWindow.loadURL(url);

  phantomWindow.webContents.setWindowOpenHandler(({ url: linkUrl }) => {
    if (!linkUrl.startsWith('http://127.0.0.1') && !linkUrl.startsWith('http://localhost')) shell.openExternal(linkUrl);
    return { action: 'deny' };
  });

  phantomWindow.on('closed', () => { phantomWindow = null; });
}

// ── Dialogs ───────────────────────────────────────────────────────────────────
function showProjectNotFound() {
  dialog.showMessageBox({
    type:    'info',
    title:   'TruAi — Project Not Found',
    message: 'TruAi project folder not found.',
    detail:  'The app looks for your TruAi project at:\n' +
             '• ~/TruAi  (recommended)\n' +
             '• ~/Desktop/TruAi\n' +
             '• ~/Documents/TruAi\n\n' +
             'Or set TRUAI_PROJECT_PATH before launching, or start the server manually:\n' +
             '  cd ~/Desktop/TruAi && php -S 127.0.0.1:8001 router.php\n' +
             'Then re-open TruAi.app.',
    buttons: ['OK'],
  }).then(() => app.quit());
}

function showPermissionError() {
  permErrShown = true;
  dialog.showMessageBox({
    type:    'warning',
    title:   'TruAi — Permission Required',
    message: 'Cannot read the TruAi project folder.',
    detail:  'macOS is blocking access. Options:\n\n' +
             '1. Grant Full Disk Access:\n' +
             '   System Settings → Privacy & Security → Full Disk Access → add TruAi\n\n' +
             '2. Start the server from Terminal first:\n' +
             '   cd ~/Desktop/TruAi && php -S 127.0.0.1:8001 router.php\n' +
             '   Then re-open TruAi.app.',
    buttons: ['OK'],
  }).then(() => app.quit());
}

// ── App startup ───────────────────────────────────────────────────────────────
function openApp() {
  const root = getTruAiRoot();
  if (!root) { showProjectNotFound(); return; }
  try { fs.accessSync(path.join(root, 'router.php'), fs.constants.R_OK); }
  catch { showPermissionError(); return; }

  const hasDashboard = fs.existsSync(path.join(__dirname, 'dashboard.html'));
  if (hasDashboard) {
    supervisor.ensureDirs();
    if (supervisor.isLockedAndAlive()) { createWindow(); return; }
    isServerRunning().then(running => {
      if (running) { createWindow(); return; }
      startPhpServer(root);
      waitForServer().then(() => createWindow()).catch(err => {
        console.error(err.message);
        stopPhpServer();
        if (!permErrShown) showProjectNotFound();
      });
    });
  } else {
    isServerRunning().then(running => {
      if (running) { createWindow(); return; }
      const started = startPhpServer(root);
      if (!started) { showProjectNotFound(); return; }
      waitForServer().then(() => createWindow()).catch(err => {
        console.error(err.message);
        stopPhpServer();
        if (!permErrShown) showProjectNotFound();
      });
    });
  }
}

// ── App menu ──────────────────────────────────────────────────────────────────
function buildMenu() {
  const template = [
    {
      label: 'File',
      submenu: [
        { label: 'Open File(s)…',  accelerator: 'CmdOrCtrl+O',       click: () => mainWindow?.webContents.executeJavaScript('typeof openFileBrowserChoice==="function"&&openFileBrowserChoice()') },
        { label: 'Open Folder…',   accelerator: 'CmdOrCtrl+Shift+O', click: () => mainWindow?.webContents.executeJavaScript('document.getElementById("contentDirInput")?.click()') },
        { type: 'separator' },
        { label: 'Platform Dashboard…', click: () => openDashboardWindow() },
        { type: 'separator' },
        { role: 'quit' },
      ],
    },
    {
      label: 'View',
      submenu: [
        { role: 'reload' },
        { role: 'forceReload' },
        { role: 'toggleDevTools' },
        { type: 'separator' },
        { role: 'resetZoom' },
        { role: 'zoomIn' },
        { role: 'zoomOut' },
        { type: 'separator' },
        { role: 'togglefullscreen' },
      ],
    },
    { label: 'Edit',   role: 'editMenu' },
    { label: 'Window', role: 'windowMenu' },
  ];
  Menu.setApplicationMenu(Menu.buildFromTemplate(template));
}

// ── IPC: native file browser ──────────────────────────────────────────────────
ipcMain.handle('truai:open-file-browser', async (_event, opts = {}) => {
  const result = await dialog.showOpenDialog(mainWindow, {
    title:      opts.title || 'Select Files or Folder',
    properties: opts.folder
      ? ['openDirectory', 'multiSelections', 'createDirectory']
      : ['openFile', 'multiSelections'],
    filters: opts.folder ? [] : [
      { name: 'Code & Web', extensions: ['html','css','js','ts','jsx','tsx','php','json','md','txt','sh','yaml','yml','xml','py','rb','sql','env','csv','toml','ini','conf','gitignore','htaccess'] },
      { name: 'Images',     extensions: ['png','jpg','jpeg','gif','webp','svg','ico'] },
      { name: 'All Files',  extensions: ['*'] },
    ],
  });
  if (result.canceled) return [];

  const TEXT_EXTS = new Set(['html','css','js','ts','jsx','tsx','php','json','md','txt','sh','yaml','yml','xml','py','rb','sql','env','csv','toml','ini','conf','htaccess','gitignore']);

  if (opts.folder && result.filePaths.length > 0) {
    const dirPath = result.filePaths[0];
    return scanDir(dirPath, 4).map(p => {
      const ext = path.extname(p).slice(1).toLowerCase();
      let content = null;
      if (TEXT_EXTS.has(ext)) { try { content = fs.readFileSync(p, 'utf8'); } catch {} }
      return { path: p, name: path.basename(p), relativePath: path.relative(dirPath, p), content };
    });
  }

  return result.filePaths.map(fp => {
    try   { return { path: fp, name: path.basename(fp), content: fs.readFileSync(fp, 'utf8') }; }
    catch { return { path: fp, name: path.basename(fp), content: null }; }
  });
});

// ── IPC: read / write file ────────────────────────────────────────────────────
ipcMain.handle('truai:read-file', async (_event, filePath) => {
  try   { return { content: fs.readFileSync(filePath, 'utf8'), path: filePath }; }
  catch (e) { return { error: e.message }; }
});

ipcMain.handle('truai:write-file', async (_event, filePath, content) => {
  try   { fs.writeFileSync(filePath, content, 'utf8'); return { success: true }; }
  catch (e) { return { error: e.message }; }
});

// ── IPC: app info ─────────────────────────────────────────────────────────────
ipcMain.handle('truai:app-info', () => ({
  version:     app.getVersion(),
  platform:    process.platform,
  projectRoot: getTruAiRoot() || '',
  userDataDir: app.getPath('userData'),
}));

// ── IPC: Trinity supervisor (dashboard) ───────────────────────────────────────
ipcMain.handle('trinity:get-status', async () => {
  const urls = getUrls(supervisor.getPorts());
  const token = process.env.TRUAI_TOKEN || 'change-me-truai-1';
  return {
    qdrant: await supervisor.checkQdrant(supervisor.getPorts().QDRANT_PORT),
    ollama: await supervisor.checkOllama(supervisor.getPorts().OLLAMA_PORT),
    gateway: await supervisor.checkGateway(supervisor.getPorts().GATEWAY_PORT, token),
    php: await isServerRunning(),
    ollamaModel: await supervisor.checkOllamaModel(),
  };
});

ipcMain.handle('trinity:start-stack', async () => {
  const root = getTruAiRoot();
  if (!root) return { error: 'TruAi project not found' };
  if (supervisor.isLockedAndAlive()) return { error: 'Stack already running' };
  supervisor.ensureDirs();
  const portCheck = await supervisor.checkPorts();
  if (portCheck.TRUAI_PORT || portCheck.GATEWAY_PORT) return { error: 'Ports in use. Stop other services or use auto-select.' };
  supervisor.acquireLock();
  const token = process.env.TRUAI_TOKEN || 'change-me-truai-1';
  const qr = await supervisor.startQdrant();
  if (!qr.ok) console.warn('Qdrant:', qr.error);
  const gr = await supervisor.startGateway(token);
  if (!gr.ok) console.warn('Gateway:', gr.error);
  const pr = supervisor.startPhp(root);
  if (!pr.ok) console.warn('PHP:', pr.error);
  phpProcess = supervisor.processes.php || phpProcess;
  return { ok: true };
});

ipcMain.handle('trinity:stop-stack', () => {
  supervisor.stopAll();
  stopPhpServer();
  return { ok: true };
});

ipcMain.handle('trinity:get-log', async (_, name) => {
  const logPath = PATHS[`${name}Log`] || path.join(PATHS.logs, `${name}.log`);
  try {
    const content = fs.readFileSync(logPath, 'utf8');
    return content.slice(-50000);
  } catch { return ''; }
});

ipcMain.handle('trinity:get-config-path', () => PATHS.configJson);
ipcMain.handle('trinity:get-config', async () => {
  try {
    return fs.readFileSync(PATHS.configJson, 'utf8');
  } catch { return '{}'; }
});
ipcMain.handle('trinity:save-config', async (_, content) => {
  try {
    fs.mkdirSync(PATHS.config, { recursive: true });
    fs.writeFileSync(PATHS.configJson, content, 'utf8');
    return { ok: true };
  } catch (e) { return { error: e.message }; }
});
ipcMain.handle('trinity:get-truai-url', () => TRUAI_LOGIN_URL);

// ── Helper: recursive directory scan ─────────────────────────────────────────
function scanDir(dir, depth, results = []) {
  if (depth <= 0) return results;
  try {
    fs.readdirSync(dir, { withFileTypes: true }).forEach(e => {
      if (e.name.startsWith('.') || e.name === 'node_modules') return;
      const full = path.join(dir, e.name);
      if (e.isDirectory()) scanDir(full, depth - 1, results);
      else results.push(full);
    });
  } catch {}
  return results;
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────
app.whenReady().then(() => {
  app.setName('TruAi');
  if (process.platform === 'darwin') process.title = 'TruAi';
  setDockIcon();
  buildMenu();
  openApp();

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) openApp();
  });
});

app.on('window-all-closed', () => {
  supervisor.stopAll();
  stopPhpServer();
  app.quit();
});

app.on('before-quit', () => {
  supervisor.stopAll();
  stopPhpServer();
});
