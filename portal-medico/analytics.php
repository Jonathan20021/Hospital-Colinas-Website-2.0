<?php
require_once __DIR__ . '/_layout.php';
doctor_require_login();

$months = max(1, min(24, (int)($_GET['months'] ?? 6)));
$res = portal_api_call('GET', '/portal-doctor/me/analytics', ['months' => $months], doctor_token());
$data = $res['data'] ?? ['by_status'=>[], 'monthly'=>[], 'top_patients'=>[]];

$doctor    = doctor_current() ?? [];
$doctorNm  = trim(mb_convert_case(mb_strtolower((string)($doctor['name'] ?? ''), 'UTF-8'), MB_CASE_TITLE, 'UTF-8'));
$specialty = (string)($doctor['specialty'] ?? '');

$byStatus = [];
foreach (($data['by_status'] ?? []) as $r) $byStatus[$r['status']] = (int)$r['total'];
$scheduled = $byStatus['scheduled'] ?? 0;
$completed = $byStatus['completed'] ?? 0;
$cancelled = $byStatus['cancelled'] ?? 0;
$grandTotal = $scheduled + $completed + $cancelled;
$completionRate = $grandTotal > 0 ? round(($completed / $grandTotal) * 100, 1) : 0;
$cancelRate     = $grandTotal > 0 ? round(($cancelled / $grandTotal) * 100, 1) : 0;

$monthly     = $data['monthly'] ?? [];
$avgPerMonth = count($monthly) ? round($grandTotal / count($monthly), 1) : $grandTotal;
$topPatients = $data['top_patients'] ?? [];

$kpis = [
    ['blue',   'calendar-check', 'Citas totales',       number_format($grandTotal), 'En el período'],
    ['green',  'check-circle-2', 'Tasa de completitud', $completionRate . '%',      $completed . ' completadas'],
    ['amber',  'x-circle',       'Tasa de cancelación', $cancelRate . '%',          $cancelled . ' canceladas'],
    ['violet', 'trending-up',    'Promedio mensual',    $avgPerMonth,               'citas por mes'],
];

$jspdfV = (string)(@filemtime(__DIR__ . '/../assets/vendor/jspdf/jspdf.umd.min.js') ?: 1);

doctor_layout_begin('Analytics', 'analytics');
?>
<header class="doctor-header" data-reveal>
    <div>
        <p class="doctor-eyebrow">Indicadores</p>
        <h1>Mi desempeño clínico</h1>
        <p class="doctor-subtitle">Análisis de tu actividad clínica en los últimos <?= (int)$months ?> meses.</p>
    </div>
    <div class="an-actions">
        <form method="GET" class="an-range">
            <div class="dm-seg">
                <?php foreach ([3=>'3 m', 6=>'6 m', 12=>'12 m', 24=>'24 m'] as $mv => $ml): ?>
                    <button type="submit" name="months" value="<?= $mv ?>" class="dm-seg-btn <?= $months === $mv ? 'on' : '' ?>"><?= $ml ?></button>
                <?php endforeach; ?>
            </div>
        </form>
        <?php if ($grandTotal > 0): ?>
        <button type="button" class="doctor-btn doctor-btn-primary" id="an-export"><i data-lucide="file-down" class="h-4 w-4"></i> Exportar PDF</button>
        <?php endif; ?>
    </div>
</header>

<!-- KPIs -->
<section class="doctor-kpis" data-reveal data-reveal-d="1">
    <?php foreach ($kpis as [$tone, $ic, $lbl, $val, $sub]): ?>
    <article class="doctor-kpi doctor-kpi-<?= $tone ?>">
        <span class="doctor-kpi-icon"><i data-lucide="<?= $ic ?>" class="h-5 w-5"></i></span>
        <div>
            <p class="doctor-kpi-label"><?= e($lbl) ?></p>
            <p class="doctor-kpi-value"><?= e($val) ?></p>
            <p class="an-kpi-sub"><?= e($sub) ?></p>
        </div>
    </article>
    <?php endforeach; ?>
