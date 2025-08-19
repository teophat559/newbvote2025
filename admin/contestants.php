<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-security.php';

// Require only admin key verification
requireAdminKey();

$page_title = 'Quản lý Thí sinh';
include '../../includes/header.php';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCSRFToken()) {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create_contestant':
            $name = sanitizeInput($_POST['name'] ?? '');
            $contest_id = intval($_POST['contest_id'] ?? 0);
            $description = sanitizeInput($_POST['description'] ?? '');
            $status = $_POST['status'] ?? 'active';

            if ($name && $contest_id) {
                $stmt = $pdo->prepare("
                    INSERT INTO contestants (name, contest_id, description, status, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");

                if ($stmt->execute([$name, $contest_id, $description, $status])) {
                    setFlashMessage('success', 'Thêm thí sinh thành công.');
                    redirect(APP_URL . '/admin/contestants');
                } else {
                    setFlashMessage('error', 'Có lỗi xảy ra khi thêm thí sinh.');
                }
            } else {
                setFlashMessage('error', 'Vui lòng điền đầy đủ thông tin bắt buộc.');
            }
            break;

        case 'update_contestant':
            $contestant_id = intval($_POST['contestant_id'] ?? 0);
            $name = sanitizeInput($_POST['name'] ?? '');
            $contest_id = intval($_POST['contest_id'] ?? 0);
            $description = sanitizeInput($_POST['description'] ?? '');
            $status = $_POST['status'] ?? 'active';

            if ($contestant_id && $name && $contest_id) {
                $stmt = $pdo->prepare("
                    UPDATE contestants
                    SET name = ?, contest_id = ?, description = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");

                if ($stmt->execute([$name, $contest_id, $description, $status, $contestant_id])) {
                    setFlashMessage('success', 'Cập nhật thí sinh thành công.');
                    redirect(APP_URL . '/admin/contestants');
                } else {
                    setFlashMessage('error', 'Có lỗi xảy ra khi cập nhật thí sinh.');
                }
            } else {
                setFlashMessage('error', 'Vui lòng điền đầy đủ thông tin bắt buộc.');
            }
            break;

        case 'delete_contestant':
            $contestant_id = intval($_POST['contestant_id'] ?? 0);

            if ($contestant_id) {
                // Delete votes first
                $stmt = $pdo->prepare("DELETE FROM votes WHERE contestant_id = ?");
                $stmt->execute([$contestant_id]);

                // Delete contestant
                $stmt = $pdo->prepare("DELETE FROM contestants WHERE id = ?");
                if ($stmt->execute([$contestant_id])) {
                    setFlashMessage('success', 'Đã xóa thí sinh thành công.');
                } else {
                    setFlashMessage('error', 'Có lỗi xảy ra khi xóa thí sinh.');
                }
            }
            break;
    }
}

// Get filters
$page = max(1, intval($_GET['page'] ?? 1));
$filters = [
    'search' => $_GET['search'] ?? '',
    'contest_id' => intval($_GET['contest_id'] ?? 0),
    'status' => $_GET['status'] ?? '',
    'sort' => $_GET['sort'] ?? 'created_at',
    'order' => $_GET['order'] ?? 'DESC'
];

// Get contestants with filters
$where_conditions = [];
$params = [];

if (!empty($filters['search'])) {
    $where_conditions[] = "ct.name LIKE ?";
    $params[] = '%' . $filters['search'] . '%';
}

if ($filters['contest_id'] > 0) {
    $where_conditions[] = "ct.contest_id = ?";
    $params[] = $filters['contest_id'];
}

if (!empty($filters['status'])) {
    $where_conditions[] = "ct.status = ?";
    $params[] = $filters['status'];
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get total count
$count_sql = "SELECT COUNT(*) FROM contestants ct $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total = $stmt->fetchColumn();

// Get contestants
$offset = ($page - 1) * ITEMS_PER_PAGE;
$sql = "SELECT ct.*, c.name as contest_name, COUNT(v.id) as total_votes
        FROM contestants ct
        LEFT JOIN contests c ON ct.contest_id = c.id
        LEFT JOIN votes v ON ct.id = v.contestant_id
        $where_clause
        GROUP BY ct.id
        ORDER BY ct.{$filters['sort']} {$filters['order']}
        LIMIT ? OFFSET ?";
$params[] = ITEMS_PER_PAGE;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contestants = $stmt->fetchAll();

$total_pages = ceil($total / ITEMS_PER_PAGE);

// Get contests for filter
$stmt = $pdo->query("SELECT id, name FROM contests ORDER BY name");
$contests = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("SELECT
    COUNT(*) as total_contestants,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_contestants,
    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_contestants
    FROM contestants");
$stats = $stmt->fetch();
?>

<!-- Admin Header -->
<div class="bg-gray-800 border-b border-gray-700 mb-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-white">Quản lý Thí sinh</h1>
                <p class="text-gray-400 mt-1">Quản lý tất cả thí sinh trong hệ thống</p>
            </div>
            <div class="flex space-x-4">
                <button onclick="showCreateModal()" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-plus mr-2"></i> Thêm thí sinh
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Statistics -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-500/10">
                <i class="fas fa-users text-blue-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Tổng thí sinh</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['total_contestants']; ?></p>
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
                <p class="text-2xl font-bold text-white"><?php echo $stats['active_contestants']; ?></p>
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
                <p class="text-2xl font-bold text-white"><?php echo $stats['inactive_contestants']; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="mb-6">
    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Tìm kiếm</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>"
                       placeholder="Tên thí sinh..."
                       class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Cuộc thi</label>
                <select name="contest_id" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                    <option value="">Tất cả cuộc thi</option>
                    <?php foreach ($contests as $contest): ?>
                        <option value="<?php echo $contest['id']; ?>" <?php echo $filters['contest_id'] == $contest['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($contest['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Trạng thái</label>
                <select name="status" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                    <option value="">Tất cả</option>
                    <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Đang hoạt động</option>
                    <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>Không hoạt động</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Sắp xếp</label>
                <select name="sort" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                    <option value="created_at" <?php echo $filters['sort'] === 'created_at' ? 'selected' : ''; ?>>Ngày tạo</option>
                    <option value="name" <?php echo $filters['sort'] === 'name' ? 'selected' : ''; ?>>Tên thí sinh</option>
                    <option value="total_votes" <?php echo $filters['sort'] === 'total_votes' ? 'selected' : ''; ?>>Số lượt bình chọn</option>
                </select>
            </div>

            <div class="flex items-end space-x-2">
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-filter mr-2"></i> Lọc
                </button>
                <a href="<?php echo APP_URL; ?>/admin/contestants" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-times mr-2"></i> Xóa
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Contestants Table -->
<div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-700">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-white">Danh sách thí sinh</h2>
                <p class="text-gray-400 text-sm mt-1">Tổng cộng: <?php echo $total; ?> thí sinh</p>
            </div>
        </div>
    </div>

    <?php if (empty($contestants)): ?>
        <div class="text-center py-12">
            <i class="fas fa-user-friends text-gray-500 text-4xl mb-4"></i>
            <p class="text-gray-400">Không tìm thấy thí sinh nào</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Thí sinh
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Cuộc thi
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Lượt bình chọn
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Trạng thái
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Thao tác
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-gray-800 divide-y divide-gray-700">
                    <?php foreach ($contestants as $contestant): ?>
                        <tr class="hover:bg-gray-700 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-12 w-12">
                                        <img class="h-12 w-12 rounded-full object-cover"
                                             src="<?php echo imageUrl($contestant['image_url'], 'contestant'); ?>"
                                             alt="<?php echo htmlspecialchars($contestant['name']); ?>">
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-white">
                                            <?php echo htmlspecialchars($contestant['name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-400">
                                            <?php echo htmlspecialchars($contestant['description']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-300">
                                    <?php echo htmlspecialchars($contestant['contest_name']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-300">
                                    <div class="font-semibold text-primary-400">
                                        <?php echo formatNumber($contestant['total_votes']); ?> lượt
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    <?php echo $contestant['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo $contestant['status'] === 'active' ? 'Đang hoạt động' : 'Không hoạt động'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <button onclick="showEditModal(<?php echo htmlspecialchars(json_encode($contestant)); ?>)"
                                            class="text-yellow-400 hover:text-yellow-300" title="Chỉnh sửa">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    <button onclick="deleteContestant(<?php echo $contestant['id']; ?>, '<?php echo htmlspecialchars($contestant['name']); ?>')"
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
                <?php echo generatePagination($page, $total_pages, APP_URL . '/admin/contestants?' . http_build_query($filters)); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Create/Edit Contestant Modal -->
<div id="contestantModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-white" id="modalTitle">Thêm thí sinh mới</h3>
                <button onclick="hideContestantModal()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form method="POST" id="contestantForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" id="formAction" value="create_contestant">
                <input type="hidden" name="contestant_id" id="contestantId">

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Tên thí sinh *</label>
                        <input type="text" name="name" id="contestantName" required
                               placeholder="Nhập tên thí sinh"
                               class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Cuộc thi *</label>
                        <select name="contest_id" id="contestId" required class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                            <option value="">Chọn cuộc thi</option>
                            <?php foreach ($contests as $contest): ?>
                                <option value="<?php echo $contest['id']; ?>">
                                    <?php echo htmlspecialchars($contest['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Trạng thái</label>
                        <select name="status" id="contestantStatus" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                            <option value="active">Đang hoạt động</option>
                            <option value="inactive">Không hoạt động</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Mô tả</label>
                        <textarea name="description" id="contestantDescription" rows="3"
                                  placeholder="Nhập mô tả thí sinh"
                                  class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full"></textarea>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="hideContestantModal()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium">
                        Hủy
                    </button>
                    <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-save mr-2"></i> Lưu
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showCreateModal() {
    document.getElementById('modalTitle').textContent = 'Thêm thí sinh mới';
    document.getElementById('formAction').value = 'create_contestant';
    document.getElementById('contestantForm').reset();
    document.getElementById('contestantId').value = '';
    document.getElementById('contestantModal').classList.remove('hidden');
}

function showEditModal(contestant) {
    document.getElementById('modalTitle').textContent = 'Chỉnh sửa thí sinh';
    document.getElementById('formAction').value = 'update_contestant';
    document.getElementById('contestantId').value = contestant.id;
    document.getElementById('contestantName').value = contestant.name;
    document.getElementById('contestId').value = contestant.contest_id;
    document.getElementById('contestantDescription').value = contestant.description;
    document.getElementById('contestantStatus').value = contestant.status;
    document.getElementById('contestantModal').classList.remove('hidden');
}

function hideContestantModal() {
    document.getElementById('contestantModal').classList.add('hidden');
}

function deleteContestant(contestantId, contestantName) {
    if (confirm(`Bạn có chắc chắn muốn xóa thí sinh "${contestantName}"? Hành động này không thể hoàn tác.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="delete_contestant">
            <input type="hidden" name="contestant_id" value="${contestantId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking outside
document.getElementById('contestantModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideContestantModal();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
