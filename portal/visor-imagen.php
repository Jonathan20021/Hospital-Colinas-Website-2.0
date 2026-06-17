<?php
/**
 * Visor de imágenes médicas (DICOM) del PORTAL DEL PACIENTE.
 * Full-screen, aislado, SIMPLIFICADO (sin IA ni herramientas clínicas de medición).
 * Consume el DICOMweb del PACS a través del proxy de mismo origen
 * /api/portal-imaging-dwr.php/{scope} (que reenvía a JENOFONTE con el JWT del paciente).
 *
 * Query: ?study=<StudyInstanceUID>&scope=<scope-token>
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/portal_client.php';
require_once __DIR__ . '/../includes/portal_session.php';
portal_require_login();

$study = preg_replace('/[^0-9.]/', '', (string)($_GET['study'] ?? ''));
$scope = preg_replace('/[^A-Za-z0-9._-]/', '', (string)($_GET['scope'] ?? ''));
$dwrBase = base_url('api/portal-imaging-dwr.php') . '/' . $scope;

if (!headers_sent()) {
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}
?>
<!DOCTYPE html>
<html lang="es-DO">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Mis imágenes · Hospital General Las Colinas</title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#262161">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="icon" type="image/png" href="<?= e(base_url('assets/site/favicon.png')) ?>">
<style>
    *{box-sizing:border-box;margin:0;padding:0}
    html,body{height:100%;overscroll-behavior:none}
    body{background:#0b0e16;color:#e6e9f2;font-family:Inter,system-ui,Arial,sans-serif;overflow:hidden;display:flex;flex-direction:column;-webkit-tap-highlight-color:transparent}
    .v-top{display:flex;align-items:center;gap:8px 12px;padding:calc(9px + env(safe-area-inset-top)) calc(14px + env(safe-area-inset-right)) 9px calc(14px + env(safe-area-inset-left));background:#111726;border-bottom:1px solid #232c42;flex-wrap:wrap}
    .v-top .ttl{font-weight:700;font-size:.95rem;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:240px}
    .v-top .meta{font-size:.78rem;color:#8b93a9;white-space:nowrap}
    .v-tool{appearance:none;border:1px solid #2b3550;background:#1a2236;color:#cdd4e6;font:inherit;font-size:.8rem;border-radius:9px;padding:7px 10px;cursor:pointer;display:inline-flex;align-items:center;gap:5px;transition:background .12s,border-color .12s;white-space:nowrap;-webkit-tap-highlight-color:transparent}
    .v-tool:hover{background:#222c45}
    .v-tool.active{background:#2f3e66;border-color:#4a5fa0;color:#fff}
    .v-toolbar{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-left:auto}
    .v-sep{width:1px;align-self:stretch;min-height:22px;background:#2b3550;margin:0 1px}
    .v-back{color:#cdd4e6;text-decoration:none;font-size:.85rem;display:inline-flex;align-items:center;gap:6px}
    .v-brand{display:inline-flex;align-items:center;background:#fff;border-radius:7px;padding:4px 9px;height:34px;flex:none}
    .v-brand img{height:24px;width:auto;display:block}
    .v-pdf{background:#4b972d;border-color:#4b972d;color:#fff}
    .v-pdf:hover{background:#397b22;border-color:#397b22}
    .v-disc{background:rgba(180,120,30,.14);color:#f5c884;font-size:.74rem;line-height:1.4;padding:7px calc(14px + env(safe-area-inset-right)) 7px calc(14px + env(safe-area-inset-left));border-bottom:1px solid #232c42;text-align:center}
    .v-disc b{color:#ffd9a0}
    .v-main{flex:1;display:flex;min-height:0;position:relative}
    .v-series{width:128px;background:#0e1320;border-right:1px solid #232c42;overflow-y:auto;flex:none}
    .v-series-item{padding:8px;cursor:pointer;border-bottom:1px solid #1a2030;text-align:center;font-size:.72rem;color:#9aa3bb}
    .v-series-item:hover{background:#161d2e}
    .v-series-item.active{background:#1d2a4a;color:#fff}
    .v-series-item .th{width:100%;height:84px;background:#000;border-radius:6px;object-fit:contain;display:block;margin-bottom:5px}
    .v-series-item .th.ph{display:grid;place-items:center;color:#8b93a9;font-weight:700;font-size:.95rem}
    .v-stage{flex:1;position:relative;min-width:0;background:#000;touch-action:none}
    #dicom{width:100%;height:100%;touch-action:none}
    .v-hud2{position:absolute;right:12px;bottom:10px;font-size:.72rem;color:#aeb6cc;text-shadow:0 1px 2px #000;pointer-events:none;text-align:right;line-height:1.5}
    .v-msg{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:12px;color:#8b93a9;font-size:.9rem;text-align:center;padding:24px}
    .v-msg-icon{font-size:2rem}
    .v-spin{width:38px;height:38px;border:3px solid #2b3550;border-top-color:#6d8bff;border-radius:50%;animation:vspin 1s linear infinite}
    @keyframes vspin{to{transform:rotate(360deg)}}
    .v-overlay{position:absolute;left:12px;top:10px;font-size:.74rem;color:#cfd6ea;text-shadow:0 1px 2px #000,0 0 4px #000;pointer-events:none;line-height:1.55;max-width:62%}
    .v-overlay b{color:#fff;font-weight:700}
    .v-nav{position:absolute;left:50%;bottom:calc(10px + env(safe-area-inset-bottom));transform:translateX(-50%);display:none;align-items:center;gap:8px;background:rgba(17,23,38,.78);backdrop-filter:blur(6px);border:1px solid #2b3550;border-radius:999px;padding:5px 8px;z-index:6}
    .v-nav button{appearance:none;border:0;background:#1a2236;color:#cdd4e6;width:34px;height:34px;border-radius:50%;cursor:pointer;font-size:1.3rem;line-height:1;display:grid;place-items:center}
    .v-nav button:hover{background:#28324e}
    .v-nav button:disabled{opacity:.3;cursor:default}
    .v-nav .cnt{font-size:.8rem;color:#e6e9f2;min-width:58px;text-align:center;font-variant-numeric:tabular-nums}
    body.single-series .v-series{display:none}
    body.single-series .v-series-toggle{display:none!important}
    .v-series-toggle{display:none}
    .v-series-count{color:#7f8aa4}
    .v-notice{position:fixed;right:16px;bottom:16px;z-index:20;max-width:min(360px,calc(100vw - 32px));padding:13px 15px;border:1px solid #efb8c5;border-radius:10px;background:#fff0f3;color:#8d1932;font-size:.88rem;box-shadow:0 18px 40px -25px rgba(0,0,0,.8)}
    @media (pointer:coarse){
        .v-tool{padding:9px 12px;font-size:.82rem}
        .v-nav button{width:46px;height:46px;font-size:1.5rem}
        .v-series-item{padding:11px 8px}
    }
    @media (max-width:760px){
        .v-top .ttl{max-width:42vw}
        .v-top .meta{display:none}
        .v-series-toggle{display:inline-flex}
        .v-toolbar{flex-wrap:nowrap;overflow-x:auto;overflow-y:hidden;width:100%;margin-left:0;padding-bottom:4px;-webkit-overflow-scrolling:touch;scrollbar-width:none}
        .v-toolbar::-webkit-scrollbar{display:none}
        .v-tool{flex:none}
        .v-sep{display:none}
        .v-series{position:absolute;left:0;top:0;bottom:0;width:160px;z-index:8;transform:translateX(-100%);transition:transform .25s ease;box-shadow:6px 0 26px rgba(0,0,0,.55)}
        .v-series.open{transform:translateX(0)}
        .v-overlay{font-size:.66rem;max-width:80%}
    }
</style>
</head>
<body>
<header class="v-top">
    <a href="#" class="v-back" id="v-close" title="Cerrar" role="button">✕</a>
    <span class="v-brand"><img src="<?= e(base_url('assets/site/logo.png')) ?>" alt="Hospital General Las Colinas"></span>
    <span class="ttl" id="v-title">Mis imágenes</span>
    <span class="meta" id="v-meta"></span>
    <div class="v-toolbar">
        <button class="v-tool v-series-toggle" id="t-series" title="Series del estudio">🗂 Series</button>
        <button class="v-tool active" id="t-wwwc" title="Brillo/Contraste (arrastrar)">◐ Brillo</button>
        <button class="v-tool" id="t-zoom" title="Zoom (arrastrar)">⤢ Zoom</button>
        <button class="v-tool" id="t-pan" title="Mover (arrastrar)">✋ Mover</button>
        <span class="v-sep"></span>
        <button class="v-tool" id="t-invert" title="Invertir (I)">◑ Invertir</button>
        <button class="v-tool" id="t-cine" title="Reproducir (multi-imagen)" style="display:none">▶ Cine</button>
        <button class="v-tool" id="t-reset" title="Restablecer (R)">⟲ Reset</button>
        <span class="v-sep"></span>
        <button class="v-tool" id="t-fs" title="Pantalla completa (F)">⛶</button>
        <button class="v-tool v-pdf" id="t-pdf" title="Descargar en PDF">⤓ PDF</button>
    </div>
</header>
<div class="v-disc">⚠ Estas imágenes son de <b>referencia</b>. La interpretación corresponde a tu médico o radiólogo. Ante cualquier duda, consulta a tu especialista.</div>
<div class="v-notice" id="v-notice" role="alert" hidden></div>
<div class="v-main">
    <aside class="v-series" id="v-series"></aside>
    <div class="v-stage">
        <div id="dicom"></div>
        <div class="v-overlay" id="v-overlay"></div>
        <div class="v-hud2" id="v-hud2"></div>
        <div class="v-nav" id="v-nav">
            <button id="v-prev" title="Imagen anterior (←)" aria-label="Anterior">‹</button>
            <span class="cnt" id="v-nav-cnt">1 / 1</span>
            <button id="v-next" title="Imagen siguiente (→)" aria-label="Siguiente">›</button>
        </div>
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
    var ESTUDIOS_URL = <?= json_encode(base_url('portal/estudios.php'), JSON_UNESCAPED_SLASHES) ?>;
    var msg = document.getElementById('v-msg'), msgTxt = document.getElementById('v-msg-txt');
    var el = document.getElementById('dicom');

    function fail(t) { msg.style.display = 'flex'; msg.innerHTML = '<div class="v-msg-icon">⚠</div><div>' + t + '</div>'; }
    function notice(t) {
        var n = document.getElementById('v-notice');
        if (!n) return;
        n.textContent = t;
        n.hidden = false;
        window.setTimeout(function () { n.hidden = true; }, 5000);
    }
    if (!STUDY || !ROOT || ROOT.endsWith('/')) { return fail('Enlace de estudio inválido.'); }
    if (!window.cornerstone || !window.cornerstoneWADOImageLoader) { return fail('No se pudo cargar el módulo de imágenes.'); }

    var cornerstone = window.cornerstone, cstools = window.cornerstoneTools, cwil = window.cornerstoneWADOImageLoader;
    cwil.external.cornerstone = cornerstone;
    cwil.external.dicomParser = window.dicomParser;
    cstools.external.cornerstone = cornerstone;
    cstools.external.cornerstoneMath = window.cornerstoneMath;
    cstools.external.Hammer = window.Hammer;
    try { cwil.configure({ decodeConfig: { use16BitDataType: true } }); } catch (e) {}
    cstools.init({ showSVGCursors: true });
    cornerstone.enable(el);

    var stack = { currentImageIdIndex: 0, imageIds: [] };
    var studyMeta = {}, currentSeriesDesc = '', cineOn = false, cineTimer = null, currentTool = 'Wwwc';
    var CLINIC = 'Hospital General Las Colinas';
    var logoImg = new Image(); logoImg.src = <?= json_encode(base_url('assets/site/logo.png'), JSON_UNESCAPED_SLASHES) ?>;
    function escH(s) { return String(s == null ? '' : s).replace(/[&<>]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]; }); }
    function fdate(d) { return (d && d.length >= 8) ? (d.slice(6, 8) + '/' + d.slice(4, 6) + '/' + d.slice(0, 4)) : ''; }
    function pn(md, t) { try { var v = md[t].Value[0]; return v && v.Alphabetic ? v.Alphabetic : (typeof v === 'string' ? v : ''); } catch (e) { return ''; } }
    function dj(url) { return fetch(url, { headers: { Accept: 'application/dicom+json' }, credentials: 'same-origin' }).then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); }); }
    function tag(md, t) { try { return md[t].Value; } catch (e) { return undefined; } }
    function tag1(md, t) { var v = tag(md, t); return v && v.length ? v[0] : undefined; }

    var EXCLUSIVE = ['Wwwc', 'Zoom', 'Pan'];
    function setActiveTool(name, btnId) {
        currentTool = name;
        EXCLUSIVE.forEach(function (n) { try { cstools.setToolPassive(n); } catch (e) {} });
        try { cstools.setToolActive(name, { mouseButtonMask: 1 }); } catch (e) {}
        document.querySelectorAll('.v-tool').forEach(function (b) { b.classList.remove('active'); });
        if (btnId) document.getElementById(btnId).classList.add('active');
        if (cineOn) { var cb = document.getElementById('t-cine'); if (cb) cb.classList.add('active'); }
    }

    function renderNav() {
        var n = stack.imageIds.length, i = stack.currentImageIdIndex;
        var nav = document.getElementById('v-nav'); if (!nav) return;
        nav.style.display = n > 1 ? 'flex' : 'none';
        var c = document.getElementById('v-nav-cnt'); if (c) { c.textContent = (i + 1) + ' / ' + n; c.style.color = ''; }
        var pv = document.getElementById('v-prev'), nx = document.getElementById('v-next');
        if (pv) pv.disabled = i <= 0;
        if (nx) nx.disabled = i >= n - 1;
        var cine = document.getElementById('t-cine'); if (cine) cine.style.display = n > 1 ? '' : 'none';
    }

    function updateHud() {
        try {
            var vp = cornerstone.getViewport(el);
            document.getElementById('v-hud2').textContent =
                'Brillo: ' + Math.round(vp.voi.windowWidth) + ' / ' + Math.round(vp.voi.windowCenter) +
                '\nZoom: ' + (vp.scale).toFixed(2) + 'x';
        } catch (e) {}
        renderNav();
    }

    function showIndex(i) {
        if (i < 0 || i >= stack.imageIds.length) return;
        stack.currentImageIdIndex = i;
        try { var st = cstools.getToolState(el, 'stack'); if (st && st.data && st.data[0]) st.data[0].currentImageIdIndex = i; } catch (e) {}
        renderNav();
        cornerstone.loadAndCacheImage(stack.imageIds[i]).then(function (image) {
            cornerstone.displayImage(el, image);
            updateHud();
        }).catch(function () {
            var c = document.getElementById('v-nav-cnt'); if (c) { c.textContent = '⚠ img ' + (i + 1); c.style.color = '#fca5a5'; }
        });
    }

    function updateOverlay() {
        var o = document.getElementById('v-overlay');
        if (!o) return;
        if (!studyMeta.pname) { o.innerHTML = ''; return; }
        var l3 = [studyMeta.studyDate ? fdate(studyMeta.studyDate) : '', studyMeta.modality || ''].filter(Boolean).join('  ·  ');
        o.innerHTML = '<b>' + escH(studyMeta.pname) + '</b>' + (l3 ? '<br>' + escH(l3) : '');
    }

    function loadThumb(su, ph) {
        if (!ph) return;
        dj(ROOT + '/studies/' + STUDY + '/series/' + su + '/instances?includefield=00080018').then(function (insts) {
            if (!insts || !insts.length) return;
            var sop = tag1(insts[0], '00080018'); if (!sop) return;
            var im = new Image(); im.className = 'th'; im.alt = '';
            im.onload = function () { try { ph.replaceWith(im); } catch (e) {} };
            im.src = ROOT + '/studies/' + STUDY + '/series/' + su + '/instances/' + sop + '/rendered?viewport=160,160&quality=80';
        }).catch(function () {});
    }

    // 1) Listar series
    msgTxt.textContent = 'Buscando series…';
    dj(ROOT + '/studies/' + STUDY + '/series').then(function (series) {
        if (!series.length) return fail('El estudio no tiene series.');
        series.sort(function (a, b) { return (tag1(a, '00200011') || 0) - (tag1(b, '00200011') || 0); });
        document.body.classList.toggle('single-series', series.length <= 1);
        var box = document.getElementById('v-series'); box.innerHTML = '';
        series.forEach(function (s, idx) {
            var su = tag1(s, '0020000E'), mod = tag1(s, '00080060') || '', desc = tag1(s, '0008103E') || ('Serie ' + (idx + 1)), n = tag1(s, '00201209') || '';
            var item = document.createElement('div');
            item.className = 'v-series-item' + (idx === 0 ? ' active' : '');
            item.innerHTML = '<div class="th ph">' + escH(mod || '—') + '</div><div>' + escH(desc) + '</div><div class="v-series-count">' + escH(String(n)) + ' img</div>';
            item.addEventListener('click', function () {
                document.querySelectorAll('.v-series-item').forEach(function (x) { x.classList.remove('active'); });
                item.classList.add('active'); loadSeries(su, mod, desc); box.classList.remove('open');
            });
            box.appendChild(item);
            loadThumb(su, item.querySelector('.th.ph'));
        });
        var first = series[0];
        loadSeries(tag1(first, '0020000E'), tag1(first, '00080060') || '', tag1(first, '0008103E') || 'Serie 1');
        document.getElementById('v-meta').textContent = series.length + ' serie(s)';
    }).catch(function (e) { fail('No se pudieron cargar las series. ' + (e && e.message ? e.message : '')); });

    function loadSeries(seriesUID, mod, desc) {
        if (!seriesUID) return;
        currentSeriesDesc = desc || ''; stopCine();
        msg.style.display = 'flex'; msgTxt.textContent = 'Cargando imágenes…';
        document.getElementById('v-title').textContent = (desc || 'Estudio') + (mod ? ' · ' + mod : '');
        dj(ROOT + '/studies/' + STUDY + '/series/' + seriesUID + '/metadata').then(function (insts) {
            insts.sort(function (a, b) { return (tag1(a, '00200013') || 0) - (tag1(b, '00200013') || 0); });
            if (!studyMeta.captured && insts.length) {
                var m0 = insts[0];
                studyMeta = {
                    captured: true,
                    pname: (pn(m0, '00100010') || '').replace(/\^/g, ' ').replace(/\s+/g, ' ').trim(),
                    pid: tag1(m0, '00100020') || '', dob: tag1(m0, '00100030') || '', sex: tag1(m0, '00100040') || '',
                    studyDate: tag1(m0, '00080020') || '', studyDesc: tag1(m0, '00081030') || '',
                    accession: tag1(m0, '00080050') || '', modality: tag1(m0, '00080060') || mod || ''
                };
                updateOverlay();
            }
            var ids = [];
            insts.forEach(function (md) {
                var sop = tag1(md, '00080018'); if (!sop) return;
                var frames = parseInt(tag1(md, '00280008') || '1', 10) || 1;
                for (var f = 1; f <= frames; f++) {
                    var id = 'wadors:' + ROOT + '/studies/' + STUDY + '/series/' + seriesUID + '/instances/' + sop + '/frames/' + f;
                    cwil.wadors.metaDataManager.add(id, md); ids.push(id);
                }
            });
            if (!ids.length) return fail('La serie no tiene imágenes.');
            stack.imageIds = ids; stack.currentImageIdIndex = 0;
            return cornerstone.loadAndCacheImage(ids[0]).then(function (image) {
                cornerstone.displayImage(el, image);
                cstools.clearToolState(el, 'stack');
                cstools.addToolState(el, 'stack', { currentImageIdIndex: 0, imageIds: ids });
                ensureTools(); msg.style.display = 'none'; updateHud();
            });
        }).catch(function (e) { fail('No se pudieron cargar las imágenes. ' + (e && e.message ? e.message : '')); });
    }

    var toolsReady = false;
    function addT(tool, cfg) { try { if (tool) cstools.addTool(tool, cfg); } catch (e) {} }
    function actT(name, opt) { try { cstools.setToolActive(name, opt || {}); } catch (e) {} }
    function ensureTools() {
        if (toolsReady) return; toolsReady = true;
        addT(cstools.WwwcTool); addT(cstools.ZoomTool); addT(cstools.PanTool);
        addT(cstools.StackScrollMouseWheelTool); addT(cstools.ZoomMouseWheelTool);
        addT(cstools.PanMultiTouchTool); addT(cstools.ZoomTouchPinchTool); addT(cstools.StackScrollMultiTouchTool);
        actT('StackScrollMouseWheel'); actT('Wwwc', { mouseButtonMask: 1 });
        actT('Pan', { mouseButtonMask: 4 }); actT('Zoom', { mouseButtonMask: 2 });
        actT('PanMultiTouch'); actT('ZoomTouchPinch'); actT('StackScrollMultiTouch');
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
    document.getElementById('t-invert').addEventListener('click', function () {
        try { var vp = cornerstone.getViewport(el); vp.invert = !vp.invert; cornerstone.setViewport(el, vp); } catch (e) {}
    });
    document.getElementById('t-reset').addEventListener('click', function () { try { cornerstone.reset(el); updateHud(); } catch (e) {} });
    document.getElementById('t-pdf').addEventListener('click', generatePdf);
    document.getElementById('t-cine').addEventListener('click', toggleCine);
    document.getElementById('t-fs').addEventListener('click', toggleFs);
    document.getElementById('t-series').addEventListener('click', function () { document.getElementById('v-series').classList.toggle('open'); });
    document.getElementById('v-prev').addEventListener('click', function () { showIndex(stack.currentImageIdIndex - 1); });
    document.getElementById('v-next').addEventListener('click', function () { showIndex(stack.currentImageIdIndex + 1); });
    document.getElementById('v-close').addEventListener('click', function (e) { e.preventDefault(); closeViewer(); });

    function closeViewer() {
        if (window.history.length > 1) { window.history.back(); return; }
        window.close();
        setTimeout(function () { if (!window.closed) location.href = ESTUDIOS_URL; }, 150);
    }
    function stopCine() {
        if (cineTimer) { clearInterval(cineTimer); cineTimer = null; }
        cineOn = false;
        var b = document.getElementById('t-cine'); if (b) { b.textContent = '▶ Cine'; b.classList.remove('active'); }
    }
    function toggleCine() {
        if (cineOn) { stopCine(); return; }
        if (stack.imageIds.length < 2) return;
        if (cineTimer) { clearInterval(cineTimer); cineTimer = null; }
        cineOn = true;
        var b = document.getElementById('t-cine'); if (b) { b.textContent = '⏸ Pausa'; b.classList.add('active'); }
        cineTimer = setInterval(function () {
            if (!cineOn || !stack.imageIds.length) return;
            showIndex((stack.currentImageIdIndex + 1) % stack.imageIds.length);
        }, 90);
    }
    function toggleFs() {
        try {
            if (!document.fullscreenElement) {
                var r = document.documentElement.requestFullscreen || document.documentElement.webkitRequestFullscreen;
                if (r) r.call(document.documentElement);
            } else {
                var x = document.exitFullscreen || document.webkitExitFullscreen;
                if (x) x.call(document);
            }
        } catch (e) {}
        setTimeout(function () { try { cornerstone.resize(el, true); } catch (e) {} }, 150);
    }

    // Atajos: ←→ cortes · +/- zoom · I invertir · R reset · C cine · F pantalla
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
            case 'c': case 'C': toggleCine(); break;
            case 'f': case 'F': toggleFs(); break;
        }
    });

    // Touch: doble-tap = alternar zoom (solo con herramientas que no dibujan)
    var SAFE_DT = { Wwwc: 1, Zoom: 1, Pan: 1 };
    var _lastTap = 0, _dtZoom = false;
    el.addEventListener('touchend', function (e) {
        if (e.touches.length > 0) return;
        var now = Date.now();
        if (now - _lastTap < 300 && SAFE_DT[currentTool]) {
            try {
                if (!_dtZoom) { var vp = cornerstone.getViewport(el); vp.scale = (vp.scale || 1) * 2.2; cornerstone.setViewport(el, vp); _dtZoom = true; }
                else { cornerstone.fitToWindow(el); _dtZoom = false; }
                updateHud();
            } catch (x) {}
            e.preventDefault(); _lastTap = 0; return;
        }
        _lastTap = now;
    }, { passive: false });

    function tr(s, n) { s = String(s == null || s === '' ? '—' : s); return s.length > n ? s.slice(0, n - 1) + '…' : s; }

    function generatePdf() {
        if (!window.jspdf || !window.jspdf.jsPDF) { notice('No se pudo cargar el generador de PDF.'); return; }
        var ee; try { ee = cornerstone.getEnabledElement(el); } catch (e) {}
        if (!ee || !ee.canvas) { notice('No hay imagen para exportar.'); return; }
        var imgData = ee.canvas.toDataURL('image/jpeg', 0.92);
        var iw = ee.canvas.width, ih = ee.canvas.height;
        var jsPDF = window.jspdf.jsPDF;
        var doc = new jsPDF({ unit: 'pt', format: 'letter' });
        var W = 612, H = 792, M = 40;
        var navy = [42, 37, 102], gray = [100, 116, 139], line = [214, 218, 228];
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
        var y = 112;
        function block(x, title, rows) {
            doc.setFont('helvetica', 'bold'); doc.setFontSize(8); doc.setTextColor(navy[0], navy[1], navy[2]);
            doc.text(title.toUpperCase(), x, y);
            doc.setFont('helvetica', 'normal'); doc.setFontSize(9.5);
            var yy = y + 16;
            rows.forEach(function (r) {
                doc.setTextColor(gray[0], gray[1], gray[2]); doc.text(r[0], x, yy);
                doc.setTextColor(30, 37, 64); doc.text(tr(r[1], 30), x + 82, yy); yy += 15;
            });
            return yy;
        }
        var le = block(M, 'Paciente', [['Nombre:', studyMeta.pname], ['Identificación:', studyMeta.pid], ['F. nacimiento:', fdate(studyMeta.dob) || '—'], ['Sexo:', studyMeta.sex]]);
        var re = block(W / 2 + 12, 'Estudio', [['Modalidad:', studyMeta.modality], ['Fecha:', fdate(studyMeta.studyDate) || '—'], ['Descripción:', studyMeta.studyDesc], ['Accession:', studyMeta.accession]]);
        var ib = Math.max(le, re) + 6;
        doc.setDrawColor(line[0], line[1], line[2]); doc.line(M, ib, W - M, ib);
        var bx = M, by = ib + 14, bw = W - 2 * M, bh = H - by - 54;
        doc.setFillColor(0, 0, 0); doc.rect(bx, by, bw, bh, 'F');
        var sc = Math.min(bw / iw, bh / ih), dw = iw * sc, dh = ih * sc;
        try { doc.addImage(imgData, 'JPEG', bx + (bw - dw) / 2, by + (bh - dh) / 2, dw, dh); } catch (e) {}
        var n = new Date(), p2 = function (x) { return ('0' + x).slice(-2); };
        var stamp = p2(n.getDate()) + '/' + p2(n.getMonth() + 1) + '/' + n.getFullYear() + ' ' + p2(n.getHours()) + ':' + p2(n.getMinutes());
        doc.setDrawColor(line[0], line[1], line[2]); doc.line(M, H - 42, W - M, H - 42);
        doc.setFont('helvetica', 'normal'); doc.setFontSize(7.5); doc.setTextColor(gray[0], gray[1], gray[2]);
        doc.text(CLINIC + ' · Generado el ' + stamp, M, H - 28);
        doc.text('Imagen de referencia — la interpretación corresponde a su médico o radiólogo.', M, H - 18);
        var fn = ('Estudio_' + (studyMeta.accession || studyMeta.modality || 'imagen') + '_' + (studyMeta.studyDate || '')).replace(/[^A-Za-z0-9._-]/g, '_') + '.pdf';
        doc.save(fn);
    }

    window.addEventListener('resize', function () { try { cornerstone.resize(el, true); } catch (e) {} });
})();
</script>
</body>
</html>
