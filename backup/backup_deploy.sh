#!/bin/bash
# =============================================================================
# Smartward Backup Deploy Script
# Run this script with sudo to set up the automated backups
# =============================================================================

# Ensure we are running as root to edit crontab
if [ "$EUID" -ne 0 ]; then
  echo "Please run this script with sudo:"
  echo "sudo ./backup_deploy.sh"
  exit 1
fi

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
CONF_FILE="$SCRIPT_DIR/backup.conf"
WORKER_SCRIPT="$SCRIPT_DIR/backup.sh"

echo "========================================"
echo " Deploying Smartward Backup System"
echo "========================================"

# Make worker script executable
chmod +x "$WORKER_SCRIPT"

# Load Configuration
if [ -f "$CONF_FILE" ]; then
    source "$CONF_FILE"
    echo "Configuration loaded from $CONF_FILE"
else
    echo "ERROR: Configuration file $CONF_FILE not found!"
    exit 1
fi

# Ensure backup directory exists
mkdir -p "$BACKUP_DIR"
echo "Backup destination ready at: $BACKUP_DIR"

# Install Cron Job
CRON_JOB="$CRON_SCHEDULE $WORKER_SCRIPT >> /var/log/smartward_backup.log 2>&1"

# Check if job already exists
(crontab -l 2>/dev/null | grep -q "$WORKER_SCRIPT")
if [ $? -eq 0 ]; then
    echo "Cron job already exists for this script. Updating..."
    # Remove old job and add new one
    (crontab -l 2>/dev/null | grep -v "$WORKER_SCRIPT"; echo "$CRON_JOB") | crontab -
else
    echo "Installing new cron job..."
    (crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -
fi

echo "========================================"
echo "Deployment Successful!"
echo "Backups will run on schedule: $CRON_SCHEDULE"
echo "Logs will be written to: /var/log/smartward_backup.log"
echo "To test a backup manually, run: sudo $WORKER_SCRIPT"
echo "========================================"
