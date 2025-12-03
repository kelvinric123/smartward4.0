const { app, BrowserWindow, ipcMain, shell, Tray, Menu, nativeImage, dialog } = require('electron');
const { spawn, exec } = require('child_process');
const path = require('path');
const fs = require('fs');

// Single instance lock
const gotTheLock = app.requestSingleInstanceLock();
if (!gotTheLock) {
  app.quit();
}

let mainWindow = null;
let dashboardWindow = null;
let tray = null;
let dockerProcess = null;
let isQuitting = false;

// Docker compose path (relative to the Laravel project)
const DOCKER_COMPOSE_PATH = path.join(__dirname, '..', 'docker_swoole');
const CONTAINER_NAME = 'smartward-app';

// Store for settings
let Store;
let store;

async function initStore() {
  try {
    Store = (await import('electron-store')).default;
    store = new Store({
      defaults: {
        autoStart: false,
        minimizeToTray: true,
        appPort: 80,
        startMinimized: false
      }
    });
  } catch (e) {
    console.log('Store initialization failed, using defaults');
    store = {
      get: (key, def) => def,
      set: () => {}
    };
  }
}

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 900,
    height: 680,
    minWidth: 800,
    minHeight: 600,
    frame: false,
    transparent: false,
    backgroundColor: '#0a0a0f',
    icon: path.join(__dirname, 'assets', 'icon.ico'),
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true,
      preload: path.join(__dirname, 'preload.js')
    },
    show: false
  });

  mainWindow.loadFile('index.html');

  mainWindow.once('ready-to-show', () => {
    if (!store.get('startMinimized', false)) {
      mainWindow.show();
    }
  });

  mainWindow.on('close', (event) => {
    if (!isQuitting && store.get('minimizeToTray', true)) {
      event.preventDefault();
      mainWindow.hide();
      return false;
    }
  });

  mainWindow.on('closed', () => {
    mainWindow = null;
  });
}

function createTray() {
  const iconPath = path.join(__dirname, 'assets', 'icon.ico');
  let trayIcon;
  
  if (fs.existsSync(iconPath)) {
    trayIcon = nativeImage.createFromPath(iconPath);
  } else {
    // Create a simple colored icon if no icon file exists
    trayIcon = nativeImage.createEmpty();
  }

  tray = new Tray(trayIcon);
  
  const contextMenu = Menu.buildFromTemplate([
    { 
      label: 'Open SmartWard', 
      click: () => {
        if (mainWindow) {
          mainWindow.show();
          mainWindow.focus();
        }
      }
    },
    { type: 'separator' },
    { 
      label: 'Open in Browser', 
      click: () => {
        const port = store.get('appPort', 80);
        shell.openExternal(`http://localhost:${port}`);
      }
    },
    { 
      label: 'Ward Dashboard (Fullscreen)', 
      click: () => openWardDashboard()
    },
    { type: 'separator' },
    { 
      label: 'Start Container', 
      click: () => startDocker()
    },
    { 
      label: 'Stop Container', 
      click: () => stopDocker()
    },
    { type: 'separator' },
    { 
      label: 'Quit', 
      click: () => {
        isQuitting = true;
        app.quit();
      }
    }
  ]);

  tray.setToolTip('SmartWard 4.0');
  tray.setContextMenu(contextMenu);
  
  tray.on('double-click', () => {
    if (mainWindow) {
      mainWindow.show();
      mainWindow.focus();
    }
  });
}

// Execute command and return promise
function execCommand(command, cwd = DOCKER_COMPOSE_PATH) {
  return new Promise((resolve, reject) => {
    exec(command, { cwd }, (error, stdout, stderr) => {
      if (error) {
        reject({ error, stderr });
      } else {
        resolve(stdout.trim());
      }
    });
  });
}

// Check if Docker is installed and running
async function checkDocker() {
  try {
    await execCommand('docker info', process.cwd());
    return { installed: true, running: true };
  } catch (e) {
    try {
      await execCommand('docker --version', process.cwd());
      return { installed: true, running: false };
    } catch (e2) {
      return { installed: false, running: false };
    }
  }
}

