# Production Deployment Guide - Newb Vote 2025s

## Overview
This guide provides step-by-step instructions for deploying the Newb Vote 2025s voting system to a production VPS server.

## Pre-deployment Checklist

### 1. Code Integrity Verification
- [x] Removed debug files: `debug-env.php`, `debug-env-only.php`, `test-mysql.php`
- [x] Replaced placeholder alert buttons with disabled production-ready buttons
- [x] Fixed TODO comments in `AuthController.php` (OTP code exposure)
- [x] Added production checks to seed scripts
- [x] Verified no sensitive data in source code

### 2. Environment Configuration
- [x] Created `.env.production` template with all required variables
- [x] Enhanced environment validation with security checks
- [x] Added production-specific security validations
- [x] Configured error display and logging for production

### 3. Security Settings
- [x] Error display disabled in production (`ERROR_DISPLAY=0`)
- [x] Error logging enabled with secure log path
- [x] Session security configured for HTTPS
- [x] CORS settings configured for API endpoints
- [x] Admin security key validation enhanced

## Deployment Steps

### Step 1: Server Preparation
```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y apache2 mysql-server php8.1 php8.1-mysql php8.1-mbstring \
    php8.1-curl php8.1-gd php8.1-json php8.1-xml php8.1-zip php8.1-bcmath \
    composer git certbot python3-certbot-apache
```

### Step 2: Clone and Configure Application
```bash
# Clone repository
git clone https://github.com/teophat559/newbvote2025.git /var/www/newbvote2025s
cd /var/www/newbvote2025s

# Set proper ownership
sudo chown -R www-data:www-data /var/www/newbvote2025s

# Create environment configuration
cp env.example .env
# Edit .env with production values (see Environment Variables section below)

# Install dependencies
composer install --no-dev --optimize-autoloader

# Set proper permissions
chmod -R 755 .
chmod -R 777 uploads/ logs/ storage/
```

### Step 3: Database Setup
```bash
# Create database and user
mysql -u root -p <<EOF
CREATE DATABASE newb_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'newb_vote2025'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON newb_db.* TO 'newb_vote2025'@'localhost';
FLUSH PRIVILEGES;
EOF

# Import database schema (if available)
# mysql -u root -p newb_db < database/schema.sql
```

### Step 4: Apache Configuration
```bash
# Deploy Apache configuration
cd apache/
sudo ./deploy-apache.sh

# Enable required modules
sudo a2enmod rewrite ssl
sudo systemctl restart apache2
```

### Step 5: SSL Certificate Setup
```bash
# Obtain SSL certificate
sudo certbot --apache -d yourdomain.com

# Set up automatic renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

### Step 6: WebSocket Service (Optional)
```bash
# Install WebSocket service
cd systemd/
sudo cp newbvote2025s-websocket.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable newbvote2025s-websocket
sudo systemctl start newbvote2025s-websocket
```

## Environment Variables

Create a `.env` file with the following required variables:

```env
# Application Environment
APP_ENV=production
APP_URL=https://yourdomain.com
APP_NAME="Special Program 2025"

# Database Configuration
DB_TYPE=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=newb_db
DB_USER=newb_vote2025
DB_PASS=your_secure_database_password

# WebSocket Configuration
WS_HOST=127.0.0.1
WS_PORT=8090

# Security Settings (CRITICAL - Generate secure keys)
ADMIN_SECURITY_KEY=your-minimum-20-character-secure-admin-key
DATA_ENCRYPTION_KEY=your-exactly-32-character-key-123456
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
```

## Security Key Generation

Generate secure keys using these commands:

```bash
# Generate ADMIN_SECURITY_KEY (minimum 20 characters)
openssl rand -base64 32

# Generate DATA_ENCRYPTION_KEY (exactly 32 characters)
openssl rand -base64 24 | cut -c1-32
```

## Post-Deployment Verification

### Run Production Checks
```bash
# Run comprehensive production check
php production-check.php

# Run deployment verification
./verify-deployment.sh
```

### Manual Testing Checklist
- [ ] Homepage loads correctly
- [ ] User registration works
- [ ] User login works
- [ ] Admin panel accessible with correct credentials
- [ ] Voting functionality works
- [ ] API endpoints respond correctly
- [ ] HTTPS redirect works
- [ ] SSL certificate valid
- [ ] Error logging works (check logs)
- [ ] WebSocket service running (if enabled)

### Performance & Security
```bash
# Check PHP performance
php -i | grep opcache

# Verify file permissions
ls -la uploads/ storage/ logs/

# Check Apache configuration
apache2ctl configtest

# Monitor logs
tail -f /var/log/apache2/error.log
tail -f /var/log/php_errors.log
```

## Monitoring & Maintenance

### Log Monitoring
```bash
# Application logs
tail -f logs/*.log

# PHP errors
tail -f /var/log/php_errors.log

# Apache access/error logs
tail -f /var/log/apache2/access.log
tail -f /var/log/apache2/error.log
```

### Backup Strategy
```bash
# Database backup
mysqldump -u root -p newb_db > backup_$(date +%Y%m%d_%H%M%S).sql

# File backup
tar -czf files_backup_$(date +%Y%m%d_%H%M%S).tar.gz uploads/ storage/
```

### Automated Backup Script
```bash
#!/bin/bash
# Add to crontab: 0 2 * * * /path/to/backup.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups"
DB_NAME="newb_db"

# Create backup directory
mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u root -p$DB_PASS $DB_NAME > $BACKUP_DIR/db_$DATE.sql

# Files backup
tar -czf $BACKUP_DIR/files_$DATE.tar.gz uploads/ storage/ logs/

# Keep only last 7 days of backups
find $BACKUP_DIR -name "*.sql" -mtime +7 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete
```

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check MySQL service: `sudo systemctl status mysql`
   - Verify credentials in `.env` file
   - Check database exists and user has permissions

2. **Permission Denied Errors**
   - Fix ownership: `sudo chown -R www-data:www-data /var/www/newbvote2025s`
   - Fix permissions: `chmod -R 755 . && chmod -R 777 uploads/ logs/ storage/`

3. **Apache Configuration Issues**
   - Test config: `apache2ctl configtest`
   - Check virtual host: `sudo apache2ctl -S`
   - Review error logs: `sudo tail -f /var/log/apache2/error.log`

4. **SSL Certificate Issues**
   - Verify certificate: `sudo certbot certificates`
   - Renew if needed: `sudo certbot renew`
   - Check DNS settings point to server IP

### Emergency Procedures

1. **Application Down**
   - Check Apache: `sudo systemctl status apache2`
   - Check PHP-FPM: `sudo systemctl status php8.1-fpm`
   - Review error logs
   - Restart services if needed

2. **Database Issues**
   - Check MySQL: `sudo systemctl status mysql`
   - Review MySQL error log: `sudo tail -f /var/log/mysql/error.log`
   - Check disk space: `df -h`

## Support

For technical support or questions about deployment:
- Review error logs first
- Check system resource usage: `htop`, `df -h`
- Verify all services are running
- Contact development team with specific error messages and logs

---

**⚠️ Important Security Notes:**
- Never commit `.env` files to version control
- Use strong, unique passwords for all services
- Regularly update system packages and application dependencies
- Monitor logs for suspicious activity
- Keep regular backups and test restoration procedures