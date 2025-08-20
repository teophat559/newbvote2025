#!/bin/bash

# Production Deployment Script for Newb Vote 2025s
# This script handles the complete production deployment setup

set -e  # Exit on any error

echo "🚀 Newb Vote 2025s - Production Deployment Setup"
echo "=================================================="

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_NAME="newbvote2025s"
DOMAIN="newbvote2025s.online"
DB_NAME="newb_vote2025s"
DB_USER="newb_vote2025s_user"
WEB_ROOT="/home/${DOMAIN}/public_html"
BACKUP_DIR="/home/${DOMAIN}/backups"

# Function to print colored output
print_status() {
    echo -e "${GREEN}✅${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠️${NC}  $1"
}

print_error() {
    echo -e "${RED}❌${NC} $1"
}

print_info() {
    echo -e "${BLUE}ℹ️${NC}  $1"
}

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   print_error "This script should not be run as root for security reasons"
   exit 1
fi

# 1. SYSTEM REQUIREMENTS CHECK
echo -e "\n${BLUE}1. CHECKING SYSTEM REQUIREMENTS${NC}"
echo "=================================="

# Check PHP version
PHP_VERSION=$(php -v | head -n1 | cut -d' ' -f2 | cut -d'.' -f1,2)
if php -v | grep -q "PHP 8.[12]"; then
    print_status "PHP version $PHP_VERSION (meets requirement >=8.1.0)"
else
    print_error "PHP version $PHP_VERSION does not meet requirement (>=8.1.0)"
    exit 1
fi

# Check required PHP extensions
REQUIRED_EXTENSIONS=("pdo" "pdo_mysql" "mbstring" "json" "openssl" "curl" "gd" "zip")
for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if php -m | grep -q "^$ext$"; then
        print_status "PHP extension: $ext"
    else
        print_error "Missing PHP extension: $ext"
        exit 1
    fi
done

# Check Apache modules
if command -v apache2ctl &> /dev/null; then
    REQUIRED_MODULES=("rewrite" "headers" "ssl")
    for mod in "${REQUIRED_MODULES[@]}"; do
        if apache2ctl -M 2>/dev/null | grep -q "${mod}_module"; then
            print_status "Apache module: $mod"
        else
            print_warning "Apache module not loaded: $mod (may need to enable)"
        fi
    done
else
    print_warning "Apache not found or not accessible"
fi

# 2. CREATE DIRECTORIES
echo -e "\n${BLUE}2. CREATING DIRECTORY STRUCTURE${NC}"
echo "===================================="

REQUIRED_DIRS=("$WEB_ROOT" "$BACKUP_DIR" "$WEB_ROOT/public" "$WEB_ROOT/uploads" "$WEB_ROOT/storage" "$WEB_ROOT/logs")
for dir in "${REQUIRED_DIRS[@]}"; do
    if [[ ! -d "$dir" ]]; then
        mkdir -p "$dir"
        print_status "Created directory: $dir"
    else
        print_info "Directory exists: $dir"
    fi
done

# Set proper permissions
chmod 755 "$WEB_ROOT"
chmod 755 "$WEB_ROOT/public"
chmod 755 "$WEB_ROOT/uploads"
chmod 755 "$WEB_ROOT/storage" 
chmod 755 "$WEB_ROOT/logs"
print_status "Set directory permissions"

# 3. COPY APPLICATION FILES
echo -e "\n${BLUE}3. DEPLOYING APPLICATION FILES${NC}"
echo "================================="

# Copy application files (assuming script is run from project directory)
if [[ -f "index.php" && -d "src" ]]; then
    rsync -av --exclude='.git' --exclude='vendor' --exclude='node_modules' --exclude='.env*' . "$WEB_ROOT/"
    print_status "Application files copied to $WEB_ROOT"
    
    # Install composer dependencies
    cd "$WEB_ROOT"
    if command -v composer &> /dev/null; then
        composer install --no-dev --optimize-autoloader --no-interaction
        print_status "Composer dependencies installed"
    else
        print_warning "Composer not found - dependencies may need manual installation"
    fi
else
    print_error "Application files not found. Run this script from the project root directory."
    exit 1
fi

# 4. ENVIRONMENT CONFIGURATION
echo -e "\n${BLUE}4. ENVIRONMENT CONFIGURATION${NC}"
echo "============================="

# Generate secure keys
ADMIN_KEY=$(openssl rand -hex 32)
ENCRYPTION_KEY=$(openssl rand -hex 16)
DB_PASS=$(openssl rand -base64 24 | tr -d "=+/" | cut -c1-20)

