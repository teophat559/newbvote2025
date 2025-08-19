<?php
// PHP built-in server router for docroot=public
// Usage: php -S 127.0.0.1:8000 router-public.php -t public

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = $path ?: '/';
$docroot = __DIR__ . '/public';
$file = realpath($docroot . $path);

// Serve existing files as-is
if ($path !== '/' && $file && strpos($file, realpath($docroot)) === 0 && is_file($file)) {
    return false; // Let built-in server serve the file
}

// Fallback to front controller
require $docroot . '/index.php';
