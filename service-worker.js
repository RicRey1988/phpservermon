'use strict';

const STATIC_CACHE = 'psm-static-4.1.0-hs';
const STATIC_ROOT = '/src/templates/default/static/';
const STATIC_ASSETS = [
  './offline.html',
  './manifest.webmanifest',
  './src/templates/default/static/css/app-shell.css?v=4.1.0-hs',
  './src/templates/default/static/js/app-shell.js?v=4.1.0-hs',
  './src/templates/default/static/js/pwa.js?v=4.1.0-hs',
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
