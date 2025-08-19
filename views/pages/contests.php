<?php
?>
<section class="space-y-4">
  <h2 class="text-xl font-semibold">Danh sách cuộc thi</h2>
  <div class="bg-slate-900/60 border border-white/10 rounded-lg p-3 grid grid-cols-1 md:grid-cols-6 gap-2">
    <div class="col-span-1">
      <input id="filterQ" class="w-full bg-slate-800/60 border border-white/10 rounded px-3 py-2 text-sm" placeholder="Tìm kiếm...">
    </div>
    <div class="col-span-1">
      <select id="filterStatus" class="w-full bg-slate-800/60 border border-white/10 rounded px-3 py-2 text-sm">
        <option value="">Trạng thái: Tất cả</option>
        <option value="ongoing">Đang diễn ra</option>
        <option value="upcoming">Sắp diễn ra</option>
        <option value="ended">Đã kết thúc</option>
      </select>
    </div>
    <div class="col-span-1">
      <select id="filterSort" class="w-full bg-slate-800/60 border border-white/10 rounded px-3 py-2 text-sm">
        <option value="newest">Mới nhất</option>
        <option value="popular">Bình chọn nhiều</option>
        <option value="ending">Sắp kết thúc</option>
        <option value="starting">Sắp bắt đầu</option>
      </select>
    </div>
    <div class="col-span-1">
      <select id="filterCategory" class="w-full bg-slate-800/60 border border-white/10 rounded px-3 py-2 text-sm">
        <option value="">Danh mục: Tất cả</option>
        <option value="music">Music</option>
        <option value="beauty">Beauty</option>
        <option value="sports">Sports</option>
        <option value="education">Education</option>
        <option value="tech">Tech</option>
        <option value="art">Art</option>
        <option value="fashion">Fashion</option>
        <option value="travel">Travel</option>
        <option value="food">Food</option>
      </select>
    </div>
    <div class="col-span-1">
      <input id="filterTags" class="w-full bg-slate-800/60 border border-white/10 rounded px-3 py-2 text-sm" placeholder="Tags (vd: hot,2025)">
    </div>
    <div class="col-span-1 text-right">
      <button id="btnApply" class="px-3 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded text-sm">Áp dụng</button>
    </div>
  </div>
  <div id="list" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"></div>
  <div id="pager" class="flex items-center justify-center gap-2 pt-2"></div>
