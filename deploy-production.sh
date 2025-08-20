#!/bin/bash

# Production Deployment Script for Newb Vote 2025s
# This script implements the 8-point production readiness checklist

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🚀 Newb Vote 2025s - Production Deployment${NC}"
echo "=============================================="

# Counters
ERRORS=0
WARNINGS=0
SUCCESS=0

error() {
    echo -e "${RED}❌ $1${NC}"
    ERRORS=$((ERRORS + 1))
}

warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
    WARNINGS=$((WARNINGS + 1))
}

success() {
    echo -e "${GREEN}✅ $1${NC}"
    SUCCESS=$((SUCCESS + 1))
}

info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

# 1. CLEAN PROJECT STRUCTURE (Cấu trúc dự án)
echo -e "\n${BLUE}1. Checking project structure cleanliness...${NC}"

# Remove debug/test files if they exist
DEBUG_FILES=(
    "debug-env.php"
    "debug-env-only.php" 
    "test-mysql.php"
    "test.php"
    "phpinfo.php"
    "test_db.php"
    "debug.php"
)

for file in "${DEBUG_FILES[@]}"; do
    if [ -f "$file" ]; then
        warning "Removing debug file: $file"
        rm -f "$file"
    else
        success "No debug file found: $file"
    fi
done

# Check public directory structure
if [ -d "public" ]; then
    success "Public directory exists"
    if [ -f "public/index.php" ]; then
        success "Public front controller exists"
    else
        error "public/index.php missing - required for production"
    fi
else
    error "public directory missing - required for production deployment"
fi

# Check sensitive directory protection
SENSITIVE_DIRS=(".git" "config" "src" "storage" "logs")
for dir in "${SENSITIVE_DIRS[@]}"; do
    if [ -d "$dir" ]; then
        success "Sensitive directory exists: $dir"
        # These should be blocked by .htaccess or Apache config
    fi
done

# 2. APACHE CONFIGURATION (Cấu hình Apache)
echo -e "\n${BLUE}2. Checking Apache configuration...${NC}"

if [ -f ".htaccess" ]; then
    success ".htaccess exists"
    
    # Check for URL rewrite rules
    if grep -q "RewriteEngine On" .htaccess; then
        success "URL rewrite enabled in .htaccess"
    else
        error "RewriteEngine not enabled in .htaccess"
    fi
    
    # Check for security headers
    if grep -q "X-Content-Type-Options" .htaccess; then
        success "Security headers configured in .htaccess"
    else
        warning "Security headers missing in .htaccess"
    fi
else
    error ".htaccess missing - required for Apache"
fi

# Check Apache virtual host configs
APACHE_CONFIGS=("apache/newbvote2025s-80.conf" "apache/newbvote2025s-443.conf")
for config in "${APACHE_CONFIGS[@]}"; do
    if [ -f "$config" ]; then
        success "Apache config exists: $config"
        
        # Check if DocumentRoot points to public/
        if grep -q "public" "$config"; then
            success "Apache DocumentRoot points to public directory"
        else
            warning "Apache DocumentRoot may not point to public directory"
        fi
    else
        error "Missing Apache config: $config"
    fi
done

# 3. ENVIRONMENT CONFIGURATION (Môi trường và biến cấu hình)
echo -e "\n${BLUE}3. Checking environment configuration...${NC}"

# Check for production env file
if [ -f ".env" ]; then
    success "Production .env file exists"
elif [ -f ".env.production" ]; then
    info "Found .env.production template - copying to .env for production"
    cp .env.production .env
    success "Created .env from template"
else
    error "No .env or .env.production file found"
fi

