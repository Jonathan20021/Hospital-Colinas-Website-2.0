<?php
require_once __DIR__ . '/_layout.php';
doctor_require_login();

$res = portal_api_call('GET', '/portal-doctor/me/availability', [], doctor_token());
$absences = $res['data'] ?? [];

doctor_layout_begin('Disponibilidad', 'disponibilidad');
?>
<header class="doctor-header">
    <div>
        <p class="doctor-eyebrow">Disponibilidad</p>
        <h1>Mis ausencias</h1>
        <p class="doctor-subtitle">Marca los días en los que no estarás disponible para consulta.</p>
    </div>
</header>

<section class="doctor-grid-2">
    <div class="doctor-card">
        <header class="doctor-card-header">
            <h2><i data-lucide="calendar-plus" class="h-5 w-5"></i> Añadir ausencia</h2>
        </header>
        <form id="avail-form" class="doctor-form-pad doctor-form-grid">
            <label>Fecha
                <input type="date" name="date" class="doctor-input" min="<?= date('Y-m-d') ?>" required>
            </label>
            <label class="doctor-form-full">Motivo (opcional)
                <input type="text" name="reason" class="doctor-input" placeholder="Congreso, vacaciones, capacitación...">
            </label>
            <button type="submit" class="doctor-btn doctor-btn-primary doctor-form-full"><i data-lucide="plus" class="h-4 w-4"></i> Registrar ausencia</button>
        </form>
    </div>

    <div class="doctor-card">
        <header class="doctor-card-header">
            <h2><i data-lucide="calendar-x" class="h-5 w-5"></i> Próximas ausencias (<?= count($absences) ?>)</h2>
        </header>
        <?php if (!$absences): ?>
            <div class="doctor-empty">
                <div class="doctor-empty-illustration">
                    <i data-lucide="calendar-check" class="h-7 w-7"></i>
                </div>
                <p class="doctor-empty-title">Sin ausencias programadas</p>
                <p>Cuando bloquees fechas no disponibles, aparecerán aquí.</p>
            </div>
        <?php else: ?>
            <ul class="doctor-appt-list" id="avail-list">
                <?php foreach ($absences as $a):
                    $ts = strtotime($a['date']);
                ?>
                    <li class="doctor-appt-row" data-id="<?= (int)$a['id'] ?>">
                        <div class="doctor-appt-date">
                            <strong><?= e(date('d', $ts)) ?></strong>
                            <span><?= e(strtoupper(date('M', $ts))) ?></span>
                        </div>
                        <div class="doctor-appt-info">
                            <p class="doctor-appt-name"><?= e(date('l j \d\e F, Y', $ts)) ?></p>
                            <p class="doctor-appt-meta"><?= e($a['reason'] ?: 'Sin motivo registrado') ?></p>
                        </div>
                        <button type="button" class="doctor-appt-action js-del-avail" data-id="<?= (int)$a['id'] ?>" title="Eliminar">
                            <i data-lucide="trash-2" class="h-4 w-4"></i>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('avail-form');
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const data = { date: fd.get('date'), reason: fd.get('reason') };
        const r = await window.doctorApi('POST', '/portal-doctor/me/availability', data);
        if (r.ok) location.reload();
        else alert(r.message || 'Error');
    });

    document.querySelectorAll('.js-del-avail').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm('¿Eliminar esta ausencia?')) return;
            const r = await window.doctorApi('DELETE', '/portal-doctor/me/availability/' + btn.dataset.id);
            if (r.ok) btn.closest('li').remove();
            else alert(r.message || 'Error');
        });
    });
});
</script>
<?php doctor_layout_end();
