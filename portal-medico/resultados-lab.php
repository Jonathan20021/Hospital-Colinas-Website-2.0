<?php
/**
 * Resultados de laboratorio (Probeta) — réplica del formato del reporte oficial del laboratorio.
 *
 * La llamada al API interno se hace SERVER-SIDE (portal_api_call + doctor_token) → el JWT nunca
 * llega al navegador y el endpoint queda scoped (médico ↔ paciente; anti-IDOR por orden).
 * El JS solo genera el PDF (jsPDF, mismo formato) y cierra. Sin PHI en caché (network-first del SW).
 *
 * El layout reproduce el reporte de Probeta (Crystal Reports): membrete del hospital, cabecera de
 * 2 cajas, departamentos con MUESTRA y VALIDADO POR. La firma manuscrita y el sello del laboratorio
 * NO están en la BD (viven en el .rpt) → se sustituyen por la validación electrónica del bioanalista.
 *
 * Query: ?patient=<id local>&order=<IDorden Probeta>
 */
require_once __DIR__ . '/_layout.php';
doctor_require_login();

// Datos fijos del membrete del hospital (del reporte oficial)
const LAB_DIR = 'Av. Imbert, casi esquina 27 de Febrero, Gurabito, Santiago, Rep. Dom.';
const LAB_TEL = '(809)-806-0444';
const LAB_WEB = 'www.colinashospital.com';
const LAB_RNC = '131293281';

$patient = (int)($_GET['patient'] ?? 0);
$order   = (int)($_GET['order'] ?? 0);

$res = ($patient && $order)
    ? portal_api_call('GET', "/portal-doctor/me/patients/$patient/lab/$order", [], doctor_token())
    : ['ok' => false, 'status' => 400, 'data' => null, 'message' => 'Parámetros inválidos.'];

$ok     = !empty($res['ok']);
$data   = $res['data'] ?? [];
$hd     = $data['header'] ?? [];
$deps   = $data['departamentos'] ?? [];
$pac    = $hd['paciente'] ?? [];
$errMsg = $ok ? '' : ($res['message'] ?: 'No se pudieron cargar los resultados.');
$status = (int)($res['status'] ?? 0);

function lab_fecha($s) {
    $t = strtotime((string)$s); if (!$t) return '';
    $ap = (int)date('G', $t) < 12 ? 'a.m' : 'p.m';
    return date('d/m/Y h:i', $t) . ' ' . $ap;
}
$flagLabel = ['high' => 'Alto', 'low' => 'Bajo', 'critical' => 'Crítico'];

