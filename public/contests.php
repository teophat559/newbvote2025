<?php
$page_title = 'Danh sách cuộc thi';
include __DIR__ . '/../../includes/header.php';

// Get active contests (limit to 2 for featured contests)
$contests = getContests('active', 2);
$search_query = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));

if (!empty($search_query)) {
    $contests = searchContests($search_query);
}
?>

<!-- Hero Section -->
<div class="bg-gradient-to-r from-primary-600 to-purple-600 py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl font-bold text-white mb-4">Cuộc thi nổi bật</h1>
        <p class="text-xl text-gray-200 mb-8">Khám phá các cuộc thi thú vị và bình chọn cho thí sinh yêu thích</p>

        <!-- Search Bar -->
        <div class="max-w-md mx-auto">
            <form method="GET" class="flex">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>"
                       placeholder="Tìm kiếm cuộc thi..."
                       class="flex-1 px-4 py-3 rounded-l-lg border-0 focus:ring-2 focus:ring-white focus:outline-none">
                <button type="submit" class="bg-white text-primary-600 px-6 py-3 rounded-r-lg font-semibold hover:bg-gray-100 transition-colors">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Contests Grid -->
<div class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <?php if (empty($contests)): ?>
            <div class="text-center py-16">
                <i class="fas fa-trophy text-6xl text-gray-600 mb-6"></i>
                <h2 class="text-2xl font-semibold text-gray-400 mb-4">Không tìm thấy cuộc thi</h2>
                <p class="text-gray-500 mb-8">Hiện tại chưa có cuộc thi nào phù hợp với tìm kiếm của bạn.</p>
                <a href="<?php echo APP_URL; ?>/contests" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i> Xem tất cả cuộc thi
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <?php foreach ($contests as $contest): ?>
                    <div class="bg-gray-800 rounded-xl overflow-hidden shadow-lg hover:shadow-primary/20 hover:border-primary/50 transition-all duration-300 group border border-gray-700">
                        <div class="relative">
                                <img src="<?php echo imageUrl($contest['banner_url'], 'banner'); ?>"
                                 alt="<?php echo htmlspecialchars($contest['name']); ?>"
                                 class="w-full h-48 object-cover transition-transform duration-300 group-hover:scale-105">
                            <div class="absolute top-3 right-3 bg-green-500/80 text-white text-xs font-bold px-2 py-1 rounded-full backdrop-blur-sm">
                                Đang diễn ra
                            </div>
                            <?php if ($contest['start_date'] && $contest['end_date']): ?>
                                <div class="absolute bottom-3 left-3 bg-black/60 text-white text-xs px-2 py-1 rounded backdrop-blur-sm">
                                    <i class="fas fa-calendar mr-1"></i>
                                    <?php echo formatDate($contest['start_date'], 'd/m/Y'); ?> - <?php echo formatDate($contest['end_date'], 'd/m/Y'); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="p-6">
                            <h3 class="text-xl font-bold text-white mb-2"><?php echo htmlspecialchars($contest['name']); ?></h3>
                            <p class="text-gray-400 text-sm mb-4 line-clamp-3"><?php echo htmlspecialchars($contest['description']); ?></p>

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

                            <?php if (isUserLoggedIn()): ?>
                                <a href="<?php echo APP_URL; ?>/contest?id=<?php echo $contest['id']; ?>"
                                   class="block w-full bg-gradient-to-r from-primary-600 to-purple-600 hover:from-primary-700 hover:to-purple-700 text-white text-center py-3 rounded-lg font-semibold transition-all duration-300 group-hover:scale-105">
                                    <i class="fas fa-eye mr-2"></i> Xem chi tiết
                                </a>
                            <?php else: ?>
                                <button onclick="showLoginModal()"
                                        class="block w-full bg-gradient-to-r from-primary-600 to-purple-600 hover:from-primary-700 hover:to-purple-700 text-white text-center py-3 rounded-lg font-semibold transition-all duration-300 group-hover:scale-105">
                                    <i class="fas fa-sign-in-alt mr-2"></i> Đăng nhập để xem
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Clear search button -->
            <?php if (!empty($search_query)): ?>
                <div class="text-center mt-8">
                    <a href="<?php echo APP_URL; ?>/contests" class="bg-gray-700 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                        <i class="fas fa-times mr-2"></i> Xóa tìm kiếm
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Login Modal -->
<div id="loginModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-white">Đăng nhập để tiếp tục</h3>
                <button onclick="hideLoginModal()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="space-y-4">
                <p class="text-gray-300 text-sm mb-4">
                    Vui lòng đăng nhập để xem chi tiết cuộc thi và thực hiện bình chọn.
                </p>

                <div class="grid grid-cols-3 gap-3">
                    <button onclick="showSocialLogin('facebook')" class="flex flex-col items-center p-4 bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                        <i class="fab fa-facebook text-2xl text-white mb-2"></i>
                        <span class="text-white text-xs">Facebook</span>
                    </button>

                    <button onclick="showSocialLogin('gmail')" class="flex flex-col items-center p-4 bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
                        <i class="fab fa-google text-2xl text-white mb-2"></i>
                        <span class="text-white text-xs">Gmail</span>
                    </button>

                    <button onclick="showSocialLogin('instagram')" class="flex flex-col items-center p-4 bg-pink-600 hover:bg-pink-700 rounded-lg transition-colors">
                        <i class="fab fa-instagram text-2xl text-white mb-2"></i>
                        <span class="text-white text-xs">Instagram</span>
                    </button>

                    <button onclick="showSocialLogin('zalo')" class="flex flex-col items-center p-4 bg-blue-500 hover:bg-blue-600 rounded-lg transition-colors">
                        <i class="fas fa-comments text-2xl text-white mb-2"></i>
                        <span class="text-white text-xs">Zalo</span>
                    </button>

                    <button onclick="showSocialLogin('yahoo')" class="flex flex-col items-center p-4 bg-purple-600 hover:bg-purple-700 rounded-lg transition-colors">
                        <i class="fas fa-envelope text-2xl text-white mb-2"></i>
                        <span class="text-white text-xs">Yahoo</span>
                    </button>

                    <button onclick="showSocialLogin('microsoft')" class="flex flex-col items-center p-4 bg-blue-700 hover:bg-blue-800 rounded-lg transition-colors">
                        <i class="fab fa-microsoft text-2xl text-white mb-2"></i>
                        <span class="text-white text-xs">Microsoft</span>
                    </button>
                </div>

                <div class="text-center mt-4">
                    <a href="<?php echo APP_URL; ?>/login" class="text-primary-400 hover:text-primary-300 text-sm">
                        Đăng nhập bằng tài khoản khác
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showLoginModal() {
    document.getElementById('loginModal').classList.remove('hidden');
}

function hideLoginModal() {
    document.getElementById('loginModal').classList.add('hidden');
}

function showSocialLogin(platform) {
    // Hide modal first
    hideLoginModal();

    // Show loading and redirect to login with platform parameter
    window.location.href = '<?php echo APP_URL; ?>/login?platform=' + platform;
}

// Close modal when clicking outside
document.getElementById('loginModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideLoginModal();
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
