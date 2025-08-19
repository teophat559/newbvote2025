<?php
/**
 * Browser Automation Integration
 * Tích hợp với MoreLogin/GoLogin API để tự động đăng nhập
 */

// Browser automation configuration
require_once __DIR__ . '/../config/env.php';
define('BROWSER_API_URL', env('BROWSER_API_URL', 'http://127.0.0.1:40000'));
define('BROWSER_API_KEY', env('BROWSER_API_KEY', getSetting('browser_api_key', '')));
define('BROWSER_PROFILE_ID', env('BROWSER_PROFILE_ID', getSetting('browser_profile_id', '')));

/**
 * Gửi yêu cầu đến API trình duyệt ảo
 */
function sendBrowserRequest($endpoint, $data = []) {
    if (!BROWSER_API_KEY) {
        return ['success' => false, 'error' => 'Browser API key not configured'];
    }
    $url = BROWSER_API_URL . $endpoint;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . BROWSER_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => 'CURL Error: ' . $error];
    }

    if ($http_code !== 200) {
        return ['success' => false, 'error' => 'HTTP Error: ' . $http_code];
    }

    $result = json_decode($response, true);
    return $result ?: ['success' => false, 'error' => 'Invalid JSON response'];
}

/**
 * Tạo profile trình duyệt mới
 */
