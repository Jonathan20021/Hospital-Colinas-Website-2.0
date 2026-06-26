<?php
/**
 * Listas de servicio (guardias de especialistas) — Portal del Médico.
 *
 * Muestra, de forma profesional, el roster de guardias que el área de Servicio
 * mantiene en JENOFONTE (/service_lists.php). Solo lectura y solo listas
 * publicadas (is_public=1). Sin PHI: especialidad · fecha · médico · teléfono.
 *
 * La llamada al API interno se hace SERVER-SIDE (portal_api_call + doctor_token):
 * el JWT nunca llega al navegador. El doctor_id del médico autenticado marca
 * "mis guardias" (is_me) en las celdas.
 */
require_once __DIR__ . '/_layout.php';
doctor_require_login();

// ── Datos: índice de listas + mis próximas guardias ───────────────────────
$idx       = portal_api_call('GET', '/portal-doctor/me/service-lists', [], doctor_token());
$idxOk     = !empty($idx['ok']);
$lists     = $idxOk ? ($idx['data']['lists'] ?? []) : [];
$upcoming  = $idxOk ? ($idx['data']['my_upcoming'] ?? []) : [];
$today     = $idx['data']['today'] ?? date('Y-m-d');
$idxErr    = $idxOk ? '' : ($idx['message'] ?: 'No se pudieron cargar las listas de servicio.');

// ── Lista seleccionada (por defecto, la más reciente) ─────────────────────
$listIds = array_map(static fn ($l) => (int) $l['id'], $lists);
$selId   = (int) ($_GET['id'] ?? 0);
if ($selId <= 0 || !in_array($selId, $listIds, true)) {
    $selId = $listIds[0] ?? 0;
}

$detail = null;
$detErr = '';
if ($selId > 0) {
    $det = portal_api_call('GET', '/portal-doctor/me/service-lists/' . $selId, [], doctor_token());
    if (!empty($det['ok'])) {
        $detail = $det['data'] ?? null;
    } else {
        $detErr = $det['message'] ?: 'No se pudo cargar el detalle de la lista.';
    }
}

// Meta de la lista seleccionada (para coberturas/badges del encabezado)
$selMeta = null;
foreach ($lists as $l) {
    if ((int) $l['id'] === $selId) { $selMeta = $l; break; }
}

// ── Helpers de formato (español) ──────────────────────────────────────────
$mesCorto = [1=>'ene',2=>'feb',3=>'mar',4=>'abr',5=>'may',6=>'jun',7=>'jul',8=>'ago',9=>'sep',10=>'oct',11=>'nov',12=>'dic'];

/** Rango legible: "29 jun – 5 jul 2026" (o con año doble si cruza años). */
$svcRange = function (string $start, string $end) use ($mesCorto): string {
    $a = strtotime($start); $b = strtotime($end);
    if (!$a || !$b) return '';
    $ya = date('Y', $a); $yb = date('Y', $b);
    $left  = (int) date('j', $a) . ' ' . ($mesCorto[(int) date('n', $a)] ?? '');
    $right = (int) date('j', $b) . ' ' . ($mesCorto[(int) date('n', $b)] ?? '') . ' ' . $yb;
    if ($ya !== $yb) $left .= ' ' . $ya;
    return $left . ' – ' . $right;
};

$linkTo = static fn (int $id): string => base_url('portal-medico/listas-servicio.php') . '?id=' . $id;

// Totales del detalle (para la barra de cobertura del documento)
$detDays = $detail['days'] ?? [];
$detRows = $detail['rows'] ?? [];
$detAssigned = 0;
foreach ($detRows as $r) {
    foreach (($r['cells'] ?? []) as $c) { if ($c) $detAssigned++; }
}
$detSlots    = count($detRows) * count($detDays);
$detCoverage = $detSlots > 0 ? (int) round($detAssigned / $detSlots * 100) : 0;
$detMyShifts = (int) ($detail['my_shifts'] ?? 0);

doctor_layout_begin('Listas de servicio', 'listas');
?>
<style>
/* ============================================================
   LISTAS DE SERVICIO · Portal del Médico (marca HGLC navy/verde)
   ============================================================ */
