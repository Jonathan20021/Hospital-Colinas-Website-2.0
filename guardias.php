<?php
/**
 * Listas de servicio (guardias) — VISTA PÚBLICA SEGURA POR ENLACE.
 *
 * Para médicos sin acceso al portal: ven el roster de guardias desde su teléfono
 * o computadora con un enlace tokenizado, sin login. El token (compartido,
 * regenerable) viaja en ?t= y se valida server-side contra la API interna
 * (settings.service_lists_public_token). El navegador nunca ve nada interno.
 *
 * Aislada y endurecida: sin header/menu/footer del sitio, noindex y anti-iframe.
 */
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/portal_client.php';

// ── Endurecimiento: noindex + anti-iframe ─────────────────────────────────
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: frame-ancestors 'none'");
header('Referrer-Policy: no-referrer');

$token = trim((string) ($_GET['t'] ?? ($_GET['token'] ?? '')));
$selId = (int) ($_GET['id'] ?? 0);

$assetVersion = (string) @filemtime(__DIR__ . '/assets/css/app.css');

// ── Datos desde la API interna (token como query, sin JWT) ────────────────
$idx     = $token !== '' ? portal_api_call('GET', '/portal-doctor/public/service-lists', ['token' => $token]) : ['ok' => false, 'status' => 403];
$idxOk   = !empty($idx['ok']);
$lists   = $idxOk ? ($idx['data']['lists'] ?? []) : [];
$today   = $idx['data']['today'] ?? date('Y-m-d');
$denied  = !$idxOk && (int) ($idx['status'] ?? 0) === 403;

$listIds = array_map(static fn ($l) => (int) $l['id'], $lists);
if ($selId <= 0 || !in_array($selId, $listIds, true)) {
    $selId = $listIds[0] ?? 0;
}

$detail = null;
if ($idxOk && $selId > 0) {
    $det = portal_api_call('GET', '/portal-doctor/public/service-lists/' . $selId, ['token' => $token]);
    if (!empty($det['ok'])) $detail = $det['data'] ?? null;
}

$selMeta = null;
foreach ($lists as $l) { if ((int) $l['id'] === $selId) { $selMeta = $l; break; } }

$detDays = $detail['days'] ?? [];
$detRows = $detail['rows'] ?? [];
$detAssigned = 0;
foreach ($detRows as $r) { foreach (($r['cells'] ?? []) as $c) { if ($c) $detAssigned++; } }

