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

// Iniciales + gradiente hash deterministico
$initials = doctor_initials($patient['name'] ?? '');
[$avc1, $avc2] = doctor_avatar_palette($patient['name'] ?? '');

// Edad
$age = '';
if (!empty($patient['dob'])) {
    $age = (int)((new DateTime())->diff(new DateTime($patient['dob']))->y);
}

doctor_layout_begin('Paciente: ' . ($patient['name'] ?? ''), 'pacientes');
?>
<a href="<?= e(base_url('portal-medico/pacientes.php')) ?>" class="doctor-back-link"><i data-lucide="arrow-left" class="h-4 w-4"></i> Volver a pacientes</a>

<header class="doctor-patient-header">
    <div class="doctor-av doctor-av-xl" style="background: linear-gradient(135deg, <?= e($avc1) ?>, <?= e($avc2) ?>);"><?= e($initials) ?></div>
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
                <span><i data-lucide="cake" class="h-3.5 w-3.5"></i> <?= e($age) ?> años</span>
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
        <a href="<?= e(base_url('portal-medico/historial.php?id=' . (int)$id . '&download=1')) ?>" class="doctor-btn doctor-btn-outline" target="_blank" rel="noopener">
            <i data-lucide="file-down" class="h-4 w-4"></i> Descargar historial
        </a>
        <button type="button" class="doctor-btn doctor-btn-outline" id="btn-edit-patient">
            <i data-lucide="user-cog" class="h-4 w-4"></i> Editar datos
        </button>
    </div>
</header>

<?php
$pAllergies  = $patient['allergies'] ?? [];
$pConditions = $patient['conditions'] ?? [];
$pBlood      = $patient['blood_type'] ?? '';
$pVitals     = $patient['vitals'] ?? [];
$bloodTypes  = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
$sevCls      = ['severa' => 'sev-high', 'moderada' => 'sev-mid', 'leve' => 'sev-low'];
?>
<section class="doctor-card mt-4 doctor-safety-card<?= $pAllergies ? ' has-allergy' : '' ?>" id="safety-card">
    <header class="doctor-card-header">
        <h2><i data-lucide="shield-alert" class="h-5 w-5"></i> Seguridad clínica</h2>
        <span class="doctor-blood-field">
            <label for="blood-type">Tipo de sangre</label>
            <select id="blood-type" class="doctor-input">
                <option value="">—</option>
                <?php foreach ($bloodTypes as $bt): ?><option value="<?= $bt ?>" <?= $pBlood === $bt ? 'selected' : '' ?>><?= $bt ?></option><?php endforeach; ?>
            </select>
        </span>
    </header>
    <div class="doctor-form-pad doctor-safety-grid">
        <div class="doctor-safety-col">
            <h3 class="doctor-safety-h"><i data-lucide="alert-triangle" class="h-4 w-4"></i> Alergias</h3>
            <div class="doctor-chip-list" id="allergy-list">
                <?php if (!$pAllergies): ?><span class="doctor-chip-empty">Sin alergias registradas</span><?php endif; ?>
                <?php foreach ($pAllergies as $a): ?>
                    <span class="doctor-chip <?= $sevCls[$a['severity']] ?? 'sev-mid' ?>" title="<?= e(ucfirst($a['severity']) . ($a['reaction'] ? ' · ' . $a['reaction'] : '')) ?>"><?= e($a['allergen']) ?><button type="button" class="doctor-chip-x" data-del-allergy="<?= (int)$a['id'] ?>" aria-label="Eliminar">&times;</button></span>
                <?php endforeach; ?>
            </div>
            <form class="doctor-chip-add" id="allergy-form">
                <input type="text" class="doctor-input" id="al-name" placeholder="Alérgeno (p.ej. Penicilina)" maxlength="160" required>
                <select class="doctor-input" id="al-sev"><option value="leve">Leve</option><option value="moderada" selected>Moderada</option><option value="severa">Severa</option></select>
                <input type="text" class="doctor-input" id="al-reaction" placeholder="Reacción (opcional)" maxlength="255">
                <button type="submit" class="doctor-btn doctor-btn-primary" aria-label="Añadir alergia"><i data-lucide="plus" class="h-4 w-4"></i></button>
            </form>
        </div>
        <div class="doctor-safety-col">
            <h3 class="doctor-safety-h"><i data-lucide="clipboard-list" class="h-4 w-4"></i> Antecedentes</h3>
            <div class="doctor-chip-list" id="cond-list">
                <?php if (!$pConditions): ?><span class="doctor-chip-empty">Sin antecedentes registrados</span><?php endif; ?>
                <?php foreach ($pConditions as $c): ?>
                    <span class="doctor-chip sev-cond" title="<?= e($c['note'] ?? '') ?>"><?= e($c['name']) ?><button type="button" class="doctor-chip-x" data-del-cond="<?= (int)$c['id'] ?>" aria-label="Eliminar">&times;</button></span>
                <?php endforeach; ?>
            </div>
            <form class="doctor-chip-add" id="cond-form">
                <input type="text" class="doctor-input" id="co-name" placeholder="Antecedente (p.ej. Hipertensión)" maxlength="160" required>
                <input type="text" class="doctor-input" id="co-note" placeholder="Nota (opcional)" maxlength="255">
                <button type="submit" class="doctor-btn doctor-btn-primary" aria-label="Añadir antecedente"><i data-lucide="plus" class="h-4 w-4"></i></button>
            </form>
        </div>
    </div>
