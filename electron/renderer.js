// SmartWard 4.0 - Renderer Process
// Handles UI interactions and communicates with main process

class SmartWardApp {
  constructor() {
    this.isRunning = false;
    this.statusInterval = null;
    this.statsInterval = null;
    
    this.init();
  }

  async init() {
    this.bindWindowControls();
    this.bindButtons();
    this.bindSettings();
    
    // Listen for status updates from main process
    window.electronAPI.onDockerStatus((data) => {
      this.updateStatusUI(data);
    });

    // Check Docker availability
    await this.checkDocker();
    
    // Start status polling
    this.startPolling();
  }

  // ===== Window Controls =====
  bindWindowControls() {
    document.getElementById('btn-minimize').addEventListener('click', () => {
      window.electronAPI.minimize();
    });

    document.getElementById('btn-maximize').addEventListener('click', () => {
      window.electronAPI.maximize();
    });

    document.getElementById('btn-close').addEventListener('click', () => {
      window.electronAPI.close();
    });
  }

  // ===== Button Bindings =====
  bindButtons() {
    // Start button
    document.getElementById('btn-start').addEventListener('click', async () => {
      this.setButtonLoading('btn-start', true);
      await window.electronAPI.startDocker();
      this.setButtonLoading('btn-start', false);
      await this.refreshStatus();
      await this.refreshLogs();
    });

    // Stop button
    document.getElementById('btn-stop').addEventListener('click', async () => {
      this.setButtonLoading('btn-stop', true);
      await window.electronAPI.stopDocker();
      this.setButtonLoading('btn-stop', false);
      await this.refreshStatus();
    });

    // Restart button
    document.getElementById('btn-restart').addEventListener('click', async () => {
      this.setButtonLoading('btn-restart', true);
      await window.electronAPI.restartDocker();
      this.setButtonLoading('btn-restart', false);
      await this.refreshStatus();
      await this.refreshLogs();
    });

    // Open App button
    document.getElementById('btn-open').addEventListener('click', async () => {
      await window.electronAPI.openBrowser();
    });

    // Rebuild button
    document.getElementById('btn-rebuild').addEventListener('click', async () => {
      if (confirm('This will rebuild the container from scratch. Continue?')) {
        this.setButtonLoading('btn-rebuild', true);
        await window.electronAPI.rebuildDocker();
        this.setButtonLoading('btn-rebuild', false);
        await this.refreshStatus();
        await this.refreshLogs();
      }
    });

    // Logs folder button
    document.getElementById('btn-logs-folder').addEventListener('click', async () => {
      await window.electronAPI.openLogsFolder();
    });

    // Refresh logs button
    document.getElementById('btn-refresh-logs').addEventListener('click', async () => {
      await this.refreshLogs();
    });

    // Clear logs display
    document.getElementById('btn-clear-logs').addEventListener('click', () => {
      document.getElementById('logs-content').textContent = '';
    });

    // Retry Docker button
    document.getElementById('btn-retry-docker').addEventListener('click', async () => {
      await this.checkDocker();
    });
  }

