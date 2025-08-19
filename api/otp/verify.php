<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';

use App\Core\Security;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

Security::enforceCsrf('POST');

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
$code = isset($input['code']) ? trim((string)$input['code']) : '';

if ($code === '' || !preg_match('/^[0-9]{6,8}$/', $code)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_code']);
    exit;
}

$expected = $_SESSION['otp_code'] ?? null;
$expires = $_SESSION['otp_expires_at'] ?? 0;
if (!$expected || time() > (int)$expires) {
    echo json_encode(['ok' => false, 'error' => 'expired']);
    exit;
}

if (!hash_equals($expected, $code)) {
    echo json_encode(['ok' => false, 'error' => 'mismatch']);
    exit;
}

// success -> clear OTP
unset($_SESSION['otp_code'], $_SESSION['otp_expires_at']);

echo json_encode([
  'ok' => true,
  'data' => [
    'redirect_url' => '/contests'
  ]
]);
