<?php
session_start();
require_once __DIR__ . '/../../config/validate_env.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-security.php';

// API auth: allow session or X-Admin-Key header
requireAdminKeyHeaderOrSession();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM blocked_ips ORDER BY created_at DESC LIMIT 1000");
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    if (!checkCSRFToken()) {
        jsonResponse(['success' => false, 'message' => 'CSRF invalid'], 403);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $ip = sanitizeInput($input['ip'] ?? '');
    $reason = sanitizeInput($input['reason'] ?? '');
    if (!$ip || !$reason) {
        jsonResponse(['success' => false, 'message' => 'Missing fields'], 400);
    }
    if (blockIP($ip, $reason)) {
        jsonResponse(['success' => true]);
    }
    jsonResponse(['success' => false, 'message' => 'Failed'], 500);
}

if ($method === 'DELETE') {
    parse_str($_SERVER['QUERY_STRING'] ?? '', $q);
    $ip = sanitizeInput($q['ip'] ?? '');
    if (!$ip) jsonResponse(['success' => false, 'message' => 'ip required'], 400);
    $stmt = $pdo->prepare('DELETE FROM blocked_ips WHERE ip_address = ?');
    $ok = $stmt->execute([$ip]);
    jsonResponse(['success' => $ok]);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
