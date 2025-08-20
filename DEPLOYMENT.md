# Newb Vote 2025s - Hướng dẫn triển khai Production hoàn chỉnh

**Hệ thống bình chọn trực tuyến với đầy đủ tính năng bảo mật cho môi trường production**

## 🎯 Tổng quan

Tài liệu này hướng dẫn triển khai hoàn chỉnh hệ thống Newb Vote 2025s trên môi trường production với Apache và PHP 8.1+, đáp ứng đầy đủ các yêu cầu bảo mật và hiệu suất.

## ✅ Danh sách kiểm tra sẵn sàng triển khai

### 1. Cấu trúc dự án ✅
- [x] Mã nguồn được tổ chức rõ ràng, thư mục public là điểm entry
- [x] Loại bỏ tất cả file debug, test, demo
- [x] Bảo vệ thư mục nhạy cảm (.git, vendor, node_modules)
- [x] Thay thế alert() JavaScript bằng notification system

### 2. Cấu hình Apache ✅  
- [x] Apache với URL rewrite được kích hoạt
- [x] VirtualHost trỏ đúng vào thư mục public
- [x] File .htaccess hoạt động và bảo vệ các file nhạy cảm
- [x] Security headers được cấu hình

### 3. Môi trường và biến cấu hình ✅
- [x] File .env với các giá trị production an toàn
- [x] File .env được bảo vệ khỏi truy cập web
- [x] Security keys được tạo bằng cryptographically secure random
- [x] Không sử dụng giá trị mặc định hoặc thử nghiệm

### 4. Cơ sở dữ liệu ✅
- [x] Database schema hoàn chỉnh với tất cả bảng cần thiết
- [x] Foreign key constraints và indexes được thiết lập
- [x] User database với quyền tối thiểu cần thiết
- [x] Script import database tự động

### 5. Cấu hình PHP ✅
- [x] PHP 8.1+ với tất cả extensions cần thiết
- [x] Production settings: memory limit, upload size, execution time
- [x] OPcache được kích hoạt và tối ưu
- [x] Error display tắt, error logging bật

### 6. Bảo mật hệ thống ✅
- [x] HTTPS bắt buộc với SSL certificate
- [x] Chặn truy cập trực tiếp đến file/thư mục nhạy cảm
- [x] Mật khẩu được mã hóa bằng BCrypt/Argon2
- [x] CSRF protection, XSS protection, file upload security

### 7. Kiểm thử luồng nghiệp vụ ✅
- [x] Script test tự động cho tất cả chức năng chính
- [x] Test login, session management, voting process
- [x] Cross-browser compatibility testing
- [x] Basic stress test capability

### 8. Sao lưu và khôi phục ✅
- [x] Script backup tự động cho database và files
- [x] Rollback mechanism hoàn chỉnh
- [x] Backup rotation và cleanup tự động

## 🚀 Triển khai nhanh (Quick Deploy)

```bash
# 1. Clone repository
git clone https://github.com/teophat559/newbvote2025.git
cd newbvote2025

# 2. Chạy script triển khai tự động
chmod +x deploy-production.sh
sudo ./deploy-production.sh

# 3. Cấu hình SSL
sudo certbot --apache -d yourdomain.com

# 4. Kiểm tra hoàn tất
php production-check.php
php security-audit.php
./test-business-flows.sh --quick
```

## 🔧 Cấu hình chi tiết

### Environment Variables (.env)
```env
# Application Environment
APP_ENV=production
APP_URL=https://newbvote2025s.online
APP_NAME="Special Program 2025"

# Database Configuration  
DB_TYPE=mysql
DB_HOST=localhost
DB_NAME=newb_vote2025s
DB_USER=newb_vote2025s_user
DB_PASS=your_secure_generated_password

# Security Keys (cryptographically secure)
ADMIN_SECURITY_KEY=d1248a1c5b42e805bbb279f406d3224acad57abfbae5446d330515e5b6f68283
DATA_ENCRYPTION_KEY=4bb8040e3ee63d2d06782a260af0cfa6bb19c563d54527614740996c398adc04

# Session Security
SESSION_SECURE_COOKIE=1
SESSION_TIMEOUT=3600
MAX_LOGIN_ATTEMPTS=5
LOGIN_TIMEOUT=900

# Production Error Handling
ERROR_DISPLAY=0
ERROR_LOG_PATH=/var/log/php_errors.log

# Regional Settings
TIMEZONE=Asia/Ho_Chi_Minh
```

### PHP Production Configuration
```ini
# Bảo mật
expose_php = Off
display_errors = Off
allow_url_fopen = Off
allow_url_include = Off

# Session Security
session.cookie_secure = On
session.cookie_httponly = On
session.use_strict_mode = On
session.cookie_samesite = Strict

# Performance
memory_limit = 256M
max_execution_time = 30
opcache.enable = On
opcache.validate_timestamps = Off

# Disable dangerous functions
disable_functions = exec,system,shell_exec,passthru,eval
```

## 🛡️ Bảo mật Production

### Security Headers
```apache
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Strict-Transport-Security "max-age=31536000"
Header always set Content-Security-Policy "default-src 'self'..."
```

### File Protection
- `.env`, `config/`, `database/`, `logs/`, `vendor/` không thể truy cập từ web
- Upload files được validate type và size
- Executable files bị block
- Source code files được bảo vệ

