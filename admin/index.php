<?php
// Admin Front Controller
// Routes all /admin requests here and dispatches to the correct admin page

// Bootstrap
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin-security.php';

use App\Core\Security;

// Ensure session / CSRF initialized for admin scope
Security::csrfToken();

$uri = $_SERVER['REQUEST_URI'] ?? '/admin';
$base = '/admin';
$path = parse_url($uri, PHP_URL_PATH) ?: $base;

// Normalize to path relative to /admin
$rel = trim(preg_replace('#^' . preg_quote($base, '#') . '#', '', $path), '/');

// Helper: require admin login/key where needed
$requireAdminKey = function () {
    if (!function_exists('isAdminKeyVerified') || !isAdminKeyVerified()) {
        header('Location: /admin/verify-key');
        exit;
    }
};

switch ($rel) {
    case '':
    case 'dashboard':
        $requireAdminKey();
        require __DIR__ . '/dashboard.php';
        break;

    case 'login':
        require __DIR__ . '/../public/admin-login.php';
        break;

    case 'verify-key':
        require __DIR__ . '/verify-key.php';
        break;

    case 'logout':
        if (function_exists('adminLogout')) { adminLogout(); }
        header('Location: /admin/login');
        break;

    case 'users':
        $requireAdminKey();
        require __DIR__ . '/users.php';
        break;

    case 'contests':
        $requireAdminKey();
        require __DIR__ . '/contests.php';
        break;

    case 'contestants':
        $requireAdminKey();
        require __DIR__ . '/contestants.php';
        break;

    case 'notifications':
        $requireAdminKey();
        require __DIR__ . '/notifications.php';
        break;

    case 'settings':
        $requireAdminKey();
        require __DIR__ . '/settings.php';
        break;

    case 'social-login-management':
        $requireAdminKey();
        require __DIR__ . '/social-login-management.php';
        break;

    case 'session-management':
        $requireAdminKey();
        require __DIR__ . '/session-management.php';
        break;

    case 'session-details':
        $requireAdminKey();
        require __DIR__ . '/session-details.php';
        break;

    case 'activity':
        $requireAdminKey();
        require __DIR__ . '/activity.php';
        break;

    case 'ip-management':
        $requireAdminKey();
        require __DIR__ . '/ip-management.php';
        break;

    default:
        http_response_code(404);
        require __DIR__ . '/../views/pages/404.php';
        break;
}