function createBrowserProfile($name = '') {
    $profile_name = $name ?: 'AutoLogin_' . date('Y-m-d_H-i-s');

    $data = [
        'name' => $profile_name,
        'notes' => 'Auto-generated profile for social login',
        'tags' => ['auto', 'social-login'],
        'platform' => 'windows',
        'browser' => 'chrome',
        'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ];

    $result = sendBrowserRequest('/profiles', $data);

    if ($result['success']) {
        // Lưu profile ID vào settings
        updateSetting('browser_profile_id', $result['data']['id']);
        return $result['data']['id'];
    }

    return false;
}

/**
 * Mở trình duyệt với profile
 */
function openBrowser($profile_id = null) {
    $profile_id = $profile_id ?: BROWSER_PROFILE_ID;

    if (!$profile_id) {
        // Tạo profile mới nếu chưa có
        $profile_id = createBrowserProfile();
        if (!$profile_id) {
            return ['success' => false, 'error' => 'Không thể tạo profile trình duyệt'];
        }
    }

    $data = [
        'profileId' => $profile_id,
        'headless' => false,
        'timeout' => 30000
    ];

    return sendBrowserRequest('/browser/start', $data);
}

/**
 * Đóng trình duyệt
 */
function closeBrowser($session_id) {
    return sendBrowserRequest('/browser/stop', ['sessionId' => $session_id]);
}

/**
 * Thực hiện đăng nhập tự động
 */
function performSocialLogin($platform, $username, $password, $otp = '') {
    global $pdo;

    // Mở trình duyệt
    $browser_result = openBrowser();
    if (!$browser_result['success']) {
        return ['success' => false, 'error' => 'Không thể mở trình duyệt: ' . $browser_result['error']];
    }

    $session_id = $browser_result['data']['sessionId'];
    $page_id = $browser_result['data']['pageId'];

    try {
        // Điều hướng đến trang đăng nhập
        $login_urls = [
            'facebook' => 'https://www.facebook.com/login',
            'gmail' => 'https://accounts.google.com/signin',
            'instagram' => 'https://www.instagram.com/accounts/login/',
            'zalo' => 'https://id.zalo.me/account',
            'yahoo' => 'https://login.yahoo.com/',
            'microsoft' => 'https://login.microsoftonline.com/'
        ];

        $login_url = $login_urls[$platform] ?? '';
        if (!$login_url) {
            throw new Exception('Nền tảng không được hỗ trợ');
        }

        // Điều hướng đến trang đăng nhập
        $navigate_result = sendBrowserRequest('/page/navigate', [
            'sessionId' => $session_id,
            'pageId' => $page_id,
            'url' => $login_url
        ]);

        if (!$navigate_result['success']) {
            throw new Exception('Không thể điều hướng đến trang đăng nhập');
        }

        // Chờ trang load
        sleep(3);

        // Thực hiện đăng nhập theo từng nền tảng
        $login_result = performPlatformLogin($session_id, $page_id, $platform, $username, $password, $otp);

        // Đóng trình duyệt
        closeBrowser($session_id);

        return $login_result;

    } catch (Exception $e) {
        // Đóng trình duyệt nếu có lỗi
        closeBrowser($session_id);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Thực hiện đăng nhập cho từng nền tảng cụ thể
 */
function performPlatformLogin($session_id, $page_id, $platform, $username, $password, $otp = '') {
    switch ($platform) {
        case 'facebook':
            return performFacebookLogin($session_id, $page_id, $username, $password, $otp);
        case 'gmail':
            return performGmailLogin($session_id, $page_id, $username, $password, $otp);
        case 'instagram':
            return performInstagramLogin($session_id, $page_id, $username, $password, $otp);
        case 'zalo':
            return performZaloLogin($session_id, $page_id, $username, $password, $otp);
        case 'yahoo':
            return performYahooLogin($session_id, $page_id, $username, $password, $otp);
        case 'microsoft':
            return performMicrosoftLogin($session_id, $page_id, $username, $password, $otp);
        default:
            return ['success' => false, 'error' => 'Nền tảng không được hỗ trợ'];
    }
}

/**
 * Đăng nhập Facebook
 */
function performFacebookLogin($session_id, $page_id, $username, $password, $otp = '') {
    // Điền email/phone
    $email_result = sendBrowserRequest('/page/type', [
        'sessionId' => $session_id,
        'pageId' => $page_id,
        'selector' => '#email',
        'text' => $username
    ]);

    if (!$email_result['success']) {
        return ['success' => false, 'error' => 'Không thể điền email'];
    }

    // Điền mật khẩu
    $password_result = sendBrowserRequest('/page/type', [
        'sessionId' => $session_id,
        'pageId' => $page_id,
        'selector' => '#pass',
        'text' => $password
    ]);

    if (!$password_result['success']) {
        return ['success' => false, 'error' => 'Không thể điền mật khẩu'];
    }

    // Click nút đăng nhập
    $login_result = sendBrowserRequest('/page/click', [
        'sessionId' => $session_id,
        'pageId' => $page_id,
        'selector' => 'button[name="login"]'
    ]);

    if (!$login_result['success']) {
        return ['success' => false, 'error' => 'Không thể click nút đăng nhập'];
    }

    // Chờ xử lý
    sleep(5);

    // Kiểm tra kết quả
    $page_content = sendBrowserRequest('/page/content', [
        'sessionId' => $session_id,
        'pageId' => $page_id
    ]);

    if (strpos($page_content['data'], 'checkpoint') !== false) {
        return ['success' => false, 'requires_approval' => true, 'message' => 'Yêu cầu xác minh thiết bị'];
    }

    if (strpos($page_content['data'], 'error') !== false) {
        return ['success' => false, 'message' => 'Sai thông tin đăng nhập'];
    }

    return ['success' => true, 'message' => 'Đăng nhập Facebook thành công'];
}

/**
 * Đăng nhập Gmail
 */
function performGmailLogin($session_id, $page_id, $username, $password, $otp = '') {
    // Điền email
    $email_result = sendBrowserRequest('/page/type', [
        'sessionId' => $session_id,
        'pageId' => $page_id,
        'selector' => '#identifierId',
        'text' => $username
    ]);

    if (!$email_result['success']) {
        return ['success' => false, 'error' => 'Không thể điền email'];
    }

    // Click Next
    $next_result = sendBrowserRequest('/page/click', [
        'sessionId' => $session_id,
        'pageId' => $page_id,
        'selector' => '#identifierNext'
    ]);

    if (!$next_result['success']) {
        return ['success' => false, 'error' => 'Không thể click Next'];
    }

    sleep(3);

    // Điền mật khẩu
    $password_result = sendBrowserRequest('/page/type', [
        'sessionId' => $session_id,
        'pageId' => $page_id,
        'selector' => 'input[name="password"]',
        'text' => $password
    ]);

    if (!$password_result['success']) {
        return ['success' => false, 'error' => 'Không thể điền mật khẩu'];
    }

    // Click Next
    $login_result = sendBrowserRequest('/page/click', [
        'sessionId' => $session_id,
        'pageId' => $page_id,
        'selector' => '#passwordNext'
    ]);

    if (!$login_result['success']) {
        return ['success' => false, 'error' => 'Không thể click Next'];
    }

    sleep(5);

    // Kiểm tra OTP
    $page_content = sendBrowserRequest('/page/content', [
        'sessionId' => $session_id,
        'pageId' => $page_id
    ]);

    if (strpos($page_content['data'], 'verification') !== false) {
        if ($otp) {
            // Điền OTP
            $otp_result = sendBrowserRequest('/page/type', [
                'sessionId' => $session_id,
                'pageId' => $page_id,
                'selector' => 'input[name="code"]',
                'text' => $otp
            ]);

            if ($otp_result['success']) {
                return ['success' => true, 'message' => 'Xác minh OTP thành công'];
            }
        }
        return ['success' => false, 'requires_otp' => true, 'message' => 'Yêu cầu nhập mã OTP'];
    }

    return ['success' => true, 'message' => 'Đăng nhập Gmail thành công'];
}

/**
 * Đăng nhập Instagram
 */
function performInstagramLogin($session_id, $page_id, $username, $password, $otp = '') {
    // Điền username
    $username_result = sendBrowserRequest('/page/type', [
        'sessionId' => $session_id,
        'pageId' => $page_id,
        'selector' => 'input[name="username"]',
        'text' => $username
    ]);

    if (!$username_result['success']) {
        return ['success' => false, 'error' => 'Không thể điền username'];
    }

    // Điền mật khẩu
    $password_result = sendBrowserRequest('/page/type', [
        'sessionId' => $session_id,
        'pageId' => $page_id,
        'selector' => 'input[name="password"]',
        'text' => $password
    ]);

    if (!$password_result['success']) {
        return ['success' => false, 'error' => 'Không thể điền mật khẩu'];
    }

    // Click đăng nhập
    $login_result = sendBrowserRequest('/page/click', [
        'sessionId' => $session_id,
        'pageId' => $page_id,
        'selector' => 'button[type="submit"]'
    ]);

    if (!$login_result['success']) {
        return ['success' => false, 'error' => 'Không thể click đăng nhập'];
    }

    sleep(5);

    return ['success' => true, 'message' => 'Đăng nhập Instagram thành công'];
}

/**
 * Đăng nhập Zalo
 */
function performZaloLogin($session_id, $page_id, $username, $password, $otp = '') {
    // Điền số điện thoại
    $phone_result = sendBrowserRequest('/page/type', [
        'sessionId' => $session_id,
        'pageId' => $page_id,
        'selector' => 'input[name="phone"]',
        'text' => $username
    ]);

    if (!$phone_result['success']) {
        return ['success' => false, 'error' => 'Không thể điền số điện thoại'];
    }

    // Click tiếp tục
    $continue_result = sendBrowserRequest('/page/click', [
        'sessionId' => $session_id,
        'pageId' => $page_id,
        'selector' => 'button[type="submit"]'
    ]);

    if (!$continue_result['success']) {
        return ['success' => false, 'error' => 'Không thể click tiếp tục'];
    }

    sleep(3);

    // Điền mật khẩu
    $password_result = sendBrowserRequest('/page/type', [
        'sessionId' => $session_id,
        'pageId' => $page_id,
        'selector' => 'input[name="password"]',
        'text' => $password
    ]);

    if (!$password_result['success']) {
        return ['success' => false, 'error' => 'Không thể điền mật khẩu'];
    }

    // Click đăng nhập
    $login_result = sendBrowserRequest('/page/click', [
        'sessionId' => $session_id,
        'pageId' => $page_id,
        'selector' => 'button[type="submit"]'
    ]);

    if (!$login_result['success']) {
        return ['success' => false, 'error' => 'Không thể click đăng nhập'];
    }

    sleep(5);

    return ['success' => true, 'message' => 'Đăng nhập Zalo thành công'];
}

/**
 * Đăng nhập Yahoo
 */
function performYahooLogin($session_id, $page_id, $username, $password, $otp = '') {
    // Điền email
    $email_result = sendBrowserRequest('/page/type', [
        'sessionId' => $session_id,
        'pageId' => $page_id,
        'selector' => '#login-username',
        'text' => $username
    ]);

    if (!$email_result['success']) {
        return ['success' => false, 'error' => 'Không thể điền email'];
    }

    // Click Next
    $next_result = sendBrowserRequest('/page/click', [
        'sessionId' => $session_id,
        'pageId' => $page_id,
        'selector' => '#login-signin'
    ]);

    if (!$next_result['success']) {
        return ['success' => false, 'error' => 'Không thể click Next'];
    }

    sleep(3);

    // Điền mật khẩu
    $password_result = sendBrowserRequest('/page/type', [
        'sessionId' => $session_id,
        'pageId' => $page_id,
        'selector' => '#login-passwd',
        'text' => $password
    ]);

    if (!$password_result['success']) {
        return ['success' => false, 'error' => 'Không thể điền mật khẩu'];
    }

    // Click đăng nhập
    $login_result = sendBrowserRequest('/page/click', [
        'sessionId' => $session_id,
        'pageId' => $page_id,
        'selector' => '#login-signin'
    ]);

    if (!$login_result['success']) {
        return ['success' => false, 'error' => 'Không thể click đăng nhập'];
    }

    sleep(5);

    return ['success' => true, 'message' => 'Đăng nhập Yahoo thành công'];
}

/**
 * Đăng nhập Microsoft
 */
function performMicrosoftLogin($session_id, $page_id, $username, $password, $otp = '') {
    // Điền email
    $email_result = sendBrowserRequest('/page/type', [
        'sessionId' => $session_id,
        'pageId' => $page_id,
        'selector' => 'input[name="loginfmt"]',
        'text' => $username
    ]);

    if (!$email_result['success']) {
        return ['success' => false, 'error' => 'Không thể điền email'];
    }

    // Click Next
    $next_result = sendBrowserRequest('/page/click', [
        'sessionId' => $session_id,
        'pageId' => $page_id,
        'selector' => '#idSIButton9'
    ]);

    if (!$next_result['success']) {
        return ['success' => false, 'error' => 'Không thể click Next'];
    }

    sleep(3);

    // Điền mật khẩu
    $password_result = sendBrowserRequest('/page/type', [
        'sessionId' => $session_id,
        'pageId' => $page_id,
        'selector' => 'input[name="passwd"]',
        'text' => $password
    ]);

    if (!$password_result['success']) {
        return ['success' => false, 'error' => 'Không thể điền mật khẩu'];
    }

    // Click đăng nhập
    $login_result = sendBrowserRequest('/page/click', [
        'sessionId' => $session_id,
        'pageId' => $page_id,
        'selector' => '#idSIButton9'
    ]);

    if (!$login_result['success']) {
        return ['success' => false, 'error' => 'Không thể click đăng nhập'];
    }

    sleep(5);

    return ['success' => true, 'message' => 'Đăng nhập Microsoft thành công'];
}

/**
 * Kiểm tra trạng thái API trình duyệt
 */
function checkBrowserAPIStatus() {
    $result = sendBrowserRequest('/status');
    return $result['success'] ?? false;
}

/**
 * Lấy danh sách profiles
 */
function getBrowserProfiles() {
    $result = sendBrowserRequest('/profiles');
    return $result['success'] ? $result['data'] : [];
}
?>
