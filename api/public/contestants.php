<?php
session_start();
require_once __DIR__ . '/../../config/validate_env.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$contestId = intval($_GET['contest_id'] ?? 0);
if ($contestId <= 0) {
    jsonResponse(['success' => false, 'message' => 'contest_id is required'], 400);
}

$list = getContestants($contestId);
jsonResponse(['success' => true, 'data' => $list]);
