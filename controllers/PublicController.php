<?php
namespace App\Controllers;

use App\Core\DB;
use App\Core\Response;
use App\Services\AuditLogService;

class PublicController
{
    public function contest(int $id): void
    {
        if (empty($_SESSION['user'])) { Response::json(['ok'=>false,'error'=>'login_required'], 401); return; }
        $pdo = DB::conn();
        $stmt = $pdo->prepare('SELECT id, name, description, image_url, starts_at, ends_at, created_at FROM contests WHERE id=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { Response::json(['ok'=>false,'error'=>'not_found'], 404); return; }
        // audit view (best-effort)
        try { AuditLogService::log('view_contest', $_SESSION['user']['id'] ?? null, ['contest_id'=>$id]); } catch (\Throwable $e) {}
        Response::json(['ok'=>true,'data'=>$row]);
    }

    public function contestants(int $contestId): void
    {
        if (empty($_SESSION['user'])) { Response::json(['ok'=>false,'error'=>'login_required'], 401); return; }
        $pdo = DB::conn();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $size = min(100, max(1, (int)($_GET['size'] ?? 20)));
        $sort = $_GET['sort'] ?? 'votes_desc';
        $q = trim($_GET['q'] ?? '');
        $orderBy = $sort==='name_asc' ? 'ct.name ASC' : ($sort==='name_desc' ? 'ct.name DESC' : 'votes DESC, ct.id ASC');
        $where = 'ct.contest_id=?'; $params = [$contestId];
        if ($q !== '') { $where .= ' AND ct.name LIKE ?'; $params[] = "%$q%"; }
        $offset = ($page-1)*$size;
        $sql = "SELECT ct.id, ct.name, ct.photo_url, COALESCE(COUNT(v.id),0) as votes
                FROM contestants ct
                LEFT JOIN votes v ON v.contestant_id = ct.id
                WHERE $where
                GROUP BY ct.id
                ORDER BY $orderBy
                LIMIT $size OFFSET $offset";
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        $rows = $stmt->fetchAll();
        // audit view contestants
        try { AuditLogService::log('view_contestants', $_SESSION['user']['id'] ?? null, ['contest_id'=>$contestId, 'page'=>$page, 'size'=>$size, 'q'=>$q, 'sort'=>$sort]); } catch (\Throwable $e) {}
        Response::json(['ok'=>true,'data'=>$rows,'page'=>$page,'size'=>$size]);
    }

    public function ranking(int $contestId): void
    {
        $pdo = DB::conn();
        $sql = 'SELECT ct.id as contestant_id, ct.name, COUNT(v.id) as votes
                FROM contestants ct
                LEFT JOIN votes v ON v.contestant_id = ct.id
                WHERE ct.contest_id=?
                GROUP BY ct.id
                ORDER BY votes DESC, ct.id ASC';
        $stmt = $pdo->prepare($sql); $stmt->execute([$contestId]);
        $rows = $stmt->fetchAll();
        try { AuditLogService::log('view_ranking', $_SESSION['user']['id'] ?? null, ['contest_id'=>$contestId]); } catch (\Throwable $e) {}
        Response::json(['ok'=>true,'data'=>$rows]);
    }

    public function rankings(): void
    {
        $pdo = DB::conn();
        $size = min(200, max(1, (int)($_GET['size'] ?? 50)));
        $sql = 'SELECT ct.id as contestant_id, ct.name as contestant_name, cs.name as contest_name, COUNT(v.id) as votes
                FROM contestants ct
                JOIN contests cs ON cs.id = ct.contest_id
                LEFT JOIN votes v ON v.contestant_id = ct.id
                GROUP BY ct.id
                ORDER BY votes DESC, ct.id ASC
                LIMIT ' . $size;
        $rows = $pdo->query($sql)->fetchAll();
        try { AuditLogService::log('view_rankings', $_SESSION['user']['id'] ?? null, ['size'=>$size]); } catch (\Throwable $e) {}
        Response::json(['ok'=>true,'data'=>$rows]);
    }
}
