<?php
/**
 * Security Audit Script for Newb Vote 2025s Production Environment
 * Run this script to verify security configuration before going live
 */

// Prevent direct access except in CLI or debug mode
if (php_sapi_name() !== 'cli' && !isset($_GET['security_audit'])) {
    http_response_code(403);
    exit('Access denied. Run via CLI or add ?security_audit=1 parameter.');
}

echo "🔒 Newb Vote 2025s - Security Audit\n";
echo "====================================\n\n";

$errors = [];
$warnings = [];
$success = [];

// Load environment
require_once 'config/env.php';

/**
 * 1. SENSITIVE FILE EXPOSURE CHECK
 */
echo "1. Checking for exposed sensitive files...\n";

$sensitiveFiles = [
    '.env' => 'Environment configuration',
    '.env.production' => 'Production environment',
    '.env.local' => 'Local environment',
    'config/database.php' => 'Database configuration',
    '.git/' => 'Git repository',
    'composer.json' => 'Composer configuration',
    'composer.lock' => 'Composer lock file',
];

foreach ($sensitiveFiles as $file => $description) {
    if (file_exists($file)) {
        // Check if file is web-accessible (this is a basic check)
        if (php_sapi_name() !== 'cli') {
            // In web mode, check if we can access these files
            $testUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . 
                      '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/' . $file;
            
            echo "   ⚠️  Sensitive file exists: $file ($description)\n";
            echo "       Test URL access: $testUrl\n";
            $warnings[] = "Sensitive file may be web-accessible: $file";
        } else {
            echo "   ✅ Sensitive file exists but access method not testable via CLI: $file\n";
            $success[] = "Sensitive file present: $file";
        }
    } else {
        echo "   ✅ Sensitive file not found (good): $file\n";
        $success[] = "Sensitive file not exposed: $file";
    }
}

/**
 * 2. ENVIRONMENT SECURITY CHECK
 */
echo "\n2. Checking environment security...\n";

// Check APP_ENV is set to production
$appEnv = env('APP_ENV', 'dev');
if ($appEnv !== 'production') {
    echo "   ❌ APP_ENV is '$appEnv' (should be 'production')\n";
    $errors[] = "APP_ENV not set to production";
} else {
    echo "   ✅ APP_ENV is set to production\n";
    $success[] = "APP_ENV configured for production";
}

// Check HTTPS URL
$appUrl = env('APP_URL', '');
if (strpos($appUrl, 'https://') !== 0) {
    echo "   ❌ APP_URL does not use HTTPS: $appUrl\n";
    $errors[] = "APP_URL should use HTTPS in production";
} else {
    echo "   ✅ APP_URL uses HTTPS: $appUrl\n";
    $success[] = "APP_URL configured with HTTPS";
}

// Check security keys
$securityKeys = [
    'ADMIN_SECURITY_KEY' => 20,
    'DATA_ENCRYPTION_KEY' => 32,
];

foreach ($securityKeys as $key => $minLength) {
    $value = env($key, '');
    $length = strlen($value);
    
    if ($length === 0) {
        echo "   ❌ Security key $key is empty\n";
        $errors[] = "Security key $key is not set";
    } elseif ($length < $minLength) {
        echo "   ❌ Security key $key is too short ($length chars, minimum $minLength)\n";
        $errors[] = "Security key $key is too short";
    } elseif (preg_match('/^(password|123|admin|test|demo)/i', $value)) {
        echo "   ❌ Security key $key appears to use weak/common values\n";
        $errors[] = "Security key $key appears weak";
    } else {
        echo "   ✅ Security key $key has adequate length and complexity\n";
        $success[] = "Security key configured: $key";
    }
}

/**
 * 3. PHP SECURITY SETTINGS CHECK
 */
echo "\n3. Checking PHP security settings...\n";

$phpSettings = [
    'display_errors' => ['expected' => '0', 'critical' => true],
    'log_errors' => ['expected' => '1', 'critical' => false],
    'expose_php' => ['expected' => '0', 'critical' => false],
    'allow_url_fopen' => ['expected' => '0', 'critical' => false],
    'allow_url_include' => ['expected' => '0', 'critical' => true],
    'register_globals' => ['expected' => '0', 'critical' => true],
    'magic_quotes_gpc' => ['expected' => '0', 'critical' => false],
];

