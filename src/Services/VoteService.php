<?php
namespace App\Services;
use App\Core\DB;

class VoteService {
  public function canVote(int $userId, int $contestId, int $quotaPerDay = 10): bool {
    $pdo = DB::conn();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM votes v JOIN contestants ct ON v.contestant_id=ct.id WHERE v.user_id=? AND ct.contest_id=? AND DATE(v.created_at)=CURDATE()');
    $stmt->execute([$userId, $contestId]);
    return (int)$stmt->fetchColumn() < $quotaPerDay;
  }
  public function recordVote(int $userId, int $contestantId): void {
    $pdo = DB::conn();
    $pdo->prepare('INSERT INTO votes(user_id, contestant_id, created_at) VALUES(?,?,NOW())')->execute([$userId, $contestantId]);
    // audit
    try { AuditLogService::log('vote_cast', $userId, ['contestant_id'=>$contestantId]); } catch (\Throwable $e) {}
  }
  public function tallyContest(int $contestId, int $limit = 25): array {
    $pdo = DB::conn();
    $sql = 'SELECT ct.id as contestant_id, ct.name, COUNT(v.id) as votes FROM contestants ct LEFT JOIN votes v ON v.contestant_id = ct.id WHERE ct.contest_id=? GROUP BY ct.id ORDER BY votes DESC, ct.id ASC LIMIT ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$contestId, $limit]);
    return $stmt->fetchAll();
  }
}
