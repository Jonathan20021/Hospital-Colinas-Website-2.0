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

    function datos(ruta) {
      return {
        v: vid,
        path: ruta,
        ref: document.referrer || '',
        title: (document.title || '').slice(0, 160),
        sw: (window.screen && screen.width) || 0,
        sh: (window.screen && screen.height) || 0,
        tz: tz,
        lang: navigator.language || ''
      };
    }

    // La ruta del proxy se deriva de la URL del propio script
    // (.../assets/js/track.js) para que tambien funcione cuando el sitio no
    // cuelga de la raiz del dominio (copias locales, staging en subcarpeta).
    var url = (function () {
      try {
        var sc = document.currentScript || document.querySelector('script[src*="assets/js/track.js"]');
        var m = sc && sc.src ? sc.src.match(/^(.*)\/assets\/js\/track\.js/) : null;
        return (m ? m[1] : '') + '/api/track.php';
      } catch (e) { return '/api/track.php'; }
    })();
    function enviar(ruta) {
      try {
        var body = JSON.stringify(datos(ruta));
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
      } catch (e) { /* nunca romper la pagina por analitica */ }
    }

    // Vistas virtuales: las paginas que cambian de pantalla SIN recargar
    // (el wizard de /agendar) avisan por aqui. La ruta debe ser fija y sin
    // datos personales: sirve para medir el embudo, no para identificar.
    window.HglcTrack = { vista: function (ruta) { enviar(String(ruta || '')); } };

    enviar(location.pathname + location.search);
  } catch (e) { /* nunca romper la página por analítica */ }
})();
