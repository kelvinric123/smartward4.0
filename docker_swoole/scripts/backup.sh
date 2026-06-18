#!/bin/bash
# =============================================================================
# Smartward Daily Backup Script
# =============================================================================

# Set variables
BACKUP_DIR="/var/backups/smartward"
DATE=$(date +%Y-%m-%d_%H-%M-%S)
DB_CONTAINER="smartward4"
DB_USER="root"
# IMPORTANT: Replace with your actual DB password from production docker.env
DB_PASS="smartward_secret"
DB_NAME="smartward"
RETENTION_DAYS=7

# Ensure backup directory exists
mkdir -p "$BACKUP_DIR"

echo "Starting Smartward Backup at $DATE"

# 1. Backup MySQL Database (Logical Dump)
echo "Dumping MySQL database..."
docker exec $DB_CONTAINER /usr/bin/mysqldump -u $DB_USER -p"$DB_PASS" $DB_NAME > "$BACKUP_DIR/db_$DATE.sql"

if [ $? -eq 0 ]; then
    echo "Database dump successful. Compressing..."
    gzip "$BACKUP_DIR/db_$DATE.sql"
else
    echo "ERROR: Database dump failed!"
    rm -f "$BACKUP_DIR/db_$DATE.sql"
fi

# 2. Backup Persistent Docker Volumes
echo "Backing up Docker Volumes..."
# You can find exact volume paths using: docker volume inspect <volume_name>
# Below are standard paths assuming default Docker setup on Linux

# Backup Storage Volume
tar -czvf "$BACKUP_DIR/storage_$DATE.tar.gz" -C /var/lib/docker/volumes/docker_swoole_smartward_storage/_data . 2>/dev/null

# Backup ECG Store
tar -czvf "$BACKUP_DIR/ecg_store_$DATE.tar.gz" -C /var/lib/docker/volumes/docker_swoole_ecg_store/_data . 2>/dev/null

# Backup ECG Data
tar -czvf "$BACKUP_DIR/ecg_data_$DATE.tar.gz" -C /var/lib/docker/volumes/docker_swoole_ecg_data/_data . 2>/dev/null

# 3. Cleanup Old Backups
echo "Cleaning up backups older than $RETENTION_DAYS days..."
find "$BACKUP_DIR" -type f -name "*.gz" -mtime +$RETENTION_DAYS -exec rm {} \;

echo "Backup completed successfully!"