</section>

<section class="doctor-card mt-4">
    <header class="doctor-card-header">
        <h2><i data-lucide="activity" class="h-5 w-5"></i> Signos vitales</h2>
        <?php if ($pVitals): ?><span class="doctor-text-soft"><?= count($pVitals) ?> registro<?= count($pVitals) === 1 ? '' : 's' ?></span><?php endif; ?>
    </header>
    <?php if (!$pVitals): ?>
        <div class="doctor-empty">
            <div class="doctor-empty-illustration"><i data-lucide="activity" class="h-7 w-7"></i></div>
            <p class="doctor-empty-title">Sin signos vitales</p>
            <p>Se registran automáticamente al guardar la consulta.</p>
        </div>
    <?php else: $last = $pVitals[0]; ?>
        <div class="doctor-form-pad">
            <div class="doctor-vitals-latest">
                <?php
                $vCards = [
                    ['T/A', ($last['systolic'] ?? '—') . '/' . ($last['diastolic'] ?? '—'), 'mmHg', 'heart-pulse'],
                    ['FC', $last['heart_rate'] ?? '—', 'lpm', 'activity'],
                    ['FR', $last['resp_rate'] ?? '—', 'rpm', 'wind'],
                    ['Temp', $last['temperature'] ?? '—', '°C', 'thermometer'],
                    ['SatO₂', $last['spo2'] ?? '—', '%', 'droplets'],
                    ['Peso', $last['weight_kg'] ?? '—', 'kg', 'scale'],
                    ['IMC', $last['bmi'] ?? '—', '', 'gauge'],
                ];
                foreach ($vCards as [$k, $val, $u, $ic]): ?>
                    <div class="doctor-vital"><span class="doctor-vital-k"><i data-lucide="<?= $ic ?>"></i> <?= $k ?></span><span class="doctor-vital-v"><?= e($val) ?><?php if ($u): ?> <i><?= $u ?></i><?php endif; ?></span></div>
                <?php endforeach; ?>
            </div>
            <p class="doctor-text-soft" style="margin:12px 2px 0">Última toma: <?= e(doctor_fecha_corta(strtotime($last['recorded_at']), true)) ?></p>
            <?php if (count($pVitals) > 1): ?>
                <div style="overflow-x:auto">
                    <table class="doctor-table doctor-vitals-trend">
                        <thead><tr><th>Fecha</th><th>T/A</th><th>FC</th><th>FR</th><th>Temp</th><th>SatO₂</th><th>Peso</th><th>IMC</th></tr></thead>
                        <tbody>
                            <?php foreach ($pVitals as $vv): ?>
                                <tr><td><?= e(doctor_fecha_corta(strtotime($vv['recorded_at']))) ?></td><td><?= e(($vv['systolic'] ?? '—') . '/' . ($vv['diastolic'] ?? '—')) ?></td><td><?= e($vv['heart_rate'] ?? '—') ?></td><td><?= e($vv['resp_rate'] ?? '—') ?></td><td><?= e($vv['temperature'] ?? '—') ?></td><td><?= e($vv['spo2'] ?? '—') ?></td><td><?= e($vv['weight_kg'] ?? '—') ?></td><td><?= e($vv['bmi'] ?? '—') ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<section class="doctor-card mt-4" id="imaging-card">
    <header class="doctor-card-header">
        <h2><i data-lucide="scan" class="h-5 w-5"></i> Estudios de imagen</h2>
        <span class="doctor-text-soft" id="imaging-count"></span>
    </header>
    <div class="doctor-form-pad">
        <p class="doctor-text-soft" id="imaging-loading">Buscando estudios en el PACS…</p>
        <div id="imaging-list" class="doctor-img-list"></div>
    </div>
