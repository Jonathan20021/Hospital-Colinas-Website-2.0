<?php
require_once __DIR__ . '/_layout.php';
doctor_require_login();

$apptId = (int)($_GET['appt'] ?? 0);
$appt = null;

if ($apptId) {
    $r = portal_api_call('GET', '/portal-doctor/me/appointments/' . $apptId, [], doctor_token());
    if ($r['ok']) {
        $appt = $r['data'];
    } else {
        doctor_flash_set('error', $r['message'] ?? 'Cita no encontrada.');
    }
}

doctor_layout_begin('Consulta medica', 'consulta');
?>

<?php if (!$appt): ?>
    <header class="doctor-header">
        <div>
            <p class="doctor-eyebrow">Consulta</p>
            <h1>Selecciona una cita</h1>
            <p class="doctor-subtitle">Abre una cita desde tu agenda o el listado de pacientes para iniciar la consulta.</p>
        </div>
        <a href="<?= e(base_url('portal-medico/agenda.php')) ?>" class="doctor-btn doctor-btn-primary"><i data-lucide="calendar-days" class="h-4 w-4"></i> Ir a la agenda</a>
    </header>
<?php else:
    $ts = strtotime($appt['appointment_time']);
    $age = '';
    if (!empty($appt['patient_dob'])) {
        $age = (int)((new DateTime())->diff(new DateTime($appt['patient_dob']))->y);
    }
