<?php
session_start();
require_once __DIR__ . '/../config/validate_env.php';
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/session-management.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isAjaxRequest()) {
    jsonResponse(['success' => false, 'message' => 'Invalid request'], 400);
}

// Allow GET for lightweight polling
$sessionId = $_GET['session_id'] ?? ($_SESSION['social_login_attempt']['session_id'] ?? '');

if (!$sessionId) {
    jsonResponse(['success' => false, 'message' => 'Missing session_id'], 400);
}

$details = getSessionDetails($sessionId);
if (!$details) {
    jsonResponse(['success' => false, 'message' => 'Session not found'], 404);
}

$session = $details['session'];

jsonResponse([
    'success' => true,
    'data' => [
        'session_id' => $session['session_id'],
        'platform' => $session['platform'],
        'username' => $session['username'],
        'status' => $session['status'],
        'details' => $session['details'],
        'created_at' => $session['created_at'],
        'updated_at' => $session['updated_at'],
    ]
]);
?>
