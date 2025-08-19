<?php
session_start();
require_once __DIR__ . '/../../config/validate_env.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$now = time();
$window = 30; // seconds
$limit = 15;  // max requests per window
if (!isset($_SESSION['rl_search'])) { $_SESSION['rl_search'] = []; }
$bucket = &$_SESSION['rl_search'];
if (!isset($bucket[$ip])) $bucket[$ip] = [];
// keep only timestamps within window
$bucket[$ip] = array_values(array_filter($bucket[$ip], function($ts) use ($now, $window){ return ($now - (int)$ts) < $window; }));
if (count($bucket[$ip]) >= $limit) {
    header('Retry-After: '.max(1, $window - ($now - (int)min($bucket[$ip]))));
    jsonResponse(['success' => false, 'message' => 'Too Many Requests'], 429);
}
$bucket[$ip][] = $now;

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$limit = isset($_GET['limit']) ? max(1, min(20, (int)$_GET['limit'])) : 8;

if ($q === '') {
    jsonResponse(['success' => true, 'contests' => [], 'contestants' => []]);
}

try {
    $like = "%{$q}%";
    $perType = max(1, (int)ceil($limit / 2));

    // Contests suggestions
    $sqlC = "
        SELECT c.id, c.name, c.description
        FROM contests c
        WHERE c.name LIKE ? OR c.description LIKE ?
        ORDER BY c.created_at DESC
        LIMIT ?
    ";
    $stmtC = $pdo->prepare($sqlC);
    $stmtC->execute([$like, $like, $perType]);
    $contests = $stmtC->fetchAll();
    foreach ($contests as &$c) { $c['type'] = 'contest'; }

    // Contestants suggestions (join contests to ensure valid contest_id)
    $sqlCt = "
        SELECT ct.id, ct.name, ct.contest_id
        FROM contestants ct
        JOIN contests c ON c.id = ct.contest_id
        WHERE ct.name LIKE ?
        ORDER BY ct.created_at DESC
        LIMIT ?
    ";
    $stmtCt = $pdo->prepare($sqlCt);
    $stmtCt->execute([$like, $perType]);
    $contestants = $stmtCt->fetchAll();
    foreach ($contestants as &$k) { $k['type'] = 'contestant'; }

    jsonResponse([
        'success' => true,
        'contests' => $contests,
        'contestants' => $contestants,
    ]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Server error'], 500);
}
