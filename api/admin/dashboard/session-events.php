<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/validate_env.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/admin-security.php';
require_once __DIR__ . '/../../../src/bootstrap.php';

use App\Core\DB;

try {
    // Security: allow either verified admin session or X-Admin-Key header
    requireAdminKeyHeaderOrSession();

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $sessionId = sanitizeInput($_GET['session_id'] ?? '');
    if (!$sessionId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing session_id']);
        exit;
    }

    $limit = intval($_GET['limit'] ?? 100);
    if ($limit <= 0 || $limit > 500) $limit = 100;
    $order = strtolower($_GET['order'] ?? 'asc');
    $orderSql = $order === 'desc' ? 'DESC' : 'ASC';

    $pdo = DB::conn();

    // Try JSON search first; fallback to LIKE for broader compatibility
    $sqlJson = "SELECT id, action, details, created_at
                FROM audit_logs
                WHERE (action IN ('admin.login.forwarded','login.status'))
                  AND (JSON_EXTRACT(details, '$.session_id') = :sid)
                ORDER BY created_at $orderSql, id $orderSql
                LIMIT :lim";

    $sqlLike = "SELECT id, action, details, created_at
                FROM audit_logs
                WHERE (action IN ('admin.login.forwarded','login.status'))
                  AND details LIKE :like
                ORDER BY created_at $orderSql, id $orderSql
                LIMIT :lim";

    $stmt = null;
    try {
        $stmt = $pdo->prepare($sqlJson);
        $stmt->bindValue(':sid', $sessionId);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
    } catch (Throwable $e) {
        // Fallback to LIKE if JSON_EXTRACT not supported or details not JSON type
        $stmt = $pdo->prepare($sqlLike);
        $stmt->bindValue(':like', '%"session_id":"' . $sessionId . '"%');
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
    }

    $rows = $stmt->fetchAll();

    $events = [];
    foreach ($rows as $r) {
        $details = [];
        try { $details = json_decode($r['details'] ?? '[]', true) ?: []; } catch (Throwable $e) { $details = []; }
        $events[] = [
            'time' => $r['created_at'] ?? date('c'),
            'type' => $r['action'] ?? 'event',
            'status' => $details['status'] ?? null,
            'details' => $details,
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [ 'events' => $events ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'internal_error']);
}
