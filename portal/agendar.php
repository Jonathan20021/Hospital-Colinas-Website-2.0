<?php
require_once __DIR__ . '/_layout.php';
portal_require_login();

if (!portal_email_verified()) {
    $meRes = portal_api_call('GET', '/portal/me', [], portal_token());
    if ($meRes['ok'] && !empty($meRes['data']['email_verified_at'])) {
        portal_set_verified(true);
    }
}

$specRes = portal_api_call('GET', '/portal/specialties');
$specialties = is_array($specRes['data'] ?? null) ? $specRes['data'] : [];

$selectedSpec = (int)($_GET['specialty_id'] ?? 0);
$selectedDoc  = (int)($_GET['doctor_id'] ?? 0);

$doctors = [];
if ($selectedSpec) {
    $docRes = portal_api_call('GET', '/portal/doctors', ['specialty_id' => $selectedSpec]);
    $doctors = is_array($docRes['data'] ?? null) ? $docRes['data'] : [];
}

$message = null;
$errors  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    portal_csrf_check();
    $res = portal_api_call('POST', '/portal/me/appointments', [
        'doctor_id'        => (int)$_POST['doctor_id'],
        'appointment_time' => trim((string)$_POST['appointment_time']),
        'notes'            => trim((string)($_POST['notes'] ?? '')),
    ], portal_token());

    if ($res['ok']) {
        portal_flash_set('success', '¡Cita agendada! Revisa el detalle en "Mis citas".');
        header('Location: ' . base_url('portal/mis-citas.php?status=scheduled'));
        exit;
    }
    $message = $res['message'] ?? 'No se pudo agendar la cita.';
    $errors  = $res['errors'];
}

portal_layout_begin('Agendar cita', 'agendar');
?>
<header class="portal-header">
    <div><p class="section-label">Nueva cita</p><h1>Agendar consulta</h1></div>
</header>

<?php if (!portal_email_verified()): ?>
    <div class="portal-banner portal-banner-warning">
        <i data-lucide="mail-warning" class="h-5 w-5"></i>
        <div>
            <strong>Verifica tu correo primero.</strong>
            <p>Necesitas confirmar tu email antes de poder agendar citas.</p>
        </div>
    </div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="portal-flash portal-flash-error"><i data-lucide="alert-circle" class="h-4 w-4"></i><span><?= e($message) ?></span></div>
<?php endif; ?>
<?= portal_render_errors($errors) ?>

<ol class="portal-steps">
    <li class="<?= !$selectedSpec ? 'is-current' : ($selectedDoc ? 'is-done' : 'is-done') ?>">
        <span>1.</span> Especialidad
    </li>
    <li class="<?= $selectedSpec && !$selectedDoc ? 'is-current' : ($selectedDoc ? 'is-done' : '') ?>">
        <span>2.</span> Médico
    </li>
    <li class="<?= $selectedDoc ? 'is-current' : '' ?>">
        <span>3.</span> Fecha y confirmación
    </li>
</ol>

<?php if (!$selectedSpec): ?>
    <!-- Paso 1: especialidad -->
    <form method="GET" class="portal-card">
        <label class="form-label" for="specialty_id">Selecciona la especialidad</label>
        <select name="specialty_id" id="specialty_id" class="form-input" required>
            <option value="">— Elige una especialidad —</option>
            <?php foreach ($specialties as $s): ?>
                <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-green mt-4">Continuar</button>
    </form>

