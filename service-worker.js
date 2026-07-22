'use strict';

const STATIC_CACHE = 'psm-static-4.3.6-hs-r1';
const STATIC_ROOT = '/src/templates/default/static/';
const STATIC_ASSETS = [
  './offline.html',
  './manifest.webmanifest',
  './src/templates/default/static/hope/css/hope-ui.min.css?v=4.3.6-hs',
  './src/templates/default/static/hope/css/dark.min.css?v=4.3.6-hs',
  './src/templates/default/static/hope/css/customizer.min.css?v=4.3.6-hs',
  './src/templates/default/static/hope/js/bootstrap.bundle.min.js?v=4.3.6-hs',
  './src/templates/default/static/hope/js/hope-ui.js?v=4.3.6-hs',
  './src/templates/default/static/hope/js/plugins/setting.js?v=4.3.6-hs',
  './src/templates/default/static/css/hs-monitor.css?v=4.3.6-hs&ui=1',
  './src/templates/default/static/hope/images/auth/01.png',
  './src/templates/default/static/js/app-shell.js?v=4.3.6-hs',
  './src/templates/default/static/js/status.js?v=4.3.6-hs',
  './src/templates/default/static/js/dashboard.js?v=4.3.6-hs',
  './src/templates/default/static/js/pwa.js?v=4.3.6-hs',
  './src/templates/default/static/images/pwa/icon-192.png',
  './src/templates/default/static/images/pwa/icon-512.png',
  './src/templates/default/static/images/pwa/icon-maskable-512.png'
];

self.addEventListener('install', function (event) {
  event.waitUntil(caches.open(STATIC_CACHE).then(function (cache) { return cache.addAll(STATIC_ASSETS); }));
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(caches.keys().then(function (keys) {
    return Promise.all(keys.filter(function (key) {
      return key.startsWith('psm-static-') && key !== STATIC_CACHE;
    }).map(function (key) { return caches.delete(key); }));
  }).then(function () { return self.clients.claim(); }));
});

function isPrivateRequest(url) {
  const path = url.pathname;
  const moduleName = url.searchParams.get('mod') || '';
  const action = url.searchParams.get('action') || '';
  return path.endsWith('index.php') || path.endsWith('install.php') || path.endsWith('public.php')
    || moduleName === 'config' || moduleName === 'server_update' || moduleName === 'server_status'
    || action === 'snapshot' || action === 'phpInfo' || url.searchParams.has('xhr');
}

function isStaticRequest(url) {
  return url.origin === self.location.origin && (
    url.pathname.includes(STATIC_ROOT) || url.pathname.endsWith('manifest.webmanifest')
  );
}

self.addEventListener('fetch', function (event) {
  const request = event.request;
  if (request.method !== 'GET') { return; }
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) { return; }

  if (isPrivateRequest(url)) {
    event.respondWith(request.mode === 'navigate'
      ? fetch(request).catch(function () { return caches.match('./offline.html'); })
      : fetch(request));
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(function () { return caches.match('./offline.html'); }));
    return;
  }

  if (isStaticRequest(url)) {
    event.respondWith(caches.match(request).then(function (cached) {
      if (cached) { return cached; }
      return fetch(request).then(function (response) {
        if (response.ok) {
          const copy = response.clone();
          caches.open(STATIC_CACHE).then(function (cache) { cache.put(response.url, copy); });
        }
        return response;
      });
    }));
  }
});

self.addEventListener('message', function (event) {
  if (event.data && event.data.type === 'CLEAR_PRIVATE_CACHES') {
    event.waitUntil(caches.keys().then(function (keys) {
      return Promise.all(keys.filter(function (key) { return key.startsWith('psm-private-'); })
        .map(function (key) { return caches.delete(key); }));
    }));
  }
});

self.addEventListener('push', function (event) {
  let payload = { title: 'PHP Server Monitor', body: 'Hay una actualización de estado.', url: './index.php?mod=server_status' };
  try { payload = Object.assign(payload, event.data ? event.data.json() : {}); } catch (error) { /* use safe defaults */ }
  event.waitUntil(self.registration.showNotification(payload.title, {
    body: payload.body,
    icon: payload.icon || './src/templates/default/static/images/pwa/icon-192.png',
    badge: payload.badge || './src/templates/default/static/images/pwa/icon-192.png',
    tag: payload.tag || 'server-monitor-update',
    renotify: Boolean(payload.critical),
    requireInteraction: Boolean(payload.critical),
    data: { url: payload.url || './index.php?mod=server_status' }
  }));
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  let target = './index.php?mod=server_status';
  try {
    const candidate = new URL(event.notification.data.url, self.location.origin);
    if (candidate.origin === self.location.origin) { target = candidate.href; }
  } catch (error) { /* keep same-origin fallback */ }
  event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clients) {
    for (const client of clients) {
      if ('focus' in client) { client.navigate(target); return client.focus(); }
    }
    return self.clients.openWindow(target);
  }));
});
