#!/bin/bash
# =============================================================================
# Smartward Backup Verification & Capacity Planning
# =============================================================================

if [ "$EUID" -ne 0 ]; then
  echo "Please run this script as root (sudo ./verify_backup.sh)"
  exit 1
fi

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
CONF_FILE="$SCRIPT_DIR/backup.conf"

if [ -f "$CONF_FILE" ]; then
    source "$CONF_FILE"
else
    echo "ERROR: Configuration file $CONF_FILE not found!"
    exit 1
fi

# Load DB credentials from docker.env if configured
if [ -n "$DOCKER_ENV_FILE" ] && [ -f "$SCRIPT_DIR/$DOCKER_ENV_FILE" ]; then
    DB_PASS=$(grep "^DB_PASSWORD=" "$SCRIPT_DIR/$DOCKER_ENV_FILE" | cut -d '=' -f2)
    DB_NAME=$(grep "^DB_DATABASE=" "$SCRIPT_DIR/$DOCKER_ENV_FILE" | cut -d '=' -f2)
fi

echo "=========================================================="
echo " Smartward Backup Capacity Planning"
echo "=========================================================="

# Ensure backup directory exists to check disk space
mkdir -p "$BACKUP_DIR"

# 1. Calculate DB Size
echo "[1] Calculating MySQL Database Size..."
DB_SIZE_MB=$(docker exec $DB_CONTAINER mysql -u "$DB_USER" -p"$DB_PASS" -e "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.TABLES WHERE table_schema='$DB_NAME';" -s -N 2>/dev/null)

if [ -z "$DB_SIZE_MB" ]; then
    echo "  WARNING: Could not connect to MySQL. Is the container running?"
    DB_SIZE_MB=0
else
    echo "  MySQL Database '$DB_NAME': ${DB_SIZE_MB} MB"
fi

# 2. Calculate Docker Volumes Size
echo "[2] Calculating Persistent Volumes Size..."

get_volume_size() {
    local path=$1
    if [ -d "$path" ]; then
        du -sm "$path" | cut -f1
    else
        echo 0
    fi
}

STORAGE_SIZE_MB=$(get_volume_size "/var/lib/docker/volumes/docker_swoole_smartward_storage/_data")
ECG_STORE_SIZE_MB=$(get_volume_size "/var/lib/docker/volumes/docker_swoole_ecg_store/_data")
ECG_DATA_SIZE_MB=$(get_volume_size "/var/lib/docker/volumes/docker_swoole_ecg_data/_data")

echo "  Storage Volume: ${STORAGE_SIZE_MB} MB"
echo "  ECG Store Volume: ${ECG_STORE_SIZE_MB} MB"
echo "  ECG Data Volume: ${ECG_DATA_SIZE_MB} MB"

TOTAL_RAW_SIZE_MB=$(echo "$DB_SIZE_MB + $STORAGE_SIZE_MB + $ECG_STORE_SIZE_MB + $ECG_DATA_SIZE_MB" | bc)
echo "----------------------------------------------------------"
echo "  Total Raw Data Size: ${TOTAL_RAW_SIZE_MB} MB"

# 3. Estimate Backup Size (assuming ~50% compression via gzip/tar.gz)
COMPRESSION_RATIO="0.5"
ESTIMATED_DAILY_BACKUP_MB=$(echo "$TOTAL_RAW_SIZE_MB * $COMPRESSION_RATIO" | bc | cut -d. -f1)
ESTIMATED_RETENTION_TOTAL_MB=$(echo "$ESTIMATED_DAILY_BACKUP_MB * $RETENTION_DAYS" | bc)

echo ""
echo "[3] Backup Projections (Retention: $RETENTION_DAYS days)"
echo "  Estimated compressed size per daily backup: ~${ESTIMATED_DAILY_BACKUP_MB} MB"
echo "  Estimated total backup size after $RETENTION_DAYS days: ~${ESTIMATED_RETENTION_TOTAL_MB} MB"

# 4. Check Disk Space
echo ""
echo "[4] Disk Space Analysis"

# Get disk usage for the partition where BACKUP_DIR resides
DF_OUTPUT=$(df -m "$BACKUP_DIR" | tail -1)
TOTAL_DISK_MB=$(echo "$DF_OUTPUT" | awk '{print $2}')
USED_DISK_MB=$(echo "$DF_OUTPUT" | awk '{print $3}')
AVAIL_DISK_MB=$(echo "$DF_OUTPUT" | awk '{print $4}')
CURRENT_USE_PCT=$(echo "$DF_OUTPUT" | awk '{print $5}')

# Calculate future used space
FUTURE_USED_MB=$(echo "$USED_DISK_MB + $ESTIMATED_RETENTION_TOTAL_MB" | bc)
FUTURE_USE_PCT=$(echo "scale=2; ($FUTURE_USED_MB / $TOTAL_DISK_MB) * 100" | bc | cut -d. -f1)

echo "  Target Backup Directory: $BACKUP_DIR"
echo "  Total Disk Size: $TOTAL_DISK_MB MB"
echo "  Currently Used:  $USED_DISK_MB MB ($CURRENT_USE_PCT)"
echo "  Currently Free:  $AVAIL_DISK_MB MB"
echo "----------------------------------------------------------"
echo "  Projected Disk Usage (Current + $RETENTION_DAYS days of backups):"
echo "  Future Used:     $FUTURE_USED_MB MB (~${FUTURE_USE_PCT}%)"

echo ""
echo "=========================================================="
if [ "$FUTURE_USE_PCT" -ge 90 ]; then
    echo " ❌ WARNING: Your disk will be over 90% full after $RETENTION_DAYS days of backups!"
    echo "    Please lower your RETENTION_DAYS in backup.conf or increase disk space."
elif [ "$FUTURE_USE_PCT" -ge 75 ]; then
    echo " ⚠️ CAUTION: Your disk usage will reach ~${FUTURE_USE_PCT}%. Keep an eye on storage."
else
    echo " ✅ SAFE: You have plenty of disk space for this backup policy."
fi
echo "=========================================================="
