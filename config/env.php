<?php
// Lightweight dotenv loader and env() helper

if (!function_exists('loadDotEnv')) {
    function loadDotEnv(string $path): void {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            $splitPos = strpos($line, '=');
            if ($splitPos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $splitPos));
            $value = trim(substr($line, $splitPos + 1));
            $len = strlen($value);
            if (($len >= 2 && $value[0] === '"' && substr($value, -1) === '"') || ($len >= 2 && $value[0] === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
            }
            if (!array_key_exists($key, $_SERVER)) {
                $_SERVER[$key] = $value;
            }
            putenv($key . '=' . $value);
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = null) {
        // Priority: $_ENV (from env.local) > $_SERVER (from env.local) > getenv() (from .env.production)
        $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($val === false) {
            $val = null;
        }
        if ($val === null) {
            return $default;
        }
        // Normalize boolean and numeric strings
        $lower = strtolower($val);
        if ($lower === 'true') return true;
        if ($lower === 'false') return false;
        if (is_numeric($val)) return $val + 0;
        return $val;
    }
}

// Attempt to load env files (prefer local development over production)
$possiblePaths = [
    dirname(__DIR__) . '/env.local',       // project root local development (highest priority)
    dirname(__DIR__) . '/.env',            // project root
    __DIR__ . '/.env',                     // config directory
    dirname(__DIR__) . '/.env.production', // project root production (lowest priority)
];
foreach ($possiblePaths as $envPath) {
    loadDotEnv($envPath);
}

// Provide defaults for commonly used constants when not defined yet
if (!defined('APP_ENV')) {
    $appEnv = env('APP_ENV', 'dev');
    define('APP_ENV', is_string($appEnv) ? $appEnv : 'dev');
}
if (!defined('WS_HOST')) {
    $wsHost = env('WS_HOST', '127.0.0.1');
    define('WS_HOST', is_string($wsHost) && $wsHost !== '' ? $wsHost : '127.0.0.1');
}
if (!defined('WS_PORT')) {
    $wsPort = env('WS_PORT', 8090);
    define('WS_PORT', (int) $wsPort ?: 8090);
}

// Environment override removed for production
?>
