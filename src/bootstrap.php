<?php
declare(strict_types=1);

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) { return; }
    $relative = substr($class, $len);
    $paths = [
        __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php',
        __DIR__ . '/../' . str_replace('\\', '/', $relative) . '.php', // allow App\Controllers in ../controllers
    ];
    foreach ($paths as $file) { if (file_exists($file)) { require $file; return; } }
});

// Load env
$envPath = __DIR__ . '/../config/env.php';
if (file_exists($envPath)) {
    require_once $envPath;
} else {
    require_once __DIR__ . '/../config/env.sample.php';
}

// Configure error reporting/logging early (affects pages that only include bootstrap)
if (!function_exists('env')) {
    // In case env() is not available, provide sane defaults
    function env(string $k, $d = null) { return $d; }
}

$errorDisplay = env('ERROR_DISPLAY', 0) ? 1 : 0;
$defaultLog = dirname(__DIR__) . '/logs/php_errors.log';
$errorLogPath = (string) (env('ERROR_LOG_PATH', $defaultLog));

// Ensure logs directory exists to avoid logging failures
$logDir = dirname($errorLogPath);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

error_reporting(E_ALL);
ini_set('display_errors', (string)$errorDisplay);
ini_set('log_errors', '1');
ini_set('error_log', $errorLogPath);

// Security init (timezone, headers, session)
App\Core\Security::init();

// Ensure CSRF is set
App\Core\Security::csrfToken();
