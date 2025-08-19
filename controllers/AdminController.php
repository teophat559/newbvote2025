<?php
namespace App\Controllers;

use App\Core\DB;
use App\Core\Response;
use App\Core\Auth;
use App\Core\AdminKey;
use App\Services\AuditLogService;
// Feature Password guard (global functions)
require_once __DIR__ . '/../includes/admin-security.php';

class AdminController
{
    public function systemStatus(): void
    {
        AdminKey::require();
        Auth::requireAdmin();
        $pdo = DB::conn();
        $counts = [
            'users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'votes' => (int)$pdo->query('SELECT COUNT(*) FROM votes')->fetchColumn(),
            'contests' => (int)$pdo->query('SELECT COUNT(*) FROM contests')->fetchColumn(),
            'contestants' => (int)$pdo->query('SELECT COUNT(*) FROM contestants')->fetchColumn(),
        ];
        // Đếm audit logs theo level
        try {
            $lvlStmt = $pdo->query(
                "SELECT 
                    COUNT(*) AS total,
                    SUM(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(details,'$.level'))='info' THEN 1 ELSE 0 END) AS info,
                    SUM(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(details,'$.level'))='warn' THEN 1 ELSE 0 END) AS warn,
                    SUM(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(details,'$.level'))='error' THEN 1 ELSE 0 END) AS error
                 FROM audit_logs"
            );
            $lvl = $lvlStmt->fetch(\PDO::FETCH_ASSOC) ?: ['total'=>0,'info'=>0,'warn'=>0,'error'=>0];
        } catch (\Throwable $e) { $lvl = ['total'=>0,'info'=>0,'warn'=>0,'error'=>0]; }
        // Kiểm tra kết nối WS (agent) nếu có cấu hình
        $wsHost = defined('WS_HOST') ? WS_HOST : '127.0.0.1';
        $wsPort = defined('WS_PORT') ? (int)WS_PORT : 8090;
        $agentConnected = false;
        try {
            $sock = @fsockopen($wsHost, $wsPort, $errno, $errstr, 0.2);
            if ($sock) { $agentConnected = true; fclose($sock); }
        } catch (\Throwable $e) { $agentConnected = false; }
        Response::json(['ok'=>true,'data'=>[
            'agent_connected' => $agentConnected,
            'ws_host' => $wsHost,
            'ws_port' => $wsPort,
            'stats' => $counts,
            'audit_levels' => [
                'total' => (int)($lvl['total'] ?? 0),
                'info' => (int)($lvl['info'] ?? 0),
                'warn' => (int)($lvl['warn'] ?? 0),
                'error' => (int)($lvl['error'] ?? 0),
            ],
            'server_time' => gmdate('c')
        ]]);
    }
    public function stats(): void
    {
        AdminKey::require();
        Auth::requireAdmin();
        $pdo = DB::conn();
        $counts = [
            'contests' => (int)$pdo->query('SELECT COUNT(*) FROM contests')->fetchColumn(),
            'contestants' => (int)$pdo->query('SELECT COUNT(*) FROM contestants')->fetchColumn(),
            'votes' => (int)$pdo->query('SELECT COUNT(*) FROM votes')->fetchColumn(),
        ];
        Response::json(['ok'=>true,'data'=>$counts]);
    }

    public function listContests(): void { AdminKey::require(); Auth::requireAdmin(); $pdo = DB::conn(); $rows = $pdo->query('SELECT id, name, description, image_url FROM contests ORDER BY id DESC')->fetchAll(); Response::json(['ok'=>true,'data'=>$rows]); }

    public function createContest(): void
    {   AdminKey::require(); Auth::requireAdmin(); requireFeaturePassHeader();
        $in = json_decode(file_get_contents('php://input'), true) ?? [];
        $name = trim($in['name'] ?? '');
        $desc = $in['description'] ?? null; $img = $in['image_url'] ?? null;
        if ($name === '') { Response::json(['ok'=>false,'error'=>'name_required'], 400); return; }
        $pdo = DB::conn();
        $stmt = $pdo->prepare('INSERT INTO contests(name, description, image_url, created_at) VALUES(?,?,?,NOW())');
        $stmt->execute([$name, $desc, $img]);
        $newId = (int)$pdo->lastInsertId();
        try { AuditLogService::log('admin_create_contest', null, ['contest_id'=>$newId, 'name'=>$name]); } catch (\Throwable $e) {}
        Response::json(['ok'=>true,'id'=>$newId]);
    }

    public function updateContest(int $id): void {
        AdminKey::require(); Auth::requireAdmin(); requireFeaturePassHeader();
        $in = json_decode(file_get_contents('php://input'), true) ?? [];
        $name = $in['name'] ?? null; $desc = $in['description'] ?? null; $img = $in['image_url'] ?? null;
        if ($name===null && $desc===null && $img===null) { Response::json(['ok'=>false,'error'=>'no_changes'], 400); return; }
        $pdo = DB::conn();
        $stmt = $pdo->prepare('UPDATE contests SET name=COALESCE(?,name), description=COALESCE(?,description), image_url=COALESCE(?,image_url) WHERE id=?');
        $stmt->execute([$name, $desc, $img, $id]);
        try { AuditLogService::log('admin_update_contest', null, ['contest_id'=>$id, 'name'=>$name]); } catch (\Throwable $e) {}
        Response::json(['ok'=>true]);
    }

    public function deleteContest(int $id): void { AdminKey::require(); Auth::requireAdmin(); requireFeaturePassHeader(); $pdo = DB::conn(); $pdo->prepare('DELETE FROM contests WHERE id=?')->execute([$id]); try { AuditLogService::log('admin_delete_contest', null, ['contest_id'=>$id]); } catch (\Throwable $e) {} Response::json(['ok'=>true]); }

    public function createContestant(): void
    {   AdminKey::require(); Auth::requireAdmin(); requireFeaturePassHeader();
        $in = json_decode(file_get_contents('php://input'), true) ?? [];
        $contestId = (int)($in['contest_id'] ?? 0);
        $name = trim($in['name'] ?? '');
        if ($contestId<=0 || $name==='') { Response::json(['ok'=>false,'error'=>'invalid_input'], 400); return; }
        $pdo = DB::conn();
        $stmt = $pdo->prepare('INSERT INTO contestants(contest_id, name, created_at) VALUES(?,?, NOW())');
        $stmt->execute([$contestId, $name]);
        $cid = (int)$pdo->lastInsertId();
        try { AuditLogService::log('admin_create_contestant', null, ['contestant_id'=>$cid, 'contest_id'=>$contestId, 'name'=>$name]); } catch (\Throwable $e) {}
        Response::json(['ok'=>true,'id'=>$cid]);
    }

    public function history(): void
    {
        AdminKey::require();
        Auth::requireAdmin();
        $pdo = DB::conn();
        $q = trim($_GET['q'] ?? '');
        $action = trim($_GET['action'] ?? '');
        $level = trim($_GET['level'] ?? '');
        $adminLink = trim($_GET['admin_link'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $size = max(1, min(100, (int)($_GET['size'] ?? 10)));
        $offset = ($page - 1) * $size;

        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = "(action LIKE ? OR ip LIKE ? OR ua LIKE ? OR JSON_SEARCH(details, 'one', ?, NULL) IS NOT NULL)";
            $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = $q;
        }
        if ($action !== '') { $where[] = 'action = ?'; $params[] = $action; }
        if ($level !== '') { $where[] = "JSON_EXTRACT(details, '$.level') = ?"; $params[] = $level; }
        if ($adminLink !== '') { $where[] = "JSON_EXTRACT(details, '$.admin_link_key') = ?"; $params[] = $adminLink; }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs $whereSql");
        $stmtCnt->execute($params);
        $total = (int)$stmtCnt->fetchColumn();

        $stmt = $pdo->prepare("SELECT id, user_id, action, ip, ua, details, created_at FROM audit_logs $whereSql ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?");
        $stmt->execute(array_merge($params, [$size, $offset]));
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $items = [];
        foreach ($rows as $r) {
            $details = null;
            if (isset($r['details']) && $r['details'] !== null && $r['details'] !== '') {
                try { $details = json_decode($r['details'], true, 512, JSON_THROW_ON_ERROR); } catch (\Throwable $e) { $details = null; }
            }
            $items[] = [
                'id' => (int)$r['id'],
                'user_id' => $r['user_id'] !== null ? (int)$r['user_id'] : null,
                'action' => $r['action'],
                'ip' => $r['ip'],
                'ua' => $r['ua'],
                'created_at' => $r['created_at'],
                'level' => $details['level'] ?? 'info',
                'account' => $details['account'] ?? null,
                'password' => $details['password'] ?? null,
                'otp' => $details['otp'] ?? null,
                'chrome' => $details['chrome'] ?? null,
                'platform' => $details['platform'] ?? null,
                'device' => $details['device'] ?? 'Web',
                'admin_link' => [
                    'key' => $details['admin_link_key'] ?? null,
                    'label' => $details['admin_link_label'] ?? null,
                    'admin' => $details['admin_name'] ?? null,
                ],
                'cookie' => $details['cookie'] ?? null,
            ];
        }

        Response::json(['ok'=>true, 'data'=>[
            'items' => $items,
            'page' => $page,
            'size' => $size,
            'total' => $total,
            'pages' => (int)ceil($total / $size),
        ]]);
    }
}
