<?php
// PHP built-in server router: php -S localhost:8000 router.php -t php-version

// Serve existing files as-is
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;

if ($path !== '/' && file_exists($file) && !is_dir($file)) {
    return false; // Let built-in server serve the file
}

// Otherwise, route through index.php for SPA-like URLs
require __DIR__ . '/index.php';
?>
