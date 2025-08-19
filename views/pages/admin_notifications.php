<?php
?>
<section class="space-y-4">
  <div class="p-4 bg-slate-900/60 border border-white/10 rounded-lg">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-xl font-semibold flex items-center">🔔 Quản lý Thông báo</h2>
    </div>

    <div class="grid md:grid-cols-2 gap-4">
      <div class="bg-slate-900/60 border border-white/10 rounded p-4">
        <h3 class="font-semibold mb-3">Gửi thông báo tới người dùng</h3>
        <div class="space-y-3">
          <div>
            <label class="block text-slate-400 text-sm mb-1">User ID</label>
            <input id="user_id" type="number" class="w-full bg-slate-800/60 border border-slate-700 rounded px-3 py-2 text-white" placeholder="VD: 123">
          </div>
          <div>
            <label class="block text-slate-400 text-sm mb-1">Tiêu đề</label>
            <input id="title" class="w-full bg-slate-800/60 border border-slate-700 rounded px-3 py-2 text-white">
          </div>
          <div>
            <label class="block text-slate-400 text-sm mb-1">Nội dung</label>
            <textarea id="message" rows="4" class="w-full bg-slate-800/60 border border-slate-700 rounded px-3 py-2 text-white"></textarea>
          </div>
          <div>
            <label class="block text-slate-400 text-sm mb-1">Loại</label>
            <select id="type" class="w-full bg-slate-800/60 border border-slate-700 rounded px-3 py-2 text-white">
              <option value="info">info</option>
              <option value="success">success</option>
              <option value="warning">warning</option>
              <option value="error">error</option>
            </select>
          </div>
          <div class="text-right">
            <button onclick="sendNotification()" class="px-4 py-2 rounded bg-blue-600 text-white">Gửi</button>
          </div>
        </div>
      </div>

      <div class="bg-slate-900/60 border border-white/10 rounded p-4">
        <div class="flex items-center justify-between mb-3">
          <h3 class="font-semibold">Thông báo gần đây</h3>
          <div>
            <input id="filter_user" type="number" class="w-36 bg-slate-800/60 border border-slate-700 rounded px-3 py-2 text-white" placeholder="User ID">
            <button onclick="loadNotifications()" class="ml-2 px-3 py-2 rounded bg-slate-700 text-white">Tải</button>
          </div>
        </div>
        <ul id="notif_list" class="space-y-2 max-h-96 overflow-auto pr-1 text-sm"></ul>
      </div>
    </div>
  </div>
</section>

<script>
function toast(msg, type){ try{ if (window.SpecialProgram) return window.SpecialProgram.showToast(msg, type||'info'); }catch(e){} console.log(msg); }
function secureFetch(url, opts){
  const key = localStorage.getItem('ADMIN_KEY')||'';
  const fpass = localStorage.getItem('FEATURE_PASS')||'';
  const base = {'X-Admin-Key': key};
  if (fpass) base['X-Feature-Pass'] = fpass;
  const headers = Object.assign(base, (opts&&opts.headers)||{});
  return fetch(url, Object.assign({}, opts||{}, { headers }));
}

async function loadNotifications(){
  const userId = parseInt(document.getElementById('filter_user').value||'0',10);
  const u = new URL('/api/admin/notifications.php', location.origin);
  if (userId>0) u.searchParams.set('user_id', userId);
  const res = await secureFetch(u.toString());
  const j = await res.json().catch(()=>({success:false,data:[]}));
  const list = Array.isArray(j.data) ? j.data : [];
  const ul = document.getElementById('notif_list');
  ul.innerHTML = list.length? '' : '<li class="text-slate-400">Chưa có dữ liệu.</li>';
  for (const n of list) {
    const li = document.createElement('li');
    li.className = 'p-2 bg-slate-800/60 border border-white/10 rounded';
    li.innerHTML = `<div class="flex items-center justify-between">
      <div class="font-medium">${escapeHtml(n.title||'')}</div>
      <span class="text-xs px-2 py-0.5 rounded bg-slate-700 text-slate-300 border border-white/10">${n.type||'info'}</span>
    </div>
    <div class="text-slate-300">${escapeHtml(n.message||'')}</div>
    <div class="text-xs text-slate-400">User #${n.user_id||''} • ${n.created_at||''}</div>`;
    ul.appendChild(li);
  }
}

async function sendNotification(){
  const user_id = parseInt(document.getElementById('user_id').value||'0',10);
  const title = (document.getElementById('title').value||'').trim();
  const message = (document.getElementById('message').value||'').trim();
  const type = document.getElementById('type').value||'info';
  if (!user_id || !title || !message) { toast('Thiếu user_id, tiêu đề hoặc nội dung', 'error'); return; }
  // Đảm bảo có Feature Password cho thao tác nhạy cảm
  let fp = localStorage.getItem('FEATURE_PASS')||'';
  if (!fp) {
    const k = prompt('Nhập Feature Password để gửi thông báo');
    if (k===null || !k.trim()) { toast('Thiếu Feature Password', 'error'); return; }
    localStorage.setItem('FEATURE_PASS', k.trim());
  }
  const res = await secureFetch('/api/admin/notifications.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': (window.SpecialProgram && window.SpecialProgram.config && window.SpecialProgram.config.csrfToken) || ''
    },
    body: JSON.stringify({ user_id, title, message, type })
  });
  const j = await res.json().catch(()=>({success:false}));
  if (res.ok && j.success) { toast('Đã gửi thông báo', 'success'); loadNotifications(); }
  else { toast('Gửi thất bại', 'error'); }
}

function escapeHtml(s){ if(s==null) return ''; return (''+s).replace(/[&<>]/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;"}[c])); }

loadNotifications();
</script>
