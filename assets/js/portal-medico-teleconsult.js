/**
 * Teleconsulta — cliente LiveKit (Hospital General Las Colinas).
 * Adquiere cámara/micrófono UNA sola vez (createLocalTracks) para la prueba
 * previa y publica ESAS MISMAS pistas al entrar (sin re-adquirir → evita el
 * fallo "cámara en uso"). Maneja reconexión, fallback a audio, calidad y temporizador.
 *
 * Requiere livekit-client (UMD, global LivekitClient) cargado antes.
 * Uso: HGLCTele.setup({ url, token, role });
 */
window.HGLCTele = (function () {
  'use strict';
  const LK = window.LivekitClient || window.LiveKitClient;
  const $ = (id) => document.getElementById(id);

  let room = null, cfg = null, localTracks = [], audioTrack = null, videoTrack = null, timer = null, t0 = 0;

  function status(msg, kind) {
    const b = $('tele-banner'); if (!b) return;
    b.textContent = msg || '';
    b.className = 'tele-banner' + (kind ? ' ' + kind : '') + (msg ? ' show' : '');
  }

  function tick() {
    const el = $('tele-timer'); if (!el) return;
    const s = Math.floor((Date.now() - t0) / 1000);
    el.textContent = String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
  }

  async function startPreview() {
    const msg = $('tele-precall-msg'), enter = $('tele-enter');
    try {
      const vopt = (LK.VideoPresets && LK.VideoPresets.h540) ? { resolution: LK.VideoPresets.h540.resolution } : true;
      localTracks = await LK.createLocalTracks({ audio: true, video: vopt });
      audioTrack = localTracks.find(t => t.kind === 'audio') || null;
      videoTrack = localTracks.find(t => t.kind === 'video') || null;
      if (videoTrack && $('tele-local-preview')) videoTrack.attach($('tele-local-preview'));
      if (enter) enter.disabled = false;
      if (msg) { msg.className = 'tele-precall-msg ok'; msg.textContent = videoTrack ? 'Cámara y micrófono listos.' : 'Micrófono listo (sin cámara).'; }
    } catch (e) {
      try { localTracks = await LK.createLocalTracks({ audio: true, video: false }); audioTrack = localTracks[0] || null; } catch (_) { localTracks = []; }
      if (enter) { enter.disabled = false; enter.dataset.audioOnly = '1'; }
      if (msg) {
        msg.className = 'tele-precall-msg err';
        msg.textContent = (e && e.name === 'NotAllowedError')
          ? 'Permiso de cámara/micrófono denegado. Actívalo en el ícono de la barra de direcciones y recarga.'
          : (e && e.name === 'NotReadableError')
            ? 'La cámara está en uso por otra app. Ciérrala y recarga, o entra solo con audio.'
            : 'No se pudo acceder a la cámara. Puedes entrar solo con audio.';
      }
    }
  }

  function bind() {
    room.on(LK.RoomEvent.TrackSubscribed, (track) => {
      const stage = $('tele-stage'); if (!stage) return;
      const el = track.attach();
      el.classList.add('tele-remote-media');
      if (track.kind === 'video') el.setAttribute('playsinline', '');
      stage.appendChild(el);
      const w = $('tele-waiting'); if (w) w.hidden = true;
    });
    room.on(LK.RoomEvent.TrackUnsubscribed, (track) => track.detach().forEach(e => e.remove()));
    room.on(LK.RoomEvent.ParticipantConnected, () => { const w = $('tele-waiting'); if (w) w.hidden = true; });
    room.on(LK.RoomEvent.ParticipantDisconnected, () => {
      if (!room.remoteParticipants || room.remoteParticipants.size === 0) { const w = $('tele-waiting'); if (w) w.hidden = false; }
    });
    room.on(LK.RoomEvent.Reconnecting, () => status('Reconectando…', 'warn'));
    room.on(LK.RoomEvent.Reconnected, () => status('Conexión restablecida', 'ok'));
    room.on(LK.RoomEvent.Disconnected, () => { status('Llamada finalizada', 'warn'); closed(); });
    room.on(LK.RoomEvent.ConnectionQualityChanged, (q, p) => {
      if (!p || !p.isLocal) return;
      const el = $('tele-quality'); if (!el) return;
      const m = { excellent: ['Buena', 'good'], good: ['Buena', 'good'], poor: ['Señal débil', 'poor'], lost: ['Sin señal', 'poor'] }[q] || ['', ''];
      el.textContent = m[0]; el.className = 'tele-quality ' + m[1];
    });
  }

  async function join() {
    if ($('tele-precall')) $('tele-precall').hidden = true;
    if ($('tele-room')) $('tele-room').hidden = false;
    status('Conectando…', '');
    room = new LK.Room({ adaptiveStream: true, dynacast: true });
    bind();
    try {
      await room.connect(cfg.url, cfg.token);
    } catch (e) {
      status('No se pudo conectar. Revisa tu internet e inténtalo de nuevo.', 'warn');
      if ($('tele-precall')) $('tele-precall').hidden = false;
      if ($('tele-room')) $('tele-room').hidden = true;
      return;
    }
    status('', '');
    for (const t of localTracks) { try { await room.localParticipant.publishTrack(t); } catch (e) {} }
    const pip = $('tele-localpip');
    if (videoTrack && pip) { try { videoTrack.attach(pip); pip.style.display = ''; } catch (e) {} }
    else if (pip) pip.style.display = 'none';
    t0 = Date.now(); tick(); timer = setInterval(tick, 1000);
    refreshControls();
  }

  function refreshControls() {
    const mic = $('tele-mic'), cam = $('tele-cam'), pip = $('tele-localpip');
    if (mic) mic.classList.toggle('off', !audioTrack || audioTrack.isMuted);
    if (cam) cam.classList.toggle('off', !videoTrack || videoTrack.isMuted);
    if (pip) pip.style.display = (videoTrack && !videoTrack.isMuted) ? '' : 'none';
  }

  function closed() {
    if (timer) { clearInterval(timer); timer = null; }
    const r = $('tele-room'), c = $('tele-closed'); if (r) r.hidden = true; if (c) c.hidden = false;
    if (cfg && typeof cfg.onEnd === 'function') cfg.onEnd();
  }

  function wire() {
    $('tele-enter') && $('tele-enter').addEventListener('click', join);
    $('tele-mic') && $('tele-mic').addEventListener('click', async () => {
      if (!audioTrack) return;
      try { audioTrack.isMuted ? await audioTrack.unmute() : await audioTrack.mute(); } catch (e) {}
      refreshControls();
    });
    $('tele-cam') && $('tele-cam').addEventListener('click', async () => {
      if (!videoTrack) return;
      try { videoTrack.isMuted ? await videoTrack.unmute() : await videoTrack.mute(); } catch (e) {}
      refreshControls();
    });
    $('tele-end') && $('tele-end').addEventListener('click', async () => {
      if (!confirm('¿Finalizar la teleconsulta?')) return;
      try { if (room) await room.disconnect(); } catch (e) {}
      closed();
    });
  }

  function setup(config) {
    cfg = config || {};
    if (!LK) { status('No se pudo cargar el módulo de video.', 'warn'); return; }
    if (!cfg.url || !cfg.token) { status('Teleconsulta no disponible.', 'warn'); return; }
    wire();
    startPreview();
    window.addEventListener('beforeunload', () => {
      try { if (room) room.disconnect(); } catch (e) {}
      localTracks.forEach(t => { try { t.stop(); } catch (e) {} });
    });
  }

  return { setup };
})();
