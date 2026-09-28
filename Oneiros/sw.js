/* Oneiros: cache public assets only. Journal and API responses remain private. */
const CACHE = 'oneiros-lucid-v5';
const SCOPE = new URL('./', self.location.href);
self.addEventListener('install', event => event.waitUntil(self.skipWaiting()));
self.addEventListener('activate', event => event.waitUntil((async () => {
  const keys = await caches.keys();
  await Promise.all(keys.filter(key => key.startsWith('oneiros-') && key !== CACHE).map(key => caches.delete(key)));
  await self.clients.claim();
})()));
self.addEventListener('fetch', event => {
  const request = event.request;
  const url = new URL(request.url);
  if (request.method !== 'GET' || url.origin !== SCOPE.origin) return;
  const path = url.pathname.slice(SCOPE.pathname.length);
  if (path.startsWith('api/') || path === 'api.php' || path.startsWith('uploads/')) return;
  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(() => new Response(`<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Oneiros · Offline</title><style>body{margin:0;min-height:100vh;display:grid;place-content:center;padding:32px;box-sizing:border-box;text-align:center;background:radial-gradient(ellipse at 50% 20%,#2a2550,#080b18 65%);color:#f2edf9;font:16px/1.8 system-ui}h1{font:italic 48px Georgia,serif;margin-bottom:4px}p{max-width:380px;color:#b9b0d0}a{color:#c8bbf4}</style><h1>Between dreams.</h1><p>Reconnect to open your private journal and sync with Oneiros. Keep any unsaved dream text in your open page until you reconnect.</p><a href="${SCOPE.pathname}oneiros.php">Try again</a></html>`, {status:503,headers:{'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-store'}})));
    return;
  }
  if (!path.startsWith('assets/') && path !== 'manifest.json') return;
  event.respondWith((async () => {
    const cache = await caches.open(CACHE);
    const cached = await cache.match(request);
    if (cached) return cached;
    const response = await fetch(request);
    if (response.ok && response.type === 'basic') await cache.put(request, response.clone());
    return response;
  })());
});
self.addEventListener('notificationclick', event => {
  event.notification.close();
  if (['dismiss', 'later'].includes(event.action)) return;
  event.waitUntil((async () => {
    const requested = new URL(event.notification.data?.url || 'oneiros.php', SCOPE);
    const target = requested.origin === SCOPE.origin ? requested.href : new URL('oneiros.php', SCOPE).href;
    const windows = await self.clients.matchAll({type:'window',includeUncontrolled:true});
    const existing = windows.find(client => client.url.startsWith(SCOPE.href));
    if (existing) { await existing.navigate(target); return existing.focus(); }
    return self.clients.openWindow(target);
  })());
});
