<?php
// General utility functions

// Get contests with contestant count and total votes
function getContests($status = 'active', $limit = null) {
    $pdo = App\Core\DB::conn();

    $sql = "
        SELECT
            c.*,
            COUNT(DISTINCT ct.id) as contestant_count,
            COALESCE(SUM(ct.total_votes), 0) as total_votes
        FROM contests c
        LEFT JOIN contestants ct ON c.id = ct.contest_id
        WHERE c.status = ?
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ";

    if ($limit) {
        $sql .= " LIMIT " . (int)$limit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$status]);
    return $stmt->fetchAll();
}

// Get contest by ID
function getContest($id) {
    $pdo = App\Core\DB::conn();

    $stmt = $pdo->prepare("
        SELECT
            c.*,
            COUNT(DISTINCT ct.id) as contestant_count,
            COALESCE(SUM(ct.total_votes), 0) as total_votes
        FROM contests c
        LEFT JOIN contestants ct ON c.id = ct.contest_id
        WHERE c.id = ?
        GROUP BY c.id
    ");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Get contestants for a contest
function getContestants($contest_id, $order_by = 'total_votes DESC') {
    $pdo = App\Core\DB::conn();

    $stmt = $pdo->prepare("
        SELECT * FROM contestants
        WHERE contest_id = ?
        ORDER BY $order_by
    ");
    $stmt->execute([$contest_id]);
    return $stmt->fetchAll();
}

// Get contestant by ID
function getContestant($id) {
    $pdo = App\Core\DB::conn();

    $stmt = $pdo->prepare("SELECT * FROM contestants WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Vote for a contestant
function voteForContestant($contestant_id, $user_id) {
    $pdo = App\Core\DB::conn();

    try {
        $pdo->beginTransaction();

        // Check if user already voted for this contestant
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE contestant_id = ? AND user_id = ?");
        $stmt->execute([$contestant_id, $user_id]);

        if ($stmt->fetchColumn() > 0) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Bạn đã bình chọn cho thí sinh này rồi.'];
        }

        // Insert vote
        $stmt = $pdo->prepare("
            INSERT INTO votes (contestant_id, user_id, ip_address, user_agent)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $contestant_id,
            $user_id,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);

        // Update contestant total votes
        $stmt = $pdo->prepare("UPDATE contestants SET total_votes = total_votes + 1 WHERE id = ?");
        $stmt->execute([$contestant_id]);

        $pdo->commit();
        return ['success' => true, 'message' => 'Bình chọn thành công!'];
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Vote error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Có lỗi xảy ra khi bình chọn.'];
    }
}

// Get user votes
function getUserVotes($user_id, $contest_id = null) {
    $pdo = App\Core\DB::conn();

    $sql = "
        SELECT v.*, c.name as contestant_name, ct.name as contest_name
        FROM votes v
        JOIN contestants c ON v.contestant_id = c.id
        JOIN contests ct ON c.contest_id = ct.id
        WHERE v.user_id = ?
    ";

    $params = [$user_id];

    if ($contest_id) {
        $sql .= " AND ct.id = ?";
        $params[] = $contest_id;
    }

    $sql .= " ORDER BY v.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Get users with pagination
function getUsers($page = 1, $search = '', $status = '') {
    $pdo = App\Core\DB::conn();

    $offset = ($page - 1) * ITEMS_PER_PAGE;
    $where_conditions = [];
    $params = [];

    if (!empty($search)) {
        $where_conditions[] = "(username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }

    if (!empty($status)) {
        $where_conditions[] = "status = ?";
        $params[] = $status;
    }

    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }

    // Get total count
    $count_sql = "SELECT COUNT(*) FROM users $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

    // Get users
    $sql = "SELECT * FROM users $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = ITEMS_PER_PAGE;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    return [
        'users' => $users,
        'total' => $total,
        'pages' => ceil($total / ITEMS_PER_PAGE),
        'current_page' => $page
    ];
}

// Get notifications for user
function getNotifications($user_id, $limit = 10) {
    $pdo = App\Core\DB::conn();

    $stmt = $pdo->prepare("
        SELECT * FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll();
}

// Create notification
function createNotification($user_id, $title, $message, $type = 'info') {
    $pdo = App\Core\DB::conn();

    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type)
        VALUES (?, ?, ?, ?)
    ");
    return $stmt->execute([$user_id, $title, $message, $type]);
}

// Mark notification as read
function markNotificationAsRead($notification_id, $user_id) {
    $pdo = App\Core\DB::conn();

    $stmt = $pdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE id = ? AND user_id = ?
    ");
    return $stmt->execute([$notification_id, $user_id]);
}

// Get unread notification count
function getUnreadNotificationCount($user_id) {
    $pdo = App\Core\DB::conn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM notifications
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

// Get user activity
function getUserActivity($user_id = null, $limit = 50) {
    $pdo = App\Core\DB::conn();

    if ($user_id) {
        $stmt = $pdo->prepare("
            SELECT * FROM user_activity
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$user_id, $limit]);
    } else {
        $stmt = $pdo->prepare("
            SELECT ua.*, u.username
            FROM user_activity ua
            LEFT JOIN users u ON ua.user_id = u.id
            ORDER BY ua.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
    }

    return $stmt->fetchAll();
}

// Get statistics
function getStatistics() {
    $pdo = App\Core\DB::conn();

    $stats = [];

    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
    $stats['total_users'] = $stmt->fetchColumn();

    // Total contests
    $stmt = $pdo->query("SELECT COUNT(*) FROM contests");
    $stats['total_contests'] = $stmt->fetchColumn();

    // Active contests
    $stmt = $pdo->query("SELECT COUNT(*) FROM contests WHERE status = 'active'");
    $stats['active_contests'] = $stmt->fetchColumn();

    // Total contestants
    $stmt = $pdo->query("SELECT COUNT(*) FROM contestants");
    $stats['total_contestants'] = $stmt->fetchColumn();

    // Total votes
    $stmt = $pdo->query("SELECT COUNT(*) FROM votes");
    $stats['total_votes'] = $stmt->fetchColumn();

    // Today's votes (SQLite and MySQL compatible)
    $todayDate = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE DATE(created_at) = ?");
    $stmt->execute([$todayDate]);
    $stats['today_votes'] = $stmt->fetchColumn();

    // Today's registrations (SQLite and MySQL compatible)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at) = ? AND role = 'user'");
    $stmt->execute([$todayDate]);
    $stats['today_registrations'] = $stmt->fetchColumn();

    return $stats;
}

// Generate pagination links
function generatePagination($current_page, $total_pages, $base_url) {
    $pagination = '';

    if ($total_pages <= 1) {
        return $pagination;
    }

    $pagination .= '<div class="flex justify-center items-center space-x-2 mt-6">';

    // Previous button
    if ($current_page > 1) {
        $pagination .= '<a href="' . $base_url . '?page=' . ($current_page - 1) . '" class="px-3 py-2 bg-gray-700 text-white rounded hover:bg-gray-600">Trước</a>';
    }

    // Page numbers
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);

    for ($i = $start; $i <= $end; $i++) {
        if ($i == $current_page) {
            $pagination .= '<span class="px-3 py-2 bg-blue-600 text-white rounded">' . $i . '</span>';
        } else {
            $pagination .= '<a href="' . $base_url . '?page=' . $i . '" class="px-3 py-2 bg-gray-700 text-white rounded hover:bg-gray-600">' . $i . '</a>';
        }
    }

    // Next button
    if ($current_page < $total_pages) {
        $pagination .= '<a href="' . $base_url . '?page=' . ($current_page + 1) . '" class="px-3 py-2 bg-gray-700 text-white rounded hover:bg-gray-600">Sau</a>';
    }

    $pagination .= '</div>';

    return $pagination;
}

// Search functionality
function searchContests($query) {
    $pdo = App\Core\DB::conn();

    $stmt = $pdo->prepare("
        SELECT
            c.*,
            COUNT(DISTINCT ct.id) as contestant_count,
            COALESCE(SUM(ct.total_votes), 0) as total_votes
        FROM contests c
        LEFT JOIN contestants ct ON c.id = ct.contest_id
        WHERE c.name LIKE ? OR c.description LIKE ?
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");

    $search_param = "%$query%";
    $stmt->execute([$search_param, $search_param]);
    return $stmt->fetchAll();
}

// Get top contestants
function getTopContestants($limit = 10) {
    $pdo = App\Core\DB::conn();

    $stmt = $pdo->prepare("
        SELECT
            ct.*,
            c.name as contest_name
        FROM contestants ct
        JOIN contests c ON ct.contest_id = c.id
        WHERE c.status = 'active'
        ORDER BY ct.total_votes DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

// Update setting
function updateSetting($key, $value) {
    $pdo = App\Core\DB::conn();

    $stmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = ?
    ");
    return $stmt->execute([$key, $value, $value]);
}

// Delete file
function deleteFile($filepath) {
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return false;
}

// Generate random string
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $string = '';
    for ($i = 0; $i < $length; $i++) {
        $string .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $string;
}

// Validate image file
function validateImageFile($file) {
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

    if (!isset($file['type']) || !in_array($file['type'], $allowed_types)) {
        return false;
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return false;
    }

    return true;
}

// Get file extension from mime type
function getFileExtension($mime_type) {
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];

    return $extensions[$mime_type] ?? 'jpg';
}

// Build safe image URL for avatars/contestants/banners
if (!function_exists('imageUrl')) {
    function imageUrl(?string $url, string $type = 'generic', string $fallback = ''): string {
        // If already absolute URL from our domain or CDN, return as-is
        if (!empty($url)) {
            if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0 || strpos($url, '//') === 0) {
                return $url;
            }
            // Relative path inside php-version/uploads
            return APP_URL . '/' . ltrim($url, '/');
        }
        // Fallbacks inside uploads or assets
        switch ($type) {
            case 'avatar':
                $candidate = 'uploads/avatars/default-avatar.svg';
                break;
            case 'contestant':
                $candidate = 'uploads/contestants/default-contestant.svg';
                break;
            case 'banner':
                $candidate = 'uploads/banners/default-banner.svg';
                break;
            default:
                $candidate = $fallback ?: 'assets/images/default-generic.svg';
        }
        return APP_URL . '/' . $candidate;
    }
}

// Sensitive data encryption/decryption using AES-256-CBC
if (!function_exists('encryptSensitiveData')) {
    function encryptSensitiveData(string $plaintext): string {
        if ($plaintext === '') return '';
        $key = env('DATA_ENCRYPTION_KEY', '');
        if (!$key) return $plaintext; // fallback: store as-is if no key configured
        $keyBin = hash('sha256', $key, true);
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $keyBin, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $ciphertext);
    }
}

if (!function_exists('decryptSensitiveData')) {
    function decryptSensitiveData(?string $encoded): string {
        if (empty($encoded)) return '';
        $key = env('DATA_ENCRYPTION_KEY', '');
        if (!$key) return $encoded; // fallback: return raw if no key configured
        $data = base64_decode($encoded, true);
        if ($data === false || strlen($data) < 17) return '';
        $iv = substr($data, 0, 16);
        $ciphertext = substr($data, 16);
        $keyBin = hash('sha256', $key, true);
        $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $keyBin, OPENSSL_RAW_DATA, $iv);
        return $plaintext === false ? '' : $plaintext;
    }
}
?>
