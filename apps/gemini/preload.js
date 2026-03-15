'use strict';
const { contextBridge, ipcRenderer } = require('electron');
contextBridge.exposeInMainWorld('gemini', {
  getGatewayUrl: () => ipcRenderer.invoke('gemini:gateway-url'),
  setGatewayUrl: (url) => ipcRenderer.invoke('gemini:set-gateway-url', url),
  getToken: () => ipcRenderer.invoke('gemini:get-token'),
  setToken: (token) => ipcRenderer.invoke('gemini:set-token', token),
  query: (opts) => ipcRenderer.invoke('gemini:query', opts),
  upsert: (opts) => ipcRenderer.invoke('gemini:upsert', opts),
  checkWg: () => ipcRenderer.invoke('gemini:check-wg'),
});
