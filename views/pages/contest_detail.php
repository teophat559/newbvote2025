<?php /** @var int $contestId */ ?>
<section class="space-y-4">
  <div id="hero" class="h-48 md:h-64 rounded-xl overflow-hidden relative bg-slate-800/60 border border-white/10">
    <img id="heroImg" alt="Hero" class="w-full h-full object-cover opacity-70" />
    <div class="absolute inset-0 bg-black/40 flex items-center justify-center">
      <div class="text-center p-4">
        <h1 id="contestName" class="text-2xl md:text-3xl font-bold text-white">Cuộc thi</h1>
        <p id="contestDesc" class="text-white/80 mt-2 max-w-2xl mx-auto"></p>
      </div>
    </div>
  </div>

  <div class="flex items-center justify-between">
    <h2 class="text-xl font-semibold">Danh sách thí sinh</h2>
    <div id="voteStatus" class="text-sm text-slate-400"></div>
  </div>

  <div id="grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"></div>
</section>

<script>
const contestId = <?php echo (int)$contestId; ?>;
const CSRF = window.__CSRF__;
function toast(msg, type='info'){ const el=document.createElement('div'); el.textContent=msg; el.className=`fixed bottom-4 right-4 z-50 px-3 py-2 rounded ${type==='success'?'bg-green-600':'bg-red-600'} text-white`; document.body.appendChild(el); setTimeout(()=>el.remove(), 2000); }

(async function init(){
  // Contest info (from public list)
  const infoRes = await secureFetch('/api/public/contests');
  if (!infoRes.ok) { toast('Lỗi tải thông tin cuộc thi', 'error'); return; }
  let info = null; try { info = await infoRes.json(); } catch(_) { toast('Dữ liệu không hợp lệ (contests)', 'error'); return; }
  const all = (info && info.data) || [];
  const c = all.find(x => Number(x.id) === Number(contestId));
  if (!c) { document.getElementById('contestName').textContent = 'Không tìm thấy'; return; }
  document.getElementById('contestName').textContent = c.name;
  document.getElementById('contestDesc').textContent = c.description || '';
  document.getElementById('heroImg').src = c.image_url || 'https://images.unsplash.com/photo-1675749191790-6b6b3b13046b';

  // Contestants
  const res = await secureFetch(`/api/public/contestants?contest_id=${contestId}`);
  if (!res.ok) { toast('Lỗi tải danh sách thí sinh', 'error'); return; }
  let json = null; try { json = await res.json(); } catch(_) { toast('Dữ liệu không hợp lệ (contestants)', 'error'); return; }
  const list = json.data || [];
  const grid = document.getElementById('grid');
  if (!list.length) { grid.innerHTML = '<div class="text-slate-400">Chưa có thí sinh</div>'; return; }
  grid.innerHTML = list.map(k => `
    <div class="bg-slate-900/60 border border-white/10 rounded-lg p-4">
      <div class="h-40 rounded mb-3 bg-slate-700/40" style="background:url('${k.photo_url||'https://via.placeholder.com/300x160'}') center/cover"></div>
      <div class="flex items-center justify-between">
        <div>
          <p class="font-medium">${k.name}</p>
          <p class="text-sm text-slate-400">${k.votes} bình chọn</p>
        </div>
        <button class="bg-blue-600 hover:bg-blue-500 text-white px-3 py-1.5 rounded" onclick="vote(${k.id})">Bình chọn</button>
      </div>
    </div>`).join('');
})();

async function vote(contestantId){
  try {
    const res = await secureFetch('/api/vote', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ contestant_id: contestantId }) });
    if (!res.ok) { toast('Lỗi bình chọn', 'error'); return; }
    let json = null; try { json = await res.json(); } catch(_) { toast('Phản hồi không hợp lệ', 'error'); return; }
    if (json.ok) { toast('Đã bình chọn!', 'success'); refresh(); }
    else { toast(json.error || 'Lỗi bình chọn', 'error'); }
  } catch(e) { toast('Lỗi kết nối', 'error'); }
}

async function refresh(){
  const res = await secureFetch(`/api/public/contestants?contest_id=${contestId}`);
  if (!res.ok) return;
  let json = null; try { json = await res.json(); } catch(_) { return; }
  const list = json.data || [];
  const grid = document.getElementById('grid');
  grid.querySelectorAll('.text-sm.text-slate-400').forEach((el,i)=>{ if(list[i]) el.textContent = `${list[i].votes} bình chọn`; });
}

// WebSocket live updates for votes in this contest
(function attachWS(){
  try {
    const isSecure = location.protocol === 'https:';
    const scheme = isSecure ? 'wss' : 'ws';
    const host = window.__WS_HOST__ && window.__WS_HOST__ !== '' ? window.__WS_HOST__ : location.hostname;
    const port = window.__WS_PORT__ && window.__WS_PORT__ !== '' ? (':' + window.__WS_PORT__) : (location.port ? (':' + location.port) : '');
    const url = `${scheme}://${host}${port}/ws`;
    const ws = new WebSocket(url);
    ws.onmessage = (ev)=>{
      try{
        const data = JSON.parse(ev.data);
        if (data && data.type === 'vote:created' && Number(data.contest_id) === Number(contestId)) {
          refresh();
        }
      }catch(_){/* ignore */}
    };
  } catch(e) { /* ignore */ }
})();
</script>
