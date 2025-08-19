<?php
namespace App\Services;
use App\Core\DB;
class RateLimiter { public static function attempt(string $key, int $max, int $windowSeconds): bool { $pdo = DB::conn(); $now = time(); $windowStart = date('Y-m-d H:i:s', $now - $windowSeconds); $pdo->prepare('DELETE FROM rate_limits WHERE key_hash=? AND created_at < ?')->execute([sha1($key), $windowStart]); $stmt = $pdo->prepare('SELECT COUNT(*) FROM rate_limits WHERE key_hash=?'); $stmt->execute([sha1($key)]); $count = (int)$stmt->fetchColumn(); if ($count >= $max) return false; $pdo->prepare('INSERT INTO rate_limits(key_hash, created_at) VALUES(?, NOW())')->execute([sha1($key)]); return true; } }
