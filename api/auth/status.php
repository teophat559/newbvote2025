<?php
session_start();
require_once __DIR__ . '/../../config/validate_env.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../src/bootstrap.php';

use App\Core\Security;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$loggedIn = function_exists('isUserLoggedIn') ? isUserLoggedIn() : false;
$user = $loggedIn && function_exists('getCurrentUser') ? getCurrentUser() : null;

// Ensure CSRF token is available for frontend subsequent POSTs
$csrf = $_SESSION['csrf_token'] ?? '';
if ($csrf === '' && class_exists(Security::class)) {
    $csrf = Security::csrfToken();
}

jsonResponse([
    'ok' => true,
    'logged_in' => $loggedIn,
    'user' => $user ? [
        'id' => $user['id'] ?? null,
        'username' => $user['username'] ?? null,
        'role' => $user['role'] ?? null,
    ] : null,
    'csrf_token' => $csrf,
]);
