<?php
namespace App\Controllers;

use App\Core\DB;
use App\Core\Response;
use App\Services\RealtimePublisher;
use App\Services\AuditLogService;

class VoteController
{
    public function vote(): void
    {
        $in = json_decode(file_get_contents('php://input'), true) ?? [];
        $contestantId = (int)($in['contestant_id'] ?? 0);
        $userId = $_SESSION['user']['id'] ?? null;
        if ($contestantId<=0 || !$userId) { try { AuditLogService::log('vote_failed', $userId ? (int)$userId : null, ['reason'=>'unauthorized_or_invalid','contestant_id'=>$contestantId], 'error'); } catch (\Throwable $e) {} Response::json(['ok'=>false,'error'=>'unauthorized_or_invalid'], 401); return; }
        $pdo = DB::conn();
        // Rate limit theo IP: tối đa 30 phiếu trong 1 phút cho cùng một IP
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $keyHash = sha1('vote:ip:' . $ip);
            // Đếm số record trong 1 phút gần nhất
            $cnt = $pdo->prepare('SELECT COUNT(*) FROM rate_limits WHERE key_hash=? AND created_at >= (NOW() - INTERVAL 1 MINUTE)');
            $cnt->execute([$keyHash]);
            if ((int)$cnt->fetchColumn() >= 30) { try { AuditLogService::log('vote_failed', (int)$userId, ['reason'=>'rate_limit_ip'], 'error'); } catch (\Throwable $e) {} Response::json(['ok'=>false,'error'=>'rate_limit_ip'], 429); return; }
            // Ghi nhận lần gọi để tính rate-limit (best-effort)
            $pdo->prepare('INSERT INTO rate_limits(key_hash, created_at) VALUES(?, NOW())')->execute([$keyHash]);
            // Dọn dẹp nhẹ để tránh phình bảng
            $pdo->query('DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL 10 MINUTE)');
        } catch (\Throwable $e) { /* ignore rate-limit storage errors */ }
        // Simple rate limit: max 10 votes per day per user
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM votes WHERE user_id=? AND DATE(created_at)=CURDATE()');
        $stmt->execute([$userId]);
        if ((int)$stmt->fetchColumn() >= 10) { try { AuditLogService::log('vote_failed', (int)$userId, ['reason'=>'vote_limit'], 'error'); } catch (\Throwable $e) {} Response::json(['ok'=>false,'error'=>'vote_limit'], 429); return; }
        // Ensure contestant exists and get its contest
        $cstmt = $pdo->prepare('SELECT contest_id FROM contestants WHERE id=?');
        $cstmt->execute([$contestantId]);
        $crow = $cstmt->fetch();
        if (!$crow) { try { AuditLogService::log('vote_failed', (int)$userId, ['reason'=>'contestant_not_found','contestant_id'=>$contestantId], 'error'); } catch (\Throwable $e) {} Response::json(['ok'=>false,'error'=>'contestant_not_found'], 404); return; }
        $contestId = (int)$crow['contest_id'];

        $pdo->prepare('INSERT INTO votes(user_id, contestant_id, created_at) VALUES(?,?,NOW())')->execute([$userId, $contestantId]);
        // Audit
        try { AuditLogService::log('vote_created', (int)$userId, ['contestant_id'=>$contestantId, 'contest_id'=>$contestId]); } catch (\Throwable $e) {}

        // Compute updated total for this contestant and publish realtime event (best-effort)
        try {
            $tstmt = $pdo->prepare('SELECT COUNT(*) FROM votes WHERE contestant_id=?');
            $tstmt->execute([$contestantId]);
            $total = (int)$tstmt->fetchColumn();
            (new RealtimePublisher())->publish('vote:created', [
                'contest_id' => $contestId,
                'contestant_id' => $contestantId,
                'total' => $total,
            ]);
        } catch (\Throwable $e) { /* ignore publish errors */ }

        Response::json(['ok'=>true]);
    }
}
