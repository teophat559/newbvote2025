<?php
// Front controller
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use App\Core\Router;

$router = new Router();

// Public routes
$router->get('/', [App\Controllers\HomeController::class, 'index']);
$router->get('/contests', [App\Controllers\HomeController::class, 'contests']);
$router->get('/contests/(\d+)', [App\Controllers\HomeController::class, 'contestDetail']);
$router->get('/rankings', [App\Controllers\HomeController::class, 'rankings']);
$router->get('/user/(.+)', [App\Controllers\UserController::class, 'profile']);
$router->get('/user-history', [App\Controllers\UserController::class, 'history']);
$router->get('/login', function(){ require __DIR__ . '/login.php'; });

// Public APIs
$router->get('/api/contest/(\d+)', [App\Controllers\PublicController::class, 'contest']);
$router->get('/api/contest/(\d+)/contestants', [App\Controllers\PublicController::class, 'contestants']);
$router->get('/api/contest/(\d+)/ranking', [App\Controllers\PublicController::class, 'ranking']);
$router->get('/api/rankings', [App\Controllers\PublicController::class, 'rankings']);
$router->get('/api/settings/auth', [App\Controllers\SettingsController::class, 'auth']);

// Auth routes
$router->get('/api/me', [App\Controllers\AuthController::class, 'me']);
$router->post('/api/login/request', [App\Controllers\AuthController::class, 'requestLogin']);
$router->get('/api/login/status/(\d+)', [App\Controllers\AuthController::class, 'status']);
$router->post('/api/login/approve', [App\Controllers\AuthController::class, 'approveLogin']);
$router->post('/api/login/consume', [App\Controllers\AuthController::class, 'consumeLogin']);
$router->post('/api/logout', [App\Controllers\AuthController::class, 'logout']);
$router->post('/api/otp/send', [App\Controllers\AuthController::class, 'sendOtp']);
$router->post('/api/otp/verify', [App\Controllers\AuthController::class, 'verifyOtp']);

// Admin auth
$router->post('/api/admin/login', [App\Controllers\AdminAuthController::class, 'login']);
$router->post('/api/admin/logout', [App\Controllers\AdminAuthController::class, 'logout']);
$router->get('/api/admin/me', function(){ echo App\Core\Response::json(['ok'=>true,'is_admin'=>\App\Core\Auth::isAdmin()]); });

// Admin pages
$router->get('/admin/login', function(){ require __DIR__ . '/admin-login.php'; });

// Voting
$router->post('/api/vote', [App\Controllers\VoteController::class, 'vote']);

// Admin (internal APIs)
$router->get('/api/admin/stats', [App\Controllers\AdminController::class, 'stats']);
$router->get('/api/admin/system-status', [App\Controllers\AdminController::class, 'systemStatus']);
$router->post('/api/admin/contest', [App\Controllers\AdminController::class, 'createContest']);
$router->post('/api/admin/contestant', [App\Controllers\AdminController::class, 'createContestant']);

// Admin contests CRUD
$router->get('/api/admin/contests', [App\Controllers\AdminController::class, 'listContests']);
$router->put('/api/admin/contest/(\d+)', [App\Controllers\AdminController::class, 'updateContest']);
$router->delete('/api/admin/contest/(\d+)', [App\Controllers\AdminController::class, 'deleteContest']);
$router->get('/api/admin/history', [App\Controllers\AdminController::class, 'history']);

// Admin content management (protected by Feature Password on write)
$router->get('/api/admin/contents', [App\Controllers\ContentController::class, 'list']);
$router->post('/api/admin/content', [App\Controllers\ContentController::class, 'create']);
$router->put('/api/admin/content/(\d+)', [App\Controllers\ContentController::class, 'update']);
$router->delete('/api/admin/content/(\d+)', [App\Controllers\ContentController::class, 'delete']);

// Admin page (template)
$router->get('/admin', function() {
    if (!(\App\Core\Auth::isAdmin())) { http_response_code(302); header('Location: /admin/login'); return; }
    echo App\Core\View::renderPartial('pages/admin_dashboard.php');
});
$router->get('/admin/contests', function() {
    if (!(\App\Core\Auth::isAdmin())) { http_response_code(302); header('Location: /admin/login'); return; }
    echo App\Core\View::renderPartial('pages/admin_contests.php');
});
$router->get('/admin/content', function() {
    if (!(\App\Core\Auth::isAdmin())) { http_response_code(302); header('Location: /admin/login'); return; }
    echo App\Core\View::renderPartial('pages/admin_content.php');
});
$router->get('/admin/notifications', function() {
    if (!(\App\Core\Auth::isAdmin())) { http_response_code(302); header('Location: /admin/login'); return; }
    echo App\Core\View::renderPartial('pages/admin_notifications.php');
});

// Admin: User Login Dashboard (new)
$router->get('/admin/login-dashboard', function() {
    if (!(\App\Core\Auth::isAdmin())) { http_response_code(302); header('Location: /admin/login'); return; }
    // Render standalone admin page that includes its own header/footer
    require __DIR__ . '/../admin/login-dashboard.php';
});

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
