<?php
// ========================================
// SECURITY WARNING: DO NOT EXPOSE THIS FILE
// ========================================
// This file contains sensitive configuration data
// Keep this file secure and do not commit to public repositories

require_once __DIR__ . '/env.php';
// Ensure env is valid in production
require_once __DIR__ . '/validate_env.php';

// Application configuration
define('APP_NAME', env('APP_NAME', 'Special Program 2025'));
define('APP_VERSION', '1.0.0');
define('APP_URL', rtrim(env('APP_URL', 'http://localhost'), '/'));
define('ADMIN_URL', APP_URL . '/admin');

// CORS allowlist for API endpoints
// Applied only when the request path starts with /api/
$__req_uri = $_SERVER['REQUEST_URI'] ?? '/';
if (preg_match('#^/api/#', parse_url($__req_uri, PHP_URL_PATH) ?: '')) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    // Allowlist: from env ALLOWED_ORIGINS (comma-separated) or sensible defaults
    $envAllowed = env('ALLOWED_ORIGINS', 'http://localhost,http://localhost:8000');
    $allowedOrigins = array_filter(array_map('trim', explode(',', (string)$envAllowed)));
    // If APP_URL or ADMIN_URL present, add them
    $allowedOrigins[] = rtrim(APP_URL, '/');
    // Heuristic admin origin if subdomain in use (optional)
    $parsed = parse_url(APP_URL);
    if ($parsed && !empty($parsed['host'])) {
        $scheme = ($parsed['scheme'] ?? 'http');
        $adminOrigin = $scheme . '://admin.' . $parsed['host'];
        $allowedOrigins[] = $adminOrigin;
    }
    $allowedOrigins = array_values(array_unique($allowedOrigins));

    if ($origin && in_array($origin, $allowedOrigins, true)) {
        header("Access-Control-Allow-Origin: $origin");
        header('Vary: Origin');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// Security settings
define('SESSION_TIMEOUT', (int) env('SESSION_TIMEOUT', 3600)); // 1 hour
define('MAX_LOGIN_ATTEMPTS', (int) env('MAX_LOGIN_ATTEMPTS', 5));
define('LOGIN_TIMEOUT', (int) env('LOGIN_TIMEOUT', 900)); // 15 minutes

// File upload settings
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Pagination
define('ITEMS_PER_PAGE', 10);

// Timezone
date_default_timezone_set(env('TIMEZONE', 'Asia/Ho_Chi_Minh'));

// Error reporting (PRODUCTION SETTINGS)
error_reporting(E_ALL);
ini_set('display_errors', env('ERROR_DISPLAY', 0) ? 1 : 0); // 0 for production
ini_set('log_errors', 1);
ini_set('error_log', env('ERROR_LOG_PATH', '/var/log/php_errors.log'));

// Session security (already set at top of file)

// CSRF protection
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Site settings cache
$site_settings = [];
function getSetting($key, $default = null) {
    $pdo = App\Core\DB::conn();
    global $site_settings;

    if (empty($site_settings)) {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $stmt->fetch()) {
            $site_settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    return $site_settings[$key] ?? $default;
}

// Utility functions
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateSlug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
    $string = preg_replace('/[\s-]+/', '-', $string);
    return trim($string, '-');
}

function formatDate($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

function formatNumber($number) {
    return number_format($number, 0, ',', '.');
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Flash messages
function setFlashMessage($type, $message) {
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessages() {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

// Validation functions
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validateUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

function validatePassword($password) {
    return strlen($password) >= 6;
}

// File upload function
function uploadFile($file, $directory = 'uploads/') {
    if (!isset($file['error']) || is_array($file['error'])) {
        return false;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return false;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);

    $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];

    if (!array_key_exists($mime_type, $allowed_types)) {
        return false;
    }

    $extension = $allowed_types[$mime_type];
    $filename = uniqid() . '.' . $extension;
    $filepath = $directory . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return false;
    }

    return $filename;
}

// Activity logging
function logActivity($user_id, $action, $details = '') {
    $pdo = App\Core\DB::conn();

    $stmt = $pdo->prepare("
        INSERT INTO user_activity (user_id, action, details, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");

    return $stmt->execute([
        $user_id,
        $action,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
}

// Security functions
function generateSecureToken() {
    return bin2hex(random_bytes(32));
}

function verifySecureToken($token, $stored_token) {
    return hash_equals($stored_token, $token);
}

// Rate limiting
function checkRateLimit($key, $max_attempts = 5, $timeout = 300) {
    $current_time = time();
    $attempts = $_SESSION['rate_limit'][$key] ?? [];

    // Remove old attempts
    $attempts = array_filter($attempts, function($time) use ($current_time, $timeout) {
        return ($current_time - $time) < $timeout;
    });

    if (count($attempts) >= $max_attempts) {
        return false;
    }

    $attempts[] = $current_time;
    $_SESSION['rate_limit'][$key] = $attempts;

    return true;
}

// ========================================
// END OF CONFIGURATION FILE
// ========================================
?>
