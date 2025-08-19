<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-security.php';

// Require only admin key verification
requireAdminKey();

$page_title = 'Quản lý Người dùng';
include '../../includes/header.php';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCSRFToken()) {
    $action = $_POST['action'] ?? '';
    $user_id = intval($_POST['user_id'] ?? 0);

    switch ($action) {
        case 'update_status':
            $status = $_POST['status'] ?? '';
            if ($user_id && in_array($status, ['active', 'inactive', 'banned'])) {
                $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                if ($stmt->execute([$status, $user_id])) {
                    setFlashMessage('success', 'Cập nhật trạng thái người dùng thành công.');
                } else {
                    setFlashMessage('error', 'Có lỗi xảy ra khi cập nhật trạng thái.');
                }
            }
            break;

        case 'delete_user':
            if ($user_id) {
                // Check if user is not admin
                $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();

                if ($user && $user['role'] !== 'admin') {
                    // Delete user's votes first
                    $stmt = $pdo->prepare("DELETE FROM votes WHERE user_id = ?");
                    $stmt->execute([$user_id]);

                    // Delete user's activity
                    $stmt = $pdo->prepare("DELETE FROM user_activity WHERE user_id = ?");
                    $stmt->execute([$user_id]);

                    // Delete user
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    if ($stmt->execute([$user_id])) {
                        setFlashMessage('success', 'Đã xóa người dùng thành công.');
                    } else {
                        setFlashMessage('error', 'Có lỗi xảy ra khi xóa người dùng.');
                    }
                } else {
                    setFlashMessage('error', 'Không thể xóa tài khoản admin.');
                }
            }
            break;

        case 'send_notification':
            $title = sanitizeInput($_POST['title'] ?? '');
            $message = sanitizeInput($_POST['message'] ?? '');

            if ($title && $message) {
                if ($user_id) {
                    // Send to specific user
                    if (createNotification($user_id, $title, $message)) {
                        setFlashMessage('success', 'Đã gửi thông báo thành công.');
                    } else {
                        setFlashMessage('error', 'Có lỗi xảy ra khi gửi thông báo.');
                    }
                } else {
                    // Send to all users
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE status = 'active'");
                    $stmt->execute();
                    $users = $stmt->fetchAll();

                    $success_count = 0;
                    foreach ($users as $user) {
                        if (createNotification($user['id'], $title, $message)) {
                            $success_count++;
                        }
                    }

                    setFlashMessage('success', "Đã gửi thông báo thành công cho {$success_count} người dùng.");
                }
            }
            break;
    }
}

// Get filters
$page = max(1, intval($_GET['page'] ?? 1));
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'role' => $_GET['role'] ?? '',
    'sort' => $_GET['sort'] ?? 'created_at',
    'order' => $_GET['order'] ?? 'DESC'
];

// Get users with filters
$where_conditions = [];
$params = [];

if (!empty($filters['search'])) {
    $where_conditions[] = "(username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $search_term = '%' . $filters['search'] . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($filters['status'])) {
    $where_conditions[] = "status = ?";
    $params[] = $filters['status'];
}

