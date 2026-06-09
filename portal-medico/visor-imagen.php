<?php
/**
 * Visor de imágenes médicas (DICOM) del portal del médico.
 * Full-screen, aislado. Carga Cornerstone (motor de OHIF) desde CDN y consume
 * el DICOMweb del PACS Autana a través del proxy de mismo origen
 * /api/imaging-dwr.php/{scope} (que reenvía a JENOFONTE con el JWT del médico).
 *
 * Query: ?study=<StudyInstanceUID>&scope=<scope-token>
 */
require_once __DIR__ . '/_layout.php';
doctor_require_login();

$study = preg_replace('/[^0-9.]/', '', (string)($_GET['study'] ?? ''));
$scope = preg_replace('/[^A-Za-z0-9._-]/', '', (string)($_GET['scope'] ?? ''));
$dwrBase = base_url('api/imaging-dwr.php') . '/' . $scope;

if (!headers_sent()) { header('X-Robots-Tag: noindex, nofollow'); }
?>
<!DOCTYPE html>
<html lang="es-DO">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Visor de imágenes · Hospital General Las Colinas</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/png" href="<?= e(base_url('assets/site/favicon.png')) ?>">
<style>
    *{box-sizing:border-box;margin:0;padding:0}
    html,body{height:100%}
    body{background:#0b0e16;color:#e6e9f2;font-family:Inter,system-ui,Arial,sans-serif;overflow:hidden;display:flex;flex-direction:column}
    .v-top{display:flex;align-items:center;gap:14px;padding:9px 16px;background:#111726;border-bottom:1px solid #232c42}
    .v-top .ttl{font-weight:700;font-size:.95rem;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .v-top .meta{font-size:.78rem;color:#8b93a9;white-space:nowrap}
    .v-top .sp{flex:1}
    .v-tool{appearance:none;border:1px solid #2b3550;background:#1a2236;color:#cdd4e6;font:inherit;font-size:.8rem;border-radius:9px;padding:7px 11px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:background .12s,border-color .12s}
    .v-tool:hover{background:#222c45}
    .v-tool.active{background:#2f3e66;border-color:#4a5fa0;color:#fff}
    .v-tool svg{width:16px;height:16px}
    .v-main{flex:1;display:flex;min-height:0}
    .v-series{width:128px;background:#0e1320;border-right:1px solid #232c42;overflow-y:auto;flex:none}
    .v-series-item{padding:8px;cursor:pointer;border-bottom:1px solid #1a2030;text-align:center;font-size:.72rem;color:#9aa3bb}
    .v-series-item:hover{background:#161d2e}
    .v-series-item.active{background:#1d2a4a;color:#fff}
    .v-series-item .th{width:100%;height:84px;background:#000;border-radius:6px;object-fit:contain;display:block;margin-bottom:5px}
    .v-stage{flex:1;position:relative;min-width:0;background:#000}
    #dicom{width:100%;height:100%}
    .v-hud{position:absolute;left:12px;bottom:10px;font-size:.72rem;color:#aeb6cc;text-shadow:0 1px 2px #000;pointer-events:none;line-height:1.5}
    .v-hud2{position:absolute;right:12px;bottom:10px;font-size:.72rem;color:#aeb6cc;text-shadow:0 1px 2px #000;pointer-events:none;text-align:right;line-height:1.5}
    .v-msg{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:12px;color:#8b93a9;font-size:.9rem;text-align:center;padding:24px}
    .v-spin{width:38px;height:38px;border:3px solid #2b3550;border-top-color:#6d8bff;border-radius:50%;animation:vspin 1s linear infinite}
    @keyframes vspin{to{transform:rotate(360deg)}}
    .v-back{color:#cdd4e6;text-decoration:none;font-size:.85rem;display:inline-flex;align-items:center;gap:6px}
    .v-brand{display:inline-flex;align-items:center;background:#fff;border-radius:7px;padding:4px 9px;height:34px;flex:none}
    .v-brand img{height:24px;width:auto;display:block}
    .v-pdf{background:#2563eb;border-color:#3b82f6;color:#fff}
    .v-pdf:hover{background:#1d4ed8}
    .v-overlay{position:absolute;left:12px;top:10px;font-size:.74rem;color:#cfd6ea;text-shadow:0 1px 2px #000,0 0 4px #000;pointer-events:none;line-height:1.55;max-width:62%}
    .v-overlay b{color:#fff;font-weight:700}
    @media(max-width:640px){ .v-series{width:92px} .v-top .meta{display:none} .v-overlay{font-size:.68rem} }
</style>
</head>
<body>
<header class="v-top">
    <a href="javascript:window.close()" class="v-back" title="Cerrar">✕</a>
    <span class="v-brand"><img src="<?= e(base_url('assets/site/logo.png')) ?>" alt="Hospital General Las Colinas"></span>
    <span class="ttl" id="v-title">Visor de imágenes</span>
    <span class="meta" id="v-meta"></span>
    <span class="sp"></span>
    <button class="v-tool active" id="t-wwwc" title="Brillo/Contraste (arrastrar)">◐ Ventana</button>
    <button class="v-tool" id="t-zoom" title="Zoom (arrastrar)">⤢ Zoom</button>
    <button class="v-tool" id="t-pan" title="Mover (arrastrar)">✋ Mover</button>
    <button class="v-tool" id="t-length" title="Medir distancia">📏 Medir</button>
    <button class="v-tool" id="t-invert" title="Invertir">◑ Invertir</button>
    <button class="v-tool" id="t-reset" title="Restablecer">⟲ Reset</button>
    <button class="v-tool v-pdf" id="t-pdf" title="Exportar a PDF (con logo y datos del paciente)">⤓ PDF</button>
</header>
<div class="v-main">
    <aside class="v-series" id="v-series"></aside>
    <div class="v-stage">
        <div id="dicom"></div>
        <div class="v-overlay" id="v-overlay"></div>
        <div class="v-hud" id="v-hud"></div>
        <div class="v-hud2" id="v-hud2"></div>
        <div class="v-msg" id="v-msg"><div class="v-spin"></div><div id="v-msg-txt">Cargando estudio…</div></div>
    </div>
</div>

<?php $csv = (string)(@filemtime(__DIR__ . '/../assets/vendor/cornerstone/cornerstone.min.js') ?: 1); ?>
<script src="<?= e(base_url('assets/vendor/cornerstone/dicomParser.min.js')) ?>?v=<?= $csv ?>"></script>
<script src="<?= e(base_url('assets/vendor/cornerstone/cornerstone.min.js')) ?>?v=<?= $csv ?>"></script>
<script src="<?= e(base_url('assets/vendor/cornerstone/cornerstoneMath.min.js')) ?>?v=<?= $csv ?>"></script>
<script src="<?= e(base_url('assets/vendor/cornerstone/hammer.min.js')) ?>?v=<?= $csv ?>"></script>
<script src="<?= e(base_url('assets/vendor/cornerstone/cornerstoneTools.min.js')) ?>?v=<?= $csv ?>"></script>
<script src="<?= e(base_url('assets/vendor/cornerstone/cornerstoneWADOImageLoader.bundle.min.js')) ?>?v=<?= $csv ?>"></script>
<script src="<?= e(base_url('assets/vendor/jspdf/jspdf.umd.min.js')) ?>?v=<?= (string)(@filemtime(__DIR__ . '/../assets/vendor/jspdf/jspdf.umd.min.js') ?: 1) ?>"></script>
<script>
(function () {
    'use strict';
    var STUDY = <?= json_encode($study) ?>;
    var ROOT  = <?= json_encode($dwrBase, JSON_UNESCAPED_SLASHES) ?>;
    var msg = document.getElementById('v-msg'), msgTxt = document.getElementById('v-msg-txt');
    var el = document.getElementById('dicom');

    function fail(t) { msg.style.display = 'flex'; msg.innerHTML = '<div style="font-size:2rem">⚠</div><div>' + t + '</div>'; }
    if (!STUDY || !ROOT || ROOT.endsWith('/')) { return fail('Enlace de estudio inválido.'); }
    if (!window.cornerstone || !window.cornerstoneWADOImageLoader) { return fail('No se pudo cargar el módulo de imágenes.'); }

    var cornerstone = window.cornerstone, cstools = window.cornerstoneTools, cwil = window.cornerstoneWADOImageLoader;

    cwil.external.cornerstone = cornerstone;
    cwil.external.dicomParser = window.dicomParser;
    cstools.external.cornerstone = cornerstone;
    cstools.external.cornerstoneMath = window.cornerstoneMath;
    cstools.external.Hammer = window.Hammer;
    try {
        cwil.configure({ decodeConfig: { use16BitDataType: true } });
    } catch (e) {}
    cstools.init({ showSVGCursors: true });

    cornerstone.enable(el);

    var imageIds = [], stack = { currentImageIdIndex: 0, imageIds: [] };
    var studyMeta = {};
    var CLINIC = 'Hospital General Las Colinas';
    var logoImg = new Image(); logoImg.src = <?= json_encode(base_url('assets/site/logo.png'), JSON_UNESCAPED_SLASHES) ?>;
    function escH(s) { return String(s == null ? '' : s).replace(/[&<>]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]; }); }
    function fdate(d) { return (d && d.length >= 8) ? (d.slice(6, 8) + '/' + d.slice(4, 6) + '/' + d.slice(0, 4)) : ''; }
    function pn(md, t) { try { var v = md[t].Value[0]; return v && v.Alphabetic ? v.Alphabetic : (typeof v === 'string' ? v : ''); } catch (e) { return ''; } }

    function dj(url) { return fetch(url, { headers: { Accept: 'application/dicom+json' }, credentials: 'same-origin' }).then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); }); }
    function tag(md, t) { try { return md[t].Value; } catch (e) { return undefined; } }
    function tag1(md, t) { var v = tag(md, t); return v && v.length ? v[0] : undefined; }

    function setActiveTool(name, btnId) {
        ['Wwwc', 'Zoom', 'Pan', 'Length'].forEach(function (n) { try { cstools.setToolPassive(n); } catch (e) {} });
        try { cstools.setToolActive(name, { mouseButtonMask: 1 }); } catch (e) {}
        document.querySelectorAll('.v-tool').forEach(function (b) { b.classList.remove('active'); });
        if (btnId) document.getElementById(btnId).classList.add('active');
    }

    function updateHud() {
        try {
            var vp = cornerstone.getViewport(el);
            document.getElementById('v-hud2').textContent =
                'WW/WC: ' + Math.round(vp.voi.windowWidth) + ' / ' + Math.round(vp.voi.windowCenter) +
                '\nZoom: ' + (vp.scale).toFixed(2) + 'x';
            document.getElementById('v-hud').textContent =
                'Imagen ' + (stack.currentImageIdIndex + 1) + ' / ' + stack.imageIds.length;
        } catch (e) {}
    }

    function showIndex(i) {
        if (i < 0 || i >= stack.imageIds.length) return;
        stack.currentImageIdIndex = i;
        try { var st = cstools.getToolState(el, 'stack'); if (st && st.data && st.data[0]) st.data[0].currentImageIdIndex = i; } catch (e) {}
        cornerstone.loadAndCacheImage(stack.imageIds[i]).then(function (image) {
            cornerstone.displayImage(el, image);
            updateHud();
        }).catch(function (e) { fail('No se pudo cargar la imagen. ' + (e && e.message ? e.message : '')); });
    }

    function updateOverlay() {
        var o = document.getElementById('v-overlay');
        if (!o) return;
        if (!studyMeta.pname) { o.innerHTML = ''; return; }
        var l2 = [studyMeta.dob ? 'F. nac.: ' + fdate(studyMeta.dob) : '', studyMeta.sex || ''].filter(Boolean).join('  ·  ');
        var l3 = [studyMeta.studyDate ? fdate(studyMeta.studyDate) : '', studyMeta.modality || ''].filter(Boolean).join('  ·  ');
        o.innerHTML = '<b>' + escH(studyMeta.pname) + '</b>' + (studyMeta.pid ? '  ·  ' + escH(studyMeta.pid) : '')
            + (l2 ? '<br>' + escH(l2) : '') + (l3 ? '<br>' + escH(l3) : '');
    }

    // 1) Listar series del estudio
    msgTxt.textContent = 'Buscando series…';
    dj(ROOT + '/studies/' + STUDY + '/series').then(function (series) {
        if (!series.length) return fail('El estudio no tiene series.');
        series.sort(function (a, b) { return (tag1(a, '00200011') || 0) - (tag1(b, '00200011') || 0); });
        var box = document.getElementById('v-series');
        box.innerHTML = '';
        series.forEach(function (s, idx) {
            var su = tag1(s, '0020000E');
            var mod = tag1(s, '00080060') || '';
            var desc = tag1(s, '0008103E') || ('Serie ' + (idx + 1));
            var n = tag1(s, '00201209') || '';
            var item = document.createElement('div');
            item.className = 'v-series-item' + (idx === 0 ? ' active' : '');
            item.innerHTML = '<div class="th" style="display:grid;place-items:center;color:#8b93a9;font-weight:700">' + mod + '</div><div>' + desc + '</div><div style="color:#5e6a85">' + n + ' img</div>';
            item.addEventListener('click', function () {
                document.querySelectorAll('.v-series-item').forEach(function (x) { x.classList.remove('active'); });
                item.classList.add('active');
                loadSeries(su, mod, desc);
            });
            box.appendChild(item);
        });
        var first = series[0];
        loadSeries(tag1(first, '0020000E'), tag1(first, '00080060') || '', tag1(first, '0008103E') || 'Serie 1');
        document.getElementById('v-meta').textContent = series.length + ' serie(s)';
    }).catch(function (e) { fail('No se pudieron cargar las series. ' + (e && e.message ? e.message : '')); });

    function loadSeries(seriesUID, mod, desc) {
        if (!seriesUID) return;
        msg.style.display = 'flex'; msgTxt.textContent = 'Cargando imágenes…';
        document.getElementById('v-title').textContent = (desc || 'Estudio') + (mod ? ' · ' + mod : '');
        dj(ROOT + '/studies/' + STUDY + '/series/' + seriesUID + '/metadata').then(function (insts) {
            insts.sort(function (a, b) { return (tag1(a, '00200013') || 0) - (tag1(b, '00200013') || 0); });
            if (!studyMeta.captured && insts.length) {
                var m0 = insts[0];
                studyMeta = {
                    captured: true,
                    pname: (pn(m0, '00100010') || '').replace(/\^/g, ' ').replace(/\s+/g, ' ').trim(),
                    pid: tag1(m0, '00100020') || '',
                    dob: tag1(m0, '00100030') || '',
                    sex: tag1(m0, '00100040') || '',
                    studyDate: tag1(m0, '00080020') || '',
                    studyDesc: tag1(m0, '00081030') || '',
                    accession: tag1(m0, '00080050') || '',
                    modality: tag1(m0, '00080060') || mod || ''
                };
                updateOverlay();
            }
            var ids = [];
            insts.forEach(function (md) {
                var sop = tag1(md, '00080018'); if (!sop) return;
                var frames = parseInt(tag1(md, '00280008') || '1', 10) || 1;
                for (var f = 1; f <= frames; f++) {
                    var id = 'wadors:' + ROOT + '/studies/' + STUDY + '/series/' + seriesUID + '/instances/' + sop + '/frames/' + f;
                    cwil.wadors.metaDataManager.add(id, md);
                    ids.push(id);
                }
            });
            if (!ids.length) return fail('La serie no tiene imágenes.');
            stack.imageIds = ids; stack.currentImageIdIndex = 0;
            return cornerstone.loadAndCacheImage(ids[0]).then(function (image) {
                cornerstone.displayImage(el, image);
                // (re)montar herramientas de stack
                cstools.clearToolState(el, 'stack');
                cstools.addToolState(el, 'stack', { currentImageIdIndex: 0, imageIds: ids });
                ensureTools();
                msg.style.display = 'none';
                updateHud();
            });
        }).catch(function (e) { fail('No se pudieron cargar las imágenes. ' + (e && e.message ? e.message : '')); });
    }

    var toolsReady = false;
    function ensureTools() {
        if (toolsReady) return; toolsReady = true;
        cstools.addTool(cstools.WwwcTool);
        cstools.addTool(cstools.ZoomTool);
        cstools.addTool(cstools.PanTool);
        cstools.addTool(cstools.LengthTool);
        cstools.addTool(cstools.StackScrollMouseWheelTool);
        cstools.addTool(cstools.ZoomMouseWheelTool);
        cstools.setToolActive('StackScrollMouseWheel', {});
        cstools.setToolActive('Wwwc', { mouseButtonMask: 1 });
        cstools.setToolActive('Pan', { mouseButtonMask: 4 });
        cstools.setToolActive('Zoom', { mouseButtonMask: 2 });
        el.addEventListener('cornerstoneimagerendered', updateHud);
        el.addEventListener('cornerstonenewimage', function (e) {
            try { stack.currentImageIdIndex = stack.imageIds.indexOf(e.detail.image.imageId); } catch (x) {}
            updateHud();
        });
    }

    // Toolbar
    document.getElementById('t-wwwc').addEventListener('click', function () { setActiveTool('Wwwc', 't-wwwc'); });
    document.getElementById('t-zoom').addEventListener('click', function () { setActiveTool('Zoom', 't-zoom'); });
    document.getElementById('t-pan').addEventListener('click', function () { setActiveTool('Pan', 't-pan'); });
    document.getElementById('t-length').addEventListener('click', function () { setActiveTool('Length', 't-length'); });
    document.getElementById('t-invert').addEventListener('click', function () {
        try { var vp = cornerstone.getViewport(el); vp.invert = !vp.invert; cornerstone.setViewport(el, vp); } catch (e) {}
    });
    document.getElementById('t-reset').addEventListener('click', function () {
        try { cornerstone.reset(el); updateHud(); } catch (e) {}
    });
    document.getElementById('t-pdf').addEventListener('click', generatePdf);

    // Atajos de teclado: ←→ cortes · +/- zoom · I invertir · R reset
    window.addEventListener('keydown', function (e) {
        if (e.target && /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) return;
        var vp;
        switch (e.key) {
            case 'ArrowRight': case 'ArrowDown': showIndex(stack.currentImageIdIndex + 1); e.preventDefault(); break;
            case 'ArrowLeft':  case 'ArrowUp':   showIndex(stack.currentImageIdIndex - 1); e.preventDefault(); break;
            case '+': case '=': try { vp = cornerstone.getViewport(el); vp.scale *= 1.2; cornerstone.setViewport(el, vp); } catch (x) {} e.preventDefault(); break;
            case '-': case '_': try { vp = cornerstone.getViewport(el); vp.scale /= 1.2; cornerstone.setViewport(el, vp); } catch (x) {} e.preventDefault(); break;
            case 'i': case 'I': try { vp = cornerstone.getViewport(el); vp.invert = !vp.invert; cornerstone.setViewport(el, vp); } catch (x) {} break;
            case 'r': case 'R': try { cornerstone.reset(el); updateHud(); } catch (x) {} break;
        }
    });

    function tr(s, n) { s = String(s == null || s === '' ? '—' : s); return s.length > n ? s.slice(0, n - 1) + '…' : s; }

    function generatePdf() {
        if (!window.jspdf || !window.jspdf.jsPDF) { alert('No se pudo cargar el generador de PDF.'); return; }
        var ee; try { ee = cornerstone.getEnabledElement(el); } catch (e) {}
        if (!ee || !ee.canvas) { alert('No hay imagen para exportar.'); return; }
        var imgData = ee.canvas.toDataURL('image/jpeg', 0.92);
        var iw = ee.canvas.width, ih = ee.canvas.height;

        var jsPDF = window.jspdf.jsPDF;
        var doc = new jsPDF({ unit: 'pt', format: 'letter' });
        var W = 612, H = 792, M = 40;
        var navy = [42, 37, 102], gray = [100, 116, 139], line = [214, 218, 228];

        // Encabezado: logo + título
        doc.setFillColor(244, 245, 250); doc.rect(0, 0, W, 84, 'F');
        doc.setDrawColor(line[0], line[1], line[2]); doc.setLineWidth(1); doc.line(0, 84, W, 84);
        if (logoImg.complete && logoImg.naturalWidth) {
            var lh = 44, lw = lh * (logoImg.naturalWidth / logoImg.naturalHeight);
            if (lw > 230) { lw = 230; lh = lw * (logoImg.naturalHeight / logoImg.naturalWidth); }
            try { doc.addImage(logoImg, 'PNG', M, (84 - lh) / 2, lw, lh); } catch (e) {}
        }
        doc.setTextColor(navy[0], navy[1], navy[2]); doc.setFont('helvetica', 'bold'); doc.setFontSize(15);
        doc.text('Estudio de Imagen', W - M, 40, { align: 'right' });
        doc.setFont('helvetica', 'normal'); doc.setFontSize(9); doc.setTextColor(gray[0], gray[1], gray[2]);
        doc.text(CLINIC + ' · Radiología', W - M, 56, { align: 'right' });

        // Datos paciente / estudio (dos columnas)
        var y = 112;
        function block(x, title, rows) {
            doc.setFont('helvetica', 'bold'); doc.setFontSize(8); doc.setTextColor(navy[0], navy[1], navy[2]);
            doc.text(title.toUpperCase(), x, y);
            doc.setFont('helvetica', 'normal'); doc.setFontSize(9.5);
            var yy = y + 16;
            rows.forEach(function (r) {
                doc.setTextColor(gray[0], gray[1], gray[2]); doc.text(r[0], x, yy);
                doc.setTextColor(30, 37, 64); doc.text(tr(r[1], 30), x + 82, yy);
                yy += 15;
            });
            return yy;
        }
        var le = block(M, 'Paciente', [
            ['Nombre:', studyMeta.pname], ['Identificación:', studyMeta.pid],
            ['F. nacimiento:', fdate(studyMeta.dob) || '—'], ['Sexo:', studyMeta.sex]
        ]);
        var re = block(W / 2 + 12, 'Estudio', [
            ['Modalidad:', studyMeta.modality], ['Fecha:', fdate(studyMeta.studyDate) || '—'],
            ['Descripción:', studyMeta.studyDesc], ['Accession:', studyMeta.accession]
        ]);
        var ib = Math.max(le, re) + 6;
        doc.setDrawColor(line[0], line[1], line[2]); doc.line(M, ib, W - M, ib);

        // Imagen sobre fondo negro, encuadrada
        var bx = M, by = ib + 14, bw = W - 2 * M, bh = H - by - 54;
        doc.setFillColor(0, 0, 0); doc.rect(bx, by, bw, bh, 'F');
        var sc = Math.min(bw / iw, bh / ih), dw = iw * sc, dh = ih * sc;
        try { doc.addImage(imgData, 'JPEG', bx + (bw - dw) / 2, by + (bh - dh) / 2, dw, dh); } catch (e) {}

        // Pie
        var n = new Date(), p2 = function (x) { return ('0' + x).slice(-2); };
        var stamp = p2(n.getDate()) + '/' + p2(n.getMonth() + 1) + '/' + n.getFullYear() + ' ' + p2(n.getHours()) + ':' + p2(n.getMinutes());
        doc.setDrawColor(line[0], line[1], line[2]); doc.line(M, H - 42, W - M, H - 42);
        doc.setFont('helvetica', 'normal'); doc.setFontSize(7.5); doc.setTextColor(gray[0], gray[1], gray[2]);
        doc.text(CLINIC + ' · Generado electrónicamente el ' + stamp, M, H - 28);
        doc.text('Imagen de referencia clínica — no sustituye el informe radiológico oficial.', M, H - 18);
        doc.text((document.getElementById('v-title').textContent || '').slice(0, 40), W - M, H - 28, { align: 'right' });

        var fn = ('Estudio_' + (studyMeta.accession || studyMeta.modality || 'imagen') + '_' + (studyMeta.studyDate || '')).replace(/[^A-Za-z0-9._-]/g, '_') + '.pdf';
        doc.save(fn);
    }

    window.addEventListener('resize', function () { try { cornerstone.resize(el, true); } catch (e) {} });
})();
</script>
</body>
</html>
