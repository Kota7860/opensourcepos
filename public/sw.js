/**
 * OSPOS service worker.
 *
 * Deliberately conservative for a point-of-sale app that serves
 * authenticated, per-user, frequently changing pages:
 *
 *   - Navigation/HTML requests are network-first and are never stored, so a
 *     cashier never sees a stale cart or another user's authenticated page.
 *     When the network is unreachable, an offline fallback page is shown.
 *   - Same-origin static assets (css/js/fonts/images/resources) use
 *     cache-first with a background refresh, which is what makes the installed
 *     app launch quickly on mobile.
 *
 * Bump CACHE_VERSION to invalidate old caches on the next activation.
 */
const CACHE_VERSION = 'v1';
const STATIC_CACHE = `ospos-static-${CACHE_VERSION}`;
const OFFLINE_URL = 'offline.html';

const STATIC_PATH = /\/(css|js|fonts|images|resources)\//;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.add(new Request(OFFLINE_URL, {cache: 'reload'})))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key !== STATIC_CACHE).map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    const sameOrigin = url.origin === self.location.origin;

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL))
        );
        return;
    }

    if (sameOrigin && STATIC_PATH.test(url.pathname)) {
        event.respondWith(
            caches.open(STATIC_CACHE).then((cache) =>
                cache.match(request).then((cached) => {
                    const network = fetch(request).then((response) => {
                        if (response && response.status === 200 && response.type === 'basic') {
                            cache.put(request, response.clone());
                        }
                        return response;
                    }).catch(() => cached);

                    return cached || network;
                })
            )
        );
    }
});