?>
    <a href="<?= e(base_url('portal-medico/agenda.php')) ?>" class="doctor-back-link"><i data-lucide="arrow-left" class="h-4 w-4"></i> Volver a la agenda</a>

    <header class="doctor-consult-header">
        <div>
            <p class="doctor-eyebrow">Consulta · <?= e(date('d M Y · H:i', $ts)) ?></p>
            <h1><?= e($appt['patient_name']) ?></h1>
            <div class="doctor-patient-meta">
                <?php if (!empty($appt['patient_cedula'])): ?><span><i data-lucide="id-card" class="h-3.5 w-3.5"></i> <?= e($appt['patient_cedula']) ?></span><?php endif; ?>
                <?php if (!empty($appt['patient_gender'])): ?><span><i data-lucide="user" class="h-3.5 w-3.5"></i> <?= e($appt['patient_gender']) ?></span><?php endif; ?>
                <?php if ($age !== ''): ?><span><i data-lucide="cake" class="h-3.5 w-3.5"></i> <?= e($age) ?> anios</span><?php endif; ?>
                <?php if (!empty($appt['patient_phone'])): ?><span><i data-lucide="phone" class="h-3.5 w-3.5"></i> <?= e($appt['patient_phone']) ?></span><?php endif; ?>
                <span class="doctor-pill doctor-pill-<?= e($appt['status']) ?>"><?= e($appt['status']) ?></span>
            </div>
        </div>
        <div class="doctor-consult-actions">
            <a href="<?= e(base_url('portal-medico/paciente.php?id=' . (int)$appt['patient_id'])) ?>" class="doctor-btn doctor-btn-outline">
                <i data-lucide="user" class="h-4 w-4"></i> Ver historial
            </a>
            <?php if ($appt['status'] === 'scheduled'): ?>
                <button type="button" class="doctor-btn doctor-btn-primary" id="btn-save-complete">
                    <i data-lucide="check-circle-2" class="h-4 w-4"></i> Guardar y completar
                </button>
            <?php endif; ?>
        </div>
    </header>

    <form id="consult-form" class="doctor-consult-form">
        <input type="hidden" name="appointment_id" value="<?= (int)$appt['id'] ?>">

        <section class="doctor-card">
            <header class="doctor-card-header"><h2><i data-lucide="message-square" class="h-5 w-5"></i> Motivo de consulta</h2></header>
            <div class="doctor-form-pad">
                <textarea name="chief_complaint" class="doctor-input" rows="2" placeholder="Sintoma principal o razon de la visita"><?= e($appt['chief_complaint'] ?? '') ?></textarea>
            </div>
        </section>

        <section class="doctor-card mt-4">
            <header class="doctor-card-header"><h2><i data-lucide="clipboard-list" class="h-5 w-5"></i> Diagnostico</h2></header>
            <div class="doctor-form-pad">
                <textarea name="diagnosis" class="doctor-input" rows="3" placeholder="Diagnostico clinico, codigos CIE, etc."><?= e($appt['diagnosis'] ?? '') ?></textarea>
            </div>
        </section>

        <section class="doctor-grid-2 mt-4">
            <div class="doctor-card">
                <header class="doctor-card-header"><h2><i data-lucide="pill" class="h-5 w-5"></i> Receta medica</h2></header>
                <div class="doctor-form-pad">
                    <textarea name="prescription" class="doctor-input" rows="6" placeholder="Medicamento - dosis - via - frecuencia - duracion"><?= e($appt['prescription'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="doctor-card">
                <header class="doctor-card-header"><h2><i data-lucide="flask-conical" class="h-5 w-5"></i> Laboratorios</h2></header>
                <div class="doctor-form-pad">
                    <textarea name="lab_orders" class="doctor-input" rows="6" placeholder="Pruebas de laboratorio solicitadas"><?= e($appt['lab_orders'] ?? '') ?></textarea>
                </div>
            </div>
        </section>

        <section class="doctor-grid-2 mt-4">
            <div class="doctor-card">
                <header class="doctor-card-header"><h2><i data-lucide="scan" class="h-5 w-5"></i> Imagenes</h2></header>
                <div class="doctor-form-pad">
                    <textarea name="imaging_orders" class="doctor-input" rows="4" placeholder="Radiografias, ecografias, RM, TAC..."><?= e($appt['imaging_orders'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="doctor-card">
                <header class="doctor-card-header"><h2><i data-lucide="syringe" class="h-5 w-5"></i> Procedimientos / interconsultas</h2></header>
                <div class="doctor-form-pad">
                    <textarea name="procedures" class="doctor-input" rows="4" placeholder="Procedimientos, referimientos, interconsultas"><?= e($appt['procedures'] ?? '') ?></textarea>
                </div>
            </div>
        </section>

        <section class="doctor-card mt-4">
            <header class="doctor-card-header"><h2><i data-lucide="file-text" class="h-5 w-5"></i> Notas adicionales</h2></header>
            <div class="doctor-form-pad">
                <textarea name="notes" class="doctor-input" rows="3" placeholder="Observaciones, plan, evolucion..."><?= e($appt['note_notes'] ?? '') ?></textarea>
            </div>
        </section>

    </form>

    <div class="doctor-sticky-save">
        <span id="save-status" class="doctor-save-status"></span>
        <div class="doctor-consult-actions">
            <button type="button" class="doctor-btn doctor-btn-outline" id="btn-save">
                <i data-lucide="save" class="h-4 w-4"></i> Guardar nota
            </button>
            <?php if ($appt['status'] === 'scheduled'): ?>
                <button type="button" class="doctor-btn doctor-btn-primary" id="btn-save-complete-bottom">
                    <i data-lucide="check-circle-2" class="h-4 w-4"></i> Guardar y completar cita
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($appt['note_id'])): ?>
        <section class="doctor-card mt-4">
            <header class="doctor-card-header">
                <h2><i data-lucide="file-output" class="h-5 w-5"></i> Imprimir documentos</h2>
                <span class="doctor-text-link" title="Color del PDF">
                    <label><input type="radio" name="pdf-theme" value="bw" checked> Blanco y negro</label>
                    <label class="ml-3"><input type="radio" name="pdf-theme" value="color"> Color</label>
                </span>
            </header>
            <div class="doctor-form-pad doctor-pdf-grid">
                <a class="doctor-pdf-card js-pdf-link" data-type="rx" target="_blank" href="<?= e(base_url('portal-medico/documento.php?appt=' . (int)$appt['id'] . '&type=rx')) ?>">
                    <i data-lucide="pill" class="h-6 w-6"></i>
                    <div><strong>Receta medica</strong><span>Prescripcion del medicamento</span></div>
                </a>
                <a class="doctor-pdf-card js-pdf-link" data-type="diagnosis_lab" target="_blank" href="<?= e(base_url('portal-medico/documento.php?appt=' . (int)$appt['id'] . '&type=diagnosis_lab')) ?>">
                    <i data-lucide="clipboard-list" class="h-6 w-6"></i>
                    <div><strong>Diagnostico + Laboratorios</strong><span>Orden de pruebas</span></div>
                </a>
                <a class="doctor-pdf-card js-pdf-link" data-type="lab" target="_blank" href="<?= e(base_url('portal-medico/documento.php?appt=' . (int)$appt['id'] . '&type=lab')) ?>">
                    <i data-lucide="scan" class="h-6 w-6"></i>
                    <div><strong>Imagenes</strong><span>Rx, eco, RM, TAC</span></div>
                </a>
                <a class="doctor-pdf-card js-pdf-link" data-type="procedures" target="_blank" href="<?= e(base_url('portal-medico/documento.php?appt=' . (int)$appt['id'] . '&type=procedures')) ?>">
                    <i data-lucide="syringe" class="h-6 w-6"></i>
                    <div><strong>Procedimientos</strong><span>Interconsultas / referimientos</span></div>
                </a>
                <a class="doctor-pdf-card" target="_blank" href="<?= e(base_url('portal-medico/documento.php?appt=' . (int)$appt['id'] . '&type=constancia')) ?>">
                    <i data-lucide="file-check-2" class="h-6 w-6"></i>
                    <div><strong>Constancia medica</strong><span>Comprobante completo de consulta</span></div>
                </a>
            </div>
        </section>
        <script>
        document.querySelectorAll('input[name="pdf-theme"]').forEach(r => {
            r.addEventListener('change', () => {
                const theme = document.querySelector('input[name="pdf-theme"]:checked').value;
                document.querySelectorAll('.js-pdf-link').forEach(a => {
                    const url = new URL(a.href, location.origin);
                    url.searchParams.set('theme', theme);
                    a.href = url.toString();
                });
            });
        });
        </script>
    <?php endif; ?>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('consult-form');
    if (!form) return;
    const status = document.getElementById('save-status');

    async function save(andComplete) {
        const fd = new FormData(form);
        const data = {};
        fd.forEach((v, k) => { data[k] = v; });

        window.doctorAutoSaveHint(status, 'saving');
        const r = await window.doctorApi('POST', '/portal-doctor/me/notes', data);
        if (!r.ok) {
            window.doctorAutoSaveHint(status, 'error');
            alert(r.message || 'Error al guardar.');
            return;
        }
        if (andComplete) {
            const c = await window.doctorApi('POST', '/portal-doctor/me/appointments/<?= (int)($appt['id'] ?? 0) ?>/complete', {});
            if (!c.ok) {
                alert(c.message || 'Nota guardada, pero no se pudo completar la cita.');
                return;
            }
            location.reload();
        } else {
            window.doctorAutoSaveHint(status, 'saved');
        }
    }

    document.getElementById('btn-save')?.addEventListener('click', () => save(false));
    document.getElementById('btn-save-complete')?.addEventListener('click', () => save(true));
    document.getElementById('btn-save-complete-bottom')?.addEventListener('click', () => save(true));
});
</script>
<?php doctor_layout_end();
