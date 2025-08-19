<?php
?>
<section class="grid grid-cols-1 md:grid-cols-3 gap-6">
  <!-- Cuộc thi nổi bật x2 -->
  <div class="md:col-span-2 bg-slate-900/60 border border-white/10 rounded-xl p-4">
    <h2 class="text-lg font-semibold mb-3">Cuộc thi nổi bật</h2>
    <div id="featured" class="grid grid-cols-1 sm:grid-cols-2 gap-4"></div>
  </div>

  <!-- Cột phải: Thí sinh nổi bật x3 + BXH top 25 cuộn dọc -->
  <div class="bg-slate-900/60 border border-white/10 rounded-xl p-4">
    <h2 class="text-lg font-semibold mb-3">Thí sinh nổi bật</h2>
    <div id="featuredContestants" class="space-y-3"></div>

    <h2 class="text-lg font-semibold mt-6 mb-3">BXH Top 25</h2>
    <div class="max-h-80 overflow-y-auto pr-1">
      <ol id="top25" class="space-y-2 text-sm"></ol>
    </div>
  </div>
</section>
<script>
// Featured contests x2
(async function(){
  try {
    const res = await fetch('/api/public/contests');
    let list = [];
    try{ if(res.ok){ const data = await res.json(); list = data.data || []; } else { console.warn('contests api', res.status, await res.text()); } }catch(e){ try{ console.warn('contests parse', await res.text()); }catch(_){} }
    const el = document.getElementById('featured');
    if (!list.length) { el.innerHTML = '<div class="text-slate-400">Chưa có dữ liệu</div>'; return; }
    for (const c of list.slice(0,2)) {
      el.innerHTML += `<div class="bg-slate-800/60 rounded-lg p-3">
        <div class="h-32 bg-slate-700/50 rounded mb-2" style="background:url('${c.image_url || 'https://via.placeholder.com/300x130'}') center/cover"></div>
        <div class="flex justify-between items-center">
          <div>
            <p class="font-medium">${c.name}</p>
            <p class="text-sm text-slate-400">${(c.description||'').slice(0,60)}</p>
          </div>
          <a class="text-sm text-blue-400" href="/contests/${c.id}">Xem</a>
        </div>
      </div>`
    }

    // Nếu đã đăng nhập: hiển thị danh sách thí sinh tối đa 15 cho cuộc thi đầu tiên
    try {
      if (window.__LOGGED_IN__ && list.length > 0) {
        const first = list[0];
        const wrap = document.createElement('div');
        wrap.className = 'mt-4';
        wrap.innerHTML = `<h3 class="text-base font-medium mb-2">Danh sách thí sinh</h3><div id="homeContestants" class="grid grid-cols-1 sm:grid-cols-2 gap-3"></div>`;
        const parent = document.querySelector('.md\:col-span-2.bg-slate-900\/60.border.border-white\/10.rounded-xl.p-4');
        if (parent) parent.appendChild(wrap);
        try {
          const r = await fetch(`/api/public/contestants?contest_id=${first.id}`);
          if (r.ok) {
            const j = await r.json();
            const ls = (j && j.data) ? j.data.slice(0,15) : [];
            const grid = document.getElementById('homeContestants');
            if (grid) {
              grid.innerHTML = (ls.length? ls: []).map(k=>`
                <div class="bg-slate-800/60 rounded p-3">
                  <div class="h-28 rounded mb-2 bg-slate-700/40" style="background:url('${k.photo_url||'https://via.placeholder.com/300x140'}') center/cover"></div>
                  <div class="flex items-center justify-between">
                    <div>
                      <p class="font-medium">${k.name||'Thí sinh'}</p>
                      <p class="text-xs text-slate-400">SBD: ${k.id}</p>
                    </div>
                    <button class="bg-blue-600 hover:bg-blue-500 text-white px-3 py-1.5 rounded" onclick="(async()=>{try{const rs=await (window.secureFetch?secureFetch:fetch)('/api/vote',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({contestant_id:${'${k.id}'} })}); if(!rs.ok){ window.toast&&toast('Lỗi bình chọn','error'); return;} let jj=null; try{jj=await rs.json();}catch(_){} if(jj&&jj.ok){ window.toast&&toast('Đã bình chọn!','success'); } else { window.toast&&toast((jj&&jj.error)||'Lỗi bình chọn','error'); } }catch(_){ window.toast&&toast('Lỗi kết nối','error'); } })()">Bình chọn</button>
                  </div>
                </div>
              `).join('');
            }
          }
        } catch(_){ /* ignore */ }
      }
    } catch(_){ /* ignore */ }
  } catch(e){}
})();

// Thí sinh nổi bật x3 + BXH top25 từ /api/rankings (public)
(async function(){
  try {
    // Use correct public rankings endpoint; slice on client
    const res = await fetch('/api/public/rankings');
    let list = [];
    try{ if(res.ok){ const data = await res.json(); list = data.data || []; } else { console.warn('rankings api', res.status, await res.text()); } }catch(e){ try{ console.warn('rankings parse', await res.text()); }catch(_){} }
    const feat = document.getElementById('featuredContestants');
    const top = document.getElementById('top25');
    // Featured contestants x3
    for (const r of list.slice(0,3)) {
      feat.innerHTML += `<div class="flex items-center gap-3 bg-slate-800/60 rounded p-2">
        <div class="w-12 h-12 bg-slate-700/50 rounded"></div>
        <div class="flex-1">
          <p class="font-medium">${r.contestant_name || r.name || 'Thí sinh'}</p>
          <p class="text-xs text-slate-400">${r.contest_name || ''}</p>
        </div>
        <span class="text-sm text-emerald-400">${r.votes} ❤</span>
      </div>`
    }
    // Top 25 vertical list
    let i=1; for (const r of list.slice(0,25)) {
      top.innerHTML += `<li class="flex items-center justify-between bg-slate-800/40 rounded px-3 py-2">
        <span class="text-slate-400">#${i++}</span>
        <div class="flex-1 px-3 truncate">${r.contestant_name || r.name}</div>
        <span class="text-xs text-slate-300">${r.votes}</span>
      </li>`
    }
  } catch(e){}
})();
</script>