// ── Helpers de formato (español) ──────────────────────────────────────────
$mesCorto = [1=>'ene',2=>'feb',3=>'mar',4=>'abr',5=>'may',6=>'jun',7=>'jul',8=>'ago',9=>'sep',10=>'oct',11=>'nov',12=>'dic'];
$svcRange = function (string $start, string $end) use ($mesCorto): string {
    $a = strtotime($start); $b = strtotime($end);
    if (!$a || !$b) return '';
    $ya = date('Y', $a); $yb = date('Y', $b);
    $left  = (int) date('j', $a) . ' ' . ($mesCorto[(int) date('n', $a)] ?? '');
    $right = (int) date('j', $b) . ' ' . ($mesCorto[(int) date('n', $b)] ?? '') . ' ' . $yb;
    if ($ya !== $yb) $left .= ' ' . $ya;
    return $left . ' – ' . $right;
};
$linkTo = static fn (int $id): string => base_url('guardias') . '?t=' . urlencode($token) . '&id=' . $id;
?>
<!DOCTYPE html>
<html lang="es-DO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Listas de Servicio | Hospital General Las Colinas</title>
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="theme-color" content="#262161">
    <link rel="icon" type="image/png" href="<?= e(base_url($assets['favicon'])) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800;900&display=swap" rel="stylesheet">
    <style>
    *{box-sizing:border-box} html,body{margin:0;padding:0}
    body{ background:#eef0f7; font-family:'Inter',system-ui,sans-serif; color:#0f1326; -webkit-text-size-adjust:100%; }
    .svc{ --nv:#262161; --nv2:#2a2566; --nv-deep:#1d1a4d; --gr:#5da334; --gr-d:#4a8a29;
          --ink:#0f1326; --mut:#6b7280; --line:#e6e8f0; --soft:#f5f6fb;
          --sh:0 1px 2px rgba(20,22,48,.05), 0 14px 34px rgba(20,22,48,.07);
          font-family:'Plus Jakarta Sans','Inter',system-ui,sans-serif; max-width:1280px; margin:0 auto; padding:0 14px 40px; }

    /* Topbar mínimo aislado */
    .svc-top{ display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 2px; }
    .svc-top .brand{ display:flex; align-items:center; gap:10px; }
    .svc-top img{ height:46px; width:auto; max-width:230px; object-fit:contain; }
    .svc-top .tag{ font-size:.72rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase;
        color:var(--nv); background:#fff; border:1px solid var(--line); border-radius:999px; padding:6px 12px; }

    /* Hero */
    .svc-hero{ position:relative; overflow:hidden; border-radius:18px; padding:22px 24px; color:#fff;
        background:linear-gradient(125deg,var(--nv) 0%, var(--nv2) 55%, #322c7a 100%); box-shadow:var(--sh); }
    .svc-hero h1{ margin:0; font-size:1.5rem; font-weight:900; line-height:1.15; letter-spacing:-.01em; }
    .svc-hero .sub{ margin:6px 0 0; color:#cdcdf0; font-size:.92rem; font-weight:600; }
    .svc-hero .meta{ display:flex; flex-wrap:wrap; gap:8px; margin-top:14px; }
    .svc-chip{ display:inline-flex; align-items:center; gap:6px; background:rgba(255,255,255,.12);
        border:1px solid rgba(255,255,255,.2); border-radius:999px; padding:6px 12px; font-size:.8rem; font-weight:700; }
    .svc-chip.gr{ background:rgba(93,163,52,.9); border-color:transparent; }

    /* Selector de periodos */
    .svc-periods{ margin:18px 0 6px; }
    .svc-periods .lbl{ font-size:.74rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:var(--mut); margin:0 2px 8px; }
    .svc-periods-track{ display:flex; gap:10px; overflow-x:auto; padding:2px 2px 10px; scrollbar-width:thin; }
    .svc-period{ flex:0 0 auto; text-decoration:none; background:#fff; border:1px solid var(--line); border-radius:12px;
        padding:10px 14px; min-width:170px; box-shadow:var(--sh); transition:transform .15s, border-color .15s; }
    .svc-period:hover{ transform:translateY(-2px); }
    .svc-period.on{ border-color:var(--gr); box-shadow:0 0 0 2px rgba(93,163,52,.25), var(--sh); }
    .svc-period .pt{ display:block; font-size:.82rem; font-weight:800; color:var(--nv); line-height:1.2; }
    .svc-period .pr{ display:block; font-size:.74rem; color:var(--mut); margin-top:3px; font-weight:600; }

    /* Toolbar (buscar + compartir) */
    .svc-toolbar{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin:14px 0 10px; }
    .svc-search{ position:relative; flex:1 1 240px; }
    .svc-search input{ width:100%; padding:11px 14px; border:1px solid var(--line); border-radius:12px; font-size:.92rem;
        font-family:inherit; background:#fff; box-shadow:var(--sh); }
    .svc-btn{ display:inline-flex; align-items:center; gap:7px; background:var(--nv); color:#fff; border:0; cursor:pointer;
        border-radius:12px; padding:11px 16px; font-size:.86rem; font-weight:800; font-family:inherit; text-decoration:none; }
    .svc-btn.gr{ background:var(--gr-d); }
    .svc-btn:active{ transform:translateY(1px); }

    /* Grid */
    .svc-card{ background:#fff; border:1px solid var(--line); border-radius:16px; box-shadow:var(--sh); overflow:hidden; }
    .svc-gridhint{ display:none; align-items:center; gap:6px; justify-content:center; padding:9px; font-size:.78rem;
        color:var(--mut); background:var(--soft); border-bottom:1px solid var(--line); font-weight:700; }
    .svc-gridwrap{ overflow:auto; -webkit-overflow-scrolling:touch; max-height:76vh; }
    table.svc-grid{ width:100%; min-width:var(--gmin,900px); border-collapse:separate; border-spacing:0; table-layout:fixed; }
    table.svc-grid th, table.svc-grid td{ border-right:1px solid var(--line); border-bottom:1px solid var(--line); }
    table.svc-grid thead th{ position:sticky; top:0; z-index:20; background:var(--nv); color:#fff; padding:10px 6px; font-size:.74rem; }
    table.svc-grid thead th.col-spec{ left:0; z-index:30; background:var(--nv-deep); text-align:left; padding-left:16px; min-width:150px; width:150px; }
    table.svc-grid thead th.wknd{ background:#322c7a; }
    table.svc-grid thead th .wd{ display:block; font-size:.78rem; font-weight:800; text-transform:uppercase; letter-spacing:.04em; }
    table.svc-grid thead th .dt{ display:block; font-size:.68rem; font-weight:600; color:#bcbde4; margin-top:2px; font-variant-numeric:tabular-nums; }
    table.svc-grid thead th.wknd .dt{ color:#cdebb5; }
    table.svc-grid thead th.is-today{ box-shadow:inset 0 -3px 0 var(--gr); }
    table.svc-grid td{ padding:9px 8px; vertical-align:middle; text-align:center; background:#fff; line-height:1.25; overflow-wrap:break-word; }
    table.svc-grid td.cell-spec{ position:sticky; left:0; z-index:10; text-align:left; padding:10px 14px; background:var(--soft);
        font-weight:800; color:var(--nv); font-size:.8rem; min-width:150px; width:150px; }
    table.svc-grid tbody tr:nth-child(even) td:not(.cell-spec):not(.wknd){ background:#f8f9fd; }
    table.svc-grid td.wknd{ background:#fffaf0; }
    table.svc-grid tbody tr:nth-child(even) td.wknd{ background:#fef6e6; }
    table.svc-grid tbody tr:hover td:not(.cell-spec){ background:#eef7e7; }
    table.svc-grid .dn{ font-weight:700; color:var(--ink); font-size:.82rem; }
    table.svc-grid .dp{ font-size:.72rem; color:var(--mut); margin-top:2px; font-variant-numeric:tabular-nums; }
    table.svc-grid .dp a{ color:var(--mut); text-decoration:none; }
    table.svc-grid .dp a:hover{ color:var(--gr-d); text-decoration:underline; }
    table.svc-grid .empty{ color:#cbcfdd; font-weight:700; letter-spacing:.2em; }
    tr.svc-row-hidden{ display:none; }

    .svc-foot{ display:flex; flex-wrap:wrap; gap:14px; align-items:center; margin-top:14px; font-size:.78rem; color:var(--mut); }
    .svc-foot .sw{ display:inline-block; width:12px; height:12px; border-radius:3px; vertical-align:-1px; margin-right:5px; }
    .svc-foot .sw.wk{ background:#fef6e6; border:1px solid #f3d9a6; }
    .svc-foot .src{ margin-left:auto; display:inline-flex; align-items:center; gap:6px; font-weight:700; color:var(--nv); }

    .svc-msg{ background:#fff; border:1px solid var(--line); border-radius:16px; box-shadow:var(--sh); padding:40px 26px; text-align:center; margin-top:20px; }
    .svc-msg h2{ margin:0 0 8px; color:var(--nv); font-size:1.25rem; }
    .svc-msg p{ margin:0; color:var(--mut); font-size:.95rem; line-height:1.6; }

    @media (max-width:760px){
        .svc{ padding:0 0 36px; }
        .svc-top, .svc-periods, .svc-toolbar{ padding-left:12px; padding-right:12px; }
        .svc-hero{ border-radius:0; }
        .svc-card{ border-radius:0; border-left:0; border-right:0; }
        .svc-gridhint{ display:flex; }
        .svc-hero h1{ font-size:1.25rem; }
        table.svc-grid thead th.col-spec, table.svc-grid td.cell-spec{ min-width:120px; width:120px; }
        .svc-foot{ padding:0 12px; }
    }
    </style>
</head>
<body>
<div class="svc">
    <div class="svc-top">
        <a class="brand" href="#" onclick="return false">
            <img src="<?= e(base_url($assets['logo'])) ?>" alt="Hospital General Las Colinas">
        </a>
        <span class="tag">Listas de Servicio</span>
    </div>

    <?php if ($denied || $token === ''): ?>
        <div class="svc-msg">
            <h2>Enlace no válido</h2>
            <p>Este enlace de listas de servicio no es válido o fue actualizado.<br>Solicita el enlace vigente al área de Servicio del hospital.</p>
        </div>
    <?php elseif (!$idxOk): ?>
        <div class="svc-msg">
            <h2>No disponible en este momento</h2>
            <p>No pudimos cargar las listas de servicio. Intenta de nuevo en unos minutos.</p>
        </div>
    <?php elseif (!$lists): ?>
        <div class="svc-msg">
            <h2>Sin listas publicadas</h2>
            <p>Aún no hay listas de servicio publicadas. Vuelve más tarde.</p>
        </div>
    <?php else: ?>

        <div class="svc-hero">
            <h1><?= e($selMeta['title'] ?? 'Lista de servicio') ?></h1>
            <?php if ($selMeta): ?>
                <p class="sub"><?= e($svcRange($selMeta['start_date'], $selMeta['end_date'])) ?></p>
            <?php endif; ?>
            <div class="meta">
                <?php if ($selMeta): ?>
                    <span class="svc-chip"><?= (int) $selMeta['specialty_count'] ?> especialidades</span>
                    <span class="svc-chip"><?= (int) $selMeta['day_count'] ?> días</span>
                    <span class="svc-chip gr"><?= (int) $selMeta['coverage_pct'] ?>% cobertura</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (count($lists) > 1): ?>
            <div class="svc-periods">
                <p class="lbl">Periodos publicados</p>
                <div class="svc-periods-track">
                    <?php foreach ($lists as $l): ?>
                        <a class="svc-period <?= (int) $l['id'] === $selId ? 'on' : '' ?>" href="<?= e($linkTo((int) $l['id'])) ?>">
                            <span class="pt"><?= e($l['title']) ?></span>
                            <span class="pr"><?= e($svcRange($l['start_date'], $l['end_date'])) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($detail && $detRows): ?>
            <div class="svc-toolbar">
                <div class="svc-search">
                    <input type="search" id="svcSearch" placeholder="Buscar especialidad o médico…" autocomplete="off" aria-label="Buscar">
                </div>
                <button type="button" class="svc-btn gr" id="svcShare"><span id="svcShareTxt">Compartir enlace</span></button>
            </div>

            <div class="svc-card">
                <div class="svc-gridhint">↔ Desliza para ver toda la semana</div>
                <div class="svc-gridwrap">
                    <table class="svc-grid" style="--gmin: <?= 150 + count($detDays) * 124 ?>px">
                        <thead>
                            <tr>
                                <th class="col-spec">Especialidad</th>
                                <?php foreach ($detDays as $day):
                                    $wk = !empty($day['weekend']); $isToday = ($day['date'] === $today); $ts = strtotime($day['date']); ?>
                                    <th class="<?= $wk ? 'wknd' : '' ?> <?= $isToday ? 'is-today' : '' ?>">
                                        <span class="wd"><?= e(mb_substr($day['weekday'], 0, 3)) ?></span>
                                        <span class="dt"><?= e(date('d/m', $ts)) ?></span>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detRows as $row): ?>
                                <tr data-spec="<?= e(mb_strtolower($row['specialty_name'])) ?>">
                                    <td class="cell-spec"><?= e($row['specialty_name']) ?></td>
                                    <?php foreach ($detDays as $day):
                                        $c  = $row['cells'][$day['date']] ?? null;
                                        $wk = !empty($day['weekend']);
                                        $docName = $c ? (string) $c['doctor_name'] : '';
                                        $phone   = $c ? trim((string) ($c['doctor_phone'] ?? '')) : '';
                                        $telHref = preg_replace('/[^0-9+]/', '', $phone);
                                    ?>
                                        <td class="cell-doc <?= $wk ? 'wknd' : '' ?>" data-doc="<?= e(mb_strtolower($docName)) ?>">
                                            <?php if ($c): ?>
                                                <div class="dn"><?= e($docName) ?></div>
                                                <?php if ($phone !== ''): ?>
                                                    <div class="dp"><a href="tel:<?= e($telHref) ?>"><?= e($phone) ?></a></div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="empty">·</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="svc-foot">
                <span><span class="sw wk"></span> Fin de semana</span>
                <span><?= count($detRows) ?> especialidades · <?= count($detDays) ?> días · <?= (int) $detAssigned ?> asignaciones</span>
                <span class="src">Fuente: área de Servicio · Hospital General Las Colinas</span>
            </div>
        <?php else: ?>
            <div class="svc-msg"><h2>Lista vacía</h2><p>Esta lista aún no tiene asignaciones publicadas.</p></div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script>
// Anti-iframe (defensa en profundidad además de la cabecera CSP/XFO).
if (window.top !== window.self) { try { window.top.location = window.self.location.href; } catch (e) { document.documentElement.style.display = 'none'; } }

(function () {
    // Filtro por especialidad o médico
    var s = document.getElementById('svcSearch');
    if (s) {
        var rows = Array.prototype.slice.call(document.querySelectorAll('table.svc-grid tbody tr'));
        var norm = function (x) { return (x || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
        s.addEventListener('input', function () {
            var q = norm(s.value.trim());
            rows.forEach(function (tr) {
                var spec = tr.getAttribute('data-spec') || '';
                var docs = Array.prototype.map.call(tr.querySelectorAll('td.cell-doc'), function (td) { return td.getAttribute('data-doc') || ''; }).join(' ');
                tr.classList.toggle('svc-row-hidden', q !== '' && norm(spec + ' ' + docs).indexOf(q) === -1);
            });
        });
    }
    // Compartir / copiar enlace
    var btn = document.getElementById('svcShare');
    if (btn) {
        btn.addEventListener('click', async function () {
            var url = window.location.href;
            try {
                if (navigator.share) { await navigator.share({ title: 'Listas de Servicio — HGLC', url: url }); return; }
                await navigator.clipboard.writeText(url);
                var t = document.getElementById('svcShareTxt'); var old = t.textContent;
                t.textContent = '¡Enlace copiado!'; setTimeout(function () { t.textContent = old; }, 1800);
            } catch (e) { /* usuario canceló */ }
        });
    }
})();
</script>
    <script defer src="/assets/js/track.js"></script>
</body>
</html>
