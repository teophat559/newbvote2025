<?php
namespace App\Controllers;

use App\Core\DB;
use App\Core\View;

class UserController
{
    public function profile(string $name): void
    {
        $pdo = DB::conn();
        $stmt = $pdo->prepare('SELECT id, name, platform, avatar_url FROM users WHERE name=? LIMIT 1');
        $stmt->execute([$name]);
        $user = $stmt->fetch() ?: ['name' => $name, 'platform' => 'web'];
        View::render('pages/user.php', compact('user'));
    }

    public function history(): void
    {
        $uid = $_SESSION['user']['id'] ?? null;
        if (!$uid) { http_response_code(401); echo 'Unauthorized'; return; }
        $pdo = DB::conn();
        $rows = $pdo->prepare('SELECT v.id, v.created_at, c.name AS contest_name, ct.name AS contestant_name
                               FROM votes v
                               JOIN contestants ct ON v.contestant_id=ct.id
                               JOIN contests c ON ct.contest_id=c.id
                               WHERE v.user_id=? ORDER BY v.created_at DESC');
        $rows->execute([$uid]);
        $history = $rows->fetchAll();
        View::render('pages/user_history.php', compact('history'));
    }
}
