/**
 * Teleconsulta — cliente LiveKit (Hospital General Las Colinas).
 * Lo usan la pantalla del médico y la del paciente. Maneja:
 * prueba previa de cámara/mic, conexión al SFU, render local/remoto,
 * controles (mic/cám/colgar), reconexión, fallback a solo-audio y calidad.
 *
 * Requiere el SDK livekit-client (UMD, global LivekitClient) cargado antes.
 * Uso: HGLCTele.setup({ url, token, displayName, role, onEnd });
 * El DOM debe contener los elementos con los IDs de includes/teleconsulta_stage.php.
 */
window.HGLCTele = (function () {
  'use strict';
  const LK = window.LivekitClient || window.LiveKitClient;
  const $ = (id) => document.getElementById(id);

  let room = null, previewStream = null, cfg = null;

  function status(msg, kind) {
    const b = $('tele-banner');
    if (!b) return;
    b.textContent = msg || '';
    b.className = 'tele-banner' + (kind ? ' ' + kind : '') + (msg ? ' show' : '');
  }

  async function startPreview() {
    const msg = $('tele-precall-msg');
    try {
      previewStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
      const v = $('tele-local-preview');
      if (v) { v.srcObject = previewStream; v.muted = true; await v.play().catch(() => {}); }
      const enter = $('tele-enter'); if (enter) enter.disabled = false;
      if (msg) { msg.className = 'tele-precall-msg ok'; msg.textContent = 'Cámara y micrófono listos.'; }
    } catch (e) {
      if (msg) {
        msg.className = 'tele-precall-msg err';
        msg.textContent = e && e.name === 'NotAllowedError'
          ? 'Permiso de cámara/micrófono denegado. Habilítalo en el navegador para continuar.'
          : 'No se pudo acceder a la cámara/micrófono. Puedes entrar solo con audio.';
      }
      const enter = $('tele-enter'); if (enter) { enter.disabled = false; enter.dataset.audioOnly = '1'; }
    }
  }

  function stopPreview() {
    if (previewStream) { previewStream.getTracks().forEach(t => t.stop()); previewStream = null; }
  }

  function bind() {
    room.on(LK.RoomEvent.TrackSubscribed, (track) => {
      const stage = $('tele-stage'); if (!stage) return;
      const el = track.attach();
      el.classList.add('tele-remote-media');
      if (track.kind === 'video') el.setAttribute('playsinline', '');
      stage.appendChild(el);
      const wait = $('tele-waiting'); if (wait) wait.hidden = true;
    });
    room.on(LK.RoomEvent.TrackUnsubscribed, (track) => track.detach().forEach(e => e.remove()));
    room.on(LK.RoomEvent.ParticipantConnected, () => { const w = $('tele-waiting'); if (w) w.hidden = true; });
    room.on(LK.RoomEvent.ParticipantDisconnected, () => {
      if (room.remoteParticipants && room.remoteParticipants.size === 0) { const w = $('tele-waiting'); if (w) w.hidden = false; }
    });
    room.on(LK.RoomEvent.Reconnecting, () => status('Reconectando…', 'warn'));
    room.on(LK.RoomEvent.Reconnected, () => status('Conexión restablecida', 'ok'));
    room.on(LK.RoomEvent.Disconnected, () => { status('Desconectado', 'warn'); closed(); });
    room.on(LK.RoomEvent.ConnectionQualityChanged, (q, p) => {
      if (p && p.isLocal) {
        const el = $('tele-quality'); if (!el) return;
        const map = { excellent: ['Buena', 'good'], good: ['Buena', 'good'], poor: ['Señal débil', 'poor'], lost: ['Sin señal', 'poor'] };
        const m = map[q] || ['', ''];
        el.textContent = m[0]; el.className = 'tele-quality ' + m[1];
      }
    });
  }

  async function join() {
    const enter = $('tele-enter');
    const audioOnly = enter && enter.dataset.audioOnly === '1';
    stopPreview();
    if ($('tele-precall')) $('tele-precall').hidden = true;
    if ($('tele-room')) $('tele-room').hidden = false;
    status('Conectando…', '');

    room = new LK.Room({ adaptiveStream: true, dynacast: true });
    bind();
    try {
      await room.connect(cfg.url, cfg.token);
    } catch (e) {
      status('No se pudo conectar. Revisa tu internet e intenta de nuevo.', 'warn');
      return;
    }
    status('', '');
    try {
      await room.localParticipant.setMicrophoneEnabled(true);
      if (!audioOnly) await room.localParticipant.setCameraEnabled(true);
    } catch (e) {
      try { await room.localParticipant.setMicrophoneEnabled(true); } catch (_) {}
      status('Cámara no disponible: continúas solo con audio.', 'warn');
    }
    // local pip
    try {
      const pub = room.localParticipant.getTrackPublication(LK.Track.Source.Camera);
      const pip = $('tele-localpip');
      if (pub && pub.track && pip) { pub.track.attach(pip); pip.muted = true; }
    } catch (e) {}
    refreshControls();
  }

  function refreshControls() {
    const mic = $('tele-mic'), cam = $('tele-cam');
    if (mic && room) mic.classList.toggle('off', !room.localParticipant.isMicrophoneEnabled);
    if (cam && room) cam.classList.toggle('off', !room.localParticipant.isCameraEnabled);
  }

  function closed() {
    const r = $('tele-room'), c = $('tele-closed');
    if (r) r.hidden = true;
    if (c) c.hidden = false;
    if (cfg && typeof cfg.onEnd === 'function') cfg.onEnd();
  }

  function wireControls() {
    $('tele-mic') && $('tele-mic').addEventListener('click', async () => {
      if (!room) return; await room.localParticipant.setMicrophoneEnabled(!room.localParticipant.isMicrophoneEnabled); refreshControls();
    });
    $('tele-cam') && $('tele-cam').addEventListener('click', async () => {
      if (!room) return; await room.localParticipant.setCameraEnabled(!room.localParticipant.isCameraEnabled);
      const pub = room.localParticipant.getTrackPublication(LK.Track.Source.Camera);
      const pip = $('tele-localpip'); if (pub && pub.track && pip) pub.track.attach(pip);
      refreshControls();
    });
    $('tele-end') && $('tele-end').addEventListener('click', async () => {
      if (!confirm('¿Finalizar la teleconsulta?')) return;
      try { if (room) await room.disconnect(); } catch (e) {}
      closed();
    });
    $('tele-enter') && $('tele-enter').addEventListener('click', join);
  }

  function setup(config) {
    cfg = config || {};
    if (!LK) { status('No se pudo cargar el módulo de video.', 'warn'); return; }
    if (!cfg.url || !cfg.token) { status('Teleconsulta no disponible.', 'warn'); return; }
    wireControls();
    startPreview();
    window.addEventListener('beforeunload', () => { try { if (room) room.disconnect(); } catch (e) {} stopPreview(); });
  }

  return { setup };
})();
