<?php
require_once __DIR__ . '/_layout.php';
doctor_require_login();

$months = max(1, min(24, (int)($_GET['months'] ?? 6)));
$res = portal_api_call('GET', '/portal-doctor/me/analytics', ['months' => $months], doctor_token());
$data = $res['data'] ?? ['by_status'=>[], 'monthly'=>[], 'top_patients'=>[], 'income_total'=>0];

$byStatus = [];
foreach ($data['by_status'] as $r) $byStatus[$r['status']] = (int)$r['total'];
$scheduled = $byStatus['scheduled'] ?? 0;
$completed = $byStatus['completed'] ?? 0;
$cancelled = $byStatus['cancelled'] ?? 0;
$grandTotal = $scheduled + $completed + $cancelled;
$completionRate = $grandTotal > 0 ? round(($completed / $grandTotal) * 100, 1) : 0;
$cancelRate     = $grandTotal > 0 ? round(($cancelled / $grandTotal) * 100, 1) : 0;

doctor_layout_begin('Analytics', 'analytics');
?>
<header class="doctor-header">
    <div>
        <p class="doctor-eyebrow">Indicadores</p>
        <h1>Mi desempeño clínico</h1>
        <p class="doctor-subtitle">Resumen de tu actividad en los últimos <?= $months ?> meses.</p>
    </div>
    <form method="GET" class="doctor-header-actions">
        <label class="doctor-cell-muted">Rango
            <select name="months" class="doctor-input doctor-input-inline" onchange="this.form.submit()">
                <option value="3" <?= $months===3?'selected':'' ?>>3 meses</option>
                <option value="6" <?= $months===6?'selected':'' ?>>6 meses</option>
                <option value="12" <?= $months===12?'selected':'' ?>>12 meses</option>
                <option value="24" <?= $months===24?'selected':'' ?>>24 meses</option>
            </select>
        </label>
    </form>
</header>

<section class="doctor-kpis">
    <article class="doctor-kpi doctor-kpi-blue">
        <span class="doctor-kpi-icon"><i data-lucide="calendar-check" class="h-5 w-5"></i></span>
        <div>
            <p class="doctor-kpi-label">Citas totales</p>
            <p class="doctor-kpi-value"><?= $grandTotal ?></p>
        </div>
    </article>
    <article class="doctor-kpi doctor-kpi-green">
        <span class="doctor-kpi-icon"><i data-lucide="check-circle-2" class="h-5 w-5"></i></span>
        <div>
            <p class="doctor-kpi-label">Tasa de completitud</p>
            <p class="doctor-kpi-value"><?= $completionRate ?>%</p>
        </div>
    </article>
    <article class="doctor-kpi doctor-kpi-amber">
        <span class="doctor-kpi-icon"><i data-lucide="x-circle" class="h-5 w-5"></i></span>
        <div>
            <p class="doctor-kpi-label">Tasa de cancelación</p>
            <p class="doctor-kpi-value"><?= $cancelRate ?>%</p>
        </div>
    </article>
    <article class="doctor-kpi doctor-kpi-violet">
        <span class="doctor-kpi-icon"><i data-lucide="dollar-sign" class="h-5 w-5"></i></span>
        <div>
            <p class="doctor-kpi-label">Ingresos cobrados</p>
            <p class="doctor-kpi-value">$<?= number_format((float)$data['income_total'], 0) ?></p>
        </div>
    </article>
</section>

<section class="doctor-grid-2 mt-4">
    <div class="doctor-card">
        <header class="doctor-card-header"><h2><i data-lucide="line-chart" class="h-5 w-5"></i> Evolución mensual</h2></header>
        <div class="doctor-form-pad">
            <canvas id="chart-monthly" height="220"></canvas>
        </div>
    </div>
    <div class="doctor-card">
        <header class="doctor-card-header"><h2><i data-lucide="pie-chart" class="h-5 w-5"></i> Distribución por estado</h2></header>
        <div class="doctor-form-pad" style="display:flex; justify-content:center;">
            <canvas id="chart-status" width="280" height="220"></canvas>
        </div>
    </div>
