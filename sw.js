const CACHE_NAME = 'nisrev-ultra-cache-v2';
const ASSETS_TO_CACHE = [
    './',
    './index',
    './new-record',
    './re-entry-visa',
    './profile',
    './globalsearch',
    './user_dashboard',
    './vendors/bootstrap/dist/css/bootstrap.min.css',
    './vendors/font-awesome/css/font-awesome.min.css',
    './vendors/bootstrap/dist/js/bootstrap.bundle.min.js',
    './vendors/qrcodejs/qrcode.min.js',
    './vendors/chartjs/chart.js',
    './assets/images/favicon.ico',
    './assets/images/logo.png'
];

// Install Event - Pre-cache critical application shells and local libraries
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('[Service Worker] Pre-caching static shells & scripts');
                return cache.addAll(ASSETS_TO_CACHE);
            })
            .then(() => self.skipWaiting())
    );
});

// Activate Event - Prune old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cache => {
                    if (cache !== CACHE_NAME) {
                        console.log('[Service Worker] Evicting outdated cache:', cache);
                        return caches.delete(cache);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Helper to check if request is a static asset
function isStaticAsset(url) {
    return (
        url.endsWith('.css') ||
        url.endsWith('.js') ||
        url.endsWith('.png') ||
        url.endsWith('.jpg') ||
        url.endsWith('.jpeg') ||
        url.endsWith('.svg') ||
        url.endsWith('.ico') ||
        url.endsWith('.woff') ||
        url.endsWith('.woff2') ||
        url.endsWith('.ttf') ||
        url.includes('/vendors/') ||
        url.includes('/assets/')
    );
}

// Fetch Event - Dynamic routing strategy for maximum responsiveness
self.addEventListener('fetch', event => {
    // Only intercept local GET requests
    if (event.request.method !== 'GET' || !event.request.url.startsWith(self.location.origin)) {
        return;
    }

    const requestUrl = event.request.url;

    // 1. Cache-First Strategy for static assets (CSS, JS, Fonts, Images)
    if (isStaticAsset(requestUrl)) {
        event.respondWith(
            caches.match(event.request).then(cachedResponse => {
                if (cachedResponse) {
                    return cachedResponse;
                }
                return fetch(event.request).then(response => {
                    if (response && response.status === 200) {
                        const responseClone = response.clone();
                        caches.open(CACHE_NAME).then(cache => {
                            cache.put(event.request, responseClone);
                        });
                    }
                    return response;
                });
            })
        );
        return;
    }

    // 2. Stale-While-Revalidate Strategy for HTML page layouts
    event.respondWith(
        caches.match(event.request).then(cachedResponse => {
            const fetchPromise = fetch(event.request)
                .then(networkResponse => {
                    if (networkResponse && networkResponse.status === 200) {
                        const responseClone = networkResponse.clone();
                        caches.open(CACHE_NAME).then(cache => {
                            cache.put(event.request, responseClone);
                        });
                    }
                    return networkResponse;
                })
                .catch(() => {
                    // Fallback to cache if network fails
                    return cachedResponse;
                });

            // Return cached response instantly if available, otherwise wait for network fetch
            return cachedResponse || fetchPromise;
        }).catch(() => {
            // Ultimate fallback to index (login form) if all fail
            if (event.request.headers.get('accept').includes('text/html')) {
                return caches.match('./index');
            }
        })
    );
});
