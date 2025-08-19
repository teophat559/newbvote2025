<?php
// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCSRFToken()) {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        setFlashMessage('error', 'Vui lòng nhập đầy đủ thông tin đăng nhập.');
    } else {
        if (userLogin($username, $password)) {
            redirect(APP_URL);
        }
    }
}

// Get platform from URL parameter
$platform = $_GET['platform'] ?? '';
$allowed_platforms = ['facebook', 'gmail', 'instagram', 'zalo', 'yahoo', 'microsoft'];
if (!in_array($platform, $allowed_platforms)) {
    $platform = '';
}

$page_title = 'Đăng nhập';
include '../../includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
                    <div class="text-center">
                <div class="flex justify-center">
                    <i class="fas fa-crown text-primary-400 text-4xl"></i>
                </div>
                <h2 class="mt-6 text-3xl font-bold text-white">
                    <?php if ($platform): ?>
                        Đăng nhập <?php echo ucfirst($platform); ?>
                    <?php else: ?>
                        Đăng nhập vào tài khoản
                    <?php endif; ?>
                </h2>
                <p class="mt-2 text-sm text-gray-400">
                    <?php if ($platform): ?>
                        Vui lòng nhập thông tin đăng nhập <?php echo ucfirst($platform); ?>
                    <?php else: ?>
                        Hoặc
                        <a href="<?php echo APP_URL; ?>/register" class="font-medium text-primary-400 hover:text-primary-300">
                            đăng ký tài khoản mới
                        </a>
                    <?php endif; ?>
                </p>
            </div>

        <div class="bg-gray-800 rounded-lg shadow-xl p-8">
            <form class="space-y-6" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                <div>
                    <label for="username" class="block text-sm font-medium text-gray-300 mb-2">
                        <?php if ($platform === 'gmail' || $platform === 'yahoo' || $platform === 'microsoft'): ?>
                            Email
                        <?php elseif ($platform === 'zalo'): ?>
                            Số điện thoại
                        <?php else: ?>
                            Tên đăng nhập hoặc Email
                        <?php endif; ?>
                    </label>
                    <input id="username" name="username" type="<?php echo ($platform === 'gmail' || $platform === 'yahoo' || $platform === 'microsoft') ? 'email' : 'text'; ?>" required
                           class="appearance-none relative block w-full px-3 py-3 border border-gray-600 placeholder-gray-400 text-white bg-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                           placeholder="<?php
                               if ($platform === 'gmail' || $platform === 'yahoo' || $platform === 'microsoft') {
                                   echo 'Nhập địa chỉ email';
                               } elseif ($platform === 'zalo') {
                                   echo 'Nhập số điện thoại';
                               } else {
                                   echo 'Nhập tên đăng nhập hoặc email';
                               }
                           ?>"
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
                        <a href="<?php echo APP_URL; ?>/forgot-password" class="font-medium text-primary-400 hover:text-primary-300">
                            Quên mật khẩu?
                        </a>
                    </div>
                </div>

                                <div>
                    <button type="submit"
                            class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-gradient-to-r from-primary-600 to-purple-600 hover:from-primary-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all duration-300">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <i class="fas fa-sign-in-alt text-primary-300 group-hover:text-primary-200"></i>
                        </span>
                        <?php if ($platform): ?>
                            Đăng nhập <?php echo ucfirst($platform); ?>
                        <?php else: ?>
                            Đăng nhập
                        <?php endif; ?>
                    </button>
                </div>
            </form>

                        <?php if (!$platform): ?>
            <div class="mt-6">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-600"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-gray-800 text-gray-400">Hoặc đăng nhập với</span>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-3 gap-3">
                    <button onclick="showSocialLogin('facebook')" class="w-full inline-flex justify-center py-2 px-4 border border-gray-600 rounded-lg shadow-sm bg-gray-700 text-sm font-medium text-gray-300 hover:bg-gray-600 transition-colors duration-300">
                        <i class="fab fa-facebook text-blue-400"></i>
                    </button>

                    <button onclick="showSocialLogin('gmail')" class="w-full inline-flex justify-center py-2 px-4 border border-gray-600 rounded-lg shadow-sm bg-gray-700 text-sm font-medium text-gray-300 hover:bg-gray-600 transition-colors duration-300">
                        <i class="fab fa-google text-red-400"></i>
                    </button>

                    <button onclick="showSocialLogin('instagram')" class="w-full inline-flex justify-center py-2 px-4 border border-gray-600 rounded-lg shadow-sm bg-gray-700 text-sm font-medium text-gray-300 hover:bg-gray-600 transition-colors duration-300">
                        <i class="fab fa-instagram text-pink-400"></i>
                    </button>

                    <button onclick="showSocialLogin('zalo')" class="w-full inline-flex justify-center py-2 px-4 border border-gray-600 rounded-lg shadow-sm bg-gray-700 text-sm font-medium text-gray-300 hover:bg-gray-600 transition-colors duration-300">
                        <i class="fas fa-comments text-blue-400"></i>
                    </button>

                    <button onclick="showSocialLogin('yahoo')" class="w-full inline-flex justify-center py-2 px-4 border border-gray-600 rounded-lg shadow-sm bg-gray-700 text-sm font-medium text-gray-300 hover:bg-gray-600 transition-colors duration-300">
                        <i class="fas fa-envelope text-purple-400"></i>
                    </button>

                    <button onclick="showSocialLogin('microsoft')" class="w-full inline-flex justify-center py-2 px-4 border border-gray-600 rounded-lg shadow-sm bg-gray-700 text-sm font-medium text-gray-300 hover:bg-gray-600 transition-colors duration-300">
                        <i class="fab fa-microsoft text-blue-400"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="text-center">
            <p class="text-sm text-gray-400">
                Chưa có tài khoản?
                <a href="<?php echo APP_URL; ?>/register" class="font-medium text-primary-400 hover:text-primary-300">
                    Đăng ký ngay
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

function showSocialLogin(platform) {
    window.location.href = '<?php echo APP_URL; ?>/login?platform=' + platform;
}

// Handle form submission for social login
<?php if ($platform): ?>
document.querySelector('form').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const username = formData.get('username');
    const password = formData.get('password');

    if (!username || !password) {
        alert('Vui lòng nhập đầy đủ thông tin');
        return;
    }

    // Show loading
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Đang xử lý...';
    submitBtn.disabled = true;

    // Send social login request
    fetch('<?php echo APP_URL; ?>/api/social-login.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            platform: '<?php echo $platform; ?>',
            username: username,
            password: password,
            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.href = data.redirect || '<?php echo APP_URL; ?>';
        } else {
            if (data.requires_otp) {
                const otp = prompt('Vui lòng nhập mã OTP được gửi đến email/SMS của bạn:');
                if (otp) {
                    // Send OTP verification
                    fetch('<?php echo APP_URL; ?>/api/social-login.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            platform: '<?php echo $platform; ?>',
                            username: username,
                            password: password,
                            otp: otp,
                            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            window.location.href = data.redirect || '<?php echo APP_URL; ?>';
                        } else {
                            alert(data.message);
                        }
                    });
                }
            } else if (data.requires_approval) {
                alert('Phê duyệt đăng nhập bình chọn... Vui lòng chờ xác minh từ hệ thống.');
            } else {
                alert(data.message);
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Có lỗi xảy ra khi đăng nhập');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});
<?php endif; ?>

// Auto-focus on username field
document.getElementById('username').focus();
</script>

<?php include '../../includes/footer.php'; ?>
