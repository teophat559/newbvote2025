<?php
// Authentication functions

// Check if user is logged in
function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check if admin is logged in
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

// Get current user data
function getCurrentUser() {
    $pdo = App\Core\DB::conn();

    if (!isUserLoggedIn()) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Get current admin data
function getCurrentAdmin() {
    $pdo = App\Core\DB::conn();

    if (!isAdminLoggedIn()) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch();
}

// User login
function userLogin($username, $password) {
    $pdo = App\Core\DB::conn();

    // Check login attempts
    if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
        if (time() - $_SESSION['last_attempt'] < LOGIN_TIMEOUT) {
            setFlashMessage('error', 'Quá nhiều lần đăng nhập thất bại. Vui lòng thử lại sau 15 phút.');
            return false;
        } else {
            unset($_SESSION['login_attempts']);
            unset($_SESSION['last_attempt']);
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active'");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Reset login attempts
        unset($_SESSION['login_attempts']);
        unset($_SESSION['last_attempt']);

        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['last_activity'] = time();

        // Log activity
        logActivity($user['id'], 'user_login', 'User logged in successfully');

        return true;
    } else {
        // Increment login attempts
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        $_SESSION['last_attempt'] = time();

        setFlashMessage('error', 'Tên đăng nhập hoặc mật khẩu không đúng.');
        return false;
    }
}

// Admin login
function adminLogin($username, $password) {
    $pdo = App\Core\DB::conn();

    // Check login attempts
    if (isset($_SESSION['admin_login_attempts']) && $_SESSION['admin_login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
        if (time() - $_SESSION['admin_last_attempt'] < LOGIN_TIMEOUT) {
            setFlashMessage('error', 'Quá nhiều lần đăng nhập thất bại. Vui lòng thử lại sau 15 phút.');
            return false;
        } else {
            unset($_SESSION['admin_login_attempts']);
            unset($_SESSION['admin_last_attempt']);
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND role = 'admin'");
    $stmt->execute([$username, $username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        // Reset login attempts
        unset($_SESSION['admin_login_attempts']);
        unset($_SESSION['admin_last_attempt']);

        // Set session
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_role'] = $admin['role'];
        $_SESSION['admin_last_activity'] = time();

        // Log activity
        logActivity($admin['id'], 'admin_login', 'Admin logged in successfully');

        return true;
    } else {
        // Increment login attempts
        $_SESSION['admin_login_attempts'] = ($_SESSION['admin_login_attempts'] ?? 0) + 1;
        $_SESSION['admin_last_attempt'] = time();

        setFlashMessage('error', 'Tên đăng nhập hoặc mật khẩu không đúng.');
        return false;
    }
}

// User logout
function userLogout() {
    if (isUserLoggedIn()) {
        logActivity($_SESSION['user_id'], 'user_logout', 'User logged out');
    }

    unset($_SESSION['user_id']);
    unset($_SESSION['user_username']);
    unset($_SESSION['user_role']);
    unset($_SESSION['last_activity']);
}

// Admin logout
function adminLogout() {
    if (isAdminLoggedIn()) {
        logActivity($_SESSION['admin_id'], 'admin_logout', 'Admin logged out');
    }

    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_username']);
    unset($_SESSION['admin_role']);
    unset($_SESSION['admin_last_activity']);
}

// Check session timeout
function checkSessionTimeout() {
    if (isUserLoggedIn() && isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            userLogout();
            setFlashMessage('warning', 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.');
            return false;
        }
        $_SESSION['last_activity'] = time();
    }

    if (isAdminLoggedIn() && isset($_SESSION['admin_last_activity'])) {
        if (time() - $_SESSION['admin_last_activity'] > SESSION_TIMEOUT) {
            adminLogout();
            setFlashMessage('warning', 'Phiên đăng nhập admin đã hết hạn. Vui lòng đăng nhập lại.');
            return false;
        }
        $_SESSION['admin_last_activity'] = time();
    }

    return true;
}

// User registration
function registerUser($username, $email, $password, $full_name = '') {
    $pdo = App\Core\DB::conn();

    // Validate input
    if (!validateUsername($username)) {
        setFlashMessage('error', 'Tên đăng nhập không hợp lệ. Chỉ cho phép 3-20 ký tự, bao gồm chữ cái, số và dấu gạch dưới.');
        return false;
    }

    if (!validateEmail($email)) {
        setFlashMessage('error', 'Email không hợp lệ.');
        return false;
    }

    if (!validatePassword($password)) {
        setFlashMessage('error', 'Mật khẩu phải có ít nhất 6 ký tự.');
        return false;
    }

    // Check if username or email already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);

    if ($stmt->fetchColumn() > 0) {
        setFlashMessage('error', 'Tên đăng nhập hoặc email đã tồn tại.');
        return false;
    }

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert user
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password, full_name, status, role)
        VALUES (?, ?, ?, ?, 'active', 'user')
    ");

    if ($stmt->execute([$username, $email, $hashed_password, $full_name])) {
        $user_id = $pdo->lastInsertId();
        logActivity($user_id, 'user_register', 'New user registered');
        setFlashMessage('success', 'Đăng ký thành công! Vui lòng đăng nhập.');
        return true;
    } else {
        setFlashMessage('error', 'Có lỗi xảy ra khi đăng ký. Vui lòng thử lại.');
        return false;
    }
}

// Change password
function changePassword($user_id, $current_password, $new_password) {
    $pdo = App\Core\DB::conn();

    // Get current user
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($current_password, $user['password'])) {
        setFlashMessage('error', 'Mật khẩu hiện tại không đúng.');
        return false;
    }

    if (!validatePassword($new_password)) {
        setFlashMessage('error', 'Mật khẩu mới phải có ít nhất 6 ký tự.');
        return false;
    }

    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    if ($stmt->execute([$hashed_password, $user_id])) {
        logActivity($user_id, 'password_change', 'Password changed successfully');
        setFlashMessage('success', 'Đổi mật khẩu thành công.');
        return true;
    } else {
        setFlashMessage('error', 'Có lỗi xảy ra khi đổi mật khẩu.');
        return false;
    }
}

// Require authentication
function requireAuth() {
    if (!isUserLoggedIn()) {
        setFlashMessage('error', 'Vui lòng đăng nhập để tiếp tục.');
        redirect('login.php');
    }
}

// Require admin authentication
function requireAdminAuth() {
    if (!isAdminLoggedIn()) {
        setFlashMessage('error', 'Vui lòng đăng nhập với quyền admin.');
        redirect('admin/login.php');
    }
}

// Check CSRF token
function getJsonInputCached() {
    static $cached = null;
    if ($cached !== null) return $cached;
    $raw = file_get_contents('php://input');
    $decoded = null;
    if ($raw) {
        $tmp = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $decoded = $tmp;
        }
    }
    $cached = $decoded ?: [];
    return $cached;
}

function checkCSRFToken() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $valid = false;

        // 1) Standard form POST
        if (isset($_POST['csrf_token']) && $_POST['csrf_token'] === ($_SESSION['csrf_token'] ?? '')) {
            $valid = true;
        }

        // 2) JSON body { csrf_token: "..." }
        if (!$valid) {
            $input = getJsonInputCached();
            if (is_array($input) && isset($input['csrf_token']) && $input['csrf_token'] === ($_SESSION['csrf_token'] ?? '')) {
                $valid = true;
            }
        }

        // 3) Header X-CSRF-Token
        if (!$valid) {
            $headers = function_exists('getallheaders') ? getallheaders() : [];
            $headerToken = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? null;
            if ($headerToken && $headerToken === ($_SESSION['csrf_token'] ?? '')) {
                $valid = true;
            }
        }

        if (!$valid) {
            setFlashMessage('error', 'Token bảo mật không hợp lệ.');
            return false;
        }
    }
    return true;
}
?>