</section>

<?php if ($grandTotal === 0): ?>
    <section class="dm-panel" data-reveal data-reveal-d="2" style="margin-top:18px">
        <div class="doctor-empty" style="padding:48px 22px">
            <div class="doctor-empty-illustration"><i data-lucide="bar-chart-3" class="h-7 w-7"></i></div>
            <p class="doctor-empty-title">Aún no hay datos para analizar</p>
            <p>Cuando registres consultas en este período, aquí verás tus gráficos de actividad, completitud y pacientes más frecuentes.</p>
        </div>
    </section>
<?php else: ?>

<!-- BARRA DE FILTROS -->
<div class="an-filters" data-reveal data-reveal-d="1">
    <span class="an-filters-lbl"><i data-lucide="sliders-horizontal"></i> Filtrar estados:</span>
    <button type="button" class="an-chip on" data-state="completed"><i class="dot" style="background:var(--hg-green)"></i> Completadas</button>
    <button type="button" class="an-chip on" data-state="scheduled"><i class="dot" style="background:var(--hg-navy-600)"></i> Agendadas</button>
    <button type="button" class="an-chip on" data-state="cancelled"><i class="dot" style="background:#be123c"></i> Canceladas</button>
    <span class="an-filters-hint">Toca para mostrar u ocultar en los gráficos</span>
</div>

<!-- EVOLUCIÓN + DISTRIBUCIÓN -->
<div class="dm-grid">
    <section class="dm-panel" data-reveal data-reveal-d="2">
        <div class="dm-panel-h">
            <div><h2 class="ttl">Evolución de citas</h2><p class="sub">Completadas, agendadas y canceladas por mes</p></div>
            <div class="dm-seg" id="an-evo-view">
                <button type="button" class="dm-seg-btn on" data-view="area">Área</button>
                <button type="button" class="dm-seg-btn" data-view="bar">Barras</button>
            </div>
        </div>
        <div class="dm-chartbox"><canvas id="chart-monthly"></canvas></div>
    </section>

    <section class="dm-panel" data-reveal data-reveal-d="3">
        <div class="dm-panel-h"><div><h2 class="ttl">Distribución por estado</h2><p class="sub">Del período completo</p></div></div>
        <div class="dm-donut-wrap">
            <canvas id="chart-status"></canvas>
            <div class="dm-donut-center"><span class="k">Completitud</span><span class="v"><?= $completionRate ?>%</span></div>
        </div>
        <div class="dm-donut-legend">
            <span><i style="background:var(--hg-green)"></i> Completadas <b><?= $completed ?></b></span>
            <span><i style="background:var(--hg-navy-600)"></i> Agendadas <b><?= $scheduled ?></b></span>
            <span><i style="background:#be123c"></i> Canceladas <b><?= $cancelled ?></b></span>
        </div>
    </section>
</div>

<!-- COMPLETITUD MENSUAL + TOP PACIENTES -->
<div class="dm-grid">
    <section class="dm-panel" data-reveal data-reveal-d="2">
        <div class="dm-panel-h"><div><h2 class="ttl">Tasa de completitud mensual</h2><p class="sub">% de citas completadas sobre el total del mes</p></div></div>
        <div class="dm-chartbox sm"><canvas id="chart-completion"></canvas></div>
    </section>
    <section class="dm-panel" data-reveal data-reveal-d="3">
        <div class="dm-panel-h"><div><h2 class="ttl">Pacientes más frecuentes</h2><p class="sub">Por número de visitas</p></div></div>
        <?php if ($topPatients): ?>
            <div class="dm-chartbox sm"><canvas id="chart-top"></canvas></div>
        <?php else: ?>
            <div class="doctor-empty" style="padding:30px 18px"><div class="dm-empty-ic"><i data-lucide="users"></i></div><p class="t">Sin pacientes frecuentes aún</p></div>
        <?php endif; ?>
    </section>
</div>

