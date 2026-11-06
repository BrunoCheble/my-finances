/*
 * Escudo service worker: makes the app installable and keeps the shell (built
 * CSS/JS, icons) cached. Pages and data are never cached - they are personal
 * financial data - so without connection the app shows the offline page.
 */
const VERSION = 'escudo-v2';
// a PDF shared from the system's share menu, kept until the import screen picks it up
const SHARE_CACHE = 'escudo-share';
const SHARED_FILE = new URL('shared-statement', self.registration.scope).href;
const OFFLINE_URL = new URL('offline.html', self.registration.scope).href;
const PRECACHE = [OFFLINE_URL, new URL('icons/icon-v2-192.png', self.registration.scope).href];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(VERSION).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== VERSION && key !== SHARE_CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

// fingerprinted build files and icons never change under the same URL
const isStatic = (url) => url.origin === self.location.origin && /\/(build|icons)\//.test(url.pathname);

// share menu (Android): keep the PDF and open the import screen, which picks it up
async function receiveShare(request) {
    const file = (await request.formData()).get('file');
    if (file && typeof file !== 'string') {
        const cache = await caches.open(SHARE_CACHE);
        // bank apps often send the PDF as a generic file; the server checks the content anyway
        await cache.put(SHARED_FILE, new Response(file, {
            headers: { 'Content-Type': 'application/pdf', 'X-File-Name': encodeURIComponent(file.name || 'extrato.pdf') },
        }));
    }
    return Response.redirect(new URL('statement-imports?shared=1', self.registration.scope).href, 303);
}

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method === 'POST' && url.pathname.endsWith('/statement-imports/share')) {
        event.respondWith(receiveShare(request));
        return;
    }
    if (request.method !== 'GET') return;

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
