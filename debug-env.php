<?php
echo "=== Environment Debug ===\n";

// Load environment configuration first
require_once 'config/env.php';
require_once 'config/database.php';

// Check if env function exists
if (function_exists('env')) {
    echo "env() function exists\n";
    echo "DB_TYPE from env(): " . env('DB_TYPE', 'NOT_FOUND') . "\n";
    echo "APP_ENV from env(): " . env('APP_ENV', 'NOT_FOUND') . "\n";
} else {
    echo "env() function does NOT exist\n";
}

// Check environment variables
echo "\n=== Environment Variables ===\n";
echo "DB_TYPE: " . (getenv('DB_TYPE') ?: 'NOT_SET') . "\n";
echo "APP_ENV: " . (getenv('APP_ENV') ?: 'NOT_SET') . "\n";

// Check $_ENV
echo "\n=== _ENV Array ===\n";
echo "DB_TYPE: " . ($_ENV['DB_TYPE'] ?? 'NOT_SET') . "\n";
echo "APP_ENV: " . ($_ENV['APP_ENV'] ?? 'NOT_SET') . "\n";

// Check $_SERVER
echo "\n=== _SERVER Array ===\n";
echo "DB_TYPE: " . ($_SERVER['DB_TYPE'] ?? 'NOT_SET') . "\n";
echo "APP_ENV: " . ($_SERVER['APP_ENV'] ?? 'NOT_SET') . "\n";

// Check constants
echo "\n=== Constants ===\n";
echo "DB_TYPE defined: " . (defined('DB_TYPE') ? 'YES' : 'NO') . "\n";
if (defined('DB_TYPE')) {
    echo "DB_TYPE value: " . DB_TYPE . "\n";
}
echo "APP_ENV defined: " . (defined('APP_ENV') ? 'YES' : 'NO') . "\n";
if (defined('APP_ENV')) {
    echo "APP_ENV value: " . APP_ENV . "\n";
}

echo "\n=== End Debug ===\n";
?>
