<?php
/**
 * Mis Medicamentos — herramienta del hub "Mi Salud".
 * Lista de medicamentos del paciente con horarios + checklist de tomas del día.
 * El JS (portal-medicamentos.js) habla con /api/portal-proxy.php → /portal/me/medications.
 */
require_once __DIR__ . '/_layout.php';
portal_require_login();

$token   = portal_token();
$meRes   = portal_api_call('GET', '/portal/me', [], $token);
$patient = is_array($meRes['data'] ?? null) ? $meRes['data'] : (portal_patient() ?? []);
$pName   = (string) ($patient['name'] ?? (portal_patient()['name'] ?? ''));
$first   = trim(explode(' ', trim(mb_convert_case(mb_strtolower($pName, 'UTF-8'), MB_CASE_TITLE, 'UTF-8')))[0] ?? '');

$GLOBALS['portal_extra_css'] = ['portal-medicamentos.css'];
$GLOBALS['portal_extra_js']  = ['portal-medicamentos.js'];
portal_layout_begin('Mis medicamentos', 'medicamentos');
?>
<div class="med" id="med-app" aria-busy="true">

    <div class="med-preview" id="med-preview" hidden>
        <i data-lucide="cloud-off"></i>
        <div><strong>Modo vista previa</strong><span>Sin conexión con el servidor. Lo que registres aquí no se guardará todavía.</span></div>
    </div>

    <header class="med-head">
        <a class="med-back" href="<?= e(base_url('portal/salud.php')) ?>" aria-label="Volver a Mi Salud"><i data-lucide="arrow-left"></i></a>
        <div class="med-head-titles">
            <h1>Mis medicamentos</h1>
            <p>Lleva el control de tus medicinas y tus tomas del día<?= $first !== '' ? ', ' . e($first) : '' ?>.</p>
        </div>
        <button type="button" class="btn btn-green med-add-btn" id="med-add-btn"><i data-lucide="plus"></i> <span>Agregar</span></button>
    </header>

    <section class="med-today-card" id="med-today-card"><!-- checklist de hoy (JS) --></section>

    <section class="med-section">
        <h2 class="med-section-title">Mis medicamentos</h2>
        <div class="med-list" id="med-list"><!-- lista (JS) --></div>
    </section>

    <p class="med-disclaimer">
        <i data-lucide="info"></i>
        Esta lista es para tu organización personal. No cambies ni suspendas ningún medicamento sin indicación de tu médico.
    </p>
</div>

<!-- Hoja agregar/editar -->
<dialog class="med-sheet" id="med-sheet" aria-labelledby="med-sheet-title">
    <form class="med-sheet-form" id="med-form" method="dialog">
        <div class="med-sheet-grip" aria-hidden="true"></div>
        <header class="med-sheet-head">
            <h2 id="med-sheet-title">Agregar medicamento</h2>
            <button type="button" class="med-sheet-close" data-med-close aria-label="Cerrar"><i data-lucide="x"></i></button>
        </header>
        <div class="med-sheet-body" id="med-sheet-body"><!-- campos (JS) --></div>
        <footer class="med-sheet-foot">
            <button type="button" class="btn btn-outline med-del-btn" id="med-del-btn" hidden><i data-lucide="trash-2"></i></button>
            <button type="submit" class="btn btn-green" id="med-save"><i data-lucide="check"></i> Guardar</button>
        </footer>
    </form>
</dialog>

<script>window.MED_BOOT = { today: <?= json_encode(date('Y-m-d')) ?> };</script>
<?php portal_layout_end();