<!-- TABLA TOP PACIENTES -->
<?php if ($topPatients): ?>
<section class="doctor-card mt-4" data-reveal data-reveal-d="2">
    <header class="doctor-card-header"><h2><i data-lucide="list-ordered" class="h-5 w-5"></i> Detalle de pacientes frecuentes</h2></header>
    <div class="doctor-table-wrap">
        <table class="doctor-table">
            <thead><tr><th>#</th><th>Paciente</th><th class="text-right">Visitas</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($topPatients as $i => $tp): ?>
                    <tr>
                        <td><span class="an-rank"><?= $i + 1 ?></span></td>
                        <td><div class="doctor-table-patient"><?= doctor_avatar_html($tp['name']) ?><a class="doctor-link-strong" href="<?= e(base_url('portal-medico/paciente.php?id=' . (int)$tp['id'])) ?>"><?= e($tp['name']) ?></a></div></td>
                        <td class="text-right"><span class="doctor-visit-chip"><?= (int)$tp['visits'] ?></span></td>
                        <td><a class="doctor-table-action" href="<?= e(base_url('portal-medico/paciente.php?id=' . (int)$tp['id'])) ?>"><i data-lucide="chevron-right" class="h-5 w-5"></i></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php endif; /* grandTotal */ ?>

<script src="<?= e(base_url('assets/vendor/chartjs/chart.umd.min.js')) ?>?v=<?= e((string)(@filemtime(__DIR__ . '/../assets/vendor/chartjs/chart.umd.min.js') ?: time())) ?>"></script>
<script>window.Chart||document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"><\/script>');</script>
<script>
window.AN_DATA = {
    monthly: <?= json_encode($monthly, JSON_UNESCAPED_UNICODE) ?>,
    status:  { scheduled: <?= (int)$scheduled ?>, completed: <?= (int)$completed ?>, cancelled: <?= (int)$cancelled ?> },
    top:     <?= json_encode(array_map(fn($t) => ['name' => $t['name'], 'visits' => (int)$t['visits']], $topPatients), JSON_UNESCAPED_UNICODE) ?>,
    meta: {
        doctor: <?= json_encode($doctorNm ?: 'Médico', JSON_UNESCAPED_UNICODE) ?>,
        specialty: <?= json_encode($specialty, JSON_UNESCAPED_UNICODE) ?>,
        months: <?= (int)$months ?>,
        total: <?= (int)$grandTotal ?>,
        completed: <?= (int)$completed ?>,
        scheduled: <?= (int)$scheduled ?>,
        cancelled: <?= (int)$cancelled ?>,
        completionRate: <?= json_encode($completionRate) ?>,
        cancelRate: <?= json_encode($cancelRate) ?>,
        avgPerMonth: <?= json_encode($avgPerMonth) ?>,
        logo: <?= json_encode(base_url('assets/site/logo.png'), JSON_UNESCAPED_SLASHES) ?>,
        jspdf: <?= json_encode(base_url('assets/vendor/jspdf/jspdf.umd.min.js') . '?v=' . $jspdfV, JSON_UNESCAPED_SLASHES) ?>
    }
};
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart === 'undefined' || !window.AN_DATA || !document.getElementById('chart-monthly')) return;
    const D = window.AN_DATA;
    Chart.defaults.font.family = "'Plus Jakarta Sans', system-ui, sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#737e9b';

    const NAVY = '#322d82', GREEN = '#5da334', RED = '#be123c';
    const tip = { backgroundColor: '#262161', titleColor: '#fff', bodyColor: '#d7d5ec', padding: 12, cornerRadius: 12, boxPadding: 6, usePointStyle: true, titleFont: { weight: '700', family: 'Outfit' } };
    const softGrid = { color: 'rgba(38,33,97,.06)' };
    const MES_AN = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    const fmtYm = (ym) => { const p = String(ym).split('-'); return p.length >= 2 ? MES_AN[(+p[1]) - 1] + " '" + p[0].slice(2) : ym; };
    const labels = D.monthly.map(r => fmtYm(r.ym));
    const charts = {};

    // Estado de filtros — DEBE definirse ANTES de buildEvo (que llama applyFilters);
    // de lo contrario applyFilters accede a stateOn/idx en TDZ y rompe la init.
    const stateOn = { completed: true, scheduled: true, cancelled: true };
    const idx = { completed: 0, scheduled: 1, cancelled: 2 };
    function applyFilters() {
        if (charts.evo) { ['completed', 'scheduled', 'cancelled'].forEach(s => charts.evo.setDatasetVisibility(idx[s], stateOn[s])); charts.evo.update(); }
        if (charts.status) { ['completed', 'scheduled', 'cancelled'].forEach(s => { if (charts.status.getDataVisibility(idx[s]) !== stateOn[s]) charts.status.toggleDataVisibility(idx[s]); }); charts.status.update(); }
    }

    // 1) Evolución (área/barras) — interactivo
    const mc = document.getElementById('chart-monthly');
    function buildEvo(kind) {
        const cx = mc.getContext('2d');
        const area = (hex) => { const g = cx.createLinearGradient(0, 0, 0, 260); g.addColorStop(0, hex + 'cc'); g.addColorStop(1, hex + '7a'); return g; };
        const C = D.monthly.map(r => +r.completed), S = D.monthly.map(r => +r.scheduled), X = D.monthly.map(r => +r.cancelled);
        const datasets = kind === 'bar'
            ? [ { label: 'Completadas', data: C, backgroundColor: GREEN, borderRadius: 6, stack: 's', maxBarThickness: 34 },
                { label: 'Agendadas',   data: S, backgroundColor: NAVY,  borderRadius: 6, stack: 's', maxBarThickness: 34 },
                { label: 'Canceladas',  data: X, backgroundColor: RED,   borderRadius: 6, stack: 's', maxBarThickness: 34 } ]
            : [ { label: 'Completadas', data: C, borderColor: GREEN, backgroundColor: area(GREEN), fill: 'origin', tension: .4, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4 },
                { label: 'Agendadas',   data: S, borderColor: NAVY,  backgroundColor: area(NAVY),  fill: '-1', tension: .4, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4 },
                { label: 'Canceladas',  data: X, borderColor: RED,   backgroundColor: area(RED),   fill: '-1', tension: .4, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4 } ];
        const cfg = {
            type: kind === 'bar' ? 'bar' : 'line',
            data: { labels, datasets },
            options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, animation: { duration: 600 },
                layout: { padding: { left: 6, right: 12, top: 6 } },
                plugins: { legend: { display: false }, tooltip: tip },
                scales: { x: { stacked: true, offset: true, grid: { display: false }, border: { display: false }, ticks: { maxRotation: 0, autoSkip: true, autoSkipPadding: 14 } },
                          y: { stacked: true, beginAtZero: true, grid: softGrid, border: { display: false }, ticks: { precision: 0 } } } }
        };
        if (charts.evo) charts.evo.destroy();
        charts.evo = new Chart(mc, cfg);
        applyFilters();
    }
    buildEvo('area');
    document.getElementById('an-evo-view').addEventListener('click', (e) => {
        const b = e.target.closest('.dm-seg-btn'); if (!b) return;
        document.querySelectorAll('#an-evo-view .dm-seg-btn').forEach(x => x.classList.remove('on'));
        b.classList.add('on'); buildEvo(b.dataset.view);
    });

    // 2) Doughnut
    const sc = document.getElementById('chart-status');
    if (sc) charts.status = new Chart(sc, {
        type: 'doughnut',
        data: { labels: ['Completadas', 'Agendadas', 'Canceladas'], datasets: [{ data: [D.status.completed, D.status.scheduled, D.status.cancelled], backgroundColor: [GREEN, NAVY, RED], borderWidth: 0, hoverOffset: 8, spacing: 2 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '72%', plugins: { legend: { display: false }, tooltip: tip } }
    });

    // 3) Completitud mensual (línea %)
    const cc = document.getElementById('chart-completion');
    if (cc) {
        const cx = cc.getContext('2d');
        const g = cx.createLinearGradient(0, 0, 0, 240); g.addColorStop(0, GREEN + '40'); g.addColorStop(1, GREEN + '04');
        const rate = D.monthly.map(r => { const t = (+r.completed) + (+r.scheduled) + (+r.cancelled); return t ? Math.round((+r.completed) / t * 100) : 0; });
        charts.completion = new Chart(cc, {
            type: 'line',
            data: { labels, datasets: [{ label: '% completitud', data: rate, borderColor: GREEN, backgroundColor: g, fill: true, tension: .4, borderWidth: 2.5, pointRadius: 3, pointBackgroundColor: GREEN, pointBorderColor: '#fff', pointBorderWidth: 1.5, pointHoverRadius: 5 }] },
            options: { responsive: true, maintainAspectRatio: false, layout: { padding: { left: 6, right: 12, top: 6 } }, plugins: { legend: { display: false }, tooltip: { ...tip, callbacks: { label: (c) => ' ' + c.parsed.y + '% completadas' } } },
                scales: { x: { offset: true, grid: { display: false }, border: { display: false }, ticks: { maxRotation: 0, autoSkip: true, autoSkipPadding: 14 } }, y: { beginAtZero: true, max: 100, grid: softGrid, border: { display: false }, ticks: { callback: (v) => v + '%' } } } }
        });
    }

    // 4) Top pacientes (barras horizontales)
    const tc = document.getElementById('chart-top');
    if (tc && D.top.length) {
        const top = D.top.slice(0, 8);
        charts.top = new Chart(tc, {
            type: 'bar',
            data: { labels: top.map(t => t.name), datasets: [{ label: 'Visitas', data: top.map(t => t.visits), backgroundColor: NAVY, borderRadius: 7, maxBarThickness: 22, hoverBackgroundColor: GREEN }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: tip },
                scales: { x: { beginAtZero: true, grid: softGrid, border: { display: false }, ticks: { precision: 0 } }, y: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 11 } } } } }
        });
    }

    // ── FILTROS DE ESTADO: los chips togglean evolución + doughnut ──────────
    document.querySelectorAll('.an-chip').forEach(c => c.addEventListener('click', () => {
        const s = c.dataset.state; stateOn[s] = !stateOn[s]; c.classList.toggle('on', stateOn[s]); applyFilters();
    }));

    // ── EXPORTAR PDF (jsPDF · diseño con identidad HGLC) ────────────────────
    const exportBtn = document.getElementById('an-export');
    if (exportBtn) exportBtn.addEventListener('click', () => exportPDF(charts, exportBtn));
});