// contadores
$nAbn = 0; $nTot = 0;
foreach ($deps as $d) foreach ($d['examenes'] as $e) foreach ($e['analitos'] as $a) {
    $nTot++; if (in_array($a['flag'] ?? 'normal', ['high','low','critical'], true)) $nAbn++;
}
// validadores únicos (para el bloque de firma)
$validadores = [];
foreach ($deps as $d) foreach ($d['examenes'] as $e) if (!empty($e['validado']['nombre'])) $validadores[$e['validado']['nombre']] = true;
$validadores = array_keys($validadores);

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
<title>Informe de resultados · Hospital General Las Colinas</title>
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
    .r-ttl{font-weight:700;font-size:.95rem;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .r-actions{display:flex;align-items:center;gap:8px;margin-left:auto}
    .r-btn{appearance:none;border:1px solid #2b3550;background:#1a2236;color:#cdd4e6;font:inherit;font-size:.82rem;border-radius:9px;padding:8px 12px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;white-space:nowrap;text-decoration:none}
    .r-btn:hover{background:#222c45}
    .r-btn.primary{background:#2563eb;border-color:#3b82f6;color:#fff}
    .r-btn.primary:hover{background:#1d4ed8}
    .r-main{flex:1;position:relative;min-height:0}
    .r-scroll{position:absolute;inset:0;overflow:auto;background:#3a3f4b;padding:18px 12px;-webkit-overflow-scrolling:touch}
    /* ───── documento (réplica del reporte de Probeta) ───── */
    .paper{max-width:830px;margin:0 auto;background:#fff;color:#1a1a1a;border-radius:4px;box-shadow:0 6px 26px rgba(0,0,0,.4);padding:26px 30px 30px;font-size:12px;line-height:1.32}
    .mb{display:flex;align-items:flex-start;gap:18px;margin-bottom:6px}
    .mb img{height:54px;width:auto;flex:none}
    .mb .addr{flex:1;text-align:right;font-size:10.5px;color:#222;line-height:1.45}
    .lab-title{text-align:center;font-style:italic;font-weight:700;font-size:14px;color:#111;margin:8px 0 12px;letter-spacing:.02em}
    .cab{display:flex;gap:12px;margin-bottom:14px}
    .cab .box{flex:1;border:1px solid #b9c0cc;border-radius:2px;padding:7px 10px}
    .cab .row{display:flex;gap:6px;font-size:11px;padding:1px 0}
    .cab .row .k{color:#333;min-width:96px;flex:none}
    .cab .row .k.short{min-width:84px}
    .cab .row .v{color:#000;font-weight:600}
    .cab .row .v.b{font-weight:800}
    .dep{margin-top:14px}
    .dep-bar{border-top:1.5px solid #1f2a4d;border-bottom:1.5px solid #1f2a4d;padding:3px 2px;font-style:italic;font-weight:800;font-size:12px;color:#1f2a4d}
    .cols{display:grid;grid-template-columns:1fr 90px 90px 150px;gap:4px;padding:5px 2px 3px;font-weight:700;font-size:10.5px;color:#333}
    .cols .num{text-align:left}
    .cols .rng{text-align:left}
    .ex{font-style:italic;font-weight:800;font-size:11.5px;color:#1a1a1a;margin:7px 0 2px}
    .an{display:grid;grid-template-columns:1fr 90px 90px 150px;gap:4px;padding:2px 2px;font-size:11px;border-bottom:1px solid #f0f0f0;align-items:baseline}
    .an .nm{color:#222}
    .an .vl{font-weight:700;font-variant-numeric:tabular-nums}
    .an .un{color:#333}
    .an .rg{color:#444;font-variant-numeric:tabular-nums}
    .an.f-high .vl{color:#b91c1c}
    .an.f-low  .vl{color:#1d4ed8}
    .an.f-critical .vl{color:#fff;background:#b91c1c;border-radius:3px;padding:0 5px;display:inline-block}
    .an .tag{font-weight:700;font-size:10px;margin-left:5px}
    .an.f-high .tag{color:#b91c1c}.an.f-low .tag{color:#1d4ed8}.an.f-critical .tag{color:#b91c1c}
    .vp{font-size:9.5px;color:#555;padding:4px 2px 2px;line-height:1.5}
    .vp b{color:#333;font-weight:700}
    .firma{margin-top:36px;display:flex;justify-content:flex-end;text-align:center}
    .firma .blk{min-width:240px;border-top:1px solid #333;padding-top:4px;font-size:10.5px;color:#333}
    .firma .blk .nm{font-weight:700;color:#111;font-size:11px}
    .pie{margin-top:20px;padding-top:8px;border-top:1px solid #e3e3e3;display:flex;justify-content:space-between;font-size:9px;color:#888}
    .r-msg{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:14px;color:#9aa3bb;font-size:.92rem;text-align:center;padding:28px;background:#0b0e16}
    .r-msg .ic{font-size:2.4rem}.r-msg .t{color:#e6e9f2;font-weight:600;font-size:1rem}
    @media (max-width:760px){
        .r-ttl{max-width:40vw}.r-btn span.lbl{display:none}.r-btn{padding:9px 11px}
        .paper{padding:16px 12px;font-size:11px}.cab{flex-direction:column}.mb img{height:42px}
        .cols,.an{grid-template-columns:1fr 64px 60px 92px}
    }
    @media print{ body{background:#fff;overflow:visible}.r-top{display:none}.r-main,.r-scroll{position:static;overflow:visible;background:#fff}.paper{box-shadow:none;max-width:none} }
</style>
</head>
<body>
<header class="r-top">
    <a href="#" class="r-back" id="r-close" title="Cerrar" role="button">✕</a>
    <span class="r-ttl">Informe de resultados</span>
    <div class="r-actions">
        <?php if ($ok && $deps): ?>
        <button type="button" class="r-btn primary" id="r-pdf">⤓ <span class="lbl">Descargar PDF</span></button>
        <?php endif; ?>
    </div>
</header>
<div class="r-main">
<?php if (!$ok): ?>
    <div class="r-msg"><div class="ic">🧪</div>
        <div class="t"><?php if ($status === 403): ?>Sin acceso a esta orden<?php elseif ($status === 404): ?>Orden no encontrada<?php else: ?>No disponible<?php endif; ?></div>
        <p><?= e($errMsg) ?></p></div>
<?php elseif (!$deps): ?>
    <div class="r-msg"><div class="ic">🧪</div><div class="t">Sin resultados validados</div>
        <p>Esta orden aún no tiene resultados validados en el laboratorio.</p></div>
<?php else: ?>
    <div class="r-scroll"><div class="paper" id="paper">
        <!-- Membrete -->
        <div class="mb">
            <img src="<?= e(base_url('assets/site/logo.png')) ?>" alt="Colinas Hospital General">
            <div class="addr"><?= e(LAB_DIR) ?><br>Tel.:<?= e(LAB_TEL) ?><br><?= e(LAB_WEB) ?><br>R.N.C.: <?= e(LAB_RNC) ?></div>
        </div>
        <div class="lab-title">INFORME DE RESULTADOS</div>
        <!-- Cabecera (2 cajas) -->
        <div class="cab">
            <div class="box">
                <div class="row"><span class="k">No. Orden:</span><span class="v b"><?= (int)$hd['orden'] ?></span></div>
                <div class="row"><span class="k">ID Paciente:</span><span class="v"><?= e($pac['id_probeta'] ?? '') ?></span></div>
                <div class="row"><span class="k">Nombre:</span><span class="v"><?= e($pac['nombre'] ?? '') ?></span></div>
                <div class="row"><span class="k">Documento:</span><span class="v"><?= e($pac['documento'] ?? '') ?></span></div>
                <div class="row"><span class="k">Edad:</span><span class="v"><?= e($pac['edad'] ?? '') ?></span><span class="k short" style="margin-left:12px">Sexo:</span><span class="v"><?= e($pac['sexo'] ?? '') ?></span></div>
                <div class="row"><span class="k">Dirección:</span><span class="v" style="font-weight:600"><?= e($pac['direccion'] ?? '') ?></span></div>
            </div>
            <div class="box">
                <div class="row"><span class="k">Fecha Facturación:</span><span class="v"><?= e(lab_fecha($hd['fecha'] ?? '')) ?></span></div>
                <div class="row"><span class="k">Tipo de Orden:</span><span class="v"><?= e($hd['tipo_orden'] ?? '') ?></span></div>
                <div class="row"><span class="k">Ubicación:</span><span class="v"><?= e($hd['ubicacion'] ?? '') ?></span></div>
                <div class="row"><span class="k">Doctor:</span><span class="v"><?= e($hd['doctor'] ?? '') ?></span></div>
                <div class="row"><span class="k">Seguro Médico:</span><span class="v"><?= e($hd['seguro'] ?? '') ?></span></div>
                <div class="row"><span class="k">Procedencia:</span><span class="v"><?= e($hd['procedencia'] ?? '') ?></span></div>
            </div>
        </div>

        <?php foreach ($deps as $d): ?>
            <div class="dep">
                <div class="dep-bar">DEPARTAMENTO: <?= e($d['nombre']) ?></div>
                <div class="cols"><span>&nbsp;</span><span class="num">Resultado</span><span>Unidad</span><span class="rng">Rangos de Referencia</span></div>
                <?php foreach ($d['examenes'] as $ex):
                    $single = count($ex['analitos']) === 1;
                    $firstName = $single ? mb_strtolower(trim($ex['analitos'][0]['analito'])) : '';
                    $sameName  = $single && ($firstName === mb_strtolower(trim($ex['examen'])));
                ?>
                    <?php if (!$sameName): ?><div class="ex"><?= e($ex['examen']) ?></div><?php endif; ?>
                    <?php foreach ($ex['analitos'] as $a):
                        $flag = $a['flag'] ?? 'normal';
                        $tag = $flagLabel[$flag] ?? '';
                        // si es examen de un solo analito con el mismo nombre, el "nombre" es el del examen (cursiva)
                    ?>
                        <div class="an f-<?= e($flag) ?>">
                            <span class="nm"<?= $sameName ? ' style="font-style:italic;font-weight:800"' : '' ?>><?= e($sameName ? $ex['examen'] : $a['analito']) ?></span>
                            <span class="vl"><?= e($a['valor']) ?><?php if ($tag): ?><span class="tag"><?= $tag ?></span><?php endif; ?></span>
                            <span class="un"><?= e($a['unidad']) ?></span>
                            <span class="rg"><?= e($a['rango']) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="vp">
                        <?php if (!empty($ex['muestra'])): ?><b>MUESTRA:</b> <?= e($ex['muestra']) ?><br><?php endif; ?>
                        <?php if (!empty($ex['validado']['nombre'])): ?><b>VALIDADO POR:</b> <?= e($ex['validado']['nombre']) ?> <?= e(lab_fecha($ex['validado']['fecha'] ?? '')) ?><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <!-- Firma (validación electrónica; el sello/firma manuscrita del lab vive en el sistema) -->
        <div class="firma"><div class="blk">
            <?php if ($validadores): ?><div class="nm"><?= e($validadores[0]) ?></div><?php endif; ?>
            Firma Autorizada
        </div></div>
        <div class="pie">
            <span>Portal Médico · Hospital General Las Colinas</span>
            <span>Validado electrónicamente · <?= e(date('d/m/Y H:i')) ?></span>
        </div>
    </div></div>
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

    var pdfBtn = document.getElementById('r-pdf');
    if (!pdfBtn) return;
    var REP = <?= json_encode([
        'membrete' => ['dir' => LAB_DIR, 'tel' => LAB_TEL, 'web' => LAB_WEB, 'rnc' => LAB_RNC],
        'header'   => $hd,
        'deps'     => $deps,
        'validador'=> $validadores[0] ?? '',
        'flagLabel'=> $flagLabel,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var LOGO  = <?= json_encode(base_url('assets/site/logo.png'), JSON_UNESCAPED_SLASHES) ?>;
    var JSPDF = <?= json_encode(base_url('assets/vendor/jspdf/jspdf.umd.min.js') . '?v=' . $jspdfV, JSON_UNESCAPED_SLASHES) ?>;

    function loadScript(src){ return new Promise(function(res,rej){ var s=document.createElement('script'); s.src=src; s.onload=res; s.onerror=rej; document.head.appendChild(s); }); }
    function loadLogo(){ return new Promise(function(res){ var i=new Image(); i.crossOrigin='anonymous'; i.onload=function(){ try{ var c=document.createElement('canvas'); c.width=i.naturalWidth; c.height=i.naturalHeight; c.getContext('2d').drawImage(i,0,0); res({d:c.toDataURL('image/png'),w:i.naturalWidth,h:i.naturalHeight}); }catch(e){ res(null);} }; i.onerror=function(){res(null);}; i.src=LOGO; }); }
    function fdate(s){ if(!s) return ''; var d=new Date(String(s).replace(' ','T')); if(isNaN(d)) return String(s).slice(0,16); var ap=d.getHours()<12?'a.m':'p.m'; var p=function(n){return('0'+n).slice(-2)}; return p(d.getDate())+'/'+p(d.getMonth()+1)+'/'+d.getFullYear()+' '+p(((d.getHours()+11)%12)+1)+':'+p(d.getMinutes())+' '+ap; }

    pdfBtn.addEventListener('click', async function () {
        pdfBtn.disabled = true; var old = pdfBtn.innerHTML; pdfBtn.innerHTML = '⏳ <span class="lbl">Generando…</span>';
        try {
            if (!window.jspdf) await loadScript(JSPDF);
            var logo = await loadLogo();
            var doc = new window.jspdf.jsPDF({ unit: 'pt', format: 'letter' });
            var W = doc.internal.pageSize.getWidth(), H = doc.internal.pageSize.getHeight();
            var M = 40, y = 0;
            var h = REP.header, p = h.paciente || {};
            var COLv = 300, COLu = 388, COLr = 470; // x de columnas Resultado/Unidad/Rango

            function membrete() {
                y = 40;
                if (logo) { var lw = 150, lh = lw*(logo.h/logo.w); doc.addImage(logo.d,'PNG',M,y,lw,lh); }
                doc.setFont('helvetica','normal'); doc.setFontSize(8); doc.setTextColor(34,34,34);
                doc.text(REP.membrete.dir, W-M, y+6, {align:'right'});
                doc.text('Tel.:'+REP.membrete.tel, W-M, y+17, {align:'right'});
                doc.text(REP.membrete.web, W-M, y+28, {align:'right'});
                doc.text('R.N.C.: '+REP.membrete.rnc, W-M, y+39, {align:'right'});
                y += 52;
                doc.setFont('helvetica','bolditalic'); doc.setFontSize(13); doc.setTextColor(17,17,17);
                doc.text('INFORME DE RESULTADOS', W/2, y, {align:'center'}); y += 12;
                // cajas
                var bx = M, bw = (W-2*M-12)/2, bh = 66, by = y;
                doc.setDrawColor(150,160,175); doc.setLineWidth(.6);
                doc.rect(bx, by, bw, bh); doc.rect(bx+bw+12, by, bw, bh);
                doc.setFontSize(8.5);
                function kv(x,yy,k,v,bold){ doc.setFont('helvetica','normal'); doc.setTextColor(60,60,60); doc.text(k,x,yy); doc.setFont('helvetica',bold?'bold':'bold'); doc.setTextColor(0,0,0); doc.text(String(v||''),x+72,yy); }
                var ly = by+12, lx = bx+7;
                kv(lx,ly,'No. Orden:',h.orden,true); ly+=10.5;
                kv(lx,ly,'ID Paciente:',p.id_probeta); ly+=10.5;
                kv(lx,ly,'Nombre:',p.nombre); ly+=10.5;
                kv(lx,ly,'Documento:',p.documento); ly+=10.5;
                kv(lx,ly,'Edad:',(p.edad||'')+'    Sexo: '+(p.sexo||'')); ly+=10.5;
                kv(lx,ly,'Dirección:',p.direccion);
                var ry = by+12, rx = bx+bw+12+7;
                kv(rx,ry,'Fecha Fact.:',fdate(h.fecha)); ry+=10.5;
                kv(rx,ry,'Tipo Orden:',h.tipo_orden); ry+=10.5;
                kv(rx,ry,'Ubicación:',h.ubicacion); ry+=10.5;
                kv(rx,ry,'Doctor:',h.doctor); ry+=10.5;
                kv(rx,ry,'Seguro:',h.seguro); ry+=10.5;
                kv(rx,ry,'Procedencia:',h.procedencia);
                y = by + bh + 14;
            }
            function foot(pg) {
                doc.setDrawColor(225,225,225); doc.setLineWidth(.5); doc.line(M,H-30,W-M,H-30);
                doc.setFont('helvetica','normal'); doc.setFontSize(7.5); doc.setTextColor(140,140,140);
                doc.text('Portal Médico · Hospital General Las Colinas', M, H-20);
                doc.text('Validado electrónicamente · Página '+pg, W-M, H-20, {align:'right'});
            }
            var page = 1;
            function brk(extra){ if (y+(extra||0) > H-46) { foot(page); doc.addPage(); page++; membrete(); } }

            membrete();
            REP.deps.forEach(function (d) {
                brk(40);
                doc.setDrawColor(31,42,77); doc.setLineWidth(1.2);
                doc.line(M,y-2,W-M,y-2);
                doc.setFont('helvetica','bolditalic'); doc.setFontSize(10); doc.setTextColor(31,42,77);
                doc.text('DEPARTAMENTO: '+d.nombre, M, y+8);
                doc.line(M,y+11,W-M,y+11); y+=20;
                doc.setFont('helvetica','bold'); doc.setFontSize(8); doc.setTextColor(60,60,60);
                doc.text('Resultado',COLv,y); doc.text('Unidad',COLu,y); doc.text('Rangos de Referencia',COLr,y); y+=11;

                d.examenes.forEach(function (ex) {
                    var single = ex.analitos.length===1;
                    var same = single && ex.analitos[0].analito.toLowerCase().trim()===ex.examen.toLowerCase().trim();
                    brk(20);
                    if (!same) { doc.setFont('helvetica','bolditalic'); doc.setFontSize(9); doc.setTextColor(20,20,20); doc.text(ex.examen, M, y); y+=12; }
                    ex.analitos.forEach(function (a) {
                        brk(13);
                        var flag=a.flag||'normal';
                        doc.setFontSize(8.4);
                        if (same){ doc.setFont('helvetica','bolditalic'); } else { doc.setFont('helvetica','normal'); }
                        doc.setTextColor(34,34,34); doc.text(String(a.analito).slice(0,52), M, y);
                        // valor con color/etiqueta
                        var tag = (REP.flagLabel[flag]||'');
                        if (flag==='high'){ doc.setTextColor(185,28,28); doc.setFont('helvetica','bold'); }
                        else if (flag==='low'){ doc.setTextColor(29,78,216); doc.setFont('helvetica','bold'); }
                        else if (flag==='critical'){ doc.setTextColor(185,28,28); doc.setFont('helvetica','bold'); }
                        else { doc.setTextColor(0,0,0); doc.setFont('helvetica','bold'); }
                        doc.text(String(a.valor)+(tag?('  '+tag):''), COLv, y);
                        doc.setFont('helvetica','normal'); doc.setTextColor(50,50,50);
                        doc.text(String(a.unidad||'').slice(0,12), COLu, y);
                        doc.text(String(a.rango||'').slice(0,24), COLr, y);
                        doc.setDrawColor(240,240,240); doc.setLineWidth(.4); doc.line(M,y+3,W-M,y+3);
                        y+=12.5;
                    });
                    brk(16);
                    doc.setFont('helvetica','normal'); doc.setFontSize(7.2); doc.setTextColor(90,90,90);
                    var vp = '';
                    if (ex.muestra) vp += 'MUESTRA: '+ex.muestra+'    ';
                    if (ex.validado && ex.validado.nombre) vp += 'VALIDADO POR: '+ex.validado.nombre+' '+fdate(ex.validado.fecha);
                    if (vp) { doc.text(vp, M, y); y+=12; } else { y+=4; }
                });
                y += 8;
            });
            // firma
            brk(60); y += 24;
            doc.setDrawColor(60,60,60); doc.setLineWidth(.6); doc.line(W-M-200, y, W-M, y);
            doc.setFont('helvetica','bold'); doc.setFontSize(8.5); doc.setTextColor(17,17,17);
            if (REP.validador) doc.text(REP.validador, W-M-100, y+11, {align:'center'});
            doc.setFont('helvetica','normal'); doc.setTextColor(80,80,80);
            doc.text('Firma Autorizada', W-M-100, y+22, {align:'center'});
            foot(page);

            var fn = 'Resultados_'+String(p.nombre||'').replace(/[^a-z0-9]+/gi,'_').slice(0,30)+'_orden'+h.orden+'.pdf';
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
