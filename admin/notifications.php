<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-security.php';

// Require only admin key verification
requireAdminKey();

$page_title = 'Quản lý Thông báo';
include '../../includes/header.php';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCSRFToken()) {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'send_notification':
            $title = sanitizeInput($_POST['title'] ?? '');
            $message = sanitizeInput($_POST['message'] ?? '');
            $target_type = $_POST['target_type'] ?? 'all';
            $user_ids = $_POST['user_ids'] ?? [];

            if ($title && $message) {
                $success_count = 0;

                if ($target_type === 'all') {
                    // Send to all active users
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE status = 'active'");
                    $stmt->execute();
                    $users = $stmt->fetchAll();

                    foreach ($users as $user) {
                        if (createNotification($user['id'], $title, $message)) {
                            $success_count++;
                        }
                    }
                } elseif ($target_type === 'selected' && !empty($user_ids)) {
                    // Send to selected users
                    foreach ($user_ids as $user_id) {
                        if (createNotification($user_id, $title, $message)) {
                            $success_count++;
                        }
                    }
                } elseif ($target_type === 'contest' && !empty($_POST['contest_id'])) {
                    // Send to users who voted in specific contest
                    $contest_id = intval($_POST['contest_id']);
                    $stmt = $pdo->prepare("
                        SELECT DISTINCT u.id FROM users u
                        JOIN votes v ON u.id = v.user_id
                        JOIN contestants c ON v.contestant_id = c.id
                        WHERE c.contest_id = ? AND u.status = 'active'
                    ");
                    $stmt->execute([$contest_id]);
                    $users = $stmt->fetchAll();

                    foreach ($users as $user) {
                        if (createNotification($user['id'], $title, $message)) {
                            $success_count++;
                        }
                    }
                }

                setFlashMessage('success', "Đã gửi thông báo thành công cho {$success_count} người dùng.");
                redirect(APP_URL . '/admin/notifications');
            } else {
                setFlashMessage('error', 'Vui lòng điền đầy đủ thông tin bắt buộc.');
            }
            break;

        case 'delete_notification':
            $notification_id = intval($_POST['notification_id'] ?? 0);

            if ($notification_id) {
                $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
                if ($stmt->execute([$notification_id])) {
                    setFlashMessage('success', 'Đã xóa thông báo thành công.');
                } else {
                    setFlashMessage('error', 'Có lỗi xảy ra khi xóa thông báo.');
                }
            }
            break;

        case 'mark_read':
            $notification_id = intval($_POST['notification_id'] ?? 0);

            if ($notification_id) {
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
                if ($stmt->execute([$notification_id])) {
                    setFlashMessage('success', 'Đã đánh dấu thông báo đã đọc.');
                } else {
                    setFlashMessage('error', 'Có lỗi xảy ra khi cập nhật thông báo.');
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
    'target_type' => $_GET['target_type'] ?? '',
    'sort' => $_GET['sort'] ?? 'created_at',
    'order' => $_GET['order'] ?? 'DESC'
];

// Get notifications with filters
$where_conditions = [];
$params = [];

if (!empty($filters['search'])) {
    $where_conditions[] = "(n.title LIKE ? OR n.message LIKE ?)";
    $search_term = '%' . $filters['search'] . '%';
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($filters['status'])) {
    if ($filters['status'] === 'read') {
        $where_conditions[] = "n.is_read = 1";
    } elseif ($filters['status'] === 'unread') {
        $where_conditions[] = "n.is_read = 0";
    }
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get total count
$count_sql = "SELECT COUNT(*) FROM notifications n $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total = $stmt->fetchColumn();

// Get notifications
$offset = ($page - 1) * ITEMS_PER_PAGE;
$sql = "SELECT n.*, u.username, u.full_name
        FROM notifications n
        LEFT JOIN users u ON n.user_id = u.id
        $where_clause
        ORDER BY n.{$filters['sort']} {$filters['order']}
        LIMIT ? OFFSET ?";
$params[] = ITEMS_PER_PAGE;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

$total_pages = ceil($total / ITEMS_PER_PAGE);

// Get contests for filter
$stmt = $pdo->query("SELECT id, name FROM contests ORDER BY name");
$contests = $stmt->fetchAll();

// Get users for filter
$stmt = $pdo->query("SELECT id, username, full_name FROM users WHERE status = 'active' ORDER BY username");
$users = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("SELECT
    COUNT(*) as total_notifications,
    SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read_notifications,
    SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_notifications,
    COUNT(DISTINCT user_id) as unique_users
    FROM notifications");
$stats = $stmt->fetch();
?>

<!-- Admin Header -->
<div class="bg-gray-800 border-b border-gray-700 mb-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-white">Quản lý Thông báo</h1>
                <p class="text-gray-400 mt-1">Gửi và quản lý thông báo cho người dùng</p>
            </div>
            <div class="flex space-x-4">
                <button onclick="showSendModal()" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-paper-plane mr-2"></i> Gửi thông báo
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Statistics -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-500/10">
                <i class="fas fa-bell text-blue-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Tổng thông báo</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['total_notifications']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-500/10">
                <i class="fas fa-check text-green-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Đã đọc</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['read_notifications']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-500/10">
                <i class="fas fa-exclamation text-yellow-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Chưa đọc</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['unread_notifications']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-500/10">
                <i class="fas fa-users text-purple-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Người dùng nhận</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['unique_users']; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="mb-6">
    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Tìm kiếm</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>"
                       placeholder="Tiêu đề, nội dung..."
                       class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Trạng thái</label>
                <select name="status" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                    <option value="">Tất cả</option>
                    <option value="read" <?php echo $filters['status'] === 'read' ? 'selected' : ''; ?>>Đã đọc</option>
                    <option value="unread" <?php echo $filters['status'] === 'unread' ? 'selected' : ''; ?>>Chưa đọc</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Sắp xếp</label>
                <select name="sort" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                    <option value="created_at" <?php echo $filters['sort'] === 'created_at' ? 'selected' : ''; ?>>Ngày gửi</option>
                    <option value="title" <?php echo $filters['sort'] === 'title' ? 'selected' : ''; ?>>Tiêu đề</option>
                    <option value="is_read" <?php echo $filters['sort'] === 'is_read' ? 'selected' : ''; ?>>Trạng thái</option>
                </select>
            </div>

            <div class="flex items-end space-x-2">
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-filter mr-2"></i> Lọc
                </button>
                <a href="<?php echo APP_URL; ?>/admin/notifications" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-times mr-2"></i> Xóa
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Notifications Table -->
<div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-700">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-white">Danh sách thông báo</h2>
                <p class="text-gray-400 text-sm mt-1">Tổng cộng: <?php echo $total; ?> thông báo</p>
            </div>
        </div>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="text-center py-12">
            <i class="fas fa-bell text-gray-500 text-4xl mb-4"></i>
            <p class="text-gray-400">Không tìm thấy thông báo nào</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Thông báo
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Người nhận
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Trạng thái
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Ngày gửi
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Thao tác
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-gray-800 divide-y divide-gray-700">
                    <?php foreach ($notifications as $notification): ?>
                        <tr class="hover:bg-gray-700 transition-colors <?php echo $notification['is_read'] ? '' : 'bg-blue-900/20'; ?>">
                            <td class="px-6 py-4">
                                <div class="max-w-md">
                                    <div class="text-sm font-medium text-white">
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                    </div>
                                    <div class="text-sm text-gray-400 mt-1 line-clamp-2">
                                        <?php echo htmlspecialchars($notification['message']); ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-300">
                                    <?php echo htmlspecialchars($notification['username'] ?: 'Không xác định'); ?>
                                </div>
                                <?php if ($notification['full_name']): ?>
                                    <div class="text-xs text-gray-400">
                                        <?php echo htmlspecialchars($notification['full_name']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    <?php echo $notification['is_read'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo $notification['is_read'] ? 'Đã đọc' : 'Chưa đọc'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                <?php echo formatDate($notification['created_at']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <?php if (!$notification['is_read']): ?>
                                        <button onclick="markAsRead(<?php echo $notification['id']; ?>)"
                                                class="text-green-400 hover:text-green-300" title="Đánh dấu đã đọc">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    <?php endif; ?>

                                    <button onclick="deleteNotification(<?php echo $notification['id']; ?>)"
                                            class="text-red-400 hover:text-red-300" title="Xóa">
                                        <i class="fas fa-trash"></i>
                                    </button>
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
                <?php echo generatePagination($page, $total_pages, APP_URL . '/admin/notifications?' . http_build_query($filters)); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Send Notification Modal -->
<div id="sendModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-white">Gửi thông báo</h3>
                <button onclick="hideSendModal()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form method="POST" id="sendForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="send_notification">

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Tiêu đề *</label>
                        <input type="text" name="title" required
                               placeholder="Nhập tiêu đề thông báo"
                               class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Nội dung *</label>
                        <textarea name="message" rows="4" required
                                  placeholder="Nhập nội dung thông báo"
                                  class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Đối tượng nhận</label>
                        <select name="target_type" id="targetType" onchange="toggleTargetOptions()" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                            <option value="all">Tất cả người dùng</option>
                            <option value="selected">Người dùng được chọn</option>
                            <option value="contest">Người dùng đã bình chọn trong cuộc thi</option>
                        </select>
                    </div>

                    <div id="selectedUsersDiv" class="hidden">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Chọn người dùng</label>
                        <select name="user_ids[]" multiple class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full" size="5">
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                    <?php if ($user['full_name']): ?>
                                        (<?php echo htmlspecialchars($user['full_name']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Giữ Ctrl để chọn nhiều người dùng</p>
                    </div>

                    <div id="contestDiv" class="hidden">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Chọn cuộc thi</label>
                        <select name="contest_id" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                            <option value="">Chọn cuộc thi</option>
                            <?php foreach ($contests as $contest): ?>
                                <option value="<?php echo $contest['id']; ?>">
                                    <?php echo htmlspecialchars($contest['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="hideSendModal()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium">
                        Hủy
                    </button>
                    <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-paper-plane mr-2"></i> Gửi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showSendModal() {
    document.getElementById('sendModal').classList.remove('hidden');
    document.getElementById('sendForm').reset();
    toggleTargetOptions();
}

function hideSendModal() {
    document.getElementById('sendModal').classList.add('hidden');
}

function toggleTargetOptions() {
    const targetType = document.getElementById('targetType').value;
    const selectedUsersDiv = document.getElementById('selectedUsersDiv');
    const contestDiv = document.getElementById('contestDiv');

    selectedUsersDiv.classList.add('hidden');
    contestDiv.classList.add('hidden');

    if (targetType === 'selected') {
        selectedUsersDiv.classList.remove('hidden');
    } else if (targetType === 'contest') {
        contestDiv.classList.remove('hidden');
    }
}

function markAsRead(notificationId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="action" value="mark_read">
        <input type="hidden" name="notification_id" value="${notificationId}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function deleteNotification(notificationId) {
    if (confirm('Bạn có chắc chắn muốn xóa thông báo này?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="delete_notification">
            <input type="hidden" name="notification_id" value="${notificationId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking outside
document.getElementById('sendModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideSendModal();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
