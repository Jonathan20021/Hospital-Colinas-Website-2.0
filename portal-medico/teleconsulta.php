<?php
require_once __DIR__ . '/_layout.php';
doctor_require_login();

$apptId = (int)($_GET['appt'] ?? 0);
$tele = null; $err = null;
if (!DOCTOR_TELECONSULT_ENABLED) {
    // Función deshabilitada por ahora (ver flag en _layout.php).
    $err = 'La teleconsulta estará disponible próximamente.';
} elseif ($apptId) {
    $r = portal_api_call('POST', '/portal-doctor/me/teleconsult/' . $apptId, [], doctor_token());
    if ($r['ok']) $tele = $r['data'];
    else $err = $r['message'] ?? 'No se pudo iniciar la teleconsulta.';
} else {
    $err = 'Abre la teleconsulta desde una cita.';
}
$waMsg = $tele ? rawurlencode('Hola ' . ($tele['patient_name'] ?? '') . ', te comparto el enlace para tu teleconsulta con el Hospital General Las Colinas. Ábrelo a la hora de tu cita: ' . ($tele['join_url'] ?? '')) : '';

doctor_layout_begin('Teleconsulta', 'agenda');
?>
<link rel="stylesheet" href="<?= e(base_url('assets/css/teleconsulta.css')) ?>?v=<?= @filemtime(__DIR__ . '/../assets/css/teleconsulta.css') ?>">

<?php if ($err): ?>
    <a href="<?= e(base_url('portal-medico/agenda.php')) ?>" class="doctor-back-link"><i data-lucide="arrow-left" class="h-4 w-4"></i> Volver a la agenda</a>
    <div class="doctor-flash doctor-flash-error"><i data-lucide="alert-circle" class="h-4 w-4"></i><span><?= e($err) ?></span></div>
<?php else: ?>

<div class="tele-shell">
    <!-- Panel de video -->
    <section class="tele-vpane">
        <div class="tele-vhead">
            <div class="tele-vhead-info">
                <span class="tele-live"><span class="tele-live-dot"></span> En vivo</span>
                <strong><?= e($tele['patient_name'] ?? 'Teleconsulta') ?></strong>
            </div>
            <div class="tele-vhead-actions">
                <a href="<?= e(base_url('portal-medico/consulta.php?appt=' . $apptId)) ?>" class="doctor-btn doctor-btn-ghost" title="Salir"><i data-lucide="arrow-left" class="h-4 w-4"></i></a>
                <button type="button" class="doctor-btn doctor-btn-outline" id="tele-invite-btn"><i data-lucide="user-plus" class="h-4 w-4"></i> Invitar paciente</button>
            </div>
        </div>

        <div id="tele-invite" class="tele-invite" hidden>
            <input type="text" id="tele-join-url" class="doctor-input" readonly value="<?= e($tele['join_url'] ?? '') ?>">
            <button type="button" class="doctor-btn doctor-btn-outline" id="tele-copy"><i data-lucide="copy" class="h-4 w-4"></i> Copiar</button>
            <a class="doctor-btn doctor-btn-primary" target="_blank" rel="noopener" href="https://wa.me/?text=<?= $waMsg ?>"><i data-lucide="message-circle" class="h-4 w-4"></i> WhatsApp</a>
        </div>

        <?php require __DIR__ . '/../includes/teleconsulta_stage.php'; ?>
    </section>

    <!-- Panel de nota clínica (en vivo, misma UI) -->
    <section class="tele-npane">
        <div class="tele-npane-head">
            <span><i data-lucide="file-edit" class="h-4 w-4"></i> Nota clínica</span>
            <a href="<?= e(base_url('portal-medico/consulta.php?appt=' . $apptId)) ?>" target="_blank" rel="noopener" class="doctor-text-link" title="Abrir en pestaña aparte"><i data-lucide="external-link" class="h-3.5 w-3.5"></i></a>
        </div>
        <iframe class="tele-noteframe" src="<?= e(base_url('portal-medico/consulta.php?appt=' . $apptId . '&bare=1')) ?>" title="Nota clínica" allow="clipboard-write"></iframe>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/livekit-client@2/dist/livekit-client.umd.min.js"></script>
<script src="<?= e(base_url('assets/js/portal-medico-teleconsult.js')) ?>?v=<?= @filemtime(__DIR__ . '/../assets/js/portal-medico-teleconsult.js') ?>"></script>
<script>
    (function () {
        const ib = document.getElementById('tele-invite-btn'), inv = document.getElementById('tele-invite');
        ib && ib.addEventListener('click', () => { inv.hidden = !inv.hidden; });
        const cp = document.getElementById('tele-copy');
        cp && cp.addEventListener('click', () => {
            const i = document.getElementById('tele-join-url'); i.select(); navigator.clipboard && navigator.clipboard.writeText(i.value);
            const t = cp.innerHTML; cp.innerHTML = '✓ Copiado'; setTimeout(() => { cp.innerHTML = t; if (window.lucide) lucide.createIcons(); }, 1500);
        });
        HGLCTele.setup({
            url: <?= json_encode($tele['url'] ?? '', JSON_UNESCAPED_SLASHES) ?>,
            token: <?= json_encode($tele['token'] ?? '', JSON_UNESCAPED_SLASHES) ?>,
            role: 'doctor'
        });
    })();
</script>

<?php endif; ?>
<?php doctor_layout_end();
