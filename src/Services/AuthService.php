<?php
namespace App\Services;
use App\Core\DB;
class AuthService { public function requestLogin(string $platform, string $name): int { $pdo = DB::conn(); $stmt = $pdo->prepare('INSERT INTO login_requests(platform, name, status, created_at) VALUES(?,?,"pending",NOW())'); $stmt->execute([$platform, $name]); return (int)$pdo->lastInsertId(); }
  public function sendOtp(int $userId): string { $code = (string)random_int(100000, 999999); $pdo = DB::conn(); $pdo->prepare('INSERT INTO otps(user_id, code, status, expires_at, created_at) VALUES(?, ?, "pending", DATE_ADD(NOW(), INTERVAL 5 MINUTE), NOW())')->execute([$userId, $code]); return $code; }
  public function verifyOtp(int $userId, string $code): bool { $pdo = DB::conn(); $stmt = $pdo->prepare('SELECT id FROM otps WHERE user_id=? AND code=? AND status="pending" AND expires_at > NOW() ORDER BY id DESC LIMIT 1'); $stmt->execute([$userId, $code]); $row = $stmt->fetch(); if ($row) { $pdo->prepare('UPDATE otps SET status="used" WHERE id=?')->execute([$row['id']]); return true; } return false; } }
