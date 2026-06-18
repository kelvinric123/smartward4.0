#!/bin/bash
# =============================================================================
# Smartward Daily Backup Script
# =============================================================================

# Get the absolute path to the directory containing this script
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
CONF_FILE="$SCRIPT_DIR/backup.conf"

# Load Configuration
if [ -f "$CONF_FILE" ]; then
    source "$CONF_FILE"
else
    echo "ERROR: Configuration file $CONF_FILE not found!"
    exit 1
fi

DATE=$(date +%Y-%m-%d_%H-%M-%S)

# Extract database credentials dynamically from docker.env
if [ -f "$SCRIPT_DIR/$DOCKER_ENV_FILE" ]; then
    DB_PASS=$(grep "^DB_PASSWORD=" "$SCRIPT_DIR/$DOCKER_ENV_FILE" | cut -d '=' -f2)
    DB_NAME=$(grep "^DB_DATABASE=" "$SCRIPT_DIR/$DOCKER_ENV_FILE" | cut -d '=' -f2)
else
    echo "ERROR: docker.env file not found at $SCRIPT_DIR/$DOCKER_ENV_FILE"
    exit 1
fi

# Ensure backup directory exists
mkdir -p "$BACKUP_DIR"

echo "Starting Smartward Backup at $DATE"

# 1. Backup MySQL Database (Logical Dump)
echo "Dumping MySQL database..."
docker exec $DB_CONTAINER /usr/bin/mysqldump -u $DB_USER -p"$DB_PASS" $DB_NAME > "$BACKUP_DIR/db_$DATE.sql" 2>/dev/null

if [ $? -eq 0 ]; then
    echo "Database dump successful. Compressing..."
    gzip "$BACKUP_DIR/db_$DATE.sql"
else
    echo "ERROR: Database dump failed! (Check if container '$DB_CONTAINER' is running and password is correct)"
    rm -f "$BACKUP_DIR/db_$DATE.sql"
fi

# 2. Backup Persistent Docker Volumes
echo "Backing up Docker Volumes..."
# Standard volume paths
tar -czvf "$BACKUP_DIR/storage_$DATE.tar.gz" -C /var/lib/docker/volumes/docker_swoole_smartward_storage/_data . 2>/dev/null
tar -czvf "$BACKUP_DIR/ecg_store_$DATE.tar.gz" -C /var/lib/docker/volumes/docker_swoole_ecg_store/_data . 2>/dev/null
tar -czvf "$BACKUP_DIR/ecg_data_$DATE.tar.gz" -C /var/lib/docker/volumes/docker_swoole_ecg_data/_data . 2>/dev/null

# 3. Cleanup Old Backups
echo "Cleaning up backups older than $RETENTION_DAYS days..."
find "$BACKUP_DIR" -type f -name "*.gz" -mtime +$RETENTION_DAYS -exec rm {} \;

echo "Backup completed successfully!"