<?php elseif (!$selectedDoc): ?>
    <!-- Paso 2: médico -->
    <div class="portal-card">
        <h2 class="portal-section-title">Médicos disponibles</h2>
        <?php if (!$doctors): ?>
            <div class="portal-empty">
                <i data-lucide="user-round-x" class="h-10 w-10"></i>
                <p>No hay médicos registrados para esa especialidad.</p>
                <a href="?" class="portal-text-link">Elegir otra especialidad</a>
            </div>
        <?php else: ?>
            <div class="portal-doctors">
                <?php foreach ($doctors as $d): ?>
                    <article class="portal-doctor">
                        <div class="portal-doctor-icon"><i data-lucide="user-round" class="h-6 w-6"></i></div>
                        <div>
                            <h3><?= e($d['name']) ?></h3>
                            <p><i data-lucide="stethoscope" class="h-3.5 w-3.5"></i> <?= e($d['specialty']) ?></p>
                            <?php if (!empty($d['office_name'])): ?>
                                <p><i data-lucide="map-pin" class="h-3.5 w-3.5"></i> <?= e($d['office_name']) ?></p>
                            <?php endif; ?>
                            <p class="portal-hint">Horario: <?= e(substr($d['schedule_start'], 0, 5)) ?>–<?= e(substr($d['schedule_end'], 0, 5)) ?></p>
                        </div>
                        <a href="?specialty_id=<?= $selectedSpec ?>&doctor_id=<?= (int)$d['id'] ?>" class="btn btn-green">Ver fechas →</a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <a href="?" class="portal-text-link mt-4 block">← Cambiar especialidad</a>
    </div>

<?php else:
    // Buscar info del medico seleccionado en la lista ya cargada
    $selectedDoctor = null;
    foreach ($doctors as $d) {
        if ((int)$d['id'] === $selectedDoc) { $selectedDoctor = $d; break; }
    }
?>
    <!-- Paso 3: medico + calendario + confirmar -->
    <?php if ($selectedDoctor): ?>
        <div class="portal-card portal-doctor-summary">
            <div class="portal-doctor-icon"><i data-lucide="user-round" class="h-7 w-7"></i></div>
            <div>
                <p class="section-label">Agendando con</p>
                <h2><?= e($selectedDoctor['name']) ?></h2>
                <p class="portal-hint"><i data-lucide="stethoscope" class="h-3.5 w-3.5"></i> <?= e($selectedDoctor['specialty']) ?>
                    <?php if (!empty($selectedDoctor['office_name'])): ?>
                        · <i data-lucide="map-pin" class="h-3.5 w-3.5"></i> <?= e($selectedDoctor['office_name']) ?>
                    <?php endif; ?>
                </p>
            </div>
            <a href="?specialty_id=<?= $selectedSpec ?>" class="portal-text-link portal-change-link">Cambiar médico</a>
        </div>
    <?php endif; ?>

    <div class="portal-card" data-doctor-id="<?= $selectedDoc ?>">
        <h2 class="portal-section-title">Selecciona fecha y hora</h2>

        <div class="portal-slot-loader">
            <i data-lucide="loader-2" class="h-5 w-5 animate-spin"></i> Cargando horarios disponibles…
        </div>

        <div id="slot-picker" class="portal-slot-picker hidden"></div>

        <form method="POST" class="mt-6 hidden" id="confirm-form">
            <input type="hidden" name="_csrf" value="<?= e(portal_csrf_token()) ?>">
            <input type="hidden" name="doctor_id" value="<?= $selectedDoc ?>">
            <input type="hidden" name="appointment_time" id="appointment_time">

            <div class="portal-confirm-box">
                <p>Cita seleccionada:</p>
                <h3 id="confirm-when">—</h3>
            </div>

            <label class="form-label mt-4" for="notes">Motivo o detalles (opcional)</label>
            <textarea name="notes" id="notes" rows="3" class="form-input" placeholder="Cuéntale al médico el motivo de la consulta"></textarea>

            <div class="mt-4 flex gap-3 items-center">
                <button type="submit" class="btn btn-green" <?= portal_email_verified() ? '' : 'disabled' ?>>
                    <i data-lucide="check" class="h-4 w-4"></i> Confirmar cita
                </button>
                <a href="?specialty_id=<?= $selectedSpec ?>" class="portal-text-link">← Cambiar médico</a>
            </div>
        </form>
    </div>

    <script>window.PORTAL_DOCTOR_ID = <?= $selectedDoc ?>;</script>
<?php endif; ?>
<?php portal_layout_end();
