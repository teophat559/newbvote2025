<?php
?>
<section class="space-y-4">
  <div class="p-4 bg-slate-900/60 border border-white/10 rounded-lg">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-xl font-semibold flex items-center">📝 Quản lý Nội dung</h2>
      <button class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded" onclick="openContentDialog()">+ Thêm Nội dung</button>
    </div>
    <div class="flex justify-between items-center mb-4">
      <div class="relative w-full max-w-xs">
        <input id="search" type="text" placeholder="Tìm kiếm nội dung..." class="w-full bg-slate-800/60 border border-slate-700 pl-10 pr-3 py-2 rounded text-white" oninput="filterContents()" />
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">🔎</span>
      </div>
    </div>
    <div id="contents" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"></div>
  </div>
</section>

<div id="contentDialog" class="hidden fixed inset-0 bg-black/60 flex items-center justify-center p-4">
  <div class="bg-slate-900 border border-purple-800 rounded-lg p-4 w-full max-w-md">
    <h3 id="dialogTitle" class="text-lg font-semibold mb-3 text-white">Thêm nội dung mới</h3>
    <div class="space-y-3">
      <div>
        <label class="block text-slate-400 text-sm mb-1">Tiêu đề</label>
        <input id="title" class="w-full bg-slate-800/60 border border-slate-700 rounded px-3 py-2 text-white">
      </div>
      <div>
        <label class="block text-slate-400 text-sm mb-1">Slug</label>
        <input id="slug" class="w-full bg-slate-800/60 border border-slate-700 rounded px-3 py-2 text-white" placeholder="vd: gioi-thieu">
      </div>
      <div>
        <label class="block text-slate-400 text-sm mb-1">Nội dung</label>
        <textarea id="body" rows="6" class="w-full bg-slate-800/60 border border-slate-700 rounded px-3 py-2 text-white" placeholder="Markdown/HTML..."></textarea>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-slate-400 text-sm mb-1">Trạng thái</label>
          <select id="status" class="w-full bg-slate-800/60 border border-slate-700 rounded px-3 py-2 text-white">
            <option value="draft">Nháp</option>
            <option value="published">Xuất bản</option>
          </select>
        </div>
        <div>
          <label class="block text-slate-400 text-sm mb-1">Thứ tự</label>
          <input id="sort_order" type="number" value="0" class="w-full bg-slate-800/60 border border-slate-700 rounded px-3 py-2 text-white">
        </div>
      </div>
    </div>
    <div class="mt-4 flex justify-end gap-2">
      <button onclick="closeContentDialog()" class="px-4 py-2 rounded bg-slate-700 text-white">Đóng</button>
      <button onclick="saveContent()" class="px-4 py-2 rounded bg-blue-600 text-white">Lưu</button>
    </div>
  </div>
</div>

<script>
let editingId = null;

// Helper: secure fetch with admin key + feature password
function secureFetch(url, opts){
  const key = localStorage.getItem('ADMIN_KEY')||'';
  const fpass = localStorage.getItem('FEATURE_PASS')||'';
  const base = {'X-Admin-Key': key};
  if (fpass) base['X-Feature-Pass'] = fpass;
  const headers = Object.assign(base, (opts&&opts.headers)||{});
  return fetch(url, Object.assign({}, opts||{}, { headers }));
}
function toast(msg, type){ try{ if (window.SpecialProgram) return window.SpecialProgram.showToast(msg, type||'info'); }catch(e){} console.log(msg); }

function openContentDialog(item){
  document.getElementById('contentDialog').classList.remove('hidden');
  document.getElementById('dialogTitle').textContent = item ? 'Chỉnh sửa Nội dung' : 'Thêm nội dung mới';
  editingId = item ? item.id : null;
  document.getElementById('title').value = item ? (item.title||'') : '';
  document.getElementById('slug').value = item ? (item.slug||'') : '';
  document.getElementById('body').value = item ? (item.body||'') : '';
  document.getElementById('status').value = item ? (item.status||'draft') : 'draft';
  document.getElementById('sort_order').value = item ? (item.sort_order||0) : 0;
}
function closeContentDialog(){ document.getElementById('contentDialog').classList.add('hidden'); }

async function fetchContents(){
  const res = await secureFetch('/api/admin/contents');
  const data = await res.json().catch(()=>({data:[]}));
  const list = data.data || [];
  const wrap = document.getElementById('contents');
  wrap.innerHTML = '';
  for (const c of list) {
    const bodyPreview = (c.body||'').slice(0, 120).replace(/</g,'&lt;').replace(/>/g,'&gt;') + ((c.body||'').length>120?'…':'');
    wrap.innerHTML += `
      <div class="bg-slate-900/60 border border-white/10 rounded-lg p-4">
        <div class="mb-2 flex items-center justify-between">
          <p class="font-medium">${(c.title||'Không tiêu đề')}</p>
          <span class="text-xs px-2 py-0.5 rounded ${c.status==='published'?'bg-emerald-900/40 text-emerald-300 border border-emerald-400/30':'bg-yellow-900/40 text-yellow-300 border border-yellow-400/30'}">${c.status||'draft'}</span>
        </div>
        <p class="text-xs text-slate-400 mb-2">/${c.slug||''}</p>
        <p class="text-sm text-slate-300">${bodyPreview}</p>
        <div class="mt-2 text-right space-x-3">
          <button class="text-yellow-400" onclick='openContentDialog(${JSON.stringify(c)})'>Sửa</button>
          <button class="text-red-400" onclick='deleteContent(${c.id})'>Xoá</button>
        </div>
      </div>`
  }
}

async function saveContent(){
  const payload = {
    id: editingId,
    title: document.getElementById('title').value.trim(),
    slug: document.getElementById('slug').value.trim(),
    body: document.getElementById('body').value,
    status: document.getElementById('status').value,
    sort_order: parseInt(document.getElementById('sort_order').value||'0',10)
  };
  const url = '/api/admin/content' + (editingId ? '/' + editingId : '');
  const method = editingId ? 'PUT' : 'POST';
  const res = await secureFetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)});
  if (res.ok) { toast('Đã lưu nội dung', 'success'); closeContentDialog(); fetchContents(); }
  else { const j = await res.json().catch(()=>({})); toast('Lỗi: ' + (j.error||res.statusText), 'error'); }
}

async function deleteContent(id){
  if (!confirm('Xóa nội dung này?')) return;
  const res = await secureFetch('/api/admin/content/'+id, { method: 'DELETE' });
  if (res.ok) { toast('Đã xoá', 'success'); fetchContents(); }
  else { const j = await res.json().catch(()=>({})); toast('Lỗi: ' + (j.error||res.statusText), 'error'); }
}

function filterContents(){
  const q = (document.getElementById('search').value || '').toLowerCase();
  for (const card of document.querySelectorAll('#contents > div')) {
    const text = card.textContent.toLowerCase();
    card.style.display = text.includes(q) ? '' : 'none';
  }
}

fetchContents();
</script>