foreach ($phpSettings as $setting => $config) {
    $value = ini_get($setting);
    $expected = $config['expected'];
    $critical = $config['critical'];
    
    // Some settings might not exist in newer PHP versions
    if ($value === false && !ini_get($setting)) {
        echo "   ℹ️  Setting $setting not applicable to this PHP version\n";
        continue;
    }
    
    if ($value == $expected) {
        echo "   ✅ $setting = $value (secure)\n";
        $success[] = "PHP setting secure: $setting";
    } else {
        $message = "$setting = $value (should be $expected)";
        if ($critical) {
            echo "   ❌ $message\n";
            $errors[] = "Critical PHP setting: $message";
        } else {
            echo "   ⚠️  $message\n";
            $warnings[] = "PHP setting: $message";
        }
    }
}

/**
 * 4. SESSION SECURITY CHECK
 */
echo "\n4. Checking session security...\n";

if (php_sapi_name() !== 'cli') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

$sessionSettings = [
    'session.cookie_secure' => ['expected' => '1', 'description' => 'Cookies over HTTPS only'],
    'session.cookie_httponly' => ['expected' => '1', 'description' => 'HTTP-only cookies'],
    'session.use_strict_mode' => ['expected' => '1', 'description' => 'Strict session mode'],
    'session.cookie_samesite' => ['expected' => 'Strict', 'description' => 'SameSite cookie protection'],
];

foreach ($sessionSettings as $setting => $config) {
    $value = ini_get($setting);
    $expected = $config['expected'];
    $description = $config['description'];
    
    if ($value == $expected || ($setting === 'session.cookie_samesite' && in_array($value, ['Strict', 'Lax']))) {
        echo "   ✅ $setting = $value ($description)\n";
        $success[] = "Session setting secure: $setting";
    } else {
        if (php_sapi_name() === 'cli') {
            echo "   ⚠️  $setting = $value (should be $expected for HTTPS) - CLI mode detected\n";
            $warnings[] = "Session setting (CLI mode): $setting";
        } else {
            echo "   ❌ $setting = $value (should be $expected) - $description\n";
            $errors[] = "Session setting: $setting should be $expected";
        }
    }
}

/**
 * 5. FILE PERMISSIONS CHECK
 */
echo "\n5. Checking file permissions...\n";

$fileChecks = [
    '.' => ['max_perm' => 0755, 'description' => 'Application root'],
    'config/' => ['max_perm' => 0755, 'description' => 'Configuration directory'],
    'uploads/' => ['max_perm' => 0777, 'description' => 'Upload directory'],
    'storage/' => ['max_perm' => 0755, 'description' => 'Storage directory'],
    'logs/' => ['max_perm' => 0755, 'description' => 'Logs directory'],
];

foreach ($fileChecks as $path => $config) {
    if (file_exists($path)) {
        $perms = fileperms($path);
        $octal = substr(sprintf('%o', $perms), -3);
        $maxPerm = $config['max_perm'];
        $description = $config['description'];
        
        if ($perms & 0002) { // World writable
            echo "   ❌ $path is world-writable ($octal) - security risk\n";
            $errors[] = "World-writable directory: $path";
        } elseif (octdec($octal) <= $maxPerm) {
            echo "   ✅ $path permissions $octal ($description)\n";
            $success[] = "File permissions secure: $path";
        } else {
            echo "   ⚠️  $path permissions $octal may be too permissive ($description)\n";
            $warnings[] = "Permissive permissions: $path";
        }
    } else {
        echo "   ⚠️  Path not found: $path\n";
        $warnings[] = "Missing path: $path";
    }
}

/**
 * 6. DANGEROUS FUNCTIONS CHECK
 */
echo "\n6. Checking for dangerous PHP functions...\n";

$dangerousFunctions = [
    'exec', 'system', 'shell_exec', 'passthru', 'eval', 'create_function',
    'file_get_contents', 'file_put_contents', 'fopen', 'fwrite'
];

$disabledFunctions = explode(',', ini_get('disable_functions'));
$disabledFunctions = array_map('trim', $disabledFunctions);

$foundDangerous = 0;
foreach ($dangerousFunctions as $func) {
    if (in_array($func, $disabledFunctions)) {
        echo "   ✅ Dangerous function disabled: $func\n";
        $success[] = "Dangerous function disabled: $func";
    } elseif (function_exists($func)) {
        echo "   ⚠️  Dangerous function available: $func\n";
        $warnings[] = "Dangerous function available: $func";
        $foundDangerous++;
    }
}

