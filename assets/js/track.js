/**
 * Beacon de analítica del sitio público (Auditoría Web).
 * Envía UN "pageview" al cargar la página, vía navigator.sendBeacon (no bloquea
 * la navegación) al proxy same-origin /api/track.php. Sin cookies de terceros:
 * un id de visitante propio en localStorage para contar visitantes/sesiones.
 * Diseño a prueba de fallos: cualquier error se traga y nunca rompe la página.
 */
(function () {
  try {
    if (!navigator || (!navigator.sendBeacon && !window.fetch)) return;

    var KEY = 'hglc_vid';
    var vid = null;
    try {
      vid = localStorage.getItem(KEY);
      if (!vid) {
        vid = Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
        localStorage.setItem(KEY, vid);
      }
    } catch (e) { vid = null; }

    var tz = '';
    try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) {}

    var data = {
      v: vid,
      path: location.pathname + location.search,
      ref: document.referrer || '',
      title: (document.title || '').slice(0, 160),
      sw: (window.screen && screen.width) || 0,
      sh: (window.screen && screen.height) || 0,
      tz: tz,
      lang: navigator.language || ''
    };

    var url = '/api/track.php';
    var body = JSON.stringify(data);

    if (navigator.sendBeacon) {
      navigator.sendBeacon(url, new Blob([body], { type: 'application/json' }));
    } else {
      fetch(url, {
        method: 'POST',
        body: body,
        headers: { 'Content-Type': 'application/json' },
        keepalive: true,
        credentials: 'same-origin'
      });
    }
  } catch (e) { /* nunca romper la página por analítica */ }
})();
