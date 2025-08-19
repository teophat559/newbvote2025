<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/admin-security.php';

// Require only admin key verification
requireAdminKey();

$page_title = 'Cài đặt Hệ thống';
include '../../includes/header.php';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCSRFToken()) {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'update_general':
            $site_name = sanitizeInput($_POST['site_name'] ?? '');
            $admin_email = sanitizeInput($_POST['admin_email'] ?? '');

            if ($site_name && $admin_email) {
                updateSetting('site_name', $site_name);
                updateSetting('admin_email', $admin_email);
                setFlashMessage('success', 'Cập nhật cài đặt chung thành công.');
                redirect(APP_URL . '/admin/settings');
            } else {
                setFlashMessage('error', 'Vui lòng điền đầy đủ thông tin bắt buộc.');
            }
            break;

        case 'update_voting':
            $max_votes_per_user = intval($_POST['max_votes_per_user'] ?? 1);
            $enable_social_login = isset($_POST['enable_social_login']) ? 1 : 0;
            $require_login_to_vote = isset($_POST['require_login_to_vote']) ? 1 : 0;

            updateSetting('max_votes_per_user', $max_votes_per_user);
            updateSetting('enable_social_login', $enable_social_login);
            updateSetting('require_login_to_vote', $require_login_to_vote);

            setFlashMessage('success', 'Cập nhật cài đặt bình chọn thành công.');
            redirect(APP_URL . '/admin/settings');
            break;

        case 'update_browser_api':
            $browser_api_url = sanitizeInput($_POST['browser_api_url'] ?? '');
            $browser_api_key = sanitizeInput($_POST['browser_api_key'] ?? '');
            $enable_browser_automation = isset($_POST['enable_browser_automation']) ? 1 : 0;

            updateSetting('browser_api_url', $browser_api_url);
            updateSetting('browser_api_key', $browser_api_key);
            updateSetting('enable_browser_automation', $enable_browser_automation);

            setFlashMessage('success', 'Cập nhật cài đặt Browser API thành công.');
            redirect(APP_URL . '/admin/settings');
            break;
    }
}

// Get current settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get system info
$system_info = [
    'php_version' => PHP_VERSION,
    'mysql_version' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
];
?>

<!-- Admin Header -->
<div class="bg-gray-800 border-b border-gray-700 mb-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-white">Cài đặt Hệ thống</h1>
                <p class="text-gray-400 mt-1">Quản lý cài đặt và cấu hình hệ thống</p>
            </div>
        </div>
    </div>
</div>

<!-- Settings Tabs -->
<div class="mb-8">
    <div class="border-b border-gray-700">
        <nav class="-mb-px flex space-x-8">
            <button onclick="showTab('general')" id="tab-general" class="tab-button active py-2 px-1 border-b-2 border-primary-500 font-medium text-sm text-primary-400">
                <i class="fas fa-cog mr-2"></i> Cài đặt chung
            </button>
            <button onclick="showTab('voting')" id="tab-voting" class="tab-button py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-400 hover:text-gray-300">
                <i class="fas fa-vote-yea mr-2"></i> Bình chọn
            </button>
            <button onclick="showTab('browser')" id="tab-browser" class="tab-button py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-400 hover:text-gray-300">
                <i class="fas fa-browser mr-2"></i> Browser API
            </button>
            <button onclick="showTab('system')" id="tab-system" class="tab-button py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-400 hover:text-gray-300">
                <i class="fas fa-server mr-2"></i> Hệ thống
            </button>
        </nav>
    </div>
</div>

