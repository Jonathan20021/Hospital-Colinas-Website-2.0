<?php
/**
 * Mis Signos Vitales — herramienta del hub "Mi Salud".
 * Muestra tendencias de presión, peso/IMC, glucosa, pulso, temperatura y
 * saturación, combinando lo que registra el hospital (vital_signs, solo lectura)
 * con las mediciones caseras de la paciente. El JS (portal-vitales.js) hidrata
 * todo vía /api/portal-proxy.php → /portal/me/vitals.
 */
require_once __DIR__ . '/_layout.php';
portal_require_login();

$token   = portal_token();
$meRes   = portal_api_call('GET', '/portal/me', [], $token);
$patient = is_array($meRes['data'] ?? null) ? $meRes['data'] : (portal_patient() ?? []);
$pName   = (string) ($patient['name'] ?? (portal_patient()['name'] ?? ''));
$first   = trim(explode(' ', trim(mb_convert_case(mb_strtolower($pName, 'UTF-8'), MB_CASE_TITLE, 'UTF-8')))[0] ?? '');

$GLOBALS['portal_extra_css'] = ['portal-vitales.css'];
$GLOBALS['portal_extra_js']  = ['portal-vitales.js'];
portal_layout_begin('Mis signos vitales', 'vitales');
?>
<div class="vit" id="vit-app" aria-busy="true">

    <div class="vit-preview" id="vit-preview" hidden>
        <i data-lucide="cloud-off"></i>
        <div><strong>Modo vista previa</strong><span>Sin conexión con el servidor. Lo que registres aquí no se guardará todavía.</span></div>
    </div>

    <header class="vit-head">
        <a class="vit-back" href="<?= e(base_url('portal/salud.php')) ?>" aria-label="Volver a Mi Salud"><i data-lucide="arrow-left"></i></a>
        <div class="vit-head-titles">
            <h1>Mis signos vitales</h1>
            <p>Tus mediciones del hospital y las que tomes en casa, en un solo lugar<?= $first !== '' ? ', ' . e($first) : '' ?>.</p>
        </div>
        <button type="button" class="btn btn-green vit-add-btn" id="vit-add-btn"><i data-lucide="plus"></i> <span>Registrar</span></button>
    </header>

    <div class="vit-grid" id="vit-grid"><!-- tarjetas por signo (JS) --></div>

    <p class="vit-disclaimer">
        <i data-lucide="info"></i>
        Las interpretaciones son orientativas y no sustituyen la evaluación de tu médico. Si tienes síntomas o valores fuera de lo normal, comunícate con el hospital.
    </p>
</div>

<!-- Hoja de registro -->
<dialog class="vit-sheet" id="vit-sheet" aria-labelledby="vit-sheet-title">
    <form class="vit-sheet-form" id="vit-form" method="dialog">
        <div class="vit-sheet-grip" aria-hidden="true"></div>
        <header class="vit-sheet-head">
            <div>
                <h2 id="vit-sheet-title">Registrar medición</h2>
                <p>Llena solo lo que mediste.</p>
            </div>
            <button type="button" class="vit-sheet-close" data-vit-close aria-label="Cerrar"><i data-lucide="x"></i></button>
        </header>
        <div class="vit-sheet-body" id="vit-sheet-body"><!-- campos (JS) --></div>
        <footer class="vit-sheet-foot">
            <button type="submit" class="btn btn-green" id="vit-save"><i data-lucide="check"></i> Guardar</button>
        </footer>
    </form>
</dialog>

<!-- Detalle / historial de un signo -->
<dialog class="vit-detail-dialog" id="vit-detail" aria-labelledby="vit-detail-title">
    <header class="vit-sheet-head">
        <h2 id="vit-detail-title">Detalle</h2>
        <button type="button" class="vit-sheet-close" data-vit-close aria-label="Cerrar"><i data-lucide="x"></i></button>
    </header>
    <div class="vit-detail-body" id="vit-detail-body"></div>
</dialog>

<script>window.VIT_BOOT = { today: <?= json_encode(date('Y-m-d')) ?>, now: <?= json_encode(date('Y-m-d H:i')) ?> };</script>
<?php portal_layout_end();
