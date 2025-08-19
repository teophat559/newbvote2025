<?php
session_start();
require_once __DIR__ . '/../../../config/validate_env.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/admin-security.php';
require_once __DIR__ . '/../../../src/bootstrap.php';

use App\Core\DB;
use App\Services\AuditLogService;

requireAdminKeyHeaderOrSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

try {
    // Create few demo audit logs
    AuditLogService::log('admin_login', 1, ['message' => 'Admin logged in demo'], 'info');
    AuditLogService::log('system.health_check', null, ['message' => 'Health OK'], 'info');
    AuditLogService::log('vote_cast', 2, ['contest_id' => 1, 'contestant_id' => 3, 'message' => 'Demo vote'], 'info');
    AuditLogService::log('admin_settings_update', 1, ['message' => 'Updated feature flag X'], 'warn');

    jsonResponse(['success' => true, 'message' => 'Seeded demo audit logs']);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Internal error'], 500);
}
