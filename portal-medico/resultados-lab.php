<?php
/**
 * Resultados de laboratorio (Probeta) del portal del médico — vista aislada full-screen.
 *
 * La llamada al API interno se hace SERVER-SIDE (portal_api_call + doctor_token), igual que
 * dashboard.php/agenda.php → el JWT nunca llega al navegador y el endpoint queda scoped
 * (médico ↔ paciente; anti-IDOR por orden). El JS solo genera el PDF (jsPDF) y cierra.
 *
 * Query: ?patient=<id local>&order=<IDorden Probeta>
 */
require_once __DIR__ . '/_layout.php';
doctor_require_login();

$patient = (int)($_GET['patient'] ?? 0);
$order   = (int)($_GET['order'] ?? 0);

$res  = ($patient && $order)
    ? portal_api_call('GET', "/portal-doctor/me/patients/$patient/lab/$order", [], doctor_token())
    : ['ok' => false, 'status' => 400, 'data' => null, 'message' => 'Parámetros inválidos.'];

$ok       = !empty($res['ok']);
$data     = $res['data'] ?? [];
$sections = $data['sections'] ?? [];
$ptName   = $data['patient']['name']   ?? '';
$ptCed    = $data['patient']['cedula'] ?? '';
$fecha    = $data['fecha'] ?? null;
$errMsg   = $ok ? '' : ($res['message'] ?: 'No se pudieron cargar los resultados.');
$status   = (int)($res['status'] ?? 0);

$fechaFmt = $fecha ? date('d/m/Y · H:i', strtotime((string)$fecha)) : '';

// flags → contador de anormales (para el resumen)
$nAbn = 0; $nTot = 0;
foreach ($sections as $s) foreach ($s['examenes'] as $e) foreach ($e['analitos'] as $a) {
    $nTot++; if (in_array($a['flag'] ?? 'normal', ['high','low','critical'], true)) $nAbn++;
}