</section>

<section class="doctor-card mt-4">
    <header class="doctor-card-header"><h2><i data-lucide="users" class="h-5 w-5"></i> Top pacientes</h2></header>
    <?php if (empty($data['top_patients'])): ?>
        <div class="doctor-empty">
            <div class="doctor-empty-illustration">
                <i data-lucide="bar-chart-3" class="h-7 w-7"></i>
            </div>
            <p class="doctor-empty-title">Sin datos en este rango</p>
            <p>Cuando completes más citas, aquí verás tus pacientes más frecuentes.</p>
        </div>
    <?php else: ?>
        <div class="doctor-table-wrap">
            <table class="doctor-table">
                <thead><tr><th>#</th><th>Paciente</th><th class="text-right">Visitas</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($data['top_patients'] as $i => $tp): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><a class="doctor-link-strong" href="<?= e(base_url('portal-medico/paciente.php?id=' . (int)$tp['id'])) ?>"><?= e($tp['name']) ?></a></td>
                            <td class="text-right"><strong><?= (int)$tp['visits'] ?></strong></td>
                            <td><a class="doctor-table-action" href="<?= e(base_url('portal-medico/paciente.php?id=' . (int)$tp['id'])) ?>"><i data-lucide="chevron-right" class="h-5 w-5"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const monthlyData = <?= json_encode($data['monthly'], JSON_UNESCAPED_UNICODE) ?>;
const statusData = {
    scheduled: <?= (int)$scheduled ?>,
    completed: <?= (int)$completed ?>,
    cancelled: <?= (int)$cancelled ?>
};

document.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart === 'undefined') return;
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#6b7691';

    const C = { indigo: '#4f46e5', green: '#0a7a52', red: '#be123c' };
    const tip = { backgroundColor: '#0f1729', titleColor: '#fff', bodyColor: '#cbd2e0', padding: 12, cornerRadius: 10, boxPadding: 5, usePointStyle: true, titleFont: { weight: '700' } };
    const legend = { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, boxHeight: 8, padding: 16, font: { size: 12, weight: '600' } } };
    const softGrid = { color: 'rgba(15,23,41,.06)' };
    const area = (ctx, hex) => { const g = ctx.createLinearGradient(0, 0, 0, 240); g.addColorStop(0, hex + '33'); g.addColorStop(1, hex + '00'); return g; };
    const ds = (label, data, hex, cx) => ({ label, data, borderColor: hex, backgroundColor: area(cx, hex), fill: true, tension: .4, borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 5, pointHoverBackgroundColor: hex, pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2 });

    const m = document.getElementById('chart-monthly');
    if (m && monthlyData.length) {
        const cx = m.getContext('2d');
        new Chart(m, {
            type: 'line',
            data: { labels: monthlyData.map(r => r.ym), datasets: [
                ds('Completadas', monthlyData.map(r => +r.completed), C.green, cx),
                ds('Agendadas', monthlyData.map(r => +r.scheduled), C.indigo, cx),
                ds('Canceladas', monthlyData.map(r => +r.cancelled), C.red, cx),
            ] },
            options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: { legend, tooltip: tip },
                scales: { x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 11 } } },
                          y: { beginAtZero: true, grid: softGrid, border: { display: false }, ticks: { precision: 0, font: { size: 11 } } } } }
        });
    }

    const s = document.getElementById('chart-status');
    if (s) {
        new Chart(s, {
            type: 'doughnut',
            data: { labels: ['Agendadas', 'Completadas', 'Canceladas'], datasets: [{ data: [statusData.scheduled, statusData.completed, statusData.cancelled], backgroundColor: [C.indigo, C.green, C.red], borderWidth: 0, hoverOffset: 6, spacing: 2 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '72%', plugins: { legend, tooltip: tip } }
        });
    }
});
</script>
<?php doctor_layout_end();
