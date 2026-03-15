/**
 * Phantom.ai — Electron main process
 * Client only; calls Memory Gateway at http://127.0.0.1:8010
 */
'use strict';

const { app, BrowserWindow, ipcMain } = require('electron');
const path = require('path');
const fs = require('fs');

const { MemoryClient } = require('@truai/memory-client');

let mainWindow = null;
let gatewayUrl = 'http://127.0.0.1:8010';
let token = process.env.PHANTOM_TOKEN || '';

ipcMain.handle('phantom:gateway-url', () => gatewayUrl);
ipcMain.handle('phantom:set-gateway-url', (_, url) => { gatewayUrl = url; });
ipcMain.handle('phantom:get-token', () => token);
ipcMain.handle('phantom:set-token', (_, t) => { token = t; });
ipcMain.handle('phantom:query', async (_, opts) => {
  const client = new MemoryClient({ baseUrl: gatewayUrl, token, strictFailClosed: false, retries: 3 });
  return client.query(opts);
});
ipcMain.handle('phantom:upsert', async (_, opts) => {
  const client = new MemoryClient({ baseUrl: gatewayUrl, token, strictFailClosed: false, retries: 3 });
  return client.upsert(opts);
});

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1280,
    height: 800,
    minWidth: 800,
    minHeight: 500,
    title: 'Phantom.ai',
    webPreferences: { nodeIntegration: false, contextIsolation: true, preload: path.join(__dirname, 'preload.js') },
  });
  mainWindow.loadFile('index.html');
  mainWindow.on('closed', () => { mainWindow = null; });
}

app.whenReady().then(createWindow);
app.on('window-all-closed', () => app.quit());
app.on('activate', () => { if (!mainWindow) createWindow(); });
