<?php
// ========================================
// HORIZONS VOTING SYSTEM - CONFIG EXAMPLE
// ========================================
// Sao chép file này thành config.php và chỉnh sửa các thông số

// Application configuration
define('APP_NAME', 'Horizons Voting System');
define('APP_VERSION', '1.0.0');

// ⚠️ QUAN TRỌNG: Thay đổi URL này thành domain thực của bạn
define('APP_URL', 'https://newbvote2025s.online');
define('ADMIN_URL', APP_URL . '/admin');

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 900); // 15 minutes

// File upload settings
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Pagination
define('ITEMS_PER_PAGE', 10);

// Timezone - Thay đổi theo múi giờ của bạn
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Error reporting - Đặt thành 0 khi production
error_reporting(E_ALL);
ini_set('display_errors', 1); // Đặt thành 0 khi production

// Session security
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Đặt thành 1 khi có HTTPS
ini_set('session.use_strict_mode', 1);

// CSRF protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Site settings cache
$site_settings = [];
function getSetting($key, $default = null) {
    global $pdo, $site_settings;

    if (empty($site_settings)) {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $stmt->fetch()) {
            $site_settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    return $site_settings[$key] ?? $default;
}

// Utility functions
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateSlug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
    $string = preg_replace('/[\s-]+/', '-', $string);
    return trim($string, '-');
}

function formatDate($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

function formatNumber($number) {
    return number_format($number, 0, ',', '.');
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Flash messages
function setFlashMessage($type, $message) {
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessages() {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

// Validation functions
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validateUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

function validatePassword($password) {
    return strlen($password) >= 6;
}

// File upload function
function uploadFile($file, $directory = 'uploads/') {
    if (!isset($file['error']) || is_array($file['error'])) {
        return false;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return false;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);

    $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];

    if (!array_key_exists($mime_type, $allowed_types)) {
        return false;
    }

    $extension = $allowed_types[$mime_type];
    $filename = uniqid() . '.' . $extension;
    $upload_path = $directory . $filename;

    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return $upload_path;
    }

    return false;
}

// Activity logging
function logActivity($user_id, $action, $details = '') {
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO user_activity (user_id, action, details, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?)
    ");

    return $stmt->execute([
        $user_id,
        $action,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
}

// Update setting function
function updateSetting($key, $value) {
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = ?
    ");

    return $stmt->execute([$key, $value, $value]);
}

// Get statistics
function getStatistics() {
    global $pdo;

    $stats = [];

    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
    $stats['total_users'] = $stmt->fetch()['count'];

    // Today registrations
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()");
    $stats['today_registrations'] = $stmt->fetch()['count'];

    // Total contests
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM contests");
    $stats['total_contests'] = $stmt->fetch()['count'];

    // Active contests
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM contests WHERE status = 'active'");
    $stats['active_contests'] = $stmt->fetch()['count'];

    // Total votes
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM votes");
    $stats['total_votes'] = $stmt->fetch()['count'];

    return $stats;
}

// Get contests with statistics
function getContests($status = null, $limit = null) {
    global $pdo;

    $where_clause = '';
    $params = [];

    if ($status) {
        $where_clause = 'WHERE c.status = ?';
        $params[] = $status;
    }

    $limit_clause = '';
    if ($limit) {
        $limit_clause = 'LIMIT ?';
        $params[] = $limit;
    }

    $sql = "
        SELECT c.*,
               COUNT(DISTINCT ct.id) as contestant_count,
               COUNT(v.id) as total_votes
        FROM contests c
        LEFT JOIN contestants ct ON c.id = ct.contest_id
        LEFT JOIN votes v ON ct.id = v.contestant_id
        $where_clause
        GROUP BY c.id
        ORDER BY c.created_at DESC
        $limit_clause
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Get top contestants
function getTopContestants($limit = 10) {
    global $pdo;

    $sql = "
        SELECT c.*, ct.name as contest_name, COUNT(v.id) as total_votes
        FROM contestants c
        JOIN contests ct ON c.contest_id = ct.id
        LEFT JOIN votes v ON c.id = v.contestant_id
        WHERE c.status = 'active' AND ct.status = 'active'
        GROUP BY c.id
        ORDER BY total_votes DESC
        LIMIT ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

// Get user activity
function getUserActivity($user_id = null, $limit = 10) {
    global $pdo;

    $where_clause = '';
    $params = [];

    if ($user_id) {
        $where_clause = 'WHERE ua.user_id = ?';
        $params[] = $user_id;
    }

    $params[] = $limit;

    $sql = "
        SELECT ua.*, u.username, u.full_name
        FROM user_activity ua
        LEFT JOIN users u ON ua.user_id = u.id
        $where_clause
        ORDER BY ua.created_at DESC
        LIMIT ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Create notification
function createNotification($user_id, $title, $message, $type = 'info') {
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type)
        VALUES (?, ?, ?, ?)
    ");

    return $stmt->execute([$user_id, $title, $message, $type]);
}

// Generate pagination
function generatePagination($current_page, $total_pages, $base_url) {
    if ($total_pages <= 1) {
        return '';
    }

    $pagination = '<div class="flex items-center justify-between">';
    $pagination .= '<div class="flex items-center space-x-2">';

    // Previous button
    if ($current_page > 1) {
        $prev_url = $base_url . (strpos($base_url, '?') !== false ? '&' : '?') . 'page=' . ($current_page - 1);
        $pagination .= '<a href="' . $prev_url . '" class="px-3 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600">Trước</a>';
    }

    // Page numbers
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);

    for ($i = $start; $i <= $end; $i++) {
        $page_url = $base_url . (strpos($base_url, '?') !== false ? '&' : '?') . 'page=' . $i;
        $active_class = $i == $current_page ? 'bg-primary-600' : 'bg-gray-700 hover:bg-gray-600';
        $pagination .= '<a href="' . $page_url . '" class="px-3 py-2 ' . $active_class . ' text-white rounded-lg">' . $i . '</a>';
    }

    // Next button
    if ($current_page < $total_pages) {
        $next_url = $base_url . (strpos($base_url, '?') !== false ? '&' : '?') . 'page=' . ($current_page + 1);
        $pagination .= '<a href="' . $next_url . '" class="px-3 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600">Sau</a>';
    }

    $pagination .= '</div>';
    $pagination .= '<div class="text-sm text-gray-400">Trang ' . $current_page . ' / ' . $total_pages . '</div>';
    $pagination .= '</div>';

    return $pagination;
}
?>
