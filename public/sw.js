/* Autentik Market — Service Worker pentru zona Staff (PWA).
 * Strategie: network-first pentru pagini (datele trebuie să fie live), cache pentru asset-urile statice din /build.
 * Fără server Node: fișier static servit de Apache. */
const CACHE = 'am-staff-v1';

self.addEventListener('install', (e) => {
    self.skipWaiting();
    e.waitUntil(caches.open(CACHE).then((c) => c.addAll(['/manifest.webmanifest', '/icons/icon-192.png'])));
});

self.addEventListener('activate', (e) => {
    e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))));
    self.clients.claim();
});

self.addEventListener('fetch', (e) => {
    const url = new URL(e.request.url);
    if (e.request.method !== 'GET' || url.origin !== location.origin) return;

    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        e.respondWith(caches.match(e.request).then((hit) => hit || fetch(e.request).then((res) => {
            const copy = res.clone();
            caches.open(CACHE).then((c) => c.put(e.request, copy));
            return res;
        })));
        return;
    }

    if (e.request.mode === 'navigate' && url.pathname.startsWith('/staff')) {
        e.respondWith(fetch(e.request).catch(() => new Response(
            '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><body style="font-family:sans-serif;background:#233a2d;color:#f6f1e7;display:grid;place-items:center;min-height:100vh;text-align:center"><div><h1>Offline</h1><p>Fără conexiune la internet. Check-in-ul necesită conexiune.<br>Nincs internetkapcsolat.</p></div></body>',
            { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        )));
    }
});
