<?php
session_start();
require_once __DIR__ . '/../../../config/validate_env.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/admin-security.php';

requireAdminKeyHeaderOrSession();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
$offset = ($page - 1) * $perPage;
$q = trim($_GET['q'] ?? '');
$action = trim($_GET['action'] ?? '');
$actionPrefix = trim($_GET['action_prefix'] ?? '');
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(action LIKE ? OR ip LIKE ? OR ua LIKE ? OR JSON_EXTRACT(details, '$.message') LIKE ? )";
    $like = "%$q%";
    array_push($params, $like, $like, $like, $like);
}
if ($action !== '') {
    $where[] = "action = ?";
    $params[] = $action;
}
if ($actionPrefix !== '') {
    $where[] = "action LIKE ?";
    $params[] = $actionPrefix . '%';
}
if (!empty($userId)) {
    $where[] = "user_id = ?";
    $params[] = $userId;
}

$whereSql = $where ? ("WHERE " . implode(' AND ', $where)) : '';

// Count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

// Data
$sql = "SELECT id, user_id, action, ip, ua, details, created_at
        FROM audit_logs
        $whereSql
        ORDER BY created_at DESC, id DESC
        LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$bind = $params;
$bind[] = $perPage;
$bind[] = $offset;
$stmt->execute($bind);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

jsonResponse([
    'success' => true,
    'data' => [
        'items' => $rows,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => (int)ceil($total / $perPage)
        ]
    ]
]);
