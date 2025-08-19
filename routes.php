<?php
// Centralized route definitions and dispatcher for SPA-like URLs

function dispatch_route(string $path): void {
    // Normalize path
    $normalized = trim($path, '/');

    // Helper to render public views within layout
    $render_public = function (string $viewFile, array $vars = []) {
        $viewPath = __DIR__ . '/views/pages/' . ltrim($viewFile, '/');
        $layoutPath = __DIR__ . '/views/layouts/public.php';
        if (!is_file($viewPath)) {
            include __DIR__ . '/views/pages/404.php';
            return;
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        include $viewPath;
        $content = ob_get_clean();
        include $layoutPath;
    };

    // Home
    if ($normalized === '' || $normalized === 'home') {
        $render_public('home.php');
        return;
    }

    // Admin area -> redirect to admin front controller
    if (strpos($normalized, 'admin') === 0) {
        $adminPath = trim(substr($normalized, strlen('admin')), '/');
        $target = '/admin' . ($adminPath !== '' ? '/' . $adminPath : '');
        header('Location: ' . $target);
        exit;
    }

    // Public named routes
    switch ($normalized) {
        case 'contests':
            $render_public('contests.php');
            return;
        case 'search':
            // Use contests view for search results as well
            $render_public('contests.php');
            return;
        case 'rankings':
            $render_public('rankings.php');
            return;
        case 'login':
            // Public login is handled via modal in layout, fallback page if needed
            $render_public('home.php');
            return;
        case 'register':
            $render_public('home.php');
            return;
        case 'logout':
            if (function_exists('userLogout')) {
                userLogout();
            }
            header('Location: /');
            exit;
    }

    // Public: contest detail as /contest?id= or pretty /contest/{id} and /contests/{id}
    if ($normalized === 'contest') {
        $contestId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $render_public('contest_detail.php', ['contestId' => $contestId]);
        return;
    }
    if (preg_match('#^contest/(\d+)$#', $normalized, $m) || preg_match('#^contests/(\d+)$#', $normalized, $m)) {
        $contestId = (int)$m[1];
        $render_public('contest_detail.php', ['contestId' => $contestId]);
        return;
    }

    // Public: user profile pretty route /user/{username}
    if (preg_match('#^user/([A-Za-z0-9_\.\-]+)$#', $normalized, $m)) {
        $username = $m[1];
        $user = null;
        if (function_exists('currentUser')) { $user = currentUser(); }
        if (!$user) { $user = ['name' => $username, 'platform' => 'web']; }
        $render_public('user.php', ['user' => $user]);
        return;
    }

    // Fallback 404
    $render_public('404.php');
}

?>
