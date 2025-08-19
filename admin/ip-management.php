<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-security.php';

// Require only admin key verification
requireAdminKey();

$page_title = 'Quản lý IP';
include '../../includes/header.php';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCSRFToken()) {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'block_ip':
            $ip_address = sanitizeInput($_POST['ip_address'] ?? '');
            $reason = sanitizeInput($_POST['reason'] ?? '');
            $duration = intval($_POST['duration'] ?? 0);

            if ($ip_address && $reason) {
                $expires_at = $duration > 0 ? date('Y-m-d H:i:s', strtotime("+{$duration} days")) : null;

                if (blockIP($ip_address, $reason, $expires_at)) {
                    setFlashMessage('success', 'Đã chặn IP thành công.');
                } else {
                    setFlashMessage('error', 'Có lỗi xảy ra khi chặn IP.');
                }
            } else {
                setFlashMessage('error', 'Vui lòng điền đầy đủ thông tin.');
            }
            break;

        case 'unblock_ip':
            $ip_address = sanitizeInput($_POST['ip_address'] ?? '');

            if ($ip_address) {
                $stmt = $pdo->prepare("DELETE FROM blocked_ips WHERE ip_address = ?");
                if ($stmt->execute([$ip_address])) {
                    setFlashMessage('success', 'Đã bỏ chặn IP thành công.');
                } else {
                    setFlashMessage('error', 'Có lỗi xảy ra khi bỏ chặn IP.');
                }
            }
            break;

        case 'clear_expired':
            $stmt = $pdo->prepare("DELETE FROM blocked_ips WHERE expires_at IS NOT NULL AND expires_at < NOW()");
            if ($stmt->execute()) {
                $deleted_count = $stmt->rowCount();
                setFlashMessage('success', "Đã xóa {$deleted_count} IP hết hạn.");
            } else {
                setFlashMessage('error', 'Có lỗi xảy ra khi xóa IP hết hạn.');
            }
            break;
    }
}

// Get filters
$page = max(1, intval($_GET['page'] ?? 1));
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'sort' => $_GET['sort'] ?? 'created_at',
    'order' => $_GET['order'] ?? 'DESC'
];

// Get blocked IPs with filters
$where_conditions = [];
$params = [];

