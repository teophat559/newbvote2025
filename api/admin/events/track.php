<?php
session_start();
require_once __DIR__ . '/../../../config/validate_env.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../src/bootstrap.php';

use App\Services\AuditLogService;

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // Basic sanitization
    $type     = trim((string)($input['type'] ?? ''));
    $level    = trim((string)($input['level'] ?? 'info'));
    $platform = trim((string)($input['platform'] ?? ''));
    $username = trim((string)($input['username'] ?? ''));
    $reason   = trim((string)($input['reason'] ?? ''));
    $ts       = (int)($input['ts'] ?? time());

    if ($type === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'type_required']);
        exit;
    }

    // Normalize level
    $allowedLevels = ['info','warn','error'];
    if (!in_array($level, $allowedLevels, true)) $level = 'info';

    // Normalize action with prefixes login.*, otp.*, ws.* and dot-separated segments
    $t = strtolower($type);
    if (strpos($t, 'login_') === 0) {
        $action = 'login.' . str_replace('_', '.', substr($t, 6));
    } elseif (strpos($t, 'otp_') === 0) {
        $action = 'otp.' . str_replace('_', '.', substr($t, 4));
    } elseif (strpos($t, 'ws_') === 0) {
        $action = 'ws.' . str_replace('_', '.', substr($t, 3));
    } else {
        $action = str_replace('_', '.', $t);
    }

    // Do not store sensitive codes/passwords - only flags
    $details = [
        'platform' => $platform,
        'username' => $username,
        'reason'   => $reason,
        'ts'       => $ts,
        'source'   => 'frontend',
        'channel'  => 'login_ui',
        'session_id' => session_id(),
    ];

    // Persist
    AuditLogService::log($action, null, $details, $level);

    echo json_encode(['success' => true]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'internal_error']);
}
