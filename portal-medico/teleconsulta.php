<?php
require_once __DIR__ . '/_layout.php';
doctor_require_login();

$apptId = (int)($_GET['appt'] ?? 0);
$tele = null; $err = null;
if ($apptId) {
    $r = portal_api_call('POST', '/portal-doctor/me/teleconsult/' . $apptId, [], doctor_token());
    if ($r['ok']) $tele = $r['data'];
    else $err = $r['message'] ?? 'No se pudo iniciar la teleconsulta.';
} else {
    $err = 'Abre la teleconsulta desde una cita.';
}

$waMsg = '';
if ($tele) {
    $waMsg = rawurlencode('Hola ' . ($tele['patient_name'] ?? '') . ', te comparto el enlace para tu teleconsulta con el Hospital General Las Colinas. Ábrelo a la hora de tu cita: ' . ($tele['join_url'] ?? ''));
}

doctor_layout_begin('Teleconsulta', 'agenda');
?>
<link rel="stylesheet" href="<?= e(base_url('assets/css/teleconsulta.css')) ?>">

<a href="<?= e(base_url('portal-medico/consulta.php?appt=' . $apptId)) ?>" class="doctor-back-link"><i data-lucide="arrow-left" class="h-4 w-4"></i> Volver a la consulta</a>

<header class="doctor-header">
    <div>
        <p class="doctor-eyebrow">Teleconsulta</p>
        <h1><?= $tele ? e($tele['patient_name'] ?? 'Consulta virtual') : 'Teleconsulta' ?></h1>
        <p class="doctor-subtitle">Comparte el enlace con el paciente y atiende por video. La nota clínica está a un clic.</p>
    </div>
</header>

<?php if ($err): ?>
    <div class="doctor-flash doctor-flash-error"><i data-lucide="alert-circle" class="h-4 w-4"></i><span><?= e($err) ?></span></div>
<?php else: ?>

<section class="doctor-card" style="margin-bottom:16px">
    <div class="doctor-form-pad">
        <p class="doctor-label">Enlace para el paciente (envíalo por WhatsApp, SMS o correo)</p>
        <div class="tele-link-row">
            <input type="text" id="tele-join-url" class="doctor-input" readonly value="<?= e($tele['join_url'] ?? '') ?>">
            <button type="button" class="doctor-btn doctor-btn-outline" id="tele-copy"><i data-lucide="copy" class="h-4 w-4"></i> Copiar</button>
            <a class="doctor-btn doctor-btn-primary" target="_blank" rel="noopener" href="https://wa.me/?text=<?= $waMsg ?>"><i data-lucide="message-circle" class="h-4 w-4"></i> WhatsApp</a>
        </div>
        <p class="doctor-text-soft" style="margin:10px 2px 0"><i data-lucide="shield-check" class="h-3.5 w-3.5" style="vertical-align:-2px"></i> El enlace es único para esta cita y caduca en 12 horas. Video cifrado, sin grabación.</p>
    </div>
</section>

<?php require __DIR__ . '/../includes/teleconsulta_stage.php'; ?>

<div style="text-align:center;margin-top:16px">
    <a href="<?= e(base_url('portal-medico/consulta.php?appt=' . $apptId)) ?>" class="doctor-btn doctor-btn-outline"><i data-lucide="file-edit" class="h-4 w-4"></i> Abrir nota clínica</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/livekit-client@2/dist/livekit-client.umd.min.js"></script>
<script src="<?= e(base_url('assets/js/portal-medico-teleconsult.js')) ?>"></script>
<script>
    document.getElementById('tele-copy')?.addEventListener('click', () => {
        const i = document.getElementById('tele-join-url');
        i.select(); navigator.clipboard?.writeText(i.value);
        const b = document.getElementById('tele-copy'); const t = b.innerHTML;
        b.innerHTML = '✓ Copiado'; setTimeout(() => { b.innerHTML = t; if (window.lucide) lucide.createIcons(); }, 1500);
    });
    HGLCTele.setup({
        url: <?= json_encode($tele['url'] ?? '', JSON_UNESCAPED_SLASHES) ?>,
        token: <?= json_encode($tele['token'] ?? '', JSON_UNESCAPED_SLASHES) ?>,
        role: 'doctor'
    });
</script>

<?php endif; ?>
<?php doctor_layout_end();
