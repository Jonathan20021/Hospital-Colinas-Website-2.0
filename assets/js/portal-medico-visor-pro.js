/* =============================================================================
   Visor de imágenes — módulo "pro".
   Mediciones avanzadas, realce de imagen, histograma, imágenes clave y
   (en bloque aparte) comparación de estudios lado a lado.

   Se monta ENCIMA del visor base usando window.HGV (expuesto por
   visor-imagen.php). No reemplaza nada del visor: si este archivo falla,
   el visor sigue funcionando. 100% client-side; sin datos del paciente
   fuera del navegador.
   ============================================================================= */
(function () {
  'use strict';

  function boot() {
    var HGV = window.HGV;
    if (!HGV || HGV.__proInit) return;
    HGV.__proInit = true;

    var cs = HGV.cornerstone, cstools = HGV.cstools, el = HGV.el, esc = HGV.escH;
    var toolbar = document.querySelector('.v-toolbar');
    var stage   = document.querySelector('.v-stage');
    if (!toolbar || !stage) return;

    /* ── helpers ──────────────────────────────────────────────────────── */
    function mk(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }
    function curImage() { try { return cs.getImage(el); } catch (e) { return null; } }
    function curVp()    { try { return cs.getViewport(el); } catch (e) { return null; } }
    function canvasEl() { return el.querySelector('canvas'); }

    function toolBtn(id, label, title, cls) {
      var b = mk('button', 'v-tool ' + (cls || ''), label);
      b.id = id; b.title = title || ''; b.type = 'button';
      return b;
    }
    function addAfter(refId, node) { var r = document.getElementById(refId); if (r) r.insertAdjacentElement('afterend', node); else toolbar.appendChild(node); }

    /* ── toast ────────────────────────────────────────────────────────── */
    var toastT;
    function toast(msg) {
      var t = document.getElementById('hgv-toast');
      if (!t) { t = mk('div', 'hgv-hint'); t.id = 'hgv-toast'; stage.appendChild(t); }
      t.innerHTML = esc(msg);
      t.classList.add('show');
      clearTimeout(toastT); toastT = setTimeout(function () { t.classList.remove('show'); }, 2600);
    }

    /* ── hint persistente (instrucciones) ─────────────────────────────── */
    var hintEl;
    function showHint(html) {
      if (!hintEl) { hintEl = mk('div', 'hgv-hint'); stage.appendChild(hintEl); }
      hintEl.innerHTML = html + ' <button type="button" class="hgv-hint-x" aria-label="Cerrar">✕</button>';
      hintEl.querySelector('.hgv-hint-x').addEventListener('click', hideHint);
      hintEl.classList.add('show');
    }
    function hideHint() { if (hintEl) hintEl.classList.remove('show'); }

    /* ── paneles flotantes (solo uno abierto) ─────────────────────────── */
    var panels = {};
    function makePanel(id, icon, title, footHtml) {
      var p = mk('div', 'hgv-panel'); p.id = 'hgv-panel-' + id;
      p.innerHTML = '<div class="hgv-panel-h"><span class="t">' + icon + ' ' + esc(title) + '</span>'
        + '<button type="button" class="x" aria-label="Cerrar">✕</button></div>'
        + '<div class="hgv-panel-b"></div>'
        + (footHtml ? '<div class="hgv-panel-foot">' + footHtml + '</div>' : '');
      stage.appendChild(p);
      var api = {
        el: p, body: p.querySelector('.hgv-panel-b'),
        open: function () { Object.keys(panels).forEach(function (k) { if (k !== id) panels[k].close(); }); p.classList.add('open'); },
        close: function () { p.classList.remove('open'); },
        toggle: function () { if (p.classList.contains('open')) api.close(); else api.open(); },
        isOpen: function () { return p.classList.contains('open'); }
      };
      p.querySelector('.x').addEventListener('click', api.close);
      panels[id] = api;
      return api;
    }

    /* ===================================================================
       1) MEDICIONES PRO — Rectángulo, Bidireccional (RECIST), Calibrar
       =================================================================== */
    var bRect  = toolBtn('t-rect',  '▭ Rect.',     'Área rectangular (media / HU en TC)', 'hgv-pro');
    var bBidir = toolBtn('t-bidir', '✛ Bidirecc.', 'Medición bidireccional (RECIST): eje mayor y menor', 'hgv-pro');
    var bCalib = toolBtn('t-calib', '🎯 Calibrar', 'Calibrar la escala con una distancia conocida', 'hgv-pro');
    addAfter('t-probe', bCalib); addAfter('t-probe', bBidir); addAfter('t-probe', bRect);

    bRect.addEventListener('click',  function () { HGV.setActiveTool('RectangleRoi', 't-rect'); });
    bBidir.addEventListener('click', function () { HGV.setActiveTool('Bidirectional', 't-bidir'); });

    /* Calibración: el médico traza una línea sobre una distancia conocida y
       le decimos cuántos mm mide → fijamos el pixelSpacing de la imagen. */
    var calibMode = false;
    bCalib.addEventListener('click', function () {
      if (!curImage()) { toast('Carga una imagen primero.'); return; }
      calibMode = true;
      HGV.setActiveTool('Length', 't-length');
      showHint('<b>Calibración:</b> traza una línea sobre una distancia conocida (regla, marcador). Al soltar te pediré la medida real.');
    });
    el.addEventListener('cornerstonetoolsmeasurementcompleted', function (ev) {
      if (!calibMode) return;
      var d = ev.detail || {};
      if ((d.toolName || d.toolType) !== 'Length') return;
      calibMode = false; hideHint();
      var m = d.measurementData || {};
      var h = m.handles || {};
      if (!h.start || !h.end) return;
      var dx = h.end.x - h.start.x, dy = h.end.y - h.start.y;
      var px = Math.sqrt(dx * dx + dy * dy);
      if (!(px > 0)) { toast('No se pudo leer la línea.'); return; }
      var ans = window.prompt('Longitud REAL de la línea trazada, en milímetros (ej. 50):', '');
      if (ans == null) return;
      var mm = parseFloat(String(ans).replace(',', '.'));
      if (!(mm > 0)) { toast('Medida inválida.'); return; }
      var img = curImage(); if (!img) return;
      var spacing = mm / px;                 // mm por píxel
      img.rowPixelSpacing = spacing; img.columnPixelSpacing = spacing;
      try { cs.updateImage(el); } catch (e) {}
      toast('Calibrado: 1 px = ' + spacing.toFixed(4) + ' mm. Las mediciones ahora usan esta escala.');
    });

    /* ===================================================================
       2) HISTOGRAMA de densidades + auto-ventana
       =================================================================== */
    var bHist = toolBtn('t-hist', '📊 Histograma', 'Histograma de densidades de la imagen', 'hgv-pro hgv-tool-img');
    addAfter('t-reset', bHist);
    var histPanel = makePanel('hist', '📊', 'Histograma de densidades',
      'Distribución de intensidades de la imagen. La franja azul marca la ventana actual.');
    histPanel.body.innerHTML =
      '<canvas class="hgv-hist-canvas" id="hgv-hist-cv" width="276" height="130"></canvas>'
      + '<div class="hgv-hist-stats" id="hgv-hist-stats"></div>'
      + '<div class="hgv-hist-actions">'
      + '<button type="button" class="hgv-btn primary" id="hgv-autowin">✨ Auto-ventana</button>'
      + '<button type="button" class="hgv-btn ghost" id="hgv-hist-refresh">↻ Recalcular</button>'
      + '</div>';

    function sampleValues(max) {
      var img = curImage(); if (!img) return null;
      var pd; try { pd = img.getPixelData(); } catch (e) { return null; }
      if (!pd || !pd.length) return null;
      var n = pd.length, step = Math.max(1, Math.floor(n / (max || 40000)));
      var vals = [];
      for (var i = 0; i < n; i += step) vals.push(pd[i]);
      return { vals: vals, slope: (img.slope != null ? img.slope : 1), intercept: (img.intercept != null ? img.intercept : 0) };
    }
    function percentile(sorted, p) {
      if (!sorted.length) return 0;
      var idx = Math.min(sorted.length - 1, Math.max(0, Math.round((p / 100) * (sorted.length - 1))));
      return sorted[idx];
    }
    function drawHistogram() {
      var s = sampleValues(60000);
      var cv = document.getElementById('hgv-hist-cv'); if (!cv) return;
      var ctx = cv.getContext('2d'); ctx.clearRect(0, 0, cv.width, cv.height);
      var statsEl = document.getElementById('hgv-hist-stats');
      if (!s) { if (statsEl) statsEl.innerHTML = '<div><b>Sin datos de imagen</b></div>'; return; }
      var vals = s.vals, mn = Infinity, mx = -Infinity, sum = 0;
      for (var i = 0; i < vals.length; i++) { var v = vals[i]; if (v < mn) mn = v; if (v > mx) mx = v; sum += v; }
      var mean = sum / vals.length;
      var BINS = 96, bins = new Array(BINS).fill(0), range = (mx - mn) || 1;
      for (i = 0; i < vals.length; i++) { var b = Math.min(BINS - 1, Math.floor(((vals[i] - mn) / range) * BINS)); bins[b]++; }
      var peak = Math.max.apply(null, bins) || 1;
      var W = cv.width, H = cv.height, bw = W / BINS;
      // franja de la ventana actual
      var vp = curVp();
      if (vp && vp.voi) {
        var slope = s.slope, intercept = s.intercept;
        // voi está en espacio HU (post-rescale); convertir a stored para ubicar en el eje
        var lowHU = vp.voi.windowCenter - vp.voi.windowWidth / 2, highHU = vp.voi.windowCenter + vp.voi.windowWidth / 2;
        var lowSt = (lowHU - intercept) / (slope || 1), highSt = (highHU - intercept) / (slope || 1);
        var x0 = ((lowSt - mn) / range) * W, x1 = ((highSt - mn) / range) * W;
        ctx.fillStyle = 'rgba(109,139,255,.18)';
        ctx.fillRect(Math.max(0, Math.min(x0, x1)), 0, Math.max(2, Math.abs(x1 - x0)), H);
      }
      ctx.fillStyle = '#6d8bff';
      for (i = 0; i < BINS; i++) {
        var hgt = (bins[i] / peak) * (H - 4);
        ctx.fillRect(i * bw, H - hgt, Math.max(1, bw - 0.5), hgt);
      }
      var toHU = function (v) { return Math.round(v * s.slope + s.intercept); };
      var mod = (HGV.getStudyMeta().modality || '');
      var isCT = /CT/i.test(mod);
      var unit = isCT ? ' HU' : '';
      if (statsEl) {
        statsEl.innerHTML =
          '<div><b>Mín.</b><span>' + toHU(mn) + unit + '</span></div>'
          + '<div><b>Máx.</b><span>' + toHU(mx) + unit + '</span></div>'
          + '<div><b>Media</b><span>' + toHU(mean) + unit + '</span></div>'
          + '<div><b>Muestras</b><span>' + vals.length.toLocaleString('es') + '</span></div>';
      }
      return { sortedFn: function () { return vals.slice().sort(function (a, b) { return a - b; }); }, slope: s.slope, intercept: s.intercept };
    }
    function autoWindow() {
      var s = sampleValues(60000); if (!s) { toast('Sin imagen.'); return; }
      var sorted = s.vals.slice().sort(function (a, b) { return a - b; });
      var p1 = percentile(sorted, 1), p99 = percentile(sorted, 99);
      var lowHU = p1 * s.slope + s.intercept, highHU = p99 * s.slope + s.intercept;
      var vp = curVp(); if (!vp) return;
      vp.voi.windowWidth = Math.max(1, highHU - lowHU);
      vp.voi.windowCenter = (highHU + lowHU) / 2;
      try { cs.setViewport(el, vp); HGV.updateHud(); } catch (e) {}
      drawHistogram();
      toast('Ventana ajustada automáticamente (percentiles 1–99 %).');
    }
    bHist.addEventListener('click', function () { histPanel.toggle(); if (histPanel.isOpen()) drawHistogram(); });
    histPanel.body.querySelector('#hgv-autowin').addEventListener('click', autoWindow);
    histPanel.body.querySelector('#hgv-hist-refresh').addEventListener('click', drawHistogram);

    /* ===================================================================
       3) REALCE de imagen (post-proceso visual sobre el lienzo)
       =================================================================== */
    // filtro SVG de nitidez
    (function injectSvgFilters() {
      var ns = 'http://www.w3.org/2000/svg';
      var svg = document.createElementNS(ns, 'svg');
      svg.setAttribute('width', '0'); svg.setAttribute('height', '0');
      svg.style.position = 'absolute'; svg.setAttribute('aria-hidden', 'true');
      svg.innerHTML = '<defs><filter id="hgv-sharpen"><feConvolveMatrix order="3" preserveAlpha="true" '
        + 'kernelMatrix="0 -1 0 -1 5 -1 0 -1 0"/></filter></defs>';
      document.body.appendChild(svg);
    })();

    var bEnh = toolBtn('t-enh', '✨ Realce', 'Realce de imagen (nitidez, contraste, suavizado)', 'hgv-pro hgv-tool-img');
    addAfter('t-hist', bEnh);
    var enhPanel = makePanel('enh', '✨', 'Realce de imagen',
      'Mejoras visuales no destructivas. No alteran los valores ni las mediciones.');
    var ENH = [
      { k: 'normal', ic: '○', t: 'Normal', d: 'Sin realce' },
      { k: 'auto',   ic: '✨', t: 'Auto-contraste', d: 'Ajusta la ventana a la imagen (percentiles 1–99 %)' },
      { k: 'sharpen', ic: '🔬', t: 'Nitidez', d: 'Resalta bordes y detalles finos' },
      { k: 'highcontrast', ic: '◐', t: 'Alto contraste', d: 'Aumenta el contraste visual' },
      { k: 'smooth', ic: '🌫', t: 'Suavizar', d: 'Reduce el ruido (desenfoque leve)' }
    ];
    enhPanel.body.innerHTML =
      '<div class="hgv-enh-list">' + ENH.map(function (o) {
        return '<button type="button" class="hgv-enh-opt" data-k="' + o.k + '"><span class="ic">' + o.ic + '</span>'
          + '<span class="tx"><b>' + esc(o.t) + '</b><span>' + esc(o.d) + '</span></span></button>';
      }).join('') + '</div>'
      + '<div class="hgv-enh-strength" id="hgv-enh-strength"><label>Intensidad <span id="hgv-enh-val">100%</span></label>'
      + '<input type="range" id="hgv-enh-range" min="20" max="200" value="100"></div>';

    var curEnh = 'normal', curStrength = 1;
    function filterFor(k, strength) {
      switch (k) {
        case 'sharpen': return 'url(#hgv-sharpen) contrast(' + (1 + 0.12 * strength).toFixed(2) + ')';
        case 'highcontrast': return 'contrast(' + (1 + 0.5 * strength).toFixed(2) + ') brightness(' + (1 + 0.04 * strength).toFixed(2) + ')';
        case 'smooth': return 'blur(' + (0.6 * strength).toFixed(2) + 'px)';
        default: return '';
      }
    }
    function applyEnhancement() {
      var c = canvasEl(); if (!c) return;
      c.style.filter = filterFor(curEnh, curStrength);
    }
    function setEnh(k) {
      if (k === 'auto') { autoWindow(); return; }   // auto-contraste = acción puntual
      curEnh = k;
      enhPanel.body.querySelectorAll('.hgv-enh-opt').forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-k') === k); });
      var str = document.getElementById('hgv-enh-strength');
      str.classList.toggle('show', k === 'sharpen' || k === 'highcontrast' || k === 'smooth');
      applyEnhancement();
      bEnh.classList.toggle('active', k !== 'normal');
    }
    enhPanel.body.querySelector('.hgv-enh-list').addEventListener('click', function (e) {
      var b = e.target.closest('.hgv-enh-opt'); if (!b) return;
      setEnh(b.getAttribute('data-k'));
    });
    var rng = document.getElementById('hgv-enh-range');
    rng.addEventListener('input', function () {
      curStrength = (+rng.value) / 100; document.getElementById('hgv-enh-val').textContent = rng.value + '%'; applyEnhancement();
    });
    bEnh.addEventListener('click', function () { enhPanel.toggle(); });
    // Reaplicar el filtro tras cada render (cornerstone no lo borra, pero por seguridad)
    el.addEventListener('cornerstoneimagerendered', function () { if (curEnh !== 'normal') { var c = canvasEl(); if (c && c.style.filter !== filterFor(curEnh, curStrength)) c.style.filter = filterFor(curEnh, curStrength); } });

    /* ===================================================================
       4) IMÁGENES CLAVE (marcar capturas para revisar / exportar)
       =================================================================== */
    var keyImages = [];
    var bKey = toolBtn('t-key', '★ Clave', 'Marcar la imagen actual como clave', 'hgv-pro');
    addAfter('t-fs', bKey);
    function updateKeyBadge() {
      var b = bKey.querySelector('.hgv-badge');
      if (keyImages.length) {
        if (!b) { b = mk('span', 'hgv-badge'); bKey.appendChild(b); }
        b.textContent = keyImages.length;
      } else if (b) { b.remove(); }
    }
    function capture(maxW) {
      var c = canvasEl(); if (!c) return null;
      var sc = Math.min(1, (maxW || 320) / Math.max(c.width, c.height));
      var o = document.createElement('canvas'); o.width = Math.max(1, Math.round(c.width * sc)); o.height = Math.max(1, Math.round(c.height * sc));
      var x = o.getContext('2d'); x.fillStyle = '#000'; x.fillRect(0, 0, o.width, o.height);
      try { x.drawImage(c, 0, 0, o.width, o.height); } catch (e) { return null; }
      try { return o.toDataURL('image/jpeg', 0.85); } catch (e) { return null; }
    }
    var keyPanel = makePanel('key', '★', 'Imágenes clave',
      'Capturas marcadas en esta sesión. No se guardan al cerrar el visor.');
    function renderKeyPanel() {
      var b = keyPanel.body;
      if (!keyImages.length) { b.innerHTML = '<div class="hgv-key-empty">Sin imágenes clave.<br>Pulsa <b>★ Clave</b> para marcar la vista actual.</div>'; return; }
      b.innerHTML = '<div class="hgv-key-grid">' + keyImages.map(function (k, i) {
        return '<div class="hgv-key-item" data-i="' + i + '"><button type="button" class="del" data-del="' + i + '" aria-label="Quitar">✕</button>'
          + '<img src="' + k.thumb + '" alt=""><div class="cap">' + esc(k.cap) + '</div></div>';
      }).join('') + '</div>'
        + '<div class="hgv-hist-actions"><button type="button" class="hgv-btn primary" id="hgv-key-pdf">⤓ Exportar a PDF</button>'
        + '<button type="button" class="hgv-btn ghost" id="hgv-key-clear">Vaciar</button></div>';
      b.querySelector('#hgv-key-pdf').addEventListener('click', exportKeyPdf);
      b.querySelector('#hgv-key-clear').addEventListener('click', function () { keyImages = []; updateKeyBadge(); renderKeyPanel(); });
    }
    keyPanel.body.addEventListener('click', function (e) {
      var del = e.target.closest('[data-del]');
      if (del) { keyImages.splice(+del.getAttribute('data-del'), 1); updateKeyBadge(); renderKeyPanel(); return; }
    });
    bKey.addEventListener('click', function () {
      var thumb = capture(320), full = capture(1400);
      if (!thumb) { toast('No hay imagen para marcar.'); return; }
      var st = HGV.getStack(), meta = HGV.getStudyMeta();
      keyImages.push({
        thumb: thumb, full: full,
        cap: (HGV.getSeriesDesc() || meta.modality || 'Imagen') + ' · ' + ((st.currentImageIdIndex || 0) + 1) + '/' + (st.imageIds.length || 1)
      });
      updateKeyBadge(); toast('Imagen marcada como clave (' + keyImages.length + ').');
      if (keyPanel.isOpen()) renderKeyPanel();
    });
    // doble clic en ★ abre el panel; además un botón "Ver clave" explícito (claro en móvil)
    bKey.addEventListener('dblclick', function (e) { e.preventDefault(); keyPanel.open(); renderKeyPanel(); });
    var bKeyView = toolBtn('t-key-view', '☆ Ver clave', 'Ver las imágenes clave marcadas', 'hgv-pro');
    addAfter('t-key', bKeyView);
    bKeyView.addEventListener('click', function () { keyPanel.toggle(); if (keyPanel.isOpen()) renderKeyPanel(); });

    function exportKeyPdf() {
      if (!window.jspdf || !window.jspdf.jsPDF) { toast('No se pudo cargar el generador de PDF.'); return; }
      if (!keyImages.length) return;
      var meta = HGV.getStudyMeta(), fdate = HGV.fdate, logoImg = HGV.logoImg, CLINIC = HGV.CLINIC;
      var doc = new window.jspdf.jsPDF({ unit: 'pt', format: 'letter' });
      var W = 612, H = 792, M = 40, navy = [42, 37, 102], gray = [100, 116, 139], line = [214, 218, 228];
      doc.setFillColor(244, 245, 250); doc.rect(0, 0, W, 78, 'F');
      doc.setDrawColor(line[0], line[1], line[2]); doc.line(0, 78, W, 78);
      if (logoImg && logoImg.complete && logoImg.naturalWidth) {
        var lh = 40, lw = lh * (logoImg.naturalWidth / logoImg.naturalHeight); if (lw > 220) { lw = 220; lh = lw * (logoImg.naturalHeight / logoImg.naturalWidth); }
        try { doc.addImage(logoImg, 'PNG', M, (78 - lh) / 2, lw, lh); } catch (e) {}
      }
      doc.setTextColor(navy[0], navy[1], navy[2]); doc.setFont('helvetica', 'bold'); doc.setFontSize(14);
      doc.text('Imágenes clave', W - M, 38, { align: 'right' });
      doc.setFont('helvetica', 'normal'); doc.setFontSize(9); doc.setTextColor(gray[0], gray[1], gray[2]);
      doc.text((CLINIC || '') + ' · Radiología', W - M, 54, { align: 'right' });
      doc.setFontSize(9.5); doc.setTextColor(30, 37, 64);
      doc.text('Paciente: ' + (meta.pname || '—') + (meta.pid ? '  ·  ' + meta.pid : ''), M, 100);
      doc.setTextColor(gray[0], gray[1], gray[2]);
      doc.text((meta.modality || '') + (meta.studyDate ? '  ·  ' + fdate(meta.studyDate) : '') + (meta.studyDesc ? '  ·  ' + meta.studyDesc : ''), M, 116);

      var cols = 2, gap = 16, cellW = (W - 2 * M - gap) / cols, cellH = 200, x = M, y = 134, col = 0;
      keyImages.forEach(function (k, i) {
        if (y + cellH > H - 40) { doc.addPage(); x = M; y = 40; col = 0; }
        doc.setFillColor(0, 0, 0); doc.rect(x, y, cellW, cellH - 20, 'F');
        var im = new Image();   // sincrónico no: usamos addImage directo del dataURL
        try {
          // medir proporción del dataURL creando una imagen temporal no es necesario: encuadramos al centro con proporción aprox 4:3
          doc.addImage(k.full || k.thumb, 'JPEG', x + 4, y + 4, cellW - 8, cellH - 28, undefined, 'FAST');
        } catch (e) {}
        doc.setFontSize(7.5); doc.setTextColor(gray[0], gray[1], gray[2]);
        doc.text(String(k.cap || '').slice(0, 44), x, y + cellH - 4);
        col++; if (col >= cols) { col = 0; x = M; y += cellH + 6; } else { x += cellW + gap; }
      });
      var n = new Date(), p2 = function (v) { return ('0' + v).slice(-2); };
      var stamp = p2(n.getDate()) + '/' + p2(n.getMonth() + 1) + '/' + n.getFullYear();
      doc.setFontSize(7.5); doc.setTextColor(gray[0], gray[1], gray[2]);
      doc.text((CLINIC || '') + ' · Imágenes de referencia — no sustituyen el informe radiológico oficial · ' + stamp, M, H - 22);
      doc.save(('Imagenes_clave_' + (meta.modality || '') + '_' + (meta.studyDate || '')).replace(/[^A-Za-z0-9._-]/g, '_') + '.pdf');
    }

    /* refrescar paneles dependientes de la imagen al cambiar de serie/imagen */
    el.addEventListener('hgv:series', function () { if (histPanel.isOpen()) drawHistogram(); if (curEnh !== 'normal') applyEnhancement(); });
    el.addEventListener('cornerstonenewimage', function () { if (histPanel.isOpen()) drawHistogram(); });

    /* ===================================================================
       5) COMPARAR ESTUDIOS lado a lado (1×2 / 2×2) con sincronización
       =================================================================== */
    var ROOT = HGV.ROOT;
    var compareOn = false, vps = [], syncOn = false;   // sync opt-in: solo tiene sentido entre estudios equivalentes (p.ej. 2 TC)
    var syncW = null, syncPZ = null, syncStack = null, studiesCache = null;

    var bCompare = toolBtn('t-compare', '⊞ Comparar', 'Comparar dos estudios lado a lado', 'hgv-pro');
    addAfter('t-key-view', bCompare);

    // Barra de comparación (layout + sincronización + salir), oculta hasta activar
    var cmpBar = mk('div', 'hgv-cmp-bar'); cmpBar.id = 'hgv-cmp-bar';
    cmpBar.innerHTML =
      '<div class="hgv-seg" id="hgv-layout"><button type="button" data-n="2" class="on">▱▱ 1×2</button><button type="button" data-n="4">⊞ 2×2</button></div>'
      + '<button type="button" class="v-tool" id="hgv-sync">🔗 Sincronizado</button>'
      + '<button type="button" class="v-tool" id="hgv-cmp-exit">✕ Salir</button>';
    toolbar.appendChild(cmpBar);

    var pickPanel = makePanel('pick', '⊞', 'Elegir estudio para comparar',
      'Otros estudios de este paciente. Se cargan con el mismo permiso seguro.');
    var pickTargetVp = null;

    // UIDs disponibles: del token de scope (JWT) que ya autoriza al visor.
    function scopeUids() {
      // El token de scope (ImagingScope) es "payload.firma" (base64url). El payload
      // puede estar en cualquier segmento según el formato; probamos todos y tomamos
      // el que traiga uids[]. Solo leemos uids para poblar el selector; la
      // autorización real la hace el proxy en el servidor.
      try {
        var sc = ROOT.split('imaging-dwr.php/')[1] || '';
        var parts = sc.split('.');
        for (var i = 0; i < parts.length; i++) {
          try {
            var json = JSON.parse(decodeURIComponent(escape(atob(parts[i].replace(/-/g, '+').replace(/_/g, '/')))));
            if (json && Array.isArray(json.uids)) return json.uids;
          } catch (e) {}
        }
      } catch (e) {}
      return [];
    }
    function fetchStudiesViaProxy() {
      if (!HGV.PID) return Promise.resolve([]);
      return fetch(HGV.PROXY, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': HGV.CSRF },
        body: JSON.stringify({ method: 'GET', path: '/portal-doctor/me/patients/' + HGV.PID + '/imaging' })
      }).then(function (r) { return r.json(); }).then(function (j) {
        var d = (j && j.data) ? j.data : j; var st = (d && d.studies) || [];
        return st.map(function (s) { return { uid: s.studyUID, modality: s.modality, date: s.date, desc: s.description }; });
      }).catch(function () { return []; });
    }
    function getComparableStudies() {
      if (studiesCache) return Promise.resolve(studiesCache);
      var uids = scopeUids();
      if (!uids.length) return fetchStudiesViaProxy().then(function (l) { studiesCache = l; return l; });
      return Promise.all(uids.map(function (uid) {
        return HGV.dj(ROOT + '/studies?StudyInstanceUID=' + uid + '&includefield=00080060&includefield=00080020&includefield=00081030')
          .then(function (r) { var s = r && r[0]; return { uid: uid, modality: s ? HGV.tag1(s, '00080060') : '', date: s ? HGV.tag1(s, '00080020') : '', desc: s ? HGV.tag1(s, '00081030') : '' }; })
          .catch(function () { return { uid: uid, modality: '', date: '', desc: '' }; });
      })).then(function (list) { studiesCache = list; return list; });
    }

    function vpTools(elem) {
      try {
        cstools.addToolForElement(elem, cstools.WwwcTool);
        cstools.addToolForElement(elem, cstools.PanTool);
        cstools.addToolForElement(elem, cstools.ZoomTool);
        cstools.addToolForElement(elem, cstools.StackScrollMouseWheelTool);
        cstools.addToolForElement(elem, cstools.ZoomTouchPinchTool);
        cstools.addToolForElement(elem, cstools.PanMultiTouchTool);
        cstools.addToolForElement(elem, cstools.StackScrollMultiTouchTool);
        cstools.setToolActiveForElement(elem, 'Wwwc', { mouseButtonMask: 1 });
        cstools.setToolActiveForElement(elem, 'Zoom', { mouseButtonMask: 2 });
        cstools.setToolActiveForElement(elem, 'Pan', { mouseButtonMask: 4 });
        cstools.setToolActiveForElement(elem, 'StackScrollMouseWheel', {});
        cstools.setToolActiveForElement(elem, 'ZoomTouchPinch', {});
        cstools.setToolActiveForElement(elem, 'PanMultiTouch', {});
        cstools.setToolActiveForElement(elem, 'StackScrollMultiTouch', {});
      } catch (e) {}
    }

    function vpLoadStudy(vp, uid) {
      vp.uid = uid; vp.spin(true); vp.setTag('Cargando…'); vp.hidePick();
      HGV.dj(ROOT + '/studies/' + uid + '/series').then(function (series) {
        if (!series || !series.length) { vp.spin(false); vp.setTag('Estudio sin series'); return; }
        series.sort(function (a, b) { return (HGV.tag1(a, '00200011') || 0) - (HGV.tag1(b, '00200011') || 0); });
        var s0 = series[0], su = HGV.tag1(s0, '0020000E'), mod = HGV.tag1(s0, '00080060') || '';
        return HGV.dj(ROOT + '/studies/' + uid + '/series/' + su + '/metadata').then(function (insts) {
          insts.sort(function (a, b) { return (HGV.tag1(a, '00200013') || 0) - (HGV.tag1(b, '00200013') || 0); });
          var ids = [];
          insts.forEach(function (md) {
            var sop = HGV.tag1(md, '00080018'); if (!sop) return;
            var frames = parseInt(HGV.tag1(md, '00280008') || '1', 10) || 1;
            for (var f = 1; f <= frames; f++) {
              var id = 'wadors:' + ROOT + '/studies/' + uid + '/series/' + su + '/instances/' + sop + '/frames/' + f;
              HGV.cwil.wadors.metaDataManager.add(id, md); ids.push(id);
            }
          });
          if (!ids.length) { vp.spin(false); vp.setTag('Serie sin imágenes'); return; }
          var m0 = insts[0];
          vp.meta = {
            pname: (HGV.pn(m0, '00100010') || '').replace(/\^/g, ' ').replace(/\s+/g, ' ').trim(),
            studyDate: HGV.tag1(m0, '00080020') || '', modality: HGV.tag1(m0, '00080060') || mod,
            desc: HGV.tag1(m0, '0008103E') || HGV.tag1(m0, '00081030') || ''
          };
          vp.stack = { currentImageIdIndex: 0, imageIds: ids };
          return cs.loadAndCacheImage(ids[0]).then(function (image) {
            cs.displayImage(vp.elem, image);
            vpTools(vp.elem);
            try { cstools.clearToolState(vp.elem, 'stack'); } catch (e) {}
            try { cstools.addToolState(vp.elem, 'stack', { currentImageIdIndex: 0, imageIds: ids }); } catch (e) {}
            vp.spin(false); vp.tagFromMeta();
            if (syncOn) attachSync(vp.elem);
            setTimeout(function () { try { cs.resize(vp.elem, true); } catch (e) {} }, 30);
          });
        });
      }).catch(function (e) { vp.spin(false); vp.setTag('Error al cargar'); });
    }

    function buildGrid(n) {
      var grid = mk('div', 'hgv-grid ' + (n === 4 ? 'cols-2 rows-2' : 'cols-2'));
      grid.id = 'hgv-grid';
      vps = [];
      for (var i = 0; i < n; i++) {
        var wrap = mk('div', 'hgv-vp'), elx = mk('div', 'hgv-vp-el'),
            tag = mk('div', 'hgv-vp-tag'), pick = mk('div', 'hgv-vp-pick', '<span class="ic">⊕</span><span>Elegir estudio</span>'),
            spin = mk('div', 'hgv-vp-spin', '<div class="s"></div>');
        wrap.appendChild(elx); wrap.appendChild(tag); wrap.appendChild(pick); wrap.appendChild(spin);
        grid.appendChild(wrap);
        (function (elx, tag, pick, spin, idx) {
          var vp = {
            elem: elx, idx: idx, meta: null, uid: null,
            spin: function (on) { spin.classList.toggle('on', !!on); },
            setTag: function (t) { tag.innerHTML = t; },
            tagFromMeta: function () { var m = vp.meta || {}; tag.innerHTML = '<b>' + esc(m.modality || '') + '</b> ' + esc(m.desc || '') + '<br>' + esc(HGV.fdate(m.studyDate) || ''); },
            showPick: function () { pick.style.display = 'flex'; },
            hidePick: function () { pick.style.display = 'none'; }
          };
          try { cs.enable(elx); } catch (e) {}
          pick.addEventListener('click', function (ev) { ev.stopPropagation(); openStudyPicker(vp); });
          vps.push(vp);
        })(elx, tag, pick, spin, i);
      }
      return grid;
    }

    function openStudyPicker(vp) {
      pickTargetVp = vp; pickPanel.open();
      pickPanel.body.innerHTML = '<div class="hgv-key-empty">Cargando estudios…</div>';
      getComparableStudies().then(function (list) {
        list = (list || []).filter(function (s) { return s.uid; });
        if (!list.length) { pickPanel.body.innerHTML = '<div class="hgv-key-empty">No hay otros estudios disponibles para comparar.</div>'; return; }
        var usedUids = vps.map(function (v) { return v.uid; });
        pickPanel.body.innerHTML = '<div class="hgv-studies">' + list.map(function (s) {
          var taken = usedUids.indexOf(s.uid) >= 0 && (!pickTargetVp || pickTargetVp.uid !== s.uid);
          return '<button type="button" class="hgv-study' + (taken ? ' current' : '') + '" data-uid="' + esc(s.uid) + '">'
            + '<span class="mod">' + esc(s.modality || '—') + '</span>'
            + '<span class="info"><b>' + esc(s.desc || 'Estudio') + '</b><span>' + esc(HGV.fdate(s.date) || '') + '</span></span></button>';
        }).join('') + '</div>';
        pickPanel.body.querySelectorAll('.hgv-study[data-uid]').forEach(function (b) {
          b.addEventListener('click', function () { pickPanel.close(); vpLoadStudy(pickTargetVp, b.getAttribute('data-uid')); });
        });
      });
    }

    function initSyncs() {
      try {
        syncW = new cstools.Synchronizer('cornerstoneimagerendered', cstools.wwwcSynchronizer);
        syncPZ = new cstools.Synchronizer('cornerstoneimagerendered', cstools.panZoomSynchronizer);
        syncStack = new cstools.Synchronizer('cornerstonenewimage', cstools.stackImageIndexSynchronizer);
      } catch (e) { syncW = syncPZ = syncStack = null; }
    }
    function attachSync(elem) { if (!syncOn) return; try { syncW.add(elem); syncPZ.add(elem); syncStack.add(elem); } catch (e) {} }
    function detachSync(elem) { try { syncW.remove(elem); syncPZ.remove(elem); syncStack.remove(elem); } catch (e) {} }
    function setSync(on) {
      syncOn = on;
      document.getElementById('hgv-sync').classList.toggle('active', on);
      document.getElementById('hgv-sync').innerHTML = on ? '🔗 Sincronizado' : '⛓️‍💥 Independiente';
      vps.forEach(function (v) { if (v.uid) { if (on) attachSync(v.elem); else detachSync(v.elem); } });
    }

    function baseOverlays(hide) {
      ['v-overlay', 'v-hud', 'v-hud2', 'v-nav'].forEach(function (id) { var n = document.getElementById(id); if (n) n.style.display = hide ? 'none' : ''; });
    }
    function resizeVps() { vps.forEach(function (v) { try { cs.resize(v.elem, true); } catch (e) {} }); }

    function teardownGrid() {
      vps.forEach(function (v) { detachSync(v.elem); try { cs.disable(v.elem); } catch (e) {} });
      vps = []; var g = document.getElementById('hgv-grid'); if (g) g.remove();
    }
    function setLayoutButtons(n) {
      document.querySelectorAll('#hgv-layout button').forEach(function (b) { b.classList.toggle('on', (+b.getAttribute('data-n')) === n); });
    }

    function enterCompare(n) {
      n = n || 2;
      if (compareOn) { relayout(n); return; }
      compareOn = true;
      Object.keys(panels).forEach(function (k) { if (k !== 'pick') panels[k].close(); });
      var dicom = document.getElementById('dicom'); if (dicom) dicom.style.display = 'none';
      baseOverlays(true);
      if (!syncW) initSyncs();
      stage.appendChild(buildGrid(n));
      cmpBar.classList.add('show'); bCompare.classList.add('active');
      setLayoutButtons(n);
      vpLoadStudy(vps[0], HGV.STUDY);
      for (var i = 1; i < vps.length; i++) vps[i].showPick();
      setSync(syncOn);
      setTimeout(resizeVps, 70);
    }
    function relayout(n) {
      var prev = vps.map(function (v) { return v.uid; });
      teardownGrid();
      stage.appendChild(buildGrid(n));
      setLayoutButtons(n);
      vpLoadStudy(vps[0], prev[0] || HGV.STUDY);
      for (var i = 1; i < n; i++) { if (prev[i]) vpLoadStudy(vps[i], prev[i]); else vps[i].showPick(); }
      setSync(syncOn);
      setTimeout(resizeVps, 70);
    }
    function exitCompare() {
      if (!compareOn) return; compareOn = false;
      teardownGrid();
      var dicom = document.getElementById('dicom'); if (dicom) dicom.style.display = '';
      baseOverlays(false);
      cmpBar.classList.remove('show'); bCompare.classList.remove('active');
      setTimeout(function () { try { cs.resize(el, true); } catch (e) {} }, 70);
    }

    bCompare.addEventListener('click', function () { if (compareOn) exitCompare(); else enterCompare(2); });
    document.getElementById('hgv-cmp-exit').addEventListener('click', exitCompare);
    document.getElementById('hgv-sync').addEventListener('click', function () { setSync(!syncOn); });
    document.getElementById('hgv-layout').addEventListener('click', function (e) {
      var b = e.target.closest('button[data-n]'); if (!b) return; enterCompare(+b.getAttribute('data-n'));
    });
    window.addEventListener('resize', function () { if (compareOn) resizeVps(); });

    /* exponer para depuración / extensiones */
    HGV.pro = { toast: toast, makePanel: makePanel, mk: mk, toolBtn: toolBtn, addAfter: addAfter,
      enterCompare: enterCompare, exitCompare: exitCompare, getComparableStudies: getComparableStudies,
      loadInto: function (i, uid) { if (vps[i]) vpLoadStudy(vps[i], uid); }, vpCount: function () { return vps.length; },
      // Captura los 2 primeros viewports con imagen (para "Comparar con IA").
      captureComparePair: function () {
        if (!compareOn) return null;
        var withImg = vps.filter(function (v) { try { return !!cs.getImage(v.elem); } catch (e) { return false; } });
        if (withImg.length < 2) return null;
        function cap(elem) {
          var ee; try { ee = cs.getEnabledElement(elem); } catch (e) {}
          if (!ee || !ee.canvas) return null;
          var src = ee.canvas, mx = 1280, sc = Math.min(1, mx / Math.max(src.width, src.height));
          var c = document.createElement('canvas'); c.width = Math.max(1, Math.round(src.width * sc)); c.height = Math.max(1, Math.round(src.height * sc));
          var x = c.getContext('2d'); x.fillStyle = '#000'; x.fillRect(0, 0, c.width, c.height);
          try { x.drawImage(src, 0, 0, c.width, c.height); } catch (e) { return null; }
          try { return c.toDataURL('image/jpeg', 0.9); } catch (e) { return null; }
        }
        return { a: cap(withImg[0].elem), b: cap(withImg[1].elem) };
      } };

    /* limpiar realce al reset */
    var resetBtn = document.getElementById('t-reset');
    if (resetBtn) resetBtn.addEventListener('click', function () { if (curEnh !== 'normal') setEnh('normal'); });
  }

  /* arranque: window.HGV se define sincrónicamente por el visor (script previo);
     por robustez, reintentamos un poco si aún no está. */
  (function wait(n) {
    if (window.HGV) { try { boot(); } catch (e) { if (window.console) console.error('[visor-pro]', e); } return; }
    if (n > 120) return;
    setTimeout(function () { wait(n + 1); }, 25);
  })(0);
})();