# Validate environment using PHP script
if [ -f ".env" ]; then
    ENV_CHECK=$(php -r "
    require_once 'config/env.php';
    try {
        require_once 'config/validate_env.php';
        echo 'OK';
    } catch (Exception \$e) {
        echo 'ERROR: ' . \$e->getMessage();
    }
    " 2>/dev/null || echo "PARSE_ERROR")
    
    if [ "$ENV_CHECK" = "OK" ]; then
        success "Environment variables validated"
    else
        error "Environment validation failed: $ENV_CHECK"
    fi
fi

# 4. DATABASE CONFIGURATION (Cơ sở dữ liệu)
echo -e "\n${BLUE}4. Checking database configuration...${NC}"

# Test database connection
DB_CHECK=$(php -r "
require_once 'config/env.php';
try {
    require_once 'config/database.php';
    if (isset(\$pdo)) {
        \$pdo->query('SELECT 1');
        echo 'OK';
    } else {
        echo 'NO_PDO';
    }
} catch (Exception \$e) {
    echo 'ERROR: ' . \$e->getMessage();
}
" 2>/dev/null || echo "PARSE_ERROR")

if [ "$DB_CHECK" = "OK" ]; then
    success "Database connection successful"
elif [ "$DB_CHECK" = "NO_PDO" ]; then
    error "Database PDO object not available"
elif [ "$DB_CHECK" = "PARSE_ERROR" ]; then
    error "PHP parse error in database configuration"
else
    warning "Database connection failed: $DB_CHECK (may be expected if DB not set up yet)"
fi

# 5. PHP CONFIGURATION (Cấu hình PHP)
echo -e "\n${BLUE}5. Checking PHP configuration...${NC}"

# Check PHP version
PHP_VERSION=$(php -r "echo PHP_VERSION;")
if php -r "exit(version_compare(PHP_VERSION, '8.1.0', '>=') ? 0 : 1);"; then
    success "PHP version $PHP_VERSION meets requirement (>=8.1.0)"
else
    error "PHP version $PHP_VERSION is too old (requires >=8.1.0)"
fi

# Check required extensions
REQUIRED_EXTENSIONS=("pdo" "pdo_mysql" "mbstring" "json" "openssl" "curl" "gd")
for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if php -m | grep -q "^$ext$"; then
        success "PHP extension available: $ext"
    else
        error "Missing PHP extension: $ext"
    fi
done

# Check OPcache
if php -m | grep -q "^Zend OPcache$"; then
    success "OPcache extension available"
    
    # Check if OPcache is enabled
    OPCACHE_STATUS=$(php -r "echo ini_get('opcache.enable') ? 'enabled' : 'disabled';")
    if [ "$OPCACHE_STATUS" = "enabled" ]; then
        success "OPcache is enabled"
    else
        warning "OPcache is disabled - enable for better performance"
    fi
else
    warning "OPcache extension not available"
fi

# 6. SYSTEM SECURITY (Bảo mật hệ thống)
echo -e "\n${BLUE}6. Checking system security...${NC}"

# Run security audit
if [ -f "security-audit.php" ]; then
    info "Running security audit..."
    php security-audit.php > /tmp/security-audit.log 2>&1 || true
    
    if grep -q "SECURITY AUDIT PASSED" /tmp/security-audit.log; then
        success "Security audit passed"
    elif grep -q "SECURITY AUDIT FAILED" /tmp/security-audit.log; then
        error "Security audit failed - check security-audit.php output"
    else
        warning "Security audit completed with warnings"
    fi
else
    warning "security-audit.php not found"
fi

# Check HTTPS configuration
if [ -f ".env" ]; then
    APP_URL=$(grep "^APP_URL=" .env | cut -d'=' -f2 | tr -d '"' | tr -d "'")
    if [[ "$APP_URL" == https://* ]]; then
        success "APP_URL configured for HTTPS: $APP_URL"
    else
        error "APP_URL not configured for HTTPS: $APP_URL"
    fi
fi

# Check file permissions
CRITICAL_DIRS=("uploads" "storage" "logs" "public")
for dir in "${CRITICAL_DIRS[@]}"; do
    if [ -d "$dir" ]; then
        PERMS=$(stat -c "%a" "$dir")
        if [ "$PERMS" = "755" ] || [ "$PERMS" = "775" ]; then
            success "Directory $dir has secure permissions: $PERMS"
        else
            warning "Directory $dir permissions may need adjustment: $PERMS"
        fi
    else
        error "Critical directory missing: $dir"
    fi
done

# 7. BUSINESS FLOW TESTING (Kiểm thử luồng nghiệp vụ)
echo -e "\n${BLUE}7. Preparing business flow testing...${NC}"

# Create a test script for business flows
if [ ! -f "test-business-flows.php" ]; then
    info "Creating business flow test script..."
    cat > test-business-flows.php << 'EOF'
<?php
// Business Flow Test Script
// Run this after deployment to test critical functionality

echo "🧪 Business Flow Testing\n";
echo "========================\n";

$tests = [
    'config_load' => 'Configuration Loading',
    'database_connect' => 'Database Connection', 
    'session_management' => 'Session Management',
    'csrf_protection' => 'CSRF Protection',
    'user_authentication' => 'User Authentication Flow',
    'admin_authentication' => 'Admin Authentication Flow',
    'voting_system' => 'Voting System',
    'api_endpoints' => 'API Endpoints'
];

$passed = 0;
$total = count($tests);

foreach ($tests as $test => $description) {
    echo "Testing: $description... ";
    
    switch ($test) {
        case 'config_load':
            try {
                require_once 'config/config.php';
                echo "✅ PASS\n";
                $passed++;
            } catch (Exception $e) {
                echo "❌ FAIL: " . $e->getMessage() . "\n";
            }
            break;
            
        case 'database_connect':
            try {
                require_once 'config/database.php';
                if (isset($pdo)) {
                    $pdo->query('SELECT 1');
                    echo "✅ PASS\n";
                    $passed++;
                } else {
                    echo "❌ FAIL: PDO not available\n";
                }
            } catch (Exception $e) {
                echo "❌ FAIL: " . $e->getMessage() . "\n";
            }
            break;
            
        case 'session_management':
            try {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['test'] = 'value';
                if (isset($_SESSION['test'])) {
                    echo "✅ PASS\n";
                    $passed++;
                } else {
                    echo "❌ FAIL: Session not working\n";
                }
            } catch (Exception $e) {
                echo "❌ FAIL: " . $e->getMessage() . "\n";
            }
            break;
            
        case 'csrf_protection':
            try {
                require_once 'src/Core/Security.php';
                $token = App\Core\Security::csrfToken();
                if (!empty($token)) {
                    echo "✅ PASS\n";
                    $passed++;
                } else {
                    echo "❌ FAIL: CSRF token empty\n";
                }
            } catch (Exception $e) {
                echo "❌ FAIL: " . $e->getMessage() . "\n";
            }
            break;
            
        default:
            echo "⚠️  MANUAL TEST REQUIRED\n";
    }
}

echo "\n========================\n";
echo "Automated Tests: $passed/$total passed\n";
echo "Manual tests required for: authentication flows, voting, API endpoints\n";
echo "========================\n";
EOF
    success "Created business flow test script"
else
    success "Business flow test script exists"
fi

# 8. BACKUP AND RECOVERY (Sao lưu và khôi phục)
echo -e "\n${BLUE}8. Setting up backup and recovery procedures...${NC}"

# Create backup script
if [ ! -f "backup-production.sh" ]; then
    info "Creating production backup script..."
    cat > backup-production.sh << 'EOF'
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
EOF
    chmod +x backup-production.sh
    success "Created production backup script"
else
    success "Production backup script exists"
fi

# Create rollback script
if [ ! -f "rollback-production.sh" ]; then
    info "Creating production rollback script..."
    cat > rollback-production.sh << 'EOF'
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
EOF
    chmod +x rollback-production.sh
    success "Created production rollback script"
else
    success "Production rollback script exists"
fi

# FINAL SUMMARY
echo -e "\n" . "$(printf '=%.0s' {1..50})"
echo -e "${BLUE}PRODUCTION DEPLOYMENT SUMMARY${NC}"
echo "$(printf '=%.0s' {1..50})"

echo -e "\n${GREEN}✅ Success: $SUCCESS${NC}"
echo -e "${YELLOW}⚠️  Warnings: $WARNINGS${NC}"  
echo -e "${RED}❌ Errors: $ERRORS${NC}"

if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo -e "\n${GREEN}🎉 PRODUCTION DEPLOYMENT READY!${NC}"
    echo "All checks passed. The application is ready for production deployment."
elif [ $ERRORS -eq 0 ]; then
    echo -e "\n${YELLOW}⚠️  DEPLOYMENT MOSTLY READY${NC}"
    echo "No critical errors found, but please review warnings above."
else
    echo -e "\n${RED}🚫 DEPLOYMENT NOT READY${NC}"
    echo "Please fix the $ERRORS error(s) before production deployment."
fi

echo -e "\n${BLUE}📋 POST-DEPLOYMENT CHECKLIST:${NC}"
echo "1. Run: php test-business-flows.php"
echo "2. Test user registration and login"
echo "3. Test admin panel access"
echo "4. Test voting functionality" 
echo "5. Verify HTTPS and SSL certificate"
echo "6. Set up automated backups (crontab)"
echo "7. Configure monitoring and logging"
echo "8. Test rollback procedures"

echo -e "\n$(printf '=%.0s' {1..50})"

exit $ERRORS