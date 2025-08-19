<?php
// /api/settings/auth
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/../../config/env.php';

header('Content-Type: application/json');

$otpRequired = (bool) env('OTP_REQUIRED', false);

echo json_encode([
    'ok' => true,
    'otp_required' => $otpRequired,
]);
