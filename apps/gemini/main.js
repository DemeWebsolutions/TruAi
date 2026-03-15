/**
 * Gemini.ai — Electron main process
 * Client only; connects over WireGuard. Strict fail-closed.
 */
'use strict';

const { app, BrowserWindow, ipcMain } = require('electron');
const path = require('path');

const { MemoryClient } = require('@truai/memory-client');

let mainWindow = null;
let gatewayUrl = process.env.GEMINI_GATEWAY_URL || 'http://10.100.0.1:8010';
let token = process.env.GEMINI_TOKEN || '';

ipcMain.handle('gemini:gateway-url', () => gatewayUrl);
ipcMain.handle('gemini:set-gateway-url', (_, url) => { gatewayUrl = url; });
ipcMain.handle('gemini:get-token', () => token);
ipcMain.handle('gemini:set-token', (_, t) => { token = t; });
ipcMain.handle('gemini:query', async (_, opts) => {
  const client = new MemoryClient({ baseUrl: gatewayUrl, token, strictFailClosed: true, retries: 0 });
  return client.query(opts);
});
ipcMain.handle('gemini:upsert', async (_, opts) => {
  const client = new MemoryClient({ baseUrl: gatewayUrl, token, strictFailClosed: true, retries: 0 });
  return client.upsert(opts);
});
ipcMain.handle('gemini:check-wg', async () => {
  try {
    const http = require('http');
    const url = new URL(gatewayUrl);
    const res = await new Promise((resolve, reject) => {
      const req = http.get(`${gatewayUrl}/health`, { headers: { Authorization: `Bearer ${token}` }, timeout: 3000 }, resolve);
      req.on('error', reject);
    });
    const chunks = [];
    for await (const c of res) chunks.push(c);
    const data = JSON.parse(Buffer.concat(chunks).toString());
    return { ok: res.statusCode === 200, zone: data.zone };
  } catch (e) { return { ok: false, error: e.message }; }
});

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1280,
    height: 800,
    minWidth: 800,
    minHeight: 500,
    title: 'Gemini.ai',
    webPreferences: { nodeIntegration: false, contextIsolation: true, preload: path.join(__dirname, 'preload.js') },
  });
  mainWindow.loadFile('index.html');
  mainWindow.on('closed', () => { mainWindow = null; });
}

app.whenReady().then(createWindow);
app.on('window-all-closed', () => app.quit());
app.on('activate', () => { if (!mainWindow) createWindow(); });
