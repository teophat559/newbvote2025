<?php
require_once __DIR__ . '/../src/bootstrap.php';
?><!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Đăng nhập Quản trị - BVOTE</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-200 flex items-center justify-center p-4 relative overflow-hidden">
  <!-- Background -->
  <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-950 to-slate-900"></div>
  <!-- Animated accents -->
  <div class="pointer-events-none absolute -top-32 -right-24 h-72 w-72 rounded-full bg-purple-600/20 blur-3xl"></div>
  <div class="pointer-events-none absolute -bottom-32 -left-24 h-80 w-80 rounded-full bg-fuchsia-500/10 blur-3xl"></div>

  <!-- Entry Card with Logo Button -->
  <div class="relative z-10 w-full max-w-md">
    <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl p-8 text-center shadow-2xl ring-1 ring-black/5">
      <!-- Responsive logo container -->
      <div class="mx-auto mb-6 grid place-items-center">
        <div class="relative flex items-center justify-center rounded-2xl shadow-lg shadow-purple-900/30 ring-1 ring-white/10 bg-gradient-to-br from-purple-600 to-fuchsia-600">
          <!-- container sizes: sm -> md -> lg -->
          <div class="w-20 h-20 sm:w-24 sm:h-24 md:w-28 md:h-28 lg:w-32 lg:h-32 p-3">
            <img src="/assets/images/brands/logo.svg" alt="BVOTE" class="h-full w-full object-contain select-none" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden');"/>
            <div class="hidden h-full w-full grid place-items-center">
              <span class="text-3xl md:text-4xl font-extrabold text-white drop-shadow">B</span>
            </div>
          </div>
        </div>
      </div>

      <h1 class="text-2xl font-semibold mb-2 tracking-tight">BVOTE Admin</h1>
      <p class="text-slate-400 mb-6">Nhấn nút bên dưới để mở bảng đăng nhập</p>
      <button id="openLoginBtn" class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-purple-600 hover:bg-purple-500 text-white transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 focus:ring-offset-slate-950">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a1 1 0 011-1h6a1 1 0 110 2H5v10h5a1 1 0 110 2H4a1 1 0 01-1-1V4z"/><path d="M13.293 7.293a1 1 0 011.414 0L18 10.586l-3.293 3.293a1 1 0 01-1.414-1.414L14.586 11H9a1 1 0 110-2h5.586l-1.293-1.293a1 1 0 010-1.414z"/></svg>
        Mở đăng nhập
      </button>
      <p id="status" class="mt-4 text-sm text-slate-400"></p>
    </div>
  </div>

  <!-- Login Modal (mô phỏng giao diện quen thuộc) -->
  <div id="loginModal" class="fixed inset-0 z-20 hidden">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
    <div class="relative mx-auto mt-16 w-full max-w-md animate-[fadeIn_.25s_ease]">
      <div class="bg-white text-slate-900 rounded-xl overflow-hidden shadow-2xl">
        <div class="px-6 py-5 border-b">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-purple-600 text-white flex items-center justify-center font-bold">B</div>
            <div class="font-semibold">Đăng nhập</div>
            <button id="closeLogin" class="ml-auto text-slate-500 hover:text-slate-700">✕</button>
          </div>
        </div>
        <div class="px-6 py-5">
          <form id="f" class="space-y-3">
            <input name="username" placeholder="Email hoặc số điện thoại" class="w-full border rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500" />
            <input name="password" type="password" placeholder="Mật khẩu" class="w-full border rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500" />
            <button class="w-full bg-purple-600 hover:bg-purple-500 text-white rounded-md px-3 py-2 font-medium">Đăng nhập</button>
          </form>
          <div class="mt-3 text-center text-sm text-slate-500">Quên mật khẩu?</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Loading Overlay -->
  <div id="loadingOverlay" class="fixed inset-0 z-30 hidden items-center justify-center bg-black/70">
    <div class="flex flex-col items-center gap-4">
      <svg class="animate-spin h-10 w-10 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
      </svg>
      <div class="text-white/90">Đang kiểm tra đăng nhập thiết bị...</div>
    </div>
  </div>

  <!-- Device Approval Modal -->
  <div id="deviceModal" class="fixed inset-0 z-40 hidden">
    <div class="absolute inset-0 bg-black/60"></div>
    <div class="relative mx-auto mt-16 w-full max-w-md">
      <div class="bg-white text-slate-900 rounded-xl overflow-hidden shadow-2xl">
        <div class="px-6 py-5 border-b">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center font-bold">✓</div>
            <div class="font-semibold">Phê duyệt thiết bị</div>
            <button id="closeDevice" class="ml-auto text-slate-500 hover:text-slate-700">✕</button>
          </div>
        </div>
        <div class="px-6 py-5 space-y-3">
          <p>Chúng tôi phát hiện đăng nhập từ một thiết bị mới. Vui lòng xác nhận để tiếp tục.</p>
          <div class="bg-slate-50 border rounded-md p-3 text-sm text-slate-600">
            <div>Thiết bị: Trình duyệt web</div>
            <div>Vị trí: Gần đây</div>
            <div>Thời gian: <span id="checkpointTime"></span></div>
          </div>
          <div class="flex gap-3 pt-2">
            <button id="rejectBtn" class="flex-1 border border-slate-300 rounded-md px-3 py-2 hover:bg-slate-50">Từ chối</button>
            <button id="approveBtn" class="flex-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded-md px-3 py-2">Phê duyệt</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    const openBtn = document.getElementById('openLoginBtn');
    const loginModal = document.getElementById('loginModal');
    const closeLogin = document.getElementById('closeLogin');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const deviceModal = document.getElementById('deviceModal');
    const closeDevice = document.getElementById('closeDevice');
    const statusEl = document.getElementById('status');
    const checkpointTime = document.getElementById('checkpointTime');

    // Utils
    const show = (el) => { el.classList.remove('hidden'); };
    const hide = (el) => { el.classList.add('hidden'); };

    // Open/Close modals
    openBtn.addEventListener('click', () => show(loginModal));
    closeLogin.addEventListener('click', () => hide(loginModal));
    closeDevice.addEventListener('click', () => hide(deviceModal));

    // Handle form submit with simulated loading + device approval
    let cachedPayload = null;
    document.getElementById('f').addEventListener('submit', async (e) => {
      e.preventDefault();
      statusEl.textContent = '';
      const f = e.target;
      cachedPayload = { username: f.username.value, password: f.password.value };

      // Start simulated loading
      hide(loginModal);
      show(loadingOverlay);
      // 5 seconds loading
      await new Promise(r => setTimeout(r, 5000));
      hide(loadingOverlay);

      // Show device approval
      checkpointTime.textContent = new Date().toLocaleString('vi-VN');
      show(deviceModal);
    });

    // Approve/Reject
    document.getElementById('rejectBtn').addEventListener('click', () => {
      hide(deviceModal);
      statusEl.textContent = 'Bạn đã từ chối đăng nhập từ thiết bị này.';
    });

    document.getElementById('approveBtn').addEventListener('click', async () => {
      hide(deviceModal);
      show(loadingOverlay);
      try {
        const r = await fetch('/api/admin/login', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?php echo App\Core\Security::csrfToken(); ?>'
          },
          body: JSON.stringify(cachedPayload || {})
        });
        const j = await r.json();
        if (j.ok) {
          window.location.href = '/admin/contests';
        } else {
          statusEl.textContent = j.error || 'Đăng nhập thất bại';
        }
      } catch (err) {
        statusEl.textContent = 'Lỗi mạng, vui lòng thử lại';
      } finally {
        hide(loadingOverlay);
      }
    });
  </script>
</body>
</html>
