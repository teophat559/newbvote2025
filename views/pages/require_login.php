<?php
?>
<section class="max-w-2xl mx-auto text-center py-16">
  <div class="bg-slate-900/60 border border-white/10 rounded-xl p-8">
    <h1 class="text-2xl font-semibold mb-3">Yêu cầu đăng nhập</h1>
    <p class="text-slate-300 mb-6">Vui lòng đăng nhập để xem nội dung chi tiết và sử dụng tính năng này.</p>
    <button onclick="if(window.openLoginModal){ openLoginModal(); }" class="px-4 py-2 rounded bg-blue-600 hover:bg-blue-500 text-white">Đăng nhập</button>
  </div>
</section>
<script>
  // Tự động mở modal nếu có tham số redirect
  (function(){ try {
    const params = new URLSearchParams(window.location.search);
    if (params.get('auto') === '1' && window.openLoginModal) { openLoginModal(); }
  } catch(e){} })();
</script>
