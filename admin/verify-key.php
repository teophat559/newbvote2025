<?php
session_start();
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/admin-security.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$page_title = 'Admin Key Verification';
$error_message = '';
$success_message = '';

// Check if IP is blocked
if (isIPBlocked()) {
    $error_message = 'IP của bạn đã bị chặn do quá nhiều lần thử sai. Vui lòng thử lại sau 1 giờ.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isIPBlocked()) {
    $admin_key = trim($_POST['admin_key'] ?? '');

    if (empty($admin_key)) {
        $error_message = 'Vui lòng nhập mã bảo mật admin.';
        logAdminAccess('key_failed');
    } else {
        if (verifyAdminKey($admin_key)) {
            logAdminAccess('key_success');
            $success_message = 'Mã bảo mật đúng! Đang chuyển hướng...';
            header('Refresh: 2; URL=' . APP_URL . '/admin/dashboard');
        } else {
            $error_message = 'Mã bảo mật không đúng. Vui lòng thử lại.';
            logAdminAccess('key_failed');

            // Check for brute force
            if (checkBruteForce()) {
                blockIP();
                $error_message = 'Quá nhiều lần thử sai. IP của bạn đã bị chặn trong 1 giờ.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fdf2f8',
                            100: '#fce7f3',
                            200: '#fbcfe8',
                            300: '#f9a8d4',
                            400: '#f472b6',
                            500: '#ec4899',
                            600: '#db2777',
                            700: '#be185d',
                            800: '#9d174d',
                            900: '#831843',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
</head>
<body class="bg-gradient-to-br from-purple-900 via-blue-900 to-indigo-900 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <!-- Security Notice -->
        <div class="bg-red-600 text-white p-4 rounded-lg mb-6 text-center">
            <i class="fas fa-shield-alt text-2xl mb-2"></i>
            <h2 class="text-xl font-bold">BẢO MẬT ADMIN</h2>
            <p class="text-sm opacity-90">Vui lòng nhập mã bảo mật để truy cập admin panel</p>
        </div>

        <!-- Main Card -->
        <div class="bg-white rounded-lg shadow-2xl p-8">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-key text-white text-2xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-800">Xác thực Admin</h1>
                <p class="text-gray-600 mt-2">Nhập mã bảo mật để tiếp tục</p>
            </div>

            <!-- Error Message -->
            <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>

            <!-- Success Message -->
            <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" class="space-y-6">
                <div>
                    <label for="admin_key" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-2"></i>Mã bảo mật Admin
                    </label>
                    <div class="relative">
                        <input
                            type="password"
                            id="admin_key"
                            name="admin_key"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                            placeholder="Nhập mã bảo mật..."
                            required
                            autocomplete="off"
                        >
                        <button
                            type="button"
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            onclick="togglePassword()"
                        >
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <button
                    type="submit"
                    class="w-full bg-gradient-to-r from-purple-500 to-pink-500 text-white py-3 px-4 rounded-lg font-medium hover:from-purple-600 hover:to-pink-600 transition duration-200"
                    <?php echo isIPBlocked() ? 'disabled' : ''; ?>
                >
                    <i class="fas fa-unlock mr-2"></i>
                    Xác thực
                </button>
            </form>

            <!-- Security Info -->
            <div class="mt-8 p-4 bg-gray-50 rounded-lg">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">
                    <i class="fas fa-info-circle mr-2"></i>Thông tin bảo mật
                </h3>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li>• Mã bảo mật có hiệu lực trong 24 giờ</li>
                    <li>• IP sẽ bị chặn sau 5 lần thử sai</li>
                    <li>• Tất cả truy cập đều được ghi log</li>
                    <li>• Sử dụng HTTPS để bảo mật</li>
                </ul>
            </div>

            <!-- Back to Home -->
            <div class="mt-6 text-center">
                <a href="<?php echo APP_URL; ?>" class="text-purple-600 hover:text-purple-800 text-sm">
                    <i class="fas fa-arrow-left mr-1"></i>
                    Về trang chủ
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6 text-white text-sm opacity-75">
            <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. Bảo mật bởi Admin Security System.</p>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        function togglePassword() {
            const input = document.getElementById('admin_key');
            const icon = document.getElementById('toggleIcon');

            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        // Auto focus on input
        document.getElementById('admin_key').focus();

        // Prevent form resubmission
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>
