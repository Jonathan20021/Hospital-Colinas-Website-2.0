<?php
require_once __DIR__ . '/_layout.php';
doctor_require_login();

$res = portal_api_call('GET', '/portal-doctor/me/availability', [], doctor_token());
$absences = $res['data'] ?? [];

// Ordenar por fecha ascendente
usort($absences, fn($a, $b) => strcmp($a['date'], $b['date']));

// Agrupar días consecutivos con el mismo motivo en rangos (para la lista)
$groups = [];
foreach ($absences as $a) {
    $d = substr((string)$a['date'], 0, 10);
    $reason = trim((string)($a['reason'] ?? ''));
    $n = count($groups);
    if ($n > 0
        && $groups[$n - 1]['reason'] === $reason
        && date('Y-m-d', strtotime($groups[$n - 1]['end'] . ' +1 day')) === $d) {
        $groups[$n - 1]['end'] = $d;
        $groups[$n - 1]['ids'][] = (int)$a['id'];
        $groups[$n - 1]['count']++;
    } else {
        $groups[] = ['start' => $d, 'end' => $d, 'reason' => $reason, 'ids' => [(int)$a['id']], 'count' => 1];
    }
}

// Fechas ya bloqueadas (para deshabilitarlas en el calendario)
$blockedDates = array_values(array_unique(array_map(fn($a) => substr((string)$a['date'], 0, 10), $absences)));

doctor_layout_begin('Disponibilidad', 'disponibilidad');
?>
<header class="doctor-header" data-reveal>
    <div>
        <p class="doctor-eyebrow">Disponibilidad</p>
        <h1>Mis ausencias</h1>
        <p class="doctor-subtitle">Bloquea los días en los que no estarás disponible. Elige un solo día o arrastra un rango completo en el calendario.</p>
    </div>
</header>

<div class="dm-grid" data-reveal data-reveal-d="1">
    <!-- CALENDARIO + SELECCIÓN -->
    <section class="dm-panel">
        <div class="av-head">
            <div>
                <h2 class="ttl"><i data-lucide="calendar-plus"></i> Bloquear fechas</h2>
                <p class="sub">Toca un día para empezar y otro para cerrar el rango</p>
            </div>
        </div>
        <div class="av-cal">
            <div class="av-cal-nav">
                <button type="button" id="av-prev" aria-label="Mes anterior"><i data-lucide="chevron-left"></i></button>
                <span id="av-month" class="av-month">—</span>
                <button type="button" id="av-next" aria-label="Mes siguiente"><i data-lucide="chevron-right"></i></button>
            </div>
            <div class="av-dow"><span>L</span><span>M</span><span>X</span><span>J</span><span>V</span><span>S</span><span>D</span></div>
            <div class="av-grid" id="av-grid"></div>
            <div class="av-legend">
                <span><i class="av-lg-sel"></i> Seleccionado</span>
                <span><i class="av-lg-blk"></i> Ya bloqueado</span>
                <span><i class="av-lg-today"></i> Hoy</span>
            </div>
        </div>
        <div class="av-confirm">
            <div class="av-sel" id="av-sel">
                <i data-lucide="hand-pointer"></i>
                <div><strong id="av-sel-main">Ninguna fecha seleccionada</strong><span id="av-sel-sub">Elige en el calendario de arriba</span></div>
            </div>
            <label class="doctor-label" for="av-reason">Motivo (opcional)</label>
            <input type="text" id="av-reason" class="doctor-input" placeholder="Vacaciones, congreso, capacitación…" maxlength="120">
            <div class="av-actions">
                <button type="button" class="doctor-btn doctor-btn-ghost" id="av-clear" hidden><i data-lucide="x"></i> Limpiar</button>
                <button type="button" class="doctor-btn doctor-btn-primary" id="av-confirm-btn" disabled><i data-lucide="calendar-off"></i> Bloquear fechas</button>
            </div>
            <p id="av-status" class="doctor-save-status"></p>
        </div>
    </section>

    <!-- LISTA DE AUSENCIAS -->
    <section class="dm-panel dm-card">
        <header class="dm-card-h">
            <h2><i data-lucide="calendar-x"></i> Ausencias programadas</h2>
            <span class="av-count" id="av-count"><?= count($absences) ?> día<?= count($absences) === 1 ? '' : 's' ?></span>
        </header>
        <div class="av-list" id="av-list">
            <?php if (!$groups): ?>
                <div class="doctor-empty" style="padding:36px 18px">
                    <div class="doctor-empty-illustration"><i data-lucide="calendar-check" class="h-7 w-7"></i></div>
                    <p class="doctor-empty-title">Sin ausencias programadas</p>
                    <p>Cuando bloquees fechas no disponibles, aparecerán aquí.</p>
                </div>
            <?php else: foreach ($groups as $g):
                $tsS = strtotime($g['start']); $tsE = strtotime($g['end']);
                $isRange = $g['start'] !== $g['end'];
            ?>
                <div class="av-item" data-ids="<?= e(implode(',', $g['ids'])) ?>">
                    <div class="av-item-date">
                        <strong><?= e(date('d', $tsS)) ?></strong>
                        <span><?= e(doctor_mes_corto_es($tsS)) ?></span>
                    </div>
                    <div class="av-item-info">
                        <p class="av-item-title">
                            <?= $isRange
                                ? e(doctor_fecha_corta($tsS)) . ' — ' . e(doctor_fecha_corta($tsE))
                                : e(doctor_fecha_es($tsS)) ?>
                        </p>
                        <p class="av-item-meta">
                            <?php if ($isRange): ?><span class="av-item-badge"><?= (int)$g['count'] ?> días</span><?php endif; ?>
                            <?= e($g['reason'] !== '' ? $g['reason'] : 'Sin motivo registrado') ?>
                        </p>
                    </div>
                    <button type="button" class="av-del" data-ids="<?= e(implode(',', $g['ids'])) ?>" title="Eliminar" aria-label="Eliminar ausencia">
                        <i data-lucide="trash-2" class="h-4 w-4"></i>
                    </button>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </section>
</div>

<script>
window.DM_AVAIL = {
    today: <?= json_encode(date('Y-m-d')) ?>,
    blocked: <?= json_encode($blockedDates, JSON_UNESCAPED_SLASHES) ?>
};
</script>
<script src="<?= e(base_url('assets/js/portal-medico-availability.js')) ?>?v=<?= e((string)(@filemtime(__DIR__ . '/../assets/js/portal-medico-availability.js') ?: time())) ?>"></script>
<?php doctor_layout_end();
