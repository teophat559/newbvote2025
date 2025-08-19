<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-security.php';

// Require only admin key verification
requireAdminKey();

$page_title = 'Dashboard';
include '../../includes/header.php';

// Get statistics
$stats = getStatistics();
$recent_activity = getUserActivity(null, 10);
$recent_contests = getContests('active', 5);
$top_contestants = getTopContestants(5);

// Handle quick actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCSRFToken()) {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create_contest':
            // Redirect to contest creation
            redirect(APP_URL . '/admin/contests?action=create');
            break;
        case 'create_contestant':
            // Redirect to contestant creation
            redirect(APP_URL . '/admin/contestants?action=create');
            break;
        case 'send_notification':
            // Handle notification sending
            $user_id = $_POST['user_id'] ?? '';
            $title = sanitizeInput($_POST['title'] ?? '');
            $message = sanitizeInput($_POST['message'] ?? '');

            if ($user_id && $title && $message) {
                if (createNotification($user_id, $title, $message)) {
                    setFlashMessage('success', 'Thông báo đã được gửi thành công.');
                } else {
                    setFlashMessage('error', 'Có lỗi xảy ra khi gửi thông báo.');
                }
            }
            break;
    }
}
?>

<!-- Admin Header -->
<div class="bg-gray-800 border-b border-gray-700 mb-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-white">Dashboard</h1>
                <p class="text-gray-400 mt-1">Chào mừng trở lại, <?php echo getCurrentAdmin()['full_name'] ?: getCurrentAdmin()['username']; ?>!</p>
            </div>
            <div class="flex space-x-4">
                <a href="<?php echo APP_URL; ?>/admin/contests?action=create"
                   class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-plus mr-2"></i> Tạo cuộc thi
                </a>
                <a href="<?php echo APP_URL; ?>/admin/contestants?action=create"
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-user-plus mr-2"></i> Thêm thí sinh
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-500/10">
                <i class="fas fa-users text-blue-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Tổng người dùng</p>
                <p class="text-2xl font-bold text-white"><?php echo formatNumber($stats['total_users']); ?></p>
            </div>
        </div>
        <div class="mt-4">
            <span class="text-green-400 text-sm">
                <i class="fas fa-arrow-up mr-1"></i>
                +<?php echo $stats['today_registrations']; ?> hôm nay
            </span>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-500/10">
                <i class="fas fa-trophy text-green-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Cuộc thi đang diễn ra</p>
                <p class="text-2xl font-bold text-white"><?php echo formatNumber($stats['active_contests']); ?></p>
            </div>
        </div>
        <div class="mt-4">
            <span class="text-gray-400 text-sm">
                Tổng: <?php echo formatNumber($stats['total_contests']); ?> cuộc thi
            </span>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-500/10">
                <i class="fas fa-star text-yellow-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Tổng lượt bình chọn</p>
                <p class="text-2xl font-bold text-white"><?php echo formatNumber($stats['total_votes']); ?></p>
            </div>
        </div>
        <div class="mt-4">
            <span class="text-green-400 text-sm">
                <i class="fas fa-arrow-up mr-1"></i>
                +<?php echo $stats['today_votes']; ?> hôm nay
            </span>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-500/10">
                <i class="fas fa-user-friends text-purple-400 text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-gray-400 text-sm">Tổng thí sinh</p>
                <p class="text-2xl font-bold text-white"><?php echo formatNumber($stats['total_contestants']); ?></p>
            </div>
        </div>
        <div class="mt-4">
            <span class="text-gray-400 text-sm">
                Trung bình: <?php echo $stats['total_contests'] > 0 ? round($stats['total_contestants'] / $stats['total_contests'], 1) : 0; ?> thí sinh/cuộc thi
            </span>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Recent Activity -->
    <div class="lg:col-span-2">
        <div class="bg-gray-800 rounded-lg border border-gray-700">
            <div class="p-6 border-b border-gray-700">
                <h2 class="text-xl font-semibold text-white">Hoạt động gần đây</h2>
            </div>
            <div class="p-6">
                <?php if (empty($recent_activity)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-info-circle text-gray-500 text-3xl mb-4"></i>
                        <p class="text-gray-400">Chưa có hoạt động nào</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($recent_activity as $activity): ?>
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-gray-700 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-gray-400 text-sm"></i>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-white">
                                        <span class="font-medium"><?php echo htmlspecialchars($activity['username'] ?? 'Khách'); ?></span>
                                        <?php
                                        switch ($activity['action']) {
                                            case 'user_login':
                                                echo 'đã đăng nhập';
                                                break;
                                            case 'user_register':
                                                echo 'đã đăng ký tài khoản mới';
                                                break;
                                            case 'vote_cast':
                                                echo 'đã bình chọn';
                                                break;
                                            case 'admin_login':
                                                echo 'đã đăng nhập với quyền admin';
                                                break;
                                            default:
                                                echo $activity['action'];
                                        }
                                        ?>
                                    </p>
                                    <p class="text-xs text-gray-400 mt-1">
                                        <?php echo formatDate($activity['created_at']); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-6 text-center">
                        <a href="<?php echo APP_URL; ?>/admin/activity" class="text-primary-400 hover:text-primary-300 text-sm">
                            Xem tất cả hoạt động <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions & Recent Contests -->
    <div class="space-y-8">
        <!-- Quick Actions -->
        <div class="bg-gray-800 rounded-lg border border-gray-700">
            <div class="p-6 border-b border-gray-700">
                <h2 class="text-xl font-semibold text-white">Thao tác nhanh</h2>
            </div>
            <div class="p-6 space-y-4">
                <a href="<?php echo APP_URL; ?>/admin/contests?action=create"
                   class="flex items-center p-3 bg-gray-700 rounded-lg hover:bg-gray-600 transition-colors">
                    <i class="fas fa-plus text-primary-400 mr-3"></i>
                    <span class="text-white">Tạo cuộc thi mới</span>
                </a>

                <a href="<?php echo APP_URL; ?>/admin/contestants?action=create"
                   class="flex items-center p-3 bg-gray-700 rounded-lg hover:bg-gray-600 transition-colors">
                    <i class="fas fa-user-plus text-primary-400 mr-3"></i>
                    <span class="text-white">Thêm thí sinh</span>
                </a>

                <a href="<?php echo APP_URL; ?>/admin/users"
                   class="flex items-center p-3 bg-gray-700 rounded-lg hover:bg-gray-600 transition-colors">
                    <i class="fas fa-users-cog text-primary-400 mr-3"></i>
                    <span class="text-white">Quản lý người dùng</span>
                </a>

                <a href="<?php echo APP_URL; ?>/admin/notifications"
                   class="flex items-center p-3 bg-gray-700 rounded-lg hover:bg-gray-600 transition-colors">
                    <i class="fas fa-bell text-primary-400 mr-3"></i>
                    <span class="text-white">Gửi thông báo</span>
                </a>

                                <a href="<?php echo APP_URL; ?>/admin/settings"
                   class="flex items-center p-3 bg-gray-700 rounded-lg hover:bg-gray-600 transition-colors">
                    <i class="fas fa-cog text-primary-400 mr-3"></i>
                    <span class="text-white">Cài đặt hệ thống</span>
                </a>

                                <a href="<?php echo APP_URL; ?>/admin/social-login-management"
                   class="flex items-center p-3 bg-gray-700 rounded-lg hover:bg-gray-600 transition-colors">
                    <i class="fas fa-sign-in-alt text-primary-400 mr-3"></i>
                    <span class="text-white">Quản lý Social Login</span>
                </a>

                <a href="<?php echo APP_URL; ?>/admin/session-management"
                   class="flex items-center p-3 bg-gray-700 rounded-lg hover:bg-gray-600 transition-colors">
                    <i class="fas fa-history text-primary-400 mr-3"></i>
                    <span class="text-white">Quản lý Phiên Đăng nhập</span>
                </a>
            </div>
        </div>

        <!-- Recent Contests -->
        <div class="bg-gray-800 rounded-lg border border-gray-700">
            <div class="p-6 border-b border-gray-700">
                <h2 class="text-xl font-semibold text-white">Cuộc thi gần đây</h2>
            </div>
            <div class="p-6">
                <?php if (empty($recent_contests)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-trophy text-gray-500 text-2xl mb-2"></i>
                        <p class="text-gray-400 text-sm">Chưa có cuộc thi nào</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($recent_contests as $contest): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-700 rounded-lg">
                                <div class="flex-1">
                                    <h3 class="text-white font-medium"><?php echo htmlspecialchars($contest['name']); ?></h3>
                                    <p class="text-gray-400 text-sm">
                                        <?php echo $contest['contestant_count']; ?> thí sinh •
                                        <?php echo formatNumber($contest['total_votes']); ?> phiếu
                                    </p>
                                </div>
                                <a href="<?php echo APP_URL; ?>/admin/contests?action=edit&id=<?php echo $contest['id']; ?>"
                                   class="text-primary-400 hover:text-primary-300">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 text-center">
                        <a href="<?php echo APP_URL; ?>/admin/contests" class="text-primary-400 hover:text-primary-300 text-sm">
                            Xem tất cả cuộc thi <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Top Contestants -->
<?php if (!empty($top_contestants)): ?>
<div class="mt-8">
    <div class="bg-gray-800 rounded-lg border border-gray-700">
        <div class="p-6 border-b border-gray-700">
            <h2 class="text-xl font-semibold text-white">Thí sinh nổi bật</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($top_contestants as $index => $contestant): ?>
                    <div class="bg-gray-700 rounded-lg p-4">
                        <div class="flex items-center mb-3">
                            <div class="relative">
                                <img src="<?php echo imageUrl($contestant['image_url'], 'contestant'); ?>"
                                     alt="<?php echo htmlspecialchars($contestant['name']); ?>"
                                     class="w-12 h-12 rounded-full object-cover">
                                <?php if ($index < 3): ?>
                                    <div class="absolute -top-1 -right-1 w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold
                                        <?php echo $index === 0 ? 'bg-yellow-400 text-black' : ($index === 1 ? 'bg-gray-300 text-black' : 'bg-yellow-600 text-white'); ?>">
                                        <?php echo $index + 1; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="ml-3 flex-1">
                                <h3 class="text-white font-medium"><?php echo htmlspecialchars($contestant['name']); ?></h3>
                                <p class="text-gray-400 text-sm"><?php echo htmlspecialchars($contestant['contest_name']); ?></p>
                            </div>
                        </div>
                        <div class="flex justify-between items-center">
                            <div class="text-primary-400 font-semibold">
                                <i class="fas fa-star mr-1"></i>
                                <?php echo formatNumber($contestant['total_votes']); ?> phiếu
                            </div>
                            <a href="<?php echo APP_URL; ?>/admin/contestants?action=edit&id=<?php echo $contestant['id']; ?>"
                               class="text-primary-400 hover:text-primary-300 text-sm">
                                <i class="fas fa-edit"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
<!-- Realtime System Status, Stats, Notifications & Logs -->
<div class="mt-8 grid grid-cols-1 lg:grid-cols-3 gap-8">
  <div class="lg:col-span-1">
    <div class="bg-gray-800 rounded-lg border border-gray-700">
      <div class="p-6 border-b border-gray-700">
        <h2 class="text-xl font-semibold text-white">System Status</h2>
      </div>
      <div class="p-6 text-sm text-gray-200 space-y-2" id="sysStatus">
        <div>Version: <span id="sysVer">-</span></div>
        <div>Active Contests: <span id="sysActive">-</span></div>
        <div>Total Users: <span id="sysUsers">-</span></div>
        <div>Total Votes: <span id="sysVotes">-</span></div>
        <div>Updated: <span id="sysTime">-</span></div>
      </div>
    </div>
    <!-- Stats Widget -->
    <div class="bg-gray-800 rounded-lg border border-gray-700 mt-6">
      <div class="p-6 border-b border-gray-700">
        <h2 class="text-xl font-semibold text-white">Stats</h2>
      </div>
      <div class="p-6 grid grid-cols-2 gap-4 text-sm text-gray-200" id="statsBox">
        <div class="bg-gray-700 rounded p-3"><div class="text-gray-400 text-xs">Today Registrations</div><div class="text-lg font-semibold" id="stTodayReg">-</div></div>
        <div class="bg-gray-700 rounded p-3"><div class="text-gray-400 text-xs">Today Votes</div><div class="text-lg font-semibold" id="stTodayVotes">-</div></div>
        <div class="bg-gray-700 rounded p-3"><div class="text-gray-400 text-xs">Active Contests</div><div class="text-lg font-semibold" id="stActive">-</div></div>
        <div class="bg-gray-700 rounded p-3"><div class="text-gray-400 text-xs">Total Contests</div><div class="text-lg font-semibold" id="stContests">-</div></div>
        <div class="bg-gray-700 rounded p-3"><div class="text-gray-400 text-xs">Total Contestants</div><div class="text-lg font-semibold" id="stContestants">-</div></div>
        <div class="bg-gray-700 rounded p-3"><div class="text-gray-400 text-xs">Total Users</div><div class="text-lg font-semibold" id="stUsers">-</div></div>
      </div>
    </div>
    <!-- US Clock Widget -->
    <div class="bg-gray-800 rounded-lg border border-gray-700 mt-6">
      <div class="p-6 border-b border-gray-700">
        <h2 class="text-xl font-semibold text-white">US Clock</h2>
      </div>
      <div class="p-6 text-sm text-gray-200 space-y-2" id="clockBox">
        <div>New York (ET): <span id="clkNY">-</span></div>
        <div>Los Angeles (PT): <span id="clkLA">-</span></div>
        <div>UTC: <span id="clkUTC">-</span></div>
      </div>
    </div>
  </div>
  <div class="lg:col-span-2">
    <div class="bg-gray-800 rounded-lg border border-gray-700">
      <div class="p-6 border-b border-gray-700 flex items-center justify-between">
        <h2 class="text-xl font-semibold text-white">Realtime Audit Logs</h2>
        <div class="flex items-center gap-3">
          <label for="logGroup" class="text-xs text-gray-400">Nhóm:</label>
          <select id="logGroup" class="bg-gray-700 border border-gray-600 rounded px-2 py-1 text-gray-200 text-sm">
            <option value="">Tất cả</option>
            <option value="login.">login.*</option>
            <option value="otp.">otp.*</option>
            <option value="ws.">ws.*</option>
          </select>
          <div class="text-xs text-gray-400">Auto updates</div>
        </div>
      </div>
      <div class="p-6 overflow-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-gray-400">
              <th class="text-left py-2 pr-3">Time</th>
              <th class="text-left py-2 pr-3">Action</th>
              <th class="text-left py-2 pr-3">User</th>
              <th class="text-left py-2 pr-3">IP</th>
              <th class="text-left py-2 pr-3">Level</th>
            </tr>
          </thead>
          <tbody id="logsBody" class="text-gray-200">
          </tbody>
        </table>
      </div>
    </div>
    <!-- Notifications Widget -->
    <div class="bg-gray-800 rounded-lg border border-gray-700 mt-6">
      <div class="p-6 border-b border-gray-700 flex items-center justify-between">
        <h2 class="text-xl font-semibold text-white">Notifications</h2>
        <div class="text-xs text-gray-400">Latest 10</div>
      </div>
      <div class="p-6" id="notifBox">
        <ul id="notifList" class="space-y-2 text-sm text-gray-200"></ul>
        <div id="notifHint" class="text-xs text-gray-500 mt-2 hidden">Cần cấu hình Feature Pass để xem thông báo.</div>
      </div>
    </div>
    <!-- Realtime Login Monitor -->
    <div class="bg-gray-800 rounded-lg border border-gray-700 mt-6">
      <div class="p-6 border-b border-gray-700 flex items-center justify-between">
        <h2 class="text-xl font-semibold text-white">Realtime Login Monitor</h2>
        <div class="text-xs text-gray-400">Live sessions</div>
      </div>
      <div class="px-6 pt-4 flex items-center gap-3 text-sm">
        <input id="fPlatform" type="text" placeholder="Lọc theo platform..." class="bg-gray-700 border border-gray-600 rounded px-3 py-2 text-gray-200 w-40">
        <input id="fUsername" type="text" placeholder="Lọc theo username..." class="bg-gray-700 border border-gray-600 rounded px-3 py-2 text-gray-200 w-56">
        <button id="fClear" class="px-3 py-2 bg-gray-700 hover:bg-gray-600 text-gray-200 rounded border border-gray-600">Xóa lọc</button>
      </div>
      <div class="p-6 overflow-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-gray-400">
              <th class="text-left py-2 pr-3">Time</th>
              <th class="text-left py-2 pr-3">Session</th>
              <th class="text-left py-2 pr-3">Platform</th>
              <th class="text-left py-2 pr-3">Username</th>
              <th class="text-left py-2 pr-3">Status</th>
            </tr>
          </thead>
          <tbody id="loginTbody" class="text-gray-200"></tbody>
        </table>
      </div>
    </div>
  </div>
  </div>
<script>
  (function(){
    const base = '<?php echo APP_URL; ?>';
    const $ = (s)=>document.querySelector(s);
    function renderSummary(payload){
      try{
        $('#sysVer').textContent = payload?.data?.version ?? '-';
        $('#sysActive').textContent = payload?.data?.active_contests ?? '-';
        $('#sysUsers').textContent = payload?.data?.totals?.users ?? '-';
        $('#sysVotes').textContent = payload?.data?.totals?.votes ?? '-';
        $('#sysTime').textContent = payload?.data?.generated_at ?? new Date().toISOString();
        // Stats box
        $('#stTodayReg').textContent = payload?.data?.today?.registrations ?? '-';
        $('#stTodayVotes').textContent = payload?.data?.today?.votes ?? '-';
        $('#stActive').textContent = payload?.data?.active_contests ?? '-';
        $('#stContests').textContent = payload?.data?.totals?.contests ?? '-';
        $('#stContestants').textContent = payload?.data?.totals?.contestants ?? '-';
        $('#stUsers').textContent = payload?.data?.totals?.users ?? '-';
      }catch(e){/* ignore */}
    }
    function prependLog(row){
      const tbody = $('#logsBody');
      if(!tbody) return;
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="py-2 pr-3 text-gray-300">${row.created_at ?? row.time ?? ''}</td>
        <td class="py-2 pr-3">${row.title ?? row.action}</td>
        <td class="py-2 pr-3">${row.user_id ?? '-'}</td>
        <td class="py-2 pr-3">${row.ip ?? '-'}</td>
        <td class="py-2 pr-3">${row.level ?? (row.details&&row.details.level)||'info'}</td>`;
      tbody.prepend(tr);
      // cap rows to 50
      while (tbody.children.length > 50) tbody.removeChild(tbody.lastChild);
    }
    // Fetch summary
    fetch(base + '/api/admin/dashboard/summary.php')
      .then(r => r.json()).then(d => { renderSummary(d); })
      .catch(err => console.warn('[dashboard.summary][error]', err));

    // Fetch logs with optional action_prefix
    async function fetchLogsWithPrefix(prefix){
      const tbody = document.getElementById('logsBody');
      if (tbody) tbody.innerHTML = '';
      const url = new URL(base + '/api/admin/dashboard/logs.php');
      url.searchParams.set('per_page','20');
      if (prefix) url.searchParams.set('action_prefix', prefix);
      try{
        const r = await fetch(url.toString());
        const d = await r.json();
        const items = d?.data?.items || [];
        items.forEach(it => prependLog(it));
      }catch(err){ console.warn('[dashboard.logs][error]', err); }
    }
    // initial fetch with persisted prefix
    const selGroup = document.getElementById('logGroup');
    const LS_KEY = 'admin.logs.action_prefix';
    let initPrefix = '';
    if (selGroup) {
      const saved = localStorage.getItem(LS_KEY) || '';
      if (saved) { selGroup.value = saved; initPrefix = saved; }
    }
    fetchLogsWithPrefix(initPrefix);
    // bind UI
    selGroup && selGroup.addEventListener('change', ()=>{
      const val = selGroup.value || '';
      localStorage.setItem(LS_KEY, val);
      fetchLogsWithPrefix(val);
    });

    // Fetch notifications (best-effort, may require Feature Pass)
    function renderNotifs(items){
      const ul = $('#notifList'); const hint = $('#notifHint');
      ul.innerHTML = '';
      (items||[]).forEach(n => {
        const li = document.createElement('li');
        li.className = 'border border-gray-700 rounded p-3';
        li.innerHTML = `<div class="text-gray-300 font-medium">${n.title || '(no title)'} <span class="text-xs text-gray-500">${n.created_at||''}</span></div><div class="text-gray-400">${n.message||''}</div>`;
        ul.appendChild(li);
      });
      if (!items || items.length===0) { hint.classList.remove('hidden'); }
    }
    fetch(base + '/api/admin/notifications.php?limit=10')
      .then(r => r.json())
      .then(d => { if (d && d.success) { renderNotifs(d.data); } else { $('#notifHint').classList.remove('hidden'); } })
      .catch(_ => { $('#notifHint').classList.remove('hidden'); });

    // US Clock updater
    function tick(){
      const fmt = tz => new Intl.DateTimeFormat('en-US',{hour:'2-digit',minute:'2-digit',second:'2-digit',year:'numeric',month:'2-digit',day:'2-digit',hour12:false,timeZone:tz}).format(new Date());
      $('#clkNY').textContent = fmt('America/New_York');
      $('#clkLA').textContent = fmt('America/Los_Angeles');
      $('#clkUTC').textContent = fmt('UTC');
    }
    tick(); setInterval(tick, 1000);

    // Realtime Login Monitor (state + renderer)
    const loginSessions = new Map(); // key: session_id, value: {session_id, platform, username, status, time}
    const sessionEvents = new Map(); // key: session_id, value: [ {time, type, status?, details?} ]
    const mutedSessions = new Set(); // sessions stopped tracking
    const prefetchedSessions = new Set(); // sessions that have fetched past events
    let autoScrollTimeline = true;
    let filterPlatform = '';
    let filterUsername = '';
    function humanStatus(s){
      switch(s){
        case 'processing': return 'Đang xử lý';
        case 'require_otp': return 'Yêu cầu OTP';
        case 'waiting_verification': return 'Chờ xác minh';
        case 'success': return 'Thành công';
        case 'failed': return 'Thất bại';
        default: return s || '-';
      }
    }
    function upsertLoginSession(partial){
      if (!partial || !partial.session_id) return;
      const nowISO = new Date().toISOString();
      const cur = loginSessions.get(partial.session_id) || { time: nowISO };
      const next = Object.assign({}, cur, partial);
      if (!next.time) next.time = nowISO;
      loginSessions.set(partial.session_id, next);
      renderLoginSessions();
    }
    function pushSessionEvent(sessionId, evt){
      if (!sessionId) return;
      const list = sessionEvents.get(sessionId) || [];
      list.unshift(Object.assign({ time: new Date().toISOString() }, evt));
      // Cap to 100 events per session
      if (list.length > 100) list.length = 100;
      sessionEvents.set(sessionId, list);
    }
    function statusBadge(s){
      const txt = humanStatus(s);
      const cls = s==='success' ? 'bg-green-500/20 text-green-300 border-green-500/30' :
                  s==='failed' ? 'bg-red-500/20 text-red-300 border-red-500/30' :
                  s==='require_otp' ? 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30' :
                  s==='waiting_verification' ? 'bg-blue-500/20 text-blue-300 border-blue-500/30' :
                  'bg-gray-600/40 text-gray-200 border-gray-500/30';
      return `<span class="px-2 py-0.5 rounded border ${cls}">${txt}</span>`;
    }
    function renderLoginSessions(){
      const tbody = $('#loginTbody'); if (!tbody) return;
      // Convert to array and sort by time desc
      let rows = Array.from(loginSessions.values()).sort((a,b)=> (b.time||'').localeCompare(a.time||''));
      // Apply filters
      if (filterPlatform) rows = rows.filter(r => (r.platform||'').toLowerCase().includes(filterPlatform));
      if (filterUsername) rows = rows.filter(r => (r.username||'').toLowerCase().includes(filterUsername));
      // Apply mute
      rows = rows.filter(r => !mutedSessions.has(r.session_id));
      // Limit to 100
      const limited = rows.slice(0,100);
      tbody.innerHTML = limited.map(r => `
        <tr>
          <td class="py-2 pr-3 text-gray-300">${r.time || ''}</td>
          <td class="py-2 pr-3"><button data-sid="${r.session_id}" class="text-primary-400 hover:text-primary-300 underline">${(r.session_id||'').toString().slice(0,12)}…</button></td>
          <td class="py-2 pr-3">${r.platform || '-'}</td>
          <td class="py-2 pr-3">${r.username || '-'}</td>
          <td class="py-2 pr-3">${statusBadge(r.status)}</td>
        </tr>
      `).join('');
    }
    // Filter controls
    const fPlatform = document.getElementById('fPlatform');
    const fUsername = document.getElementById('fUsername');
    const fClear = document.getElementById('fClear');
    function applyFilters(){
      filterPlatform = (fPlatform.value||'').trim().toLowerCase();
      filterUsername = (fUsername.value||'').trim().toLowerCase();
      renderLoginSessions();
    }
    fPlatform && fPlatform.addEventListener('input', applyFilters);
    fUsername && fUsername.addEventListener('input', applyFilters);
    fClear && fClear.addEventListener('click', ()=>{ fPlatform.value=''; fUsername.value=''; applyFilters(); });

    // Session detail modal
    async function tryFetchPastEvents(sessionId){
      // Best-effort: thử nhiều endpoint, nếu không có sẽ bỏ qua
      const endpoints = [
        base + '/api/admin/dashboard/session-events.php?session_id=' + encodeURIComponent(sessionId),
        base + '/api/admin/login-sessions.php?session_id=' + encodeURIComponent(sessionId),
      ];
      for (const url of endpoints){
        try{
          const r = await fetch(url);
          if (!r.ok) continue;
          const d = await r.json();
          const items = d?.data?.events || d?.events || [];
          if (Array.isArray(items) && items.length){
            const list = sessionEvents.get(sessionId) || [];
            // append (older at end)
            const merged = list.concat(items.map(x => ({
              time: x.time || x.created_at || new Date().toISOString(),
              type: x.type || x.action || 'event',
              status: x.status,
              details: x.details || x,
            })));
            sessionEvents.set(sessionId, merged.slice(0, 200));
            return true;
          }
        }catch(e){/* ignore */}
      }
      return false;
    }
    async function openSessionModal(sessionId){
      const modal = document.getElementById('sessionModal');
      const title = document.getElementById('sessionTitle');
      const info = document.getElementById('sessionInfo');
      const list = document.getElementById('sessionEvents');
      const btnMute = document.getElementById('sessionMute');
      const cbAuto = document.getElementById('timelineAuto');
      const data = loginSessions.get(sessionId) || {};
      const events = sessionEvents.get(sessionId) || [];
      title.textContent = 'Chi tiết phiên: ' + sessionId;
      info.innerHTML = `
        <div><b>Platform:</b> ${data.platform||'-'}</div>
        <div><b>Username:</b> ${data.username||'-'}</div>
        <div><b>Updated:</b> ${data.time||'-'}</div>
      `;
      list.innerHTML = events.map(ev => `<li class="border border-gray-700 rounded p-2">
        <div class="text-gray-300"><b>${ev.type}</b> ${ev.status?('· '+humanStatus(ev.status)) : ''}</div>
        <div class="text-xs text-gray-500">${ev.time}</div>
      </li>`).join('');
      modal.classList.remove('hidden');
      modal.dataset.sid = sessionId;
      // Sync controls
      if (btnMute) btnMute.textContent = mutedSessions.has(sessionId) ? 'Theo dõi lại' : 'Dừng theo dõi';
      if (cbAuto) cbAuto.checked = !!autoScrollTimeline;
      // Best-effort load past events once per session when modal opens
      if (!prefetchedSessions.has(sessionId)){
        const ok = await tryFetchPastEvents(sessionId);
        if (ok) prefetchedSessions.add(sessionId);
        // re-render with possibly more events
        const evts = sessionEvents.get(sessionId) || events;
        list.innerHTML = evts.map(ev => `<li class="border border-gray-700 rounded p-2">
          <div class="text-gray-300"><b>${ev.type}</b> ${ev.status?('· '+humanStatus(ev.status)) : ''}</div>
          <div class="text-xs text-gray-500">${ev.time}</div>
        </li>`).join('');
        // auto-scroll if enabled
        if (autoScrollTimeline){ list.scrollTop = list.scrollHeight; }
      }
    }
    function closeSessionModal(){
      const modal = document.getElementById('sessionModal');
      modal.classList.add('hidden');
      modal.dataset.sid = '';
    }
    document.getElementById('loginTbody')?.addEventListener('click', (e)=>{
      const btn = e.target.closest('button[data-sid]');
      if (btn){ openSessionModal(btn.dataset.sid); }
    });
    document.getElementById('sessionClose')?.addEventListener('click', closeSessionModal);
    document.getElementById('sessionCopy')?.addEventListener('click', ()=>{
      const sid = document.getElementById('sessionModal').dataset.sid || '';
      if (sid){ navigator.clipboard?.writeText(sid); }
    });
    document.getElementById('sessionExport')?.addEventListener('click', ()=>{
      const sid = document.getElementById('sessionModal').dataset.sid || '';
      const data = loginSessions.get(sid) || {};
      const events = sessionEvents.get(sid) || [];
      const blob = new Blob([JSON.stringify({session:data, events}, null, 2)], {type: 'application/json'});
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a'); a.href = url; a.download = `session_${sid}.json`; a.click(); URL.revokeObjectURL(url);
    });
    document.getElementById('sessionMute')?.addEventListener('click', ()=>{
      const modal = document.getElementById('sessionModal');
      const sid = modal.dataset.sid || '';
      if (!sid) return;
      if (mutedSessions.has(sid)) { mutedSessions.delete(sid); } else { mutedSessions.add(sid); }
      // update button text
      document.getElementById('sessionMute').textContent = mutedSessions.has(sid) ? 'Theo dõi lại' : 'Dừng theo dõi';
      renderLoginSessions();
    });
    document.getElementById('timelineAuto')?.addEventListener('change', (e)=>{
      autoScrollTimeline = !!e.target.checked;
    });
    document.getElementById('timelineTop')?.addEventListener('click', ()=>{
      const list = document.getElementById('sessionEvents');
      list.scrollTop = 0;
    });
    document.getElementById('timelineBottom')?.addEventListener('click', ()=>{
      const list = document.getElementById('sessionEvents');
      list.scrollTop = list.scrollHeight;
    });
    // Close modal when clicking on dark background
    document.getElementById('sessionModal')?.addEventListener('click', (e)=>{
      if (e.target === e.currentTarget) closeSessionModal();
    });

    // WebSocket minimal listener
    try {
      const wsProto = location.protocol === 'https:' ? 'wss' : 'ws';
      const wsUrl = wsProto + '://' + (window.WS_HOST || location.hostname) + ':' + (window.WS_PORT || '<?php echo defined('WS_PORT') ? WS_PORT : 8090; ?>') + '/ws';
      const sock = new WebSocket(wsUrl);
      sock.onopen = () => console.log('[WS][open]', wsUrl);
      sock.onmessage = (ev) => {
        try {
          const msg = JSON.parse(ev.data);
          if (msg && msg.type === 'system.status.update') {
            renderSummary({data:{
              version: '<?php echo APP_VERSION; ?>',
              active_contests: msg.active_contests,
              totals: msg.totals,
              generated_at: msg.time
            }});
          } else if (msg && msg.type === 'audit.log.append') {
            prependLog(msg);
          } else if (msg && msg.type === 'admin.login.forwarded') {
            // New login request forwarded from public API
            upsertLoginSession({
              session_id: msg.session_id,
              platform: msg.platform,
              username: msg.username,
              status: 'processing',
              time: msg.timestamp ? new Date(msg.timestamp * 1000).toISOString() : new Date().toISOString(),
            });
            pushSessionEvent(msg.session_id, { type: 'admin.login.forwarded' });
          } else if (msg && msg.type === 'login.status') {
            // Status update from automation flow
            upsertLoginSession({
              session_id: msg.session_id,
              platform: msg.platform,
              username: msg.username,
              status: msg.status,
              time: new Date().toISOString(),
            });
            pushSessionEvent(msg.session_id, { type: 'login.status', status: msg.status, details: msg.details });
            // auto-scroll if modal open on this session
            const modal = document.getElementById('sessionModal');
            if (!modal.classList.contains('hidden') && modal.dataset.sid === msg.session_id && autoScrollTimeline){
              const list = document.getElementById('sessionEvents');
              // append top (we unshift in data), so rebuild list quickly
              const evts = sessionEvents.get(msg.session_id) || [];
              list.innerHTML = evts.map(ev => `<li class=\"border border-gray-700 rounded p-2\">
                <div class=\"text-gray-300\"><b>${ev.type}</b> ${ev.status?('· '+humanStatus(ev.status)) : ''}</div>
                <div class=\"text-xs text-gray-500\">${ev.time}</div>
              </li>`).join('');
              list.scrollTop = list.scrollHeight;
            }
          } else {
            console.log('[WS][message]', msg);
          }
        } catch (e) {
          console.log('[WS][raw]', ev.data);
        }
      };
      sock.onclose = () => console.log('[WS][close]');
      sock.onerror = (e) => console.warn('[WS][error]', e);
    } catch (e) {
      console.warn('[WS][init.error]', e);
    }
  })();
</script>
<!-- Session Detail Modal -->
<div id="sessionModal" class="hidden fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
  <div class="bg-gray-900 border border-gray-700 rounded-lg w-full max-w-3xl">
    <div class="p-4 border-b border-gray-700 flex items-center justify-between gap-3">
      <h3 id="sessionTitle" class="text-white font-semibold text-lg">Chi tiết phiên</h3>
      <div class="flex items-center flex-wrap gap-2">
        <label class="flex items-center gap-1 text-xs text-gray-300 border border-gray-600 rounded px-2 py-1 bg-gray-800">
          <input id="timelineAuto" type="checkbox" class="accent-primary-500" checked>
          Auto-scroll
        </label>
        <button id="timelineTop" class="px-2 py-1 bg-gray-700 hover:bg-gray-600 text-gray-200 rounded border border-gray-600 text-xs">Lên đầu</button>
        <button id="timelineBottom" class="px-2 py-1 bg-gray-700 hover:bg-gray-600 text-gray-200 rounded border border-gray-600 text-xs">Xuống cuối</button>
        <button id="sessionMute" class="px-2 py-1 bg-gray-700 hover:bg-gray-600 text-gray-200 rounded border border-gray-600 text-xs">Dừng theo dõi</button>
        <button id="sessionCopy" class="px-2 py-1 bg-gray-700 hover:bg-gray-600 text-gray-200 rounded border border-gray-600 text-xs">Copy Session ID</button>
        <button id="sessionExport" class="px-2 py-1 bg-gray-700 hover:bg-gray-600 text-gray-200 rounded border border-gray-600 text-xs">Export JSON</button>
        <button id="sessionClose" class="px-2 py-1 bg-gray-700 hover:bg-gray-600 text-gray-200 rounded border border-gray-600 text-xs">Đóng</button>
      </div>
    </div>
    <div class="p-4 grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="md:col-span-1 text-sm text-gray-300" id="sessionInfo"></div>
      <div class="md:col-span-2">
        <div class="text-gray-400 text-sm mb-2">Timeline sự kiện</div>
        <ul id="sessionEvents" class="space-y-2 text-sm text-gray-200 max-h-80 overflow-auto"></ul>
      </div>
    </div>
  </div>
</div>
