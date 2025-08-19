<?php
/**
 * Production Readiness Check Script for Newb Vote 2025s
 * Comprehensive validation before VPS deployment
 */

// Prevent direct access in production
if (php_sapi_name() !== 'cli' && !isset($_GET['debug'])) {
    http_response_code(403);
    exit('Access denied');
}

echo "🚀 Newb Vote 2025s - Production Readiness Check\n";
echo "==============================================\n\n";

$errors = [];
$warnings = [];
$success = [];

/**
 * 1. CODE INTEGRITY CHECK
 */
echo "1. Checking code integrity...\n";

// Check for debug/test files that should not exist in production
$debugFiles = [
    'debug-env.php',
    'debug-env-only.php', 
    'test-mysql.php',
    'test.php',
    'phpinfo.php'
];

foreach ($debugFiles as $file) {
    if (file_exists($file)) {
        echo "   ❌ Debug file found: $file\n";
        $errors[] = "Debug file exists: $file";
    } else {
        echo "   ✅ Debug file absent: $file\n";
        $success[] = "No debug file: $file";
    }
}

// Check for demo/placeholder code
$demoPatterns = [
    'alert(' => 'JavaScript alerts (should be replaced with proper UI)',
    'TODO:' => 'TODO comments (should be resolved)',
    'FIXME:' => 'FIXME comments (should be resolved)',
    'XXX:' => 'XXX comments (should be resolved)',
];

foreach ($demoPatterns as $pattern => $description) {
    $found = shell_exec("grep -r '$pattern' . --include='*.php' 2>/dev/null | wc -l");
    $count = (int)trim($found);
    
    if ($count > 0) {
        echo "   ⚠️  Found $count occurrences of '$pattern' ($description)\n";
        $warnings[] = "Found $count occurrences of '$pattern'";
    } else {
        echo "   ✅ No '$pattern' patterns found\n";
        $success[] = "No '$pattern' patterns found";
    }
}

/**
 * 2. ENVIRONMENT CONFIGURATION CHECK
 */
echo "\n2. Checking environment configuration...\n";

// Load environment
require_once 'config/env.php';

// Required production environment variables
$requiredEnvVars = [
    'APP_ENV' => 'production',
    'APP_URL' => null,
    'APP_NAME' => null,
    'DB_HOST' => null,
    'DB_NAME' => null,
    'DB_USER' => null,
    'DB_PASS' => null,
    'ADMIN_SECURITY_KEY' => null,
    'TIMEZONE' => 'Asia/Ho_Chi_Minh'
];

foreach ($requiredEnvVars as $key => $expectedValue) {
    $value = env($key, null);
    if ($value === null) {
        echo "   ❌ Missing required env var: $key\n";
        $errors[] = "Missing environment variable: $key";
    } else {
        if ($expectedValue && $value !== $expectedValue) {
            echo "   ⚠️  Env var $key = '$value' (expected: '$expectedValue')\n";
            $warnings[] = "Environment variable $key has unexpected value";
        } else {
            echo "   ✅ $key = " . (strlen($value) > 20 ? substr($value, 0, 20) . '...' : $value) . "\n";
            $success[] = "Environment variable configured: $key";
        }
    }
}

// Security-critical environment variables
$securityVars = [
    'DATA_ENCRYPTION_KEY' => 32, // minimum length
    'ADMIN_SECURITY_KEY' => 20,
];

foreach ($securityVars as $key => $minLength) {
    $value = env($key, '');
    if (strlen($value) < $minLength) {
        echo "   ❌ Security key $key is too short (minimum $minLength chars)\n";
        $errors[] = "Security key $key is too short";
    } else {
        echo "   ✅ Security key $key has adequate length\n";
        $success[] = "Security key configured: $key";
    }
}

/**
 * 3. SECURITY SETTINGS CHECK
 */
echo "\n3. Checking security settings...\n";

// Error display should be off in production
$errorDisplay = ini_get('display_errors');
if ($errorDisplay) {
    echo "   ❌ Error display is ON (should be OFF in production)\n";
    $errors[] = "Error display is enabled";
} else {
    echo "   ✅ Error display is OFF\n";
    $success[] = "Error display disabled";
}