if (!empty($filters['role'])) {
    $where_conditions[] = "role = ?";
    $params[] = $filters['role'];
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
$offset = ($page - 1) * ITEMS_PER_PAGE;
$sql = "SELECT u.*,
        COUNT(DISTINCT v.id) as total_votes,
        COUNT(DISTINCT ua.id) as total_activities
        FROM users u
        LEFT JOIN votes v ON u.id = v.user_id
        LEFT JOIN user_activity ua ON u.id = ua.user_id
        $where_clause
        GROUP BY u.id
        ORDER BY u.{$filters['sort']} {$filters['order']}
        LIMIT ? OFFSET ?";
$params[] = ITEMS_PER_PAGE;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$total_pages = ceil($total / ITEMS_PER_PAGE);

// Get statistics
$stmt = $pdo->query("SELECT
    COUNT(*) as total_users,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_users,
    SUM(CASE WHEN status = 'banned' THEN 1 ELSE 0 END) as banned_users,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_users,
    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_registrations
    FROM users");
$stats = $stmt->fetch();
?>

<!-- Admin Header -->
<div class="bg-gray-800 border-b border-gray-700 mb-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-white">Quản lý Người dùng</h1>
                <p class="text-gray-400 mt-1">Quản lý tất cả người dùng trong hệ thống</p>
            </div>
            <div class="flex space-x-4">
                <button onclick="showNotificationModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-bell mr-2"></i> Gửi thông báo
                </button>
                <button onclick="showBulkActionModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-tasks mr-2"></i> Thao tác hàng loạt
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Statistics -->
<div class="grid grid-cols-1 md:grid-cols-6 gap-6 mb-8">
    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-500/10">
                <i class="fas fa-users text-blue-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Tổng người dùng</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['total_users']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-500/10">
                <i class="fas fa-check text-green-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Đang hoạt động</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['active_users']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-500/10">
                <i class="fas fa-pause text-yellow-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Không hoạt động</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['inactive_users']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-500/10">
                <i class="fas fa-ban text-red-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Bị cấm</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['banned_users']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-500/10">
                <i class="fas fa-shield-alt text-purple-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Admin</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['admin_users']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-indigo-500/10">
                <i class="fas fa-user-plus text-indigo-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Hôm nay</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['today_registrations']; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="mb-6">
    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Tìm kiếm</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>"
                       placeholder="Tên, email, username..."
                       class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Trạng thái</label>
                <select name="status" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                    <option value="">Tất cả</option>
                    <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Đang hoạt động</option>
                    <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>Không hoạt động</option>
                    <option value="banned" <?php echo $filters['status'] === 'banned' ? 'selected' : ''; ?>>Bị cấm</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Vai trò</label>
                <select name="role" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                    <option value="">Tất cả</option>
                    <option value="user" <?php echo $filters['role'] === 'user' ? 'selected' : ''; ?>>Người dùng</option>
                    <option value="admin" <?php echo $filters['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Sắp xếp</label>
                <select name="sort" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                    <option value="created_at" <?php echo $filters['sort'] === 'created_at' ? 'selected' : ''; ?>>Ngày tạo</option>
                    <option value="username" <?php echo $filters['sort'] === 'username' ? 'selected' : ''; ?>>Tên đăng nhập</option>
                    <option value="status" <?php echo $filters['sort'] === 'status' ? 'selected' : ''; ?>>Trạng thái</option>
                    <option value="last_login" <?php echo $filters['sort'] === 'last_login' ? 'selected' : ''; ?>>Lần đăng nhập cuối</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Thứ tự</label>
                <select name="order" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                    <option value="DESC" <?php echo $filters['order'] === 'DESC' ? 'selected' : ''; ?>>Giảm dần</option>
                    <option value="ASC" <?php echo $filters['order'] === 'ASC' ? 'selected' : ''; ?>>Tăng dần</option>
                </select>
            </div>

            <div class="flex items-end space-x-2">
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-filter mr-2"></i> Lọc
                </button>
                <a href="<?php echo APP_URL; ?>/admin/users" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-times mr-2"></i> Xóa
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-700">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-white">Danh sách người dùng</h2>
                <p class="text-gray-400 text-sm mt-1">Tổng cộng: <?php echo $total; ?> người dùng</p>
            </div>
        </div>
    </div>

    <?php if (empty($users)): ?>
        <div class="text-center py-12">
            <i class="fas fa-users text-gray-500 text-4xl mb-4"></i>
            <p class="text-gray-400">Không tìm thấy người dùng nào</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Người dùng
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Thông tin
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Hoạt động
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Trạng thái
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Ngày tạo
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Thao tác
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-gray-800 divide-y divide-gray-700">
                    <?php foreach ($users as $user): ?>
                        <tr class="hover:bg-gray-700 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <img class="h-10 w-10 rounded-full object-cover"
                                             src="<?php echo imageUrl($user['avatar_url'], 'avatar'); ?>"
                                             alt="<?php echo htmlspecialchars($user['username']); ?>">
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-white">
                                            <?php echo htmlspecialchars($user['username']); ?>
                                            <?php if ($user['role'] === 'admin'): ?>
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                                    Admin
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-sm text-gray-400"><?php echo htmlspecialchars($user['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-300">
                                    <div><?php echo htmlspecialchars($user['full_name'] ?: 'Chưa cập nhật'); ?></div>
                                    <div class="text-xs text-gray-400">
                                        <?php if ($user['social_platform']): ?>
                                            <i class="fab fa-<?php echo $user['social_platform']; ?> mr-1"></i>
                                            <?php echo ucfirst($user['social_platform']); ?>
                                        <?php else: ?>
                                            <i class="fas fa-user mr-1"></i>
                                            Tài khoản thường
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-300">
                                    <div><?php echo formatNumber($user['total_votes']); ?> lượt bình chọn</div>
                                    <div class="text-xs text-gray-400"><?php echo formatNumber($user['total_activities']); ?> hoạt động</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    <?php
                                    echo $user['status'] === 'active' ? 'bg-green-100 text-green-800' :
                                        ($user['status'] === 'inactive' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                    ?>">
                                    <?php
                                    echo $user['status'] === 'active' ? 'Đang hoạt động' :
                                        ($user['status'] === 'inactive' ? 'Không hoạt động' : 'Bị cấm');
                                    ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                <?php echo formatDate($user['created_at']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <button onclick="showUserDetails(<?php echo $user['id']; ?>)"
                                            class="text-blue-400 hover:text-blue-300">
                                        <i class="fas fa-eye"></i>
                                    </button>

                                    <?php if ($user['role'] !== 'admin'): ?>
                                        <button onclick="showStatusModal(<?php echo $user['id']; ?>, '<?php echo $user['status']; ?>')"
                                                class="text-yellow-400 hover:text-yellow-300">
                                            <i class="fas fa-edit"></i>
                                        </button>

                                        <button onclick="showNotificationModal(<?php echo $user['id']; ?>)"
                                                class="text-green-400 hover:text-green-300">
                                            <i class="fas fa-bell"></i>
                                        </button>

                                        <button onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                                class="text-red-400 hover:text-red-300">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 border-t border-gray-700">
                <?php echo generatePagination($page, $total_pages, APP_URL . '/admin/users?' . http_build_query($filters)); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Status Update Modal -->
<div id="statusModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-white">Cập nhật trạng thái</h3>
                <button onclick="hideStatusModal()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="user_id" id="statusUserId">

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Trạng thái mới</label>
                        <select name="status" id="statusSelect" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                            <option value="active">Đang hoạt động</option>
                            <option value="inactive">Không hoạt động</option>
                            <option value="banned">Bị cấm</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="hideStatusModal()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium">
                        Hủy
                    </button>
                    <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium">
                        Cập nhật
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Notification Modal -->
<div id="notificationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-white">Gửi thông báo</h3>
                <button onclick="hideNotificationModal()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="send_notification">
                <input type="hidden" name="user_id" id="notificationUserId">

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Tiêu đề</label>
                        <input type="text" name="title" required
                               placeholder="Nhập tiêu đề thông báo"
                               class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Nội dung</label>
                        <textarea name="message" rows="4" required
                                  placeholder="Nhập nội dung thông báo"
                                  class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full"></textarea>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="hideNotificationModal()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium">
                        Hủy
                    </button>
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-paper-plane mr-2"></i> Gửi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showStatusModal(userId, currentStatus) {
    document.getElementById('statusUserId').value = userId;
    document.getElementById('statusSelect').value = currentStatus;
    document.getElementById('statusModal').classList.remove('hidden');
}

function hideStatusModal() {
    document.getElementById('statusModal').classList.add('hidden');
}

function showNotificationModal(userId = null) {
    document.getElementById('notificationUserId').value = userId || '';
    document.getElementById('notificationModal').classList.remove('hidden');
}

function hideNotificationModal() {
    document.getElementById('notificationModal').classList.add('hidden');
}

function deleteUser(userId, username) {
    if (confirm(`Bạn có chắc chắn muốn xóa người dùng "${username}"? Hành động này không thể hoàn tác.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function showUserDetails(userId) {
    // Redirect to user details page
    window.location.href = '<?php echo APP_URL; ?>/admin/user-details?id=' + userId;
}

// Close modals when clicking outside
document.getElementById('statusModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideStatusModal();
    }
});

document.getElementById('notificationModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideNotificationModal();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
