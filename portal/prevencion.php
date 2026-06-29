<?php
/**
 * Recordatorios de Prevención — herramienta del hub "Mi Salud".
 * Muestra los tamizajes recomendados según la edad y el sexo del expediente,
 * con su estado (te toca / al día / vencido). El catálogo y el cálculo viven en
 * portal-prevencion.js; el backend solo guarda cuándo se hizo cada uno.
 */
require_once __DIR__ . '/_layout.php';
portal_require_login();

$token   = portal_token();
$meRes   = portal_api_call('GET', '/portal/me', [], $token);
$patient = is_array($meRes['data'] ?? null) ? $meRes['data'] : (portal_patient() ?? []);
$pName   = (string) ($patient['name'] ?? (portal_patient()['name'] ?? ''));
$first   = trim(explode(' ', trim(mb_convert_case(mb_strtolower($pName, 'UTF-8'), MB_CASE_TITLE, 'UTF-8')))[0] ?? '');

$age = null;
if (!empty($patient['dob'])) {
    $t = strtotime((string) $patient['dob']);
    if ($t) $age = (int) floor((time() - $t) / 31557600);
}
$sex = strtolower(trim((string) ($patient['gender'] ?? '')));
$sex = in_array($sex, ['female', 'f', 'femenino'], true) ? 'female' : (in_array($sex, ['male', 'm', 'masculino'], true) ? 'male' : '');

$GLOBALS['portal_extra_css'] = ['portal-prevencion.css'];
$GLOBALS['portal_extra_js']  = ['portal-prevencion.js'];
portal_layout_begin('Recordatorios de prevención', 'prevencion');
?>
<div class="prev" id="prev-app" aria-busy="true">

    <div class="prev-preview" id="prev-preview" hidden>
        <i data-lucide="cloud-off"></i>
        <div><strong>Modo vista previa</strong><span>Sin conexión con el servidor. Lo que marques aquí no se guardará todavía.</span></div>
    </div>

    <header class="prev-head">
        <a class="prev-back" href="<?= e(base_url('portal/salud.php')) ?>" aria-label="Volver a Mi Salud"><i data-lucide="arrow-left"></i></a>
        <div class="prev-head-titles">
            <h1>Recordatorios de prevención</h1>
            <p>Chequeos y tamizajes que te conviene tener al día<?= $first !== '' ? ', ' . e($first) : '' ?>.</p>
        </div>
    </header>

    <div class="prev-intro" id="prev-intro"><!-- perfil + nota (JS) --></div>

    <div class="prev-list" id="prev-list"><!-- secciones (JS) --></div>

    <p class="prev-disclaimer">
        <i data-lucide="info"></i>
        Estas recomendaciones son generales y orientativas. Tu médico puede ajustarlas según tus antecedentes y factores de riesgo.
    </p>
</div>

<!-- Marcar como hecho -->
<dialog class="prev-sheet" id="prev-sheet" aria-labelledby="prev-sheet-title">
    <form class="prev-sheet-form" id="prev-form" method="dialog">
        <div class="prev-sheet-grip" aria-hidden="true"></div>
        <header class="prev-sheet-head">
            <div>
                <h2 id="prev-sheet-title">Marcar como realizado</h2>
                <p id="prev-sheet-sub"></p>
            </div>
            <button type="button" class="prev-sheet-close" data-prev-close aria-label="Cerrar"><i data-lucide="x"></i></button>
        </header>
        <div class="prev-sheet-body">
            <div class="prev-field">
                <label for="prev-date">¿Cuándo te lo hiciste?</label>
                <input class="prev-input" id="prev-date" type="date">
            </div>
        </div>
        <footer class="prev-sheet-foot">
            <button type="button" class="btn btn-outline prev-clear-btn" id="prev-clear-btn" hidden>Quitar registro</button>
            <button type="submit" class="btn btn-green" id="prev-save"><i data-lucide="check"></i> Guardar</button>
        </footer>
    </form>
</dialog>

<script>
    window.PREV_BOOT = {
        age: <?= $age !== null ? (int) $age : 'null' ?>,
        sex: <?= json_encode($sex) ?>,
        today: <?= json_encode(date('Y-m-d')) ?>
    };
</script>
<?php portal_layout_end();
