<?php
$page_title = 'Trang cá nhân';
include '../../includes/header.php';

$username = $_GET['username'] ?? '';
if (!$username) {
    redirect(APP_URL . '/');
}

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    redirect(APP_URL . '/');
}

// Get user's voting statistics
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_votes,
           COUNT(DISTINCT contestant_id) as unique_contestants,
           COUNT(DISTINCT DATE(created_at)) as active_days
    FROM votes
    WHERE user_id = ?
");
$stmt->execute([$user['id']]);
$vote_stats = $stmt->fetch();

// Get user's recent votes
$stmt = $pdo->prepare("
    SELECT v.*, c.name as contestant_name, ct.name as contest_name
    FROM votes v
    JOIN contestants c ON v.contestant_id = c.id
    JOIN contests ct ON c.contest_id = ct.id
    WHERE v.user_id = ?
    ORDER BY v.created_at DESC
    LIMIT 10
");
$stmt->execute([$user['id']]);
$recent_votes = $stmt->fetchAll();

// Get user's recent activities
$stmt = $pdo->prepare("
    SELECT * FROM user_activity
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$user['id']]);
$recent_activities = $stmt->fetchAll();

// Check if current user is viewing their own profile
$current_user = getCurrentUser();
$is_own_profile = $current_user && $current_user['id'] == $user['id'];
?>

<!-- Hero Section -->
<div class="bg-gradient-to-r from-primary-600 to-primary-800 text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="text-center">
            <div class="mb-6">
                <img src="<?php echo imageUrl($user['avatar_url'], 'avatar'); ?>"
                        alt="<?php echo htmlspecialchars($user['username']); ?>"
                     class="w-32 h-32 rounded-full mx-auto border-4 border-white shadow-lg">
            </div>
            <h1 class="text-4xl font-bold mb-2"><?php echo htmlspecialchars($user['username']); ?></h1>
            <?php if ($user['full_name']): ?>
                <p class="text-xl text-primary-100 mb-4"><?php echo htmlspecialchars($user['full_name']); ?></p>
            <?php endif; ?>
            <p class="text-primary-100">Thành viên từ <?php echo formatDate($user['created_at']); ?></p>
        </div>
    </div>
</div>

<!-- Statistics Section -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-lg p-6 text-center">
            <div class="text-3xl font-bold text-primary-600 mb-2"><?php echo formatNumber($vote_stats['total_votes']); ?></div>
            <div class="text-gray-600">Tổng lượt bình chọn</div>
        </div>
        <div class="bg-white rounded-lg shadow-lg p-6 text-center">
            <div class="text-3xl font-bold text-primary-600 mb-2"><?php echo formatNumber($vote_stats['unique_contestants']); ?></div>
            <div class="text-gray-600">Thí sinh đã bình chọn</div>
        </div>
        <div class="bg-white rounded-lg shadow-lg p-6 text-center">
            <div class="text-3xl font-bold text-primary-600 mb-2"><?php echo formatNumber($vote_stats['active_days']); ?></div>
            <div class="text-gray-600">Ngày hoạt động</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Recent Votes -->
        <div class="bg-white rounded-lg shadow-lg">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Lượt bình chọn gần đây</h2>
            </div>
            <div class="p-6">
                <?php if (empty($recent_votes)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-vote-yea text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-500">Chưa có lượt bình chọn nào</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($recent_votes as $vote): ?>
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                <div class="flex-1">
                                    <h3 class="font-medium text-gray-900"><?php echo htmlspecialchars($vote['contestant_name']); ?></h3>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($vote['contest_name']); ?></p>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo formatDate($vote['created_at']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="bg-white rounded-lg shadow-lg">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Hoạt động gần đây</h2>
            </div>
            <div class="p-6">
                <?php if (empty($recent_activities)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-chart-line text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-500">Chưa có hoạt động nào</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-circle text-primary-600 text-xs"></i>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-900">
                                        <?php
                                        switch ($activity['action']) {
                                            case 'user_login':
                                                echo 'Đã đăng nhập';
                                                break;
                                            case 'vote_cast':
                                                echo 'Đã bình chọn';
                                                break;
                                            case 'user_register':
                                                echo 'Đã đăng ký tài khoản';
                                                break;
                                            default:
                                                echo ucfirst(str_replace('_', ' ', $activity['action']));
                                        }
                                        ?>
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <?php echo formatDate($activity['created_at']); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Profile Actions -->
    <?php if ($is_own_profile): ?>
        <div class="mt-8 text-center">
            <a href="<?php echo APP_URL; ?>/contests" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                <i class="fas fa-trophy mr-2"></i> Xem cuộc thi
            </a>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