</section>

<style>
.doctor-img-list{display:flex;flex-direction:column;gap:10px}
.doctor-img-row{display:flex;align-items:center;gap:14px;padding:12px 14px;border:1px solid #e6e8f0;border-radius:12px;background:#fff}
.doctor-img-ico{flex:none;width:54px;height:54px;border-radius:10px;background:#0e1320;color:#cdd4e6;display:grid;place-items:center;font-weight:800;font-size:.78rem;letter-spacing:.04em;text-align:center}
.doctor-img-meta{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}
.doctor-img-meta strong{color:#1e2540;font-size:.95rem}
.doctor-img-meta span{color:#64748b;font-size:.82rem}
@media(max-width:560px){.doctor-img-row{flex-wrap:wrap}}
</style>

<script>
(function () {
    const pid = <?= (int)$id ?>;
    const viewerBase = <?= json_encode(base_url('portal-medico/visor-imagen'), JSON_UNESCAPED_SLASHES) ?>;
    const listEl = document.getElementById('imaging-list');
    const loadEl = document.getElementById('imaging-loading');
    const cntEl  = document.getElementById('imaging-count');
    const escI = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fdate = d => (d && d.length === 8) ? (d.slice(6, 8) + '/' + d.slice(4, 6) + '/' + d.slice(0, 4)) : (d || '');
    (async function () {
        let r;
        try { r = await window.doctorApi('GET', '/portal-doctor/me/patients/' + pid + '/imaging'); }
        catch (e) { r = { ok: false }; }
        if (loadEl) loadEl.style.display = 'none';
        if (!r || !r.ok) { listEl.innerHTML = '<p class="doctor-text-soft">No se pudo consultar el PACS.</p>'; return; }
        const studies = (r.data && r.data.studies) || [];
        const scope = (r.data && r.data.scope) || '';
        if (cntEl) cntEl.textContent = studies.length ? (studies.length + ' estudio(s)') : '';
        if (!studies.length) {
            listEl.innerHTML = '<div class="doctor-empty"><div class="doctor-empty-illustration"><i data-lucide="scan-line" class="h-7 w-7"></i></div><p class="doctor-empty-title">Sin estudios de imagen</p><p>No se encontraron estudios en el PACS para la cédula de este paciente.</p></div>';
            if (window.lucide) lucide.createIcons(); return;
        }
        listEl.innerHTML = studies.map(function (s) {
            const url = viewerBase + '?study=' + encodeURIComponent(s.studyUID) + '&scope=' + encodeURIComponent(scope);
            return '<div class="doctor-img-row">'
                + '<div class="doctor-img-ico">' + escI(s.modality || '—') + '</div>'
                + '<div class="doctor-img-meta"><strong>' + escI(s.description || 'Estudio') + '</strong>'
                + '<span>' + fdate(s.date) + ' · ' + (s.instances || 0) + ' imágenes · ' + (s.series || 0) + ' serie(s)</span></div>'
                + '<a class="doctor-btn doctor-btn-primary" target="_blank" rel="noopener" href="' + url + '"><i data-lucide="eye" class="h-4 w-4"></i> Abrir visor</a>'
                + '</div>';
        }).join('');
        if (window.lucide) lucide.createIcons();
    })();
})();
</script>

<script>
(function () {
    const pid = <?= (int)$id ?>;
    const sevCls = { severa: 'sev-high', moderada: 'sev-mid', leve: 'sev-low' };
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function renderAllergies(list) {
        const el = document.getElementById('allergy-list');
        const card = document.getElementById('safety-card');
        if (!list || !list.length) { el.innerHTML = '<span class="doctor-chip-empty">Sin alergias registradas</span>'; card.classList.remove('has-allergy'); return; }
        el.innerHTML = list.map(a => `<span class="doctor-chip ${sevCls[a.severity] || 'sev-mid'}" title="${esc((a.severity || '') + (a.reaction ? ' · ' + a.reaction : ''))}">${esc(a.allergen)}<button type="button" class="doctor-chip-x" data-del-allergy="${a.id}" aria-label="Eliminar">&times;</button></span>`).join('');
        card.classList.add('has-allergy');
    }
    function renderConds(list) {
        const el = document.getElementById('cond-list');
        if (!list || !list.length) { el.innerHTML = '<span class="doctor-chip-empty">Sin antecedentes registrados</span>'; return; }
        el.innerHTML = list.map(c => `<span class="doctor-chip sev-cond" title="${esc(c.note || '')}">${esc(c.name)}<button type="button" class="doctor-chip-x" data-del-cond="${c.id}" aria-label="Eliminar">&times;</button></span>`).join('');
    }

    document.getElementById('allergy-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const allergen = document.getElementById('al-name').value.trim(); if (!allergen) return;
        const severity = document.getElementById('al-sev').value;
        const reaction = document.getElementById('al-reaction').value.trim();
        const r = await window.doctorApi('POST', '/portal-doctor/me/patients/' + pid + '/allergies', { allergen, severity, reaction });
        if (r.ok && r.data) { renderAllergies(r.data.allergies || []); e.target.reset(); document.getElementById('al-sev').value = 'moderada'; }
        else alert(r.message || 'No se pudo registrar la alergia.');
    });
    document.getElementById('cond-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const name = document.getElementById('co-name').value.trim(); if (!name) return;
        const note = document.getElementById('co-note').value.trim();
        const r = await window.doctorApi('POST', '/portal-doctor/me/patients/' + pid + '/conditions', { name, note });
        if (r.ok && r.data) { renderConds(r.data.conditions || []); e.target.reset(); }
        else alert(r.message || 'No se pudo registrar el antecedente.');
    });
    document.addEventListener('click', async e => {
        const da = e.target.closest('[data-del-allergy]');
        if (da) { if (!confirm('¿Eliminar esta alergia?')) return; const r = await window.doctorApi('DELETE', '/portal-doctor/me/patients/' + pid + '/allergies/' + da.dataset.delAllergy); if (r.ok && r.data) renderAllergies(r.data.allergies || []); return; }
        const dc = e.target.closest('[data-del-cond]');
        if (dc) { if (!confirm('¿Eliminar este antecedente?')) return; const r = await window.doctorApi('DELETE', '/portal-doctor/me/patients/' + pid + '/conditions/' + dc.dataset.delCond); if (r.ok && r.data) renderConds(r.data.conditions || []); return; }
    });
    document.getElementById('blood-type')?.addEventListener('change', async e => {
        const r = await window.doctorApi('PUT', '/portal-doctor/me/patients/' + pid, { blood_type: e.target.value });
        if (!r.ok) alert(r.message || 'No se pudo guardar el tipo de sangre.');
    });
})();
</script>

<section class="doctor-grid-2 mt-4">
    <div class="doctor-card">
        <header class="doctor-card-header">
            <h2><i data-lucide="contact" class="h-5 w-5"></i> Datos demográficos</h2>
        </header>
        <dl class="doctor-dl-grid">
            <div><dt>Email</dt><dd><?= e($patient['email'] ?? '—') ?></dd></div>
            <div><dt>Fecha de nacimiento</dt><dd><?= e($patient['dob'] ?? '—') ?></dd></div>
            <div><dt>Dirección</dt><dd><?= e($patient['address'] ?? '—') ?></dd></div>
            <div><dt>Provincia</dt><dd><?= e($patient['province'] ?? '—') ?></dd></div>
            <div><dt>Barrio</dt><dd><?= e($patient['neighborhood'] ?? '—') ?></dd></div>
            <div><dt>Póliza</dt><dd><?= e($patient['insurance_policy'] ?? '—') ?></dd></div>
        </dl>
    </div>

    <div class="doctor-card">
        <header class="doctor-card-header">
            <h2><i data-lucide="activity" class="h-5 w-5"></i> Resumen clínico</h2>
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
        <h2><i data-lucide="history" class="h-5 w-5"></i> Historial clínico</h2>
        <a href="<?= e(base_url('portal-medico/agenda.php')) ?>" class="doctor-text-link">Ver agenda completa →</a>
    </header>

    <?php if (!$history): ?>
        <div class="doctor-empty">
            <div class="doctor-empty-illustration">
                <i data-lucide="file-text" class="h-7 w-7"></i>
            </div>
            <p class="doctor-empty-title">Sin consultas registradas</p>
            <p>Las consultas y notas médicas aparecerán aquí cuando inicies la primera.</p>
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
                                <p class="doctor-timeline-date"><?= e(doctor_fecha_es($ts, true)) ?></p>
                                <p class="doctor-timeline-doc"><i data-lucide="stethoscope" class="h-3.5 w-3.5"></i> <?= e($h['doctor_name']) ?> · <?= e($h['specialty']) ?></p>
                            </div>
                            <span class="doctor-pill <?= e($statusClass) ?>"><?= e(doctor_estado_es($h['status'])) ?></span>
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
                                        <strong>Diagnóstico:</strong>
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
                                        <strong>Imágenes:</strong>
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
            <label>Cédula <input name="cedula" value="<?= e($patient['cedula'] ?? '') ?>" class="doctor-input"></label>
            <label>Teléfono <input name="phone" value="<?= e($patient['phone'] ?? '') ?>" class="doctor-input"></label>
            <label>Email <input name="email" value="<?= e($patient['email'] ?? '') ?>" class="doctor-input"></label>
            <label>Fecha de nacimiento <input name="dob" type="date" value="<?= e($patient['dob'] ?? '') ?>" class="doctor-input"></label>
            <label>Género
                <select name="gender" class="doctor-input">
                    <option value="">—</option>
                    <option value="Male" <?= ($patient['gender'] ?? '')==='Male'?'selected':'' ?>>Masculino</option>
                    <option value="Female" <?= ($patient['gender'] ?? '')==='Female'?'selected':'' ?>>Femenino</option>
                    <option value="Other" <?= ($patient['gender'] ?? '')==='Other'?'selected':'' ?>>Otro</option>
                </select>
            </label>
            <label class="doctor-form-full">Dirección <textarea name="address" rows="2" class="doctor-input"><?= e($patient['address'] ?? '') ?></textarea></label>
            <label>Seguro <input name="insurance_provider" value="<?= e($patient['insurance_provider'] ?? '') ?>" class="doctor-input"></label>
            <label>Póliza <input name="insurance_policy" value="<?= e($patient['insurance_policy'] ?? '') ?>" class="doctor-input"></label>
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