<!-- General Settings -->
<div id="tab-content-general" class="tab-content">
    <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
        <h2 class="text-xl font-semibold text-white mb-6">Cài đặt chung</h2>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="update_general">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Tên website *</label>
                    <input type="text" name="site_name" value="<?php echo htmlspecialchars($settings['site_name'] ?? 'Voting System'); ?>" required
                           class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Email admin *</label>
                    <input type="email" name="admin_email" value="<?php echo htmlspecialchars($settings['admin_email'] ?? ''); ?>" required
                           class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-save mr-2"></i> Lưu cài đặt
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Voting Settings -->
<div id="tab-content-voting" class="tab-content hidden">
    <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
        <h2 class="text-xl font-semibold text-white mb-6">Cài đặt bình chọn</h2>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="update_voting">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Số lượt bình chọn tối đa mỗi người dùng</label>
                    <input type="number" name="max_votes_per_user" value="<?php echo intval($settings['max_votes_per_user'] ?? 1); ?>" min="1"
                           class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                </div>

                <div class="md:col-span-2">
                    <div class="flex items-center">
                        <input type="checkbox" name="enable_social_login" id="enable_social_login"
                               <?php echo ($settings['enable_social_login'] ?? 1) ? 'checked' : ''; ?>
                               class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                        <label for="enable_social_login" class="ml-2 block text-sm text-gray-300">
                            Bật đăng nhập qua mạng xã hội
                        </label>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <div class="flex items-center">
                        <input type="checkbox" name="require_login_to_vote" id="require_login_to_vote"
                               <?php echo ($settings['require_login_to_vote'] ?? 1) ? 'checked' : ''; ?>
                               class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                        <label for="require_login_to_vote" class="ml-2 block text-sm text-gray-300">
                            Yêu cầu đăng nhập để bình chọn
                        </label>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-save mr-2"></i> Lưu cài đặt
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Browser API Settings -->
<div id="tab-content-browser" class="tab-content hidden">
    <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
        <h2 class="text-xl font-semibold text-white mb-6">Cài đặt Browser API</h2>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="update_browser_api">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Browser API URL</label>
                    <input type="url" name="browser_api_url" value="<?php echo htmlspecialchars($settings['browser_api_url'] ?? 'http://127.0.0.1:40000'); ?>"
                           placeholder="http://127.0.0.1:40000"
                           class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Browser API Key</label>
                    <input type="text" name="browser_api_key" value="<?php echo htmlspecialchars($settings['browser_api_key'] ?? ''); ?>"
                           placeholder="Nhập API key"
                           class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent w-full">
                </div>

                <div class="md:col-span-2">
                    <div class="flex items-center">
                        <input type="checkbox" name="enable_browser_automation" id="enable_browser_automation"
                               <?php echo ($settings['enable_browser_automation'] ?? 1) ? 'checked' : ''; ?>
                               class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                        <label for="enable_browser_automation" class="ml-2 block text-sm text-gray-300">
                            Bật tự động hóa trình duyệt
                        </label>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-save mr-2"></i> Lưu cài đặt
                </button>
            </div>
        </form>
    </div>
</div>

<!-- System Settings -->
<div id="tab-content-system" class="tab-content hidden">
    <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
        <h2 class="text-xl font-semibold text-white mb-6">Thông tin hệ thống</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h3 class="text-lg font-medium text-white mb-4">Thông tin server</h3>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-400">PHP Version:</span>
                        <span class="text-white"><?php echo $system_info['php_version']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">MySQL Version:</span>
                        <span class="text-white"><?php echo $system_info['mysql_version']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Server Software:</span>
                        <span class="text-white"><?php echo $system_info['server_software']; ?></span>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-lg font-medium text-white mb-4">Thống kê hệ thống</h3>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Tổng người dùng:</span>
                        <span class="text-white"><?php echo getStatistics()['total_users']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Tổng cuộc thi:</span>
                        <span class="text-white"><?php echo getStatistics()['total_contests']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Tổng lượt bình chọn:</span>
                        <span class="text-white"><?php echo getStatistics()['total_votes']; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => {
        content.classList.add('hidden');
    });

    // Remove active class from all tab buttons
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(button => {
        button.classList.remove('active', 'border-primary-500', 'text-primary-400');
        button.classList.add('border-transparent', 'text-gray-400');
    });

    // Show selected tab content
    document.getElementById('tab-content-' + tabName).classList.remove('hidden');

    // Add active class to selected tab button
    document.getElementById('tab-' + tabName).classList.add('active', 'border-primary-500', 'text-primary-400');
    document.getElementById('tab-' + tabName).classList.remove('border-transparent', 'text-gray-400');
}
</script>

<?php include '../../includes/footer.php'; ?>
