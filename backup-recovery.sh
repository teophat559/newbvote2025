#!/bin/bash

# Backup and Recovery Script for Newb Vote 2025s
# Handles complete backup and rollback procedures for production deployment

set -e

# Configuration
PROJECT_NAME="newbvote2025s"
DOMAIN="newbvote2025s.online"
DB_NAME="newb_vote2025s"
DB_USER="newb_vote2025s_user"
WEB_ROOT="/home/${DOMAIN}/public_html"
BACKUP_DIR="/home/${DOMAIN}/backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() { echo -e "${GREEN}✅${NC} $1"; }
print_warning() { echo -e "${YELLOW}⚠️${NC}  $1"; }
print_error() { echo -e "${RED}❌${NC} $1"; }
print_info() { echo -e "${BLUE}ℹ️${NC}  $1"; }

# Function to create complete backup
create_backup() {
    echo -e "\n${BLUE}📦 CREATING COMPLETE BACKUP${NC}"
    echo "=============================="
    
    # Create backup directories
    BACKUP_PATH="${BACKUP_DIR}/${TIMESTAMP}"
    mkdir -p "$BACKUP_PATH"
    
    # Backup database
    print_info "Backing up database..."
    read -s -p "Enter database password: " DB_PASS
    echo
    
    mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_PATH/database_${TIMESTAMP}.sql"
    if [[ $? -eq 0 ]]; then
        print_status "Database backup completed: $BACKUP_PATH/database_${TIMESTAMP}.sql"
    else
        print_error "Database backup failed"
        exit 1
    fi
    
    # Backup application files
    print_info "Backing up application files..."
    tar -czf "$BACKUP_PATH/application_${TIMESTAMP}.tar.gz" -C "$WEB_ROOT" .
    print_status "Application backup completed: $BACKUP_PATH/application_${TIMESTAMP}.tar.gz"
    
    # Backup configuration
    print_info "Backing up Apache configuration..."
    if [[ -f "/etc/apache2/sites-available/${PROJECT_NAME}-80.conf" ]]; then
        cp "/etc/apache2/sites-available/${PROJECT_NAME}-80.conf" "$BACKUP_PATH/"
    fi
    if [[ -f "/etc/apache2/sites-available/${PROJECT_NAME}-443.conf" ]]; then
        cp "/etc/apache2/sites-available/${PROJECT_NAME}-443.conf" "$BACKUP_PATH/"
    fi
    
    # Create backup manifest
    cat > "$BACKUP_PATH/backup_manifest.txt" << EOF
Backup Created: $(date)
Project: $PROJECT_NAME
Domain: $DOMAIN
Database: $DB_NAME
Web Root: $WEB_ROOT

Files:
- database_${TIMESTAMP}.sql (Database dump)
- application_${TIMESTAMP}.tar.gz (Application files)
- ${PROJECT_NAME}-80.conf (Apache HTTP config)
- ${PROJECT_NAME}-443.conf (Apache HTTPS config)

Restore command:
./backup-recovery.sh restore $TIMESTAMP
EOF
    
    print_status "Backup manifest created: $BACKUP_PATH/backup_manifest.txt"
    
    # Set proper permissions
    chmod -R 600 "$BACKUP_PATH"
    
    echo -e "\n${GREEN}🎉 BACKUP COMPLETED SUCCESSFULLY!${NC}"
    echo "Backup location: $BACKUP_PATH"
    echo "Total size: $(du -sh "$BACKUP_PATH" | cut -f1)"
}

