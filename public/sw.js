/*
| FoodLoop service worker. Makes the app installable on phones and shows a
| friendly page when there is no internet.
|
| Pages are always fetched from the server (deals and codes must be live);
| nothing personal is cached. Only the offline page and its icon are stored.
*/
const CACHE = 'foodloop-offline-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll([OFFLINE_URL, '/icons/icon-192.png'])));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    // Only plain page loads. Form submits (reserve, collect…) always go straight to the server.
    if (event.request.mode !== 'navigate' || event.request.method !== 'GET') {
        return;
    }
    event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE_URL)));
});
