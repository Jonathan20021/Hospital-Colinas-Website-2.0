<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../includes/portal_client.php';
require_once __DIR__ . '/../includes/portal_directory.php';
require_once __DIR__ . '/../includes/doctor_avatar.php';

require_admin_permission('doctors');

$query     = trim($_GET['q'] ?? '');
$specFilter= (int)($_GET['specialty_id'] ?? 0);

// Verificar que HOSPITAL_API_KEY esté configurada para acciones de escritura
$apiKey = defined('HOSPITAL_API_KEY') ? HOSPITAL_API_KEY : '';
$apiKeyMissing = $apiKey === '';

// Cargar médicos del hospital via API (con cache 1h)
$apiRes = portal_directory_doctors();
$doctorsAll = $apiRes['ok'] ? $apiRes['data'] : [];

// Filtros
$doctors = array_values(array_filter($doctorsAll, function($d) use ($query, $specFilter) {
    if ($specFilter && (int)($d['specialty_id'] ?? 0) !== $specFilter) return false;
    if ($query !== '') {
        $hay = mb_strtolower(($d['name'] ?? '') . ' ' . ($d['specialty'] ?? '') . ' ' . ($d['exequatur'] ?? ''), 'UTF-8');
        if (mb_strpos($hay, mb_strtolower($query, 'UTF-8')) === false) return false;
    }
    return true;
}));

// Especialidades para filtro
$specRes = portal_directory_specialties();
$specs = $specRes['ok'] ? $specRes['data'] : [];

// Stats
$stats = [
    'total'      => count($doctorsAll),
    'visible'    => count($doctorsAll),
    'with_photo' => count(array_filter($doctorsAll, fn($d) => !empty($d['photo_url']) && strpos($d['photo_url'], 'data:') !== 0)),
    'featured'   => count(array_filter($doctorsAll, fn($d) => !empty($d['is_featured']))),
];

