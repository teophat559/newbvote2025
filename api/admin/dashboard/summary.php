<?php
session_start();
require_once __DIR__ . '/../../../config/validate_env.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/admin-security.php';
require_once __DIR__ . '/../../../src/bootstrap.php';
use App\Services\RealtimePublisher;

requireAdminKeyHeaderOrSession();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

try {
    // Totals
    $totals = [
        'users' => (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'contests' => (int) $pdo->query("SELECT COUNT(*) FROM contests")->fetchColumn(),
        'contestants' => (int) $pdo->query("SELECT COUNT(*) FROM contestants")->fetchColumn(),
        'votes' => (int) $pdo->query("SELECT COUNT(*) FROM votes")->fetchColumn(),
    ];

    // Today
    $today = [
        'registrations' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()")->fetchColumn(),
        'votes' => (int) $pdo->query("SELECT COUNT(*) FROM votes WHERE DATE(created_at)=CURDATE()")->fetchColumn(),
    ];

    // Active contests (by time window) - using correct column names
    $stmt = $pdo->query("SELECT COUNT(*) FROM contests c WHERE (c.start_date IS NULL OR c.start_date <= CURDATE()) AND (c.end_date IS NULL OR c.end_date >= CURDATE()) AND c.status = 'active'");
    $active_contests = (int) $stmt->fetchColumn();

    $data = [
        'totals' => $totals,
        'today' => $today,
        'active_contests' => $active_contests,
        'generated_at' => date('c'),
        'version' => APP_VERSION,
    ];

    // Best-effort WS publish system status update
    try {
        (new RealtimePublisher())->publish('system.status.update', [
            'status' => 'ok',
            'active_contests' => $active_contests,
            'totals' => $totals,
            'time' => $data['generated_at'],
        ]);
    } catch (Throwable $e) { /* ignore */ }

    jsonResponse(['success' => true, 'data' => $data]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Internal error'], 500);
}
