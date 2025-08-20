#!/bin/bash
# Production Backup Script for Newb Vote 2025s
# Add to crontab: 0 2 * * * /path/to/backup-production.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/newbvote2025s"
DB_NAME=$(grep "^DB_NAME=" .env | cut -d'=' -f2)
DB_USER=$(grep "^DB_USER=" .env | cut -d'=' -f2)
DB_PASS=$(grep "^DB_PASS=" .env | cut -d'=' -f2)

# Create backup directory
mkdir -p $BACKUP_DIR

echo "$(date): Starting backup..."

# Database backup
if [ ! -z "$DB_NAME" ] && [ ! -z "$DB_USER" ]; then
    mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/database_$DATE.sql
    echo "$(date): Database backup completed"
fi

# Files backup
tar -czf $BACKUP_DIR/files_$DATE.tar.gz \
    uploads/ storage/ logs/ .env config/ \
    --exclude="*.log" --exclude="node_modules" --exclude=".git"
echo "$(date): Files backup completed"

# Keep only last 7 days of backups
find $BACKUP_DIR -name "database_*.sql" -mtime +7 -delete
find $BACKUP_DIR -name "files_*.tar.gz" -mtime +7 -delete

echo "$(date): Backup completed successfully"
