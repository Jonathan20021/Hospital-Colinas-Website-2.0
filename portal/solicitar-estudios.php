<?php
/**
 * Portal (autenticado): "Solicitar autorización de estudios".
 * Misma experiencia que la pública, pero dentro del portal y con los datos del
 * paciente precargados desde su expediente.
 */
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/study_request_form.php';
portal_require_login();

// Datos del paciente para precargar seguro/identidad.
$me = portal_api_call('GET', '/portal/me', [], portal_token());
$p  = $me['data'] ?? [];

$diag = $services['diagnostico']['items'] ?? [];
$labNames = ['Laboratorio Clínico y Banco de Sangre', 'Anatomía Patológica'];
$labList = array_values(array_filter($diag, fn($n) => in_array($n, $labNames, true)));
$imgList = array_values(array_filter($diag, fn($n) => !in_array($n, $labNames, true)));

$cssVersion = (string) (@filemtime(__DIR__ . '/../assets/css/estudios.css') ?: 0);
$jsVersion  = (string) (@filemtime(__DIR__ . '/../assets/js/solicitar-estudios.js') ?: 0);

portal_layout_begin('Solicitar estudios', 'solicitar-estudios');
?>
<link rel="stylesheet" href="<?= e(base_url('assets/css/estudios.css')) ?>?v=<?= e($cssVersion) ?>">

<div class="pa-head">
    <h1>Solicitar autorización de estudios</h1>
    <p>Sube tu orden médica y tu seguro; gestionamos la autorización y te decimos tu copago.</p>
</div>

<ol class="se-progress" data-se-progress aria-hidden="true"></ol>

<?php
render_study_request_form([
    'mode'     => 'portal',
    'prefill'  => [
        'full_name'        => $p['name'] ?? '',
        'cedula'           => $p['cedula'] ?? '',
        'phone'            => $p['phone'] ?? '',
        'email'            => $p['email'] ?? '',
        'insurer'          => $p['insurance_provider'] ?? '',
        'insurer_member_id'=> $p['insurance_policy'] ?? '',
    ],
    'imaging'  => $imgList,
    'lab'      => $labList,
    'insurers' => $insurers,
]);
?>

<script>
    window.SE_CONFIG = {
        mode: 'portal',
        catalogUrl: <?= json_encode(base_url('api/study-catalog.php'), JSON_UNESCAPED_SLASHES) ?>,
        guestSubmitUrl: '',
        proxyUrl: <?= json_encode(base_url('api/portal-proxy.php'), JSON_UNESCAPED_SLASHES) ?>,
        portalHome: <?= json_encode(base_url('portal/mis-solicitudes.php'), JSON_UNESCAPED_SLASHES) ?>,
        loginUrl: <?= json_encode(base_url('portal/login.php'), JSON_UNESCAPED_SLASHES) ?>,
        csrfToken: <?= json_encode(portal_csrf_token(), JSON_UNESCAPED_SLASHES) ?>
    };
</script>
<script src="<?= e(base_url('assets/js/solicitar-estudios.js')) ?>?v=<?= e($jsVersion) ?>"></script>
<?php portal_layout_end();
