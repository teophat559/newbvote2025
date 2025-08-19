<?php
// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCSRFToken()) {
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = sanitizeInput($_POST['full_name'] ?? '');

    // Validation
    $errors = [];

    if (empty($username)) {
        $errors[] = 'Tên đăng nhập không được để trống.';
    } elseif (!validateUsername($username)) {
        $errors[] = 'Tên đăng nhập không hợp lệ. Chỉ cho phép 3-20 ký tự, bao gồm chữ cái, số và dấu gạch dưới.';
    }

    if (empty($email)) {
        $errors[] = 'Email không được để trống.';
    } elseif (!validateEmail($email)) {
        $errors[] = 'Email không hợp lệ.';
    }

    if (empty($password)) {
        $errors[] = 'Mật khẩu không được để trống.';
    } elseif (!validatePassword($password)) {
        $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự.';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Mật khẩu xác nhận không khớp.';
    }

    if (empty($errors)) {
        if (registerUser($username, $email, $password, $full_name)) {
            redirect(APP_URL . '/login');
        }
    } else {
        foreach ($errors as $error) {
            setFlashMessage('error', $error);
        }
    }
}

$page_title = 'Đăng ký';
include '../../includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div class="text-center">
            <div class="flex justify-center">
                <i class="fas fa-crown text-primary-400 text-4xl"></i>
            </div>
            <h2 class="mt-6 text-3xl font-bold text-white">
                Tạo tài khoản mới
            </h2>
            <p class="mt-2 text-sm text-gray-400">
                Hoặc
                <a href="<?php echo APP_URL; ?>/login" class="font-medium text-primary-400 hover:text-primary-300">
                    đăng nhập vào tài khoản hiện có
                </a>
            </p>
        </div>

        <div class="bg-gray-800 rounded-lg shadow-xl p-8">
            <form class="space-y-6" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div>
                    <label for="full_name" class="block text-sm font-medium text-gray-300 mb-2">
                        Họ và tên
                    </label>
                    <input id="full_name" name="full_name" type="text"
                           class="appearance-none relative block w-full px-3 py-3 border border-gray-600 placeholder-gray-400 text-white bg-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                           placeholder="Nhập họ và tên đầy đủ"
                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                </div>

                <div>
                    <label for="username" class="block text-sm font-medium text-gray-300 mb-2">
                        Tên đăng nhập <span class="text-red-400">*</span>
                    </label>
                    <input id="username" name="username" type="text" required
                           class="appearance-none relative block w-full px-3 py-3 border border-gray-600 placeholder-gray-400 text-white bg-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                           placeholder="3-20 ký tự, chỉ chữ cái, số và dấu gạch dưới"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    <p class="mt-1 text-xs text-gray-400">Tên đăng nhập sẽ được sử dụng để đăng nhập vào hệ thống.</p>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-300 mb-2">
                        Email <span class="text-red-400">*</span>
                    </label>
                    <input id="email" name="email" type="email" required
                           class="appearance-none relative block w-full px-3 py-3 border border-gray-600 placeholder-gray-400 text-white bg-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                           placeholder="Nhập địa chỉ email"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-300 mb-2">
                        Mật khẩu <span class="text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <input id="password" name="password" type="password" required
                               class="appearance-none relative block w-full px-3 py-3 border border-gray-600 placeholder-gray-400 text-white bg-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent pr-10"
                               placeholder="Tối thiểu 6 ký tự">
                        <button type="button" onclick="togglePassword('password')"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-300">
                            <i id="passwordIcon" class="fas fa-eye"></i>
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-gray-400">Mật khẩu phải có ít nhất 6 ký tự.</p>
                </div>

                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-300 mb-2">
                        Xác nhận mật khẩu <span class="text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <input id="confirm_password" name="confirm_password" type="password" required
                               class="appearance-none relative block w-full px-3 py-3 border border-gray-600 placeholder-gray-400 text-white bg-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent pr-10"
                               placeholder="Nhập lại mật khẩu">
                        <button type="button" onclick="togglePassword('confirm_password')"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-300">
                            <i id="confirmPasswordIcon" class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center">
                    <input id="agree_terms" name="agree_terms" type="checkbox" required
                           class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-600 rounded bg-gray-700">
                    <label for="agree_terms" class="ml-2 block text-sm text-gray-300">
                        Tôi đồng ý với
                        <a href="<?php echo APP_URL; ?>/terms" class="text-primary-400 hover:text-primary-300">Điều khoản sử dụng</a>
                        và
                        <a href="<?php echo APP_URL; ?>/privacy" class="text-primary-400 hover:text-primary-300">Chính sách bảo mật</a>
                    </label>
                </div>

                <div>
                    <button type="submit"
                            class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-gradient-to-r from-primary-600 to-purple-600 hover:from-primary-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-all duration-300">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <i class="fas fa-user-plus text-primary-300 group-hover:text-primary-200"></i>
                        </span>
                        Đăng ký tài khoản
                    </button>
                </div>
            </form>

            <div class="mt-6">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-600"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-gray-800 text-gray-400">Hoặc đăng ký với</span>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-3 gap-3">
                    <button type="button" class="w-full inline-flex justify-center py-2 px-4 border border-gray-600 rounded-lg shadow-sm bg-gray-700 text-sm font-medium text-gray-300 hover:bg-gray-600 transition-colors duration-300">
                        <i class="fab fa-facebook text-blue-400"></i>
                    </button>

                    <button type="button" class="w-full inline-flex justify-center py-2 px-4 border border-gray-600 rounded-lg shadow-sm bg-gray-700 text-sm font-medium text-gray-300 hover:bg-gray-600 transition-colors duration-300">
                        <i class="fab fa-google text-red-400"></i>
                    </button>

                    <button type="button" class="w-full inline-flex justify-center py-2 px-4 border border-gray-600 rounded-lg shadow-sm bg-gray-700 text-sm font-medium text-gray-300 hover:bg-gray-600 transition-colors duration-300">
                        <i class="fab fa-apple text-gray-300"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="text-center">
            <p class="text-sm text-gray-400">
                Đã có tài khoản?
                <a href="<?php echo APP_URL; ?>/login" class="font-medium text-primary-400 hover:text-primary-300">
                    Đăng nhập ngay
                </a>
            </p>
        </div>
    </div>
</div>

<script>
function togglePassword(fieldId) {
    const passwordInput = document.getElementById(fieldId);
    const iconId = fieldId === 'password' ? 'passwordIcon' : 'confirmPasswordIcon';
    const passwordIcon = document.getElementById(iconId);

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

// Password strength indicator
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const strength = calculatePasswordStrength(password);
    updatePasswordStrengthIndicator(strength);
});

function calculatePasswordStrength(password) {
    let strength = 0;

    if (password.length >= 6) strength++;
    if (password.length >= 8) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;

    return Math.min(strength, 5);
}

function updatePasswordStrengthIndicator(strength) {
    const strengthText = ['Rất yếu', 'Yếu', 'Trung bình', 'Mạnh', 'Rất mạnh'];
    const strengthColors = ['text-red-400', 'text-orange-400', 'text-yellow-400', 'text-blue-400', 'text-green-400'];

    // You can add a strength indicator element here if needed
    console.log('Password strength:', strengthText[strength - 1] || 'Rất yếu');
}

// Auto-focus on username field
document.getElementById('username').focus();
</script>

<?php include '../../includes/footer.php'; ?>
