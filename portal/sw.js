/*
 * Service Worker — Portal del Paciente · Hospital General Las Colinas
 * Scope: /portal/  (se sirve desde esta misma carpeta).
 *
 * PRINCIPIO DE PRIVACIDAD (datos clínicos / PHI):
 *   - Las navegaciones a páginas del portal (que pueden contener datos del
 *     paciente) usan NETWORK-FIRST y NO se almacenan en caché. Si no hay red,
 *     se muestra una página "Sin conexión" estática (sin datos).
 *   - El proxy del API (/api/portal-proxy.php) y los PDF/binarios quedan
 *     EXCLUIDOS siempre: ningún dato clínico se persiste en CacheStorage.
 *   - Solo se cachean estáticos sin PHI: íconos, manifest, página offline y
 *     las librerías/CSS/JS versionados del sitio (?v=mtime).
 *
 * Actualizaciones: install precachea y espera; el cliente decide cuándo activar
 * (toast "Nueva versión"), enviando {type:'SKIP_WAITING'}.
 */
'use strict';

const VERSION  = 'hglc-paciente-v2';
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

/** Solo estáticos propios del portal del paciente son cacheables (jamás PHI). */
function isCacheableStatic(url) {
  return /\/portal\/(icons\/|manifest\.webmanifest$|offline\.html$)/.test(url.pathname);
}

/**
 * Estáticos versionados del sitio (?v=mtime), SIN PHI: CSS/JS del portal,
 * fuentes, logos, librerías. Se cachean cache-first porque cambian de URL al
 * cambiar el archivo (auto-bust). NUNCA cachear nada bajo /api/ (proxy del
 * API, PDF de recetas/lab y DICOM del PACS = PHI).
 */
function isStaticAsset(url) {
  if (url.pathname.indexOf('/api/') !== -1) return false;
  return /\/assets\/(vendor|css|js|site|fonts)\/.+\.(js|css|png|jpe?g|svg|webp|woff2?|ttf|ico|gif)$/i.test(url.pathname);
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
      keys.filter((k) => k.startsWith('hglc-paciente-') && k.indexOf(VERSION) !== 0)
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
  if (req.method !== 'GET') return;                // nunca tocar POST/PUT/DELETE
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return; // solo mismo origen

  // 1) Navegaciones (HTML, posibles datos del paciente): network-first, sin cachear.
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

  // 3) Librerías/estáticos versionados del sitio (sin PHI): cache-first.
  //    El ?v=mtime hace que un archivo nuevo sea otra URL → se refresca solo.
  if (isStaticAsset(url)) {
    event.respondWith((async () => {
      const cache = await caches.open(RUNTIME);
      const hit = await cache.match(req);
      if (hit) return hit;
      try {
        const res = await fetch(req);
        if (res && res.status === 200 && (res.type === 'basic' || res.type === 'cors')) {
          // Anti-acumulación: borra versiones anteriores (?v viejo) del MISMO
          // archivo antes de guardar la nueva, para que la caché no crezca sin fin.
          const keys = await cache.keys();
          await Promise.all(keys.map(async (k) => {
            const ku = new URL(k.url);
            if (ku.origin === url.origin && ku.pathname === url.pathname && ku.search !== url.search) {
              await cache.delete(k);
            }
          }));
          await cache.put(req, res.clone());
        }
        return res;
      } catch (e) {
        return hit || new Response('', { status: 504 });
      }
    })());
    return;
  }

  // 4) Cualquier otra petición in-scope (incluido /api/ y los PDF): red directa,
  //    sin caché. Nada clínico se persiste jamás en CacheStorage.
});

// ── Notificaciones push (infraestructura lista; emisor server-side = fase 2) ──
self.addEventListener('push', (event) => {
  let data = {};
  try { data = event.data ? event.data.json() : {}; }
  catch (e) { data = { body: event.data ? event.data.text() : '' }; }

  const title = data.title || 'Hospital Las Colinas';
  const options = {
    body:     data.body || '',
    icon:     new URL('icons/icon-192.png', self.location).toString(),
    badge:    new URL('icons/favicon-32.png', self.location).toString(),
    tag:      data.tag || 'hglc-paciente',
    renotify: true,
    data:     { url: data.url || './' },
    vibrate:  [80, 40, 80],
    lang:     'es',
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = (event.notification.data && event.notification.data.url) || './';
  const url = new URL(target, self.location).toString();
  event.waitUntil((async () => {
    const wins = await clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const c of wins) {
      if (c.url.indexOf('/portal/') !== -1 && c.url.indexOf('/portal-medico/') === -1 && 'focus' in c) {
        try { await c.navigate(url); } catch (e) {}
        return c.focus();
      }
    }
    if (clients.openWindow) return clients.openWindow(url);
  })());
});
