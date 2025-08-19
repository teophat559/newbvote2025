<?php
/**
 * Session Management & Logging System
 * Hệ thống quản lý phiên và log chi tiết cho social login
 */

/**
 * Tạo phiên đăng nhập mới
 */
function createLoginSession($platform, $username, $user_ip, $user_agent) {
    global $pdo;

    $session_id = generateSessionId();
    $device_info = getDeviceInfo($user_agent);

    $stmt = $pdo->prepare("
        INSERT INTO login_sessions (
            session_id, platform, username, user_ip, user_agent,
            device_type, browser, os, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");

    $stmt->execute([
        $session_id,
        $platform,
        $username,
        $user_ip,
        $user_agent,
        $device_info['device_type'],
        $device_info['browser'],
        $device_info['os']
    ]);

    return $session_id;
}

/**
 * Cập nhật trạng thái phiên
 */
function updateSessionStatus($session_id, $status, $details = '') {
    global $pdo;

    $stmt = $pdo->prepare("
        UPDATE login_sessions
        SET status = ?, details = ?, updated_at = NOW()
        WHERE session_id = ?
    ");

    return $stmt->execute([$status, $details, $session_id]);
}

/**
 * Lưu log hành động
 */
function logSessionAction($session_id, $action, $details = '', $status = 'info') {
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO session_logs (
            session_id, action, details, status, created_at
        ) VALUES (?, ?, ?, ?, NOW())
    ");

    return $stmt->execute([$session_id, $action, $details, $status]);
}

/**
 * Lấy thông tin thiết bị từ User-Agent
 */
function getDeviceInfo($user_agent) {
    $device_type = 'desktop';
    $browser = 'unknown';
    $os = 'unknown';

    // Detect device type
    if (preg_match('/(android|iphone|ipad|mobile)/i', $user_agent)) {
        $device_type = 'mobile';
    } elseif (preg_match('/(tablet|ipad)/i', $user_agent)) {
        $device_type = 'tablet';
    }

    // Detect browser
    if (preg_match('/chrome/i', $user_agent)) {
        $browser = 'Chrome';
    } elseif (preg_match('/firefox/i', $user_agent)) {
        $browser = 'Firefox';
    } elseif (preg_match('/safari/i', $user_agent)) {
        $browser = 'Safari';
    } elseif (preg_match('/edge/i', $user_agent)) {
        $browser = 'Edge';
    } elseif (preg_match('/opera/i', $user_agent)) {
        $browser = 'Opera';
    }

    // Detect OS
    if (preg_match('/windows/i', $user_agent)) {
        $os = 'Windows';
    } elseif (preg_match('/macintosh|mac os/i', $user_agent)) {
        $os = 'macOS';
    } elseif (preg_match('/linux/i', $user_agent)) {
        $os = 'Linux';
    } elseif (preg_match('/android/i', $user_agent)) {
        $os = 'Android';
    } elseif (preg_match('/iphone|ipad/i', $user_agent)) {
        $os = 'iOS';
    }

    return [
        'device_type' => $device_type,
        'browser' => $browser,
        'os' => $os
    ];
}

/**
 * Tạo Session ID duy nhất
 */
function generateSessionId() {
    return uniqid('session_', true) . '_' . time();
}

/**
 * Lấy danh sách phiên đăng nhập
 */
function getLoginSessions($page = 1, $filters = []) {
    global $pdo;

    $where_conditions = [];
    $params = [];

    if (!empty($filters['platform'])) {
        $where_conditions[] = "platform = ?";
        $params[] = $filters['platform'];
    }

    if (!empty($filters['status'])) {
        $where_conditions[] = "status = ?";
        $params[] = $filters['status'];
    }

    if (!empty($filters['username'])) {
        $where_conditions[] = "username LIKE ?";
        $params[] = '%' . $filters['username'] . '%';
    }

    if (!empty($filters['date_from'])) {
        $where_conditions[] = "DATE(created_at) >= ?";
        $params[] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $where_conditions[] = "DATE(created_at) <= ?";
        $params[] = $filters['date_to'];
    }

    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }

    // Get total count
    $count_sql = "SELECT COUNT(*) FROM login_sessions $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

    // Get sessions
    $offset = ($page - 1) * ITEMS_PER_PAGE;
    $sql = "SELECT * FROM login_sessions $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = ITEMS_PER_PAGE;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sessions = $stmt->fetchAll();

    return [
        'sessions' => $sessions,
        'total' => $total,
        'total_pages' => ceil($total / ITEMS_PER_PAGE)
    ];
}

/**
 * Lấy chi tiết phiên đăng nhập
 */
function getSessionDetails($session_id) {
    global $pdo;

    // Get session info
    $stmt = $pdo->prepare("SELECT * FROM login_sessions WHERE session_id = ?");
    $stmt->execute([$session_id]);
    $session = $stmt->fetch();

    if (!$session) {
        return null;
    }

    // Get session logs
    $stmt = $pdo->prepare("SELECT * FROM session_logs WHERE session_id = ? ORDER BY created_at ASC");
    $stmt->execute([$session_id]);
    $logs = $stmt->fetchAll();

    return [
        'session' => $session,
        'logs' => $logs
    ];
}

/**
 * Lấy thống kê phiên đăng nhập
 */
function getSessionStatistics() {
    global $pdo;

    $stats = [];

    // Total sessions
    $stmt = $pdo->query("SELECT COUNT(*) FROM login_sessions");
    $stats['total_sessions'] = $stmt->fetchColumn();

    // Sessions by status
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM login_sessions GROUP BY status");
    $stats['by_status'] = $stmt->fetchAll();

    // Sessions by platform
    $stmt = $pdo->query("SELECT platform, COUNT(*) as count FROM login_sessions GROUP BY platform");
    $stats['by_platform'] = $stmt->fetchAll();

    // Sessions by device type
    $stmt = $pdo->query("SELECT device_type, COUNT(*) as count FROM login_sessions GROUP BY device_type");
    $stats['by_device'] = $stmt->fetchAll();

    // Today's sessions
    $stmt = $pdo->query("SELECT COUNT(*) FROM login_sessions WHERE DATE(created_at) = CURDATE()");
    $stats['today_sessions'] = $stmt->fetchColumn();

    // Success rate
    $stmt = $pdo->query("SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success
        FROM login_sessions");
    $result = $stmt->fetch();
    $stats['success_rate'] = $result['total'] > 0 ? round(($result['success'] / $result['total']) * 100, 2) : 0;

    return $stats;
}

/**
 * Xóa phiên cũ (older than 30 days)
 */
function cleanupOldSessions() {
    global $pdo;

    $stmt = $pdo->prepare("
        DELETE FROM login_sessions
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");

    $sessions_deleted = $stmt->rowCount();

    // Also cleanup related logs
    $stmt = $pdo->prepare("
        DELETE sl FROM session_logs sl
        LEFT JOIN login_sessions ls ON sl.session_id = ls.session_id
        WHERE ls.session_id IS NULL
    ");

    $logs_deleted = $stmt->rowCount();

    return [
        'sessions_deleted' => $sessions_deleted,
        'logs_deleted' => $logs_deleted
    ];
}

/**
 * Export session data
 */
function exportSessionData($filters = [], $format = 'json') {
    global $pdo;

    $where_conditions = [];
    $params = [];

    if (!empty($filters['platform'])) {
        $where_conditions[] = "platform = ?";
        $params[] = $filters['platform'];
    }

    if (!empty($filters['status'])) {
        $where_conditions[] = "status = ?";
        $params[] = $filters['status'];
    }

    if (!empty($filters['date_from'])) {
        $where_conditions[] = "DATE(created_at) >= ?";
        $params[] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $where_conditions[] = "DATE(created_at) <= ?";
        $params[] = $filters['date_to'];
    }

    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }

    $sql = "SELECT * FROM login_sessions $where_clause ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sessions = $stmt->fetchAll();

    switch ($format) {
        case 'csv':
            return exportToCSV($sessions);
        case 'json':
        default:
            return json_encode($sessions, JSON_PRETTY_PRINT);
    }
}

/**
 * Export to CSV
 */
function exportToCSV($data) {
    if (empty($data)) {
        return '';
    }

    $output = fopen('php://temp', 'r+');

    // Add headers
    fputcsv($output, array_keys($data[0]));

    // Add data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }

    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);

    return $csv;
}

/**
 * Real-time monitoring functions
 */
function getActiveSessions() {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT * FROM login_sessions
        WHERE status IN ('pending', 'processing')
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ORDER BY created_at DESC
    ");
    $stmt->execute();

    return $stmt->fetchAll();
}

function getRecentLogs($limit = 50) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT sl.*, ls.platform, ls.username
        FROM session_logs sl
        LEFT JOIN login_sessions ls ON sl.session_id = ls.session_id
        ORDER BY sl.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);

    return $stmt->fetchAll();
}

/**
 * Security functions
 */
function detectSuspiciousActivity($user_ip, $username) {
    global $pdo;

    // Check for too many failed attempts from same IP
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM login_sessions
        WHERE user_ip = ? AND status = 'failed'
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$user_ip]);
    $failed_attempts = $stmt->fetchColumn();

    if ($failed_attempts > 10) {
        return ['suspicious' => true, 'reason' => 'Too many failed attempts from IP'];
    }

    // Check for too many attempts with same username
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM login_sessions
        WHERE username = ?
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$username]);
    $username_attempts = $stmt->fetchColumn();

    if ($username_attempts > 5) {
        return ['suspicious' => true, 'reason' => 'Too many attempts for username'];
    }

    return ['suspicious' => false];
}

/**
 * Block suspicious IP
 */
if (!function_exists('blockIP')) {
function blockIP($user_ip, $reason = '') {
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO blocked_ips (ip_address, reason, created_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE
        reason = VALUES(reason),
        updated_at = NOW()
    ");

    return $stmt->execute([$user_ip, $reason]);
}
}

/**
 * Check if IP is blocked
 */
if (!function_exists('isIPBlocked')) {
function isIPBlocked($user_ip) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT * FROM blocked_ips
        WHERE ip_address = ?
        AND (expires_at IS NULL OR expires_at > NOW())
    ");
    $stmt->execute([$user_ip]);

    return $stmt->fetch();
}
}
?>