// Check log errors setting
$logErrors = ini_get('log_errors');
if (!$logErrors) {
    echo "   ⚠️  Error logging is OFF (should be ON)\n";
    $warnings[] = "Error logging is disabled";
} else {
    echo "   ✅ Error logging is ON\n";
    $success[] = "Error logging enabled";
}

// Check session security
if (php_sapi_name() !== 'cli') {
    session_start();
}
$sessionConfig = [
    'session.cookie_secure' => true,
    'session.cookie_httponly' => true,
    'session.use_strict_mode' => true,
];

foreach ($sessionConfig as $setting => $expectedValue) {
    $currentValue = ini_get($setting);
    if ($currentValue != $expectedValue && php_sapi_name() !== 'cli') {
        echo "   ⚠️  $setting = $currentValue (should be $expectedValue for HTTPS)\n";
        $warnings[] = "Session setting $setting not optimal for production";
    } else {
        echo "   ✅ $setting configured properly\n";
        $success[] = "Session setting configured: $setting";
    }
}

/**
 * 4. DIRECTORY STRUCTURE & PERMISSIONS CHECK
 */
echo "\n4. Checking directory structure and permissions...\n";

$directories = [
    'public' => ['required' => true, 'permissions' => 0755],
    'uploads' => ['required' => true, 'permissions' => 0755],
    'storage' => ['required' => true, 'permissions' => 0755],
    'logs' => ['required' => true, 'permissions' => 0755],
    'config' => ['required' => true, 'permissions' => 0755],
    'src' => ['required' => true, 'permissions' => 0755],
];

foreach ($directories as $dir => $config) {
    if (!is_dir($dir)) {
        if ($config['required']) {
            echo "   ❌ Required directory missing: $dir\n";
            $errors[] = "Missing required directory: $dir";
        } else {
            echo "   ⚠️  Optional directory missing: $dir\n";
            $warnings[] = "Missing optional directory: $dir";
        }
    } else {
        $perms = substr(sprintf('%o', fileperms($dir)), -4);
        if (octdec($perms) < $config['permissions']) {
            echo "   ⚠️  Directory $dir has permissions $perms (should be at least " . decoct($config['permissions']) . ")\n";
            $warnings[] = "Directory permissions too restrictive: $dir";
        } else {
            echo "   ✅ Directory $dir exists with permissions $perms\n";
            $success[] = "Directory configured: $dir";
        }
    }
}

// Check .htaccess exists for Apache
if (file_exists('.htaccess')) {
    echo "   ✅ .htaccess file exists\n";
    $success[] = ".htaccess file present";
} else {
    echo "   ⚠️  .htaccess file missing (required for Apache)\n";
    $warnings[] = ".htaccess file missing";
}

/**
 * 5. DEPENDENCIES CHECK
 */
echo "\n5. Checking dependencies...\n";

// Check PHP version
$phpVersion = PHP_VERSION;
if (version_compare($phpVersion, '8.1.0', '>=')) {
    echo "   ✅ PHP version $phpVersion (meets requirement >=8.1.0)\n";
    $success[] = "PHP version adequate: $phpVersion";
} else {
    echo "   ❌ PHP version $phpVersion (requires >=8.1.0)\n";
    $errors[] = "PHP version too old: $phpVersion";
}

// Check required PHP extensions
$requiredExtensions = [
    'pdo', 'pdo_mysql', 'mbstring', 'json', 'openssl', 'curl'
];

foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "   ✅ PHP extension: $ext\n";
        $success[] = "Extension available: $ext";
    } else {
        echo "   ❌ Missing PHP extension: $ext\n";
        $errors[] = "Missing required extension: $ext";
    }
}

// Check composer autoloader
if (file_exists('vendor/autoload.php')) {
    echo "   ✅ Composer autoloader exists\n";
    $success[] = "Composer dependencies installed";
} else {
    echo "   ⚠️  Composer vendor directory missing (run 'composer install --no-dev --optimize-autoloader')\n";
    $warnings[] = "Composer dependencies not installed";
}

/**
 * 6. DATABASE CONNECTION CHECK
 */
echo "\n6. Checking database connection...\n";

