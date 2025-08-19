<?php
?>
<section class="space-y-4">
  <div class="p-4 bg-slate-900/60 border border-white/10 rounded-lg">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-xl font-semibold flex items-center">🏆 Danh sách Cuộc Thi</h2>
      <button class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded" onclick="openContestDialog()">+ Thêm Cuộc Thi</button>
    </div>
    <div class="flex justify-between items-center mb-4">
      <div class="relative w-full max-w-xs">
        <input id="search" type="text" placeholder="Tìm kiếm cuộc thi..." class="w-full bg-slate-800/60 border border-slate-700 pl-10 pr-3 py-2 rounded text-white" oninput="filterContests()" />
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">🔎</span>
      </div>
    </div>
    <div id="contests" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"></div>
  </div>
</section>

<div id="contestDialog" class="hidden fixed inset-0 bg-black/60 flex items-center justify-center p-4">
  <div class="bg-slate-900 border border-purple-800 rounded-lg p-4 w-full max-w-md">
    <h3 id="dialogTitle" class="text-lg font-semibold mb-3 text-white">Thêm Cuộc thi mới</h3>
    <div class="space-y-3">
      <div>
        <label class="block text-slate-400 text-sm mb-1">Tên Cuộc Thi</label>
        <input id="name" class="w-full bg-slate-800/60 border border-slate-700 rounded px-3 py-2 text-white">
      </div>
      <div>
        <label class="block text-slate-400 text-sm mb-1">Mô tả</label>
        <textarea id="description" class="w-full bg-slate-800/60 border border-slate-700 rounded px-3 py-2 text-white"></textarea>
      </div>
      <div>
        <label class="block text-slate-400 text-sm mb-1">URL Hình ảnh</label>
        <input id="imageUrl" class="w-full bg-slate-800/60 border border-slate-700 rounded px-3 py-2 text-white">
      </div>
    </div>
    <div class="mt-4 flex justify-end gap-2">
      <button onclick="closeContestDialog()" class="px-4 py-2 rounded bg-slate-700 text-white">Đóng</button>
      <button onclick="saveContest()" class="px-4 py-2 rounded bg-blue-600 text-white">Lưu</button>
    </div>
  </div>
</div>

<script>
let editingId = null;
function openContestDialog(contest) {
  document.getElementById('contestDialog').classList.remove('hidden');
  document.getElementById('dialogTitle').textContent = contest ? 'Chỉnh sửa Cuộc thi' : 'Thêm Cuộc thi mới';
  editingId = contest ? contest.id : null;
  document.getElementById('name').value = contest ? contest.name : '';
  document.getElementById('description').value = contest ? contest.description || '' : '';
  document.getElementById('imageUrl').value = contest ? (contest.image_url || '') : '';
}
function closeContestDialog() { document.getElementById('contestDialog').classList.add('hidden'); }
async function fetchContests() {
  const res = await secureFetch('/api/admin/contests');
  const data = await res.json();
  const list = data.data || [];
  const wrap = document.getElementById('contests');
  wrap.innerHTML = '';
  for (const c of list) {
    wrap.innerHTML += `
      <div class="bg-slate-900/60 border border-white/10 rounded-lg p-4">
        <div class="h-36 rounded mb-3" style="background:url('${c.image_url || 'https://via.placeholder.com/300x120'}') center/cover"></div>
        <p class="font-medium">${c.name}</p>
        <p class="text-sm text-slate-400">${c.description || ''}</p>
        <div class="mt-2 text-right space-x-2">
          <button class="text-yellow-400" onclick='openContestDialog(${JSON.stringify(c)})'>Sửa</button>
          <button class="text-red-400" onclick='deleteContest(${c.id})'>Xoá</button>
        </div>
      </div>`
  }
}
async function saveContest() {
  const payload = { id: editingId, name: document.getElementById('name').value.trim(), description: document.getElementById('description').value.trim(), image_url: document.getElementById('imageUrl').value.trim() };
  const url = '/api/admin/contest' + (editingId ? '/'+editingId : '');
  const method = editingId ? 'PUT' : 'POST';
  const res = await secureFetch(url, { method, headers: { 'Content-Type':'application/json' }, body: JSON.stringify(payload)});
  if (res.ok) { closeContestDialog(); fetchContests(); }
}
async function deleteContest(id) { const res = await secureFetch('/api/admin/contest/'+id, { method: 'DELETE' }); if (res.ok) fetchContests(); }
function filterContests() {
  const q = (document.getElementById('search').value || '').toLowerCase();
  for (const card of document.querySelectorAll('#contests > div')) { const text = card.textContent.toLowerCase(); card.style.display = text.includes(q) ? '' : 'none'; }
}
fetchContests();
</script>
