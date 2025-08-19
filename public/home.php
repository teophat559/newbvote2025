<?php
$page_title = 'Trang chủ';
include __DIR__ . '/../../includes/header.php';

// Get active contests
$contests = getContests('active', 2); // Tối đa 2 cuộc thi nổi bật
$top_contestants = getTopContestants(3); // Tối đa 3 thí sinh nổi bật
$stats = getStatistics();
?>

<!-- Hero Section -->
<div class="relative overflow-hidden">
    <div class="gradient-bg py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="animate-fade-in">
                <h1 class="text-4xl md:text-6xl font-bold text-white mb-6">
                    Chào mừng đến với <span class="text-primary-300"><?php echo getSetting('site_name', APP_NAME); ?></span>
                </h1>
                <p class="text-xl text-gray-200 mb-8 max-w-3xl mx-auto">
                    <?php echo getSetting('site_description', 'Hệ thống bình chọn trực tuyến hiện đại và an toàn. Tham gia các cuộc thi thú vị và bình chọn cho thí sinh yêu thích của bạn.'); ?>
                </p>

                <?php if (!isUserLoggedIn()): ?>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="<?php echo APP_URL; ?>/login" class="bg-primary-600 hover:bg-primary-700 text-white px-8 py-4 rounded-full text-lg font-semibold transition-all duration-300 hover:scale-105">
                            <i class="fas fa-sign-in-alt mr-2"></i> Đăng nhập
                        </a>
                        <a href="<?php echo APP_URL; ?>/register" class="bg-transparent border-2 border-white text-white hover:bg-white hover:text-gray-900 px-8 py-4 rounded-full text-lg font-semibold transition-all duration-300">
                            <i class="fas fa-user-plus mr-2"></i> Đăng ký
                        </a>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="<?php echo APP_URL; ?>/contests" class="bg-primary-600 hover:bg-primary-700 text-white px-8 py-4 rounded-full text-lg font-semibold transition-all duration-300 hover:scale-105">
                            <i class="fas fa-trophy mr-2"></i> Xem cuộc thi
                        </a>
                        <a href="<?php echo APP_URL; ?>/rankings" class="bg-transparent border-2 border-white text-white hover:bg-white hover:text-gray-900 px-8 py-4 rounded-full text-lg font-semibold transition-all duration-300">
                            <i class="fas fa-chart-bar mr-2"></i> Bảng xếp hạng
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Section -->
<div class="py-16 bg-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
            <div class="text-center">
                <div class="text-3xl font-bold text-primary-400 mb-2"><?php echo formatNumber($stats['total_users']); ?></div>
                <div class="text-gray-400">Người dùng</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-primary-400 mb-2"><?php echo formatNumber($stats['active_contests']); ?></div>
                <div class="text-gray-400">Cuộc thi đang diễn ra</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-primary-400 mb-2"><?php echo formatNumber($stats['total_contestants']); ?></div>
                <div class="text-gray-400">Thí sinh</div>
            </div>
            <div class="text-center">
                <div class="text-3xl font-bold text-primary-400 mb-2"><?php echo formatNumber($stats['total_votes']); ?></div>
                <div class="text-gray-400">Lượt bình chọn</div>
            </div>
        </div>
    </div>
</div>

