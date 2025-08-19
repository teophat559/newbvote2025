<?php
session_start();
require_once __DIR__ . '/../../config/validate_env.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/session-management.php';
require_once __DIR__ . '/../../includes/admin-security.php';

requireAdminKeyHeaderOrSession();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$page = max(1, intval($_GET['page'] ?? 1));
$filters = [
    'status' => $_GET['status'] ?? '',
    'platform' => $_GET['platform'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$result = getLoginSessions($page, $filters);
jsonResponse(['success' => true, 'data' => $result]);
