<?php
/**
 * Embarazo semana a semana — herramienta del hub "Mi Salud".
 * A partir de la FUM calcula la semana de gestación y muestra el desarrollo del
 * bebé, el tamaño comparado, la cuenta regresiva y consejos. El JS
 * (portal-embarazo.js) habla con /api/portal-proxy.php → /portal/me/pregnancy.
 */
require_once __DIR__ . '/_layout.php';
portal_require_login();

$token   = portal_token();
$meRes   = portal_api_call('GET', '/portal/me', [], $token);
$patient = is_array($meRes['data'] ?? null) ? $meRes['data'] : (portal_patient() ?? []);
$pName   = (string) ($patient['name'] ?? (portal_patient()['name'] ?? ''));
$first   = trim(explode(' ', trim(mb_convert_case(mb_strtolower($pName, 'UTF-8'), MB_CASE_TITLE, 'UTF-8')))[0] ?? '');

$GLOBALS['portal_extra_css'] = ['portal-embarazo.css'];
$GLOBALS['portal_extra_js']  = ['portal-embarazo.js'];
portal_layout_begin('Embarazo semana a semana', 'embarazo');
?>
<div class="preg" id="preg-app" aria-busy="true">

    <div class="preg-preview" id="preg-preview" hidden>
        <i data-lucide="cloud-off"></i>
        <div><strong>Modo vista previa</strong><span>Sin conexión con el servidor. Lo que registres aquí no se guardará todavía.</span></div>
    </div>

    <header class="preg-head">
        <a class="preg-back" href="<?= e(base_url('portal/salud.php')) ?>" aria-label="Volver a Mi Salud"><i data-lucide="arrow-left"></i></a>
        <div class="preg-head-titles">
            <h1>Embarazo semana a semana</h1>
            <p>Acompaña el crecimiento de tu bebé<?= $first !== '' ? ', ' . e($first) : '' ?>.</p>
        </div>
        <button type="button" class="preg-settings" id="preg-settings-btn" hidden aria-label="Ajustes del embarazo"><i data-lucide="settings-2"></i></button>
    </header>

    <div id="preg-content"><!-- onboarding o vista de semana (JS) --></div>
</div>

<!-- Configurar FUM -->
<dialog class="preg-sheet" id="preg-setup" aria-labelledby="preg-setup-title">
    <form class="preg-sheet-form" id="preg-setup-form" method="dialog">
        <div class="preg-sheet-grip" aria-hidden="true"></div>
        <header class="preg-sheet-head">
            <h2 id="preg-setup-title">Configura tu embarazo</h2>
            <button type="button" class="preg-sheet-close" data-preg-close aria-label="Cerrar"><i data-lucide="x"></i></button>
        </header>
        <div class="preg-sheet-body">
            <p class="preg-setup-help">Indica el primer día de tu última menstruación (FUM). Con esa fecha calculamos las semanas de tu embarazo y la fecha probable de parto.</p>
            <div class="preg-field">
                <label for="preg-lmp">Primer día de tu última menstruación</label>
                <input class="preg-input" id="preg-lmp" type="date">
            </div>
            <p class="preg-setup-hint" id="preg-setup-calc" hidden></p>
        </div>
        <footer class="preg-sheet-foot">
            <button type="button" class="btn btn-outline preg-end-btn" id="preg-end-btn" hidden>Finalizar seguimiento</button>
            <button type="submit" class="btn btn-green" id="preg-setup-save"><i data-lucide="check"></i> Guardar</button>
        </footer>
    </form>
</dialog>

<script>window.PREG_BOOT = { today: <?= json_encode(date('Y-m-d')) ?> };</script>
<?php portal_layout_end();
