/*
 * Escudo service worker: makes the app installable and keeps the shell (built
 * CSS/JS, icons) cached. Pages and data are never cached - they are personal
 * financial data - so without connection the app shows the offline page.
 */
const VERSION = 'escudo-v1';
const OFFLINE_URL = new URL('offline.html', self.registration.scope).href;
const PRECACHE = [OFFLINE_URL, new URL('icons/icon-192.png', self.registration.scope).href];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(VERSION).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== VERSION).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

// fingerprinted build files and icons never change under the same URL
const isStatic = (url) => url.origin === self.location.origin && /\/(build|icons)\//.test(url.pathname);

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    if (isStatic(url)) {
        event.respondWith(caches.open(VERSION).then(async (cache) => {
            const cached = await cache.match(request);
            if (cached) return cached;
            const response = await fetch(request);
            if (response.ok) cache.put(request, response.clone());
            return response;
        }));
    }
});
