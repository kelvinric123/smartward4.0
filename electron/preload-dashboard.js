const { contextBridge, ipcRenderer } = require('electron');

// Expose fullscreen control to the dashboard page
contextBridge.exposeInMainWorld('smartwardDashboard', {
  toggleFullscreen: () => ipcRenderer.invoke('toggle-dashboard-fullscreen'),
  close: () => ipcRenderer.invoke('close-dashboard'),
  isElectron: true
});

// Also inject a script to handle fullscreen button in the ward dashboard
window.addEventListener('DOMContentLoaded', () => {
  // Add keyboard shortcuts info
  const style = document.createElement('style');
  style.textContent = `
    .electron-fullscreen-hint {
      position: fixed;
      bottom: 20px;
      right: 20px;
      background: rgba(0, 0, 0, 0.8);
      color: white;
      padding: 10px 16px;
      border-radius: 8px;
      font-size: 12px;
      z-index: 9999;
      opacity: 0;
      transition: opacity 0.3s ease;
      pointer-events: none;
    }
    .electron-fullscreen-hint.visible {
      opacity: 1;
    }
  `;
  document.head.appendChild(style);

  // Show hint briefly on load
  const hint = document.createElement('div');
  hint.className = 'electron-fullscreen-hint';
  hint.innerHTML = 'Press <strong>F11</strong> to toggle fullscreen • <strong>Esc</strong> to exit';
  document.body.appendChild(hint);
  
  setTimeout(() => hint.classList.add('visible'), 500);
  setTimeout(() => hint.classList.remove('visible'), 4000);
});