if (!empty($filters['search'])) {
    $where_conditions[] = "(ip_address LIKE ? OR reason LIKE ?)";
    $search_term = '%' . $filters['search'] . '%';
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($filters['status'])) {
    if ($filters['status'] === 'active') {
        $where_conditions[] = "(expires_at IS NULL OR expires_at > NOW())";
    } elseif ($filters['status'] === 'expired') {
        $where_conditions[] = "expires_at IS NOT NULL AND expires_at <= NOW()";
    }
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get total count
$count_sql = "SELECT COUNT(*) FROM blocked_ips $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total = $stmt->fetchColumn();

// Get blocked IPs
$offset = ($page - 1) * ITEMS_PER_PAGE;
$sql = "SELECT * FROM blocked_ips $where_clause ORDER BY {$filters['sort']} {$filters['order']} LIMIT ? OFFSET ?";
$params[] = ITEMS_PER_PAGE;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$blocked_ips = $stmt->fetchAll();

$total_pages = ceil($total / ITEMS_PER_PAGE);

// Get statistics
$stmt = $pdo->query("SELECT
    COUNT(*) as total_blocked,
    COUNT(CASE WHEN expires_at IS NULL OR expires_at > NOW() THEN 1 END) as active_blocked,
    COUNT(CASE WHEN expires_at IS NOT NULL AND expires_at <= NOW() THEN 1 END) as expired_blocked,
    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as today_blocked
    FROM blocked_ips");
$stats = $stmt->fetch();
?>

<!-- Admin Header -->
<div class="bg-gray-800 border-b border-gray-700 mb-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-white">Quản lý IP</h1>
                <p class="text-gray-400 mt-1">Quản lý danh sách IP bị chặn</p>
            </div>
            <div class="flex space-x-4">
                <button onclick="showBlockModal()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-ban mr-2"></i> Chặn IP
                </button>
                <button onclick="clearExpired()" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-broom mr-2"></i> Xóa IP hết hạn
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Statistics -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-500/10">
                <i class="fas fa-ban text-red-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Tổng IP bị chặn</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['total_blocked']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-orange-500/10">
                <i class="fas fa-clock text-orange-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Đang chặn</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['active_blocked']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-gray-500/10">
                <i class="fas fa-hourglass-end text-gray-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Hết hạn</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['expired_blocked']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-500/10">
                <i class="fas fa-calendar-day text-blue-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Hôm nay</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['today_blocked']; ?></p>
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
                       placeholder="IP, lý do..."
                       class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Trạng thái</label>
                <select name="status" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                    <option value="">Tất cả</option>
                    <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Đang chặn</option>
                    <option value="expired" <?php echo $filters['status'] === 'expired' ? 'selected' : ''; ?>>Hết hạn</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Sắp xếp</label>
                <select name="sort" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                    <option value="created_at" <?php echo $filters['sort'] === 'created_at' ? 'selected' : ''; ?>>Ngày tạo</option>
                    <option value="ip_address" <?php echo $filters['sort'] === 'ip_address' ? 'selected' : ''; ?>>Địa chỉ IP</option>
                    <option value="expires_at" <?php echo $filters['sort'] === 'expires_at' ? 'selected' : ''; ?>>Ngày hết hạn</option>
                </select>
            </div>

            <div class="flex items-end space-x-2">
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-filter mr-2"></i> Lọc
                </button>
                <a href="<?php echo APP_URL; ?>/admin/ip-management" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-times mr-2"></i> Xóa
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Blocked IPs Table -->
<div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-700">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-white">Danh sách IP bị chặn</h2>
                <p class="text-gray-400 text-sm mt-1">Tổng cộng: <?php echo $total; ?> IP</p>
            </div>
        </div>
    </div>

    <?php if (empty($blocked_ips)): ?>
        <div class="text-center py-12">
            <i class="fas fa-shield-alt text-gray-500 text-4xl mb-4"></i>
            <p class="text-gray-400">Không có IP nào bị chặn</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Địa chỉ IP
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Lý do
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Trạng thái
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Ngày tạo
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Hết hạn
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Thao tác
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-gray-800 divide-y divide-gray-700">
                    <?php foreach ($blocked_ips as $ip): ?>
                        <tr class="hover:bg-gray-700 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-white font-mono">
                                    <?php echo htmlspecialchars($ip['ip_address']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-300 max-w-md">
                                    <?php echo htmlspecialchars($ip['reason']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $is_expired = $ip['expires_at'] && strtotime($ip['expires_at']) <= time();
                                $is_permanent = !$ip['expires_at'];
                                ?>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    <?php
                                    echo $is_expired ? 'bg-gray-100 text-gray-800' :
                                        ($is_permanent ? 'bg-red-100 text-red-800' : 'bg-orange-100 text-orange-800');
                                    ?>">
                                    <?php
                                    echo $is_expired ? 'Hết hạn' :
                                        ($is_permanent ? 'Vĩnh viễn' : 'Tạm thời');
                                    ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                <?php echo formatDate($ip['created_at']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                <?php echo $ip['expires_at'] ? formatDate($ip['expires_at']) : 'Vĩnh viễn'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="unblockIP('<?php echo htmlspecialchars($ip['ip_address']); ?>')"
                                        class="text-green-400 hover:text-green-300" title="Bỏ chặn">
                                    <i class="fas fa-unlock"></i>
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
                <?php echo generatePagination($page, $total_pages, APP_URL . '/admin/ip-management?' . http_build_query($filters)); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Block IP Modal -->
<div id="blockModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-white">Chặn IP</h3>
                <button onclick="hideBlockModal()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form method="POST" id="blockForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="block_ip">

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Địa chỉ IP *</label>
                        <input type="text" name="ip_address" required
                               placeholder="192.168.1.1"
                               class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Lý do chặn *</label>
                        <textarea name="reason" rows="3" required
                                  placeholder="Nhập lý do chặn IP"
                                  class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Thời gian chặn</label>
                        <select name="duration" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                            <option value="0">Vĩnh viễn</option>
                            <option value="1">1 ngày</option>
                            <option value="7">7 ngày</option>
                            <option value="30">30 ngày</option>
                            <option value="90">90 ngày</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="hideBlockModal()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium">
                        Hủy
                    </button>
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-ban mr-2"></i> Chặn IP
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showBlockModal() {
    document.getElementById('blockModal').classList.remove('hidden');
    document.getElementById('blockForm').reset();
}

function hideBlockModal() {
    document.getElementById('blockModal').classList.add('hidden');
}

function unblockIP(ipAddress) {
    if (confirm(`Bạn có chắc chắn muốn bỏ chặn IP ${ipAddress}?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="unblock_ip">
            <input type="hidden" name="ip_address" value="${ipAddress}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function clearExpired() {
    if (confirm('Bạn có chắc chắn muốn xóa tất cả IP đã hết hạn?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="clear_expired">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking outside
document.getElementById('blockModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideBlockModal();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
