/*
 * Service Worker — Portal del Médico · Hospital General Las Colinas
 * Scope: /portal-medico/  (se sirve desde esta misma carpeta).
 *
 * PRINCIPIO DE PRIVACIDAD (datos clínicos / PHI):
 *   - Las navegaciones a páginas del portal (que pueden contener datos de
 *     pacientes) usan NETWORK-FIRST y NO se almacenan en caché. Si no hay red,
 *     se muestra una página "Sin conexión" estática (sin datos).
 *   - El proxy del API (/api/doctor-proxy.php) queda FUERA de este scope, así
 *     que el SW ni siquiera lo intercepta. Ningún dato clínico se persiste.
 *   - Solo se cachean estáticos propios del portal sin PHI: íconos, manifest
 *     y la página offline.
 *
 * Actualizaciones: install precachea y espera; el cliente decide cuándo activar
 * (toast "Nueva versión"), enviando {type:'SKIP_WAITING'}.
 */
'use strict';

const VERSION  = 'hglc-medico-v1';
const PRECACHE = VERSION + '-precache';
const RUNTIME  = VERSION + '-runtime';

const PRECACHE_URLS = [
  'offline.html',
  'manifest.webmanifest',
  'icons/icon-192.png',
  'icons/icon-512.png',
  'icons/maskable-192.png',
  'icons/apple-touch-icon.png',
  'icons/favicon-32.png',
].map((u) => new URL(u, self.location).toString());

const OFFLINE_URL = new URL('offline.html', self.location).toString();

/** Solo estáticos propios del portal son cacheables (jamás PHI). */
function isCacheableStatic(url) {
  return /\/portal-medico\/(icons\/|manifest\.webmanifest$|offline\.html$)/.test(url.pathname);
}

self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(PRECACHE);
    await cache.addAll(PRECACHE_URLS);
  })());
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    if (self.registration.navigationPreload) {
      try { await self.registration.navigationPreload.enable(); } catch (e) { /* noop */ }
    }
    const keys = await caches.keys();
    await Promise.all(
      keys.filter((k) => k.startsWith('hglc-medico-') && k.indexOf(VERSION) !== 0)
          .map((k) => caches.delete(k))
    );
    await self.clients.claim();
  })());
});

self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') self.skipWaiting();
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;               // nunca tocar POST/PUT/DELETE
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return; // solo mismo origen

  // 1) Navegaciones (HTML, posibles datos de paciente): network-first, sin cachear.
  if (req.mode === 'navigate') {
    event.respondWith((async () => {
      try {
        const preload = await event.preloadResponse;
        if (preload) return preload;
        return await fetch(req);
      } catch (e) {
        const cache = await caches.open(PRECACHE);
        const offline = await cache.match(OFFLINE_URL);
        return offline || new Response('Sin conexión', {
          status: 503,
          headers: { 'Content-Type': 'text/plain; charset=utf-8' },
        });
      }
    })());
    return;
  }

  // 2) Estáticos del portal (íconos/manifest/offline): stale-while-revalidate.
  if (isCacheableStatic(url)) {
    event.respondWith((async () => {
      const cache = await caches.open(RUNTIME);
      const cached = await cache.match(req);
      const network = fetch(req).then((res) => {
        if (res && res.status === 200 && res.type === 'basic') cache.put(req, res.clone());
        return res;
      }).catch(() => null);
      return cached || (await network) || new Response('', { status: 504 });
    })());
    return;
  }

  // 3) Cualquier otra petición in-scope: red directa (sin caché). El navegador
  //    aplica su propio HTTP cache; nada clínico se persiste en CacheStorage.
});
