// Service worker: keeps pages on the device so the gate still works when the internet fails.
const C = 'guardlink-v1';
self.addEventListener('install', e => { e.waitUntil(caches.open(C).then(c => c.addAll(['offline.html', 'style.css']))); self.skipWaiting(); });
self.addEventListener('activate', e => e.waitUntil(clients.claim()));
self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET') return;
  e.respondWith(fetch(e.request).then(r => {
    if (r.ok || r.type === 'opaque') { const copy = r.clone(); caches.open(C).then(c => c.put(e.request, copy)); }
    return r;
  }).catch(() => caches.match(e.request).then(r => r || caches.match('offline.html'))));
});
