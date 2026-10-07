/*
 * Puente app ↔ portal. La app lo inyecta en cada página del portal antes que
 * cualquier otro script (atDocumentStart, solo marco principal). Los scripts
 * que inyecta la app no pasan por la CSP de la página.
 *
 * Contrato con la web (ver assets/js/portal-pwa.js del sitio):
 *   window.HGLCApp = { plataforma: 'ios', version, llamar(accion, datos) }
 *   llamar() devuelve una Promise con la respuesta de la app.
 *   Acciones: app.info · pagina.lista · push.estado · push.activar ·
 *             push.desactivar · push.ajustes · bloqueo.estado ·
 *             bloqueo.activar · bloqueo.desactivar
 *
 * Para el portal, la app es "standalone" como la PWA instalada: no ofrece
 * "Instalar la app" y no abre ventanas nuevas.
 *
 * __VERSION__ lo reemplaza la app con su versión al cargar este archivo.
 */
(function () {
  'use strict';
  if (window.HGLCApp) return;
  var canal = window.webkit && window.webkit.messageHandlers && window.webkit.messageHandlers.hglc;
  if (!canal) return;

  function llamar(accion, datos) {
    var mensaje = { accion: String(accion) };
    if (datos) {
      for (var k in datos) {
        if (Object.prototype.hasOwnProperty.call(datos, k)) mensaje[k] = datos[k];
      }
    }
    return canal.postMessage(mensaje);
  }

  Object.defineProperty(window, 'HGLCApp', {
    value: Object.freeze({ plataforma: 'ios', version: '__VERSION__', llamar: llamar }),
    writable: false,
    configurable: false
  });

  // Mismo valor que tiene la PWA instalada desde Safari. El portal ya decide
  // con él (banners de instalación, ventanas nuevas), así que la app se porta
  // bien aunque la web publicada todavía no la conozca.
  try {
    Object.defineProperty(navigator, 'standalone', {
      configurable: true,
      get: function () { return true; }
    });
  } catch (e) {}

  // WebKit garantiza que <html> ya existe a esta altura; si no (otro motor,
  // otro tipo de documento), se vuelve a intentar al cargar el DOM.
  function marcar() {
    var raiz = document.documentElement;
    if (raiz) {
      raiz.classList.add('hglc-app');
      raiz.classList.add('pwa-standalone');
    }
  }
  marcar();

  function avisar() {
    llamar('pagina.lista', {
      ruta: location.pathname,
      sesion: !!document.querySelector('.portal-shell-app')
    }).catch(function () {});
  }

  function alCargar() {
    marcar();
    // Refuerzo por si la web publicada aún no conoce la app.
    var estilo = document.createElement('style');
    estilo.textContent = '.pwa-install,.pwa-ios,.pwa-install-cta,.pwa-install-entry{display:none!important}';
    (document.head || document.documentElement).appendChild(estilo);
    avisar();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', alCargar, { once: true });
  } else {
    alCargar();
  }
  // Al volver "atrás" a una página guardada en memoria no hay DOMContentLoaded.
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) avisar();
  });
})();
