<?php
/**
 * Diario de Síntomas — herramienta del hub "Mi Salud".
 * Cronología de cómo se siente el paciente; se comparte con el médico (PDF).
 * El JS (portal-sintomas.js) habla con /api/portal-proxy.php → /portal/me/symptoms.
 */
require_once __DIR__ . '/_layout.php';
portal_require_login();

$token   = portal_token();
$meRes   = portal_api_call('GET', '/portal/me', [], $token);
$patient = is_array($meRes['data'] ?? null) ? $meRes['data'] : (portal_patient() ?? []);
$pName   = (string) ($patient['name'] ?? (portal_patient()['name'] ?? ''));
$first   = trim(explode(' ', trim(mb_convert_case(mb_strtolower($pName, 'UTF-8'), MB_CASE_TITLE, 'UTF-8')))[0] ?? '');

$GLOBALS['portal_extra_css'] = ['portal-sintomas.css'];
$GLOBALS['portal_extra_js']  = ['portal-sintomas.js'];
portal_layout_begin('Diario de síntomas', 'sintomas');
?>
<div class="sym" id="sym-app" aria-busy="true">

    <div class="sym-preview" id="sym-preview" hidden>
        <i data-lucide="cloud-off"></i>
        <div><strong>Modo vista previa</strong><span>Sin conexión con el servidor. Lo que registres aquí no se guardará todavía.</span></div>
    </div>

    <header class="sym-head">
        <a class="sym-back" href="<?= e(base_url('portal/salud.php')) ?>" aria-label="Volver a Mi Salud"><i data-lucide="arrow-left"></i></a>
        <div class="sym-head-titles">
            <h1>Diario de síntomas</h1>
            <p>Registra cómo te sientes y llévalo a tu próxima consulta<?= $first !== '' ? ', ' . e($first) : '' ?>.</p>
        </div>
        <button type="button" class="btn btn-green sym-add-btn" id="sym-add-btn"><i data-lucide="plus"></i> <span>Registrar</span></button>
    </header>

    <a class="sym-share" id="sym-share" href="<?= e(base_url('portal/sintomas-pdf.php')) ?>" target="_blank" rel="noopener">
        <span class="sym-share-ic"><i data-lucide="file-text"></i></span>
        <span class="sym-share-copy"><strong>Resumen para mi médico</strong><span>Genera un PDF con tu diario para llevarlo a la consulta.</span></span>
        <i data-lucide="download" class="sym-share-dl"></i>
    </a>

    <div class="sym-list" id="sym-list"><!-- timeline (JS) --></div>
</div>

<!-- Hoja de registro -->
<dialog class="sym-sheet" id="sym-sheet" aria-labelledby="sym-sheet-title">
    <form class="sym-sheet-form" id="sym-form" method="dialog">
        <div class="sym-sheet-grip" aria-hidden="true"></div>
        <header class="sym-sheet-head">
            <div>
                <h2 id="sym-sheet-title">¿Cómo te sientes?</h2>
                <p id="sym-sheet-date">Ahora</p>
            </div>
            <button type="button" class="sym-sheet-close" data-sym-close aria-label="Cerrar"><i data-lucide="x"></i></button>
        </header>
        <div class="sym-sheet-body" id="sym-sheet-body"><!-- campos (JS) --></div>
        <footer class="sym-sheet-foot">
            <button type="submit" class="btn btn-green" id="sym-save"><i data-lucide="check"></i> Guardar</button>
        </footer>
    </form>
</dialog>

<script>window.SYM_BOOT = { now: <?= json_encode(date('Y-m-d H:i')) ?> };</script>
<?php portal_layout_end();
