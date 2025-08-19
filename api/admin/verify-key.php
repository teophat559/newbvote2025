<?php
session_start();
require_once __DIR__ . '/../../config/validate_env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/admin-security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$key = $input['key'] ?? '';

if (!$key) {
    jsonResponse(['success' => false, 'message' => 'Missing key'], 400);
}

if (verifyAdminKey($key)) {
    jsonResponse(['success' => true]);
}

jsonResponse(['success' => false, 'message' => 'Invalid key'], 401);