// Get container status
async function getContainerStatus() {
  try {
    const result = await execCommand(`docker ps -a --filter "name=${CONTAINER_NAME}" --format "{{.Status}}"`, process.cwd());
    if (!result) {
      return { status: 'not_found', message: 'Container not created' };
    }
    
    if (result.toLowerCase().includes('up')) {
      // Get health status
      const health = await execCommand(`docker inspect --format="{{.State.Health.Status}}" ${CONTAINER_NAME}`, process.cwd()).catch(() => 'unknown');
      return { 
        status: 'running', 
        message: result,
        health: health || 'unknown'
      };
    } else if (result.toLowerCase().includes('exited')) {
      return { status: 'stopped', message: result };
    } else {
      return { status: 'starting', message: result };
    }
  } catch (e) {
    return { status: 'not_found', message: 'Container not found' };
  }
}

// Get container stats
async function getContainerStats() {
  try {
    const stats = await execCommand(
      `docker stats ${CONTAINER_NAME} --no-stream --format "{{.CPUPerc}}|{{.MemUsage}}|{{.NetIO}}"`,
      process.cwd()
    );
    const [cpu, memory, network] = stats.split('|');
    return { cpu, memory, network };
  } catch (e) {
    return { cpu: '-', memory: '-', network: '-' };
  }
}

// Get container logs
async function getContainerLogs(lines = 100) {
  try {
    const logs = await execCommand(`docker logs ${CONTAINER_NAME} --tail ${lines}`, process.cwd());
    return logs;
  } catch (e) {
    return 'No logs available';
  }
}

// Start Docker container
async function startDocker() {
  try {
    sendToRenderer('docker-status', { status: 'starting', message: 'Starting container...' });
    
    // Check if container exists
    const exists = await execCommand(`docker ps -a --filter "name=${CONTAINER_NAME}" --format "{{.Names}}"`, process.cwd());
    
    if (exists) {
      // Container exists, just start it
      await execCommand(`docker start ${CONTAINER_NAME}`, process.cwd());
    } else {
      // Build and start with docker-compose
      await execCommand('docker-compose up -d --build', DOCKER_COMPOSE_PATH);
    }
    
    sendToRenderer('docker-status', { status: 'running', message: 'Container started successfully' });
    return { success: true };
  } catch (e) {
    sendToRenderer('docker-status', { status: 'error', message: e.stderr || e.error?.message || 'Failed to start' });
    return { success: false, error: e.stderr || e.error?.message };
  }
}

// Stop Docker container
async function stopDocker() {
  try {
    sendToRenderer('docker-status', { status: 'stopping', message: 'Stopping container...' });
    await execCommand(`docker stop ${CONTAINER_NAME}`, process.cwd());
    sendToRenderer('docker-status', { status: 'stopped', message: 'Container stopped' });
    return { success: true };
  } catch (e) {
    return { success: false, error: e.stderr || e.error?.message };
  }
}

// Restart Docker container
async function restartDocker() {
  try {
    sendToRenderer('docker-status', { status: 'restarting', message: 'Restarting container...' });
    await execCommand(`docker restart ${CONTAINER_NAME}`, process.cwd());
    sendToRenderer('docker-status', { status: 'running', message: 'Container restarted' });
    return { success: true };
  } catch (e) {
    return { success: false, error: e.stderr || e.error?.message };
  }
}

// Rebuild container
async function rebuildDocker() {
  try {
    sendToRenderer('docker-status', { status: 'building', message: 'Rebuilding container...' });
    await execCommand('docker-compose down', DOCKER_COMPOSE_PATH);
    await execCommand('docker-compose up -d --build', DOCKER_COMPOSE_PATH);
    sendToRenderer('docker-status', { status: 'running', message: 'Container rebuilt successfully' });
    return { success: true };
  } catch (e) {
    return { success: false, error: e.stderr || e.error?.message };
  }
}

// Open Ward Dashboard in fullscreen mode
function openWardDashboard() {
  // Close existing dashboard window if open
  if (dashboardWindow && !dashboardWindow.isDestroyed()) {
    dashboardWindow.focus();
    return { success: true };
  }

  const port = store.get('appPort', 80);
  const dashboardUrl = `http://localhost:${port}/ward-dashboard`;

  dashboardWindow = new BrowserWindow({
    width: 1920,
    height: 1080,
    fullscreen: true,
    kiosk: false, // Set to false so user can exit with F11 or Escape
    autoHideMenuBar: true,
    frame: true, // Keep frame for exit controls
    backgroundColor: '#0a0a0f',
    icon: path.join(__dirname, 'assets', 'icon.ico'),
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true,
      preload: path.join(__dirname, 'preload-dashboard.js')
    }
  });

  dashboardWindow.loadURL(dashboardUrl);

  // Enable F11 to toggle fullscreen and Escape to exit fullscreen
  dashboardWindow.webContents.on('before-input-event', (event, input) => {
    if (input.key === 'F11') {
      dashboardWindow.setFullScreen(!dashboardWindow.isFullScreen());
    }
    if (input.key === 'Escape' && dashboardWindow.isFullScreen()) {
      dashboardWindow.setFullScreen(false);
    }
  });

  dashboardWindow.on('closed', () => {
    dashboardWindow = null;
  });

  return { success: true };
}