  // ===== Settings =====
  bindSettings() {
    const modal = document.getElementById('settings-modal');
    
    // Open settings
    document.getElementById('btn-settings').addEventListener('click', async () => {
      const settings = await window.electronAPI.getSettings();
      document.getElementById('setting-autostart').checked = settings.autoStart;
      document.getElementById('setting-tray').checked = settings.minimizeToTray;
      document.getElementById('setting-minimized').checked = settings.startMinimized;
      modal.classList.add('visible');
    });

    // Close settings
    document.getElementById('btn-close-settings').addEventListener('click', () => {
      modal.classList.remove('visible');
    });

    document.getElementById('btn-cancel-settings').addEventListener('click', () => {
      modal.classList.remove('visible');
    });

    // Save settings
    document.getElementById('btn-save-settings').addEventListener('click', async () => {
      const settings = {
        autoStart: document.getElementById('setting-autostart').checked,
        minimizeToTray: document.getElementById('setting-tray').checked,
        startMinimized: document.getElementById('setting-minimized').checked
      };
      await window.electronAPI.saveSettings(settings);
      modal.classList.remove('visible');
    });

    // Close on overlay click
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        modal.classList.remove('visible');
      }
    });
  }

  // ===== Docker Check =====
  async checkDocker() {
    const dockerStatus = await window.electronAPI.checkDocker();
    const warning = document.getElementById('docker-warning');
    const warningText = document.getElementById('docker-warning-text');

    if (!dockerStatus.installed) {
      warningText.textContent = 'Docker is not installed. Please install Docker Desktop from docker.com';
      warning.classList.remove('hidden');
      return false;
    }

    if (!dockerStatus.running) {
      warningText.textContent = 'Docker Desktop is not running. Please start Docker Desktop and try again.';
      warning.classList.remove('hidden');
      return false;
    }

    warning.classList.add('hidden');
    return true;
  }

  // ===== Status Updates =====
  async refreshStatus() {
    const status = await window.electronAPI.getStatus();
    this.updateStatusUI(status);
  }

  updateStatusUI(status) {
    const card = document.getElementById('status-card');
    const dot = document.getElementById('status-dot');
    const text = document.getElementById('status-text');
    const message = document.getElementById('status-message');

    // Remove all status classes
    card.classList.remove('running', 'starting', 'stopped');
    dot.classList.remove('running', 'starting');

    switch (status.status) {
      case 'running':
        this.isRunning = true;
        card.classList.add('running');
        dot.classList.add('running');
        text.textContent = 'Running';
        message.textContent = status.health === 'healthy' ? 'Container is healthy' : status.message;
        break;
      case 'starting':
      case 'building':
      case 'restarting':
        this.isRunning = false;
        card.classList.add('starting');
        dot.classList.add('starting');
        text.textContent = status.status.charAt(0).toUpperCase() + status.status.slice(1) + '...';
        message.textContent = status.message;
        break;
      case 'stopped':
        this.isRunning = false;
        text.textContent = 'Stopped';
        message.textContent = status.message;
        break;
      case 'stopping':
        this.isRunning = false;
        card.classList.add('starting');
        dot.classList.add('starting');
        text.textContent = 'Stopping...';
        message.textContent = status.message;
        break;
      case 'error':
        this.isRunning = false;
        text.textContent = 'Error';
        message.textContent = status.message;
        break;
      default:
        this.isRunning = false;
        text.textContent = 'Not Found';
        message.textContent = 'Container not created yet';
    }

    this.updateButtonStates();
  }

  updateButtonStates() {
    const startBtn = document.getElementById('btn-start');
    const stopBtn = document.getElementById('btn-stop');
    const restartBtn = document.getElementById('btn-restart');
    const openBtn = document.getElementById('btn-open');

    if (this.isRunning) {
      startBtn.disabled = true;
      stopBtn.disabled = false;
      restartBtn.disabled = false;
      openBtn.disabled = false;
    } else {
      startBtn.disabled = false;
      stopBtn.disabled = true;
      restartBtn.disabled = true;
      openBtn.disabled = true;
    }
  }

  // ===== Stats Updates =====
  async refreshStats() {
    if (!this.isRunning) {
      document.getElementById('stat-cpu').textContent = '-';
      document.getElementById('stat-memory').textContent = '-';
      document.getElementById('stat-network').textContent = '-';
      return;
    }

    const stats = await window.electronAPI.getStats();
    document.getElementById('stat-cpu').textContent = stats.cpu || '-';
    document.getElementById('stat-memory').textContent = stats.memory ? stats.memory.split('/')[0].trim() : '-';
    document.getElementById('stat-network').textContent = stats.network ? stats.network.split('/')[0].trim() : '-';
  }

  // ===== Logs =====
  async refreshLogs() {
    if (!this.isRunning) {
      document.getElementById('logs-content').textContent = 'Container is not running';
      return;
    }

    const logs = await window.electronAPI.getLogs(100);
    const logsContent = document.getElementById('logs-content');
    logsContent.textContent = logs || 'No logs available';
    logsContent.scrollTop = logsContent.scrollHeight;
  }

  // ===== Polling =====
  startPolling() {
    // Status polling every 3 seconds
    this.statusInterval = setInterval(async () => {
      if (!document.getElementById('docker-warning').classList.contains('hidden')) {
        await this.checkDocker();
      }
      await this.refreshStatus();
    }, 3000);

    // Stats polling every 5 seconds
    this.statsInterval = setInterval(async () => {
      await this.refreshStats();
    }, 5000);

    // Initial refresh
    this.refreshStatus();
    this.refreshStats();
  }

  stopPolling() {
    if (this.statusInterval) {
      clearInterval(this.statusInterval);
      this.statusInterval = null;
    }
    if (this.statsInterval) {
      clearInterval(this.statsInterval);
      this.statsInterval = null;
    }
  }

  // ===== Helpers =====
  setButtonLoading(buttonId, loading) {
    const button = document.getElementById(buttonId);
    if (loading) {
      button.disabled = true;
      button.classList.add('loading');
      const svg = button.querySelector('svg');
      if (svg) svg.classList.add('spinner');
    } else {
      button.disabled = false;
      button.classList.remove('loading');
      const svg = button.querySelector('svg');
      if (svg) svg.classList.remove('spinner');
    }
  }
}

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  window.smartWardApp = new SmartWardApp();
});

