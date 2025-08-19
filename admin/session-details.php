<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-security.php';

// Require only admin key verification
requireAdminKey();

$page_title = 'Chi tiết Phiên Đăng nhập';
include '../../includes/header.php';

$session_id = $_GET['id'] ?? '';
if (!$session_id) {
    setFlashMessage('error', 'Không tìm thấy phiên đăng nhập.');
    redirect(APP_URL . '/admin/session-management');
}

// Get session details
$session_details = getSessionDetails($session_id);
if (!$session_details) {
    setFlashMessage('error', 'Không tìm thấy phiên đăng nhập.');
    redirect(APP_URL . '/admin/session-management');
}

$session = $session_details['session'];
$logs = $session_details['logs'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCSRFToken()) {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'block_ip':
            $reason = sanitizeInput($_POST['reason'] ?? '');
            if (blockIP($session['user_ip'], $reason)) {
                setFlashMessage('success', 'Đã chặn IP thành công.');
                redirect(APP_URL . '/admin/session-details?id=' . $session_id);
            } else {
                setFlashMessage('error', 'Có lỗi xảy ra khi chặn IP.');
            }
            break;
    }
}

// Check if IP is blocked
$blocked_ip = isIPBlocked($session['user_ip']);
?>

<!-- Admin Header -->
<div class="bg-gray-800 border-b border-gray-700 mb-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-white">Chi tiết Phiên Đăng nhập</h1>
                <p class="text-gray-400 mt-1">Thông tin chi tiết về phiên đăng nhập</p>
            </div>
            <div class="flex space-x-4">
                <a href="<?php echo APP_URL; ?>/admin/session-management" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-arrow-left mr-2"></i> Quay lại
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Session Information -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Main Session Info -->
    <div class="lg:col-span-2">
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h2 class="text-xl font-semibold text-white mb-6">Thông tin phiên</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Session ID</label>
                    <p class="text-white font-mono text-sm"><?php echo htmlspecialchars($session['session_id']); ?></p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Nền tảng</label>
                    <div class="flex items-center">
                        <i class="fab fa-<?php echo $session['platform']; ?> text-2xl mr-2 text-blue-400"></i>
                        <span class="text-white"><?php echo ucfirst($session['platform']); ?></span>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Tài khoản</label>
                    <p class="text-white"><?php echo htmlspecialchars($session['username']); ?></p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Trạng thái</label>
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                        <?php
                        echo $session['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                            ($session['status'] === 'processing' ? 'bg-blue-100 text-blue-800' :
                            ($session['status'] === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'));
                        ?>">
                        <?php
                        echo $session['status'] === 'pending' ? 'Chờ xử lý' :
                            ($session['status'] === 'processing' ? 'Đang xử lý' :
                            ($session['status'] === 'success' ? 'Thành công' : 'Thất bại'));
                        ?>
                    </span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Thời gian tạo</label>
                    <p class="text-white"><?php echo formatDate($session['created_at']); ?></p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Cập nhật cuối</label>
                    <p class="text-white"><?php echo formatDate($session['updated_at']); ?></p>
                </div>

                <?php if ($session['details']): ?>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-400 mb-1">Chi tiết</label>
                        <p class="text-white text-sm"><?php echo htmlspecialchars($session['details']); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Device Information -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6 mt-6">
            <h2 class="text-xl font-semibold text-white mb-6">Thông tin thiết bị</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Địa chỉ IP</label>
                    <div class="flex items-center space-x-2">
                        <p class="text-white font-mono"><?php echo htmlspecialchars($session['user_ip']); ?></p>
                        <?php if ($blocked_ip): ?>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                Bị chặn
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Loại thiết bị</label>
                    <p class="text-white capitalize"><?php echo $session['device_type']; ?></p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Trình duyệt</label>
                    <p class="text-white"><?php echo htmlspecialchars($session['browser']); ?></p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Hệ điều hành</label>
                    <p class="text-white"><?php echo htmlspecialchars($session['os']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions Sidebar -->
    <div class="space-y-6">
        <!-- IP Actions -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Thao tác IP</h3>

            <?php if ($blocked_ip): ?>
                <div class="mb-4 p-3 bg-red-900/20 border border-red-700 rounded-lg">
                    <p class="text-red-400 text-sm">
                        <i class="fas fa-ban mr-1"></i>
                        IP này đã bị chặn: <?php echo htmlspecialchars($blocked_ip['reason']); ?>
                    </p>
                </div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="block_ip">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Lý do chặn</label>
                        <textarea name="reason" rows="3" required
                                  placeholder="Nhập lý do chặn IP"
                                  class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full"></textarea>
                    </div>

                    <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-ban mr-2"></i> Chặn IP
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Session Statistics -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Thống kê</h3>

            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-400">Tổng log:</span>
                    <span class="text-white"><?php echo count($logs); ?></span>
                </div>

                <div class="flex justify-between">
                    <span class="text-gray-400">Thời gian xử lý:</span>
                    <span class="text-white">
                        <?php
                        $duration = strtotime($session['updated_at']) - strtotime($session['created_at']);
                        echo $duration > 0 ? gmdate('H:i:s', $duration) : 'N/A';
                        ?>
                    </span>
                </div>

                <div class="flex justify-between">
                    <span class="text-gray-400">Trạng thái cuối:</span>
                    <span class="text-white capitalize"><?php echo $session['status']; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Session Logs -->
<div class="mt-8">
    <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-700">
            <h2 class="text-xl font-semibold text-white">Lịch sử hoạt động</h2>
        </div>

        <?php if (empty($logs)): ?>
            <div class="text-center py-12">
                <i class="fas fa-history text-gray-500 text-4xl mb-4"></i>
                <p class="text-gray-400">Không có log nào</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                Thời gian
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                Hành động
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                Chi tiết
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                Trạng thái
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-gray-800 divide-y divide-gray-700">
                        <?php foreach ($logs as $log): ?>
                            <tr class="hover:bg-gray-700 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                    <?php echo formatDate($log['created_at']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-white font-medium"><?php echo htmlspecialchars($log['action']); ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-sm text-gray-300 max-w-md">
                                        <?php echo htmlspecialchars($log['details']); ?>
                                    </p>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                        <?php
                                        echo $log['status'] === 'success' ? 'bg-green-100 text-green-800' :
                                            ($log['status'] === 'error' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800');
                                        ?>">
                                        <?php
                                        echo $log['status'] === 'success' ? 'Thành công' :
                                            ($log['status'] === 'error' ? 'Lỗi' : 'Thông tin');
                                        ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
