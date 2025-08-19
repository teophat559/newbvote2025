<?php
session_start();
require_once __DIR__ . '/../../config/validate_env.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Inputs
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$sort = isset($_GET['sort']) ? trim((string)$_GET['sort']) : 'newest';
$category = isset($_GET['category']) ? trim((string)$_GET['category']) : '';
$tagsCsv = isset($_GET['tags']) ? trim((string)$_GET['tags']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? max(1, min(50, (int)$_GET['per_page'])) : 12;

// Build WHERE conditions
$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(c.name LIKE ? OR c.description LIKE ?)";
    $like = "%{$q}%";
    $params[] = $like; $params[] = $like;
}

// Time-based status mapping if provided
// ongoing: started and not ended; upcoming: starts in future; ended: ended in past
if ($status !== '') {
    $now = date('Y-m-d');
    if ($status === 'ongoing' || $status === 'active') {
        $where[] = "( (c.start_date IS NULL OR c.start_date <= ?) AND (c.end_date IS NULL OR c.end_date >= ?) AND c.status = 'active' )";
        $params[] = $now; $params[] = $now;
    } elseif ($status === 'upcoming') {
        $where[] = "(c.start_date IS NOT NULL AND c.start_date > ?)";
        $params[] = $now;
    } elseif ($status === 'ended') {
        $where[] = "(c.end_date IS NOT NULL AND c.end_date < ?)";
        $params[] = $now;
    } else {
        // Unknown status: ignore
    }
}

// Optional: category/tags filters if columns exist
// Helper cached per request
$__col_cache = [];
$columnExists = function(string $table, string $column) use (&$__col_cache, $pdo): bool {
    $key = $table.'__'.$column;
    if (array_key_exists($key, $__col_cache)) return $__col_cache[$key];
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $stmt->execute([$column]);
        $__col_cache[$key] = (bool)$stmt->fetch();
        return $__col_cache[$key];
    } catch (Exception $e) { $__col_cache[$key] = false; return false; }
};

if ($category !== '' && $columnExists('contests','category')) {
    $where[] = "c.category = ?";
    $params[] = $category;
}

if ($tagsCsv !== '' && $columnExists('contests','tags')) {
    $tags = array_values(array_filter(array_map('trim', explode(',', $tagsCsv))));
    foreach ($tags as $tg) {
        $where[] = "FIND_IN_SET(?, c.tags)";
        $params[] = $tg;
    }
}

$where_sql = '';
if (!empty($where)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where);
}

// Sorting
switch ($sort) {
    case 'popular':
        $order_sql = 'ORDER BY total_votes DESC, c.created_at DESC';
        break;
    case 'ending':
        $order_sql = 'ORDER BY (c.end_date IS NULL) ASC, c.end_date ASC';
        break;
    case 'starting':
        $order_sql = 'ORDER BY (c.start_date IS NULL) ASC, c.start_date ASC';
        break;
    default:
        $order_sql = 'ORDER BY c.created_at DESC';
}

// Count total rows (distinct contests)
$count_sql = "
    SELECT COUNT(*) AS cnt
    FROM contests c
    {$where_sql}
";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total = (int)($stmt->fetchColumn() ?: 0);

// Paging
$offset = ($page - 1) * $per_page;

// Data query with aggregates
$sql = "
    SELECT
        c.*,
        COUNT(DISTINCT ct.id) AS contestant_count,
        COALESCE(SUM(ct.total_votes), 0) AS total_votes
    FROM contests c
    LEFT JOIN contestants ct ON c.id = ct.contest_id
    {$where_sql}
    GROUP BY c.id
    {$order_sql}
    LIMIT ? OFFSET ?
";

$params_data = $params;
$params_data[] = $per_page;
$params_data[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params_data);
$rows = $stmt->fetchAll();

jsonResponse([
    'success' => true,
    'data' => $rows,
    'meta' => [
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'pages' => (int)max(1, ceil($total / $per_page))
    ]
]);
