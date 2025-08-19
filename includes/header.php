<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?></title>
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>" />
    <meta name="description" content="<?php echo getSetting('site_description', 'Hệ thống bình chọn trực tuyến'); ?>">

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

    <!-- Custom CSS -->
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .hover-scale {
            transition: transform 0.3s ease;
        }
        .hover-scale:hover {
            transform: scale(1.05);
        }
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <!-- Navigation -->
    <nav class="bg-gray-800 border-b border-gray-700">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="<?php echo APP_URL; ?>" class="flex items-center space-x-2">
                        <i class="fas fa-crown text-primary-400 text-2xl"></i>
                        <span class="text-xl font-bold text-white"><?php echo getSetting('site_name', APP_NAME); ?></span>
                    </a>
                </div>

                <div class="flex items-center space-x-4">
                    <?php if (isUserLoggedIn()): ?>
                        <a href="<?php echo APP_URL; ?>/contests" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-trophy mr-1"></i> Cuộc thi
                        </a>
                        <a href="<?php echo APP_URL; ?>/rankings" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-chart-bar mr-1"></i> Bảng xếp hạng
                        </a>

                        <!-- Notifications -->
                        <?php
                        $unread_count = getUnreadNotificationCount($_SESSION['user_id']);
                        ?>
                        <div class="relative">
                            <a href="<?php echo APP_URL; ?>/notifications" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-bell mr-1"></i>
                                <?php if ($unread_count > 0): ?>
                                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                        <?php echo $unread_count; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </div>

                        <!-- User Menu -->
                        <div class="relative">
                            <button class="flex items-center space-x-2 text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium" onclick="toggleUserMenu()">
                                <img src="<?php echo imageUrl(getCurrentUser()['avatar_url'] ?? null, 'avatar'); ?>"
                                     alt="Avatar" class="h-8 w-8 rounded-full">
                                <span><?php echo getCurrentUser()['username']; ?></span>
                                <i class="fas fa-chevron-down"></i>
                            </button>

                            <div id="userMenu" class="hidden absolute right-0 mt-2 w-48 bg-gray-700 rounded-md shadow-lg py-1 z-50">
                                <a href="<?php echo APP_URL; ?>/user/<?php echo getCurrentUser()['username']; ?>" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-600">
                                    <i class="fas fa-user mr-2"></i> Hồ sơ
                                </a>
                                <a href="<?php echo APP_URL; ?>/settings" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-600">
                                    <i class="fas fa-cog mr-2"></i> Cài đặt
                                </a>
                                <hr class="border-gray-600">
                                <a href="<?php echo APP_URL; ?>/logout" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-600">
                                    <i class="fas fa-sign-out-alt mr-2"></i> Đăng xuất
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo APP_URL; ?>/contests" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-trophy mr-1"></i> Cuộc thi
                        </a>
                        <a href="<?php echo APP_URL; ?>/rankings" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-chart-bar mr-1"></i> Bảng xếp hạng
                        </a>
                        <a href="<?php echo APP_URL; ?>/login" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-sign-in-alt mr-1"></i> Đăng nhập
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    <?php $flash_messages = getFlashMessages(); ?>
    <?php if (!empty($flash_messages)): ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <?php foreach ($flash_messages as $message): ?>
                <div class="mb-4 p-4 rounded-md <?php
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
        </div>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <script>
            function toggleUserMenu() {
                const menu = document.getElementById('userMenu');
                menu.classList.toggle('hidden');
            }

            // Close menu when clicking outside
            document.addEventListener('click', function(event) {
                const menu = document.getElementById('userMenu');
                const button = event.target.closest('button');

                if (!button && !menu.contains(event.target)) {
                    menu.classList.add('hidden');
                }
            });
        </script>
