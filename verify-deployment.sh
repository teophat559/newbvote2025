#!/bin/bash

# Production Deployment Verification Script
# Run this script on the target VPS server after deployment

echo "🚀 Newb Vote 2025s - Deployment Verification"
echo "=============================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

ERRORS=0
WARNINGS=0

error() {
    echo -e "${RED}❌ $1${NC}"
    ((ERRORS++))
}

warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
    ((WARNINGS++))
}

success() {
    echo -e "${GREEN}✅ $1${NC}"
}

# Check if running as root or with sudo
if [ "$EUID" -eq 0 ]; then 
    warning "Running as root. Consider using a dedicated user for better security."
fi

echo -e "\n1. Checking system requirements..."

# Check PHP version
PHP_VERSION=$(php -r "echo PHP_VERSION;")
if [ $? -eq 0 ]; then
    if php -r "exit(version_compare(PHP_VERSION, '8.1.0', '>=') ? 0 : 1);"; then
        success "PHP version $PHP_VERSION (meets requirement >=8.1.0)"
    else
        error "PHP version $PHP_VERSION is too old (requires >=8.1.0)"
    fi
else
    error "PHP is not installed or not in PATH"
fi

# Check PHP extensions
REQUIRED_EXTENSIONS=("pdo" "pdo_mysql" "mbstring" "json" "openssl" "curl")
for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if php -m | grep -q "^$ext$"; then
        success "PHP extension: $ext"
    else
        error "Missing PHP extension: $ext"
    fi
done

# Check web server
if command -v apache2 >/dev/null 2>&1 || command -v httpd >/dev/null 2>&1; then
    success "Apache web server found"
elif command -v nginx >/dev/null 2>&1; then
    success "Nginx web server found"
else
    error "No web server (Apache/Nginx) found"
fi

echo -e "\n2. Checking file permissions..."

# Check directory permissions
DIRS=("uploads" "storage" "logs" "public")
for dir in "${DIRS[@]}"; do
    if [ -d "$dir" ]; then
        PERMS=$(stat -c "%a" "$dir")
        if [ "$PERMS" -ge "755" ]; then
            success "Directory $dir has permissions $PERMS"
        else
            warning "Directory $dir permissions $PERMS may be too restrictive"
        fi
    else
        error "Directory $dir does not exist"
    fi
done

echo -e "\n3. Checking configuration files..."

# Check environment files
if [ -f ".env" ] || [ -f ".env.production" ]; then
    success "Environment configuration file exists"
else
    error "No .env or .env.production file found"
fi

# Check config files
CONFIG_FILES=("config/config.php" "config/database.php" "config/session.php")
for file in "${CONFIG_FILES[@]}"; do
    if [ -f "$file" ]; then
        success "Config file exists: $file"
    else
        error "Config file missing: $file"
    fi
done

echo -e "\n4. Checking database connection..."

# Test database connection using PHP
DB_CHECK=$(php -r "
try {
    require_once 'config/env.php';
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
" 2>/dev/null)

if [ "$DB_CHECK" = "OK" ]; then
    success "Database connection successful"
elif [ "$DB_CHECK" = "NO_PDO" ]; then
    error "Database PDO object not available"
else
    error "Database connection failed: $DB_CHECK"
fi

echo -e "\n5. Checking web server configuration..."

# Check if running via web server
if [ -f ".htaccess" ]; then
    success ".htaccess file exists"
else
    warning ".htaccess file missing (may be required for URL rewriting)"
fi

# Check SSL certificate (if HTTPS is configured)
if command -v openssl >/dev/null 2>&1; then
    DOMAIN=$(grep -E "^APP_URL=" .env* 2>/dev/null | head -n1 | cut -d'=' -f2 | sed 's/https:\/\///' | sed 's/http:\/\///')
    if [ -n "$DOMAIN" ] && [[ "$DOMAIN" != *"localhost"* ]]; then
        if echo | openssl s_client -connect "$DOMAIN:443" -servername "$DOMAIN" 2>/dev/null | openssl x509 -noout -dates >/dev/null 2>&1; then
            success "SSL certificate appears valid for $DOMAIN"
        else
            warning "Could not verify SSL certificate for $DOMAIN"
        fi
    fi
fi

echo -e "\n6. Running PHP production check..."

# Run the production check script
if [ -f "production-check.php" ]; then
    php production-check.php | tail -n 20
else
    warning "Production check script not found"
fi

echo -e "\n7. Checking systemd services (if applicable)..."

# Check for systemd services
if [ -d "systemd" ]; then
    for service_file in systemd/*.service; do
        if [ -f "$service_file" ]; then
            service_name=$(basename "$service_file")
            if systemctl is-active --quiet "$service_name" 2>/dev/null; then
                success "Service $service_name is running"
            else
                warning "Service $service_name is not running or not installed"
            fi
        fi
    done
fi

echo -e "\n=============================================="
echo -e "DEPLOYMENT VERIFICATION SUMMARY"
echo -e "=============================================="

if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo -e "${GREEN}🎉 DEPLOYMENT SUCCESSFUL!${NC}"
    echo "All checks passed. The application appears ready for production use."
elif [ $ERRORS -eq 0 ]; then
    echo -e "${YELLOW}⚠️  DEPLOYMENT MOSTLY SUCCESSFUL${NC}"
    echo "No critical errors, but $WARNINGS warning(s) should be reviewed."
else
    echo -e "${RED}🚫 DEPLOYMENT ISSUES DETECTED${NC}"
    echo "$ERRORS error(s) and $WARNINGS warning(s) need to be addressed."
fi

echo -e "\n📝 Post-deployment checklist:"
echo "1. Test user login and authentication"
echo "2. Test admin panel access and functionality" 
echo "3. Test voting functionality"
echo "4. Verify SSL certificate and HTTPS redirect"
echo "5. Check log files for any errors"
echo "6. Set up automated backups"
echo "7. Configure monitoring and alerting"
echo "8. Review and set appropriate file permissions"
echo "9. Enable firewall and security measures"
echo "10. Test disaster recovery procedures"

echo -e "\n=============================================="
exit $ERRORS