<?php
session_start();
require_once __DIR__ . '/../config/validate_env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../src/bootstrap.php';

use App\Core\Security;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$csrfOk = function_exists('checkCSRFToken') ? checkCSRFToken() : true;
if (!$csrfOk) {
    jsonResponse(['success' => false, 'message' => 'Token bảo mật không hợp lệ'], 403);
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$username = trim($input['username'] ?? '');
$password = (string)($input['password'] ?? '');

if ($username === '' || $password === '') {
    jsonResponse(['success' => false, 'message' => 'Thiếu username hoặc password'], 400);
}

// Dùng hàm có sẵn để login
if (!userLogin($username, $password)) {
    jsonResponse(['success' => false, 'message' => 'Tên đăng nhập hoặc mật khẩu không đúng'], 401);
}

// Ensure CSRF token exists
$token = $_SESSION['csrf_token'] ?? '';
if ($token === '') {
    $token = Security::csrfToken();
}

// Lấy thông tin user hiện tại
$user = getCurrentUser();

jsonResponse([
    'success' => true,
    'message' => 'Đăng nhập thành công',
    'data' => [
        'user' => $user ? [
            'id' => $user['id'] ?? null,
            'username' => $user['username'] ?? null,
            'role' => $user['role'] ?? null,
        ] : null,
        'csrf_token' => $token,
        // Trang danh sách cuộc thi/thí sinh sau đăng nhập
        'redirect_url' => '/contests',
    ]
]);
