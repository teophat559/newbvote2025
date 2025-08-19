<?php
namespace App\Core;

class Security
{
    public static function init(): void
    {
        date_default_timezone_set('Asia/Phnom_Penh');

        // Determine context (admin vs web) by host or URI
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $isAdmin = (stripos($host, 'admin.') === 0) || str_starts_with($uri, '/admin');

        // Use separate session names and cookie paths
        $sessionName = $isAdmin ? 'ADMINSESSID' : 'WEBSESSID';
        if (session_status() === PHP_SESSION_ACTIVE && session_name() !== $sessionName) {
            // Cannot change name of an active session; close and restart
            session_write_close();
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name($sessionName);
        }

        $cookiePath = $isAdmin ? '/admin' : '/';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookiePath,
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        header("X-Frame-Options: DENY");
        header("X-Content-Type-Options: nosniff");
        header("Referrer-Policy: no-referrer");
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');
        }
        header("Content-Security-Policy: default-src 'self'; img-src 'self' https: data:; style-src 'self' 'unsafe-inline' https:; script-src 'self' 'unsafe-inline' https:; connect-src 'self' ws: wss:");
    }

    private static function csrfKey(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $isAdmin = (stripos($host, 'admin.') === 0) || str_starts_with($uri, '/admin');
        return $isAdmin ? '_csrf_admin' : '_csrf_web';
    }

    public static function csrfToken(): string
    {
        $key = self::csrfKey();
        if (empty($_SESSION[$key])) { $_SESSION[$key] = bin2hex(random_bytes(32)); }
        return $_SESSION[$key];
    }

    public static function enforceCsrf(string $method): void
    {
        if (!in_array($method, ['POST','PUT','PATCH','DELETE'], true)) return;
        $key = self::csrfKey();
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
        if (!$token || $token !== ($_SESSION[$key] ?? null)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['ok'=>false,'error'=>'csrf_failed','server_time'=>gmdate('c')]);
            exit;
        }
    }
}
