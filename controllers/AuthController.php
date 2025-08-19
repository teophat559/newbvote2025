<?php
namespace App\Controllers;

use App\Core\DB;
use App\Core\Response;
use App\Services\AuditLogService;

class AuthController
{
    public function requestLogin(): void
    {
        $in = json_decode(file_get_contents('php://input'), true) ?? [];
        $name = trim($in['name'] ?? '');
        $platform = trim($in['platform'] ?? 'web');
        if ($name==='') { AuditLogService::log('login_request_failed', null, ['reason'=>'name_required'], 'error'); Response::json(['ok'=>false,'error'=>'name_required'], 400); return; }
        // Simple rate limit per IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $key = sha1('login:' . $ip);
        $pdo = DB::conn();
        $pdo->prepare('DELETE FROM rate_limits WHERE key_hash=? AND created_at < DATE_SUB(NOW(), INTERVAL 1 MINUTE)')->execute([$key]);
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM rate_limits WHERE key_hash='{$key}'")->fetchColumn();
        if ($cnt >= 5) { AuditLogService::log('login_request_failed', null, ['reason'=>'rate_limited', 'ip'=>$ip], 'error'); Response::json(['ok'=>false,'error'=>'rate_limited'], 429); return; }
        $pdo->prepare('INSERT INTO rate_limits(key_hash, created_at) VALUES(?, NOW())')->execute([$key]);

        $stmt = $pdo->prepare('INSERT INTO login_requests(platform, name, status, created_at) VALUES(?,?,"pending",NOW())');
        $stmt->execute([$platform, $name]);
        $reqId = (int)$pdo->lastInsertId();
        // audit
        AuditLogService::log('login_request_created', null, [ 'name'=>$name, 'platform'=>$platform, 'request_id'=>$reqId ]);
        Response::json(['ok'=>true,'request_id'=>$reqId]);
    }

    public function status(int $requestId): void
    {
        $pdo = DB::conn();
        $stmt = $pdo->prepare('SELECT status FROM login_requests WHERE id=?');
        $stmt->execute([$requestId]);
        $row = $stmt->fetch();
        if (!$row) { AuditLogService::log('login_status_failed', null, ['request_id'=>$requestId, 'reason'=>'not_found'], 'error'); Response::json(['ok'=>false,'error'=>'not_found'], 404); return; }
        Response::json(['ok'=>true,'status'=>$row['status']]);
    }

    public function sendOtp(): void
    {
        $userId = (int)($_SESSION['user']['id'] ?? 0);
        if ($userId<=0) { AuditLogService::log('otp_failed', null, ['reason'=>'unauthorized'], 'error'); Response::json(['ok'=>false,'error'=>'unauthorized'], 401); return; }
        $code = (string)random_int(100000,999999);
        $pdo = DB::conn();
        $pdo->prepare('INSERT INTO otps(user_id, code, status, expires_at, created_at) VALUES(?, ?, "pending", DATE_ADD(NOW(), INTERVAL 5 MINUTE), NOW())')->execute([$userId, $code]);
        AuditLogService::log('otp_sent', $userId, [ 'otp_len' => strlen($code) ]);
        // In production, don't return the actual OTP code for security
        if (defined('APP_ENV') && APP_ENV === 'production') {
            Response::json(['ok'=>true,'message'=>'OTP sent successfully']);
        } else {
            // Only return code in development/testing
            Response::json(['ok'=>true,'code'=>$code]);
        }
    }

