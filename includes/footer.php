    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 border-t border-gray-700 mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="col-span-1 md:col-span-2">
                    <div class="flex items-center space-x-2 mb-4">
                        <i class="fas fa-crown text-primary-400 text-2xl"></i>
                        <span class="text-xl font-bold text-white"><?php echo getSetting('site_name', APP_NAME); ?></span>
                    </div>
                    <p class="text-gray-400 mb-4">
                        <?php echo getSetting('site_description', 'Hệ thống bình chọn trực tuyến hiện đại và an toàn.'); ?>
                    </p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-white">
                            <i class="fab fa-facebook text-xl"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white">
                            <i class="fab fa-twitter text-xl"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white">
                            <i class="fab fa-instagram text-xl"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white">
                            <i class="fab fa-youtube text-xl"></i>
                        </a>
                    </div>
                </div>

                <div>
                    <h3 class="text-white font-semibold mb-4">Liên kết nhanh</h3>
                    <ul class="space-y-2">
                        <li><a href="<?php echo APP_URL; ?>/contests" class="text-gray-400 hover:text-white">Cuộc thi</a></li>
                        <li><a href="<?php echo APP_URL; ?>/rankings" class="text-gray-400 hover:text-white">Bảng xếp hạng</a></li>
                        <li><a href="<?php echo APP_URL; ?>/about" class="text-gray-400 hover:text-white">Giới thiệu</a></li>
                        <li><a href="<?php echo APP_URL; ?>/contact" class="text-gray-400 hover:text-white">Liên hệ</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-white font-semibold mb-4">Hỗ trợ</h3>
                    <ul class="space-y-2">
                        <li><a href="<?php echo APP_URL; ?>/help" class="text-gray-400 hover:text-white">Hướng dẫn</a></li>
                        <li><a href="<?php echo APP_URL; ?>/faq" class="text-gray-400 hover:text-white">Câu hỏi thường gặp</a></li>
                        <li><a href="<?php echo APP_URL; ?>/terms" class="text-gray-400 hover:text-white">Điều khoản</a></li>
                        <li><a href="<?php echo APP_URL; ?>/privacy" class="text-gray-400 hover:text-white">Chính sách</a></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-gray-700 mt-8 pt-8">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <p class="text-gray-400 text-sm">
                        &copy; <?php echo date('Y'); ?> <?php echo getSetting('site_name', APP_NAME); ?>. Tất cả quyền được bảo lưu.
                    </p>
                    <p class="text-gray-400 text-sm mt-2 md:mt-0">
                        Phiên bản <?php echo APP_VERSION; ?>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Back to top button -->
    <button id="backToTop" class="fixed bottom-8 right-8 bg-primary-600 hover:bg-primary-700 text-white p-3 rounded-full shadow-lg transition-all duration-300 opacity-0 invisible">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script>
        // Back to top functionality
        const backToTopButton = document.getElementById('backToTop');

        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                backToTopButton.classList.remove('opacity-0', 'invisible');
                backToTopButton.classList.add('opacity-100', 'visible');
            } else {
                backToTopButton.classList.add('opacity-0', 'invisible');
                backToTopButton.classList.remove('opacity-100', 'visible');
            }
        });

        backToTopButton.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Auto-hide flash messages
        const flashMessages = document.querySelectorAll('.bg-green-600, .bg-red-600, .bg-yellow-600, .bg-blue-600');
        flashMessages.forEach(message => {
            setTimeout(() => {
                message.style.transition = 'opacity 0.5s ease';
                message.style.opacity = '0';
                setTimeout(() => {
                    message.remove();
                }, 500);
            }, 5000);
        });

        // Confirm delete actions
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('delete-confirm')) {
                if (!confirm('Bạn có chắc chắn muốn xóa mục này?')) {
                    e.preventDefault();
                }
            }
        });
    </script>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Custom JavaScript -->
    <script src="<?php echo APP_URL; ?>/assets/js/app.js"></script>
</body>
</html>
