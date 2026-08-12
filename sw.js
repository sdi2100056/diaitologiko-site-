const CACHE = 'diet-v1';
const SHELL = ['index.php', 'assets/css/style.css', 'assets/js/main.js', 'offline.html'];
self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(SHELL.map(u => new Request(u, {cache:'reload'})))).catch(()=>{}));
  self.skipWaiting();
});
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))));
  self.clients.claim();
});
self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  // Ποτέ cache για admin/portal/api (ευαίσθητα/δυναμικά)
  if (/\/(admin|portal|api|cron)\//.test(url.pathname)) return;
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match(req).then(r => r || caches.match('offline.html'))));
    return;
  }
  e.respondWith(caches.match(req).then(r => r || fetch(req).then(resp => {
    if (resp.ok && (url.pathname.includes('/assets/'))) {
      const copy = resp.clone(); caches.open(CACHE).then(c => c.put(req, copy));
    }
    return resp;
  }).catch(() => r)));
});
