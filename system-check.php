<?php
/**
 * System Check Script for Newb Vote 2025s
 * This script checks the system health and configuration
 */

// Prevent direct access in production
if (php_sapi_name() !== 'cli' && !isset($_GET['debug'])) {
    http_response_code(403);
    exit('Access denied');
}

echo "🔍 Newb Vote 2025s - System Check\n";
echo "=====================================\n\n";

$errors = [];
$warnings = [];
$success = [];

// 1. Check PHP version
echo "1. Checking PHP version...\n";
if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
    echo "   ✅ PHP " . PHP_VERSION . " (OK)\n";
    $success[] = "PHP version: " . PHP_VERSION;
} else {
    echo "   ❌ PHP " . PHP_VERSION . " (Required: 8.1+)\n";
    $errors[] = "PHP version too old: " . PHP_VERSION;
}

// 2. Check required extensions
echo "\n2. Checking PHP extensions...\n";
$required_extensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'openssl'];
$recommended_extensions = ['curl', 'gd', 'zip', 'xml', 'opcache', 'bcmath'];

foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "   ✅ $ext (Required)\n";
        $success[] = "Extension loaded: $ext";
    } else {
        echo "   ❌ $ext (Required - Missing)\n";
        $errors[] = "Missing required extension: $ext";
    }
}

foreach ($recommended_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "   ✅ $ext (Recommended)\n";
        $success[] = "Extension loaded: $ext";
    } else {
        echo "   ⚠️  $ext (Recommended - Missing)\n";
        $warnings[] = "Missing recommended extension: $ext";
    }
}

// 3. Check file permissions
echo "\n3. Checking file permissions...\n";
$directories = [
    'uploads' => 0777,
    'storage' => 0755,
    'logs' => 0755
];

foreach ($directories as $dir => $required_perms) {
    if (is_dir($dir)) {
        $perms = substr(sprintf('%o', fileperms($dir)), -4);
        if ($perms >= $required_perms) {
            echo "   ✅ $dir ($perms)\n";
            $success[] = "Directory permissions: $dir ($perms)";
        } else {
            echo "   ⚠️  $dir ($perms - Should be $required_perms)\n";
            $warnings[] = "Directory permissions: $dir ($perms)";
        }
    } else {
        echo "   ❌ $dir (Directory not found)\n";
        $errors[] = "Directory not found: $dir";
    }
}

// 4. Check configuration files
echo "\n4. Checking configuration files...\n";
$config_files = [
    'config/config.php',
    'config/database.php',
    'config/session.php',
    'src/bootstrap.php'
];

foreach ($config_files as $file) {
    if (file_exists($file)) {
        echo "   ✅ $file\n";
        $success[] = "Config file exists: $file";
    } else {
        echo "   ❌ $file (Missing)\n";
        $errors[] = "Missing config file: $file";
    }
}

// 5. Check database connection
echo "\n5. Checking database connection...\n";
try {
    // Load environment configuration
    if (file_exists('env.local')) {
        require_once 'env.local';
    }
    require_once 'config/env.php';
    require_once 'config/database.php';

    if (isset($pdo)) {
        $pdo->query('SELECT 1');
        echo "   ✅ Database connection successful\n";
        $success[] = "Database connection successful";

        // Check tables
        $tables = ['users', 'contests', 'contestants', 'votes', 'settings'];
        foreach ($tables as $table) {
            try {
                $pdo->query("SELECT COUNT(*) FROM $table");
                echo "   ✅ Table $table exists\n";
                $success[] = "Table exists: $table";
            } catch (Exception $e) {
                echo "   ❌ Table $table missing\n";
                $errors[] = "Missing table: $table";
            }
        }
    } else {
        echo "   ❌ Database connection failed\n";
        $errors[] = "Database connection failed";
    }
} catch (Exception $e) {
    echo "   ❌ Database error: " . $e->getMessage() . "\n";
    $errors[] = "Database error: " . $e->getMessage();
}

// 6. Check API endpoints
echo "\n6. Checking API endpoints...\n";
$api_files = [
    'api/public/contests.php',
    'api/public/rankings.php',
    'api/admin/contests.php',
    'api/admin/contestants.php',
    'api/admin/users.php'
];

foreach ($api_files as $file) {
    if (file_exists($file)) {
        echo "   ✅ $file\n";
        $success[] = "API file exists: $file";
    } else {
        echo "   ❌ $file (Missing)\n";
        $errors[] = "Missing API file: $file";
    }
}

// 7. Check admin files
echo "\n7. Checking admin files...\n";
$admin_files = [
    'admin/dashboard.php',
    'admin/contests.php',
    'admin/contestants.php',
    'admin/users.php',
    'admin/settings.php'
];

foreach ($admin_files as $file) {
    if (file_exists($file)) {
        echo "   ✅ $file\n";
        $success[] = "Admin file exists: $file";
    } else {
        echo "   ❌ $file (Missing)\n";
        $errors[] = "Missing admin file: $file";
    }
}

// 8. Check public files
echo "\n8. Checking public files...\n";
$public_files = [
    'public/home.php',
    'public/contests.php',
    'public/contest_detail.php',
    'public/rankings.php',
    'public/login.php',
    'public/register.php'
];

foreach ($public_files as $file) {
    if (file_exists($file)) {
        echo "   ✅ $file\n";
        $success[] = "Public file exists: $file";
    } else {
        echo "   ❌ $file (Missing)\n";
        $errors[] = "Missing public file: $file";
    }
}

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 SYSTEM CHECK SUMMARY\n";
echo str_repeat("=", 50) . "\n";

echo "✅ Success: " . count($success) . " checks passed\n";
echo "⚠️  Warnings: " . count($warnings) . " issues found\n";
echo "❌ Errors: " . count($errors) . " critical issues found\n\n";

if (!empty($warnings)) {
    echo "⚠️  WARNINGS:\n";
    foreach ($warnings as $warning) {
        echo "   - $warning\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "❌ CRITICAL ERRORS:\n";
    foreach ($errors as $error) {
        echo "   - $error\n";
    }
    echo "\n";
    echo "🚨 System is NOT ready for production deployment!\n";
} else {
    echo "🎉 System is ready for production deployment!\n";
}

if (empty($errors) && empty($warnings)) {
    echo "\n✨ All checks passed successfully!\n";
    echo "🚀 Your Newb Vote 2025s system is ready to go!\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Check completed at: " . date('Y-m-d H:i:s') . "\n";
?>
