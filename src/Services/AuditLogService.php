<?php
namespace App\Services;

use App\Core\DB;

class AuditLogService
{
    private static function ip(): string { return $_SERVER['REMOTE_ADDR'] ?? ''; }
    private static function ua(): string { return $_SERVER['HTTP_USER_AGENT'] ?? ''; }

    public static function log(string $action, ?int $userId = null, array $details = [], string $level = 'info'): void
    {
        $pdo = DB::conn();
        $ip = self::ip();
        $ua = self::ua();
        $now = date('Y-m-d H:i:s');
        try {
            // persist level inside details for SQL filtering
            $detailsForDb = $details; $detailsForDb['level'] = $level;
            $stmt = $pdo->prepare('INSERT INTO audit_logs(user_id, action, ip, ua, details, created_at) VALUES(?,?,?,?,?,NOW())');
            $stmt->execute([
                $userId,
                $action,
                $ip,
                $ua,
                json_encode($detailsForDb, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            ]);
            $id = (int)$pdo->lastInsertId();
            // publish WS best-effort
            try {
                (new RealtimePublisher())->publish('audit.log.append', [
                    'id' => $id,
                    'action' => $action,
                    'level' => $level,
                    'user_id' => $userId,
                    'ip' => $ip,
                    'ua' => $ua,
                    'details' => $details,
                    'created_at' => $now,
                    'title' => self::formatTitle($action, $level),
                ]);
            } catch (\Throwable $e) { /* ignore */ }
        } catch (\Throwable $e) { /* ignore DB errors */ }
    }

    private static function formatTitle(string $action, string $level): string
    {
        $emojiLevel = $level === 'error' ? '❌' : ($level === 'warn' ? '⚠️' : 'ℹ️');
        $prefix = 'ℹ️';
        if (strpos($action, 'login') === 0) $prefix = '🔐';
        elseif (strpos($action, 'otp') === 0) $prefix = '🔢';
        elseif (strpos($action, 'vote') === 0) $prefix = '🗳️';
        elseif (strpos($action, 'view') === 0) $prefix = '👁️';
        elseif (strpos($action, 'admin') === 0) $prefix = '🛡️';
        elseif (strpos($action, 'system') === 0) $prefix = '🖥️';

        $label = ucwords(str_replace(['_', '.'], ' ', $action));
        return trim($emojiLevel . ' ' . $prefix . ' ' . $label);
    }
}
