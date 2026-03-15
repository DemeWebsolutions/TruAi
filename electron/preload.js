/**
 * TruAi Electron Preload Script
 *
 * Exposes a secure `window.truaiElectron` API to the renderer (dashboard).
 * All Node/Electron capabilities are gated through contextBridge — the
 * renderer never gets direct Node access.
 */

'use strict';

const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('truaiElectron', {

  /**
   * Open a native file or folder browser dialog.
   * @param {object} opts  { multiple: bool, folder: bool, title: string }
   * @returns {Promise<Array>}  Array of { path, name, content?, relativePath? }
   */
  openFileBrowser: (opts = {}) => ipcRenderer.invoke('truai:open-file-browser', opts),

  /**
   * Read a file from disk by absolute path.
   * @param {string} filePath
   * @returns {Promise<{content: string}|{error: string}>}
   */
  readFile: (filePath) => ipcRenderer.invoke('truai:read-file', filePath),

  /**
   * Write content to a file at absolute path.
   * @param {string} filePath
   * @param {string} content
   * @returns {Promise<{success: true}|{error: string}>}
   */
  writeFile: (filePath, content) => ipcRenderer.invoke('truai:write-file', filePath, content),

  /**
   * Get Electron app info (version, platform, project root path).
   * @returns {Promise<object>}
   */
  getAppInfo: () => ipcRenderer.invoke('truai:app-info'),

  /** Platform string for conditional UI */
  platform: process.platform,
});

contextBridge.exposeInMainWorld('trinity', {
  getStatus: () => ipcRenderer.invoke('trinity:get-status'),
  startStack: () => ipcRenderer.invoke('trinity:start-stack'),
  stopStack: () => ipcRenderer.invoke('trinity:stop-stack'),
  getLog: (name) => ipcRenderer.invoke('trinity:get-log', name),
  getConfig: () => ipcRenderer.invoke('trinity:get-config'),
  saveConfig: (content) => ipcRenderer.invoke('trinity:save-config', content),
  getConfigPath: () => ipcRenderer.invoke('trinity:get-config-path'),
  getTruAiUrl: () => ipcRenderer.invoke('trinity:get-truai-url'),
});
