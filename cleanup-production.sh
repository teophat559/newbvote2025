#!/bin/bash

# Production Cleanup Script
# Removes development files and artifacts before production deployment

echo "🧹 Production Cleanup Script"
echo "============================"

# Files to remove (debug, test, development artifacts)
FILES_TO_REMOVE=(
    "debug-env.php"
    "debug-env-only.php" 
    "test-mysql.php"
    "test.php"
    "phpinfo.php"
    "test_db.php"
    "debug.php"
    "composer.lock"
    "package-lock.json"
    "yarn.lock"
    ".DS_Store"
    "Thumbs.db"
    ".vscode"
    ".idea"
    "node_modules"
    "vendor"
)

# Directories to remove
DIRS_TO_REMOVE=(
    "tests"
    "docs" 
    ".vscode"
    ".idea"
    "node_modules"
    "vendor"
)

removed_count=0
kept_count=0

echo "Removing debug and development files..."

for file in "${FILES_TO_REMOVE[@]}"; do
    if [ -f "$file" ] || [ -d "$file" ]; then
        rm -rf "$file"
        echo "✅ Removed: $file"
        ((removed_count++))
    else
        echo "   Already clean: $file"
        ((kept_count++))
    fi
done

echo "Removing development directories..."

for dir in "${DIRS_TO_REMOVE[@]}"; do
    if [ -d "$dir" ]; then
        rm -rf "$dir"
        echo "✅ Removed directory: $dir"
        ((removed_count++))
    else
        echo "   Directory not found: $dir"
        ((kept_count++))
    fi
done

# Clean up JavaScript alerts and TODO comments
echo ""
echo "Checking for development artifacts in code..."

# Find and report JavaScript alerts
ALERT_COUNT=$(grep -r "alert(" --include="*.js" --include="*.php" --include="*.html" . 2>/dev/null | wc -l)
if [ $ALERT_COUNT -gt 0 ]; then
    echo "⚠️  Found $ALERT_COUNT JavaScript alert() calls:"
    grep -r "alert(" --include="*.js" --include="*.php" --include="*.html" . | head -5
    echo "   Consider replacing with proper UI notifications"
fi

# Find and report TODO comments
TODO_COUNT=$(grep -r "TODO:" --include="*.php" --include="*.js" --include="*.html" . 2>/dev/null | wc -l)
if [ $TODO_COUNT -gt 0 ]; then
    echo "⚠️  Found $TODO_COUNT TODO comments:"
    grep -r "TODO:" --include="*.php" --include="*.js" --include="*.html" . | head -3
    echo "   Consider resolving these before production"
fi

# Find and report FIXME comments  
FIXME_COUNT=$(grep -r "FIXME:" --include="*.php" --include="*.js" --include="*.html" . 2>/dev/null | wc -l)
if [ $FIXME_COUNT -gt 0 ]; then
    echo "⚠️  Found $FIXME_COUNT FIXME comments:"
    grep -r "FIXME:" --include="*.php" --include="*.js" --include="*.html" . | head -3
    echo "   These should be fixed before production"
fi

# Clean up log files (keep directory structure)
echo ""
echo "Cleaning log files..."

if [ -d "logs" ]; then
    find logs/ -name "*.log" -type f -delete 2>/dev/null || true
    echo "✅ Cleared log files in logs/"
fi

# Set proper file permissions
echo ""
echo "Setting proper file permissions..."

# Directories should be 755
find . -type d -exec chmod 755 {} \; 2>/dev/null || true
echo "✅ Set directory permissions to 755"

# PHP files should be 644
find . -name "*.php" -exec chmod 644 {} \; 2>/dev/null || true
echo "✅ Set PHP file permissions to 644"

# Shell scripts should be executable
find . -name "*.sh" -exec chmod +x {} \; 2>/dev/null || true
echo "✅ Made shell scripts executable"

# Secure sensitive directories
if [ -d "config" ]; then
    chmod 750 config/
    echo "✅ Secured config directory (750)"
fi

if [ -d "storage" ]; then
    chmod 755 storage/
    echo "✅ Set storage directory permissions (755)"
fi

if [ -d "logs" ]; then
    chmod 755 logs/
    echo "✅ Set logs directory permissions (755)"
fi

# Create .gitignore for production artifacts
if [ ! -f ".gitignore" ]; then
    echo "Creating .gitignore..."
    cat > .gitignore << 'EOF'
# Environment files
.env
.env.local
.env.production

# Logs
logs/*.log
*.log

# Temporary files
*.tmp
*.temp
*.bak
*.backup
*.old

# OS files
.DS_Store
Thumbs.db

# IDE files
.vscode/
.idea/

# Dependencies
node_modules/
vendor/

# Uploads
uploads/*
!uploads/.gitkeep

# Cache
cache/
*.cache

# Database backups
*.sql
*.db
*.sqlite
EOF
    echo "✅ Created .gitignore"
fi

echo ""
echo "============================"
echo "Cleanup Summary:"
echo "• Files/directories processed: $((removed_count + kept_count))"
echo "• Items removed: $removed_count"
echo "• Items already clean: $kept_count"
echo "• JavaScript alerts found: $ALERT_COUNT"
echo "• TODO comments found: $TODO_COUNT"
echo "• FIXME comments found: $FIXME_COUNT"
echo "============================"

if [ $ALERT_COUNT -gt 0 ] || [ $TODO_COUNT -gt 0 ] || [ $FIXME_COUNT -gt 0 ]; then
    echo "⚠️  Manual cleanup recommended for code comments and alerts"
    exit 1
else
    echo "✅ Production cleanup completed successfully"
    exit 0
fi