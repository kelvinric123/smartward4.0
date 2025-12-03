const { contextBridge, ipcRenderer } = require('electron');

// Expose protected methods that allow the renderer process to use
// the ipcRenderer without exposing the entire object
contextBridge.exposeInMainWorld('electronAPI', {
  // Docker operations
  checkDocker: () => ipcRenderer.invoke('check-docker'),
  getStatus: () => ipcRenderer.invoke('get-status'),
  getStats: () => ipcRenderer.invoke('get-stats'),
  getLogs: (lines) => ipcRenderer.invoke('get-logs', lines),
  startDocker: () => ipcRenderer.invoke('start-docker'),
  stopDocker: () => ipcRenderer.invoke('stop-docker'),
  restartDocker: () => ipcRenderer.invoke('restart-docker'),
  rebuildDocker: () => ipcRenderer.invoke('rebuild-docker'),
  
  // App operations
  openBrowser: () => ipcRenderer.invoke('open-browser'),
  openLogsFolder: () => ipcRenderer.invoke('open-logs-folder'),
  
  // Settings
  getSettings: () => ipcRenderer.invoke('get-settings'),
  saveSettings: (settings) => ipcRenderer.invoke('save-settings', settings),
  
  // Window controls
  minimize: () => ipcRenderer.send('window-minimize'),
  maximize: () => ipcRenderer.send('window-maximize'),
  close: () => ipcRenderer.send('window-close'),
  
  // Events
  onDockerStatus: (callback) => {
    ipcRenderer.on('docker-status', (event, data) => callback(data));
  }
});