admin_header('Médicos', 'medicos');
?>
<style>
.med-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:1.5rem; }
.med-stat { padding:1rem 1.25rem; background:#fff; border:1px solid #e2e8f0; border-radius:12px; }
.med-stat strong { font-size:1.75rem; display:block; color:#0f172a; margin-top:.25rem; }
.med-stat span { color:#6b7280; font-size:.8rem; }
.med-filters { display:flex; gap:.75rem; align-items:end; margin-bottom:1.25rem; flex-wrap:wrap; }
.med-filters .field { display:flex; flex-direction:column; gap:.25rem; }
.med-filters input, .med-filters select { padding:.5rem .7rem; border:1px solid #cbd5e1; border-radius:8px; min-width:200px; }
.med-source { font-size:.78rem; color:#94a3b8; margin:.5rem 0 1.25rem; }
.med-source.stale { color:#b45309; font-weight:600; }

.med-grid { display:grid; gap:.85rem; }
.med-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1.25rem; display:grid; grid-template-columns:96px 1fr auto; gap:1.25rem; align-items:center; transition: box-shadow .15s; }
.med-card:hover { box-shadow: 0 4px 16px rgba(15,23,42,.06); }
.med-photo-wrap { width:96px; height:96px; border-radius:50%; overflow:hidden; position:relative; background:#f1f5f9; flex-shrink:0; }
.med-photo-wrap img { width:100%; height:100%; object-fit:cover; display:block; }
.med-photo-edit { position:absolute; bottom:0; left:0; right:0; padding:.3rem; background:rgba(15,23,42,.75); color:#fff; font-size:.65rem; text-align:center; cursor:pointer; opacity:0; transition:opacity .15s; }
.med-photo-wrap:hover .med-photo-edit { opacity:1; }
.med-body h3 { margin:0 0 .25rem; color:#0f172a; font-size:1.05rem; }
.med-spec { color:#047857; font-weight:600; font-size:.85rem; margin:0; }
.med-meta { display:flex; gap:1rem; margin-top:.4rem; font-size:.78rem; color:#64748b; flex-wrap:wrap; }
.med-meta span { display:inline-flex; align-items:center; gap:.25rem; }
.med-badges { display:flex; gap:.35rem; flex-wrap:wrap; margin-top:.4rem; }
.med-badge { font-size:.65rem; font-weight:700; text-transform:uppercase; padding:.15rem .45rem; border-radius:999px; }
.med-badge-photo { background:#dbeafe; color:#1e40af; }
.med-badge-nophoto { background:#fef2f2; color:#991b1b; }
.med-badge-featured { background:#fef3c7; color:#854d0e; }
.med-actions { display:flex; flex-direction:column; gap:.35rem; }
.med-btn { padding:.4rem .75rem; border:1px solid #047857; background:#fff; color:#047857; border-radius:8px; cursor:pointer; font-size:.78rem; text-decoration:none; text-align:center; }
.med-btn:hover { background:#ecfdf5; }
.med-btn-danger { border-color:#dc2626; color:#dc2626; }
.med-btn-danger:hover { background:#fef2f2; }

.med-edit-form { display:none; grid-column:1/-1; padding-top:1rem; margin-top:1rem; border-top:1px dashed #e2e8f0; }
.med-edit-form.is-open { display:block; }
.med-row { display:grid; grid-template-columns:repeat(2,1fr); gap:.75rem; margin-bottom:.75rem; }
.med-row label { display:block; font-size:.78rem; color:#334155; font-weight:600; margin-bottom:.2rem; }
.med-row textarea, .med-row input { width:100%; padding:.55rem .65rem; border:1px solid #cbd5e1; border-radius:8px; font-family:inherit; font-size:.88rem; }
.med-row .full { grid-column:1/-1; }

.med-warn { background:#fef3c7; border:1px solid #fcd34d; color:#78350f; padding:1rem 1.25rem; border-radius:10px; margin-bottom:1.5rem; }
.med-warn code { background:#fde68a; padding:.1rem .35rem; border-radius:4px; font-size:.85rem; }

@media (max-width: 720px) {
    .med-card { grid-template-columns: 96px 1fr; }
    .med-actions { grid-column:1/-1; flex-direction:row; flex-wrap:wrap; padding-top:.5rem; border-top:1px dashed #f1f5f9; }
    .med-row { grid-template-columns: 1fr; }
    .med-stats { grid-template-columns: repeat(2,1fr); }
}
</style>

<section class="admin-panel">
    <div class="admin-panel-head">
        <div>
            <span>Directorio médico</span>
            <h2>Médicos del hospital (<?= count($doctorsAll) ?>)</h2>
        </div>
    </div>

    <?php
    // Flash de acciones (api-medico-action.php)
    if (!empty($_SESSION['admin_flash'])):
        $flash = $_SESSION['admin_flash'];
        unset($_SESSION['admin_flash']);
        $bg = $flash['type'] === 'success' ? '#dcfce7' : ($flash['type'] === 'danger' ? '#fee2e2' : '#fef3c7');
        $color = $flash['type'] === 'success' ? '#166534' : ($flash['type'] === 'danger' ? '#991b1b' : '#854d0e');
    ?>
        <div style="padding:1rem 1.25rem;background:<?= $bg ?>;color:<?= $color ?>;border-radius:10px;margin-bottom:1.25rem;font-weight:600">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php if ($apiKeyMissing): ?>
        <div class="med-warn">
            <strong>⚠ Modo solo lectura.</strong>
            Para subir fotos y editar perfiles desde aquí, agrega tu API key del hospital en
            <code>includes/config.local.php</code>:<br>
            <code>define('HOSPITAL_API_KEY', 're_xxxxxxxx');</code>
            <br><br>
            Para generarla, ve al admin del hospital → <em>API Keys</em> → crear nueva.
        </div>
    <?php endif; ?>

    <div class="med-stats">
        <div class="med-stat"><span>Total médicos</span><strong><?= $stats['total'] ?></strong></div>
        <div class="med-stat"><span>Con foto</span><strong style="color:#1e40af"><?= $stats['with_photo'] ?></strong></div>
        <div class="med-stat"><span>Sin foto</span><strong style="color:#b91c1c"><?= $stats['total'] - $stats['with_photo'] ?></strong></div>
        <div class="med-stat"><span>Destacados</span><strong style="color:#b45309"><?= $stats['featured'] ?></strong></div>
    </div>

    <form method="GET" class="med-filters">
        <div class="field">
            <label>Buscar</label>
            <input type="search" name="q" value="<?= e($query) ?>" placeholder="Nombre, especialidad...">
        </div>
        <div class="field">
            <label>Especialidad</label>
            <select name="specialty_id">
                <option value="">Todas</option>
                <?php foreach ($specs as $s): ?>
                    <option value="<?= (int)$s['id'] ?>" <?= $specFilter === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="admin-primary-action">Filtrar</button>
        <a href="medicos.php" style="color:#6b7280;align-self:center;text-decoration:none">Limpiar</a>
    </form>

    <p class="med-source <?= ($apiRes['stale'] ?? false) ? 'stale' : '' ?>">
        Datos desde: <strong><?= e($apiRes['source'] ?? '?') ?></strong>
        <?php if ($apiRes['stale'] ?? false): ?> · ⚠ caché obsoleto (la API no responde)<?php endif; ?>
        · <a href="medicos.php?refresh=1" style="color:#047857">refrescar caché</a>
    </p>

    <?php
    // Forzar refresh
    if (!empty($_GET['refresh'])) {
        $cacheDir = __DIR__ . '/../storage/cache/directory';
        if (is_dir($cacheDir)) {
            foreach (glob($cacheDir . '/*.json') as $f) @unlink($f);
        }
        header('Location: medicos.php');
        exit;
    }
    ?>

    <div class="med-grid">
        <?php foreach ($doctors as $d):
            $hasPhoto = !empty($d['photo_url']);
            $displayPhoto = $hasPhoto
                ? portal_directory_photo_url($d['photo_url'])
                : doctor_avatar_svg($d['name'] ?? 'Médico');
            $id = (int)$d['id'];
        ?>
            <article class="med-card" id="doc-<?= $id ?>">
                <div class="med-photo-wrap">
                    <img src="<?= e($displayPhoto) ?>" alt="<?= e($d['name']) ?>">
                    <?php if (!$apiKeyMissing): ?>
                        <div class="med-photo-edit" onclick="document.getElementById('photo-input-<?= $id ?>').click()">📷 Cambiar</div>
                    <?php endif; ?>
                </div>

                <div class="med-body">
                    <h3><?= e($d['name']) ?></h3>
                    <p class="med-spec"><?= e($d['specialty']) ?></p>
                    <div class="med-meta">
                        <?php if (!empty($d['office_name'])): ?>
                            <span><i data-lucide="map-pin" style="width:12px;height:12px"></i> <?= e($d['office_name']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($d['schedule']['label'])): ?>
                            <span><i data-lucide="clock" style="width:12px;height:12px"></i> <?= e($d['schedule']['label']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($d['exequatur'])): ?>
                            <span><i data-lucide="badge-check" style="width:12px;height:12px"></i> <?= e($d['exequatur']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="med-badges">
                        <?php if ($hasPhoto): ?>
                            <span class="med-badge med-badge-photo">Con foto</span>
                        <?php else: ?>
                            <span class="med-badge med-badge-nophoto">Sin foto</span>
                        <?php endif; ?>
                        <?php if (!empty($d['is_featured'])): ?>
                            <span class="med-badge med-badge-featured">Destacado</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="med-actions">
                    <a href="<?= e(base_url('medico/' . $d['slug'])) ?>" target="_blank" class="med-btn">Ver perfil</a>
                    <?php if (!$apiKeyMissing): ?>
                        <button type="button" class="med-btn" onclick="document.getElementById('form-<?= $id ?>').classList.toggle('is-open')">Editar</button>
                        <?php if ($hasPhoto): ?>
                            <form method="POST" action="api-medico-action.php" onsubmit="return confirm('¿Eliminar foto?')" style="margin:0">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete_photo">
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <button type="submit" class="med-btn med-btn-danger" style="width:100%">Quitar foto</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if (!$apiKeyMissing): ?>
                    <form method="POST" action="api-medico-action.php" enctype="multipart/form-data" style="display:none" id="photo-form-<?= $id ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="upload_photo">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="file" id="photo-input-<?= $id ?>" name="photo" accept="image/jpeg,image/png,image/webp" onchange="this.form.submit()">
                    </form>

                    <form method="POST" action="api-medico-action.php" class="med-edit-form" id="form-<?= $id ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="update_directory">
                        <input type="hidden" name="id" value="<?= $id ?>">

                        <div class="med-row">
                            <div class="full">
                                <label>Biografía profesional</label>
                                <textarea name="biography" rows="3"><?= e($d['biography'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="med-row">
                            <div>
                                <label>Formación / Educación</label>
                                <textarea name="education" rows="2"><?= e($d['education'] ?? '') ?></textarea>
                            </div>
                            <div>
                                <label>Idiomas</label>
                                <input type="text" name="languages" value="<?= e($d['languages'] ?? 'Español') ?>">
                            </div>
                        </div>
                        <div class="med-row">
                            <div>
                                <label>Servicios que ofrece</label>
                                <textarea name="services" rows="2"><?= e($d['services'] ?? '') ?></textarea>
                            </div>
                            <div>
                                <label>Aseguradoras</label>
                                <textarea name="insurances" rows="2"><?= e($d['insurances'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="med-row">
                            <div class="full">
                                <label>Asociaciones / Sociedades médicas</label>
                                <textarea name="associations" rows="2"><?= e($d['associations'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="med-row" style="grid-template-columns: 1fr 1fr 1fr">
                            <label style="font-size:.85rem;display:flex;align-items:center;gap:.4rem">
                                <input type="checkbox" name="is_featured" value="1" <?= !empty($d['is_featured']) ? 'checked' : '' ?>>
                                Destacado en directorio
                            </label>
                            <label style="font-size:.85rem;display:flex;align-items:center;gap:.4rem">
                                <input type="checkbox" name="show_in_directory" value="1" checked>
                                Visible en landing
                            </label>
                            <div>
                                <label>Orden</label>
                                <input type="number" name="sort_order" value="<?= (int)($d['sort_order'] ?? 0) ?>">
                            </div>
                        </div>
                        <button type="submit" class="admin-primary-action">Guardar cambios</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>

        <?php if (!$doctors): ?>
            <p style="text-align:center;color:#6b7280;padding:2rem">No hay médicos que coincidan.</p>
        <?php endif; ?>
    </div>
</section>
<?php admin_footer(); ?>
