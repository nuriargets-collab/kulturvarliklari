/* kulturvarliklari.com.tr — çevrimdışı destek */
const V = 'kv-2e297a6ac9';
const A = 'kv-assets-2e297a6ac9';
const CORE = ['./', 'index.html', 'site.css', 'app.js', 'data.json', 'fonts/fonts.css', 'favicon.svg', 'offline.html', 'manifest.webmanifest'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(V).then(c => c.addAll(CORE)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => k.startsWith('kv-') && k !== V && k !== 'kv-pages' && k !== A).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', e => {
  const r = e.request, u = new URL(r.url);
  if (r.method !== 'GET' || u.origin !== location.origin || u.pathname.includes('/api/')) return;
  const isPage = r.mode === 'navigate' || (r.headers.get('accept') || '').includes('text/html');
  if (isPage) {
    // Önce ağ; olmazsa önbellek; o da yoksa çevrimdışı sayfası
    e.respondWith(fetch(r).then(res => {
      if (res.ok) { const cp = res.clone(); caches.open('kv-pages').then(c => c.put(r, cp)); }
      return res;
    }).catch(() => caches.match(r, { ignoreSearch: true }).then(m => m || caches.match('offline.html'))));
    return;
  }
  // Görseller, yazı tipleri, betikler: önbellekten hızlı, arka planda yenile
  e.respondWith(caches.match(r).then(m => {
    const net = fetch(r).then(res => { if (res.ok) { const cp = res.clone(); caches.open(A).then(c => c.put(r, cp)); } return res; }).catch(() => m || caches.match(r, { ignoreSearch: true }));
    return m || net;
  }));
});
