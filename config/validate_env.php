<?php
// Validate required environment variables for production deployment
require_once __DIR__ . '/env.php';

if (!function_exists('require_env_keys')) {
    function require_env_keys(array $keys): void {
        // Skip validation in development mode
        if (defined('APP_ENV') && APP_ENV === 'dev') {
            return;
        }

        $missing = [];
        foreach ($keys as $k) {
            $v = env($k, null);
            // Allow empty DB_PASS for local development
            if ($v === null || ($v === '' && $k !== 'DB_PASS')) {
                $missing[] = $k;
            }
        }
        if (!empty($missing)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Configuration error: missing required environment variables: " . implode(', ', $missing) . "\n";
            echo "Please create and populate .env (based on .env.example) before deploying.";
            exit;
        }
    }
}

// Production-required keys
$requiredKeys = [
    'APP_ENV',
    'APP_URL',
    'APP_NAME',
    'TIMEZONE', 
    'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS',
    'ADMIN_SECURITY_KEY',
];

require_env_keys($requiredKeys);

// Security validation for production
if (defined('APP_ENV') && APP_ENV === 'production') {
    // Validate security keys meet minimum requirements
    $securityKeys = [
        'ADMIN_SECURITY_KEY' => 20,
        'DATA_ENCRYPTION_KEY' => 32
    ];
    
    foreach ($securityKeys as $key => $minLength) {
        $value = env($key, '');
        if (strlen($value) < $minLength) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Security error: $key must be at least $minLength characters long.\n";
            echo "Current length: " . strlen($value) . " characters.\n";
            echo "Please generate a secure key and update your .env file.";
            exit;
        }
    }
    
    // Ensure APP_URL is HTTPS in production
    $appUrl = env('APP_URL', '');
    if (strpos($appUrl, 'https://') !== 0) {
        error_log("[WARN] APP_URL should use HTTPS in production: $appUrl");
    }
    
    // Ensure error display is disabled
    if (env('ERROR_DISPLAY', 0)) {
        error_log("[WARN] ERROR_DISPLAY should be 0 in production");
    }
}

// Soft warnings (not fatal) for recommended keys
$recommended = [
    'DATA_ENCRYPTION_KEY',
    'ERROR_LOG_PATH',
    'WS_HOST',
    'WS_PORT',
];
foreach ($recommended as $rk) {
    if (!env($rk, '')) {
        error_log("[WARN] Missing recommended env key: {$rk}");
    }
}
?>