<!-- Active Contests Section -->
<div class="py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-white mb-4">Cuộc thi đang diễn ra</h2>
            <p class="text-gray-400 max-w-2xl mx-auto">
                Khám phá các cuộc thi thú vị và bình chọn cho thí sinh yêu thích của bạn
            </p>
        </div>

        <?php if (empty($contests)): ?>
            <div class="text-center py-12">
                <i class="fas fa-trophy text-6xl text-gray-600 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-400 mb-2">Chưa có cuộc thi nào</h3>
                <p class="text-gray-500">Hiện tại chưa có cuộc thi nào đang diễn ra.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($contests as $contest): ?>
                    <div class="bg-gray-800 rounded-xl overflow-hidden shadow-lg hover:shadow-primary/20 hover:border-primary/50 transition-all duration-300 group">
                        <div class="relative">
                            <img src="<?php echo imageUrl($contest['banner_url'], 'banner'); ?>"
                                 alt="<?php echo htmlspecialchars($contest['name']); ?>"
                                 class="w-full h-48 object-cover transition-transform duration-300 group-hover:scale-105">
                            <div class="absolute top-3 right-3 bg-green-500/80 text-white text-xs font-bold px-2 py-1 rounded-full backdrop-blur-sm">
                                Đang diễn ra
                            </div>
                        </div>
                        <div class="p-6">
                            <h3 class="text-xl font-bold text-white mb-2"><?php echo htmlspecialchars($contest['name']); ?></h3>
                            <p class="text-gray-400 text-sm mb-4 line-clamp-2"><?php echo htmlspecialchars($contest['description']); ?></p>
                            <div class="flex justify-between items-center text-sm text-gray-400 mb-4">
                                <div class="flex items-center">
                                    <i class="fas fa-users text-primary-400 mr-1"></i>
                                    <span><?php echo $contest['contestant_count']; ?> thí sinh</span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-star text-yellow-400 mr-1"></i>
                                    <span><?php echo formatNumber($contest['total_votes']); ?> phiếu</span>
                                </div>
                            </div>
                            <a href="<?php echo APP_URL; ?>/contest?id=<?php echo $contest['id']; ?>"
                               class="block w-full bg-gradient-to-r from-primary-600 to-purple-600 hover:from-primary-700 hover:to-purple-700 text-white text-center py-3 rounded-lg font-semibold transition-all duration-300 group-hover:scale-105">
                                <i class="fas fa-eye mr-2"></i> Xem ngay
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-8">
                <a href="<?php echo APP_URL; ?>/contests" class="inline-flex items-center bg-gray-700 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-semibold transition-all duration-300">
                    <i class="fas fa-list mr-2"></i> Xem tất cả cuộc thi
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Top Contestants Section -->
<?php if (!empty($top_contestants)): ?>
<div class="py-16 bg-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-white mb-4">Thí sinh nổi bật</h2>
            <p class="text-gray-400 max-w-2xl mx-auto">
                Những thí sinh nhận được nhiều lượt bình chọn nhất
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($top_contestants as $index => $contestant): ?>
                <div class="bg-gray-700 rounded-lg p-6 hover:bg-gray-600 transition-all duration-300">
                    <div class="flex items-center mb-4">
                        <div class="relative">
                            <img src="<?php echo imageUrl($contestant['image_url'], 'contestant'); ?>"
                                 alt="<?php echo htmlspecialchars($contestant['name']); ?>"
                                 class="w-16 h-16 rounded-full object-cover">
                            <?php if ($index < 3): ?>
                                <div class="absolute -top-2 -right-2 w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold
                                    <?php echo $index === 0 ? 'bg-yellow-400 text-black' : ($index === 1 ? 'bg-gray-300 text-black' : 'bg-yellow-600 text-white'); ?>">
                                    <?php echo $index + 1; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="ml-4 flex-1">
                            <h3 class="text-lg font-semibold text-white"><?php echo htmlspecialchars($contestant['name']); ?></h3>
                            <p class="text-gray-400 text-sm"><?php echo htmlspecialchars($contestant['contest_name']); ?></p>
                        </div>
                    </div>
                    <div class="flex justify-between items-center">
                        <div class="text-primary-400 font-semibold">
                            <i class="fas fa-star mr-1"></i>
                            <?php echo formatNumber($contestant['total_votes']); ?> phiếu
                        </div>
                        <a href="<?php echo APP_URL; ?>/contest?id=<?php echo $contestant['contest_id']; ?>"
                           class="text-primary-400 hover:text-primary-300 text-sm">
                            <i class="fas fa-eye mr-1"></i> Xem
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Features Section -->
<div class="py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-white mb-4">Tại sao chọn chúng tôi?</h2>
            <p class="text-gray-400 max-w-2xl mx-auto">
                Hệ thống bình chọn trực tuyến với nhiều tính năng hiện đại và bảo mật cao
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="text-center">
                <div class="w-16 h-16 bg-primary-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-shield-alt text-2xl text-white"></i>
                </div>
                <h3 class="text-xl font-semibold text-white mb-2">Bảo mật cao</h3>
                <p class="text-gray-400">Hệ thống bảo mật đa lớp, đảm bảo tính minh bạch và công bằng trong mọi cuộc thi.</p>
            </div>

            <div class="text-center">
                <div class="w-16 h-16 bg-primary-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-mobile-alt text-2xl text-white"></i>
                </div>
                <h3 class="text-xl font-semibold text-white mb-2">Tương thích mọi thiết bị</h3>
                <p class="text-gray-400">Giao diện responsive, hoạt động mượt mà trên mọi thiết bị từ điện thoại đến máy tính.</p>
            </div>

            <div class="text-center">
                <div class="w-16 h-16 bg-primary-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-chart-line text-2xl text-white"></i>
                </div>
                <h3 class="text-xl font-semibold text-white mb-2">Thống kê chi tiết</h3>
                <p class="text-gray-400">Theo dõi kết quả bình chọn theo thời gian thực với biểu đồ và báo cáo chi tiết.</p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
