<?php
// Configure session before starting it
require_once __DIR__ . '/config/session.php';
// Bootstrap: autoload, env, security headers, CSRF token init
require_once __DIR__ . '/src/bootstrap.php';

// Validate environment before proceeding
require_once __DIR__ . '/config/validate_env.php';
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/admin-security.php';

// Determine request path relative to the app base directory automatically
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = trim($path, '/');

// Compute base path from the executing script location to avoid hardcoded prefixes
$scriptDir = trim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
if ($scriptDir !== '' && strpos($path, $scriptDir) === 0) {
    $path = trim(substr($path, strlen($scriptDir)), '/');
}

// Load route table and dispatch
require_once __DIR__ . '/routes.php';

dispatch_route($path);
?>
