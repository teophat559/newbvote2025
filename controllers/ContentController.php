<?php
namespace App\Controllers;

use App\Core\DB;
use App\Core\Response;
use App\Core\Auth;
use App\Core\AdminKey;

// Feature Password guard (global functions)
require_once __DIR__ . '/../includes/admin-security.php';

class ContentController
{
    public function list(): void
    {
        AdminKey::require();
        Auth::requireAdmin();
        $pdo = DB::conn();
        try {
            $rows = $pdo->query('SELECT id, title, slug, status, updated_at FROM contents ORDER BY id DESC LIMIT 100')->fetchAll(\PDO::FETCH_ASSOC);
            Response::json(['ok'=>true,'data'=>$rows]);
        } catch (\Throwable $e) {
            Response::json(['ok'=>true,'data'=>[], 'note'=>'contents_table_missing'], 200);
        }
    }

    public function create(): void
    {
        AdminKey::require();
        Auth::requireAdmin();
        requireFeaturePassHeader();
        $pdo = DB::conn();
        $in = json_decode(file_get_contents('php://input'), true) ?? [];
        $title = trim($in['title'] ?? '');
        $slug = trim($in['slug'] ?? '');
        $status = trim($in['status'] ?? 'draft');
        if ($title === '' || $slug === '') { Response::json(['ok'=>false,'error'=>'title_and_slug_required'], 400); return; }
        try {
            $stmt = $pdo->prepare('INSERT INTO contents(title, slug, status, created_at, updated_at) VALUES(?,?,?,NOW(),NOW())');
            $stmt->execute([$title, $slug, $status]);
            Response::json(['ok'=>true,'id'=>(int)$pdo->lastInsertId()]);
        } catch (\Throwable $e) {
            Response::json(['ok'=>false,'error'=>'contents_table_missing'], 501);
        }
    }

    public function update(int $id): void
    {
        AdminKey::require();
        Auth::requireAdmin();
        requireFeaturePassHeader();
        $pdo = DB::conn();
        $in = json_decode(file_get_contents('php://input'), true) ?? [];
        $title = $in['title'] ?? null; $slug = $in['slug'] ?? null; $status = $in['status'] ?? null;
        if ($title===null && $slug===null && $status===null) { Response::json(['ok'=>false,'error'=>'no_changes'], 400); return; }
        try {
            $stmt = $pdo->prepare('UPDATE contents SET title=COALESCE(?,title), slug=COALESCE(?,slug), status=COALESCE(?,status), updated_at=NOW() WHERE id=?');
            $stmt->execute([$title, $slug, $status, $id]);
            Response::json(['ok'=>true]);
        } catch (\Throwable $e) {
            Response::json(['ok'=>false,'error'=>'contents_table_missing'], 501);
        }
    }

    public function delete(int $id): void
    {
        AdminKey::require();
        Auth::requireAdmin();
        requireFeaturePassHeader();
        $pdo = DB::conn();
        try {
            $pdo->prepare('DELETE FROM contents WHERE id=?')->execute([$id]);
            Response::json(['ok'=>true]);
        } catch (\Throwable $e) {
            Response::json(['ok'=>false,'error'=>'contents_table_missing'], 501);
        }
    }
}
