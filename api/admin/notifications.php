<?php
session_start();
require_once __DIR__ . '/../../config/validate_env.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-security.php';

requireAdminKeyHeaderOrSession();
// Yêu cầu Feature Password cho khu vực thông báo (nhạy cảm)
requireFeaturePassHeader();

$userId = intval($_GET['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $limit = max(1, min(200, intval($_GET['limit'] ?? 50)));
    if ($userId > 0) {
        $rows = getNotifications($userId, $limit);
    } else {
        // Latest notifications across users
        $stmt = $pdo->prepare("SELECT * FROM notifications ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$limit]);
        $rows = $stmt->fetchAll();
    }
    jsonResponse(['success' => true, 'data' => $rows]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCSRFToken()) {
        jsonResponse(['success' => false, 'message' => 'CSRF invalid'], 403);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $userId = intval($input['user_id'] ?? 0);
    $title = sanitizeInput($input['title'] ?? '');
    $message = $input['message'] ?? '';
    $type = sanitizeInput($input['type'] ?? 'info');
    if (!$title || !$message || $userId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Missing title, message, or user_id'], 400);
    }
    $ok = createNotification($userId, $title, $message, $type);
    jsonResponse(['success' => $ok]);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
