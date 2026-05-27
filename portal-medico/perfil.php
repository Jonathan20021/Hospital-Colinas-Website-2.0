<?php
require_once __DIR__ . '/_layout.php';
doctor_require_login();

$res = portal_api_call('GET', '/portal-doctor/me', [], doctor_token());
$doctor = $res['data'] ?? [];

doctor_layout_begin('Mi perfil', 'perfil');
?>
<header class="doctor-header">
    <div>
        <p class="doctor-eyebrow">Perfil</p>
        <h1>Mi informacion profesional</h1>
        <p class="doctor-subtitle">Esta informacion aparece en el directorio publico y en tus recetas.</p>
    </div>
</header>

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

    <div class="doctor-form-full doctor-consult-footer">
        <span id="save-status" class="doctor-cell-muted"></span>
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
    status.textContent = 'Guardando...';
    const r = await window.doctorApi('PUT', '/portal-doctor/me', data);
    if (r.ok) {
        status.textContent = 'Guardado · ' + new Date().toLocaleTimeString();
    } else {
        status.textContent = '';
        alert(r.message || 'Error');
    }
});
</script>
<?php doctor_layout_end();
