<?php
session_start();
require_once __DIR__ . '/../config/config.php';
// Ensure a token exists then return it for SPA usage
jsonResponse(['success' => true, 'csrf_token' => $_SESSION['csrf_token'] ?? '']);
