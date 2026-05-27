<?php
require_once __DIR__ . '/_layout.php';
doctor_require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . base_url('portal-medico/pacientes.php'));
    exit;
}

$pRes = portal_api_call('GET', '/portal-doctor/me/patients/' . $id, [], doctor_token());
if (!$pRes['ok']) {
    doctor_flash_set('error', $pRes['message'] ?? 'Paciente no encontrado.');
    header('Location: ' . base_url('portal-medico/pacientes.php'));
    exit;
}
$patient = $pRes['data'];

$hRes = portal_api_call('GET', '/portal-doctor/me/patients/' . $id . '/history', [], doctor_token());
$history = $hRes['data'] ?? [];

// Iniciales
$parts = preg_split('/\s+/', trim((string)($patient['name'] ?? ''))) ?: [];
$initials = '';
foreach ($parts as $p) { if ($p !== '' && strlen($initials) < 2) $initials .= mb_substr($p, 0, 1, 'UTF-8'); }
$initials = mb_strtoupper($initials ?: '?', 'UTF-8');

// Edad
$age = '';
if (!empty($patient['dob'])) {
    $age = (int)((new DateTime())->diff(new DateTime($patient['dob']))->y);
}

doctor_layout_begin('Paciente: ' . ($patient['name'] ?? ''), 'pacientes');
?>
<a href="<?= e(base_url('portal-medico/pacientes.php')) ?>" class="doctor-back-link"><i data-lucide="arrow-left" class="h-4 w-4"></i> Volver a pacientes</a>