if ($foundDangerous === 0) {
    echo "   ✅ No dangerous functions found enabled\n";
    $success[] = "No dangerous functions enabled";
}

/**
 * 7. DATABASE SECURITY CHECK
 */
echo "\n7. Checking database security...\n";

try {
    require_once 'config/database.php';
    
    if (isset($pdo)) {
        // Check database connection encryption
        $stmt = $pdo->query("SHOW STATUS LIKE 'Ssl_cipher'");
        $sslStatus = $stmt->fetch();
        
        if ($sslStatus && !empty($sslStatus['Value'])) {
            echo "   ✅ Database connection uses SSL: " . $sslStatus['Value'] . "\n";
            $success[] = "Database SSL enabled";
        } else {
            echo "   ⚠️  Database connection may not use SSL encryption\n";
            $warnings[] = "Database SSL not detected";
        }
        
        // Check for default/weak passwords in database
        $dbPass = env('DB_PASS', '');
        if (empty($dbPass) || in_array(strtolower($dbPass), ['password', '123456', 'admin', 'root', ''])) {
            echo "   ❌ Database password appears weak or default\n";
            $errors[] = "Weak database password";
        } else {
            echo "   ✅ Database password appears secure\n";
            $success[] = "Database password secure";
        }
        
    } else {
        echo "   ❌ Database connection not available for security check\n";
        $errors[] = "Cannot check database security";
    }
} catch (Exception $e) {
    echo "   ❌ Database security check failed: " . $e->getMessage() . "\n";
    $errors[] = "Database security check error";
}

/**
 * 8. SUMMARY AND RECOMMENDATIONS
 */
echo "\n" . str_repeat("=", 50) . "\n";
echo "SECURITY AUDIT SUMMARY\n";
echo str_repeat("=", 50) . "\n";

echo "\n✅ SECURE CONFIGURATIONS (" . count($success) . "):\n";
foreach (array_slice($success, 0, 10) as $item) { // Show first 10 to avoid clutter
    echo "   • $item\n";
}
if (count($success) > 10) {
    echo "   • ... and " . (count($success) - 10) . " more\n";
}

if (!empty($warnings)) {
    echo "\n⚠️  SECURITY WARNINGS (" . count($warnings) . "):\n";
    foreach ($warnings as $warning) {
        echo "   • $warning\n";
    }
}

if (!empty($errors)) {
    echo "\n❌ SECURITY ERRORS (" . count($errors) . "):\n";
    foreach ($errors as $error) {
        echo "   • $error\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";

if (empty($errors)) {
    if (empty($warnings)) {
        echo "🔒 SECURITY AUDIT PASSED!\n";
        echo "All security checks passed. The application is secure for production.\n";
    } else {
        echo "⚠️  SECURITY MOSTLY ACCEPTABLE\n";
        echo "Please review and address the " . count($warnings) . " warning(s) above.\n";
    }
} else {
    echo "🚫 SECURITY AUDIT FAILED\n";
    echo "Critical security issues found. Please fix the " . count($errors) . " error(s) before production deployment.\n";
}

echo "\n🔧 SECURITY RECOMMENDATIONS:\n";
echo "1. Use HTTPS everywhere (APP_URL, cookies, redirects)\n";
echo "2. Generate strong, unique security keys (32+ characters)\n";
echo "3. Disable PHP error display in production\n";
echo "4. Enable PHP error logging to secure location\n";
echo "5. Set restrictive file permissions (755 for directories, 644 for files)\n";
echo "6. Use database SSL/TLS encryption\n";
echo "7. Regularly update PHP, Apache/Nginx, and system packages\n";
echo "8. Implement rate limiting for login attempts\n";
echo "9. Use Content Security Policy (CSP) headers\n";
echo "10. Set up automated security monitoring and log analysis\n";

echo "\n📚 ADDITIONAL SECURITY MEASURES:\n";
echo "• Configure fail2ban for brute-force protection\n";
echo "• Set up automated backups with encryption\n";
echo "• Use a Web Application Firewall (WAF)\n";
echo "• Implement database query monitoring\n";
echo "• Regular security audits and penetration testing\n";
echo "• Monitor file integrity (AIDE, Tripwire)\n";
echo "• Use security headers (HSTS, X-Frame-Options, etc.)\n";

echo "\n====================================\n";
?>