    public function verifyOtp(): void
    {
        $in = json_decode(file_get_contents('php://input'), true) ?? [];
        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $code = trim($in['code'] ?? '');
        if ($userId<=0 || $code==='') { AuditLogService::log('otp_verify_failed', $userId ?: null, ['reason'=>'invalid'], 'error'); Response::json(['ok'=>false,'error'=>'invalid'], 400); return; }
        $pdo = DB::conn();
        $stmt = $pdo->prepare('SELECT id FROM otps WHERE user_id=? AND code=? AND status="pending" AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
        $stmt->execute([$userId, $code]);
        $row = $stmt->fetch();
        if (!$row) { AuditLogService::log('otp_verify_failed', $userId, ['reason'=>'invalid_code'], 'error'); Response::json(['ok'=>false,'error'=>'invalid_code'], 400); return; }
        $pdo->prepare('UPDATE otps SET status="used" WHERE id=?')->execute([$row['id']]);
        AuditLogService::log('otp_verified', $userId, []);
        Response::json(['ok'=>true]);
    }

    public function approveLogin(): void
    {
        $in = json_decode(file_get_contents('php://input'), true) ?? [];
        $requestId = (int)($in['request_id'] ?? 0);
        if ($requestId<=0) { AuditLogService::log('login_approve_failed', null, ['reason'=>'invalid_request_id'], 'error'); Response::json(['ok'=>false,'error'=>'invalid_request_id'], 400); return; }
        $pdo = DB::conn();
        $stmt = $pdo->prepare('UPDATE login_requests SET status="approved" WHERE id=?');
        $stmt->execute([$requestId]);
        // Simplified: create/find user
        $userName = $in['name'] ?? 'User';
        $platform = $in['platform'] ?? 'web';
        $pdo->prepare('INSERT INTO users(name, platform, created_at) VALUES(?,?,NOW())')->execute([$userName, $platform]);
        $userId = (int)$pdo->lastInsertId();
        $_SESSION['user'] = ['id'=>$userId, 'name'=>$userName, 'platform'=>$platform];
        // Audit log
        AuditLogService::log('login_approved', $userId, ['platform'=>$platform, 'request_id'=>$requestId]);
        // Realtime publish (best-effort): notify clients that this request_id is approved
        try {
            $pub = new \App\Services\RealtimePublisher();
            $pub->publish('auth:approved', ['request_id' => $requestId]);
        } catch (\Throwable $e) { /* ignore */ }
        Response::json(['ok'=>true,'user'=>$_SESSION['user']]);
    }

    public function logout(): void
    {
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        unset($_SESSION['user']);
        if ($uid>0) { AuditLogService::log('logout', $uid, []); }
        Response::json(['ok'=>true]);
    }

    public function consumeLogin(): void
    {
        $in = json_decode(file_get_contents('php://input'), true) ?? [];
        $requestId = (int)($in['request_id'] ?? 0);
        $name = $in['name'] ?? 'User';
        $platform = $in['platform'] ?? 'web';
        if ($requestId<=0) { Response::json(['ok'=>false,'error'=>'invalid_request_id'], 400); return; }
        $pdo = DB::conn();
        $stmt = $pdo->prepare('SELECT status FROM login_requests WHERE id=?');
        $stmt->execute([$requestId]);
        $row = $stmt->fetch();
        if (!$row) { AuditLogService::log('login_consume_failed', null, ['request_id'=>$requestId, 'reason'=>'not_found'], 'error'); Response::json(['ok'=>false,'error'=>'not_found'], 404); return; }
        if ($row['status'] !== 'approved') { AuditLogService::log('login_consume_failed', null, ['request_id'=>$requestId, 'reason'=>'not_approved'], 'error'); Response::json(['ok'=>false,'error'=>'not_approved'], 400); return; }
        // Create user session
        $pdo->prepare('INSERT INTO users(name, platform, created_at) VALUES(?,?,NOW())')->execute([$name, $platform]);
        $userId = (int)$pdo->lastInsertId();
        $_SESSION['user'] = ['id'=>$userId, 'name'=>$name, 'platform'=>$platform];
        AuditLogService::log('login_consumed', $userId, ['request_id'=>$requestId, 'platform'=>$platform]);
        Response::json(['ok'=>true,'user'=>$_SESSION['user']]);
    }

    public function me(): void
    {
        $user = $_SESSION['user'] ?? null;
        Response::json(['ok'=>true,'user'=>$user]);
    }
}
