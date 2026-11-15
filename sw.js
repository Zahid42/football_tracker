/* Football Tracker service worker: fast static assets, network-first pages, offline fallback. */
const VERSION = 'ft-v1';
const OFFLINE = 'offline.html';
self.addEventListener('install', e => {
  e.waitUntil(caches.open(VERSION).then(c => c.add(OFFLINE)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => k !== VERSION).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', e => {
  const req = e.request, url = new URL(req.url);
  if (req.method !== 'GET' || url.origin !== location.origin || url.pathname.includes('/admin/')) { return; }
  if (/\/assets\/|\/uploads\//.test(url.pathname)) {
    e.respondWith(caches.open(VERSION).then(c => c.match(req).then(hit => hit || fetch(req).then(r => { if (r.ok) { c.put(req, r.clone()); } return r; }))));
    return;
  }
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
  }
});
