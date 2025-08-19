<?php
/**
 * ADMIN SECURITY SYSTEM - SINGLE LAYER PROTECTION
 * ===============================================
 * Layer 1: Admin Key Verification Only
 */

require_once __DIR__ . '/../config/env.php';
// Admin security key (đọc từ .env nếu có)
define('ADMIN_SECURITY_KEY', env('ADMIN_SECURITY_KEY', 'SP2025_ADMIN_SECURE_KEY_2025'));
// Feature password cho các tính năng nhạy cảm (đọc từ .env nếu có)
define('FEATURE_PASS', env('FEATURE_PASS', ''));

// Session key for admin verification
define('ADMIN_KEY_SESSION', 'admin_key_verified');

/**
 * Check if admin key is verified
 */
function isAdminKeyVerified() {
    return isset($_SESSION[ADMIN_KEY_SESSION]) && $_SESSION[ADMIN_KEY_SESSION] === true;
}

/**
 * Check X-Feature-Pass header for sensitive features
 */
function isFeaturePassHeaderValid(): bool {
    $headers = function_exists('getallheaders') ? array_change_key_case(getallheaders(), CASE_LOWER) : [];
    $pass = $headers['x-feature-pass'] ?? null;
    if (!$pass) {
        // Fallback: query/body
        $pass = $_GET['feature_pass'] ?? $_POST['feature_pass'] ?? null;
    }
    // Nếu FEATURE_PASS chưa cấu hình => coi như không hợp lệ (bắt buộc phải cấu hình)
    if (!FEATURE_PASS) return false;
    return $pass && hash_equals(FEATURE_PASS, $pass);
}

/**
 * Require feature password for sensitive endpoints
 */
function requireFeaturePassHeader(): void {
    if (isFeaturePassHeaderValid()) return;
    header('Content-Type: application/json');
    http_response_code(403);
    $msg = FEATURE_PASS ? 'feature_password_invalid' : 'feature_password_not_configured';
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

/**
 * Check X-Admin-Key header for API requests
 */
function isAdminKeyHeaderValid(): bool {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $key = $headers['X-Admin-Key'] ?? $headers['x-admin-key'] ?? null;
    if (!$key) {
        // Also allow query param fallback for simple clients
        $key = $_GET['admin_key'] ?? $_POST['admin_key'] ?? null;
    }
    return $key && hash_equals(ADMIN_SECURITY_KEY, $key);
}

/**
 * For API endpoints: accept either a verified session or a valid X-Admin-Key header
 */
function requireAdminKeyHeaderOrSession(): void {
    if (isAdminKeyVerified()) {
        return;
    }
    if (isAdminKeyHeaderValid()) {
        // Mark session as verified for subsequent requests
        $_SESSION[ADMIN_KEY_SESSION] = true;
        $_SESSION['admin_key_time'] = time();
        return;
    }
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

/**
 * Verify admin security key
 */
function verifyAdminKey($key) {
    if ($key === ADMIN_SECURITY_KEY) {
        $_SESSION[ADMIN_KEY_SESSION] = true;
        $_SESSION['admin_key_time'] = time();
        return true;
    }
    return false;
}

/**
 * Require admin key verification
 */
function requireAdminKey() {
    if (!isAdminKeyVerified()) {
        // Check if key is expired (24 hours)
        if (isset($_SESSION['admin_key_time']) && (time() - $_SESSION['admin_key_time']) > 86400) {
            unset($_SESSION[ADMIN_KEY_SESSION]);
            unset($_SESSION['admin_key_time']);
        }

        // Redirect to key verification page
        header('Location: ' . APP_URL . '/admin/verify-key');
        exit;
    }
}

/**
 * Require admin key only (no username/password)
 */
function requireAdminAccess() {
    // Only check admin key verification
    requireAdminKey();
}

/**
 * Clear admin key session
 */
function clearAdminKey() {
    unset($_SESSION[ADMIN_KEY_SESSION]);
    unset($_SESSION['admin_key_time']);
}

/**
 * Generate random admin key (for emergency use)
 */
function generateEmergencyKey() {
    return 'EMERGENCY_' . strtoupper(substr(md5(uniqid()), 0, 8));
}

/**
 * Log admin access attempts
 */
function logAdminAccess($action, $ip = null, $user_agent = null) {
    $pdo = App\Core\DB::conn();

    $ip = $ip ?: $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $user_agent ?: $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $stmt = $pdo->prepare("
        INSERT INTO admin_access_log (action, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, NOW())
    ");

    return $stmt->execute([$action, $ip, $user_agent]);
}

/**
 * Check for brute force attempts
 */
function checkBruteForce($ip = null) {
    $pdo = App\Core\DB::conn();

    $ip = $ip ?: $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $time_window = 300; // 5 minutes

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as attempts
        FROM admin_access_log
        WHERE ip_address = ?
        AND action IN ('key_failed', 'login_failed')
        AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");

    $stmt->execute([$ip, $time_window]);
    $result = $stmt->fetch();

    return $result['attempts'] >= 5; // Block after 5 failed attempts
}

/**
 * Block IP if brute force detected
 */
function blockIP($ip = null, $reason = 'Brute force attack') {
    $pdo = App\Core\DB::conn();

    $ip = $ip ?: $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $stmt = $pdo->prepare("
        INSERT INTO blocked_ips (ip_address, reason, blocked_by, expires_at)
        VALUES (?, ?, 0, DATE_ADD(NOW(), INTERVAL 1 HOUR))
        ON DUPLICATE KEY UPDATE
        expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR),
        is_active = 1
    ");

    return $stmt->execute([$ip, $reason]);
}

/**
 * Check if IP is blocked
 */
function isIPBlocked($ip = null) {
    $pdo = App\Core\DB::conn();

    $ip = $ip ?: $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as blocked
        FROM blocked_ips
        WHERE ip_address = ?
        AND is_active = 1
        AND (expires_at IS NULL OR expires_at > NOW())
    ");

    $stmt->execute([$ip]);
    $result = $stmt->fetch();

    return $result['blocked'] > 0;
}
?>
