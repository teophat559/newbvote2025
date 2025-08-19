<?php
session_start();
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/admin-security.php';
require_once dirname(__DIR__) . '/includes/auth.php';

// Redirect to dashboard if already verified
if (isAdminKeyVerified()) {
    header('Location: ' . APP_URL . '/admin/dashboard');
    exit;
}

// Handle admin login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCSRFToken()) {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        setFlashMessage('error', 'Vui lòng nhập đầy đủ thông tin đăng nhập.');
    } else {
        if (adminLogin($username, $password)) {
            redirect(APP_URL . '/admin/dashboard');
        }
    }
}

$page_title = 'Admin Login';
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

    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <!-- Deprecated: This legacy admin login page is kept for backward compatibility. New UI is routed at /admin/login -> public/admin-login.php -->
    <div style="display:none" aria-hidden="true"><!-- deprecated -->Legacy admin login page (deprecated). Please use the redesigned UI at /public/admin-login.php. Updated: 2025-08-17.</div>
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <div class="flex justify-center">
                    <i class="fas fa-crown text-primary-400 text-4xl"></i>
                </div>
                <h2 class="mt-6 text-3xl font-bold text-white">
                    Đăng nhập Admin
                </h2>
                <p class="mt-2 text-sm text-gray-400">
                    Truy cập vào bảng quản trị hệ thống
                </p>
            </div>

            <!-- Flash Messages -->
            <?php $flash_messages = getFlashMessages(); ?>
            <?php if (!empty($flash_messages)): ?>
                <?php foreach ($flash_messages as $message): ?>
                    <div class="p-4 rounded-md <?php
                        echo $message['type'] === 'success' ? 'bg-green-600' :
                            ($message['type'] === 'error' ? 'bg-red-600' :
                            ($message['type'] === 'warning' ? 'bg-yellow-600' : 'bg-blue-600'));
                    ?> text-white">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas <?php
                                    echo $message['type'] === 'success' ? 'fa-check-circle' :
                                        ($message['type'] === 'error' ? 'fa-exclamation-circle' :
                                        ($message['type'] === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle'));
                                ?>"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm"><?php echo $message['message']; ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="bg-gray-800 rounded-lg shadow-xl p-8">
                <form class="space-y-6" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-300 mb-2">
                            Tên đăng nhập hoặc Email
                        </label>
                        <input id="username" name="username" type="text" required
                               class="appearance-none relative block w-full px-3 py-3 border border-gray-600 placeholder-gray-400 text-white bg-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                               placeholder="Nhập tên đăng nhập hoặc email"
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-300 mb-2">
                            Mật khẩu
                        </label>
                        <div class="relative">
                            <input id="password" name="password" type="password" required
                                   class="appearance-none relative block w-full px-3 py-3 border border-gray-600 placeholder-gray-400 text-white bg-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent pr-10"
                                   placeholder="Nhập mật khẩu">
                            <button type="button" onclick="togglePassword()"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-300">
                                <i id="passwordIcon" class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input id="remember_me" name="remember_me" type="checkbox"
                                   class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-600 rounded bg-gray-700">
                            <label for="remember_me" class="ml-2 block text-sm text-gray-300">
                                Ghi nhớ đăng nhập
                            </label>
                        </div>

                        <div class="text-sm">
                            <a href="<?php echo APP_URL; ?>/admin/forgot-password" class="font-medium text-primary-400 hover:text-primary-300">
                                Quên mật khẩu?
                            </a>
                        </div>
                    </div>

                    <div>
                        <button type="submit"
                                class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-gradient-to-r from-primary-600 to-purple-600 hover:from-primary-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all duration-300">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <i class="fas fa-shield-alt text-primary-300 group-hover:text-primary-200"></i>
                            </span>
                            Đăng nhập Admin
                        </button>
                    </div>
                </form>

                <div class="mt-6">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-600"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-gray-800 text-gray-400">Thông tin đăng nhập mặc định</span>
                        </div>
                    </div>

                    <div class="mt-4 p-4 bg-gray-700 rounded-lg">
                        <div class="text-sm text-gray-300">
                            <p><strong>Tên đăng nhập:</strong> admin</p>
                            <p><strong>Mật khẩu:</strong> admin123</p>
                        </div>
                        <div class="mt-2 text-xs text-gray-400">
                            <i class="fas fa-info-circle mr-1"></i>
                            Vui lòng thay đổi mật khẩu mặc định sau khi đăng nhập lần đầu.
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center">
                <p class="text-sm text-gray-400">
                    <a href="<?php echo APP_URL; ?>" class="font-medium text-primary-400 hover:text-primary-300">
                        <i class="fas fa-arrow-left mr-1"></i> Quay lại trang chủ
                    </a>
                </p>
            </div>
        </div>
    </div>

    <script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const passwordIcon = document.getElementById('passwordIcon');

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            passwordIcon.classList.remove('fa-eye');
            passwordIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            passwordIcon.classList.remove('fa-eye-slash');
            passwordIcon.classList.add('fa-eye');
        }
    }

    // Auto-focus on username field
    document.getElementById('username').focus();
    </script>
</body>
</html>
