/* =============================================================================
   Visor de imágenes — módulo MPR (reconstrucción multiplanar 3D del paciente).

   Reconstruye las vistas axial / coronal / sagital a partir del VOLUMEN real
   de una serie TC/RM volumétrica de Autana, sin librerías nuevas: reutiliza el
   motor del visor (window.HGV → cornerstone) SOLO para decodificar cada corte
   a píxeles; apila el volumen en memoria (Int16Array) y reconstruye los planos
   en <canvas> propios, con crosshairs sincronizados estilo 3D Slicer.

   Incluye un render 3D (WebGL2 ray casting: MIP y volume rendering), carga
   PROGRESIVA (el volumen se ve construirse, interacción inmediata) y CACHÉ en
   memoria por serie (re-entrar es instantáneo; nada se persiste en disco → sin
   PHI en caché). Se monta ENCIMA del visor base (igual que el módulo "pro"):
   si este archivo falla, el visor sigue funcionando. 100% client-side.
   ============================================================================= */
(function () {
  'use strict';

  var MIN_SLICES = 24;            // mínimo de cortes para que el MPR sea útil
  var MAX_VOXELS = 170 * 1000000; // ~340 MB en Int16; por encima → submuestreo de cortes
  var CONCURRENCY = 8;            // descargas simultáneas de cortes
  var COL = { ax: '#ff5a5a', co: '#5dd55d', sa: '#ffd23f' };  // colores por plano

  function boot() {
    var HGV = window.HGV;
    if (!HGV || HGV.__mprInit) return;
    HGV.__mprInit = true;

    var cs = HGV.cornerstone, el = HGV.el, esc = HGV.escH;
    var dj = HGV.dj, tagF = HGV.tag, tag1 = HGV.tag1, ROOT = HGV.ROOT, STUDY = HGV.STUDY;
    var toolbar = document.querySelector('.v-toolbar');
    var stage = document.querySelector('.v-stage');
    if (!toolbar || !stage || !cs) return;

    function mk(t, c, h) { var e = document.createElement(t); if (c) e.className = c; if (h != null) e.innerHTML = h; return e; }

    /* ── estado de la serie actual (proviene del evento hgv:series) ──────── */
    var curSeries = { uid: null, mod: '', desc: '', count: 0 };
    el.addEventListener('hgv:series', function (e) {
      var d = e.detail || {};
      curSeries = { uid: d.seriesUID || null, mod: (d.mod || '').toUpperCase(), desc: d.desc || '', count: d.count || 0 };
      updateBtn();
    });

    /* ── botón en la toolbar ─────────────────────────────────────────────── */
    var bMpr = mk('button', 'v-tool hgv-pro mpr-btn', '🧊 3D/MPR');
    bMpr.id = 't-mpr'; bMpr.type = 'button';
    var ref = document.getElementById('t-compare') || document.getElementById('t-fs');
    if (ref) ref.insertAdjacentElement('afterend', bMpr); else toolbar.appendChild(bMpr);

    function canMpr() { return curSeries.uid && curSeries.count >= MIN_SLICES && /^(CT|MR|PT)$/.test(curSeries.mod); }
    function updateBtn() {
      bMpr.disabled = !canMpr() && !mprOn;
      bMpr.title = canMpr()
        ? ('Reconstrucción multiplanar — ' + curSeries.count + ' cortes (' + curSeries.mod + ')')
        : ('MPR disponible solo en series volumétricas (TC/RM/PET, ≥ ' + MIN_SLICES + ' cortes)');
    }

    /* ── barra de controles MPR (oculta hasta entrar) ────────────────────── */
    var bar = mk('div', 'mpr-bar'); bar.id = 'mpr-bar';
    bar.innerHTML =
      '<div class="seg" id="mpr-mode">'
      + '<button type="button" data-m="nav" class="on" title="Clic / arrastre = mover el punto de cruce">✛ Navegar</button>'
      + '<button type="button" data-m="wl" title="Arrastre = brillo / contraste">◐ Ventana</button>'
      + '</div>'
      + '<div class="seg" id="mpr-lay">'
      + '<button type="button" data-l="3" class="on" title="Cuadrícula 2×2 (3 vistas + 3D)">⊞ 2×2</button>'
      + '<button type="button" data-l="row" title="Fila de 3 vistas">▭▭▭ Fila</button>'
      + '</div>'
      + '<button type="button" class="v-tool active" id="mpr-cross" title="Mostrar / ocultar las líneas de cruce">✛ Cruz</button>'
      + '<div class="v-dd" id="mpr-preset-dd">'
      + '<button type="button" class="v-tool" id="mpr-preset" title="Preajustes de ventana (W/L)">🎚 Ventana ▾</button>'
      + '<div class="v-dd-menu" id="mpr-preset-menu">'
      + '<button data-ww="" data-wc="">Auto (del volumen)</button>'
      + '<div class="lbl">Tomografía (TC)</div>'
      + '<button data-ww="1500" data-wc="-600">Pulmón</button>'
      + '<button data-ww="350" data-wc="40">Tejidos blandos</button>'
      + '<button data-ww="2000" data-wc="400">Hueso</button>'
      + '<button data-ww="80" data-wc="40">Cerebro</button>'
      + '<button data-ww="400" data-wc="40">Abdomen</button>'
      + '</div>'
      + '</div>'
      + '<span class="mpr-wl" id="mpr-wl"></span>'
      + '<span class="mpr-spacer"></span>'
      + '<button type="button" class="v-tool" id="mpr-fs" title="Pantalla completa (toda la cuadrícula)">⛶ Pantalla completa</button>'
      + '<button type="button" class="v-tool" id="mpr-exit" title="Volver al visor 2D">✕ Salir de MPR</button>';
    toolbar.appendChild(bar);

    /* ── estado del volumen / vistas ─────────────────────────────────────── */
    var mprOn = false, abort = false;
    var vol = null;                                    // Int16Array W*H*D
    var dim = { W: 0, H: 0, D: 0 };
    var geom = { colSp: 1, rowSp: 1, slSp: 1 };        // espaciado físico (mm)
    var rescale = { slope: 1, intercept: 0 };
    var modality = '', invert = false;
    var cross = { x: 0, y: 0, z: 0 };                  // índices de voxel (centro de cruce)
    var wl = { wc: 40, ww: 400 };
    var showCross = true, layout = '3', mode = 'nav', maxed = null;
    var views = {};                                    // ax/co/sa → objeto de vista
    var mprStage = null, loadEl = null;
    // Caché en MEMORIA por serie (re-entrar = instantáneo). NO se persiste en disco → sin PHI en caché.
    var volCache = {}, cacheOrder = [], loadGen = 0;
    // Estado del render 3D (WebGL2 ray casting); ver bloque "VOLUMEN 3D" más abajo.
    var G = { gl: null, on: false, rot: { yaw: 0.6, pitch: -0.45 }, scale: 1.0, mode: 'vr', presetKey: '', thr: 0.5, presets: [], refineT: 0, uSize: [0.9, 0.9, 0.9], tex: null };

    /* ── construcción de una vista ───────────────────────────────────────── */
    function buildView(key, label) {
      var v = mk('div', 'mpr-view ' + key);
      var cv = mk('canvas');
      var lbl = mk('div', 'lbl', '<span class="dot"></span>' + label);
      var pos = mk('div', 'pos');
      var hu = mk('div', 'hu');
      var maxBtn = mk('button', 'vmax', '⛶'); maxBtn.type = 'button'; maxBtn.title = 'Pantalla completa';
      var slc = mk('div', 'slc', '<input type="range" min="0" max="1" value="0" aria-label="Corte">');
      v.appendChild(cv); v.appendChild(lbl); v.appendChild(pos); v.appendChild(hu); v.appendChild(maxBtn); v.appendChild(slc);
      var o = {
        wrap: v, canvas: cv, ctx: cv.getContext('2d'),
        off: document.createElement('canvas'), pos: pos, hu: hu, slider: slc.querySelector('input')
      };
      o.offctx = o.off.getContext('2d');
      views[key] = o;
      attachView(key, o);
      maxBtn.addEventListener('click', function (ev) { ev.stopPropagation(); fsView(key); });   // ⛶ = pantalla completa de esta vista
      cv.addEventListener('dblclick', function () { toggleMax(key); });                          // doble-clic = maximizar en la rejilla
      o.slider.addEventListener('input', function () { setSlicePos(key, parseInt(o.slider.value, 10)); });
      return v;
    }

    /* ── interacción por vista (navegar / ventana / rueda / táctil) ───────
       Los listeners de movimiento/soltar viven en window UNA sola vez (abajo). */
    var drag = null;
    function relOf(o, ev) {
      var r = o.canvas.getBoundingClientRect(), p = (ev.touches && ev.touches[0]) ? ev.touches[0] : ev;
      return { x: (p.clientX - r.left) / r.width, y: (p.clientY - r.top) / r.height };
    }
    function attachView(key, o) {
      function down(ev) {
        if (!vol) return;
        ev.preventDefault();
        if (mode === 'wl') { var p = (ev.touches && ev.touches[0]) ? ev.touches[0] : ev; drag = { key: key, o: o, wlStart: { x: p.clientX, y: p.clientY, wc: wl.wc, ww: wl.ww } }; }
        else { drag = { key: key, o: o, wlStart: null }; var q = relOf(o, ev); setCrossFrom(key, q.x, q.y); }
      }
      o.canvas.addEventListener('mousedown', down);
      o.canvas.addEventListener('touchstart', down, { passive: false });
      o.wrap.addEventListener('wheel', function (ev) { if (!vol) return; ev.preventDefault(); stepSlice(key, ev.deltaY > 0 ? 1 : -1); }, { passive: false });
    }
    function gmove(ev) {
      if (!drag || !vol) return;
      var p = (ev.touches && ev.touches[0]) ? ev.touches[0] : ev;
      if (drag.wlStart) {
        if (ev.cancelable) ev.preventDefault();
        wl.ww = Math.max(1, drag.wlStart.ww + (p.clientX - drag.wlStart.x) * 2);
        wl.wc = drag.wlStart.wc + (drag.wlStart.y - p.clientY) * 2;
        updateWlChip(); renderAll();
      } else {
        if (ev.cancelable) ev.preventDefault();
        var q = relOf(drag.o, ev); setCrossFrom(drag.key, q.x, q.y);
      }
    }
    function gup() { drag = null; }
    window.addEventListener('mousemove', gmove);
    window.addEventListener('mouseup', gup);
    window.addEventListener('touchmove', gmove, { passive: false });
    window.addEventListener('touchend', gup);

    function clampI(v, n) { v = Math.floor(v); return v < 0 ? 0 : (v >= n ? n - 1 : v); }
    function clampStep(v, n) { return v < 0 ? 0 : (v >= n ? n - 1 : v); }

    function setCrossFrom(key, fx, fy) {
      fx = fx < 0 ? 0 : (fx > 1 ? 1 : fx); fy = fy < 0 ? 0 : (fy > 1 ? 1 : fy);
      if (key === 'ax') { cross.x = clampI(fx * dim.W, dim.W); cross.y = clampI(fy * dim.H, dim.H); }
      else if (key === 'co') { cross.x = clampI(fx * dim.W, dim.W); cross.z = clampI((1 - fy) * dim.D, dim.D); }
      else { cross.y = clampI(fx * dim.H, dim.H); cross.z = clampI((1 - fy) * dim.D, dim.D); }
      renderAll();
    }
    function stepSlice(key, d) {
      if (key === 'ax') cross.z = clampStep(cross.z + d, dim.D);
      else if (key === 'co') cross.y = clampStep(cross.y + d, dim.H);
      else cross.x = clampStep(cross.x + d, dim.W);
      renderAll();
    }
    function setSlicePos(key, v) {
      if (key === 'ax') cross.z = clampStep(v, dim.D);
      else if (key === 'co') cross.y = clampStep(v, dim.H);
      else cross.x = clampStep(v, dim.W);
      renderAll();
    }

    /* ── extracción de un plano (con W/L → RGBA) ─────────────────────────── */
    function buildPlane(key) {
      var W = dim.W, H = dim.H, D = dim.D, sl = rescale.slope, ic = rescale.intercept;
      var lo = wl.wc - wl.ww / 2, span = wl.ww || 1, inv = invert;
      var pw, ph, src, i, g, o, z, x, yy, base;
      if (key === 'ax') {
        pw = W; ph = H; src = new Uint8ClampedArray(pw * ph * 4);
        base = cross.z * W * H;
        for (i = 0; i < W * H; i++) {
          g = ((vol[base + i] * sl + ic - lo) / span) * 255; g = g < 0 ? 0 : (g > 255 ? 255 : g); if (inv) g = 255 - g;
          o = i * 4; src[o] = src[o + 1] = src[o + 2] = g; src[o + 3] = 255;
        }
      } else if (key === 'co') {
        pw = W; ph = D; src = new Uint8ClampedArray(pw * ph * 4);
        var y = cross.y;
        for (z = 0; z < D; z++) {
          var orow = (D - 1 - z) * W, vb = z * W * H + y * W;
          for (x = 0; x < W; x++) {
            g = ((vol[vb + x] * sl + ic - lo) / span) * 255; g = g < 0 ? 0 : (g > 255 ? 255 : g); if (inv) g = 255 - g;
            o = (orow + x) * 4; src[o] = src[o + 1] = src[o + 2] = g; src[o + 3] = 255;
          }
        }
      } else { // sa
        pw = H; ph = D; src = new Uint8ClampedArray(pw * ph * 4);
        var x0 = cross.x;
        for (z = 0; z < D; z++) {
          var orow2 = (D - 1 - z) * H, vz = z * W * H;
          for (yy = 0; yy < H; yy++) {
            g = ((vol[vz + yy * W + x0] * sl + ic - lo) / span) * 255; g = g < 0 ? 0 : (g > 255 ? 255 : g); if (inv) g = 255 - g;
            o = (orow2 + yy) * 4; src[o] = src[o + 1] = src[o + 2] = g; src[o + 3] = 255;
          }
        }
      }
      return { pw: pw, ph: ph, data: src };
    }
    function physSize(key) {
      if (key === 'ax') return { w: dim.W * geom.colSp, h: dim.H * geom.rowSp };
      if (key === 'co') return { w: dim.W * geom.colSp, h: dim.D * geom.slSp };
      return { w: dim.H * geom.rowSp, h: dim.D * geom.slSp };
    }

    function renderView(key) {
      var o = views[key]; if (!o || !vol) return;
      var p = buildPlane(key);
      o.off.width = p.pw; o.off.height = p.ph;
      var id = o.offctx.createImageData(p.pw, p.ph); id.data.set(p.data); o.offctx.putImageData(id, 0, 0);
      var box = o.wrap.getBoundingClientRect();
      if (box.width < 4 || box.height < 4) return;
      var ph_ = physSize(key), ar = (ph_.w / ph_.h) || 1;
      var dw, dh;
      if (box.width / box.height > ar) { dh = box.height; dw = dh * ar; } else { dw = box.width; dh = dw / ar; }
      var dpr = Math.min(2, window.devicePixelRatio || 1);
      o.canvas.width = Math.max(1, Math.round(dw * dpr)); o.canvas.height = Math.max(1, Math.round(dh * dpr));
      o.canvas.style.width = Math.round(dw) + 'px'; o.canvas.style.height = Math.round(dh) + 'px';
      var ctx = o.ctx;
      ctx.imageSmoothingEnabled = true; ctx.imageSmoothingQuality = 'high';
      ctx.clearRect(0, 0, o.canvas.width, o.canvas.height);
      ctx.drawImage(o.off, 0, 0, p.pw, p.ph, 0, 0, o.canvas.width, o.canvas.height);
      if (showCross) drawCross(key, ctx, o.canvas.width, o.canvas.height);
      updateViewLabels(key);
    }
    function lineSeg(ctx, x1, y1, x2, y2, col) {
      ctx.strokeStyle = col; ctx.lineWidth = Math.max(1, (window.devicePixelRatio || 1));
      ctx.globalAlpha = 0.9; ctx.beginPath(); ctx.moveTo(x1, y1); ctx.lineTo(x2, y2); ctx.stroke(); ctx.globalAlpha = 1;
    }
    function drawCross(key, ctx, cw, ch) {
      var W = dim.W, H = dim.H, D = dim.D, vx, hy;
      if (key === 'ax') {
        vx = (cross.x + 0.5) / W * cw; hy = (cross.y + 0.5) / H * ch;
        lineSeg(ctx, vx, 0, vx, ch, COL.sa); lineSeg(ctx, 0, hy, cw, hy, COL.co);
      } else if (key === 'co') {
        vx = (cross.x + 0.5) / W * cw; hy = (D - 1 - cross.z + 0.5) / D * ch;
        lineSeg(ctx, vx, 0, vx, ch, COL.sa); lineSeg(ctx, 0, hy, cw, hy, COL.ax);
      } else {
        vx = (cross.y + 0.5) / H * cw; hy = (D - 1 - cross.z + 0.5) / D * ch;
        lineSeg(ctx, vx, 0, vx, ch, COL.co); lineSeg(ctx, 0, hy, cw, hy, COL.ax);
      }
    }
    function updateViewLabels(key) {
      var o = views[key]; if (!o) return;
      var idx, tot;
      if (key === 'ax') { idx = cross.z + 1; tot = dim.D; }
      else if (key === 'co') { idx = cross.y + 1; tot = dim.H; }
      else { idx = cross.x + 1; tot = dim.W; }
      o.pos.textContent = idx + ' / ' + tot;
      var raw = vol[cross.z * dim.W * dim.H + cross.y * dim.W + cross.x];
      var val = Math.round(raw * rescale.slope + rescale.intercept);
      o.hu.textContent = (modality === 'CT' ? (val + ' HU') : ('valor ' + val));
    }

    function renderAll() {
      ['ax', 'co', 'sa'].forEach(function (k) { if (views[k] && (!maxed || maxed === k)) renderView(k); });
      syncSliders();
    }
    function syncSliders() {
      if (views.ax) { views.ax.slider.max = dim.D - 1; views.ax.slider.value = cross.z; }
      if (views.co) { views.co.slider.max = dim.H - 1; views.co.slider.value = cross.y; }
      if (views.sa) { views.sa.slider.max = dim.W - 1; views.sa.slider.value = cross.x; }
    }

    function toggleMax(key) {
      maxed = (maxed === key) ? null : key;
      ['ax', 'co', 'sa'].forEach(function (k) { if (views[k]) views[k].wrap.classList.toggle('max', maxed === k); });
      if (mprStage) mprStage.classList.toggle('maxed', !!maxed);
      setTimeout(renderAll, 30);
      if (G.on && !maxed && layout !== 'row') setTimeout(function () { onResize3D(); render3D(360); }, 50);
    }

    /* ── pantalla completa (de una vista o de toda la cuadrícula) ────────── */
    function fsEl() { return document.fullscreenElement || document.webkitFullscreenElement || null; }
    function fsView(key) {
      var wrap = (key === 'v3') ? G.wrap : (views[key] && views[key].wrap);
      if (!wrap) return;
      if (!fsEl()) { var rq = wrap.requestFullscreen || wrap.webkitRequestFullscreen; if (rq) try { rq.call(wrap); } catch (e) {} }
      else { var ex = document.exitFullscreen || document.webkitExitFullscreen; if (ex) try { ex.call(document); } catch (e) {} }
    }
    function fsStage() {
      var t = document.documentElement;
      if (!fsEl()) { var rq = t.requestFullscreen || t.webkitRequestFullscreen; if (rq) try { rq.call(t); } catch (e) {} }
      else { var ex = document.exitFullscreen || document.webkitExitFullscreen; if (ex) try { ex.call(document); } catch (e) {} }
    }

    /* ── ventana (W/L) ───────────────────────────────────────────────────── */
    function autoWL() {
      var W = dim.W, H = dim.H, base = (dim.D >> 1) * W * H, n = W * H, step = Math.max(1, Math.floor(n / 20000));
      var vals = [];
      for (var i = 0; i < n; i += step) vals.push(vol[base + i] * rescale.slope + rescale.intercept);
      vals.sort(function (a, b) { return a - b; });
      var lo = vals[Math.floor(vals.length * 0.02)] || 0, hi = vals[Math.floor(vals.length * 0.98)] || 1;
      return { wc: (hi + lo) / 2, ww: Math.max(1, hi - lo) };
    }
    function initWL() { wl = (modality === 'CT') ? { wc: 40, ww: 400 } : autoWL(); updateWlChip(); }
    function updateWlChip() { var c = document.getElementById('mpr-wl'); if (c) c.innerHTML = 'W/L <b>' + Math.round(wl.ww) + ' / ' + Math.round(wl.wc) + '</b>'; }

    /* ── progreso (overlay de arranque + chip no bloqueante) ─────────────── */
    function setProg(frac, txt) {
      var pct = Math.round(frac * 100);
      var b = document.getElementById('mpr-load-bar'), p = document.getElementById('mpr-load-pct'), s = document.getElementById('mpr-load-sub');
      if (b) b.style.width = pct + '%';
      if (p) p.textContent = pct + '%';
      if (txt && s) s.textContent = 'Cargando cortes… ' + txt;
      var cp = document.getElementById('mpr-prog-t'); if (cp) cp.innerHTML = 'Cargando volumen <b>' + pct + '%</b> · ya puedes explorar';
      var pi = document.getElementById('mpr-prog-i'); if (pi) pi.style.width = pct + '%';
    }
    function showOverlay(show) { if (loadEl) loadEl.classList.toggle('hide', !show); }
    function showChip(show) { var c = document.getElementById('mpr-prog'); if (c) c.classList.toggle('show', !!show); }
    function setErr(msg) {
      if (!loadEl) return;
      loadEl.classList.remove('hide');
      loadEl.innerHTML = '<div class="ic">⚠</div><div class="ttl">No se pudo reconstruir</div>'
        + '<div class="err">' + esc(msg) + '</div>'
        + '<button type="button" class="cancel" id="mpr-err-close">Volver al visor</button>';
      var b = document.getElementById('mpr-err-close'); if (b) b.addEventListener('click', exitMpr);
    }

    /* ── caché en memoria ────────────────────────────────────────────────── */
    function cacheStore(uid) {
      if (!uid || !vol) return;
      volCache[uid] = { vol: vol, dim: { W: dim.W, H: dim.H, D: dim.D }, geom: { colSp: geom.colSp, rowSp: geom.rowSp, slSp: geom.slSp }, rescale: { slope: rescale.slope, intercept: rescale.intercept }, modality: modality, invert: invert };
      cacheOrder = cacheOrder.filter(function (u) { return u !== uid; }); cacheOrder.push(uid);
      while (cacheOrder.length > 2) { delete volCache[cacheOrder.shift()]; }   // máx 2 series en memoria
    }
    function cacheRestore(uid) {
      var c = volCache[uid]; if (!c) return false;
      vol = c.vol; dim = { W: c.dim.W, H: c.dim.H, D: c.dim.D }; geom = { colSp: c.geom.colSp, rowSp: c.geom.rowSp, slSp: c.geom.slSp };
      rescale = { slope: c.rescale.slope, intercept: c.rescale.intercept }; modality = c.modality; invert = c.invert;
      return true;
    }
    // Orden de descarga que cubre TODO el volumen rápido (dispersión por mitades).
    function interleaveOrder(n) {
      var order = [], seen = new Uint8Array(n), st = 1;
      while (st * 2 < n) st *= 2;
      for (; st >= 1; st = Math.floor(st / 2)) {
        for (var i = 0; i < n; i += st) { if (!seen[i]) { seen[i] = 1; order.push(i); } }
        if (st === 1) break;
      }
      for (var j = 0; j < n; j++) if (!seen[j]) order.push(j);
      return order;
    }

    /* ── preparar el volumen (metadata + reserva; NO descarga aún) ───────── */
    function prepareVolume(seriesUID) {
      if (cacheRestore(seriesUID)) return Promise.resolve({ ok: true, cached: true });
      setProg(0, '0 / 0');
      return dj(ROOT + '/studies/' + STUDY + '/series/' + seriesUID + '/metadata').then(function (insts) {
        if (abort) return { ok: false };
        var slices = [];
        insts.forEach(function (md) {
          var sop = tag1(md, '00080018'); if (!sop) return;
          var rows = parseInt(tag1(md, '00280010') || '0', 10), cols = parseInt(tag1(md, '00280011') || '0', 10);
          if (!rows || !cols) return;
          var ipp = tagF(md, '00200032'), iop = tagF(md, '00200037');
          slices.push({ sop: sop, rows: rows, cols: cols, ipp: ipp ? ipp.map(Number) : null, iop: iop ? iop.map(Number) : null, instNum: parseInt(tag1(md, '00200013') || '0', 10), md: md });
        });
        if (slices.length < 2) { setErr('La serie no tiene suficientes cortes para reconstruir.'); return { ok: false }; }
        var dc = {};
        slices.forEach(function (s) { var k = s.cols + 'x' + s.rows; dc[k] = (dc[k] || 0) + 1; });
        var bestK = null, bestN = 0;
        Object.keys(dc).forEach(function (k) { if (dc[k] > bestN) { bestN = dc[k]; bestK = k; } });
        slices = slices.filter(function (s) { return (s.cols + 'x' + s.rows) === bestK; });
        if (slices.length < MIN_SLICES) { setErr('La serie no es suficientemente volumétrica para una reconstrucción útil.'); return { ok: false }; }

        var first = slices[0], W = first.cols, H = first.rows;
        var normal = [0, 0, 1];
        if (first.iop && first.iop.length === 6) {
          var r = first.iop.slice(0, 3), c = first.iop.slice(3, 6);
          normal = [r[1] * c[2] - r[2] * c[1], r[2] * c[0] - r[0] * c[2], r[0] * c[1] - r[1] * c[0]];
        }
        slices.forEach(function (s) { s.proj = s.ipp ? (s.ipp[0] * normal[0] + s.ipp[1] * normal[1] + s.ipp[2] * normal[2]) : s.instNum; });
        slices.sort(function (a, b) { return a.proj - b.proj; });

        var ps = tagF(first.md, '00280030');
        var rowSp = ps ? Number(ps[0]) : 1, colSp = ps ? Number(ps[1]) : 1;
        var slSp = estimateSliceSpacing(slices, first.md);
        var slp = Number(tag1(first.md, '00281053')), itc = Number(tag1(first.md, '00281052'));
        rescale = { slope: (isFinite(slp) && slp) ? slp : 1, intercept: isFinite(itc) ? itc : 0 };
        modality = (tag1(first.md, '00080060') || curSeries.mod || '').toUpperCase();
        invert = /MONOCHROME1/i.test(tag1(first.md, '00280004') || '');

        var stepK = 1, voxels = W * H * slices.length;
        if (voxels > MAX_VOXELS) stepK = Math.ceil(voxels / MAX_VOXELS);
        var used = [];
        for (var i = 0; i < slices.length; i += stepK) used.push(slices[i]);
        var D = used.length;
        slSp = slSp * stepK;

        dim = { W: W, H: H, D: D };
        geom = { colSp: colSp || 1, rowSp: rowSp || 1, slSp: slSp || 1 };
        try { vol = new Int16Array(W * H * D); }
        catch (e) { setErr('No hay memoria suficiente para este volumen (' + W + '×' + H + '×' + D + ').'); return { ok: false }; }
        return { ok: true, cached: false, used: used, planeLen: W * H, seriesUID: seriesUID };
      }).catch(function (e) { setErr('No se pudieron leer los cortes. ' + (e && e.message ? e.message : '')); return { ok: false }; });
    }

    function estimateSliceSpacing(slices, md0) {
      if (slices.length >= 2 && slices[0].ipp && slices[1].ipp) {
        var diffs = [], n = Math.min(slices.length - 1, 20);
        for (var i = 0; i < n; i++) { var d = Math.abs(slices[i + 1].proj - slices[i].proj); if (d > 0) diffs.push(d); }
        if (diffs.length) { diffs.sort(function (a, b) { return a - b; }); return diffs[Math.floor(diffs.length / 2)]; }
      }
      var sbs = Number(tag1(md0, '00180088')); if (isFinite(sbs) && sbs > 0) return sbs;
      var th = Number(tag1(md0, '00180050')); if (isFinite(th) && th > 0) return th;
      return 1;
    }

    /* ── carga PROGRESIVA: el volumen se ve construirse, interacción ya ──── */
    function loadSlicesProgressive(used, seriesUID, planeLen) {
      var total = used.length, done = 0, gen = ++loadGen;
      var order = interleaveOrder(total), oi = 0, finished = false;
      var lastRender = 0, lastTexFrac = 0, didInit = false;

      function maybeInit() {
        if (didInit) return; didInit = true;
        initWL(); cross = { x: dim.W >> 1, y: dim.H >> 1, z: dim.D >> 1 };
        showOverlay(false); showChip(true);
        renderAll();
        if (G.on) { uploadVolData(G.gl, 256); render3D(360); }
      }
      return new Promise(function (resolve) {
        function fin(ok) { if (finished) return; finished = true; resolve(ok); }
        function pump() {
          if (abort || gen !== loadGen) { fin(false); return; }
          if (oi >= order.length) return;
          var slot = order[oi++], s = used[slot];
          var imageId = 'wadors:' + ROOT + '/studies/' + STUDY + '/series/' + seriesUID + '/instances/' + s.sop + '/frames/1';
          try { HGV.cwil.wadors.metaDataManager.add(imageId, s.md); } catch (e) {}
          cs.loadImage(imageId).then(function (img) {
            if (gen !== loadGen) return;
            try { var pd = img.getPixelData(), base = slot * planeLen; if (pd && pd.length >= planeLen) { for (var i = 0; i < planeLen; i++) vol[base + i] = pd[i]; } } catch (e) {}
          }).catch(function () {}).then(function () {
            if (gen !== loadGen) { fin(false); return; }
            done++; setProg(done / total, done + ' / ' + total);
            if (!didInit && done >= Math.min(8, total)) maybeInit();
            if (didInit && (done - lastRender) >= 14) { lastRender = done; renderAll(); }
            if (didInit && G.on && (done / total - lastTexFrac) >= 0.25) { lastTexFrac = done / total; uploadVolData(G.gl, 256); draw3D(false); }
            if (done >= total) {
              maybeInit(); renderAll();
              if (G.on) { uploadVolData(G.gl, tex3DMax(G.gl)); draw3D(false); }   // textura final a resolución completa
              cacheStore(seriesUID); showChip(false); fin(true);
            } else { pump(); }
          });
        }
        for (var k = 0, c = Math.min(CONCURRENCY, order.length); k < c; k++) pump();
      });
    }

    /* ── stage / entrar / salir ──────────────────────────────────────────── */
    function buildStage() {
      mprStage = mk('div', 'mpr-stage lay-3'); mprStage.id = 'mpr-stage';
      mprStage.appendChild(buildView('ax', 'Axial'));
      mprStage.appendChild(buildView('co', 'Coronal'));
      mprStage.appendChild(buildView('sa', 'Sagital'));
      var v3 = mk('div', 'mpr-view v3d');
      v3.innerHTML =
        '<canvas class="mpr-gl"></canvas>'
        + '<div class="lbl"><span class="dot"></span>3D · <span id="mpr3d-meta" style="color:#aeb6cc;font-weight:600"></span></div>'
        + '<button type="button" class="vmax" id="mpr3d-fs" title="Pantalla completa">⛶</button>'
        + '<div class="mpr3d-spin"></div>'
        + '<div class="mpr3d-ctl">'
        + '<div class="seg" id="mpr3d-mode"><button type="button" data-m3="vr" class="on" title="Render volumétrico con relieve">Volumen</button><button type="button" data-m3="mip" title="Proyección de intensidad máxima">MIP</button></div>'
        + '<select id="mpr3d-preset" title="Preajuste de tejido"></select>'
        + '<label class="thr">Umbral <input type="range" id="mpr3d-thr" min="0" max="100" value="50" aria-label="Umbral 3D"></label>'
        + '<button type="button" class="r3d" id="mpr3d-reset" title="Restablecer la vista 3D">⟲</button>'
        + '</div>'
        + '<div class="mpr3d-fallback">El render 3D requiere un navegador con WebGL2.<br>Las vistas MPR siguen disponibles.</div>';
      mprStage.appendChild(v3);
      G.wrap = v3; G.canvas = v3.querySelector('.mpr-gl');
      loadEl = mk('div', 'mpr-load'); loadEl.id = 'mpr-load';
      loadEl.innerHTML = '<div class="ic">🧊</div><div class="ttl">Preparando reconstrucción…</div>'
        + '<div class="sub" id="mpr-load-sub">Descargando los cortes del estudio.</div>'
        + '<div class="bar"><i id="mpr-load-bar"></i></div><div class="pct" id="mpr-load-pct">0%</div>'
        + '<button type="button" class="cancel" id="mpr-load-cancel">Cancelar</button>';
      mprStage.appendChild(loadEl);
      var prog = mk('div', 'mpr-prog'); prog.id = 'mpr-prog';
      prog.innerHTML = '<span class="sp"></span><span id="mpr-prog-t">Cargando volumen…</span><span class="mpr-prog-bar"><i id="mpr-prog-i"></i></span>';
      mprStage.appendChild(prog);
      stage.appendChild(mprStage);
      document.getElementById('mpr-load-cancel').addEventListener('click', function () { abort = true; exitMpr(); });
      var fs3 = document.getElementById('mpr3d-fs'); if (fs3) fs3.addEventListener('click', function (ev) { ev.stopPropagation(); fsView('v3'); });
    }

    function hideBaseUi(hide) {
      ['v-overlay', 'v-hud', 'v-hud2', 'v-nav', 'v-msg'].forEach(function (id) { var n = document.getElementById(id); if (n) n.style.display = hide ? 'none' : ''; });
    }
    function setLayout(l) {
      layout = l; maxed = null;
      if (!mprStage) return;
      mprStage.className = 'mpr-stage ' + (l === 'row' ? 'lay-row' : 'lay-3');
      ['ax', 'co', 'sa'].forEach(function (k) { if (views[k]) views[k].wrap.classList.remove('max'); });
      document.querySelectorAll('#mpr-lay button').forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-l') === l); });
      setTimeout(renderAll, 30);
      if (G.on && l !== 'row') setTimeout(function () { onResize3D(); render3D(360); }, 50);
    }

    function enterMpr() {
      if (mprOn) { exitMpr(); return; }
      if (!canMpr()) return;
      mprOn = true; abort = false;
      bMpr.classList.add('active');
      var dicom = document.getElementById('dicom'); if (dicom) dicom.style.display = 'none';
      hideBaseUi(true);
      stage.classList.add('mpr-active');   // oculta de forma robusta los overlays del visor 2D (nav "1/270", etc.)
      bar.classList.add('show');
      buildStage();
      updateBtn();
      var uid = curSeries.uid;
      prepareVolume(uid).then(function (r) {
        if (!r || !r.ok || abort || !mprOn) return;
        if (r.cached) {
          initWL(); cross = { x: dim.W >> 1, y: dim.H >> 1, z: dim.D >> 1 };
          showOverlay(false); showChip(false);
          setLayout(layout); renderAll();
          try { start3D(); if (G.on) { uploadVolData(G.gl, tex3DMax(G.gl)); render3D(360); } }
          catch (e) { if (window.console) console.error('[mpr3d]', e); if (G.wrap) G.wrap.classList.add('nogl'); }
        } else {
          setLayout(layout);
          try { start3D(); } catch (e) { if (window.console) console.error('[mpr3d]', e); if (G.wrap) G.wrap.classList.add('nogl'); }
          loadSlicesProgressive(r.used, uid, r.planeLen);
        }
      });
    }
    function exitMpr() {
      if (!mprOn && !mprStage) return;
      mprOn = false; abort = true; loadGen++;
      try { destroy3D(); } catch (e) {}
      if (mprStage) { mprStage.remove(); mprStage = null; }
      views = {}; vol = null; loadEl = null;            // la variable; el ArrayBuffer puede seguir en volCache
      bar.classList.remove('show'); bMpr.classList.remove('active');
      stage.classList.remove('mpr-active');
      if (document.fullscreenElement) { try { (document.exitFullscreen || document.webkitExitFullscreen).call(document); } catch (e) {} }
      var dicom = document.getElementById('dicom'); if (dicom) dicom.style.display = '';
      hideBaseUi(false);
      updateBtn();
      setTimeout(function () { try { cs.resize(el, true); } catch (e) {} }, 40);
    }

    /* ===================================================================
       VOLUMEN 3D — ray casting WebGL2 sobre el MISMO volumen (MIP / VR).
       VR: transfer function por densidad + realce de superficie (gradiente),
       iluminación difusa + specular y dither anti-banding.
       =================================================================== */
    var VS3D = [
      '#version 300 es',
      'in vec2 aPos; out vec2 vNdc;',
      'void main(){ vNdc = aPos; gl_Position = vec4(aPos,0.0,1.0); }'
    ].join('\n');
    var FS3D = [
      '#version 300 es',
      'precision highp float; precision highp sampler3D;',
      'in vec2 vNdc; out vec4 frag;',
      'uniform sampler3D uVol; uniform mat3 uRot; uniform vec3 uSize, uTint;',
      'uniform float uAspect, uScale, uThr, uThrW; uniform int uMode, uSteps;',
      'bool hitBox(vec3 ro, vec3 rd, vec3 bmn, vec3 bmx, out float t0, out float t1){',
      '  vec3 inv=1.0/rd; vec3 a=(bmn-ro)*inv, b=(bmx-ro)*inv; vec3 mn=min(a,b), mx=max(a,b);',
      '  t0=max(max(mn.x,mn.y),mn.z); t1=min(min(mx.x,mx.y),mx.z); return t1>=max(t0,0.0); }',
      'float vsamp(vec3 p){ return texture(uVol, p/uSize*0.5+0.5).r; }',
      'float rnd(vec2 c){ return fract(sin(dot(c,vec2(12.9898,78.233)))*43758.5453); }',
      'void main(){',
      '  vec3 ro=vec3(vNdc.x*uAspect/uScale, vNdc.y/uScale, 2.0); vec3 rd=vec3(0.0,0.0,-1.0);',
      '  mat3 Rt=transpose(uRot); vec3 rov=Rt*ro, rdv=Rt*rd;',
      '  float t0,t1; if(!hitBox(rov,rdv,-uSize,uSize,t0,t1)){ frag=vec4(0.0); return; }',
      '  t0=max(t0,0.0); int N=uSteps; float dt=(t1-t0)/float(N);',
      '  t0 += rnd(gl_FragCoord.xy)*dt;',
      '  vec3 p=rov+rdv*t0, ds=rdv*dt;',
      '  if(uMode==0){ float mx=0.0;',
      '    for(int i=0;i<768;i++){ if(i>=N) break; float s=vsamp(p); if(s>=uThr) mx=max(mx,s); p+=ds; }',
      '    if(mx<=0.001){ frag=vec4(0.0); return; } frag=vec4(uTint*mx, 1.0); return; }',
      '  vec3 gstep=uSize*(2.0/vec3(textureSize(uVol,0)));',
      '  vec3 L=normalize(vec3(0.5,0.75,0.95)); vec3 Vv=vec3(0.0,0.0,1.0); vec3 Hh=normalize(L+Vv);',
      '  vec4 acc=vec4(0.0);',
      '  for(int i=0;i<768;i++){ if(i>=N) break; float s=vsamp(p);',
      '    if(s>uThr){',
      '      vec3 g=vec3(vsamp(p+vec3(gstep.x,0,0))-vsamp(p-vec3(gstep.x,0,0)),',
      '                  vsamp(p+vec3(0,gstep.y,0))-vsamp(p-vec3(0,gstep.y,0)),',
      '                  vsamp(p+vec3(0,0,gstep.z))-vsamp(p-vec3(0,0,gstep.z)));',
      '      float gm=length(g);',
      '      vec3 nrm=(gm>1e-4)? normalize(-g): vec3(0.0,0.0,1.0); vec3 nv=uRot*nrm;',
      '      float dif=0.30+0.70*max(dot(nv,L),0.0);',
      '      float spec=pow(max(dot(nv,Hh),0.0),22.0)*0.5;',
      '      float dens=clamp((s-uThr)/max(0.04,uThrW),0.0,1.0);',
      '      float a=dens*(0.12+0.6*clamp(gm*6.0,0.0,1.0));',
      '      a=clamp(a,0.0,1.0);',
      '      vec3 col=uTint*dif + vec3(spec);',
      '      acc.rgb+=(1.0-acc.a)*a*col; acc.a+=(1.0-acc.a)*a; if(acc.a>0.97) break;',
      '    }',
      '    p+=ds; }',
      '  if(acc.a<=0.003){ frag=vec4(0.0); return; } frag=vec4(acc.rgb, acc.a); }'
    ].join('\n');

    function gl3dShader(gl, type, src) {
      var s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s);
      if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) { if (window.console) console.error('[mpr3d shader]', gl.getShaderInfoLog(s)); return null; }
      return s;
    }
    function gl3dProgram(gl, vs, fs) {
      var v = gl3dShader(gl, gl.VERTEX_SHADER, vs), f = gl3dShader(gl, gl.FRAGMENT_SHADER, fs); if (!v || !f) return null;
      var p = gl.createProgram(); gl.attachShader(p, v); gl.attachShader(p, f); gl.linkProgram(p);
      if (!gl.getProgramParameter(p, gl.LINK_STATUS)) { if (window.console) console.error('[mpr3d link]', gl.getProgramInfoLog(p)); return null; }
      return p;
    }
    function presetsFor(mod) {
      if (mod === 'CT') return [
        { key: 'bone', label: 'Hueso', thr: 0.50, thrW: 0.10, tint: [1.0, 0.97, 0.90] },
        { key: 'soft', label: 'Tejidos blandos', thr: 0.40, thrW: 0.08, tint: [0.93, 0.69, 0.58] },
        { key: 'skin', label: 'Piel / superficie', thr: 0.37, thrW: 0.06, tint: [0.97, 0.83, 0.75] },
        { key: 'angio', label: 'Angio / contraste', thr: 0.54, thrW: 0.08, tint: [1.0, 0.52, 0.46] }
      ];
      return [
        { key: 'std', label: 'Estándar', thr: 0.30, thrW: 0.10, tint: [0.88, 0.92, 1.0] },
        { key: 'surf', label: 'Superficie', thr: 0.46, thrW: 0.07, tint: [0.93, 0.93, 0.98] }
      ];
    }
    function curPreset() {
      for (var i = 0; i < G.presets.length; i++) if (G.presets[i].key === G.presetKey) return G.presets[i];
      return G.presets[0] || { thr: 0.4, thrW: 0.08, tint: [1, 1, 1] };
    }
    function is3DMobile() { try { return window.matchMedia('(pointer:coarse)').matches || Math.min(screen.width, screen.height) < 760; } catch (e) { return false; } }
    function tex3DMax(gl) { var hw = (gl && gl.getParameter(gl.MAX_3D_TEXTURE_SIZE)) || 256; return Math.min(hw, is3DMobile() ? 192 : 512); }

    // Genera (o actualiza) la textura 3D R8 con el estado ACTUAL de `vol`, a la
    // resolución `res` (limitada por la GPU y el tipo de dispositivo).
    function uploadVolData(gl, res) {
      if (!gl || !vol) return;
      var maxd = Math.min(res || 256, tex3DMax(gl));
      var tw = Math.min(dim.W, maxd), th = Math.min(dim.H, maxd), td = Math.min(dim.D, maxd);
      var lo, hi;
      if (modality === 'CT') { lo = -1000; hi = 1500; }
      else {
        var W0 = dim.W, H0 = dim.H, base = (dim.D >> 1) * W0 * H0, n = W0 * H0, step = Math.max(1, Math.floor(n / 20000)), vals = [];
        for (var kk = 0; kk < n; kk += step) vals.push(vol[base + kk] * rescale.slope + rescale.intercept);
        vals.sort(function (a, b) { return a - b; });
        lo = vals[Math.floor(vals.length * 0.02)] || 0; hi = vals[Math.floor(vals.length * 0.99)] || 1;
      }
      var span = (hi - lo) || 1, data = new Uint8Array(tw * th * td), inv = invert ? 1 : 0;
      for (var z = 0; z < td; z++) {
        var sz = Math.min(dim.D - 1, Math.floor(z * dim.D / td)), zb = sz * dim.W * dim.H, ob = z * tw * th;
        for (var y = 0; y < th; y++) {
          var sy = Math.min(dim.H - 1, Math.floor(y * dim.H / th)), yb = zb + sy * dim.W, oyb = ob + y * tw;
          for (var x = 0; x < tw; x++) {
            var sx = Math.min(dim.W - 1, Math.floor(x * dim.W / tw));
            var g = (vol[yb + sx] * rescale.slope + rescale.intercept - lo) / span * 255;
            g = g < 0 ? 0 : (g > 255 ? 255 : g); if (inv) g = 255 - g;
            data[oyb + x] = g;
          }
        }
      }
      if (!G.tex) G.tex = gl.createTexture();
      gl.bindTexture(gl.TEXTURE_3D, G.tex);
      gl.texParameteri(gl.TEXTURE_3D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
      gl.texParameteri(gl.TEXTURE_3D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
      gl.texParameteri(gl.TEXTURE_3D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
      gl.texParameteri(gl.TEXTURE_3D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
      gl.texParameteri(gl.TEXTURE_3D, gl.TEXTURE_WRAP_R, gl.CLAMP_TO_EDGE);
      gl.pixelStorei(gl.UNPACK_ALIGNMENT, 1);
      gl.texImage3D(gl.TEXTURE_3D, 0, gl.R8, tw, th, td, 0, gl.RED, gl.UNSIGNED_BYTE, data);
    }
    function rot3(yaw, pitch) {
      var cy = Math.cos(yaw), sy = Math.sin(yaw), cx = Math.cos(pitch), sx = Math.sin(pitch);
      return [cy, 0, sy, sx * sy, cx, -sx * cy, -cx * sy, sx, cx * cy];   // Rx(pitch)·Ry(yaw), fila-mayor
    }
    function buildPresetSelect() {
      var sel = document.getElementById('mpr3d-preset'); if (!sel) return;
      G.presets = presetsFor(modality); G.presetKey = G.presets[0].key;
      sel.innerHTML = G.presets.map(function (p) { return '<option value="' + p.key + '">' + esc(p.label) + '</option>'; }).join('');
      var pr = curPreset(); G.thr = pr.thr;
      var t = document.getElementById('mpr3d-thr'); if (t) t.value = Math.round(pr.thr * 100);
    }

    var drag3 = null;
    function gm3(ev) {
      if (!drag3 || !G.on) return;
      var p = (ev.touches && ev.touches[0]) ? ev.touches[0] : ev;
      var dx = p.clientX - drag3.x, dy = p.clientY - drag3.y; drag3.x = p.clientX; drag3.y = p.clientY;
      G.rot.yaw += dx * 0.01; G.rot.pitch = Math.max(-1.55, Math.min(1.55, G.rot.pitch + dy * 0.01));
      if (ev.cancelable) ev.preventDefault();
      draw3D(true);
    }
    function gu3() { if (drag3) { drag3 = null; if (G.wrap) G.wrap.classList.remove('grabbing'); draw3D(false); } }
    window.addEventListener('mousemove', gm3);
    window.addEventListener('mouseup', gu3);
    window.addEventListener('touchmove', gm3, { passive: false });
    window.addEventListener('touchend', gu3);

    function attach3DControls() {
      var c = G.canvas, wrap = G.wrap;
      function p0(ev) { return ev.touches && ev.touches[0] ? ev.touches[0] : ev; }
      c.addEventListener('mousedown', function (ev) { ev.preventDefault(); var p = p0(ev); drag3 = { x: p.clientX, y: p.clientY }; wrap.classList.add('grabbing'); });
      c.addEventListener('touchstart', function (ev) { ev.preventDefault(); var p = p0(ev); drag3 = { x: p.clientX, y: p.clientY }; }, { passive: false });
      c.addEventListener('wheel', function (ev) { ev.preventDefault(); G.scale = Math.max(0.4, Math.min(4, G.scale * (ev.deltaY > 0 ? 0.9 : 1.1))); draw3D(true); }, { passive: false });
      c.addEventListener('dblclick', function () { G.rot = { yaw: 0.6, pitch: -0.45 }; G.scale = 1.0; draw3D(false); });
      document.getElementById('mpr3d-mode').addEventListener('click', function (e) {
        var b = e.target.closest('button[data-m3]'); if (!b) return;
        G.mode = b.getAttribute('data-m3');
        this.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); });
        draw3D(false);
      });
      document.getElementById('mpr3d-preset').addEventListener('change', function () {
        G.presetKey = this.value; var pr = curPreset(); G.thr = pr.thr;
        var t = document.getElementById('mpr3d-thr'); if (t) t.value = Math.round(pr.thr * 100);
        draw3D(false);
      });
      document.getElementById('mpr3d-thr').addEventListener('input', function () { G.thr = (+this.value) / 100; draw3D(true); });
      document.getElementById('mpr3d-thr').addEventListener('change', function () { draw3D(false); });
      document.getElementById('mpr3d-reset').addEventListener('click', function () { G.rot = { yaw: 0.6, pitch: -0.45 }; G.scale = 1.0; draw3D(false); });
    }

    function start3D() {
      if (!G.canvas) return;
      var gl = null;
      try { gl = G.canvas.getContext('webgl2', { antialias: false, alpha: true, premultipliedAlpha: true, preserveDrawingBuffer: true }); } catch (e) {}
      if (!gl) { if (G.wrap) G.wrap.classList.add('nogl'); return; }
      G.gl = gl;
      G.prog = gl3dProgram(gl, VS3D, FS3D);
      if (!G.prog) { G.wrap.classList.add('nogl'); return; }
      G.on = true; G.tex = null;
      var quad = new Float32Array([-1, -1, 1, -1, -1, 1, -1, 1, 1, -1, 1, 1]);
      G.vao = gl.createVertexArray(); gl.bindVertexArray(G.vao);
      var vbo = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, vbo); gl.bufferData(gl.ARRAY_BUFFER, quad, gl.STATIC_DRAW);
      var aPos = gl.getAttribLocation(G.prog, 'aPos'); gl.enableVertexAttribArray(aPos); gl.vertexAttribPointer(aPos, 2, gl.FLOAT, false, 0, 0);
      G.loc = {};
      ['uVol', 'uRot', 'uSize', 'uTint', 'uAspect', 'uScale', 'uThr', 'uThrW', 'uMode', 'uSteps'].forEach(function (kk) { G.loc[kk] = gl.getUniformLocation(G.prog, kk); });
      var hp = [dim.W * geom.colSp / 2, dim.H * geom.rowSp / 2, dim.D * geom.slSp / 2];
      var dg = Math.sqrt(hp[0] * hp[0] + hp[1] * hp[1] + hp[2] * hp[2]) || 1;
      G.uSize = [hp[0] / dg * 0.9, hp[1] / dg * 0.9, hp[2] / dg * 0.9];
      G.rot = { yaw: 0.6, pitch: -0.45 }; G.scale = 1.0; G.mode = 'vr';
      buildPresetSelect();
      attach3DControls();
      var meta = document.getElementById('mpr3d-meta'); if (meta) meta.textContent = modality;
      onResize3D();
    }
    function onResize3D() {
      if (!G.gl || !G.canvas || !G.wrap) return;
      var box = G.wrap.getBoundingClientRect(), dpr = Math.min(document.fullscreenElement ? 1.1 : 1.6, window.devicePixelRatio || 1);
      var w = Math.max(1, Math.round(box.width * dpr)), h = Math.max(1, Math.round(box.height * dpr));
      if (G.canvas.width !== w || G.canvas.height !== h) { G.canvas.width = w; G.canvas.height = h; }
    }
    function render3D(steps) {
      var gl = G.gl; if (!gl || !G.prog || !G.tex) return;
      onResize3D();
      gl.viewport(0, 0, G.canvas.width, G.canvas.height);
      gl.clearColor(0, 0, 0, 0); gl.clear(gl.COLOR_BUFFER_BIT);
      gl.useProgram(G.prog); gl.bindVertexArray(G.vao);
      gl.activeTexture(gl.TEXTURE0); gl.bindTexture(gl.TEXTURE_3D, G.tex); gl.uniform1i(G.loc.uVol, 0);
      gl.uniformMatrix3fv(G.loc.uRot, true, rot3(G.rot.yaw, G.rot.pitch));
      gl.uniform3fv(G.loc.uSize, G.uSize);
      gl.uniform1f(G.loc.uAspect, G.canvas.width / G.canvas.height);
      gl.uniform1f(G.loc.uScale, G.scale);
      var pr = curPreset();
      gl.uniform1f(G.loc.uThr, G.thr); gl.uniform1f(G.loc.uThrW, pr.thrW || 0.08);
      gl.uniform1i(G.loc.uMode, G.mode === 'mip' ? 0 : 1);
      gl.uniform1i(G.loc.uSteps, Math.max(48, Math.min(768, steps | 0)));
      gl.uniform3fv(G.loc.uTint, pr.tint);
      gl.drawArrays(gl.TRIANGLES, 0, 6);
    }
    function draw3D(interacting) {
      if (!G.on || !G.gl) return;
      if (G.refineT) { clearTimeout(G.refineT); G.refineT = 0; }
      render3D(interacting ? 140 : 420);
      if (interacting) G.refineT = setTimeout(function () { render3D(520); }, 170);
    }
    function destroy3D() {
      if (G.refineT) { clearTimeout(G.refineT); G.refineT = 0; }
      var gl = G.gl;
      if (gl) {
        try { if (G.tex) gl.deleteTexture(G.tex); } catch (e) {}
        try { if (G.prog) gl.deleteProgram(G.prog); } catch (e) {}
        try { if (G.vao) gl.deleteVertexArray(G.vao); } catch (e) {}
        try { var lc = gl.getExtension('WEBGL_lose_context'); if (lc) lc.loseContext(); } catch (e) {}
      }
      G.gl = null; G.on = false; G.tex = null; G.prog = null; G.vao = null; G.canvas = null; G.wrap = null;
    }

    /* ── eventos de la barra ─────────────────────────────────────────────── */
    bMpr.addEventListener('click', enterMpr);
    document.getElementById('mpr-exit').addEventListener('click', exitMpr);
    document.getElementById('mpr-fs').addEventListener('click', fsStage);
    document.addEventListener('fullscreenchange', function () { if (!mprOn) return; setTimeout(function () { renderAll(); if (G.on) { onResize3D(); render3D(440); } }, 80); });
    bar.querySelector('#mpr-mode').addEventListener('click', function (e) {
      var b = e.target.closest('button[data-m]'); if (!b) return;
      mode = b.getAttribute('data-m');
      bar.querySelectorAll('#mpr-mode button').forEach(function (x) { x.classList.toggle('on', x === b); });
    });
    bar.querySelector('#mpr-lay').addEventListener('click', function (e) {
      var b = e.target.closest('button[data-l]'); if (!b) return; setLayout(b.getAttribute('data-l'));
    });
    var crossBtn = document.getElementById('mpr-cross');
    crossBtn.addEventListener('click', function () { showCross = !showCross; crossBtn.classList.toggle('active', showCross); renderAll(); });

    var ddP = document.getElementById('mpr-preset-dd');
    document.getElementById('mpr-preset').addEventListener('click', function (e) { e.stopPropagation(); ddP.classList.toggle('open'); });
    document.addEventListener('click', function (e) { if (ddP && !ddP.contains(e.target)) ddP.classList.remove('open'); });
    document.getElementById('mpr-preset-menu').addEventListener('click', function (e) {
      var b = e.target.closest('button[data-ww]'); if (!b) return;
      var ww = b.getAttribute('data-ww'), wc = b.getAttribute('data-wc');
      wl = (ww === '' || ww == null) ? autoWL() : { ww: parseFloat(ww), wc: parseFloat(wc) };
      updateWlChip(); renderAll(); ddP.classList.remove('open');
    });

    window.addEventListener('resize', function () { if (mprOn && vol) setTimeout(function () { renderAll(); if (G.on && layout !== 'row') { onResize3D(); render3D(300); } }, 60); });

    updateBtn();
    HGV.mpr = { enter: enterMpr, exit: exitMpr, isOn: function () { return mprOn; } };
  }

  (function wait(n) {
    if (window.HGV) { try { boot(); } catch (e) { if (window.console) console.error('[visor-mpr]', e); } return; }
    if (n > 160) return;
    setTimeout(function () { wait(n + 1); }, 25);
  })(0);
})();
