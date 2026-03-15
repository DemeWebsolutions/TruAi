'use strict';
const { contextBridge, ipcRenderer } = require('electron');
contextBridge.exposeInMainWorld('phantom', {
  getGatewayUrl: () => ipcRenderer.invoke('phantom:gateway-url'),
  setGatewayUrl: (url) => ipcRenderer.invoke('phantom:set-gateway-url', url),
  getToken: () => ipcRenderer.invoke('phantom:get-token'),
  setToken: (token) => ipcRenderer.invoke('phantom:set-token', token),
  query: (opts) => ipcRenderer.invoke('phantom:query', opts),
  upsert: (opts) => ipcRenderer.invoke('phantom:upsert', opts),
});
