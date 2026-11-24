const CACHE = 'cinemastar-public-v1';
const ASSETS = ['/offline.html', '/images/app-icon-192.png', '/images/app-icon-512.png'];
self.addEventListener('install', event => { event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(ASSETS))); self.skipWaiting(); });
self.addEventListener('activate', event => { event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key.startsWith('cinemastar-public-') && key !== CACHE).map(key => caches.delete(key)))).then(() => self.clients.claim())); });
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);
    if (event.request.method !== 'GET' || url.origin !== self.location.origin) return;
    if (event.request.mode === 'navigate') {
        event.respondWith(fetch(event.request).catch(() => caches.match('/offline.html')));
    } else if (ASSETS.includes(url.pathname)) {
        event.respondWith(caches.match(event.request).then(cached => cached || fetch(event.request)));
    }
});
