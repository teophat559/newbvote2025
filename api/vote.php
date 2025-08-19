<?php
session_start();
require_once __DIR__ . '/../config/validate_env.php';
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once __DIR__ . '/../src/bootstrap.php';
use App\Services\AuditLogService;

// Check if it's an AJAX request
if (!isAjaxRequest()) {
    jsonResponse(['success' => false, 'message' => 'Invalid request method'], 400);
}

// Check if user is logged in
if (!isUserLoggedIn()) {
    try { AuditLogService::log('vote_failed', null, ['reason'=>'unauthorized'], 'warn'); } catch (\Throwable $e) {}
    jsonResponse(['success' => false, 'message' => 'Vui lòng đăng nhập để bình chọn'], 401);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    try { AuditLogService::log('vote_failed', $_SESSION['user']['id'] ?? null, ['reason'=>'invalid_input'], 'error'); } catch (\Throwable $e) {}
    jsonResponse(['success' => false, 'message' => 'Invalid input data'], 400);
}

// Validate CSRF token
if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
    try { AuditLogService::log('vote_failed', $_SESSION['user']['id'] ?? null, ['reason'=>'csrf_invalid'], 'error'); } catch (\Throwable $e) {}
    jsonResponse(['success' => false, 'message' => 'Token bảo mật không hợp lệ'], 403);
}

// Validate contestant ID
$contestant_id = intval($input['contestant_id'] ?? 0);
if ($contestant_id <= 0) {
    try { AuditLogService::log('vote_failed', $_SESSION['user']['id'] ?? null, ['reason'=>'invalid_contestant_id'], 'error'); } catch (\Throwable $e) {}
    jsonResponse(['success' => false, 'message' => 'ID thí sinh không hợp lệ'], 400);
}

// Get current user
$user = getCurrentUser();
if (!$user) {
    try { AuditLogService::log('vote_failed', null, ['reason'=>'invalid_user_ctx'], 'error'); } catch (\Throwable $e) {}
    jsonResponse(['success' => false, 'message' => 'Thông tin người dùng không hợp lệ'], 401);
}

// Check if contestant exists and contest is active
$contestant = getContestant($contestant_id);
if (!$contestant) {
    try { AuditLogService::log('vote_failed', $user['id'] ?? null, ['reason'=>'contestant_not_found','contestant_id'=>$contestant_id], 'warn'); } catch (\Throwable $e) {}
    jsonResponse(['success' => false, 'message' => 'Thí sinh không tồn tại'], 404);
}

// Get contest info
$contest = getContest($contestant['contest_id']);
if (!$contest || $contest['status'] !== 'active') {
    try { AuditLogService::log('vote_failed', $user['id'] ?? null, ['reason'=>'contest_inactive','contest_id'=>$contest['id'] ?? null], 'warn'); } catch (\Throwable $e) {}
    jsonResponse(['success' => false, 'message' => 'Cuộc thi không còn hoạt động'], 400);
}

// Check if user already voted for this contestant
$stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE contestant_id = ? AND user_id = ?");
$stmt->execute([$contestant_id, $user['id']]);

if ($stmt->fetchColumn() > 0) {
    try { AuditLogService::log('vote_failed', $user['id'] ?? null, ['reason'=>'duplicate_vote','contestant_id'=>$contestant_id], 'warn'); } catch (\Throwable $e) {}
    jsonResponse(['success' => false, 'message' => 'Bạn đã bình chọn cho thí sinh này rồi'], 400);
}

// Check if user has reached vote limit for this contest
$max_votes_per_contest = intval(getSetting('max_votes_per_contest', 1));
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM votes v
    JOIN contestants c ON v.contestant_id = c.id
    WHERE c.contest_id = ? AND v.user_id = ?
");
$stmt->execute([$contest['id'], $user['id']]);

if ($stmt->fetchColumn() >= $max_votes_per_contest) {
    try { AuditLogService::log('vote_failed', $user['id'] ?? null, ['reason'=>'quota_reached','contest_id'=>$contest['id'], 'limit'=>$max_votes_per_contest], 'warn'); } catch (\Throwable $e) {}
    jsonResponse(['success' => false, 'message' => "Bạn đã đạt giới hạn bình chọn cho cuộc thi này (tối đa $max_votes_per_contest lượt)"], 400);
}

// Process vote
$result = voteForContestant($contestant_id, $user['id']);

if ($result['success']) {
    try { AuditLogService::log('vote_cast', $user['id'] ?? null, ['contest_id'=>$contest['id'], 'contestant_id'=>$contestant_id]); } catch (\Throwable $e) {}
    // Send notification to admin about new vote
    $admin_users = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
    foreach ($admin_users as $admin) {
        createNotification(
            $admin['id'],
            'Bình chọn mới',
            "Người dùng {$user['username']} vừa bình chọn cho thí sinh {$contestant['name']} trong cuộc thi {$contest['name']}",
            'info'
        );
    }

    jsonResponse(['success' => true, 'message' => $result['message']]);
} else {
    try { AuditLogService::log('vote_failed', $user['id'] ?? null, ['reason'=>'vote_process_failed','contest_id'=>$contest['id'] ?? null, 'contestant_id'=>$contestant_id], 'error'); } catch (\Throwable $e) {}
    jsonResponse(['success' => false, 'message' => $result['message']], 400);
}
?>
