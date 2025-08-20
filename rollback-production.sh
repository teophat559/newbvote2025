#!/bin/bash
# Production Rollback Script for Newb Vote 2025s

echo "🔄 Production Rollback Script"
echo "============================="

if [ $# -eq 0 ]; then
    echo "Usage: $0 <backup_date>"
    echo "Example: $0 20231225_020000"
    echo ""
    echo "Available backups:"
    ls -1 /backups/newbvote2025s/database_*.sql | grep -o '[0-9]\{8\}_[0-9]\{6\}' | sort -r | head -5
    exit 1
fi

BACKUP_DATE=$1
BACKUP_DIR="/backups/newbvote2025s"

echo "⚠️  WARNING: This will restore your system to backup date: $BACKUP_DATE"
echo "Current data will be backed up before rollback."
read -p "Are you sure? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    echo "Rollback cancelled."
    exit 1
fi

echo "Creating emergency backup of current state..."
./backup-production.sh

echo "Stopping services..."
systemctl stop ratchet-newbvote2025s.service || true
systemctl stop apache2 || systemctl stop httpd || true

echo "Restoring database..."
DB_NAME=$(grep "^DB_NAME=" .env | cut -d'=' -f2)
DB_USER=$(grep "^DB_USER=" .env | cut -d'=' -f2)
DB_PASS=$(grep "^DB_PASS=" .env | cut -d'=' -f2)

if [ -f "$BACKUP_DIR/database_$BACKUP_DATE.sql" ]; then
    mysql -u $DB_USER -p$DB_PASS $DB_NAME < $BACKUP_DIR/database_$BACKUP_DATE.sql
    echo "Database restored"
else
    echo "Database backup not found: $BACKUP_DIR/database_$BACKUP_DATE.sql"
    exit 1
fi

echo "Restoring files..."
if [ -f "$BACKUP_DIR/files_$BACKUP_DATE.tar.gz" ]; then
    tar -xzf $BACKUP_DIR/files_$BACKUP_DATE.tar.gz
    echo "Files restored"
else
    echo "Files backup not found: $BACKUP_DIR/files_$BACKUP_DATE.tar.gz"
    exit 1
fi

echo "Starting services..."
systemctl start apache2 || systemctl start httpd
systemctl start ratchet-newbvote2025s.service || true

echo "✅ Rollback completed successfully"
echo "Please verify your application is working correctly"
