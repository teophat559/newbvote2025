<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-security.php';

// Require only admin key verification
requireAdminKey();

$page_title = 'Quản lý Hoạt động';
include '../../includes/header.php';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCSRFToken()) {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'delete_activity':
            $activity_id = intval($_POST['activity_id'] ?? 0);

            if ($activity_id) {
                $stmt = $pdo->prepare("DELETE FROM user_activity WHERE id = ?");
                if ($stmt->execute([$activity_id])) {
                    setFlashMessage('success', 'Đã xóa hoạt động thành công.');
                } else {
                    setFlashMessage('error', 'Có lỗi xảy ra khi xóa hoạt động.');
                }
            }
            break;

        case 'clear_old_activities':
            $days = intval($_POST['days'] ?? 30);
            $stmt = $pdo->prepare("DELETE FROM user_activity WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            if ($stmt->execute([$days])) {
                $deleted_count = $stmt->rowCount();
                setFlashMessage('success', "Đã xóa {$deleted_count} hoạt động cũ hơn {$days} ngày.");
            } else {
                setFlashMessage('error', 'Có lỗi xảy ra khi xóa hoạt động cũ.');
            }
            break;
    }
}

// Get filters
$page = max(1, intval($_GET['page'] ?? 1));
$filters = [
    'search' => $_GET['search'] ?? '',
    'action_type' => $_GET['action_type'] ?? '',
    'user_id' => $_GET['user_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'sort' => $_GET['sort'] ?? 'created_at',
    'order' => $_GET['order'] ?? 'DESC'
];

// Get activities with filters
$where_conditions = [];
$params = [];

if (!empty($filters['search'])) {
    $where_conditions[] = "(ua.details LIKE ? OR u.username LIKE ? OR u.full_name LIKE ?)";
    $search_term = '%' . $filters['search'] . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($filters['action_type'])) {
    $where_conditions[] = "ua.action = ?";
    $params[] = $filters['action_type'];
}

if (!empty($filters['user_id'])) {
    $where_conditions[] = "ua.user_id = ?";
    $params[] = $filters['user_id'];
}

if (!empty($filters['date_from'])) {
    $where_conditions[] = "DATE(ua.created_at) >= ?";
    $params[] = $filters['date_from'];
}

if (!empty($filters['date_to'])) {
    $where_conditions[] = "DATE(ua.created_at) <= ?";
    $params[] = $filters['date_to'];
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get total count
$count_sql = "SELECT COUNT(*) FROM user_activity ua LEFT JOIN users u ON ua.user_id = u.id $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total = $stmt->fetchColumn();

// Get activities
$offset = ($page - 1) * ITEMS_PER_PAGE;
$sql = "SELECT ua.*, u.username, u.full_name, u.email
        FROM user_activity ua
        LEFT JOIN users u ON ua.user_id = u.id
        $where_clause
        ORDER BY ua.{$filters['sort']} {$filters['order']}
        LIMIT ? OFFSET ?";
$params[] = ITEMS_PER_PAGE;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$activities = $stmt->fetchAll();

$total_pages = ceil($total / ITEMS_PER_PAGE);

// Get users for filter
$stmt = $pdo->query("SELECT id, username, full_name FROM users ORDER BY username");
$users = $stmt->fetchAll();

// Get action types for filter
$stmt = $pdo->query("SELECT DISTINCT action FROM user_activity ORDER BY action");
$action_types = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("SELECT
    COUNT(*) as total_activities,
    COUNT(DISTINCT user_id) as unique_users,
    COUNT(CASE WHEN action = 'user_login' THEN 1 END) as login_activities,
    COUNT(CASE WHEN action = 'vote_cast' THEN 1 END) as vote_activities,
    COUNT(CASE WHEN action = 'user_register' THEN 1 END) as register_activities,
    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as today_activities
    FROM user_activity");
$stats = $stmt->fetch();
?>

<!-- Admin Header -->
<div class="bg-gray-800 border-b border-gray-700 mb-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-white">Quản lý Hoạt động</h1>
                <p class="text-gray-400 mt-1">Theo dõi và quản lý hoạt động của người dùng</p>
            </div>
            <div class="flex space-x-4">
                <button onclick="showClearModal()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-trash mr-2"></i> Xóa hoạt động cũ
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Statistics -->
<div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-6 mb-8">
    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-500/10">
                <i class="fas fa-chart-line text-blue-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Tổng hoạt động</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['total_activities']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-500/10">
                <i class="fas fa-users text-green-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Người dùng hoạt động</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['unique_users']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-500/10">
                <i class="fas fa-sign-in-alt text-yellow-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Đăng nhập</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['login_activities']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-500/10">
                <i class="fas fa-vote-yea text-purple-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Bình chọn</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['vote_activities']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-indigo-500/10">
                <i class="fas fa-user-plus text-indigo-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Đăng ký</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['register_activities']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-500/10">
                <i class="fas fa-clock text-red-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Hôm nay</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['today_activities']; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="mb-6">
    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Tìm kiếm</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>"
                       placeholder="Chi tiết, username..."
                       class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Loại hoạt động</label>
                <select name="action_type" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                    <option value="">Tất cả</option>
                    <?php foreach ($action_types as $type): ?>
                        <option value="<?php echo $type['action']; ?>" <?php echo $filters['action_type'] === $type['action'] ? 'selected' : ''; ?>>
                            <?php echo ucfirst(str_replace('_', ' ', $type['action'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Người dùng</label>
                <select name="user_id" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                    <option value="">Tất cả</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $filters['user_id'] == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['username']); ?>
                            <?php if ($user['full_name']): ?>
                                (<?php echo htmlspecialchars($user['full_name']); ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Từ ngày</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>"
                       class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Đến ngày</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>"
                       class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
            </div>

            <div class="flex items-end space-x-2">
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-filter mr-2"></i> Lọc
                </button>
                <a href="<?php echo APP_URL; ?>/admin/activity" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-times mr-2"></i> Xóa
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Activities Table -->
<div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-700">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-white">Danh sách hoạt động</h2>
                <p class="text-gray-400 text-sm mt-1">Tổng cộng: <?php echo $total; ?> hoạt động</p>
            </div>
        </div>
    </div>

    <?php if (empty($activities)): ?>
        <div class="text-center py-12">
            <i class="fas fa-chart-line text-gray-500 text-4xl mb-4"></i>
            <p class="text-gray-400">Không tìm thấy hoạt động nào</p>
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
                            Hoạt động
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Chi tiết
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Thời gian
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Thao tác
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-gray-800 divide-y divide-gray-700">
                    <?php foreach ($activities as $activity): ?>
                        <tr class="hover:bg-gray-700 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <img class="h-10 w-10 rounded-full object-cover"
                                             src="<?php echo imageUrl($activity['avatar_url'] ?? null, 'avatar'); ?>"
                                             alt="<?php echo htmlspecialchars($activity['username'] ?? 'Unknown'); ?>">
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-white">
                                            <?php echo htmlspecialchars($activity['username'] ?? 'Khách'); ?>
                                        </div>
                                        <?php if ($activity['full_name']): ?>
                                            <div class="text-sm text-gray-400">
                                                <?php echo htmlspecialchars($activity['full_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    <?php
                                    echo $activity['action'] === 'user_login' ? 'bg-green-100 text-green-800' :
                                        ($activity['action'] === 'vote_cast' ? 'bg-blue-100 text-blue-800' :
                                        ($activity['action'] === 'user_register' ? 'bg-purple-100 text-purple-800' :
                                        ($activity['action'] === 'admin_login' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')));
                                    ?>">
                                    <?php
                                    echo $activity['action'] === 'user_login' ? 'Đăng nhập' :
                                        ($activity['action'] === 'vote_cast' ? 'Bình chọn' :
                                        ($activity['action'] === 'user_register' ? 'Đăng ký' :
                                        ($activity['action'] === 'admin_login' ? 'Admin Login' : ucfirst(str_replace('_', ' ', $activity['action'])))));
                                    ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-300 max-w-md">
                                    <?php echo htmlspecialchars($activity['details']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                <?php echo formatDate($activity['created_at']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="deleteActivity(<?php echo $activity['id']; ?>)"
                                        class="text-red-400 hover:text-red-300" title="Xóa">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 border-t border-gray-700">
                <?php echo generatePagination($page, $total_pages, APP_URL . '/admin/activity?' . http_build_query($filters)); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Clear Old Activities Modal -->
<div id="clearModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-white">Xóa hoạt động cũ</h3>
                <button onclick="hideClearModal()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form method="POST" id="clearForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="clear_old_activities">

                <div class="space-y-4">
                    <p class="text-gray-300">Chọn số ngày để xóa các hoạt động cũ hơn:</p>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Số ngày</label>
                        <select name="days" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                            <option value="7">7 ngày</option>
                            <option value="30" selected>30 ngày</option>
                            <option value="60">60 ngày</option>
                            <option value="90">90 ngày</option>
                            <option value="180">180 ngày</option>
                        </select>
                    </div>

                    <div class="bg-yellow-900/20 border border-yellow-700 rounded-lg p-3">
                        <p class="text-yellow-400 text-sm">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            Cảnh báo: Hành động này không thể hoàn tác!
                        </p>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="hideClearModal()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium">
                        Hủy
                    </button>
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-trash mr-2"></i> Xóa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showClearModal() {
    document.getElementById('clearModal').classList.remove('hidden');
}

function hideClearModal() {
    document.getElementById('clearModal').classList.add('hidden');
}

function deleteActivity(activityId) {
    if (confirm('Bạn có chắc chắn muốn xóa hoạt động này?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="delete_activity">
            <input type="hidden" name="activity_id" value="${activityId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking outside
document.getElementById('clearModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideClearModal();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