$jspdfV = (string)(@filemtime(__DIR__ . '/../assets/vendor/jspdf/jspdf.umd.min.js') ?: 1);

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
<title>Resultados de laboratorio · Hospital General Las Colinas</title>
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
    .r-main{flex:1;position:relative;min-height:0}
    .r-scroll{position:absolute;inset:0;overflow:auto;background:#3a3f4b;padding:18px 14px;-webkit-overflow-scrolling:touch}
    /* documento tipo papel */
    .paper{max-width:860px;margin:0 auto;background:#fff;color:#16203a;border-radius:8px;box-shadow:0 6px 26px rgba(0,0,0,.4);padding:30px 34px 40px}
    .p-head{display:flex;align-items:flex-start;gap:16px;border-bottom:2px solid #1e2a52;padding-bottom:16px;margin-bottom:18px}
    .p-head img{height:46px;width:auto}
    .p-head .h-t{flex:1;min-width:0}
    .p-head h1{font-size:1.15rem;color:#16203a;letter-spacing:-.01em}
    .p-head .sub{color:#64748b;font-size:.82rem;margin-top:2px}
    .p-meta{display:flex;flex-wrap:wrap;gap:8px 26px;margin-bottom:20px}
    .p-meta .m{font-size:.86rem}
    .p-meta .m b{display:block;font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;font-weight:700;margin-bottom:1px}
    .p-summary{display:inline-flex;align-items:center;gap:8px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:999px;padding:5px 13px;font-size:.8rem;color:#475569;margin-bottom:18px}
    .p-summary b{color:#16203a}
    .p-summary .dot{width:8px;height:8px;border-radius:50%;background:#16a34a}
    .p-summary.has-abn .dot{background:#dc2626}
    .sec-h{margin:22px 0 8px;font-size:.78rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;color:#1e2a52;border-left:4px solid #1e2a52;padding-left:9px}
    .ex-h{margin:14px 0 4px;font-weight:700;font-size:.92rem;color:#243156}
    table.lab{width:100%;border-collapse:collapse;margin-bottom:6px}
    table.lab th{text-align:left;font-size:.66rem;text-transform:uppercase;letter-spacing:.04em;color:#94a3b8;font-weight:700;padding:6px 10px;border-bottom:1px solid #e8ecf3}
    table.lab th.num,table.lab td.num{text-align:right;font-variant-numeric:tabular-nums}
    table.lab td{padding:8px 10px;border-bottom:1px solid #f0f3f8;font-size:.9rem;color:#243156}
    table.lab tr:last-child td{border-bottom:0}
    td.val{font-weight:700;font-variant-numeric:tabular-nums}
    tr.flag-high td.val{color:#c2410c}
    tr.flag-low  td.val{color:#1d4ed8}
    tr.flag-critical td.val{color:#fff}
    tr.flag-critical td.val .pill{background:#dc2626;color:#fff;border-radius:6px;padding:1px 7px;display:inline-block}
    .arrow{font-size:.74rem;margin-left:4px;font-weight:800}
    tr.flag-high .arrow{color:#c2410c}
    tr.flag-low .arrow{color:#1d4ed8}
    .ref{color:#64748b;font-size:.84rem;font-variant-numeric:tabular-nums}
    .p-foot{margin-top:26px;padding-top:14px;border-top:1px solid #e8ecf3;font-size:.72rem;color:#94a3b8;line-height:1.5}
    .r-msg{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:14px;color:#9aa3bb;font-size:.92rem;text-align:center;padding:28px;background:#0b0e16}
    .r-msg .ic{font-size:2.4rem}
    .r-msg .t{color:#e6e9f2;font-weight:600;font-size:1rem}
    @media (max-width:760px){
        .r-ttl{max-width:38vw}.r-btn span.lbl{display:none}.r-btn{padding:9px 11px}
        .paper{padding:20px 16px 28px}.p-head{gap:10px}.p-head img{height:36px}
    }
    @media print{ body{background:#fff;overflow:visible}.r-top{display:none}.r-main,.r-scroll{position:static;overflow:visible;background:#fff}.paper{box-shadow:none;max-width:none} }
</style>
</head>
<body>
<header class="r-top">
    <a href="#" class="r-back" id="r-close" title="Cerrar" role="button">✕</a>
    <span class="r-brand"><img src="<?= e(base_url('assets/site/logo.png')) ?>" alt="Hospital General Las Colinas"></span>
    <span class="r-ttl">Resultados de laboratorio</span>
    <div class="r-actions">
        <?php if ($ok && $sections): ?>
        <button type="button" class="r-btn primary" id="r-pdf">⤓ <span class="lbl">Descargar PDF</span></button>
        <?php endif; ?>
    </div>
</header>
<div class="r-main">
<?php if (!$ok): ?>
    <div class="r-msg">
        <div class="ic">🧪</div>
        <div class="t"><?php if ($status === 403): ?>Sin acceso a esta orden<?php elseif ($status === 404): ?>Orden no encontrada<?php else: ?>No disponible<?php endif; ?></div>
        <p><?= e($errMsg) ?></p>
    </div>
<?php elseif (!$sections): ?>
    <div class="r-msg">
        <div class="ic">🧪</div>
        <div class="t">Sin resultados validados</div>
        <p>Esta orden aún no tiene resultados validados en el laboratorio.</p>
    </div>
<?php else: ?>
    <div class="r-scroll">
        <div class="paper" id="paper">
            <div class="p-head">
                <img src="<?= e(base_url('assets/site/logo.png')) ?>" alt="HGLC">
                <div class="h-t">
                    <h1>Resultados de laboratorio</h1>
                    <div class="sub">Hospital General Las Colinas · Reporte de consulta médica</div>
                </div>
            </div>
            <div class="p-meta">
                <div class="m"><b>Paciente</b><?= e($ptName ?: '—') ?></div>
                <div class="m"><b>Cédula</b><?= e($ptCed ?: '—') ?></div>
                <div class="m"><b>Orden</b>#<?= (int)$order ?></div>
                <div class="m"><b>Fecha</b><?= e($fechaFmt ?: '—') ?></div>
            </div>
            <div class="p-summary <?= $nAbn ? 'has-abn' : '' ?>">
                <span class="dot"></span>
                <span><b><?= $nTot ?></b> parámetro<?= $nTot === 1 ? '' : 's' ?><?php if ($nAbn): ?> · <b><?= $nAbn ?></b> fuera de rango<?php else: ?> · todos dentro de rango<?php endif; ?></span>
            </div>

            <?php foreach ($sections as $s): ?>
                <div class="sec-h"><?= e($s['seccion']) ?></div>
                <?php foreach ($s['examenes'] as $ex): ?>
                    <?php if (count($s['examenes']) > 1 || trim($ex['examen']) !== trim($s['seccion'])): ?>
                        <div class="ex-h"><?= e($ex['examen']) ?></div>
                    <?php endif; ?>
                    <table class="lab">
                        <thead><tr><th>Parámetro</th><th class="num">Resultado</th><th>Unidad</th><th>Valores de referencia</th></tr></thead>
                        <tbody>
                        <?php foreach ($ex['analitos'] as $a):
                            $flag = $a['flag'] ?? 'normal';
                            $arrow = $flag === 'high' ? '↑' : ($flag === 'low' ? '↓' : '');
                        ?>
                            <tr class="flag-<?= e($flag) ?>">
                                <td><?= e($a['analito']) ?></td>
                                <td class="val num"><?php if ($flag === 'critical'): ?><span class="pill"><?= e($a['valor']) ?></span><?php else: ?><?= e($a['valor']) ?><?php if ($arrow): ?><span class="arrow"><?= $arrow ?></span><?php endif; ?><?php endif; ?></td>
                                <td><?= e($a['unidad']) ?></td>
                                <td class="ref"><?= e($a['rango']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <div class="p-foot">
                Reporte generado desde el Portal Médico del Hospital General Las Colinas el <?= e(date('d/m/Y H:i')) ?>.
                Los resultados son emitidos y validados por el laboratorio clínico. Documento de consulta; ante cualquier duda, verifique con el laboratorio.
            </div>
        </div>
    </div>
<?php endif; ?>
</div>

<script>
(function () {
    var PACIENTES_URL = <?= json_encode(base_url('portal-medico/pacientes'), JSON_UNESCAPED_SLASHES) ?>;
    document.getElementById('r-close').addEventListener('click', function (e) {
        e.preventDefault();
        if (window.history.length > 1) { window.history.back(); return; }
        window.close();
        setTimeout(function () { if (!window.closed) location.href = PACIENTES_URL; }, 150);
    });

    var rep = <?= json_encode([
        'patient'  => $ptName,
        'cedula'   => $ptCed,
        'order'    => $order,
        'fecha'    => $fechaFmt,
        'nTot'     => $nTot,
        'nAbn'     => $nAbn,
        'sections' => $sections,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    var pdfBtn = document.getElementById('r-pdf');
    if (!pdfBtn) return;
    var LOGO = <?= json_encode(base_url('assets/site/logo.png'), JSON_UNESCAPED_SLASHES) ?>;
    var JSPDF = <?= json_encode(base_url('assets/vendor/jspdf/jspdf.umd.min.js') . '?v=' . $jspdfV, JSON_UNESCAPED_SLASHES) ?>;

    function loadScript(src){ return new Promise(function(res,rej){ var s=document.createElement('script'); s.src=src; s.onload=res; s.onerror=rej; document.head.appendChild(s); }); }
    function loadLogo(){ return new Promise(function(res){ var img=new Image(); img.crossOrigin='anonymous'; img.onload=function(){ try{ var c=document.createElement('canvas'); c.width=img.naturalWidth; c.height=img.naturalHeight; c.getContext('2d').drawImage(img,0,0); res({d:c.toDataURL('image/png'),w:img.naturalWidth,h:img.naturalHeight}); }catch(e){ res(null);} }; img.onerror=function(){res(null);}; img.src=LOGO; }); }

    pdfBtn.addEventListener('click', async function () {
        pdfBtn.disabled = true; var old = pdfBtn.innerHTML; pdfBtn.innerHTML = '⏳ <span class="lbl">Generando…</span>';
        try {
            if (!window.jspdf) await loadScript(JSPDF);
            var logo = await loadLogo();
            var jsPDF = window.jspdf.jsPDF;
            var doc = new jsPDF({ unit: 'pt', format: 'letter' });
            var W = doc.internal.pageSize.getWidth(), H = doc.internal.pageSize.getHeight();
            var M = 44, y = 46;
            var COL = { name: M, res: 320, unit: 392, ref: 470 };

            function header() {
                if (logo) { var lw = 92, lh = lw * (logo.h / logo.w); doc.addImage(logo.d, 'PNG', M, y - 6, lw, lh); }
                doc.setTextColor(22,32,58); doc.setFont('helvetica','bold'); doc.setFontSize(14);
                doc.text('Resultados de laboratorio', W - M, y + 4, { align: 'right' });
                doc.setFont('helvetica','normal'); doc.setFontSize(8.5); doc.setTextColor(120,130,150);
                doc.text('Hospital General Las Colinas', W - M, y + 18, { align: 'right' });
                y += 42;
                doc.setDrawColor(30,42,82); doc.setLineWidth(1.2); doc.line(M, y, W - M, y); y += 18;
                doc.setFontSize(9); doc.setTextColor(40,49,86);
                doc.setFont('helvetica','bold'); doc.text(String(rep.patient || '—'), M, y);
                doc.setFont('helvetica','normal'); doc.setTextColor(110,120,140);
                doc.text('Cédula: ' + (rep.cedula || '—') + '    Orden #' + rep.order + '    ' + (rep.fecha || ''), M, y + 13);
                doc.setTextColor(rep.nAbn ? 190 : 22, rep.nAbn ? 30 : 130, rep.nAbn ? 30 : 70);
                doc.text(rep.nTot + ' parámetros · ' + (rep.nAbn ? (rep.nAbn + ' fuera de rango') : 'todos en rango'), W - M, y + 13, { align: 'right' });
                y += 28;
            }
            function pageBreak(extra){ if (y + (extra||0) > H - 50) { foot(); doc.addPage(); y = 50; } }
            function foot() {
                doc.setFont('helvetica','normal'); doc.setFontSize(7.2); doc.setTextColor(150,160,175);
                doc.text('Generado desde el Portal Médico · HGLC · ' + new Date().toLocaleString('es-DO') + ' — resultados emitidos por el laboratorio clínico.', M, H - 28, { maxWidth: W - 2*M });
            }
            function row(a, alt) {
                pageBreak(16);
                var flag = a.flag || 'normal';
                if (alt) { doc.setFillColor(248,250,252); doc.rect(M, y - 9, W - 2*M, 16, 'F'); }
                doc.setFontSize(8.6); doc.setFont('helvetica','normal'); doc.setTextColor(36,49,86);
                doc.text(String(a.analito || '').slice(0,46), COL.name, y);
                var val = String(a.valor || '');
                if (flag === 'high') { doc.setTextColor(194,65,12); doc.setFont('helvetica','bold'); val += ' ↑'; }
                else if (flag === 'low') { doc.setTextColor(29,78,216); doc.setFont('helvetica','bold'); val += ' ↓'; }
                else if (flag === 'critical') { doc.setTextColor(220,38,38); doc.setFont('helvetica','bold'); val += ' !'; }
                else { doc.setFont('helvetica','bold'); }
                doc.text(val, COL.unit - 6, y, { align: 'right' });
                doc.setFont('helvetica','normal'); doc.setTextColor(110,120,140);
                doc.text(String(a.unidad || '').slice(0,12), COL.unit, y);
                doc.text(String(a.rango || '').slice(0,22), COL.ref, y);
                y += 16;
            }
            header();
            rep.sections.forEach(function (s) {
                pageBreak(30);
                doc.setFont('helvetica','bold'); doc.setFontSize(9); doc.setTextColor(30,42,82);
                doc.text(String(s.seccion || '').toUpperCase(), M, y); y += 6;
                doc.setDrawColor(210,216,228); doc.setLineWidth(.6); doc.line(M, y, W - M, y); y += 14;
                s.examenes.forEach(function (ex) {
                    if (s.examenes.length > 1) { pageBreak(20); doc.setFont('helvetica','bold'); doc.setFontSize(8.8); doc.setTextColor(36,49,86); doc.text(String(ex.examen || ''), M, y); y += 14; }
                    ex.analitos.forEach(function (a, i) { row(a, i % 2 === 1); });
                    y += 6;
                });
                y += 6;
            });
            foot();
            var fn = 'Resultados_' + String(rep.patient||'').replace(/[^a-z0-9]+/gi,'_').slice(0,30) + '_orden' + rep.order + '.pdf';
            doc.save(fn);
        } catch (e) {
            alert('No se pudo generar el PDF. Puedes usar la opción de imprimir del navegador.');
        } finally {
            pdfBtn.disabled = false; pdfBtn.innerHTML = old;
        }
    });
})();
</script>
</body>
</html>
