<?php
namespace App\Core;

class AdminKey
{
    public static function getKey(): string
    {
        return defined('ADMIN_KEY') ? (string)constant('ADMIN_KEY') : '';
    }

    public static function providedKey(): ?string
    {
        // Prefer header X-Admin-Key; fallback to query admin_key
        $hdr = null;
        // Apache/Nginx fastcgi
        if (isset($_SERVER['HTTP_X_ADMIN_KEY'])) { $hdr = $_SERVER['HTTP_X_ADMIN_KEY']; }
        // Fallback: some servers expose header lowercase
        elseif (function_exists('getallheaders')) {
            $headers = array_change_key_case(getallheaders(), CASE_LOWER);
            $hdr = $headers['x-admin-key'] ?? null;
        }
        return $hdr ?: ($_GET['admin_key'] ?? null);
    }

    public static function require(): void
    {
        $expected = self::getKey();
        $given = self::providedKey();
        if ($expected === '' || $given === null || !hash_equals($expected, (string)$given)) {
            Response::json(['ok'=>false,'error'=>'invalid_or_missing_admin_key'], 401);
            exit;
        }
    }
}
