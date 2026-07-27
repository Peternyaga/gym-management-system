const CACHE = 'gymflow-shell-v1';
const SHELL = ['/', '/favicon.svg', '/favicon.ico'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(SHELL)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys.filter((key) => key !== CACHE).map((key) => caches.delete(key)),
                ),
            ),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;
    const cacheable =
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/themes/') ||
        url.pathname.endsWith('.webmanifest') ||
        SHELL.includes(url.pathname);
    if (!cacheable) return;

    event.respondWith(
        caches.open(CACHE).then(async (cache) => {
            const cached = await cache.match(request);
            const network = fetch(request)
                .then((response) => {
                    if (response.ok) cache.put(request, response.clone());
                    return response;
                })
                .catch(() => cached);

            return cached || network;
        }),
    );
});