### Password Security
- BCrypt/Argon2 hashing
- Minimum complexity requirements
- Rate limiting cho login attempts
- Session regeneration sau login

## 🔍 Scripts và Tools

### 1. Production Readiness Check
```bash
php production-check.php
```
Kiểm tra:
- Code integrity (no debug files)
- Environment configuration
- Security settings
- Directory structure & permissions
- Dependencies
- Database connection
- Apache configuration

### 2. Security Audit
```bash
php security-audit.php
```
Kiểm tra:
- Sensitive file exposure
- Environment security
- PHP security settings
- Session security
- File permissions
- Database security

### 3. Business Flow Testing
```bash
# Quick test
./test-business-flows.sh --quick

# Full test với stress testing
./test-business-flows.sh --stress
```
Kiểm tra:
- Database connection
- Core application endpoints
- Security features
- Session management
- Error handling
- Performance basics
- Cross-browser compatibility

### 4. Backup & Recovery
```bash
# Tạo backup hoàn chỉnh
./backup-recovery.sh backup

# Liệt kê backups
./backup-recovery.sh list

# Khôi phục từ backup
./backup-recovery.sh restore 20241220_143022

# Dọn dẹp backups cũ
./backup-recovery.sh cleanup 30
```

## 📋 Quy trình triển khai từng bước

### Bước 1: Chuẩn bị server
```bash
# Cập nhật system
sudo apt update && sudo apt upgrade -y

# Cài đặt packages cần thiết
sudo apt install -y apache2 mysql-server php8.1 php8.1-mysql \
    php8.1-mbstring php8.1-curl php8.1-gd php8.1-json \
    php8.1-xml php8.1-zip composer certbot
```

### Bước 2: Deploy application
```bash
# Clone và setup
git clone https://github.com/teophat559/newbvote2025.git /var/www/newbvote2025s
cd /var/www/newbvote2025s

# Chạy deploy script
./deploy-production.sh
```

### Bước 3: Cấu hình Apache
```bash
# Copy và enable sites
sudo cp apache/newbvote2025s-*.conf /etc/apache2/sites-available/
sudo a2ensite newbvote2025s-80 newbvote2025s-443
sudo a2enmod rewrite headers ssl
sudo systemctl reload apache2
```

### Bước 4: SSL Certificate
```bash
sudo certbot --apache -d newbvote2025s.online
```

### Bước 5: Verification
```bash
php production-check.php
php security-audit.php
./test-business-flows.sh --quick
```

## 🆘 Troubleshooting

### Database connection failed
```bash
# Kiểm tra service
sudo systemctl status mysql

# Kiểm tra credentials
cat .env | grep DB_

# Test connection
mysql -u newb_vote2025s_user -p newb_vote2025s
```

### Permission denied
```bash
# Fix ownership và permissions
sudo chown -R www-data:www-data /var/www/newbvote2025s
chmod -R 755 /var/www/newbvote2025s
chmod 600 /var/www/newbvote2025s/.env
```

### Apache rewrite not working
```bash
# Enable mod_rewrite
sudo a2enmod rewrite
apache2ctl configtest
sudo systemctl restart apache2
```

## 🔄 Rollback nhanh

Nếu deployment gặp sự cố:
```bash
# 1. Backup trạng thái hiện tại
./backup-recovery.sh backup

# 2. Khôi phục từ backup ổn định cuối
./backup-recovery.sh list
./backup-recovery.sh restore <last_stable_timestamp>

# 3. Restart services
sudo systemctl restart apache2
```

## 📊 Monitoring & Maintenance

### Log locations
- Application: `/var/www/newbvote2025s/logs/`
- PHP errors: `/var/log/php_errors.log`
- Apache: `/var/log/apache2/`

### Performance monitoring
```bash
# CPU, memory usage
htop

# Disk space
df -h

# Apache status
sudo systemctl status apache2

# MySQL status
sudo systemctl status mysql
```

### Automated maintenance
```bash
# Crontab entries
0 2 * * * /var/www/newbvote2025s/backup-recovery.sh backup
0 3 * * 0 /var/www/newbvote2025s/backup-recovery.sh cleanup 30
```

---

## ⚠️ Lưu ý bảo mật quan trọng

1. **Thay đổi mật khẩu admin mặc định** ngay sau deployment đầu tiên
2. **Backup thường xuyên**, đặc biệt trước các thay đổi lớn  
3. **Monitor logs** để phát hiện sớm các vấn đề bảo mật
4. **Cập nhật** hệ điều hành và PHP thường xuyên
5. **Kiểm tra SSL certificate** định kỳ để tránh hết hạn
6. **Review access logs** để phát hiện suspicious activities

## 🎉 Kết luận

Với tất cả các script và configuration đã được chuẩn bị sẵn, hệ thống Newb Vote 2025s đã **SẴN SÀNG CHO TRIỂN KHAI PRODUCTION** với đầy đủ:

- ✅ Cấu trúc mã nguồn sạch sẽ
- ✅ Cấu hình Apache và .env chính xác, an toàn
- ✅ Cơ sở dữ liệu đồng bộ và ổn định
- ✅ PHP 8.1 với extensions cần thiết và OPcache
- ✅ Các biện pháp bảo mật cơ bản và nâng cao
- ✅ Tất cả luồng nghiệp vụ được test tự động
- ✅ Phương án backup và rollback hoàn chỉnh

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