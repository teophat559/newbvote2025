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

$code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$_SESSION['otp_code'] = $code;
$_SESSION['otp_expires_at'] = time() + 300; // 5 minutes

$response = ['ok' => true];
if (defined('APP_ENV') && APP_ENV === 'dev') {
    $response['code'] = $code;
}

echo json_encode($response);
