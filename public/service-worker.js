const CACHE_NAME = 'bvote-v2';
const ASSETS = [
  '/',
  '/manifest.json',
  '/assets/css/style.css',
  '/assets/js/app.js'
];

self.addEventListener('install', (e) => {
  e.waitUntil((async () => {
    const cache = await caches.open(CACHE_NAME);
    await cache.addAll(ASSETS);
    self.skipWaiting();
  })());
});

self.addEventListener('activate', (e) => {
  e.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)));
    self.clients.claim();
  })());
});

self.addEventListener('fetch', (e) => {
  const { request } = e;
  if (request.method !== 'GET') return;
  const url = new URL(request.url);

  // Do not interfere with websocket, API, or cross-origin requests
  if (request.destination === 'document') {
    // Network-first for navigations to avoid serving stale HTML
    e.respondWith((async () => {
      try {
        const fresh = await fetch(request);
        const cache = await caches.open(CACHE_NAME);
        cache.put(request, fresh.clone());
        return fresh;
      } catch (err) {
        const cached = await caches.match(request);
        return cached || Response.error();
      }
    })());
    return;
  }

  if (url.pathname.startsWith('/api/') || url.protocol === 'ws:' || url.protocol === 'wss:') {
    // Bypass caching for API/WS
    return; // let the request go to network normally
  }

  if (url.origin !== location.origin) {
    // Don't cache cross-origin by default
    return;
  }

  // Cache-first for static assets
  e.respondWith((async () => {
    const cached = await caches.match(request);
    if (cached) return cached;
    try {
      const resp = await fetch(request);
      // Only cache successful GET responses
      if (resp && resp.ok) {
        const cache = await caches.open(CACHE_NAME);
        cache.put(request, resp.clone());
      }
      return resp;
    } catch (err) {
      return cached || Response.error();
    }
  })());
});
