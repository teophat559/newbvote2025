<?php
$page_title = 'Bảng xếp hạng';
include '../../includes/header.php';

// Get top 25 contestants
$top_contestants = getTopContestants(25);
?>

<!-- Hero Section -->
<div class="bg-gradient-to-r from-yellow-600 to-orange-600 py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl font-bold text-white mb-4">Bảng xếp hạng bình chọn</h1>
        <p class="text-xl text-gray-200 mb-8">Top 25 thí sinh nhận được nhiều lượt bình chọn nhất</p>
    </div>
</div>

<!-- Rankings Table -->
<div class="py-12">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <?php if (empty($top_contestants)): ?>
            <div class="text-center py-16">
                <i class="fas fa-trophy text-6xl text-gray-600 mb-6"></i>
                <h2 class="text-2xl font-semibold text-gray-400 mb-4">Chưa có dữ liệu bình chọn</h2>
                <p class="text-gray-500 mb-8">Hiện tại chưa có thí sinh nào nhận được lượt bình chọn.</p>
                <a href="<?php echo APP_URL; ?>/contests" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                    <i class="fas fa-trophy mr-2"></i> Xem cuộc thi
                </a>
            </div>
        <?php else: ?>
            <div class="bg-gray-800 rounded-lg shadow-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-700">
                    <h2 class="text-xl font-semibold text-white">Top 25 Thí sinh</h2>
                    <p class="text-gray-400 text-sm mt-1">Cập nhật theo thời gian thực</p>
                </div>

                <div class="overflow-y-auto max-h-96">
                    <table class="w-full">
                        <thead class="bg-gray-700 sticky top-0">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                    Hạng
                                </th>
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
                                    Thao tác
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-gray-800 divide-y divide-gray-700">
                            <?php foreach ($top_contestants as $index => $contestant): ?>
                                <tr class="hover:bg-gray-700 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <?php if ($index < 3): ?>
                                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold
                                                    <?php echo $index === 0 ? 'bg-yellow-400 text-black' : ($index === 1 ? 'bg-gray-300 text-black' : 'bg-yellow-600 text-white'); ?>">
                                                    <?php echo $index + 1; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="w-8 h-8 rounded-full bg-gray-600 flex items-center justify-center text-sm font-bold text-white">
                                                    <?php echo $index + 1; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                       <img class="h-10 w-10 rounded-full object-cover"
                                           src="<?php echo imageUrl($contestant['image_url'], 'contestant'); ?>"
                                                     alt="<?php echo htmlspecialchars($contestant['name']); ?>">
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-white">
                                                    <?php echo htmlspecialchars($contestant['name']); ?>
                                                </div>
                                                <?php if ($contestant['description']): ?>
                                                    <div class="text-sm text-gray-400 line-clamp-1">
                                                        <?php echo htmlspecialchars($contestant['description']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-300">
                                            <?php echo htmlspecialchars($contestant['contest_name']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <i class="fas fa-star text-yellow-400 mr-2"></i>
                                            <span class="text-sm font-semibold text-white">
                                                <?php echo formatNumber($contestant['total_votes']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <?php if (isUserLoggedIn()): ?>
                                            <a href="<?php echo APP_URL; ?>/contest?id=<?php echo $contestant['contest_id']; ?>"
                                               class="text-primary-400 hover:text-primary-300 mr-3">
                                                <i class="fas fa-eye mr-1"></i> Xem
                                            </a>
                                            <button onclick="voteForContestant(<?php echo $contestant['id']; ?>)"
                                                    class="text-green-400 hover:text-green-300">
                                                <i class="fas fa-vote-yea mr-1"></i> Bình chọn
                                            </button>
                                        <?php else: ?>
                                            <button onclick="showLoginModal()"
                                                    class="text-primary-400 hover:text-primary-300">
                                                <i class="fas fa-sign-in-alt mr-1"></i> Đăng nhập
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Statistics -->
            <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-500/10">
                            <i class="fas fa-trophy text-yellow-400 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-gray-400 text-sm">Tổng lượt bình chọn</p>
                            <p class="text-2xl font-bold text-white">
                                <?php echo formatNumber(array_sum(array_column($top_contestants, 'total_votes'))); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-500/10">
                            <i class="fas fa-users text-blue-400 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-gray-400 text-sm">Thí sinh tham gia</p>
                            <p class="text-2xl font-bold text-white"><?php echo count($top_contestants); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-500/10">
                            <i class="fas fa-chart-line text-green-400 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-gray-400 text-sm">Cuộc thi đang diễn ra</p>
                            <p class="text-2xl font-bold text-white">
                                <?php echo count(array_unique(array_column($top_contestants, 'contest_id'))); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Login Modal -->
<div id="loginModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-white">Đăng nhập để bình chọn</h3>
                <button onclick="hideLoginModal()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="space-y-4">
                <p class="text-gray-300 text-sm mb-4">
                    Vui lòng đăng nhập để thực hiện bình chọn cho thí sinh yêu thích.
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
    hideLoginModal();
    window.location.href = '<?php echo APP_URL; ?>/login?platform=' + platform;
}

function voteForContestant(contestantId) {
    if (!confirm('Bạn có chắc chắn muốn bình chọn cho thí sinh này?')) {
        return;
    }

    // Show loading
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Đang bình chọn...';
    button.disabled = true;

    // Send vote request
    fetch('<?php echo APP_URL; ?>/api/vote.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            contestant_id: contestantId,
            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Bình chọn thành công!');
            location.reload();
        } else {
            alert(data.message || 'Có lỗi xảy ra khi bình chọn.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Có lỗi xảy ra khi bình chọn.');
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// Close modal when clicking outside
document.getElementById('loginModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideLoginModal();
    }
});

// Auto-refresh rankings every 30 seconds
setInterval(function() {
    // Only refresh if user is not interacting with the page
    if (!document.hidden) {
        location.reload();
    }
}, 30000);
</script>

<?php include '../../includes/footer.php'; ?>
