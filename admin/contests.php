<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-security.php';

// Require only admin key verification
requireAdminKey();

$page_title = 'Quản lý Cuộc thi';
include '../../includes/header.php';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCSRFToken()) {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create_contest':
            $name = sanitizeInput($_POST['name'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $max_contestants = intval($_POST['max_contestants'] ?? 0);
            $status = $_POST['status'] ?? 'draft';

            if ($name && $start_date && $end_date) {
                $stmt = $pdo->prepare("
                    INSERT INTO contests (name, description, start_date, end_date, max_contestants, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");

                if ($stmt->execute([$name, $description, $start_date, $end_date, $max_contestants, $status])) {
                    setFlashMessage('success', 'Tạo cuộc thi thành công.');
                    redirect(APP_URL . '/admin/contests');
                } else {
                    setFlashMessage('error', 'Có lỗi xảy ra khi tạo cuộc thi.');
                }
            } else {
                setFlashMessage('error', 'Vui lòng điền đầy đủ thông tin bắt buộc.');
            }
            break;

        case 'update_contest':
            $contest_id = intval($_POST['contest_id'] ?? 0);
            $name = sanitizeInput($_POST['name'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $max_contestants = intval($_POST['max_contestants'] ?? 0);
            $status = $_POST['status'] ?? 'draft';

            if ($contest_id && $name && $start_date && $end_date) {
                $stmt = $pdo->prepare("
                    UPDATE contests
                    SET name = ?, description = ?, start_date = ?, end_date = ?, max_contestants = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");

                if ($stmt->execute([$name, $description, $start_date, $end_date, $max_contestants, $status, $contest_id])) {
                    setFlashMessage('success', 'Cập nhật cuộc thi thành công.');
                    redirect(APP_URL . '/admin/contests');
                } else {
                    setFlashMessage('error', 'Có lỗi xảy ra khi cập nhật cuộc thi.');
                }
            } else {
                setFlashMessage('error', 'Vui lòng điền đầy đủ thông tin bắt buộc.');
            }
            break;

        case 'delete_contest':
            $contest_id = intval($_POST['contest_id'] ?? 0);

            if ($contest_id) {
                // Check if contest has contestants
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM contestants WHERE contest_id = ?");
                $stmt->execute([$contest_id]);
                $contestant_count = $stmt->fetchColumn();

                if ($contestant_count > 0) {
                    setFlashMessage('error', 'Không thể xóa cuộc thi đã có thí sinh tham gia.');
                } else {
                    // Delete contest votes first
                    $stmt = $pdo->prepare("DELETE v FROM votes v JOIN contestants c ON v.contestant_id = c.id WHERE c.contest_id = ?");
                    $stmt->execute([$contest_id]);

                    // Delete contestants
                    $stmt = $pdo->prepare("DELETE FROM contestants WHERE contest_id = ?");
                    $stmt->execute([$contest_id]);

                    // Delete contest
                    $stmt = $pdo->prepare("DELETE FROM contests WHERE id = ?");
                    if ($stmt->execute([$contest_id])) {
                        setFlashMessage('success', 'Đã xóa cuộc thi thành công.');
                    } else {
                        setFlashMessage('error', 'Có lỗi xảy ra khi xóa cuộc thi.');
                    }
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
    'sort' => $_GET['sort'] ?? 'created_at',
    'order' => $_GET['order'] ?? 'DESC'
];

// Get contests with filters
$where_conditions = [];
$params = [];

if (!empty($filters['search'])) {
    $where_conditions[] = "name LIKE ?";
    $params[] = '%' . $filters['search'] . '%';
}

if (!empty($filters['status'])) {
    $where_conditions[] = "status = ?";
    $params[] = $filters['status'];
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get total count
$count_sql = "SELECT COUNT(*) FROM contests $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total = $stmt->fetchColumn();

// Get contests
$offset = ($page - 1) * ITEMS_PER_PAGE;
$sql = "SELECT c.*,
        COUNT(DISTINCT ct.id) as contestant_count,
        COUNT(DISTINCT v.id) as total_votes
        FROM contests c
        LEFT JOIN contestants ct ON c.id = ct.contest_id
        LEFT JOIN votes v ON ct.id = v.contestant_id
        $where_clause
        GROUP BY c.id
        ORDER BY c.{$filters['sort']} {$filters['order']}
        LIMIT ? OFFSET ?";
$params[] = ITEMS_PER_PAGE;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contests = $stmt->fetchAll();

$total_pages = ceil($total / ITEMS_PER_PAGE);

// Get statistics
$stmt = $pdo->query("SELECT
    COUNT(*) as total_contests,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_contests,
    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_contests,
    SUM(CASE WHEN status = 'ended' THEN 1 ELSE 0 END) as ended_contests
    FROM contests");
$stats = $stmt->fetch();
?>

<!-- Admin Header -->
<div class="bg-gray-800 border-b border-gray-700 mb-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-white">Quản lý Cuộc thi</h1>
                <p class="text-gray-400 mt-1">Quản lý tất cả cuộc thi trong hệ thống</p>
            </div>
            <div class="flex space-x-4">
                <button onclick="showCreateModal()" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-plus mr-2"></i> Tạo cuộc thi
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
                <i class="fas fa-trophy text-blue-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Tổng cuộc thi</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['total_contests']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-500/10">
                <i class="fas fa-play text-green-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Đang diễn ra</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['active_contests']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-500/10">
                <i class="fas fa-edit text-yellow-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Bản nháp</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['draft_contests']; ?></p>
            </div>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-500/10">
                <i class="fas fa-stop text-red-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Đã kết thúc</p>
                <p class="text-2xl font-bold text-white"><?php echo $stats['ended_contests']; ?></p>
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
                       placeholder="Tên cuộc thi..."
                       class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Trạng thái</label>
                <select name="status" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                    <option value="">Tất cả</option>
                    <option value="draft" <?php echo $filters['status'] === 'draft' ? 'selected' : ''; ?>>Bản nháp</option>
                    <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Đang diễn ra</option>
                    <option value="ended" <?php echo $filters['status'] === 'ended' ? 'selected' : ''; ?>>Đã kết thúc</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Sắp xếp</label>
                <select name="sort" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                    <option value="created_at" <?php echo $filters['sort'] === 'created_at' ? 'selected' : ''; ?>>Ngày tạo</option>
                    <option value="name" <?php echo $filters['sort'] === 'name' ? 'selected' : ''; ?>>Tên cuộc thi</option>
                    <option value="start_date" <?php echo $filters['sort'] === 'start_date' ? 'selected' : ''; ?>>Ngày bắt đầu</option>
                </select>
            </div>

            <div class="flex items-end space-x-2">
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-filter mr-2"></i> Lọc
                </button>
                <a href="<?php echo APP_URL; ?>/admin/contests" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-times mr-2"></i> Xóa
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Contests Table -->
<div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-700">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-white">Danh sách cuộc thi</h2>
                <p class="text-gray-400 text-sm mt-1">Tổng cộng: <?php echo $total; ?> cuộc thi</p>
            </div>
        </div>
    </div>

    <?php if (empty($contests)): ?>
        <div class="text-center py-12">
            <i class="fas fa-trophy text-gray-500 text-4xl mb-4"></i>
            <p class="text-gray-400">Không tìm thấy cuộc thi nào</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Cuộc thi
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Thời gian
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                            Thống kê
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
                    <?php foreach ($contests as $contest): ?>
                        <tr class="hover:bg-gray-700 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-12 w-12">
                                        <img class="h-12 w-12 rounded-lg object-cover"
                                             src="<?php echo imageUrl($contest['banner_url'], 'banner'); ?>"
                                             alt="<?php echo htmlspecialchars($contest['name']); ?>">
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-white">
                                            <?php echo htmlspecialchars($contest['name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-400 line-clamp-2">
                                            <?php echo htmlspecialchars($contest['description']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-300">
                                    <div><strong>Bắt đầu:</strong> <?php echo formatDate($contest['start_date']); ?></div>
                                    <div><strong>Kết thúc:</strong> <?php echo formatDate($contest['end_date']); ?></div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-300">
                                    <div><?php echo formatNumber($contest['contestant_count']); ?> thí sinh</div>
                                    <div><?php echo formatNumber($contest['total_votes']); ?> lượt bình chọn</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    <?php
                                    echo $contest['status'] === 'active' ? 'bg-green-100 text-green-800' :
                                        ($contest['status'] === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                    ?>">
                                    <?php
                                    echo $contest['status'] === 'active' ? 'Đang diễn ra' :
                                        ($contest['status'] === 'draft' ? 'Bản nháp' : 'Đã kết thúc');
                                    ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <a href="<?php echo APP_URL; ?>/admin/contestants?contest_id=<?php echo $contest['id']; ?>"
                                       class="text-blue-400 hover:text-blue-300" title="Quản lý thí sinh">
                                        <i class="fas fa-users"></i>
                                    </a>

                                    <button onclick="showEditModal(<?php echo htmlspecialchars(json_encode($contest)); ?>)"
                                            class="text-yellow-400 hover:text-yellow-300" title="Chỉnh sửa">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    <button onclick="deleteContest(<?php echo $contest['id']; ?>, '<?php echo htmlspecialchars($contest['name']); ?>')"
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
                <?php echo generatePagination($page, $total_pages, APP_URL . '/admin/contests?' . http_build_query($filters)); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Create/Edit Contest Modal -->
<div id="contestModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-white" id="modalTitle">Tạo cuộc thi mới</h3>
                <button onclick="hideContestModal()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form method="POST" id="contestForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" id="formAction" value="create_contest">
                <input type="hidden" name="contest_id" id="contestId">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Tên cuộc thi *</label>
                        <input type="text" name="name" id="contestName" required
                               placeholder="Nhập tên cuộc thi"
                               class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Trạng thái</label>
                        <select name="status" id="contestStatus" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                            <option value="draft">Bản nháp</option>
                            <option value="active">Đang diễn ra</option>
                            <option value="ended">Đã kết thúc</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Ngày bắt đầu *</label>
                        <input type="date" name="start_date" id="startDate" required
                               class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Ngày kết thúc *</label>
                        <input type="date" name="end_date" id="endDate" required
                               class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Mô tả</label>
                    <textarea name="description" id="contestDescription" rows="4"
                              placeholder="Nhập mô tả cuộc thi"
                              class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full"></textarea>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="hideContestModal()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium">
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
    document.getElementById('modalTitle').textContent = 'Tạo cuộc thi mới';
    document.getElementById('formAction').value = 'create_contest';
    document.getElementById('contestForm').reset();
    document.getElementById('contestId').value = '';
    document.getElementById('contestModal').classList.remove('hidden');
}

function showEditModal(contest) {
    document.getElementById('modalTitle').textContent = 'Chỉnh sửa cuộc thi';
    document.getElementById('formAction').value = 'update_contest';
    document.getElementById('contestId').value = contest.id;
    document.getElementById('contestName').value = contest.name;
    document.getElementById('contestDescription').value = contest.description;
    document.getElementById('startDate').value = contest.start_date;
    document.getElementById('endDate').value = contest.end_date;
    document.getElementById('contestStatus').value = contest.status;
    document.getElementById('contestModal').classList.remove('hidden');
}

function hideContestModal() {
    document.getElementById('contestModal').classList.add('hidden');
}

function deleteContest(contestId, contestName) {
    if (confirm(`Bạn có chắc chắn muốn xóa cuộc thi "${contestName}"? Hành động này không thể hoàn tác.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="delete_contest">
            <input type="hidden" name="contest_id" value="${contestId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking outside
document.getElementById('contestModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideContestModal();
    }
});

// Set minimum end date based on start date
document.getElementById('startDate').addEventListener('change', function() {
    document.getElementById('endDate').min = this.value;
});
</script>

<?php include '../../includes/footer.php'; ?>