// Toggle fullscreen for dashboard window
function toggleDashboardFullscreen() {
  if (dashboardWindow && !dashboardWindow.isDestroyed()) {
    dashboardWindow.setFullScreen(!dashboardWindow.isFullScreen());
    return { success: true, fullscreen: dashboardWindow.isFullScreen() };
  }
  return { success: false };
}

// Close dashboard window
function closeDashboard() {
  if (dashboardWindow && !dashboardWindow.isDestroyed()) {
    dashboardWindow.close();
    return { success: true };
  }
  return { success: false };
}

// Send message to renderer
function sendToRenderer(channel, data) {
  if (mainWindow && mainWindow.webContents) {
    mainWindow.webContents.send(channel, data);
  }
}

// IPC Handlers
function setupIPC() {
  ipcMain.handle('check-docker', async () => {
    return await checkDocker();
  });

  ipcMain.handle('get-status', async () => {
    return await getContainerStatus();
  });

  ipcMain.handle('get-stats', async () => {
    return await getContainerStats();
  });

  ipcMain.handle('get-logs', async (event, lines) => {
    return await getContainerLogs(lines);
  });

  ipcMain.handle('start-docker', async () => {
    return await startDocker();
  });

  ipcMain.handle('stop-docker', async () => {
    return await stopDocker();
  });

  ipcMain.handle('restart-docker', async () => {
    return await restartDocker();
  });

  ipcMain.handle('rebuild-docker', async () => {
    return await rebuildDocker();
  });

  ipcMain.handle('open-browser', async () => {
    const port = store.get('appPort', 80);
    shell.openExternal(`http://localhost:${port}`);
    return { success: true };
  });

  ipcMain.handle('open-ward-dashboard', async () => {
    return openWardDashboard();
  });

  ipcMain.handle('toggle-dashboard-fullscreen', async () => {
    return toggleDashboardFullscreen();
  });

  ipcMain.handle('close-dashboard', async () => {
    return closeDashboard();
  });

  ipcMain.handle('open-logs-folder', async () => {
    const logsPath = path.join(__dirname, '..', 'storage', 'logs');
    shell.openPath(logsPath);
    return { success: true };
  });

  ipcMain.handle('get-settings', async () => {
    return {
      autoStart: store.get('autoStart', false),
      minimizeToTray: store.get('minimizeToTray', true),
      appPort: store.get('appPort', 80),
      startMinimized: store.get('startMinimized', false)
    };
  });

  ipcMain.handle('save-settings', async (event, settings) => {
    Object.keys(settings).forEach(key => {
      store.set(key, settings[key]);
    });
    
    // Update auto-launch
    app.setLoginItemSettings({
      openAtLogin: settings.autoStart
    });
    
    return { success: true };
  });

  // Window controls
  ipcMain.on('window-minimize', () => {
    if (mainWindow) mainWindow.minimize();
  });

  ipcMain.on('window-maximize', () => {
    if (mainWindow) {
      if (mainWindow.isMaximized()) {
        mainWindow.unmaximize();
      } else {
        mainWindow.maximize();
      }
    }
  });

  ipcMain.on('window-close', () => {
    if (mainWindow) mainWindow.close();
  });
}

// App lifecycle
app.whenReady().then(async () => {
  await initStore();
  createWindow();
  createTray();
  setupIPC();

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createWindow();
    }
  });
});

app.on('second-instance', () => {
  if (mainWindow) {
    if (mainWindow.isMinimized()) mainWindow.restore();
    mainWindow.show();
    mainWindow.focus();
  }
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    if (!store.get('minimizeToTray', true)) {
      app.quit();
    }
  }
});

app.on('before-quit', () => {
  isQuitting = true;
});