function exportPDF(charts, btn) {
    const D = window.AN_DATA, M = D.meta;
    const loadScript = (src) => new Promise((res, rej) => { const s = document.createElement('script'); s.src = src; s.onload = res; s.onerror = rej; document.head.appendChild(s); });
    const loadImg = (src) => new Promise((res) => { if (!src) return res(null); const i = new Image(); i.crossOrigin = 'anonymous'; i.onload = () => { try { const c = document.createElement('canvas'); c.width = i.naturalWidth; c.height = i.naturalHeight; c.getContext('2d').drawImage(i, 0, 0); res({ d: c.toDataURL('image/png'), w: i.naturalWidth, h: i.naturalHeight }); } catch (e) { res(null); } }; i.onerror = () => res(null); i.src = src; });
    const old = btn.innerHTML; btn.disabled = true; btn.innerHTML = 'Generando…';

    (async () => {
        try {
            if (!window.jspdf) await loadScript(M.jspdf);
            const logo = await loadImg(M.logo);
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ unit: 'pt', format: 'letter' });
            const PW = doc.internal.pageSize.getWidth();   // 612
            const PH = doc.internal.pageSize.getHeight();  // 792
            const MA = 40, CW = PW - MA * 2;
            const NAVY = [38, 33, 97], NAVY6 = [50, 45, 130], GREEN = [93, 163, 52], INK = [20, 22, 58], MUT = [113, 122, 156], LINE = [233, 236, 245];

            // ── Encabezado: logo + datos del hospital ──
            let y = MA;
            if (logo) { const lw = 150, lh = lw * (logo.h / logo.w); doc.addImage(logo.d, 'PNG', MA, y, lw, lh); }
            doc.setFont('helvetica', 'bold'); doc.setFontSize(10); doc.setTextColor(...NAVY);
            doc.text('Portal Médico', PW - MA, y + 12, { align: 'right' });
            doc.setFont('helvetica', 'normal'); doc.setFontSize(9); doc.setTextColor(...MUT);
            doc.text('Hospital General Las Colinas', PW - MA, y + 26, { align: 'right' });
            doc.text('Documento confidencial', PW - MA, y + 39, { align: 'right' });
            y += 56;
            // línea de acento bicolor (navy + verde)
            doc.setFillColor(...NAVY6); doc.rect(MA, y, CW * 0.62, 3, 'F');
            doc.setFillColor(...GREEN); doc.rect(MA + CW * 0.62, y, CW * 0.38, 3, 'F');
            y += 24;

            // ── Título + metadatos ──
            doc.setFont('helvetica', 'bold'); doc.setFontSize(17); doc.setTextColor(...INK);
            doc.text('Informe de indicadores clínicos', MA, y); y += 18;
            doc.setFont('helvetica', 'normal'); doc.setFontSize(10); doc.setTextColor(...MUT);
            const drLine = 'Dr/a. ' + M.doctor + (M.specialty ? '  ·  ' + M.specialty : '');
            doc.text(drLine, MA, y); y += 14;
            const now = new Date();
            const fecha = now.toLocaleDateString('es-DO', { day: '2-digit', month: 'long', year: 'numeric' }) + ' · ' + now.toLocaleTimeString('es-DO', { hour: '2-digit', minute: '2-digit' });
            doc.text('Período: últimos ' + M.months + ' meses    ·    Generado: ' + fecha, MA, y); y += 22;

            // ── KPIs (4 cajas) ──
            const kpis = [
                ['Citas totales', String(M.total)],
                ['Tasa de completitud', M.completionRate + '%'],
                ['Tasa de cancelación', M.cancelRate + '%'],
                ['Promedio mensual', String(M.avgPerMonth)],
            ];
            const gap = 12, kw = (CW - gap * 3) / 4, kh = 58;
            kpis.forEach((k, i) => {
                const kx = MA + i * (kw + gap);
                doc.setDrawColor(...LINE); doc.setFillColor(250, 251, 253);
                doc.roundedRect(kx, y, kw, kh, 8, 8, 'FD');
                doc.setFont('helvetica', 'normal'); doc.setFontSize(7.5); doc.setTextColor(...MUT);
                doc.text(k[0].toUpperCase(), kx + 11, y + 18);
                doc.setFont('helvetica', 'bold'); doc.setFontSize(18); doc.setTextColor(...NAVY);
                doc.text(k[1], kx + 11, y + 42);
            });
            y += kh + 24;

            // ── Helper: añade un gráfico (título + imagen del canvas) ──
            const colW = (CW - gap) / 2, chartH = 150;
            function chartBlock(chart, title, cx, cy, boxW, boxH, donutCenter) {
                doc.setFont('helvetica', 'bold'); doc.setFontSize(10.5); doc.setTextColor(...INK);
                doc.text(title, cx, cy);
                const top = cy + 8;
                if (chart) {
                    try {
                        // Mantener el aspect ratio real del canvas (evita doughnut ovalado) y centrar
                        const cv = chart.canvas, ar = (cv.width || 2) / (cv.height || 1);
                        let w = boxW, h = w / ar;
                        if (h > boxH) { h = boxH; w = h * ar; }
                        const ox = cx + (boxW - w) / 2;
                        doc.addImage(chart.toBase64Image('image/png', 1), 'PNG', ox, top, w, h);
                        if (donutCenter) {
                            doc.setFont('helvetica', 'bold'); doc.setFontSize(15); doc.setTextColor(...INK);
                            doc.text(donutCenter, ox + w / 2, top + h / 2 + 1, { align: 'center' });
                            doc.setFont('helvetica', 'normal'); doc.setFontSize(7); doc.setTextColor(...MUT);
                            doc.text('completitud', ox + w / 2, top + h / 2 + 13, { align: 'center' });
                        }
                    } catch (e) {}
                }
            }
            // Fila 1: evolución + doughnut
            chartBlock(charts.evo, 'Evolución de citas', MA, y, colW, chartH);
            chartBlock(charts.status, 'Distribución por estado', MA + colW + gap, y, colW, chartH, M.completionRate + '%');
            y += chartH + 28;
            // Fila 2: completitud + top
            chartBlock(charts.completion, 'Tasa de completitud mensual', MA, y, colW, chartH);
            chartBlock(charts.top, 'Pacientes más frecuentes', MA + colW + gap, y, colW, chartH);
            y += chartH + 28;

            // ── Tabla de pacientes frecuentes ──
            if (D.top && D.top.length) {
                if (y > PH - 140) { doc.addPage(); y = MA; }
                doc.setFont('helvetica', 'bold'); doc.setFontSize(10.5); doc.setTextColor(...INK);
                doc.text('Pacientes más frecuentes', MA, y); y += 14;
                // cabecera
                doc.setFillColor(246, 248, 252); doc.rect(MA, y, CW, 22, 'F');
                doc.setFont('helvetica', 'bold'); doc.setFontSize(8); doc.setTextColor(...MUT);
                doc.text('#', MA + 10, y + 14);
                doc.text('PACIENTE', MA + 36, y + 14);
                doc.text('VISITAS', PW - MA - 10, y + 14, { align: 'right' });
                y += 22;
                doc.setFont('helvetica', 'normal'); doc.setFontSize(10); doc.setTextColor(...INK);
                D.top.slice(0, 12).forEach((t, i) => {
                    if (y > PH - 60) { doc.addPage(); y = MA; }
                    doc.setTextColor(...MUT); doc.setFontSize(9);
                    doc.text(String(i + 1), MA + 10, y + 15);
                    doc.setTextColor(...INK); doc.setFontSize(10);
                    doc.text(String(t.name).slice(0, 60), MA + 36, y + 15);
                    doc.setFont('helvetica', 'bold'); doc.setTextColor(...NAVY);
                    doc.text(String(t.visits), PW - MA - 10, y + 15, { align: 'right' });
                    doc.setFont('helvetica', 'normal');
                    doc.setDrawColor(...LINE); doc.line(MA, y + 24, PW - MA, y + 24);
                    y += 26;
                });
            }

            // ── Footer en todas las páginas ──
            const pages = doc.internal.getNumberOfPages();
            for (let p = 1; p <= pages; p++) {
                doc.setPage(p);
                doc.setDrawColor(...LINE); doc.line(MA, PH - 36, PW - MA, PH - 36);
                doc.setFont('helvetica', 'normal'); doc.setFontSize(8); doc.setTextColor(...MUT);
                doc.text('Hospital General Las Colinas · Portal Médico · Documento confidencial', MA, PH - 22);
                doc.text('Página ' + p + ' de ' + pages, PW - MA, PH - 22, { align: 'right' });
            }

            const safe = (M.doctor || 'medico').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/gi, '-').toLowerCase();
            doc.save('indicadores-' + safe + '.pdf');
        } catch (e) {
            alert('No se pudo generar el PDF. Intenta de nuevo.');
        } finally {
            btn.disabled = false; btn.innerHTML = old;
            if (window.lucide) window.lucide.createIcons();
        }
    })();
}
</script>
<?php doctor_layout_end();