# Function to restore from backup
restore_backup() {
    local backup_timestamp="$1"
    
    if [[ -z "$backup_timestamp" ]]; then
        print_error "Please specify backup timestamp"
        list_backups
        exit 1
    fi
    
    RESTORE_PATH="${BACKUP_DIR}/${backup_timestamp}"
    
    if [[ ! -d "$RESTORE_PATH" ]]; then
        print_error "Backup not found: $RESTORE_PATH"
        list_backups
        exit 1
    fi
    
    echo -e "\n${YELLOW}🔄 RESTORING FROM BACKUP${NC}"
    echo "================================"
    print_info "Restore path: $RESTORE_PATH"
    
    # Confirmation
    read -p "This will overwrite current installation. Continue? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_info "Restore cancelled"
        exit 0
    fi
    
    # Create current backup before restore
    print_info "Creating pre-restore backup..."
    create_backup
    
    # Restore database
    print_info "Restoring database..."
    read -s -p "Enter database password: " DB_PASS
    echo
    
    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$RESTORE_PATH/database_${backup_timestamp}.sql"
    if [[ $? -eq 0 ]]; then
        print_status "Database restored successfully"
    else
        print_error "Database restore failed"
        exit 1
    fi
    
    # Restore application files
    print_info "Restoring application files..."
    
    # Create temporary directory for extraction
    TEMP_DIR=$(mktemp -d)
    tar -xzf "$RESTORE_PATH/application_${backup_timestamp}.tar.gz" -C "$TEMP_DIR"
    
    # Backup current .env before overwriting
    if [[ -f "$WEB_ROOT/.env" ]]; then
        cp "$WEB_ROOT/.env" "$TEMP_DIR/.env.backup"
    fi
    
    # Replace application files
    rsync -av --delete "$TEMP_DIR/" "$WEB_ROOT/"
    
    # Restore .env if backed up
    if [[ -f "$TEMP_DIR/.env.backup" ]]; then
        mv "$TEMP_DIR/.env.backup" "$WEB_ROOT/.env"
        print_info "Preserved current .env configuration"
    fi
    
    # Cleanup
    rm -rf "$TEMP_DIR"
    
    print_status "Application files restored successfully"
    
    # Restore Apache configuration if available
    if [[ -f "$RESTORE_PATH/${PROJECT_NAME}-80.conf" ]]; then
        sudo cp "$RESTORE_PATH/${PROJECT_NAME}-80.conf" "/etc/apache2/sites-available/"
        print_status "Apache HTTP configuration restored"
    fi
    
    if [[ -f "$RESTORE_PATH/${PROJECT_NAME}-443.conf" ]]; then
        sudo cp "$RESTORE_PATH/${PROJECT_NAME}-443.conf" "/etc/apache2/sites-available/"
        print_status "Apache HTTPS configuration restored"
    fi
    
    # Reload Apache
    sudo systemctl reload apache2
    print_status "Apache configuration reloaded"
    
    echo -e "\n${GREEN}🎉 RESTORE COMPLETED SUCCESSFULLY!${NC}"
    echo "Application restored from backup: $backup_timestamp"
}

# Function to list available backups
list_backups() {
    echo -e "\n${BLUE}📋 AVAILABLE BACKUPS${NC}"
    echo "===================="
    
    if [[ ! -d "$BACKUP_DIR" ]]; then
        print_warning "No backup directory found: $BACKUP_DIR"
        return
    fi
    
    local backups=($(ls -1 "$BACKUP_DIR" | sort -r))
    
    if [[ ${#backups[@]} -eq 0 ]]; then
        print_warning "No backups found"
        return
    fi
    
    for backup in "${backups[@]}"; do
        if [[ -d "$BACKUP_DIR/$backup" ]]; then
            local size=$(du -sh "$BACKUP_DIR/$backup" 2>/dev/null | cut -f1)
            local date=$(echo "$backup" | sed 's/_/ /' | sed 's/\(..\)\(..\)\(..\)/20\3-\2-\1 /')
            echo "  $backup ($size) - $date"
        fi
    done
    
    echo
    echo "Usage: $0 restore <timestamp>"
}

# Function to cleanup old backups
cleanup_backups() {
    local keep_days=${1:-30}
    
    echo -e "\n${BLUE}🧹 CLEANING UP OLD BACKUPS${NC}"
    echo "============================"
    print_info "Removing backups older than $keep_days days"
    
    find "$BACKUP_DIR" -type d -name "20*_*" -mtime +$keep_days -exec rm -rf {} \;
    print_status "Cleanup completed"
}

# Main script logic
case "$1" in
    "backup")
        create_backup
        ;;
    "restore")
        restore_backup "$2"
        ;;
    "list")
        list_backups
        ;;
    "cleanup")
        cleanup_backups "$2"
        ;;
    *)
        echo "Newb Vote 2025s - Backup and Recovery Script"
        echo "============================================"
        echo
        echo "Usage: $0 <command> [options]"
        echo
        echo "Commands:"
        echo "  backup                 Create complete backup"
        echo "  restore <timestamp>    Restore from specific backup"
        echo "  list                   List available backups"
        echo "  cleanup [days]         Remove backups older than [days] (default: 30)"
        echo
        echo "Examples:"
        echo "  $0 backup"
        echo "  $0 restore 20241220_143022"
        echo "  $0 list"
        echo "  $0 cleanup 7"
        exit 1
        ;;
esac