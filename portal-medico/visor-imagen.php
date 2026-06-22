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
$hasReport = !empty($_GET['report']);
$pid       = (int)($_GET['pid'] ?? 0);   // para listar otros estudios del paciente (comparar)
$reportUrl = base_url('portal-medico/informe') . '?study=' . rawurlencode($study) . '&scope=' . rawurlencode($scope);

if (!headers_sent()) {
    header('X-Robots-Tag: noindex, nofollow');
    // El visor cambia seguido y trae datos del paciente: nunca servir HTML/JS
    // cacheado (en el PWA instalado no hay "recarga dura"). Así siempre carga
    // la versión más reciente del visor.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}
?>
<!DOCTYPE html>
<html lang="es-DO">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Visor de imágenes · Hospital General Las Colinas</title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0b0e16">
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
    .v-tool{appearance:none;border:1px solid #2b3550;background:#1a2236;color:#cdd4e6;font:inherit;font-size:.8rem;border-radius:9px;padding:7px 10px;cursor:pointer;display:inline-flex;align-items:center;gap:5px;transition:background .12s,border-color .12s;white-space:nowrap}
    .v-tool:hover{background:#222c45}
    .v-tool.active{background:#2f3e66;border-color:#4a5fa0;color:#fff}
    .v-tool svg{width:16px;height:16px}
    .v-toolbar{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-left:auto}
    .v-sep{width:1px;align-self:stretch;min-height:22px;background:#2b3550;margin:0 1px}
    .v-dd{position:relative}
    .v-dd-menu{position:absolute;top:calc(100% + 6px);right:0;background:#161d2e;border:1px solid #2b3550;border-radius:10px;padding:6px;display:none;flex-direction:column;gap:2px;z-index:30;min-width:200px;box-shadow:0 12px 34px rgba(0,0,0,.55)}
    .v-dd.open .v-dd-menu{display:flex}
    .v-dd-menu button{appearance:none;border:0;background:transparent;color:#cdd4e6;font:inherit;font-size:.82rem;text-align:left;padding:7px 10px;border-radius:7px;cursor:pointer;white-space:nowrap}
    .v-dd-menu button:hover{background:#222c45}
    .v-dd-menu .lbl{font-size:.64rem;text-transform:uppercase;letter-spacing:.5px;color:#5e6a85;padding:7px 10px 2px}
    .v-main{flex:1;display:flex;min-height:0;position:relative;overflow:hidden}
    .v-series{width:128px;background:#0e1320;border-right:1px solid #232c42;overflow-y:auto;flex:none}
    .v-series-item{padding:8px;cursor:pointer;border-bottom:1px solid #1a2030;text-align:center;font-size:.72rem;color:#9aa3bb}
    .v-series-item:hover{background:#161d2e}
    .v-series-item.active{background:#1d2a4a;color:#fff}
    .v-series-item .th{width:100%;height:84px;background:#000;border-radius:6px;object-fit:contain;display:block;margin-bottom:5px}
    .v-series-item .th.ph{display:grid;place-items:center;color:#8b93a9;font-weight:700;font-size:.95rem}
    .v-stage{flex:1;position:relative;min-width:0;background:#000;touch-action:none}
    #dicom{width:100%;height:100%;touch-action:none}
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
    .v-report{background:#0d9488;border-color:#14b8a6;color:#fff}
    .v-report:hover{background:#0f766e}
    .v-overlay{position:absolute;left:12px;top:10px;font-size:.74rem;color:#cfd6ea;text-shadow:0 1px 2px #000,0 0 4px #000;pointer-events:none;line-height:1.55;max-width:62%}
    .v-overlay b{color:#fff;font-weight:700}
    .v-nav{position:absolute;left:50%;bottom:calc(10px + env(safe-area-inset-bottom));transform:translateX(-50%);display:none;align-items:center;gap:8px;background:rgba(17,23,38,.78);backdrop-filter:blur(6px);border:1px solid #2b3550;border-radius:999px;padding:5px 8px;z-index:6}
    .v-nav button{appearance:none;border:0;background:#1a2236;color:#cdd4e6;width:34px;height:34px;border-radius:50%;cursor:pointer;font-size:1.3rem;line-height:1;display:grid;place-items:center}
    .v-nav button:hover{background:#28324e}
    .v-nav button:disabled{opacity:.3;cursor:default}
    .v-nav .cnt{font-size:.8rem;color:#e6e9f2;min-width:58px;text-align:center;font-variant-numeric:tabular-nums}
    .v-tool{-webkit-tap-highlight-color:transparent}
    /* Sidebar de series oculto cuando solo hay una serie (gana espacio en todos lados) */
    body.single-series .v-series{display:none}
    body.single-series .v-series-toggle{display:none!important}
    .v-series-toggle{display:none}

    /* Targets táctiles más grandes en dispositivos de dedo (tablet/móvil) */
    @media (pointer:coarse){
        .v-tool{padding:9px 12px;font-size:.82rem}
        .v-nav button{width:46px;height:46px;font-size:1.5rem}
        .v-series-item{padding:11px 8px}
        .ai-chip{padding:7px 12px}
        .ai-send{width:42px;height:42px}
    }

    /* Teléfono / tablet en vertical */
    @media (max-width:760px){
        .v-top{gap:7px 8px}
        .v-top .ttl{max-width:42vw}
        .v-top .meta{display:none}
        .v-series-toggle{display:inline-flex}
        /* Barra de herramientas: una sola fila desplazable (no envuelve) */
        .v-toolbar{flex-wrap:nowrap;overflow-x:auto;overflow-y:hidden;width:100%;margin-left:0;padding-bottom:4px;-webkit-overflow-scrolling:touch;scrollbar-width:none}
        .v-toolbar::-webkit-scrollbar{display:none}
        .v-tool{flex:none}
        .v-sep{display:none}
        /* Series como panel deslizable desde la izquierda */
        .v-series{position:absolute;left:0;top:0;bottom:0;width:160px;z-index:8;transform:translateX(-100%);transition:transform .25s ease;box-shadow:6px 0 26px rgba(0,0,0,.55)}
        .v-series.open{transform:translateX(0)}
        .v-overlay{font-size:.66rem;max-width:80%}
    }

    /* ── Asistente de IA (drawer lateral) ───────────────────────────────── */
    .v-ai{background:#3b2f6b;border-color:#5a4aa0;color:#e9e3ff}
    .v-ai:hover{background:#473a82}
    .v-ai.active{background:#5a4aa0;border-color:#7a68c8;color:#fff}
    .ai-drawer{flex:none;width:0;overflow:hidden;background:#0e1320;border-left:0 solid #232c42;transition:width .28s ease;display:flex}
    .ai-drawer.open{width:400px;border-left-width:1px}
    .ai-inner{width:400px;display:flex;flex-direction:column;height:100%;flex:none}
    .ai-head{display:flex;align-items:center;gap:8px;padding:11px 12px;border-bottom:1px solid #232c42;flex:none}
    .ai-head .t{font-weight:700;font-size:.9rem;color:#fff;flex:1;display:flex;align-items:center;gap:7px}
    .ai-beta{font-size:.58rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;background:#2f3e66;color:#bcd0ff;padding:2px 6px;border-radius:5px}
    .ai-x{appearance:none;border:0;background:#1a2236;color:#cdd4e6;width:30px;height:30px;border-radius:8px;cursor:pointer;font-size:.85rem}
    .ai-x:hover{background:#28324e}
    .ai-disc{font-size:.72rem;color:#f5c884;background:rgba(180,120,30,.12);border-bottom:1px solid #232c42;padding:8px 12px;line-height:1.45;flex:none}
    .ai-disc b{color:#ffd9a0}
    .ai-body{flex:1;overflow-y:auto;padding:12px;display:flex;flex-direction:column;gap:9px;min-height:0}
    .ai-empty{margin:auto;text-align:center;color:#8b93a9;padding:14px;display:flex;flex-direction:column;align-items:center;gap:12px}
    .ai-empty-ic{font-size:2.1rem}
    .ai-empty p{font-size:.85rem;line-height:1.5;max-width:240px}
    .ai-empty b{color:#cdd4e6}
    .ai-go{appearance:none;border:0;background:#5a4aa0;color:#fff;font:inherit;font-size:.85rem;font-weight:600;border-radius:10px;padding:10px 16px;cursor:pointer;transition:background .12s}
    .ai-go:hover{background:#6a59b8}
    .ai-msg{max-width:93%;padding:9px 12px;border-radius:12px;font-size:.84rem;line-height:1.55;word-wrap:break-word;overflow-wrap:anywhere}
    .ai-msg.user{align-self:flex-end;background:#2f3e66;color:#fff;border-bottom-right-radius:4px}
    .ai-msg.ai{align-self:flex-start;background:#161d2e;color:#dfe4f1;border:1px solid #232c42;border-bottom-left-radius:4px}
    .ai-msg.ai strong{color:#fff}
    .ai-msg.ai em{color:#9aa3bb;font-style:italic}
    .ai-msg.err{align-self:stretch;background:#3a1d22;border:1px solid #6b2b34;color:#fecaca;font-size:.8rem}
    .ai-dots{display:inline-flex;gap:4px;padding:2px 0}
    .ai-dots i{width:7px;height:7px;border-radius:50%;background:#6d8bff;animation:aidot 1s infinite ease-in-out}
    .ai-dots i:nth-child(2){animation-delay:.15s}.ai-dots i:nth-child(3){animation-delay:.3s}
    @keyframes aidot{0%,60%,100%{opacity:.25;transform:translateY(0)}30%{opacity:1;transform:translateY(-3px)}}
    .ai-suggest{display:flex;flex-wrap:wrap;gap:6px;padding:0 12px 8px;flex:none}
    .ai-chip{appearance:none;border:1px solid #2b3550;background:#141b2c;color:#aeb6cc;font:inherit;font-size:.74rem;border-radius:999px;padding:5px 10px;cursor:pointer;transition:background .12s,color .12s}
    .ai-chip:hover{background:#1d2740;color:#fff}
    .ai-input{display:flex;gap:8px;padding:10px 12px;border-top:1px solid #232c42;align-items:flex-end;flex:none}
    .ai-input textarea{flex:1;resize:none;background:#0b0e16;border:1px solid #2b3550;border-radius:10px;color:#e6e9f2;font:inherit;font-size:.84rem;padding:8px 10px;max-height:110px;min-height:38px;line-height:1.4}
    .ai-input textarea:focus{outline:none;border-color:#4a5fa0}
    .ai-send{appearance:none;border:0;background:#2563eb;color:#fff;width:38px;height:38px;border-radius:10px;cursor:pointer;font-size:1rem;flex:none;transition:background .12s}
    .ai-send:hover{background:#1d4ed8}.ai-send:disabled{opacity:.45;cursor:default}
    .ai-foot{font-size:.66rem;color:#5e6a85;padding:7px 12px 9px;border-top:1px solid #1a2030;text-align:center;flex:none;line-height:1.4}
    /* Móvil/tablet: el drawer de IA CUBRE el visor (overlay), no lo empuja —
       así la imagen no se aplasta a una tira ni el overlay del paciente se parte. */
    @media(max-width:760px){
        .ai-drawer{position:absolute;top:0;right:0;bottom:0;left:0;width:auto;transform:translateX(100%);transition:transform .28s ease;border-left:0;z-index:12}
        .ai-drawer.open{width:auto;transform:none}
        .ai-inner{width:100%}
    }
</style>
</head>
<body>
<header class="v-top">
    <a href="#" class="v-back" id="v-close" title="Cerrar" role="button">✕</a>
    <span class="v-brand"><img src="<?= e(base_url('assets/site/logo.png')) ?>" alt="Hospital General Las Colinas"></span>
    <span class="ttl" id="v-title">Visor de imágenes</span>
    <span class="meta" id="v-meta"></span>
    <div class="v-toolbar">
        <button class="v-tool v-series-toggle" id="t-series" title="Series del estudio">🗂 Series</button>
        <button class="v-tool active" id="t-wwwc" title="Brillo/Contraste (arrastrar)">◐ Ventana</button>
        <button class="v-tool" id="t-zoom" title="Zoom (arrastrar)">⤢ Zoom</button>
        <button class="v-tool" id="t-pan" title="Mover (arrastrar)">✋ Mover</button>
        <button class="v-tool" id="t-magnify" title="Lupa (arrastrar para magnificar)">🔎 Lupa</button>
        <span class="v-sep"></span>
        <button class="v-tool" id="t-length" title="Medir distancia">📏 Medir</button>
        <button class="v-tool" id="t-angle" title="Medir ángulo">📐 Ángulo</button>
        <button class="v-tool" id="t-roi" title="Área / densidad (ROI elíptico: área, media, HU en TC)">⬭ Área</button>
        <button class="v-tool" id="t-arrow" title="Flecha con etiqueta">➳ Flecha</button>
        <button class="v-tool" id="t-probe" title="Punto: valor del píxel / HU">🎯 Punto</button>
        <span class="v-sep"></span>
        <div class="v-dd" id="dd-preset">
            <button class="v-tool" id="t-preset" title="Preajustes de ventana (W/L)">🎚 Preajustes ▾</button>
            <div class="v-dd-menu" id="preset-menu">
                <button data-ww="" data-wc="">Auto (de la imagen)</button>
                <div class="lbl">Tomografía (TC)</div>
                <button data-ww="1500" data-wc="-600">Pulmón</button>
                <button data-ww="350" data-wc="40">Mediastino / tejidos blandos</button>
                <button data-ww="2000" data-wc="400">Hueso</button>
                <button data-ww="80" data-wc="40">Cerebro</button>
                <button data-ww="400" data-wc="40">Abdomen</button>
                <button data-ww="150" data-wc="60">Hígado</button>
                <button data-ww="600" data-wc="150">Angio / contraste</button>
            </div>
        </div>
        <span class="v-sep"></span>
        <button class="v-tool" id="t-rotate" title="Rotar 90°">⟳ Rotar</button>
        <button class="v-tool" id="t-flip" title="Voltear horizontal">⇄ Voltear</button>
        <button class="v-tool" id="t-invert" title="Invertir (I)">◑ Invertir</button>
        <button class="v-tool" id="t-cine" title="Reproducir cine (multi-imagen)" style="display:none">▶ Cine</button>
        <button class="v-tool" id="t-reset" title="Restablecer (R)">⟲ Reset</button>
        <span class="v-sep"></span>
        <button class="v-tool" id="t-fs" title="Pantalla completa (F)">⛶</button>
        <button class="v-tool v-report" id="t-report" title="Ver el informe radiológico (PDF de Autana)" style="display:none">📄 Informe</button>
        <button class="v-tool v-ai" id="t-ai" title="Asistente de IA — apoyo a la lectura (no es diagnóstico)">✨ IA</button>
        <button class="v-tool v-pdf" id="t-pdf" title="Exportar a PDF (con logo y datos del paciente)">⤓ PDF</button>
    </div>
</header>
<div class="v-main">
    <aside class="v-series" id="v-series"></aside>
    <div class="v-stage">
        <div id="dicom"></div>
        <div class="v-overlay" id="v-overlay"></div>
        <div class="v-hud" id="v-hud"></div>
        <div class="v-hud2" id="v-hud2"></div>
        <div class="v-nav" id="v-nav">
            <button id="v-prev" title="Imagen anterior (←)" aria-label="Anterior">‹</button>
            <span class="cnt" id="v-nav-cnt">1 / 1</span>
            <button id="v-next" title="Imagen siguiente (→)" aria-label="Siguiente">›</button>
        </div>
        <div class="v-msg" id="v-msg"><div class="v-spin"></div><div id="v-msg-txt">Cargando estudio…</div></div>
    </div>
    <aside class="ai-drawer" id="ai-drawer">
        <div class="ai-inner">
            <header class="ai-head">
                <span class="t">✨ Asistente de IA <span class="ai-beta">beta</span></span>
                <button class="ai-x" id="ai-close" title="Cerrar">✕</button>
            </header>
            <div class="ai-disc">Apoyo a la lectura sobre la imagen que estás viendo. <b>No es un diagnóstico</b>: la decisión clínica es tuya.</div>
            <div class="ai-body" id="ai-body">
                <div class="ai-empty" id="ai-empty">
                    <div class="ai-empty-ic">✨</div>
                    <p>Pide a la IA un <b>borrador de lectura</b> de la imagen actual. No se envían datos del paciente.</p>
                    <button class="ai-go" id="ai-analyze">Analizar imagen actual</button>
                </div>
            </div>
            <div class="ai-suggest" id="ai-suggest">
                <button class="ai-chip" data-q="__analyze__">↻ Analizar imagen actual</button>
                <button class="ai-chip" data-mode="patient">🧑‍⚕️ Explicar al paciente</button>
                <button class="ai-chip" data-mode="compare">⚖️ Comparar (2 estudios)</button>
                <button class="ai-chip" data-q="Describe los hallazgos principales.">Describir hallazgos</button>
                <button class="ai-chip" data-q="¿Se observa alguna fractura o lesión ósea?">¿Hay fractura?</button>
                <button class="ai-chip" data-q="¿Qué recomiendas como siguiente paso?">¿Qué recomiendas?</button>
            </div>
            <form class="ai-input" id="ai-form">
                <textarea id="ai-q" rows="1" placeholder="Pregunta sobre esta imagen…"></textarea>
                <button type="submit" class="ai-send" id="ai-send" title="Enviar">➤</button>
            </form>
            <div class="ai-foot" id="ai-foot">Asistente de apoyo · no sustituye el informe radiológico</div>
        </div>
    </aside>
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
    var AI_ENDPOINT = <?= json_encode(base_url('api/ai-imaging.php'), JSON_UNESCAPED_SLASHES) ?>;
    var CSRF = <?= json_encode(doctor_csrf_token()) ?>;
    var PACIENTES_URL = <?= json_encode(base_url('portal-medico/pacientes'), JSON_UNESCAPED_SLASHES) ?>;
    var REPORT_URL = <?= json_encode($reportUrl, JSON_UNESCAPED_SLASHES) ?>;
    var HAS_REPORT = <?= $hasReport ? 'true' : 'false' ?>;
    var PID = <?= (int)$pid ?>;
    var PROXY = <?= json_encode(base_url('api/doctor-proxy.php'), JSON_UNESCAPED_SLASHES) ?>;
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
    var currentSeriesDesc = '';
    var CLINIC = 'Hospital General Las Colinas';
    var logoImg = new Image(); logoImg.src = <?= json_encode(base_url('assets/site/logo.png'), JSON_UNESCAPED_SLASHES) ?>;
    function escH(s) { return String(s == null ? '' : s).replace(/[&<>]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]; }); }
    function fdate(d) { return (d && d.length >= 8) ? (d.slice(6, 8) + '/' + d.slice(4, 6) + '/' + d.slice(0, 4)) : ''; }
    function pn(md, t) { try { var v = md[t].Value[0]; return v && v.Alphabetic ? v.Alphabetic : (typeof v === 'string' ? v : ''); } catch (e) { return ''; } }

    function dj(url) { return fetch(url, { headers: { Accept: 'application/dicom+json' }, credentials: 'same-origin' }).then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); }); }
    function tag(md, t) { try { return md[t].Value; } catch (e) { return undefined; } }
    function tag1(md, t) { var v = tag(md, t); return v && v.length ? v[0] : undefined; }

    var EXCLUSIVE = ['Wwwc', 'Zoom', 'Pan', 'Length', 'Angle', 'EllipticalRoi', 'RectangleRoi', 'Bidirectional', 'ArrowAnnotate', 'Probe', 'Magnify'];
    var currentTool = 'Wwwc';
    function setActiveTool(name, btnId) {
        currentTool = name;
        EXCLUSIVE.forEach(function (n) { try { cstools.setToolPassive(n); } catch (e) {} });
        try { cstools.setToolActive(name, { mouseButtonMask: 1 }); } catch (e) {}
        document.querySelectorAll('.v-tool').forEach(function (b) { b.classList.remove('active'); });
        if (btnId) document.getElementById(btnId).classList.add('active');
        if (cineOn) { var cb = document.getElementById('t-cine'); if (cb) cb.classList.add('active'); }
    }
    var cineOn = false, cineTimer = null;

    function renderNav() {
        var n = stack.imageIds.length, i = stack.currentImageIdIndex;
        var nav = document.getElementById('v-nav'); if (!nav) return;
        nav.style.display = n > 1 ? 'flex' : 'none';
        var c = document.getElementById('v-nav-cnt'); if (c) { c.textContent = (i + 1) + ' / ' + n; c.style.color = ''; }
        var pv = document.getElementById('v-prev'), nx = document.getElementById('v-next');
        if (pv) pv.disabled = i <= 0;
        if (nx) nx.disabled = i >= n - 1;
        var cine = document.getElementById('t-cine'); if (cine) cine.style.display = n > 1 ? '' : 'none';
        var hud = document.getElementById('v-hud'); if (hud) hud.textContent = '';
    }

    function updateHud() {
        try {
            var vp = cornerstone.getViewport(el);
            document.getElementById('v-hud2').textContent =
                'WW/WC: ' + Math.round(vp.voi.windowWidth) + ' / ' + Math.round(vp.voi.windowCenter) +
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
        }).catch(function (e) {
            var c = document.getElementById('v-nav-cnt'); if (c) { c.textContent = '⚠ img ' + (i + 1); c.style.color = '#fca5a5'; }
        });
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

    // Miniatura real de cada serie: 1ª instancia vía endpoint WADO-RS "rendered".
    // Si el PACS/proxy no lo permite, se queda el placeholder con la modalidad.
    function loadThumb(su, ph) {
        if (!ph) return;
        dj(ROOT + '/studies/' + STUDY + '/series/' + su + '/instances?includefield=00080018').then(function (insts) {
            if (!insts || !insts.length) return;
            var sop = tag1(insts[0], '00080018'); if (!sop) return;
            var im = new Image();
            im.className = 'th';
            im.alt = '';
            im.onload = function () { try { ph.replaceWith(im); } catch (e) {} };
            im.src = ROOT + '/studies/' + STUDY + '/series/' + su + '/instances/' + sop + '/rendered?viewport=160,160&quality=80';
        }).catch(function () {});
    }

    // 1) Listar series del estudio
    msgTxt.textContent = 'Buscando series…';
    dj(ROOT + '/studies/' + STUDY + '/series').then(function (series) {
        if (!series.length) return fail('El estudio no tiene series.');
        series.sort(function (a, b) { return (tag1(a, '00200011') || 0) - (tag1(b, '00200011') || 0); });
        document.body.classList.toggle('single-series', series.length <= 1);
        var box = document.getElementById('v-series');
        box.innerHTML = '';
        series.forEach(function (s, idx) {
            var su = tag1(s, '0020000E');
            var mod = tag1(s, '00080060') || '';
            var desc = tag1(s, '0008103E') || ('Serie ' + (idx + 1));
            var n = tag1(s, '00201209') || '';
            var item = document.createElement('div');
            item.className = 'v-series-item' + (idx === 0 ? ' active' : '');
            item.innerHTML = '<div class="th ph">' + escH(mod || '—') + '</div><div>' + escH(desc) + '</div><div style="color:#5e6a85">' + escH(String(n)) + ' img</div>';
            item.addEventListener('click', function () {
                document.querySelectorAll('.v-series-item').forEach(function (x) { x.classList.remove('active'); });
                item.classList.add('active');
                loadSeries(su, mod, desc);
                box.classList.remove('open');   // cerrar el panel en móvil al elegir
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
        currentSeriesDesc = desc || '';
        stopCine();
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
                try { el.dispatchEvent(new CustomEvent('hgv:series', { detail: { seriesUID: seriesUID, mod: mod, desc: desc, count: ids.length } })); } catch (e) {}
            });
        }).catch(function (e) { fail('No se pudieron cargar las imágenes. ' + (e && e.message ? e.message : '')); });
    }

    var toolsReady = false;
    function addT(tool, cfg) { try { if (tool) cstools.addTool(tool, cfg); } catch (e) {} }
    function actT(name, opt) { try { cstools.setToolActive(name, opt || {}); } catch (e) {} }
    function ensureTools() {
        if (toolsReady) return; toolsReady = true;
        addT(cstools.WwwcTool);
        addT(cstools.ZoomTool);
        addT(cstools.PanTool);
        addT(cstools.LengthTool);
        addT(cstools.AngleTool);
        addT(cstools.EllipticalRoiTool);
        addT(cstools.RectangleRoiTool);
        addT(cstools.BidirectionalTool);
        addT(cstools.ProbeTool);
        addT(cstools.MagnifyTool);
        addT(cstools.ArrowAnnotateTool, {
            configuration: {
                getTextCallback: function (cb) { cb(window.prompt('Etiqueta (opcional):', '') || ''); },
                changeTextCallback: function (data, evt, cb) { cb(window.prompt('Editar etiqueta:', (data && data.text) || '') || ''); }
            }
        });
        addT(cstools.StackScrollMouseWheelTool);
        addT(cstools.ZoomMouseWheelTool);
        // Soporte táctil (tablet): pellizco=zoom, 2 dedos=mover, 3 dedos=cortes.
        addT(cstools.PanMultiTouchTool);
        addT(cstools.ZoomTouchPinchTool);
        addT(cstools.StackScrollMultiTouchTool);
        actT('StackScrollMouseWheel');
        actT('Wwwc', { mouseButtonMask: 1 });
        actT('Pan', { mouseButtonMask: 4 });
        actT('Zoom', { mouseButtonMask: 2 });
        actT('PanMultiTouch');
        actT('ZoomTouchPinch');
        actT('StackScrollMultiTouch');
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
    document.getElementById('v-prev').addEventListener('click', function () { showIndex(stack.currentImageIdIndex - 1); });
    document.getElementById('v-next').addEventListener('click', function () { showIndex(stack.currentImageIdIndex + 1); });

    // Herramientas de medición / anotación añadidas
    document.getElementById('t-magnify').addEventListener('click', function () { setActiveTool('Magnify', 't-magnify'); });
    document.getElementById('t-angle').addEventListener('click', function () { setActiveTool('Angle', 't-angle'); });
    document.getElementById('t-roi').addEventListener('click', function () { setActiveTool('EllipticalRoi', 't-roi'); });
    document.getElementById('t-arrow').addEventListener('click', function () { setActiveTool('ArrowAnnotate', 't-arrow'); });
    document.getElementById('t-probe').addEventListener('click', function () { setActiveTool('Probe', 't-probe'); });
    // Transformaciones instantáneas
    document.getElementById('t-rotate').addEventListener('click', function () {
        try { var vp = cornerstone.getViewport(el); vp.rotation = ((vp.rotation || 0) + 90) % 360; cornerstone.setViewport(el, vp); } catch (e) {}
    });
    document.getElementById('t-flip').addEventListener('click', function () {
        try { var vp = cornerstone.getViewport(el); vp.hflip = !vp.hflip; cornerstone.setViewport(el, vp); } catch (e) {}
    });
    document.getElementById('t-cine').addEventListener('click', toggleCine);
    document.getElementById('t-fs').addEventListener('click', toggleFs);

    // Cerrar adaptado al PWA: si se navegó dentro de la app (standalone) → volver
    // atrás; si fue una pestaña nueva (navegador) → cerrarla; si no se puede → ir
    // a la lista de pacientes.
    function closeViewer() {
        if (window.history.length > 1) { window.history.back(); return; }
        window.close();
        setTimeout(function () { if (!window.closed) location.href = PACIENTES_URL; }, 150);
    }
    document.getElementById('v-close').addEventListener('click', function (e) { e.preventDefault(); closeViewer(); });

    // Botón de informe radiológico (PDF de Autana). Visible solo si el estudio tiene
    // informe (se abrió el visor con &report=1). Abre la página de informe embebido.
    (function () {
        var rb = document.getElementById('t-report');
        if (!rb) return;
        if (HAS_REPORT) rb.style.display = '';
        rb.addEventListener('click', function () {
            var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
            if (standalone) { window.location.href = REPORT_URL; }
            else { window.open(REPORT_URL, '_blank', 'noopener'); }
        });
    })();

    // Panel de series deslizable (móvil)
    document.getElementById('t-series').addEventListener('click', function () {
        document.getElementById('v-series').classList.toggle('open');
    });

    // Touch: doble-tap = alternar zoom (solo con herramientas que NO dibujan, para
    // no crear mediciones por accidente).
    var SAFE_DT = { Wwwc: 1, Zoom: 1, Pan: 1, Magnify: 1 };
    var _lastTap = 0, _dtZoom = false;
    el.addEventListener('touchend', function (e) {
        if (e.touches.length > 0) return;            // aún hay dedos sobre la pantalla
        var now = Date.now();
        if (now - _lastTap < 300 && SAFE_DT[currentTool]) {
            try {
                if (!_dtZoom) { var vp = cornerstone.getViewport(el); vp.scale = (vp.scale || 1) * 2.2; cornerstone.setViewport(el, vp); _dtZoom = true; }
                else { cornerstone.fitToWindow(el); _dtZoom = false; }
                updateHud();
            } catch (x) {}
            e.preventDefault();
            _lastTap = 0; return;
        }
        _lastTap = now;
    }, { passive: false });

    // Preajustes de ventana (W/L)
    var ddPreset = document.getElementById('dd-preset');
    // En móvil la barra tiene overflow (scroll) → el menú absoluto se recortaría.
    // Lo reposicionamos como fixed bajo el botón para que escape del recorte.
    function positionPresetMenu() {
        var menu = document.getElementById('preset-menu');
        if (window.matchMedia('(max-width:760px)').matches) {
            var r = document.getElementById('t-preset').getBoundingClientRect();
            menu.style.position = 'fixed';
            menu.style.top = (r.bottom + 6) + 'px';
            menu.style.left = Math.max(8, Math.min(r.left, window.innerWidth - 216)) + 'px';
            menu.style.right = 'auto';
        } else {
            menu.style.position = ''; menu.style.top = ''; menu.style.left = ''; menu.style.right = '';
        }
    }
    document.getElementById('t-preset').addEventListener('click', function (e) {
        e.stopPropagation();
        var opening = !ddPreset.classList.contains('open');
        ddPreset.classList.toggle('open');
        if (opening) positionPresetMenu();
    });
    document.getElementById('preset-menu').addEventListener('click', function (e) {
        var b = e.target.closest('button[data-ww]'); if (!b) return;
        applyPreset(b.getAttribute('data-ww'), b.getAttribute('data-wc'));
        ddPreset.classList.remove('open');
    });
    document.addEventListener('click', function (e) { if (ddPreset && !ddPreset.contains(e.target)) ddPreset.classList.remove('open'); });

    function applyPreset(ww, wc) {
        try {
            var vp = cornerstone.getViewport(el);
            if (ww === '' || ww == null) {
                var img = cornerstone.getImage(el);
                if (img) { vp.voi.windowWidth = img.windowWidth; vp.voi.windowCenter = img.windowCenter; }
            } else {
                vp.voi.windowWidth = parseFloat(ww); vp.voi.windowCenter = parseFloat(wc);
            }
            cornerstone.setViewport(el, vp); updateHud();
        } catch (e) {}
    }

    // Cine controlado por nosotros (timer propio) → la pausa es 100% fiable, sin
    // depender de cstools.stopClip (que en este build no cancela el bucle).
    function stopCine() {
        if (cineTimer) { clearInterval(cineTimer); cineTimer = null; }
        try { if (cstools && cstools.stopClip) cstools.stopClip(el); } catch (e) {}   // por si quedó un clip de cstools
        cineOn = false;
        var b = document.getElementById('t-cine');
        if (b) { b.textContent = '▶ Cine'; b.classList.remove('active'); }
    }
    function toggleCine() {
        if (cineOn) { stopCine(); return; }
        if (stack.imageIds.length < 2) return;
        if (cineTimer) { clearInterval(cineTimer); cineTimer = null; }   // nunca dos timers
        cineOn = true;
        var b = document.getElementById('t-cine');
        if (b) { b.textContent = '⏸ Pausa'; b.classList.add('active'); }
        cineTimer = setInterval(function () {
            if (!cineOn || !stack.imageIds.length) { return; }
            showIndex((stack.currentImageIdIndex + 1) % stack.imageIds.length);
        }, 90);   // ~11 fps
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
            case 'c': case 'C': toggleCine(); break;
            case 'f': case 'F': toggleFs(); break;
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

    /* ── Asistente de IA ─────────────────────────────────────────────────── */
    (function aiModule() {
        var drawer = document.getElementById('ai-drawer');
        var body   = document.getElementById('ai-body');
        var empty  = document.getElementById('ai-empty');
        var ta     = document.getElementById('ai-q');
        var sendBtn= document.getElementById('ai-send');
        if (!drawer || !body) return;
        var convo = [];   // { role:'user'|'assistant', text }
        var busy = false;

        function toggle(force) {
            var open = (force === undefined) ? !drawer.classList.contains('open') : force;
            drawer.classList.toggle('open', open);
            document.getElementById('t-ai').classList.toggle('active', open);
            setTimeout(function () { try { cornerstone.resize(el, true); } catch (e) {} }, 40);
            if (open) setTimeout(function () { try { ta.focus(); } catch (e) {} }, 300);
        }
        drawer.addEventListener('transitionend', function (e) {
            if (e.propertyName === 'width') { try { cornerstone.resize(el, true); } catch (x) {} }
        });
        document.getElementById('t-ai').addEventListener('click', function () { toggle(); });
        document.getElementById('ai-close').addEventListener('click', function () { toggle(false); });

        function mdLite(s) {
            s = escH(s);
            s = s.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
            s = s.replace(/(^|[\s(>])_([^_\n]+)_/g, '$1<em>$2</em>');
            s = s.replace(/^[\-\*•]\s+/gm, '• ');
            return s.replace(/\n/g, '<br>');
        }
        function addMsg(cls, html) {
            if (empty) { empty.style.display = 'none'; }
            var d = document.createElement('div');
            d.className = 'ai-msg ' + cls;
            d.innerHTML = html;
            body.appendChild(d);
            body.scrollTop = body.scrollHeight;
            return d;
        }
        function loadingMsg() {
            return addMsg('ai', '<span class="ai-dots"><i></i><i></i><i></i></span>');
        }

        function ageAtStudy() {
            var d = studyMeta.dob || '', s = studyMeta.studyDate || '';
            if (d.length < 8) return 0;
            var by = +d.slice(0, 4), bm = +d.slice(4, 6), bd = +d.slice(6, 8), ry, rm, rd;
            if (s.length >= 8) { ry = +s.slice(0, 4); rm = +s.slice(4, 6); rd = +s.slice(6, 8); }
            else { var n = new Date(); ry = n.getFullYear(); rm = n.getMonth() + 1; rd = n.getDate(); }
            var a = ry - by; if (rm < bm || (rm === bm && rd < bd)) a--;
            return (a >= 0 && a <= 120) ? a : 0;
        }
        // SOLO contexto NO identificante. Jamás nombre / cédula / fecha de nacimiento.
        function aiContext() {
            return {
                modality:  studyMeta.modality || '',
                studyDesc: studyMeta.studyDesc || '',
                bodyPart:  currentSeriesDesc || '',
                sex:       studyMeta.sex || '',
                age:       ageAtStudy()
            };
        }
        // Captura el lienzo visible, reescalado para controlar costo (solo píxeles).
        function captureImage() {
            var ee; try { ee = cornerstone.getEnabledElement(el); } catch (e) {}
            if (!ee || !ee.canvas) return null;
            var src = ee.canvas, max = 1280;
            var sc = Math.min(1, max / Math.max(src.width, src.height));
            var cw = Math.max(1, Math.round(src.width * sc)), chh = Math.max(1, Math.round(src.height * sc));
            var c = document.createElement('canvas'); c.width = cw; c.height = chh;
            var cx = c.getContext('2d'); cx.fillStyle = '#000'; cx.fillRect(0, 0, cw, chh);
            try { cx.drawImage(src, 0, 0, cw, chh); } catch (e) { return null; }
            try { return c.toDataURL('image/jpeg', 0.9); } catch (e) { return null; }
        }

        function setBusy(b) {
            busy = b; sendBtn.disabled = b; ta.disabled = b;
            document.querySelectorAll('.ai-chip').forEach(function (x) { x.disabled = b; });
        }
        function aiFetch(payload) {
            return fetch(AI_ENDPOINT, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify(payload)
            }).then(function (r) {
                return r.json().catch(function () { return { success: false, message: 'Respuesta inválida del servidor.' }; })
                    .then(function (j) { j.__status = r.status; return j; });
            });
        }

        function run(question, mode) {
            if (busy) return;
            mode = mode || 'analyze';
            var payload = { context: aiContext(), history: convo.slice() }, userLabel = '';
            if (mode === 'compare') {
                var pair = (window.HGV && window.HGV.pro && window.HGV.pro.captureComparePair) ? window.HGV.pro.captureComparePair() : null;
                if (!pair || !pair.a || !pair.b) { addMsg('err', 'Para comparar con IA abre “⊞ Comparar”, carga dos estudios y vuelve a intentarlo.'); return; }
                payload.mode = 'compare'; payload.image = pair.a; payload.image2 = pair.b; payload.question = '';
                userLabel = '⚖️ Comparar los dos estudios en pantalla.';
            } else if (mode === 'patient') {
                var imgP = captureImage(); if (!imgP) { addMsg('err', 'No hay una imagen cargada.'); return; }
                payload.mode = 'patient'; payload.image = imgP; payload.question = '';
                userLabel = '🧑‍⚕️ Explicar al paciente esta imagen.';
            } else {
                var img = captureImage(); if (!img) { addMsg('err', 'No hay una imagen cargada para analizar.'); return; }
                var isAnalyze = (question === '__analyze__' || !question);
                payload.mode = isAnalyze ? 'analyze' : 'followup'; payload.image = img; payload.question = isAnalyze ? '' : question;
                if (!isAnalyze) userLabel = question;
            }
            if (userLabel) addMsg('user', escH(userLabel));
            var load = loadingMsg();
            setBusy(true);
            aiFetch(payload)
                .then(function (j) {
                    load.remove();
                    if (j && j.success && j.text) {
                        var critical = /HALLAZGO\s+CR[IÍ]TICO/i.test(j.text);
                        addMsg('ai' + (critical ? ' ai-critical' : ''), mdLite(j.text));
                        convo.push({ role: 'user', text: userLabel || 'Análisis de la imagen actual.' });
                        convo.push({ role: 'assistant', text: j.text });
                        if (convo.length > 16) convo = convo.slice(-16);
                    } else {
                        addMsg('err', (j && j.message) ? j.message : 'No se pudo obtener respuesta de la IA.');
                    }
                })
                .catch(function () { load.remove(); addMsg('err', 'Error de red al contactar el asistente.'); })
                .then(function () { setBusy(false); });
        }

        // Disparadores
        document.getElementById('ai-analyze').addEventListener('click', function () { run('__analyze__'); });
        document.getElementById('ai-suggest').addEventListener('click', function (e) {
            var b = e.target.closest('.ai-chip'); if (!b) return;
            if (b.getAttribute('data-mode')) run(null, b.getAttribute('data-mode'));
            else run(b.getAttribute('data-q'));
        });
        document.getElementById('ai-form').addEventListener('submit', function (e) {
            e.preventDefault();
            var q = ta.value.trim(); if (!q) return;
            ta.value = ''; ta.style.height = 'auto';
            run(q);
        });
        ta.addEventListener('input', function () { ta.style.height = 'auto'; ta.style.height = Math.min(110, ta.scrollHeight) + 'px'; });
        ta.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                var q = ta.value.trim(); if (!q) return;
                ta.value = ''; ta.style.height = 'auto';
                run(q);
            }
        });
    })();

    // ── API mínima para el módulo "pro" (mediciones avanzadas, comparación, IA+).
    //    Se monta ENCIMA del visor sin alterar su flujo: el módulo escucha los eventos
    //    hgv:ready / hgv:series y usa estas referencias. Si el módulo no carga, el visor
    //    sigue funcionando exactamente igual.
    window.HGV = {
        cornerstone: cornerstone, cstools: cstools, cwil: cwil, dicomParser: window.dicomParser, Hammer: window.Hammer,
        el: el, ROOT: ROOT, STUDY: STUDY, PID: PID, PROXY: PROXY, CSRF: CSRF, AI_ENDPOINT: AI_ENDPOINT,
        CLINIC: CLINIC, logoImg: logoImg,
        dj: dj, tag: tag, tag1: tag1, pn: pn, escH: escH, fdate: fdate,
        setActiveTool: setActiveTool, showIndex: showIndex, updateHud: updateHud, loadSeries: loadSeries, addT: addT,
        applyPreset: applyPreset, generatePdf: generatePdf,
        getStack: function () { return stack; },
        getStudyMeta: function () { return studyMeta; },
        getCurrentTool: function () { return currentTool; },
        getSeriesDesc: function () { return currentSeriesDesc; }
    };
    try { el.dispatchEvent(new CustomEvent('hgv:ready')); } catch (e) {}
})();
</script>
<link rel="stylesheet" href="<?= e(base_url('assets/css/portal-medico-visor-pro.css')) ?>?v=<?= (string)(@filemtime(__DIR__ . '/../assets/css/portal-medico-visor-pro.css') ?: 1) ?>">
<script src="<?= e(base_url('assets/js/portal-medico-visor-pro.js')) ?>?v=<?= (string)(@filemtime(__DIR__ . '/../assets/js/portal-medico-visor-pro.js') ?: 1) ?>"></script>
<link rel="stylesheet" href="<?= e(base_url('assets/css/portal-medico-mpr.css')) ?>?v=<?= (string)(@filemtime(__DIR__ . '/../assets/css/portal-medico-mpr.css') ?: 1) ?>">
<script src="<?= e(base_url('assets/js/portal-medico-mpr.js')) ?>?v=<?= (string)(@filemtime(__DIR__ . '/../assets/js/portal-medico-mpr.js') ?: 1) ?>"></script>
</body>
</html>
