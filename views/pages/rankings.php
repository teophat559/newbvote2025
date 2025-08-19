<?php
?>
<section class="space-y-4">
  <h2 class="text-xl font-semibold">Bảng xếp hạng</h2>
  <div id="rank" class="overflow-x-auto bg-slate-900/60 border border-white/10 rounded-lg">
    <table class="min-w-full text-left text-sm">
      <thead class="text-slate-400">
        <tr><th class="px-4 py-2">#</th><th class="px-4 py-2">Thí sinh</th><th class="px-4 py-2">Cuộc thi</th><th class="px-4 py-2">Bình chọn</th></tr>
      </thead>
      <tbody id="rankBody"><tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Đang tải...</td></tr></tbody>
    </table>
  </div>
</section>
<script>
async function load() {
  const res = await secureFetch('/api/public/rankings');
  let rows = [];
  try {
    if (!res.ok) {
      const t = await res.text();
      console.error('rankings api error', res.status, t);
      rows = [];
    } else {
      const json = await res.json();
      rows = (json && Array.isArray(json.data)) ? json.data : [];
    }
  } catch (e) {
    try { const t = await res.text(); console.error('rankings parse error', t); } catch(_){}
    rows = [];
  }
  const tbody = document.getElementById('rankBody');
  if (!rows.length) { tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Chưa có dữ liệu</td></tr>'; return; }
  tbody.innerHTML = rows.map((r,i)=>`<tr class="border-t border-white/10"><td class="px-4 py-2">${i+1}</td><td class="px-4 py-2">${r.contestant_name||r.name}</td><td class="px-4 py-2">${r.contest_name||''}</td><td class="px-4 py-2">${r.votes}</td></tr>`).join('');
}
load();
setInterval(load, 5000);

// Live update on votes across contests
(function attachWS(){
  try {
    const isSecure = location.protocol === 'https:';
    const scheme = isSecure ? 'wss' : 'ws';
    const host = window.__WS_HOST__ && window.__WS_HOST__ !== '' ? window.__WS_HOST__ : location.hostname;
    const port = window.__WS_PORT__ && window.__WS_PORT__ !== '' ? (':' + window.__WS_PORT__) : (location.port ? (':' + location.port) : '');
    const url = `${scheme}://${host}${port}/ws`;
    const ws = new WebSocket(url);
    ws.onmessage = (ev)=>{
      try{ const data = JSON.parse(ev.data); if (data && data.type === 'vote:created') { load(); } }catch(_){/* ignore */}
    };
  } catch(e) { /* ignore */ }
})();
</script>
