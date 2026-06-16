<?php
/**
 * Informe radiológico (PDF de Autana) del portal del médico.
 * Full-screen, aislado. Embebe el MISMO PDF del RIS de Autana servido a través del proxy
 * de mismo origen /api/imaging-report.php/{scope}/{studyUID} (que reenvía a JENOFONTE con
 * el JWT del médico; el unq se resuelve y valida server-side). El navegador nunca ve el
 * unq, la cookie de Autana ni la IP interna del RIS.
 *
 * Query: ?study=<StudyInstanceUID>&scope=<scope-token>[&u=<unq>]
 */
require_once __DIR__ . '/_layout.php';
doctor_require_login();

$study = preg_replace('/[^0-9.]/', '', (string)($_GET['study'] ?? ''));
$scope = preg_replace('/[^A-Za-z0-9._-]/', '', (string)($_GET['scope'] ?? ''));
$unq   = preg_replace('/[^A-Za-z0-9_]/', '', (string)($_GET['u'] ?? ''));

$base = base_url('api/imaging-report.php') . '/' . rawurlencode($scope) . '/' . rawurlencode($study);
$pdfUrl = $base . ($unq !== '' ? '?u=' . rawurlencode($unq) : '');

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
<title>Informe radiológico · Hospital General Las Colinas</title>
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
    .r-top{display:flex;align-items:center;gap:8px 12px;padding:calc(9px + env(safe-area-inset-top)) calc(14px + env(safe-area-inset-right)) 9px calc(14px + env(safe-area-inset-left));background:#111726;border-bottom:1px solid #232c42;flex-wrap:wrap}
    .r-back{color:#cdd4e6;text-decoration:none;font-size:1rem;display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;background:#1a2236;border:1px solid #2b3550;cursor:pointer}
    .r-back:hover{background:#222c45}
    .r-brand{display:inline-flex;align-items:center;background:#fff;border-radius:7px;padding:4px 9px;height:34px;flex:none}
    .r-brand img{height:24px;width:auto;display:block}
    .r-ttl{font-weight:700;font-size:.95rem;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .r-actions{display:flex;align-items:center;gap:8px;margin-left:auto}
    .r-btn{appearance:none;border:1px solid #2b3550;background:#1a2236;color:#cdd4e6;font:inherit;font-size:.82rem;border-radius:9px;padding:8px 12px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;white-space:nowrap;text-decoration:none}
    .r-btn:hover{background:#222c45}
    .r-btn.primary{background:#2563eb;border-color:#3b82f6;color:#fff}
    .r-btn.primary:hover{background:#1d4ed8}
    .r-main{flex:1;position:relative;min-height:0;background:#1f2430}
    #pdf{width:100%;height:100%;border:0;display:none;background:#525659}
    .r-msg{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:14px;color:#9aa3bb;font-size:.92rem;text-align:center;padding:28px}
    .r-spin{width:38px;height:38px;border:3px solid #2b3550;border-top-color:#6d8bff;border-radius:50%;animation:rspin 1s linear infinite}
    @keyframes rspin{to{transform:rotate(360deg)}}
    .r-msg .ic{font-size:2.4rem}
    .r-msg .t{color:#e6e9f2;font-weight:600;font-size:1rem}
    .r-msg p{max-width:340px;line-height:1.5}
    @media (max-width:760px){
        .r-ttl{max-width:38vw}
        .r-btn span.lbl{display:none}
        .r-btn{padding:9px 11px}
    }
</style>
</head>
<body>
<header class="r-top">
    <a href="#" class="r-back" id="r-close" title="Cerrar" role="button">✕</a>
    <span class="r-brand"><img src="<?= e(base_url('assets/site/logo.png')) ?>" alt="Hospital General Las Colinas"></span>
    <span class="r-ttl">Informe radiológico</span>
    <div class="r-actions">
        <a href="#" class="r-btn" id="r-open" title="Abrir en otra pestaña" target="_blank" rel="noopener">⤢ <span class="lbl">Abrir</span></a>
        <a href="#" class="r-btn primary" id="r-dl" title="Descargar PDF" download>⤓ <span class="lbl">Descargar</span></a>
    </div>
</header>
<div class="r-main">
    <iframe id="pdf" title="Informe radiológico"></iframe>
    <div class="r-msg" id="r-msg"><div class="r-spin"></div><div>Cargando informe…</div></div>
</div>
<script>
(function () {
    'use strict';
    var PDF_URL  = <?= json_encode($pdfUrl, JSON_UNESCAPED_SLASHES) ?>;
    var STUDY    = <?= json_encode($study) ?>;
    var PACIENTES_URL = <?= json_encode(base_url('portal-medico/pacientes'), JSON_UNESCAPED_SLASHES) ?>;
    var frame = document.getElementById('pdf');
    var msgEl = document.getElementById('r-msg');
    var dlEl  = document.getElementById('r-dl');
    var openEl = document.getElementById('r-open');

    function showMsg(ic, title, text) {
        msgEl.innerHTML = '<div class="ic">' + ic + '</div>'
            + (title ? '<div class="t">' + title + '</div>' : '')
            + (text ? '<p>' + text + '</p>' : '');
        msgEl.style.display = 'flex';
        frame.style.display = 'none';
    }

    if (!STUDY || !PDF_URL || /\/$/.test(PDF_URL.split('?')[0])) {
        showMsg('⚠', 'Enlace inválido', 'No se pudo identificar el estudio.');
        return;
    }

    // Descargamos el PDF como blob (mismo origen) → render inline fiable + descarga/abrir
    // sin depender de la disposición del servidor. Si el proxy devuelve 404/JSON (sin
    // informe o no autorizado), mostramos el mensaje correspondiente.
    fetch(PDF_URL, { credentials: 'same-origin' })
        .then(function (r) {
            var ct = (r.headers.get('Content-Type') || '').toLowerCase();
            if (!r.ok || ct.indexOf('application/pdf') < 0) {
                return r.text().then(function (t) {
                    var m = '';
                    try { m = (JSON.parse(t) || {}).message || ''; } catch (e) {}
                    if (r.status === 404) showMsg('📄', 'Sin informe', m || 'Este estudio aún no tiene un informe disponible.');
                    else if (r.status === 401) showMsg('🔒', 'Sesión expirada', 'Vuelve a iniciar sesión en el portal.');
                    else if (r.status === 403) showMsg('⛔', 'No autorizado', 'No tienes acceso a este informe.');
                    else showMsg('⚠', 'No disponible', m || 'No se pudo cargar el informe en este momento.');
                    throw new Error('no-pdf');
                });
            }
            return r.blob();
        })
        .then(function (blob) {
            var url = URL.createObjectURL(blob);
            frame.src = url;
            frame.style.display = 'block';
            msgEl.style.display = 'none';
            dlEl.href = url; dlEl.setAttribute('download', 'informe-' + STUDY + '.pdf');
            openEl.href = url;
        })
        .catch(function (e) {
            if (e && e.message === 'no-pdf') return;   // ya mostramos el mensaje
            showMsg('⚠', 'Error de red', 'No se pudo contactar el servidor del informe.');
        });

    // Cerrar adaptado al PWA: dentro de la app → atrás; pestaña nueva → cerrar; si no → lista.
    document.getElementById('r-close').addEventListener('click', function (e) {
        e.preventDefault();
        if (window.history.length > 1) { window.history.back(); return; }
        window.close();
        setTimeout(function () { if (!window.closed) location.href = PACIENTES_URL; }, 150);
    });
})();
</script>
</body>
</html>