try {
    require_once 'config/database.php';
    
    if (isset($pdo)) {
        // Test connection
        $pdo->query('SELECT 1');
        echo "   ✅ Database connection successful\n";
        $success[] = "Database connection working";
        
        // Check required tables
        $requiredTables = [
            'users', 'contests', 'contestants', 'votes', 'settings',
            'login_requests', 'rate_limits', 'otps'
        ];
        
        foreach ($requiredTables as $table) {
            try {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                if ($stmt->fetch()) {
                    echo "   ✅ Table exists: $table\n";
                    $success[] = "Table exists: $table";
                } else {
                    echo "   ❌ Table missing: $table\n";
                    $errors[] = "Missing required table: $table";
                }
            } catch (Exception $e) {
                echo "   ❌ Error checking table $table: " . $e->getMessage() . "\n";
                $errors[] = "Error checking table: $table";
            }
        }
    } else {
        echo "   ❌ Database connection not available\n";
        $errors[] = "Database connection failed";
    }
} catch (Exception $e) {
    echo "   ❌ Database connection failed: " . $e->getMessage() . "\n";
    $errors[] = "Database connection error: " . $e->getMessage();
}

/**
 * 7. WEB SERVER CONFIGURATION CHECK
 */
echo "\n7. Checking web server configuration...\n";

// Check if running via web server
if (php_sapi_name() !== 'cli') {
    // Check document root
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $currentDir = realpath('.');
    $publicDir = realpath('./public');
    
    if ($docRoot && $publicDir) {
        if (strpos($docRoot, 'public') !== false || $docRoot === $publicDir) {
            echo "   ✅ Document root appears to point to public directory\n";
            $success[] = "Document root configured correctly";
        } else {
            echo "   ⚠️  Document root may not point to public directory\n";
            $warnings[] = "Document root configuration unclear";
        }
    } else {
        echo "   ⚠️  Cannot verify document root configuration\n";
        $warnings[] = "Cannot verify document root";
    }
} else {
    echo "   ⚠️  Running via CLI - cannot check web server config\n";
    $warnings[] = "Web server config check skipped (CLI mode)";
}

// Check Apache config files exist
$apacheConfigs = [
    'apache/newbvote2025s-80.conf',
    'apache/newbvote2025s-443.conf',
    'apache/deploy-apache.sh'
];

foreach ($apacheConfigs as $config) {
    if (file_exists($config)) {
        echo "   ✅ Apache config exists: $config\n";
        $success[] = "Apache config file: $config";
    } else {
        echo "   ⚠️  Apache config missing: $config\n";
        $warnings[] = "Missing Apache config: $config";
    }
}

/**
 * 8. FINAL SUMMARY
 */
echo "\n" . str_repeat("=", 50) . "\n";
echo "PRODUCTION READINESS SUMMARY\n";
echo str_repeat("=", 50) . "\n";

echo "\n✅ SUCCESSES (" . count($success) . "):\n";
foreach ($success as $item) {
    echo "   • $item\n";
}

if (!empty($warnings)) {
    echo "\n⚠️  WARNINGS (" . count($warnings) . "):\n";
    foreach ($warnings as $warning) {
        echo "   • $warning\n";
    }
}

if (!empty($errors)) {
    echo "\n❌ ERRORS (" . count($errors) . "):\n";
    foreach ($errors as $error) {
        echo "   • $error\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";

if (empty($errors)) {
    if (empty($warnings)) {
        echo "🎉 READY FOR PRODUCTION DEPLOYMENT!\n";
        echo "All checks passed successfully.\n";
    } else {
        echo "⚠️  MOSTLY READY FOR PRODUCTION\n";
        echo "Please review the " . count($warnings) . " warning(s) above.\n";
    }
} else {
    echo "🚫 NOT READY FOR PRODUCTION\n";
    echo "Please fix the " . count($errors) . " error(s) above before deploying.\n";
}

echo "\n📝 Next steps:\n";
echo "1. Address any errors and warnings shown above\n";
echo "2. Test core functionality (login, admin, voting)\n";
echo "3. Configure SSL certificates on target server\n";
echo "4. Set up proper backup procedures\n";
echo "5. Monitor logs after deployment\n";

echo "\n==============================================\n";
?>