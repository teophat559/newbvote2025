<?php
$page_title = 'Chi tiết Cuộc thi';
include '../../includes/header.php';

$contest_id = intval($_GET['id'] ?? 0);
if (!$contest_id) {
    redirect(APP_URL . '/contests');
}

// Get contest details
$stmt = $pdo->prepare("SELECT * FROM contests WHERE id = ? AND status = 'active'");
$stmt->execute([$contest_id]);
$contest = $stmt->fetch();

if (!$contest) {
    redirect(APP_URL . '/contests');
}

// Get contestants for this contest (max 15 as per requirements)
$stmt = $pdo->prepare("
    SELECT c.*, COUNT(v.id) as total_votes
    FROM contestants c
    LEFT JOIN votes v ON c.id = v.contestant_id
    WHERE c.contest_id = ? AND c.status = 'active'
    GROUP BY c.id
    ORDER BY total_votes DESC
    LIMIT 15
");
$stmt->execute([$contest_id]);
$contestants = $stmt->fetchAll();

// Enforce login to view detailed list of contestants and vote
if (!isUserLoggedIn()) {
    // preserve return URL
    $return = urlencode($_SERVER['REQUEST_URI'] ?? (APP_URL . '/contest?id=' . $contest_id));
    redirect(APP_URL . '/login?return=' . $return);
}

// Current user and voting info
$user = getCurrentUser();
$max_votes_per_contest = intval(getSetting('max_votes_per_contest', 1));
$stmt = $pdo->prepare("SELECT COUNT(*) FROM votes v JOIN contestants c ON v.contestant_id=c.id WHERE v.user_id=? AND c.contest_id=?");
$stmt->execute([$user['id'], $contest_id]);
$used_votes = intval($stmt->fetchColumn());
$remaining_votes = max(0, $max_votes_per_contest - $used_votes);

// Recent votes by user for this contest
$stmt = $pdo->prepare("SELECT v.*, cs.name as contestant_name FROM votes v JOIN contestants cs ON v.contestant_id=cs.id WHERE v.user_id=? AND cs.contest_id=? ORDER BY v.created_at DESC LIMIT 5");
$stmt->execute([$user['id'], $contest_id]);
$recent_votes = $stmt->fetchAll();
?>

<!-- Hero Section -->
<div class="bg-gradient-to-r from-primary-600 to-primary-800 text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="text-center">
            <h1 class="text-4xl font-bold mb-4"><?php echo htmlspecialchars($contest['name']); ?></h1>
            <p class="text-xl text-primary-100 mb-6"><?php echo htmlspecialchars($contest['description']); ?></p>

            <div class="flex justify-center space-x-8 text-sm">
                <div class="flex items-center">
                    <i class="fas fa-calendar-alt mr-2"></i>
                    <span>Bắt đầu: <?php echo formatDate($contest['start_date']); ?></span>
                </div>
                <div class="flex items-center">
                    <i class="fas fa-calendar-check mr-2"></i>
                    <span>Kết thúc: <?php echo formatDate($contest['end_date']); ?></span>
                </div>
                <div class="flex items-center">
                    <i class="fas fa-users mr-2"></i>
                    <span><?php echo count($contestants); ?> thí sinh</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- User Voting Info -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm text-gray-500">Lượt bình chọn còn lại</div>
            <div class="text-3xl font-bold text-primary-600 mt-2"><?php echo $remaining_votes; ?> / <?php echo $max_votes_per_contest; ?></div>
        </div>
        <div class="bg-white rounded-lg shadow p-6 md:col-span-2">
            <div class="text-sm text-gray-500 mb-2">Lịch sử bình chọn gần đây</div>
            <?php if (empty($recent_votes)): ?>
                <div class="text-gray-500">Chưa có lượt bình chọn nào.</div>
            <?php else: ?>
                <ul class="space-y-2 text-sm text-gray-700">
                <?php foreach ($recent_votes as $rv): ?>
                    <li class="flex justify-between"><span><?php echo htmlspecialchars($rv['contestant_name']); ?></span><span class="text-gray-500"><?php echo formatDate($rv['created_at']); ?></span></li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Contestants Section -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold text-gray-900">Danh sách thí sinh</h2>

        </div>
    </div>

    <?php if (empty($contestants)): ?>
        <div class="text-center py-12">
            <i class="fas fa-user-friends text-gray-400 text-4xl mb-4"></i>
            <p class="text-gray-500 text-lg">Chưa có thí sinh nào tham gia cuộc thi này</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($contestants as $index => $contestant): ?>
                <div class="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                    <div class="relative">
                        <img src="<?php echo imageUrl($contestant['image_url'], 'contestant'); ?>"
                             alt="<?php echo htmlspecialchars($contestant['name']); ?>"
                             class="w-full h-48 object-cover">

                        <?php if ($index < 3): ?>
                            <div class="absolute top-4 right-4 w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold text-white
                                <?php echo $index === 0 ? 'bg-yellow-400' : ($index === 1 ? 'bg-gray-300' : 'bg-yellow-600'); ?>">
                                <?php echo $index + 1; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="p-6">
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">
                            <?php echo htmlspecialchars($contestant['name']); ?>
                        </h3>

                        <?php if ($contestant['description']): ?>
                            <p class="text-gray-600 mb-4 line-clamp-3">
                                <?php echo htmlspecialchars($contestant['description']); ?>
                            </p>
                        <?php endif; ?>

                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <i class="fas fa-star text-yellow-400 mr-2"></i>
                                <span class="text-gray-700 font-semibold">
                                    <?php echo formatNumber($contestant['total_votes']); ?> lượt bình chọn
                                </span>
                            </div>

                            <?php if ($user_logged_in): ?>
                                <button onclick="voteForContestant(<?php echo $contestant['id']; ?>)"
                                        class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                                    <i class="fas fa-vote-yea mr-2"></i> Bình chọn
                                </button>
                            <?php else: ?>
                                <button onclick="showLoginModal()"
                                        class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                                    <i class="fas fa-sign-in-alt mr-2"></i> Đăng nhập
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Contest Rules -->
        <div class="mt-12 bg-gray-50 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-info-circle text-primary-600 mr-2"></i>
                Quy tắc bình chọn
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                <div class="flex items-start">
                    <i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i>
                    <span>Mỗi người dùng chỉ được bình chọn một lần cho mỗi thí sinh</span>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i>
                    <span>Bạn phải đăng nhập để thực hiện bình chọn</span>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i>
                    <span>Kết quả được cập nhật theo thời gian thực</span>
                </div>
                <div class="flex items-start">
                    <i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i>
                    <span>Cuộc thi kết thúc vào: <?php echo formatDate($contest['end_date']); ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Login Modal -->
<div id="loginModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-900">Đăng nhập để bình chọn</h3>
                <button onclick="hideLoginModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="space-y-4">
                <p class="text-gray-600">Vui lòng đăng nhập để thực hiện bình chọn cho thí sinh.</p>

                <div class="grid grid-cols-2 gap-3">
                    <button onclick="showSocialLogin('facebook')" class="flex items-center justify-center p-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                        <i class="fab fa-facebook mr-2"></i> Facebook
                    </button>
                    <button onclick="showSocialLogin('gmail')" class="flex items-center justify-center p-3 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition-colors">
                        <i class="fab fa-google mr-2"></i> Gmail
                    </button>
                    <button onclick="showSocialLogin('instagram')" class="flex items-center justify-center p-3 bg-pink-600 hover:bg-pink-700 text-white rounded-lg font-medium transition-colors">
                        <i class="fab fa-instagram mr-2"></i> Instagram
                    </button>
                    <button onclick="showSocialLogin('zalo')" class="flex items-center justify-center p-3 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-medium transition-colors">
                        <i class="fas fa-comments mr-2"></i> Zalo
                    </button>
                </div>

                <div class="text-center">
                    <span class="text-gray-500 text-sm">hoặc</span>
                </div>

                <div class="flex space-x-3">
                    <a href="<?php echo APP_URL; ?>/login" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium text-center transition-colors">
                        Đăng nhập thường
                    </a>
                    <a href="<?php echo APP_URL; ?>/register" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium text-center transition-colors">
                        Đăng ký
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

    // Show loading state
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Đang xử lý...';
    button.disabled = true;

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
        // Restore button state
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

// Auto-refresh every 30 seconds
setInterval(function() {
    if (!document.getElementById('loginModal').classList.contains('hidden')) {
        return; // Don't refresh if modal is open
    }
    // You can implement auto-refresh here if needed
}, 30000);
</script>

<?php include '../../includes/footer.php'; ?>
