<?php
namespace App\Controllers;

use App\Core\Response;
use App\Core\AdminKey;
use App\Services\AuditLogService;

class AdminAuthController
{
    public function login(): void
    {
        // Require admin key for admin auth endpoints
        AdminKey::require();
        $in = json_decode(file_get_contents('php://input'), true) ?? [];
        $user = $in['username'] ?? '';
        $pass = $in['password'] ?? '';
    $cfgUser = defined('ADMIN_USER') ? constant('ADMIN_USER') : 'admin';
    $cfgPass = defined('ADMIN_PASS') ? constant('ADMIN_PASS') : 'admin';
    if ($user === $cfgUser && $pass === $cfgPass) {
            $_SESSION['admin'] = true;
            // audit admin login
            try { AuditLogService::log('admin_login', null, ['username'=>$user]); } catch (\Throwable $e) {}
            Response::json(['ok'=>true]); return;
        }
        Response::json(['ok'=>false,'error'=>'invalid_credentials'], 401);
    }

    public function logout(): void
    {
        AdminKey::require();
        $_SESSION['admin'] = false;
        try { AuditLogService::log('admin_logout', null, []); } catch (\Throwable $e) {}
        Response::json(['ok'=>true]);
    }
}
