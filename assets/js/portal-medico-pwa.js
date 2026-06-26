/**
 * Portal del Médico — capa PWA (Hospital General Las Colinas)
 *
 * - Registra el Service Worker (scope /portal-medico/).
 * - Flujo de actualización: avisa "Nueva versión" y recarga al confirmar.
 * - Instalación: captura beforeinstallprompt y muestra un banner propio;
 *   en iOS (sin ese evento) muestra una guía "Añadir a pantalla de inicio".
 * - Barra de estado de conexión (online/offline).
 *
 * Config inyectada por el layout en window.DM_PWA = { sw, scope, icon }.
 */
(function () {
  'use strict';

  var CFG = window.DM_PWA || {};
  var LS = {
    install: 'dmPwaInstallDismissed',
    ios: 'dmPwaIosHintDismissed',
  };
  var INSTALL_SNOOZE_DAYS = 14;

  // ── Utilidades ───────────────────────────────────────────────────
  function ls(key) { try { return localStorage.getItem(key); } catch (e) { return null; } }
  function lsSet(key, val) { try { localStorage.setItem(key, val); } catch (e) {} }
  function nowDays() { return Math.floor(Date.now() / 86400000); }
  function snoozed(key, days) {
    var v = parseInt(ls(key) || '0', 10);
    return v && (nowDays() - v) < days;
  }
  function isStandalone() {
    return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) ||
           window.navigator.standalone === true;
  }
  function isIOS() {
    return /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;
  }
  function el(tag, cls, html) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (html != null) n.innerHTML = html;
    return n;
  }

  if (isStandalone()) document.documentElement.classList.add('pwa-standalone');

  // SVGs (Phosphor-style, trazo consistente con el portal)
  var SVG = {
    download: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
    refresh: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9 9 0 0 0-6.36 2.64L3 8"/><path d="M3 3v5h5"/></svg>',
    close: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
    share: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4"/><path d="m8 8 4-4 4 4"/><path d="M20 14v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-5"/></svg>',
    plus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="4"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>',
    spark: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3v4M3 5h4M6 17v4m-2-2h4"/><path d="M13 3 16 9l6 3-6 3-3 6-3-6-6-3 6-3z"/></svg>',
    cloudOff: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2 2 20 20"/><path d="M5.78 5.77A7 7 0 0 0 8 19h9a4 4 0 0 0 1.5-7.71"/><path d="M11.5 5.04A5 5 0 0 1 18 9.66"/></svg>',
    wifi: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13a10 10 0 0 1 14 0"/><path d="M8.5 16.5a5 5 0 0 1 7 0"/><path d="M2 8.82a15 15 0 0 1 20 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>',
  };

  // ── Banner / toast genérico (parte inferior) ─────────────────────
  var dock = null;
  function getDock() {
    if (dock) return dock;
    dock = el('div', 'pwa-dock');
    document.body.appendChild(dock);
    return dock;
  }

  // ── Flujo de actualización ───────────────────────────────────────
  function showUpdate(worker) {
    if (document.querySelector('.pwa-toast')) return;
    var t = el('div', 'pwa-toast');
    t.setAttribute('role', 'status');
    t.innerHTML =
      '<span class="pwa-toast-ic">' + SVG.spark + '</span>' +
      '<div class="pwa-toast-tx"><strong>Nueva versión disponible</strong>' +
      '<span>Actualiza para obtener las últimas mejoras.</span></div>';
    var act = el('button', 'pwa-btn pwa-btn-primary', SVG.refresh + 'Actualizar');
    var dis = el('button', 'pwa-btn-icon', SVG.close);
    dis.setAttribute('aria-label', 'Descartar');
    act.addEventListener('click', function () {
      act.disabled = true;
      act.innerHTML = SVG.refresh + 'Actualizando…';
      if (worker) worker.postMessage({ type: 'SKIP_WAITING' });
    });
    dis.addEventListener('click', function () { t.remove(); });
    t.appendChild(act);
    t.appendChild(dis);
    getDock().appendChild(t);
    requestAnimationFrame(function () { t.classList.add('in'); });
  }

  var swReg = null;
  function checkUpdate() { if (swReg) { try { swReg.update(); } catch (e) {} } }

  function registerSW() {
    if (!('serviceWorker' in navigator) || !CFG.sw) return;
    var refreshing = false;
    navigator.serviceWorker.addEventListener('controllerchange', function () {
      if (refreshing) return;
      refreshing = true;
      window.location.reload();
    });

    // updateViaCache:'none' → el script del SW (y sus imports) se piden SIEMPRE
    // a la red en cada comprobación, nunca del HTTP cache: jamás un SW viejo.
    var regOpts = { updateViaCache: 'none' };
    if (CFG.scope) regOpts.scope = CFG.scope;
    navigator.serviceWorker.register(CFG.sw, regOpts)
      .then(function (reg) {
        swReg = reg;
        if (reg.waiting && navigator.serviceWorker.controller) showUpdate(reg.waiting);
        reg.addEventListener('updatefound', function () {
          var nw = reg.installing;
          if (!nw) return;
          nw.addEventListener('statechange', function () {
            if (nw.state === 'installed' && navigator.serviceWorker.controller) showUpdate(nw);
          });
        });
        // Revisa actualizaciones periódicamente mientras la app esté abierta.
        setInterval(checkUpdate, 900000);
      })
      .catch(function () { /* SW no disponible: el portal sigue funcionando normal */ });
  }

  // ── Instalación (Android/Chromium) ───────────────────────────────
  // Comprobar actualización al volver a la app (cambiar de pestaña, desbloquear,
  // reabrir el PWA) → el aviso "Nueva versión" aparece de inmediato tras un deploy.
  document.addEventListener('visibilitychange', function () { if (!document.hidden) checkUpdate(); });
  window.addEventListener('focus', checkUpdate);

  var deferredPrompt = null;
  function showInstall() {
    if (isStandalone() || document.querySelector('.pwa-install')) return;
    if (snoozed(LS.install, INSTALL_SNOOZE_DAYS)) return;
    var c = el('div', 'pwa-install');
    c.innerHTML =
      '<img class="pwa-install-ic" src="' + (CFG.icon || '') + '" alt="">' +
      '<div class="pwa-install-tx"><strong>Instala el Portal Médico</strong>' +
      '<span>Ábrelo a pantalla completa desde tu inicio.</span></div>';
    var install = el('button', 'pwa-btn pwa-btn-primary', SVG.download + 'Instalar');
    var later = el('button', 'pwa-btn-icon', SVG.close);
    later.setAttribute('aria-label', 'Ahora no');
    install.addEventListener('click', function () {
      if (!deferredPrompt) { c.remove(); return; }
      deferredPrompt.prompt();
      deferredPrompt.userChoice.then(function () {
        deferredPrompt = null;
        c.remove();
      });
    });
    later.addEventListener('click', function () {
      lsSet(LS.install, String(nowDays()));
      c.classList.remove('in');
      setTimeout(function () { c.remove(); }, 220);
    });
    c.appendChild(install);
    c.appendChild(later);
    getDock().appendChild(c);
    requestAnimationFrame(function () { c.classList.add('in'); });
  }

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredPrompt = e;
    showInstall();
  });
  window.addEventListener('appinstalled', function () {
    deferredPrompt = null;
    var c = document.querySelector('.pwa-install');
    if (c) c.remove();
    lsSet(LS.install, String(nowDays()));
  });

  // ── Guía de instalación en iOS (Safari no dispara beforeinstallprompt) ──
  function showIosHint() {
    if (!isIOS() || isStandalone()) return;
    if (snoozed(LS.ios, 30)) return;
    var c = el('div', 'pwa-install pwa-ios');
    c.innerHTML =
      '<img class="pwa-install-ic" src="' + (CFG.icon || '') + '" alt="">' +
      '<div class="pwa-install-tx"><strong>Instala el portal en tu iPhone</strong>' +
      '<span>Pulsa ' + SVG.share + ' y luego «Añadir a inicio».</span></div>';
    var ok = el('button', 'pwa-btn-icon', SVG.close);
    ok.setAttribute('aria-label', 'Entendido');
    ok.addEventListener('click', function () {
      lsSet(LS.ios, String(nowDays()));
      c.classList.remove('in');
      setTimeout(function () { c.remove(); }, 220);
    });
    c.appendChild(ok);
    getDock().appendChild(c);
    requestAnimationFrame(function () { c.classList.add('in'); });
  }

  // ── Estado de conexión ───────────────────────────────────────────
  var netBar = null, netTimer = null;
  function ensureNetBar() {
    if (netBar) return netBar;
    netBar = el('div', 'pwa-net');
    netBar.setAttribute('role', 'status');
    document.body.appendChild(netBar);
    return netBar;
  }
  function onNet() {
    var bar = ensureNetBar();
    clearTimeout(netTimer);
    if (navigator.onLine) {
      bar.className = 'pwa-net online show';
      bar.innerHTML = SVG.wifi + '<span>Conexión restablecida</span>';
      netTimer = setTimeout(function () { bar.classList.remove('show'); }, 2600);
    } else {
      bar.className = 'pwa-net offline show';
      bar.innerHTML = SVG.cloudOff + '<span>Sin conexión — modo limitado</span>';
    }
  }
  window.addEventListener('online', onNet);
  window.addEventListener('offline', onNet);

  // ── Suscripción a notificaciones push ────────────────────────────
  function urlB64ToU8(base64String) {
    var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    var raw = atob(base64);
    var arr = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
    return arr;
  }
  function pushSupported() {
    return ('serviceWorker' in navigator) && ('PushManager' in window) && ('Notification' in window);
  }
  async function getVapidKey() {
    if (!window.doctorApi) return null;
    var r = await window.doctorApi('GET', '/portal-doctor/me/push/key');
    return (r && r.ok && r.data) ? r.data.publicKey : null;
  }
  async function enablePush() {
    if (!pushSupported()) throw new Error('unsupported');
    var perm = await Notification.requestPermission();
    if (perm !== 'granted') throw new Error('denied');
    var reg = await navigator.serviceWorker.ready;
    var sub = await reg.pushManager.getSubscription();
    if (!sub) {
      var key = await getVapidKey();
      if (!key) throw new Error('no_key');
      sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlB64ToU8(key) });
    }
    var j = sub.toJSON();
    var r = await window.doctorApi('POST', '/portal-doctor/me/push/subscribe', {
      endpoint: sub.endpoint,
      p256dh: j.keys && j.keys.p256dh,
      auth: j.keys && j.keys.auth,
      ua: navigator.userAgent,
    });
    if (!r || !r.ok) throw new Error('save_failed');
    return true;
  }
  async function disablePush() {
    if (!pushSupported()) return false;
    var reg = await navigator.serviceWorker.ready;
    var sub = await reg.pushManager.getSubscription();
    if (sub) {
      try { await window.doctorApi('POST', '/portal-doctor/me/push/unsubscribe', { endpoint: sub.endpoint }); } catch (e) {}
      try { await sub.unsubscribe(); } catch (e) {}
    }
    return true;
  }
  async function pushStatus() {
    if (!pushSupported()) return { supported: false };
    var reg = await navigator.serviceWorker.ready;
    var sub = await reg.pushManager.getSubscription();
    return { supported: true, permission: Notification.permission, subscribed: !!sub };
  }
  function pushTest() { return window.doctorApi('POST', '/portal-doctor/me/push/test'); }

  window.DMPush = {
    supported: pushSupported(),
    enable: enablePush,
    disable: disablePush,
    status: pushStatus,
    test: pushTest,
  };

  // ── Arranque ─────────────────────────────────────────────────────
  function boot() {
    registerSW();
    if (!navigator.onLine) onNet();
    // La guía iOS aparece un momento después para no competir con la carga.
    if (isIOS()) setTimeout(showIosHint, 2500);
  }
  if (document.readyState === 'loading') {
    window.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
