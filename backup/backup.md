# Smartward Automated Backup System

This folder contains the complete automated backup system for your Smartward Docker deployment. It handles logical database dumps, volume archiving, retention management, and capacity planning.

## Included Files

*   `backup.conf`: The central configuration file. Edit this to change your database password (pulled dynamically if `docker.env` is linked), retention policy (default 7 days), or backup run time (default 2:00 AM).
*   `verify_backup.sh`: Run this to instantly calculate your database and volume sizes, and mathematically project if your hard drive has enough space to handle your retention policy safely.
*   `backup.sh`: The worker script. It executes the `mysqldump`, runs `tar` on the Docker volumes, and cleans up old files. **You do not need to run this manually**, but you can if you want an immediate backup.
*   `backup_deploy.sh`: The installer script. It ensures the worker script is executable, creates the target `/var/backups/smartward` directory, and automatically registers the `backup.sh` script into the server's `cron` schedule.

## How to Deploy

To activate the automated nightly backups, simply run the deploy script on your Linux server:

```bash
cd ~/Desktop/Qmed/smartward4.0/backup
sudo chmod +x verify_backup.sh backup_deploy.sh backup.sh
sudo ./verify_backup.sh
sudo ./backup_deploy.sh
```

Once deployed, backups will run automatically every night at the time specified in `backup.conf` (default is 2:00 AM). 
Logs for the automated runs are saved to: `/var/log/smartward_backup.log`.

## How to Restore Data

If you ever need to restore from a backup:

**1. Restore the Database:**
```bash
# Unzip the SQL file
gunzip /var/backups/smartward/db_YYYY-MM-DD.sql.gz

# Pipe it back into the running container
cat /var/backups/smartward/db_YYYY-MM-DD.sql | docker exec -i smartward4 mysql -u root -psmartward_secret smartward
```

**2. Restore Volumes (e.g., Storage):**
```bash
# Assuming the container is stopped
sudo tar -xzvf /var/backups/smartward/storage_YYYY-MM-DD.tar.gz -C /var/lib/docker/volumes/docker_swoole_smartward_storage/_data
```

## Security Best Practices

> [!WARNING]
> While this setup provides local redundancy, true disaster recovery requires an off-site backup.

It is highly recommended to set up an external sync (e.g., using `aws s3 sync` or `rsync` to a NAS) to copy the contents of `/var/backups/smartward` to an off-site location every day to fulfill the **3-2-1 Backup Strategy** (3 copies, 2 media types, 1 off-site).
