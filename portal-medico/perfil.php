<?php
require_once __DIR__ . '/_layout.php';
doctor_require_login();

$res = portal_api_call('GET', '/portal-doctor/me', [], doctor_token());
$doctor = $res['data'] ?? [];

// Calcular porcentaje de completitud
$profileFields = [
    'phone'             => 'Telefono',
    'office_name'       => 'Consultorio',
    'consultation_cost' => 'Costo',
    'office_address'    => 'Direccion',
    'languages'         => 'Idiomas',
    'education'         => 'Educacion',
    'associations'      => 'Asociaciones',
    'services'          => 'Servicios',
    'insurances'        => 'Seguros',
    'biography'         => 'Biografia',
];
$filled = 0; $missing = [];
foreach ($profileFields as $k => $label) {
    $v = trim((string)($doctor[$k] ?? ''));
    if ($v !== '' && $v !== '0' && $v !== '0.00') $filled++;
    else $missing[] = $label;
}
$totalFields = count($profileFields);
$percent = $totalFields > 0 ? (int)round(($filled / $totalFields) * 100) : 0;
$ring_r = 28;
$ring_c = 2 * M_PI * $ring_r;
$ring_offset = $ring_c * (1 - ($percent / 100));

doctor_layout_begin('Mi perfil', 'perfil');
?>

<svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <defs>
        <linearGradient id="docGrad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#0d9488"/>
            <stop offset="100%" stop-color="#1e40af"/>
        </linearGradient>
    </defs>
</svg>
<header class="doctor-header">
    <div>
        <p class="doctor-eyebrow">Perfil</p>
        <h1>Mi informacion profesional</h1>
        <p class="doctor-subtitle">Esta informacion aparece en el directorio publico y en tus recetas.</p>
    </div>
</header>

<div class="doctor-completion">
    <div class="doctor-completion-ring">
        <svg width="64" height="64" viewBox="0 0 64 64">
            <circle class="bg" cx="32" cy="32" r="<?= $ring_r ?>"></circle>
            <circle class="fg" cx="32" cy="32" r="<?= $ring_r ?>"
                stroke-dasharray="<?= round($ring_c, 2) ?>"
                stroke-dashoffset="<?= round($ring_offset, 2) ?>"></circle>
        </svg>
        <span class="doctor-completion-pct"><?= $percent ?>%</span>
    </div>
    <div class="doctor-completion-text">
        <p class="doctor-completion-title">Perfil <?= $percent === 100 ? 'completo' : 'a ' . $percent . '%' ?></p>
        <p class="doctor-completion-hint">
            <?php if ($percent === 100): ?>
                Excelente — toda tu informacion esta actualizada.
            <?php elseif ($missing): ?>
                Completa los siguientes campos para mejorar tu presencia en el directorio:
            <?php endif; ?>
        </p>
        <?php if ($missing && $percent < 100): ?>
            <div class="doctor-completion-pills">
                <?php foreach (array_slice($missing, 0, 6) as $m): ?>
                    <span class="doctor-completion-pill"><?= e($m) ?></span>
                <?php endforeach; ?>
                <?php if (count($missing) > 6): ?>
                    <span class="doctor-completion-pill">+<?= count($missing) - 6 ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<form id="profile-form" class="doctor-grid-2">
    <div class="doctor-card">
        <header class="doctor-card-header"><h2><i data-lucide="user" class="h-5 w-5"></i> Datos personales</h2></header>
        <div class="doctor-form-pad doctor-form-grid">
            <label>Nombre <input class="doctor-input" value="<?= e($doctor['name'] ?? '') ?>" disabled></label>
            <label>Email <input class="doctor-input" value="<?= e($doctor['email'] ?? '') ?>" disabled></label>
            <label>Especialidad <input class="doctor-input" value="<?= e($doctor['specialty'] ?? '') ?>" disabled></label>
            <label>Telefono <input name="phone" class="doctor-input" value="<?= e($doctor['phone'] ?? '') ?>"></label>
            <label>Exequatur <input class="doctor-input" value="<?= e($doctor['exequatur'] ?? '—') ?>" disabled></label>
            <label>Numero de licencia <input class="doctor-input" value="<?= e($doctor['medical_license_number'] ?? '—') ?>" disabled></label>
        </div>
    </div>

    <div class="doctor-card">
        <header class="doctor-card-header"><h2><i data-lucide="building-2" class="h-5 w-5"></i> Consultorio</h2></header>
        <div class="doctor-form-pad doctor-form-grid">
            <label>Nombre del consultorio <input name="office_name" class="doctor-input" value="<?= e($doctor['office_name'] ?? '') ?>"></label>
            <label>Costo de consulta (DOP) <input name="consultation_cost" type="number" step="0.01" class="doctor-input" value="<?= e($doctor['consultation_cost'] ?? '') ?>"></label>
            <label class="doctor-form-full">Direccion <textarea name="office_address" rows="2" class="doctor-input"><?= e($doctor['office_address'] ?? '') ?></textarea></label>
            <label>Idiomas <input name="languages" class="doctor-input" value="<?= e($doctor['languages'] ?? '') ?>"></label>
        </div>
    </div>

    <div class="doctor-card">
        <header class="doctor-card-header"><h2><i data-lucide="graduation-cap" class="h-5 w-5"></i> Formacion</h2></header>
        <div class="doctor-form-pad doctor-form-grid">
            <label class="doctor-form-full">Educacion <textarea name="education" rows="3" class="doctor-input"><?= e($doctor['education'] ?? '') ?></textarea></label>
            <label class="doctor-form-full">Asociaciones medicas <textarea name="associations" rows="2" class="doctor-input"><?= e($doctor['associations'] ?? '') ?></textarea></label>
        </div>
    </div>

    <div class="doctor-card">
        <header class="doctor-card-header"><h2><i data-lucide="stethoscope" class="h-5 w-5"></i> Practica</h2></header>
        <div class="doctor-form-pad doctor-form-grid">
            <label class="doctor-form-full">Servicios que ofreces <textarea name="services" rows="2" class="doctor-input"><?= e($doctor['services'] ?? '') ?></textarea></label>
            <label class="doctor-form-full">Seguros aceptados <textarea name="insurances" rows="2" class="doctor-input"><?= e($doctor['insurances'] ?? '') ?></textarea></label>
            <label class="doctor-form-full">Biografia <textarea name="biography" rows="4" class="doctor-input" placeholder="Anios de experiencia, enfoque profesional, especializaciones..."><?= e($doctor['biography'] ?? '') ?></textarea></label>
        </div>
    </div>

    <div class="doctor-form-full doctor-sticky-save">
        <span id="save-status" class="doctor-save-status"></span>
        <button type="submit" class="doctor-btn doctor-btn-primary">
            <i data-lucide="save" class="h-4 w-4"></i> Guardar cambios
        </button>
    </div>
</form>

<script>
document.getElementById('profile-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const data = {};
    fd.forEach((v, k) => { data[k] = v; });
    const status = document.getElementById('save-status');
    window.doctorAutoSaveHint(status, 'saving');
    const r = await window.doctorApi('PUT', '/portal-doctor/me', data);
    if (r.ok) {
        window.doctorAutoSaveHint(status, 'saved');
    } else {
        window.doctorAutoSaveHint(status, 'error');
        alert(r.message || 'Error');
    }
});
</script>
<?php doctor_layout_end();