</section>
<script>
(async function(){
  const wrap = document.getElementById('list');

  // helpers
  const qs = new URLSearchParams(location.search);
  const getStatus = (c) => {
    const now = Date.now();
    const s = c.starts_at ? Date.parse(c.starts_at) : null;
    const e = c.ends_at ? Date.parse(c.ends_at) : null;
    if (s && now < s) return 'upcoming';
    if (e && now > e) return 'ended';
    return 'ongoing';
  };
  const applyFilters = () => {
    const q = (document.getElementById('filterQ').value||'').toLowerCase().trim();
    const st = document.getElementById('filterStatus').value;
    const sort = document.getElementById('filterSort').value;
    let arr = list.slice();
    if (q) arr = arr.filter(c => (c.name||'').toLowerCase().includes(q) || (c.description||'').toLowerCase().includes(q));
    if (st) arr = arr.filter(c => getStatus(c) === st);
    switch (sort) {
      case 'popular': arr.sort((a,b)=> (b.total_votes||0)-(a.total_votes||0)); break;
      case 'ending': arr.sort((a,b)=> (Date.parse(a.ends_at||'9999')||Infinity) - (Date.parse(b.ends_at||'9999')||Infinity)); break;
      case 'starting': arr.sort((a,b)=> (Date.parse(a.starts_at||'9999')||Infinity) - (Date.parse(b.starts_at||'9999')||Infinity)); break;
      default: arr.sort((a,b)=> (Date.parse(b.created_at||0)||0) - (Date.parse(a.created_at||0)||0));
    }
    return arr;
  };

  // initialize controls from query
  document.getElementById('filterQ').value = qs.get('q')||'';
  document.getElementById('filterStatus').value = qs.get('status')||'';
  document.getElementById('filterSort').value = qs.get('sort')||'newest';
  if (document.getElementById('filterCategory')) document.getElementById('filterCategory').value = qs.get('category')||'';
  if (document.getElementById('filterTags')) document.getElementById('filterTags').value = qs.get('tags')||'';

  const render = async () => {
    // Build API query
    const q = qs.get('q')||'';
    const st = qs.get('status')||'';
    const sort = qs.get('sort')||'newest';
    const category = qs.get('category')||'';
    const tags = qs.get('tags')||'';
    const page = Math.max(1, Number(qs.get('page')||'1'));
    const perPage = 12;

    const url = `/api/public/contests?q=${encodeURIComponent(q)}&status=${encodeURIComponent(st)}&sort=${encodeURIComponent(sort)}&category=${encodeURIComponent(category)}&tags=${encodeURIComponent(tags)}&page=${page}&per_page=${perPage}`;
    const res = await secureFetch(url);
    const data = await res.json();
    const arr = (data && data.data) ? data.data : [];
    const meta = (data && data.meta) ? data.meta : { page, pages: 1, total: arr.length, per_page: perPage };

    if (!arr.length) { wrap.innerHTML = '<div class="text-slate-400">Chưa có dữ liệu</div>'; document.getElementById('pager').innerHTML=''; return; }

    wrap.innerHTML = arr.map(c => `
    <a href="/contests/${c.id}" class="block bg-slate-900/60 border border-white/10 rounded-lg p-4 hover:bg-slate-900">
      <div class="h-36 bg-slate-700/40 rounded mb-3" style="background:url('${c.image_url||'https://via.placeholder.com/300x140'}') center/cover"></div>
      <p class="font-medium">${c.name}</p>
      <p class="text-sm text-slate-400">${(c.description||'').slice(0,120)}</p>
      <div class="mt-1 text-xs text-slate-500">${getStatus(c)==='ongoing'?'Đang diễn ra':(getStatus(c)==='upcoming'?'Sắp diễn ra':'Đã kết thúc')} • ${c.total_votes||0} bình chọn</div>
    </a>`).join('');

    // pager
    const pager = document.getElementById('pager');
    if (meta.pages > 1) {
      const mkHref = (p)=>{ const nqs = new URLSearchParams(qs); nqs.set('page', String(p)); return `?${nqs.toString()}`; };
      const mkBtn = (p, label = p, disabled=false)=>`<a href="${mkHref(p)}" class="px-3 py-1 rounded ${disabled?'bg-slate-800/60 text-slate-500 pointer-events-none':'bg-slate-800/60 hover:bg-slate-700 text-white'}">${label}</a>`;
      pager.innerHTML = `
        ${mkBtn(Math.max(1, meta.page-1), 'Prev', meta.page===1)}
        <span class="px-2 text-sm text-slate-400">Trang ${meta.page}/${meta.pages}</span>
        ${mkBtn(Math.min(meta.pages, meta.page+1), 'Next', meta.page===meta.pages)}
      `;
    } else { pager.innerHTML = ''; }
  };

  await render();

  // Bind apply button
  document.getElementById('btnApply').addEventListener('click', ()=>{
    const q = document.getElementById('filterQ').value.trim();
    const st = document.getElementById('filterStatus').value;
    const sort = document.getElementById('filterSort').value;
    const category = (document.getElementById('filterCategory')?.value || '').trim();
    const tags = (document.getElementById('filterTags')?.value || '').trim();
    const newQs = new URLSearchParams(qs);
    if (q) newQs.set('q', q); else newQs.delete('q');
    if (st) newQs.set('status', st); else newQs.delete('status');
    if (sort) newQs.set('sort', sort); else newQs.delete('sort');
    if (category) newQs.set('category', category); else newQs.delete('category');
    if (tags) newQs.set('tags', tags); else newQs.delete('tags');
    newQs.delete('page');
    window.location.search = `?${newQs.toString()}`;
  });
})();
</script>