.svc{ --nv:#262161; --nv2:#2a2566; --nv-deep:#1d1a4d; --gr:#5da334; --gr-d:#4a8a29;
      --ink:#0f1326; --mut:#6b7280; --line:#e6e8f0; --soft:#f5f6fb;
      --card-sh:0 1px 2px rgba(20,22,48,.05), 0 14px 34px rgba(20,22,48,.07);
      --ease:cubic-bezier(.32,.72,0,1);
      font-family:'Plus Jakarta Sans','Inter',system-ui,sans-serif; color:var(--ink);
      max-width:1280px; margin:0 auto; }

/* ---- Hero ---- */
.svc-hero{ position:relative; overflow:hidden; border-radius:18px; padding:22px 24px;
    background:linear-gradient(125deg,var(--nv) 0%, var(--nv2) 55%, #322c7a 100%);
    color:#fff; box-shadow:var(--card-sh); margin-bottom:16px; }
.svc-hero::after{ content:''; position:absolute; right:-60px; top:-70px; width:280px; height:280px;
    background:radial-gradient(circle, rgba(93,163,52,.30) 0%, transparent 70%); pointer-events:none; }
.svc-hero::before{ content:''; position:absolute; left:0; top:0; bottom:0; width:5px;
    background:linear-gradient(180deg,var(--gr),transparent); }
.svc-hero-row{ display:flex; align-items:center; gap:16px; flex-wrap:wrap; position:relative; z-index:1; }
.svc-hero-ic{ width:52px; height:52px; border-radius:14px; flex:none; display:grid; place-items:center;
    background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.16); }
.svc-hero-ic i{ width:26px; height:26px; color:#fff; }
.svc-hero h1{ font-family:'Outfit',sans-serif; font-size:1.5rem; font-weight:800; line-height:1.1; letter-spacing:-.02em; margin:0; }
.svc-hero p{ margin:4px 0 0; font-size:.86rem; color:#d4d5ec; max-width:54ch; }
.svc-hero-stats{ margin-left:auto; display:flex; gap:10px; flex-wrap:wrap; }
.svc-hstat{ background:rgba(255,255,255,.10); border:1px solid rgba(255,255,255,.14); border-radius:12px;
    padding:8px 14px; text-align:center; min-width:74px; }
.svc-hstat b{ display:block; font-family:'Outfit',sans-serif; font-size:1.25rem; font-weight:800; line-height:1; font-variant-numeric:tabular-nums; }
.svc-hstat span{ display:block; font-size:.62rem; text-transform:uppercase; letter-spacing:.12em; color:#b9bbe0; margin-top:3px; font-weight:700; }

/* ---- Mis próximas guardias ---- */
.svc-mine{ background:#fff; border:1px solid var(--line); border-radius:16px; box-shadow:var(--card-sh);
    padding:16px 18px; margin-bottom:16px; }
.svc-sec-h{ display:flex; align-items:center; gap:9px; margin:0 0 12px; }
.svc-sec-h i{ width:18px; height:18px; color:var(--gr-d); }
.svc-sec-h b{ font-family:'Outfit',sans-serif; font-size:1rem; font-weight:800; letter-spacing:-.01em; }
.svc-sec-h .pill{ margin-left:auto; font-size:.7rem; font-weight:800; color:var(--gr-d);
    background:#eef7e7; border:1px solid #d3e9c2; padding:3px 10px; border-radius:999px; }
.svc-mine-track{ display:flex; gap:10px; overflow-x:auto; padding-bottom:4px; scrollbar-width:thin; }
.svc-mine-card{ flex:none; width:178px; text-decoration:none; color:inherit;
    border:1px solid var(--line); border-radius:14px; padding:12px 13px; background:linear-gradient(180deg,#fff,#fbfcfe);
    display:flex; gap:11px; align-items:flex-start; transition:transform .18s var(--ease), border-color .18s, box-shadow .18s; }
.svc-mine-card:hover{ transform:translateY(-2px); border-color:#c9cbe0; box-shadow:0 10px 22px rgba(38,33,97,.10); }
.svc-mine-date{ flex:none; width:46px; text-align:center; border-radius:10px; overflow:hidden;
    border:1px solid #d3e9c2; box-shadow:0 2px 6px rgba(74,138,41,.14); }
.svc-mine-date .m{ background:var(--gr); color:#fff; font-size:.6rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em; padding:2px 0; }
.svc-mine-date .d{ background:#fff; color:var(--nv); font-family:'Outfit',sans-serif; font-size:1.3rem; font-weight:800; line-height:1.5; }
.svc-mine-meta{ min-width:0; }
.svc-mine-wd{ font-size:.66rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em; color:var(--gr-d); }
.svc-mine-sp{ font-size:.82rem; font-weight:700; color:var(--ink); line-height:1.2; margin:2px 0 3px;
    display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.svc-mine-ls{ font-size:.66rem; color:var(--mut); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

/* ---- Selector de periodos ---- */
.svc-periods{ margin-bottom:16px; }
.svc-periods-track{ display:flex; gap:10px; overflow-x:auto; padding:2px 2px 8px; scrollbar-width:thin; }
.svc-chip{ flex:none; min-width:150px; text-decoration:none; color:inherit; position:relative;
    border:1.5px solid var(--line); border-radius:14px; padding:11px 14px; background:#fff;
    transition:transform .16s var(--ease), border-color .16s, box-shadow .16s; }
.svc-chip:hover{ transform:translateY(-2px); border-color:#c4c6dd; box-shadow:0 8px 18px rgba(38,33,97,.09); }
.svc-chip .rng{ font-family:'Outfit',sans-serif; font-size:.92rem; font-weight:800; letter-spacing:-.01em; }
.svc-chip .sub{ font-size:.68rem; color:var(--mut); margin-top:3px; font-weight:600; }
.svc-chip .me{ display:inline-flex; align-items:center; gap:4px; margin-top:7px; font-size:.66rem; font-weight:800;
    color:var(--gr-d); background:#eef7e7; border:1px solid #d3e9c2; padding:2px 8px; border-radius:999px; }
.svc-chip .me i{ width:11px; height:11px; }
.svc-chip.on{ background:linear-gradient(160deg,var(--nv),var(--nv2)); border-color:var(--nv); color:#fff; box-shadow:0 10px 24px rgba(38,33,97,.26); }
.svc-chip.on .sub{ color:#c2c3e6; }
.svc-chip.on .me{ background:rgba(93,163,52,.22); border-color:rgba(93,163,52,.5); color:#d8f0c5; }
.svc-chip.on::after{ content:''; position:absolute; left:14px; right:14px; bottom:-1px; height:3px; border-radius:3px; background:var(--gr); }

/* ---- Documento (grid) ---- */
.svc-doc{ background:#fff; border:1px solid var(--line); border-radius:18px; box-shadow:var(--card-sh); overflow:hidden; }
.svc-toolbar{ display:flex; align-items:center; gap:14px; flex-wrap:wrap; padding:16px 20px; border-bottom:1px solid var(--line);
    background:linear-gradient(180deg,#fff,#fafbfe); }
.svc-toolbar-l{ min-width:0; }
.svc-toolbar-l h2{ font-family:'Outfit',sans-serif; font-size:1.12rem; font-weight:800; letter-spacing:-.01em; margin:0; line-height:1.15; }
.svc-toolbar-l .per{ font-size:.78rem; color:var(--mut); font-weight:600; margin-top:3px; display:flex; align-items:center; gap:7px; flex-wrap:wrap; }
.svc-cov{ display:inline-flex; align-items:center; gap:7px; }
.svc-cov .bar{ width:80px; height:6px; border-radius:4px; background:var(--line); overflow:hidden; }
.svc-cov .bar i{ display:block; height:100%; border-radius:4px; background:linear-gradient(90deg,var(--gr),#7bc14d); }
.svc-cov b{ font-variant-numeric:tabular-nums; color:var(--ink); }
.svc-toolbar-r{ margin-left:auto; display:flex; align-items:center; gap:9px; flex-wrap:wrap; }
.svc-search{ display:flex; align-items:center; gap:8px; background:var(--soft); border:1.5px solid var(--line);
    border-radius:10px; padding:8px 12px; min-width:210px; transition:border-color .15s, box-shadow .15s, background .15s; }
.svc-search:focus-within{ background:#fff; border-color:var(--nv2); box-shadow:0 0 0 3px rgba(42,37,102,.10); }
.svc-search i{ width:16px; height:16px; color:#9aa0b4; flex:none; }
.svc-search input{ border:0; outline:0; background:none; font:inherit; font-size:.84rem; color:var(--ink); width:100%; min-width:0; }
.svc-toggle{ display:inline-flex; align-items:center; gap:8px; cursor:pointer; user-select:none;
    border:1.5px solid var(--line); border-radius:10px; padding:7px 12px; font-size:.8rem; font-weight:700; color:#475066; background:#fff; }
.svc-toggle:hover{ border-color:#c4c6dd; }
.svc-toggle input{ position:absolute; opacity:0; pointer-events:none; }
.svc-toggle .sw{ width:34px; height:19px; border-radius:999px; background:#cfd2e2; position:relative; transition:background .18s; flex:none; }
.svc-toggle .sw::after{ content:''; position:absolute; top:2px; left:2px; width:15px; height:15px; border-radius:50%; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.25); transition:transform .18s var(--ease); }
.svc-toggle input:checked + .sw{ background:var(--gr); }
.svc-toggle input:checked + .sw::after{ transform:translateX(15px); }
.svc-toggle.on{ border-color:var(--gr); color:var(--gr-d); background:#f3faee; }
.svc-btn{ display:inline-flex; align-items:center; gap:7px; border:1.5px solid var(--line); border-radius:10px;
    padding:8px 14px; font:inherit; font-size:.82rem; font-weight:700; color:#fff; background:var(--nv); cursor:pointer;
    transition:background .15s, transform .12s; text-decoration:none; }
.svc-btn:hover{ background:var(--nv2); transform:translateY(-1px); }
.svc-btn i{ width:15px; height:15px; }

.svc-gridwrap{ overflow:auto; -webkit-overflow-scrolling:touch; max-height:74vh; }
table.svc-grid{ width:100%; border-collapse:separate; border-spacing:0; table-layout:fixed; }
table.svc-grid th, table.svc-grid td{ border-right:1px solid var(--line); border-bottom:1px solid var(--line); }
table.svc-grid thead th{ position:sticky; top:0; z-index:20; background:var(--nv); color:#fff;
    font-size:.74rem; font-weight:700; letter-spacing:.03em; padding:9px 6px; text-align:center; vertical-align:middle; }
table.svc-grid thead th.col-spec{ left:0; z-index:30; background:var(--nv-deep); text-align:left; padding-left:16px;
    text-transform:uppercase; font-size:.72rem; letter-spacing:.1em; min-width:178px; width:178px; }
table.svc-grid thead th.wknd{ background:#322c7a; }
table.svc-grid thead th .wd{ display:block; font-size:.78rem; font-weight:800; text-transform:uppercase; letter-spacing:.04em; }
table.svc-grid thead th .dt{ display:block; font-size:.68rem; font-weight:600; color:#bcbde4; margin-top:2px; font-variant-numeric:tabular-nums; }
table.svc-grid thead th.wknd .dt{ color:#cdebb5; }
table.svc-grid thead th.is-today{ box-shadow:inset 0 -3px 0 var(--gr); }

table.svc-grid td{ padding:9px 8px; vertical-align:middle; text-align:center; background:#fff; line-height:1.25; }
table.svc-grid td.cell-spec{ position:sticky; left:0; z-index:10; text-align:left; padding:10px 14px;
    background:linear-gradient(180deg,#211d50,var(--nv-deep)); color:#fff; font-weight:800; font-size:.78rem;
    text-transform:uppercase; letter-spacing:.02em; border-right:3px solid var(--gr); line-height:1.2; }
table.svc-grid tbody tr:nth-child(even) td:not(.cell-spec):not(.wknd){ background:#f8f9fd; }
table.svc-grid td.wknd{ background:#fffaf0; }
table.svc-grid tbody tr:nth-child(even) td.wknd{ background:#fef6e6; }
table.svc-grid tbody tr:hover td:not(.cell-spec){ background:#eef7e7; }
table.svc-grid .dn{ font-weight:700; color:var(--ink); font-size:.82rem; }
table.svc-grid .dp{ font-size:.72rem; color:var(--mut); margin-top:2px; font-variant-numeric:tabular-nums; letter-spacing:.01em; }
table.svc-grid .dp a{ color:var(--mut); text-decoration:none; }
table.svc-grid .dp a:hover{ color:var(--gr-d); text-decoration:underline; }
table.svc-grid .empty{ color:#cbcfdd; font-weight:700; letter-spacing:.2em; }
/* mis guardias */
table.svc-grid td.is-me{ background:#eef7e7 !important; box-shadow:inset 0 0 0 2px var(--gr); position:relative; }
table.svc-grid td.is-me .dn{ color:var(--gr-d); }
.svc-metag{ display:inline-block; margin-top:4px; font-size:.58rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em;
    color:#fff; background:var(--gr); border-radius:999px; padding:1px 7px; }
/* búsqueda / filtros */
tr.svc-row-hidden{ display:none; }
td.svc-dim{ opacity:.22; }
td.svc-hl{ box-shadow:inset 0 0 0 2px #f59e0b; background:#fffbeb !important; }
table.svc-grid.only-me tbody tr:not([data-me="1"]){ display:none; }
table.svc-grid.only-me td:not(.is-me):not(.cell-spec){ opacity:.3; }

/* legend / footer */
.svc-foot{ display:flex; align-items:center; gap:18px; flex-wrap:wrap; padding:12px 20px; border-top:1px solid var(--line);
    background:#fafbfe; font-size:.74rem; color:var(--mut); }
.svc-leg{ display:inline-flex; align-items:center; gap:7px; }
.svc-leg .sw{ width:14px; height:14px; border-radius:4px; border:1px solid var(--line); }
.svc-leg .sw.me{ background:#eef7e7; box-shadow:inset 0 0 0 2px var(--gr); border-color:transparent; }
.svc-leg .sw.wk{ background:#fef6e6; }
.svc-foot .src{ margin-left:auto; display:inline-flex; align-items:center; gap:6px; }
.svc-foot .src i{ width:13px; height:13px; }

/* empty / error states */
.svc-note{ background:#fff; border:1px solid var(--line); border-radius:16px; box-shadow:var(--card-sh);
    padding:34px 24px; text-align:center; color:var(--mut); }
.svc-note i{ width:34px; height:34px; color:#c4c6dd; }
.svc-note h3{ font-family:'Outfit',sans-serif; color:var(--ink); font-size:1.05rem; font-weight:800; margin:10px 0 4px; }
.svc-note.err{ border-color:#f3c7c7; background:#fdf3f3; }
.svc-note.err i{ color:#e06666; }

/* print-only header */
.svc-print-head{ display:none; }

@media (max-width:720px){
    .svc-hero-stats{ margin-left:0; width:100%; }
    .svc-toolbar-r{ width:100%; }
    .svc-search{ flex:1; }
}

/* ===== Impresión: ocultar shell del portal y dejar solo el documento ===== */
@media print{
    .dm-sb, .dm-top, .dm-backdrop, .skip-link,
    .svc-hero, .svc-mine, .svc-periods, .svc-toolbar-r, .svc-foot .src{ display:none !important; }
    .dm-app{ display:block !important; }
    .dm-maincol{ margin:0 !important; }
    .doctor-main{ padding:0 !important; max-width:none !important; }
    .svc{ max-width:none; }
    .svc-doc{ border:0; box-shadow:none; border-radius:0; }
    .svc-gridwrap{ overflow:visible; max-height:none; }
    .svc-print-head{ display:block; margin-bottom:10px; }
    .svc-print-head .t{ font-size:14pt; font-weight:800; color:#262161; }
    .svc-print-head .s{ font-size:9pt; color:#555; margin-top:2px; }
    table.svc-grid thead th{ position:static; }
    table.svc-grid td.cell-spec, table.svc-grid thead th.col-spec{ position:static; }
    table.svc-grid.only-me tbody tr:not([data-me="1"]){ display:table-row; }
    table.svc-grid.only-me td:not(.is-me):not(.cell-spec){ opacity:1; }
    *{ -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; }
}
</style>

<div class="svc">

    <!-- Hero -->
    <div class="svc-hero">
        <div class="svc-hero-row">
            <div class="svc-hero-ic"><i data-lucide="calendar-range"></i></div>
            <div>
                <h1>Listas de servicio</h1>
                <p>Programación de guardias de especialistas publicada por el área de Servicio del hospital.</p>
            </div>
            <div class="svc-hero-stats">
                <div class="svc-hstat"><b><?= count($lists) ?></b><span>Listas</span></div>
                <?php if ($detMyShifts > 0): ?>
                    <div class="svc-hstat"><b><?= $detMyShifts ?></b><span>Mis guardias</span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($idxErr): ?>
        <div class="svc-note err">
            <i data-lucide="alert-triangle"></i>
            <h3>No se pudieron cargar las listas</h3>
            <p><?= e($idxErr) ?></p>
        </div>
    <?php elseif (!$lists): ?>
        <div class="svc-note">
            <i data-lucide="calendar-x"></i>
            <h3>Aún no hay listas publicadas</h3>
            <p>Cuando el área de Servicio publique una lista de guardias, aparecerá aquí.</p>
        </div>
    <?php else: ?>

        <!-- Mis próximas guardias -->
        <?php if ($upcoming): ?>
        <div class="svc-mine">
            <div class="svc-sec-h">
                <i data-lucide="user-check"></i>
                <b>Mis próximas guardias</b>
                <span class="pill"><?= count($upcoming) ?> programada<?= count($upcoming) === 1 ? '' : 's' ?></span>
            </div>
            <div class="svc-mine-track">
                <?php foreach ($upcoming as $u):
                    $ts = strtotime($u['date']); ?>
                    <a class="svc-mine-card" href="<?= e($linkTo((int) $u['list_id'])) ?>">
                        <div class="svc-mine-date">
                            <div class="m"><?= e($mesCorto[(int) date('n', $ts)] ?? '') ?></div>
                            <div class="d"><?= (int) date('j', $ts) ?></div>
                        </div>
                        <div class="svc-mine-meta">
                            <div class="svc-mine-wd"><?= e($u['weekday']) ?></div>
                            <div class="svc-mine-sp"><?= e($u['specialty_name']) ?></div>
                            <div class="svc-mine-ls"><?= e($u['list_title']) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Selector de periodos -->
        <div class="svc-periods">
            <div class="svc-sec-h"><i data-lucide="layers"></i><b>Periodos publicados</b></div>
            <div class="svc-periods-track">
                <?php foreach ($lists as $l):
                    $on = (int) $l['id'] === $selId; ?>
                    <a class="svc-chip <?= $on ? 'on' : '' ?>" href="<?= e($linkTo((int) $l['id'])) ?>">
                        <div class="rng"><?= e($svcRange($l['start_date'], $l['end_date'])) ?></div>
                        <div class="sub"><?= (int) $l['specialty_count'] ?> esp. · <?= (int) $l['coverage_pct'] ?>% cubierto</div>
                        <?php if ((int) $l['my_shifts'] > 0): ?>
                            <span class="me"><i data-lucide="check"></i><?= (int) $l['my_shifts'] ?> mía<?= (int) $l['my_shifts'] === 1 ? '' : 's' ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Documento de la lista seleccionada -->
        <?php if ($detErr): ?>
            <div class="svc-note err">
                <i data-lucide="alert-triangle"></i>
                <h3>No se pudo cargar la lista</h3>
                <p><?= e($detErr) ?></p>
            </div>
        <?php elseif ($detail): ?>
            <div class="svc-doc">
                <div class="svc-print-head">
                    <div class="t">Hospital General Las Colinas · <?= e($detail['list']['title']) ?></div>
                    <div class="s"><?= e($svcRange($detail['list']['start_date'], $detail['list']['end_date'])) ?> · Listas de servicio de especialistas</div>
                </div>

                <div class="svc-toolbar">
                    <div class="svc-toolbar-l">
                        <h2><?= e($detail['list']['title']) ?></h2>
                        <div class="per">
                            <i data-lucide="calendar" style="width:14px;height:14px;"></i>
                            <span><?= e($svcRange($detail['list']['start_date'], $detail['list']['end_date'])) ?></span>
                            <span>·</span>
                            <span class="svc-cov">
                                <span class="bar"><i style="width:<?= (int) $detCoverage ?>%"></i></span>
                                <b><?= (int) $detCoverage ?>%</b> cubierto
                            </span>
                        </div>
                    </div>
                    <div class="svc-toolbar-r">
                        <label class="svc-search">
                            <i data-lucide="search"></i>
                            <input type="search" id="svcSearch" placeholder="Buscar especialidad o médico…" autocomplete="off">
                        </label>
                        <?php if ($detMyShifts > 0): ?>
                            <label class="svc-toggle" id="svcMineToggle">
                                <input type="checkbox" id="svcMine">
                                <span class="sw"></span>
                                <span>Solo mis guardias</span>
                            </label>
                        <?php endif; ?>
                        <button type="button" class="svc-btn" id="svcPrint"><i data-lucide="printer"></i>Imprimir</button>
                    </div>
                </div>

                <?php if (!empty($detail['list']['notes'])): ?>
                    <div style="margin:0; padding:10px 20px; background:#fffbeb; border-bottom:1px solid var(--line); font-size:.82rem; color:#7a4b08;">
                        <strong style="text-transform:uppercase; font-size:.68rem; letter-spacing:.1em; color:#92580a; margin-right:6px;">Observaciones</strong>
                        <?= e($detail['list']['notes']) ?>
                    </div>
                <?php endif; ?>

                <div class="svc-gridwrap">
                    <table class="svc-grid" id="svcGrid">
                        <thead>
                            <tr>
                                <th class="col-spec">Especialidad</th>
                                <?php foreach ($detDays as $day):
                                    $wk = !empty($day['weekend']);
                                    $isToday = ($day['date'] === $today);
                                    $ts = strtotime($day['date']); ?>
                                    <th class="<?= $wk ? 'wknd' : '' ?> <?= $isToday ? 'is-today' : '' ?>">
                                        <span class="wd"><?= e(mb_substr($day['weekday'], 0, 3)) ?></span>
                                        <span class="dt"><?= e(date('d/m', $ts)) ?></span>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detRows as $row):
                                $rowHasMe = false;
                                foreach (($row['cells'] ?? []) as $c) { if ($c && !empty($c['is_me'])) { $rowHasMe = true; break; } }
                            ?>
                                <tr data-me="<?= $rowHasMe ? '1' : '0' ?>" data-spec="<?= e(mb_strtolower($row['specialty_name'])) ?>">
                                    <td class="cell-spec"><?= e($row['specialty_name']) ?></td>
                                    <?php foreach ($detDays as $day):
                                        $c  = $row['cells'][$day['date']] ?? null;
                                        $wk = !empty($day['weekend']);
                                        $me = $c && !empty($c['is_me']);
                                        $docName = $c ? (string) $c['doctor_name'] : '';
                                        $phone   = $c ? trim((string) ($c['doctor_phone'] ?? '')) : '';
                                        $telHref = preg_replace('/[^0-9+]/', '', $phone);
                                    ?>
                                        <td class="cell-doc <?= $wk ? 'wknd' : '' ?> <?= $me ? 'is-me' : '' ?>"
                                            data-doc="<?= e(mb_strtolower($docName)) ?>">
                                            <?php if ($c): ?>
                                                <div class="dn">Dr. <?= e($docName) ?></div>
                                                <?php if ($phone !== ''): ?>
                                                    <div class="dp"><a href="tel:<?= e($telHref) ?>"><?= e($phone) ?></a></div>
                                                <?php endif; ?>
                                                <?php if ($me): ?><span class="svc-metag">Tú</span><?php endif; ?>
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

                <div class="svc-foot">
                    <span class="svc-leg"><span class="sw me"></span> Mis guardias</span>
                    <span class="svc-leg"><span class="sw wk"></span> Fin de semana</span>
                    <span class="svc-leg"><?= count($detRows) ?> especialidades · <?= count($detDays) ?> días · <?= (int) $detAssigned ?> asignaciones</span>
                    <span class="src"><i data-lucide="shield-check"></i> Fuente: área de Servicio · Hospital Las Colinas</span>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script>
(function () {
    var grid = document.getElementById('svcGrid');
    if (!grid) return;

    // Buscar especialidad o médico
    var search = document.getElementById('svcSearch');
    if (search) {
        search.addEventListener('input', function () {
            var q = this.value.trim().toLowerCase();
            var rows = grid.querySelectorAll('tbody tr');
            rows.forEach(function (tr) {
                var spec = tr.getAttribute('data-spec') || '';
                var matchSpec = q !== '' && spec.indexOf(q) !== -1;
                var anyDoc = false;
                tr.querySelectorAll('td.cell-doc').forEach(function (td) {
                    td.classList.remove('svc-hl', 'svc-dim');
                    var doc = td.getAttribute('data-doc') || '';
                    if (q === '') return;
                    if (doc !== '' && doc.indexOf(q) !== -1) { td.classList.add('svc-hl'); anyDoc = true; }
                    else if (!matchSpec) { td.classList.add('svc-dim'); }
                });
                tr.classList.toggle('svc-row-hidden', q !== '' && !matchSpec && !anyDoc);
            });
        });
    }

    // Solo mis guardias
    var mine = document.getElementById('svcMine');
    var mineLbl = document.getElementById('svcMineToggle');
    if (mine) {
        mine.addEventListener('change', function () {
            grid.classList.toggle('only-me', this.checked);
            if (mineLbl) mineLbl.classList.toggle('on', this.checked);
        });
    }

    // Imprimir
    var pr = document.getElementById('svcPrint');
    if (pr) pr.addEventListener('click', function () { window.print(); });
})();
</script>
<?php
doctor_layout_end();
