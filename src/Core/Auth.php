<?php
namespace App\Core;

class Auth
{
    public static function isAdmin(): bool { return !empty($_SESSION['admin']) && $_SESSION['admin']===true; }
    public static function requireAdmin(): void { if (!self::isAdmin()) { Response::json(['ok'=>false,'error'=>'admin_required'], 401); exit; } }
}
