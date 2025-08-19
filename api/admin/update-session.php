<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin-security.php';
require_once '../../includes/session-management.php';

// Only admin-key verified can update session status (used by admin UI or bot webhook)
if (!isAdminKeyVerified()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    jsonResponse(['success' => false, 'message' => 'Invalid input'], 400);
}

$sessionId = sanitizeInput($input['session_id'] ?? '');
$status = sanitizeInput($input['status'] ?? '');
$details = $input['details'] ?? '';

if (!$sessionId || !$status) {
    jsonResponse(['success' => false, 'message' => 'Missing required fields'], 400);
}

$allowed = ['pending', 'processing', 'success', 'failed', 'blocked'];
if (!in_array($status, $allowed, true)) {
    jsonResponse(['success' => false, 'message' => 'Invalid status'], 400);
}

$ok = updateSessionStatus($sessionId, $status, $details);
if ($ok) {
    logSessionAction($sessionId, 'admin_update', $details, $status === 'success' ? 'success' : 'info');
    jsonResponse(['success' => true]);
}

jsonResponse(['success' => false, 'message' => 'Update failed'], 500);
?>
