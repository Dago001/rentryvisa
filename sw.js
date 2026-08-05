const CACHE_NAME = 'nisrev-offline-cache-v1';
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
    './assets/images/favicon.ico'
];

// Install Event - Cache initial shell assets
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('[Service Worker] Pre-caching offline shell');
                return cache.addAll(ASSETS_TO_CACHE);
            })
            .then(() => self.skipWaiting())
    );
});

// Activate Event - Clean up old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cache => {
                    if (cache !== CACHE_NAME) {
                        console.log('[Service Worker] Clearing old cache:', cache);
                        return caches.delete(cache);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch Event - Network-First, falling back to cache
self.addEventListener('fetch', event => {
    // Only intercept local GET requests
    if (event.request.method !== 'GET' || !event.request.url.startsWith(self.location.origin)) {
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then(response => {
                // If response is valid, clone and cache it
                if (response && response.status === 200) {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then(cache => {
                        cache.put(event.request, responseClone);
                    });
                }
                return response;
            })
            .catch(() => {
                // Network failed, lookup in cache
                return caches.match(event.request)
                    .then(cachedResponse => {
                        if (cachedResponse) {
                            return cachedResponse;
                        }
                        // If everything fails and it's a page navigation, return generic offline response
                        if (event.request.headers.get('accept').includes('text/html')) {
                            return caches.match('./index');
                        }
                    });
            })
    );
});