<header class="doctor-patient-header">
    <div class="doctor-avatar doctor-avatar-lg"><?= e($initials) ?></div>
    <div class="doctor-patient-info">
        <h1><?= e($patient['name']) ?></h1>
        <div class="doctor-patient-meta">
            <?php if (!empty($patient['cedula'])): ?>
                <span><i data-lucide="id-card" class="h-3.5 w-3.5"></i> <?= e($patient['cedula']) ?></span>
            <?php endif; ?>
            <?php if (!empty($patient['gender'])): ?>
                <span><i data-lucide="user" class="h-3.5 w-3.5"></i> <?= e($patient['gender']) ?></span>
            <?php endif; ?>
            <?php if ($age !== ''): ?>
                <span><i data-lucide="cake" class="h-3.5 w-3.5"></i> <?= e($age) ?> anios</span>
            <?php endif; ?>
            <?php if (!empty($patient['phone'])): ?>
                <span><i data-lucide="phone" class="h-3.5 w-3.5"></i> <?= e($patient['phone']) ?></span>
            <?php endif; ?>
            <?php if (!empty($patient['insurance_provider'])): ?>
                <span><i data-lucide="shield" class="h-3.5 w-3.5"></i> <?= e($patient['insurance_provider']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="doctor-patient-actions">
        <button type="button" class="doctor-btn doctor-btn-outline" id="btn-edit-patient">
            <i data-lucide="user-cog" class="h-4 w-4"></i> Editar datos
        </button>
    </div>
</header>

<section class="doctor-grid-2 mt-4">
    <div class="doctor-card">
        <header class="doctor-card-header">
            <h2><i data-lucide="contact" class="h-5 w-5"></i> Datos demograficos</h2>
        </header>
        <dl class="doctor-dl-grid">
            <div><dt>Email</dt><dd><?= e($patient['email'] ?? '—') ?></dd></div>
            <div><dt>Fecha de nacimiento</dt><dd><?= e($patient['dob'] ?? '—') ?></dd></div>
            <div><dt>Direccion</dt><dd><?= e($patient['address'] ?? '—') ?></dd></div>
            <div><dt>Provincia</dt><dd><?= e($patient['province'] ?? '—') ?></dd></div>
            <div><dt>Barrio</dt><dd><?= e($patient['neighborhood'] ?? '—') ?></dd></div>
            <div><dt>Poliza</dt><dd><?= e($patient['insurance_policy'] ?? '—') ?></dd></div>
        </dl>
    </div>

    <div class="doctor-card">
        <header class="doctor-card-header">
            <h2><i data-lucide="activity" class="h-5 w-5"></i> Resumen clinico</h2>
        </header>
        <div class="doctor-summary">
            <div class="doctor-summary-stat">
                <span class="doctor-summary-k">Visitas totales</span>
                <span class="doctor-summary-v"><?= count($history) ?></span>
            </div>
            <?php
                $completed = 0; $pending = 0; $cancelled = 0;
                foreach ($history as $h) {
                    if ($h['status'] === 'completed') $completed++;
                    elseif ($h['status'] === 'cancelled') $cancelled++;
                    else $pending++;
                }
            ?>
            <div class="doctor-summary-stat"><span class="doctor-summary-k">Completadas</span><span class="doctor-summary-v"><?= $completed ?></span></div>
            <div class="doctor-summary-stat"><span class="doctor-summary-k">Pendientes</span><span class="doctor-summary-v"><?= $pending ?></span></div>
            <div class="doctor-summary-stat"><span class="doctor-summary-k">Canceladas</span><span class="doctor-summary-v"><?= $cancelled ?></span></div>
        </div>
    </div>
</section>

<section class="doctor-card mt-4">
    <header class="doctor-card-header">
        <h2><i data-lucide="history" class="h-5 w-5"></i> Historial clinico</h2>
        <a href="<?= e(base_url('portal-medico/agenda.php')) ?>" class="doctor-text-link">Ver agenda completa →</a>
    </header>

    <?php if (!$history): ?>
        <div class="doctor-empty">
            <div class="doctor-empty-illustration">
                <i data-lucide="file-text" class="h-7 w-7"></i>
            </div>
            <p class="doctor-empty-title">Sin consultas registradas</p>
            <p>Las consultas y notas medicas apareceran aqui cuando inicies la primera.</p>
        </div>
    <?php else: ?>
        <ol class="doctor-timeline">
            <?php foreach ($history as $h):
                $ts = strtotime($h['appointment_time']);
                $statusClass = 'doctor-pill-' . $h['status'];
            ?>
                <li class="doctor-timeline-item">
                    <div class="doctor-timeline-dot"></div>
                    <article class="doctor-timeline-card">
                        <header>
                            <div>
                                <p class="doctor-timeline-date"><?= e(date('l j \d\e F, Y · H:i', $ts)) ?></p>
                                <p class="doctor-timeline-doc"><i data-lucide="stethoscope" class="h-3.5 w-3.5"></i> <?= e($h['doctor_name']) ?> · <?= e($h['specialty']) ?></p>
                            </div>
                            <span class="doctor-pill <?= e($statusClass) ?>"><?= e($h['status']) ?></span>
                        </header>

                        <?php if ($h['chief_complaint'] || $h['diagnosis'] || $h['prescription'] || $h['lab_orders'] || $h['imaging_orders'] || $h['procedures'] || $h['note_notes']): ?>
                            <div class="doctor-timeline-body">
                                <?php if ($h['chief_complaint']): ?>
                                    <div class="doctor-timeline-row">
                                        <strong>Motivo:</strong>
                                        <p><?= nl2br(e($h['chief_complaint'])) ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if ($h['diagnosis']): ?>
                                    <div class="doctor-timeline-row">
                                        <strong>Diagnostico:</strong>
                                        <p><?= nl2br(e($h['diagnosis'])) ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if ($h['prescription']): ?>
                                    <div class="doctor-timeline-row">
                                        <strong>Receta:</strong>
                                        <p style="white-space:pre-wrap"><?= e($h['prescription']) ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if ($h['lab_orders']): ?>
                                    <div class="doctor-timeline-row">
                                        <strong>Laboratorios:</strong>
                                        <p style="white-space:pre-wrap"><?= e($h['lab_orders']) ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if ($h['imaging_orders']): ?>
                                    <div class="doctor-timeline-row">
                                        <strong>Imagenes:</strong>
                                        <p style="white-space:pre-wrap"><?= e($h['imaging_orders']) ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if ($h['procedures']): ?>
                                    <div class="doctor-timeline-row">
                                        <strong>Procedimientos:</strong>
                                        <p style="white-space:pre-wrap"><?= e($h['procedures']) ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if ($h['note_notes']): ?>
                                    <div class="doctor-timeline-row">
                                        <strong>Notas:</strong>
                                        <p style="white-space:pre-wrap"><?= e($h['note_notes']) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($h['status'] === 'scheduled'): ?>
                            <footer class="doctor-timeline-foot">
                                <a href="<?= e(base_url('portal-medico/consulta.php?appt=' . (int)$h['id'])) ?>" class="doctor-btn doctor-btn-primary">
                                    <i data-lucide="stethoscope" class="h-4 w-4"></i> Iniciar consulta
                                </a>
                            </footer>
                        <?php elseif ($h['status'] === 'completed'): ?>
                            <footer class="doctor-timeline-foot">
                                <a href="<?= e(base_url('portal-medico/consulta.php?appt=' . (int)$h['id'])) ?>" class="doctor-btn doctor-btn-outline">
                                    <i data-lucide="eye" class="h-4 w-4"></i> Ver consulta
                                </a>
                            </footer>
                        <?php endif; ?>
                    </article>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>

<!-- Modal edicion paciente -->
<div id="edit-modal" class="doctor-modal" hidden>
    <div class="doctor-modal-backdrop" data-close></div>
    <div class="doctor-modal-card">
        <header class="doctor-modal-header">
            <h3>Editar datos del paciente</h3>
            <button type="button" class="doctor-modal-close" data-close><i data-lucide="x" class="h-5 w-5"></i></button>
        </header>
        <form id="edit-form" class="doctor-modal-body doctor-form-grid">
            <label>Nombre <input name="name" value="<?= e($patient['name']) ?>" class="doctor-input"></label>
            <label>Cedula <input name="cedula" value="<?= e($patient['cedula'] ?? '') ?>" class="doctor-input"></label>
            <label>Telefono <input name="phone" value="<?= e($patient['phone'] ?? '') ?>" class="doctor-input"></label>
            <label>Email <input name="email" value="<?= e($patient['email'] ?? '') ?>" class="doctor-input"></label>
            <label>Fecha de nacimiento <input name="dob" type="date" value="<?= e($patient['dob'] ?? '') ?>" class="doctor-input"></label>
            <label>Genero
                <select name="gender" class="doctor-input">
                    <option value="">—</option>
                    <option value="Male" <?= ($patient['gender'] ?? '')==='Male'?'selected':'' ?>>Masculino</option>
                    <option value="Female" <?= ($patient['gender'] ?? '')==='Female'?'selected':'' ?>>Femenino</option>
                    <option value="Other" <?= ($patient['gender'] ?? '')==='Other'?'selected':'' ?>>Otro</option>
                </select>
            </label>
            <label class="doctor-form-full">Direccion <textarea name="address" rows="2" class="doctor-input"><?= e($patient['address'] ?? '') ?></textarea></label>
            <label>Seguro <input name="insurance_provider" value="<?= e($patient['insurance_provider'] ?? '') ?>" class="doctor-input"></label>
            <label>Poliza <input name="insurance_policy" value="<?= e($patient['insurance_policy'] ?? '') ?>" class="doctor-input"></label>
        </form>
        <footer class="doctor-modal-footer">
            <button type="button" class="doctor-btn doctor-btn-outline" data-close>Cancelar</button>
            <button type="button" class="doctor-btn doctor-btn-primary" id="btn-save-patient"><i data-lucide="save" class="h-4 w-4"></i> Guardar</button>
        </footer>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('edit-modal');
    document.getElementById('btn-edit-patient')?.addEventListener('click', () => modal.hidden = false);
    modal.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => modal.hidden = true));

    document.getElementById('btn-save-patient').addEventListener('click', async () => {
        const data = {};
        document.getElementById('edit-form').querySelectorAll('[name]').forEach(el => {
            data[el.name] = el.value || null;
        });
        const r = await window.doctorApi('PUT', '/portal-doctor/me/patients/<?= (int)$id ?>', data);
        if (r.ok) location.reload();
        else alert(r.message || 'Error al guardar.');
    });
});
</script>
<?php doctor_layout_end();