# Create production .env file
cat > "$WEB_ROOT/.env" << EOF
# Production Environment Configuration
APP_ENV=production
APP_URL=https://${DOMAIN}
APP_NAME="Special Program 2025"

# Database Configuration
DB_TYPE=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}

# WebSocket Configuration
WS_HOST=127.0.0.1
WS_PORT=8090

# Security Settings
ADMIN_SECURITY_KEY=${ADMIN_KEY}
DATA_ENCRYPTION_KEY=${ENCRYPTION_KEY}
SESSION_SECURE_COOKIE=1
SESSION_TIMEOUT=3600
MAX_LOGIN_ATTEMPTS=5
LOGIN_TIMEOUT=900

# Error Reporting (Production)
ERROR_DISPLAY=0
ERROR_LOG_PATH=/var/log/php_errors.log

# Timezone
TIMEZONE=Asia/Ho_Chi_Minh

# File Upload
MAX_FILE_SIZE=5242880
UPLOAD_DIR=uploads/

# Additional Security
ALLOWED_ORIGINS=https://${DOMAIN},https://www.${DOMAIN}
MAINTENANCE_MODE=0
EOF

chmod 600 "$WEB_ROOT/.env"
print_status "Environment file created with secure keys"

# 5. DATABASE SETUP
echo -e "\n${BLUE}5. DATABASE SETUP${NC}"
echo "=================="

print_info "Database setup requires MySQL root access"
read -p "Do you want to set up the database now? (y/n): " -n 1 -r
echo

if [[ $REPLY =~ ^[Yy]$ ]]; then
    read -s -p "Enter MySQL root password: " MYSQL_ROOT_PASS
    echo
    
    # Create database and user
    mysql -u root -p"$MYSQL_ROOT_PASS" << EOF
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT SELECT, INSERT, UPDATE, DELETE ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF

    if [[ $? -eq 0 ]]; then
        print_status "Database and user created"
        
        # Import database schema
        mysql -u root -p"$MYSQL_ROOT_PASS" "$DB_NAME" < "$WEB_ROOT/database/setup.sql"
        print_status "Database schema imported"
    else
        print_error "Database setup failed"
        exit 1
    fi
else
    print_warning "Database setup skipped - manual configuration required"
fi

# 6. SECURITY HARDENING
echo -e "\n${BLUE}6. SECURITY HARDENING${NC}"
echo "======================"

# Protect sensitive files
cat > "$WEB_ROOT/.htaccess" << 'EOF'
# Comprehensive Security Configuration

# Enable rewrite engine
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    
    # Block access to sensitive files
    RewriteRule ^\.env$ - [F,L]
    RewriteRule ^config/ - [F,L]
    RewriteRule ^database/ - [F,L]
    RewriteRule ^logs/ - [F,L]
    RewriteRule ^vendor/ - [F,L]
    RewriteRule ^storage/ - [F,L]
    
    # Route all requests to index.php
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [L]
</IfModule>

# Security Headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
</IfModule>

# Block sensitive file extensions
<FilesMatch "\.(env|log|sql|bak|backup|old|tmp)$">
    Require all denied
</FilesMatch>

# Performance optimizations
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain text/html text/xml text/css application/xml application/xhtml+xml application/rss+xml application/javascript application/x-javascript
</IfModule>

<IfModule mod_expires.c>
    ExpiresActive on
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType image/* "access plus 1 year"
</IfModule>
EOF

print_status "Security configuration applied"

# 7. FINAL VERIFICATION
echo -e "\n${BLUE}7. RUNNING PRODUCTION CHECKS${NC}"
echo "============================="

cd "$WEB_ROOT"
php production-check.php | grep -E "(✅|⚠️|❌)"

echo -e "\n${GREEN}🎉 DEPLOYMENT COMPLETED!${NC}"
echo "========================="
echo
echo "Next steps:"
echo "1. Configure your web server to point to: $WEB_ROOT/public"
echo "2. Set up SSL certificate for HTTPS"
echo "3. Configure firewall rules"
echo "4. Set up automated backups"
echo "5. Test the complete application functionality"
echo
echo "Database credentials have been saved to $WEB_ROOT/.env"
echo "Keep this file secure and never commit it to version control!"
echo
echo "Default admin login:"
echo "  Username: admin"
echo "  Password: admin123"
echo "  ⚠️  CHANGE THIS PASSWORD IMMEDIATELY AFTER FIRST LOGIN!"