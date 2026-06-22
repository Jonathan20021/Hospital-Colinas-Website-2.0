<?php
require_once __DIR__ . '/_layout.php';
portal_require_login();

$pwMessage = null;
$pwErrors  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    portal_csrf_check();
    if (($_POST['form'] ?? '') !== 'password') {
        http_response_code(405);
        $pwMessage = 'Desde el portal solo puedes cambiar tu contraseña.';
    } else {
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['new_password_confirm'] ?? '');
        if ($new !== $confirm) {
            $pwErrors = ['new_password_confirm' => ['Las contraseñas no coinciden.']];
        } else {
            $res = portal_api_call('PUT', '/portal/me/password', [
                'current_password' => (string)($_POST['current_password'] ?? ''),
                'new_password'     => $new,
            ], portal_token());
            if ($res['ok']) {
                portal_flash_set('success', 'Contraseña actualizada.');
                header('Location: ' . base_url('portal/perfil.php'));
                exit;
            }
            $pwMessage = $res['message'] ?? 'No se pudo cambiar la contraseña.';
            $pwErrors  = $res['errors'];
        }
    }
}

$me = portal_api_call('GET', '/portal/me', [], portal_token());
$p  = $me['data'] ?? [];
$profileError = !$me['ok'] ? ($me['message'] ?? 'No pudimos cargar tu información en este momento.') : null;

$displayValue = static function ($value, string $fallback = 'No registrado'): string {
    $value = trim((string) $value);
    return $value !== '' ? $value : $fallback;
};

$dob = 'No registrada';
if (!empty($p['dob'])) {
    $timestamp = strtotime((string) $p['dob']);
    if ($timestamp !== false) {
        $months = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];
        $dob = date('j', $timestamp) . ' de ' . $months[(int) date('n', $timestamp)] . ' de ' . date('Y', $timestamp);
    }
}

$genderLabels = [
    'male' => 'Masculino',
    'female' => 'Femenino',
    'other' => 'Otro',
    'masculino' => 'Masculino',
    'femenino' => 'Femenino',
    'otro' => 'Otro',
];
$genderKey = mb_strtolower(trim((string) ($p['gender'] ?? '')), 'UTF-8');
$gender = $genderLabels[$genderKey] ?? 'No registrado';

$addressParts = [];
$addressSeen = [];
foreach ([$p['address'] ?? '', $p['neighborhood'] ?? '', $p['province'] ?? ''] as $addressPart) {
    $addressPart = trim((string) $addressPart);
    $addressKey = mb_strtolower($addressPart, 'UTF-8');
    if ($addressPart !== '' && !isset($addressSeen[$addressKey])) {
        $addressParts[] = $addressPart;
        $addressSeen[$addressKey] = true;
    }
}
$fullAddress = $addressParts ? implode(', ', $addressParts) : 'No registrada';

portal_layout_begin('Mi perfil', 'perfil');
?>
<header class="portal-page-title">
    <h1>Mi perfil</h1>
    <p>Consulta la información vinculada a tu expediente y administra la seguridad de tu cuenta.</p>
</header>

<?php if ($profileError): ?>
    <div class="portal-flash portal-flash-error"><i data-lucide="alert-circle" class="h-4 w-4"></i><span><?= e($profileError) ?></span></div>
<?php endif; ?>

<div class="portal-profile-grid">
<section class="portal-card portal-profile-details" aria-labelledby="profile-details-title">
    <div class="portal-profile-details-head">
        <div>
            <h2 id="profile-details-title">Información del paciente</h2>
            <p>Estos datos provienen de tu expediente en el hospital.</p>
        </div>
        <span class="portal-readonly-badge"><i data-lucide="lock-keyhole"></i> Solo lectura</span>
    </div>

    <dl class="portal-profile-data">
        <div>
            <dt><i data-lucide="user-round"></i> Nombre completo</dt>
            <dd><?= e($displayValue($p['name'] ?? null)) ?></dd>
        </div>
        <div>
            <dt><i data-lucide="id-card"></i> Cédula</dt>
            <dd><?= e($displayValue($p['cedula'] ?? null)) ?></dd>
        </div>
        <div>
            <dt><i data-lucide="mail"></i> Correo electrónico</dt>
            <dd>
                <?php if (trim((string)($p['email'] ?? '')) !== ''): ?>
                    <?= e($p['email']) ?>
                <?php else: ?>
                    <button type="button" class="portal-text-link" data-open-email-onboarding>Agregar correo</button>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt><i data-lucide="phone"></i> Teléfono</dt>
            <dd><?= e($displayValue($p['phone'] ?? null)) ?></dd>
        </div>
        <div>
            <dt><i data-lucide="cake-slice"></i> Fecha de nacimiento</dt>
            <dd><?= e($dob) ?></dd>
        </div>
        <div>
            <dt><i data-lucide="users-round"></i> Sexo</dt>
            <dd><?= e($gender) ?></dd>
        </div>
        <div class="portal-profile-data-wide">
            <dt><i data-lucide="map-pin"></i> Dirección</dt>
            <dd><?= e($fullAddress) ?></dd>
        </div>
        <div>
            <dt><i data-lucide="shield-plus"></i> Seguro médico</dt>
            <dd><?= e($displayValue($p['insurance_provider'] ?? null)) ?></dd>
        </div>
        <div>
            <dt><i data-lucide="badge-check"></i> No. de póliza</dt>
            <dd><?= e($displayValue($p['insurance_policy'] ?? null)) ?></dd>
        </div>
    </dl>

    <?php if (trim((string)($p['email'] ?? '')) === ''): ?>
    <div class="portal-email-cta">
        <span class="portal-email-cta-ic"><i data-lucide="mail-plus"></i></span>
        <div class="portal-email-cta-copy">
            <strong>Agrega tu correo y entra más fácil</strong>
            <span>Entra con un código que te llega al correo, sin recordar contraseñas.</span>
        </div>
        <button type="button" class="btn btn-green" data-open-email-onboarding><i data-lucide="mail-plus"></i> Agregar correo</button>
    </div>
    <?php endif; ?>
</section>

<aside class="portal-profile-aside">
    <section class="portal-surface portal-profile-note portal-profile-help">
        <span class="portal-profile-note-icon"><i data-lucide="file-pen-line"></i></span>
        <h2>¿Necesitas corregir un dato?</h2>
        <p>Para proteger tu expediente, los cambios de información personal se realizan directamente con el hospital.</p>
        <a class="btn btn-outline" href="tel:8098060444"><i data-lucide="phone"></i> Llamar al (809) 806-0444</a>
    </section>
    <section class="portal-surface portal-profile-note">
        <h2>Privacidad</h2>
        <p>Evita compartir capturas de tus resultados o credenciales de acceso en canales públicos.</p>
    </section>
</aside>
</div>

<header class="portal-page-title portal-security-title">
    <h2>Seguridad de la cuenta</h2>
    <p>La contraseña es el único dato que puedes modificar desde el portal.</p>
</header>

<?php if (!empty($p['using_id_as_password'])): ?>
    <div class="portal-banner portal-banner-warning">
        <i data-lucide="shield-alert" class="h-5 w-5"></i>
        <div>
            <strong>Estás usando tu cédula como contraseña.</strong>
            <p>Por tu seguridad, te recomendamos cambiarla por una contraseña personal.</p>
        </div>
    </div>
<?php endif; ?>

<?php if ($pwMessage): ?>
    <div class="portal-flash portal-flash-error"><i data-lucide="alert-circle" class="h-4 w-4"></i><span><?= e($pwMessage) ?></span></div>
<?php endif; ?>
<?= portal_render_errors($pwErrors) ?>

<form method="POST" class="portal-card portal-form portal-password-card">
    <input type="hidden" name="_csrf" value="<?= e(portal_csrf_token()) ?>">
    <input type="hidden" name="form" value="password">

    <div class="portal-password-card-head">
        <span><i data-lucide="key-round"></i></span>
        <div>
            <h3>Cambiar contraseña</h3>
            <p>Usa al menos 8 caracteres y evita datos fáciles de adivinar.</p>
        </div>
    </div>

    <div class="portal-password-field">
        <label class="form-label" for="current_password">Contraseña actual</label>
        <input type="password" name="current_password" id="current_password" class="form-input" required autocomplete="current-password">
        <button type="button" class="portal-password-toggle" data-target="current_password" aria-label="Mostrar contraseña"><i data-lucide="eye"></i></button>
    </div>

    <div class="portal-grid-2">
        <div class="portal-password-field">
            <label class="form-label" for="new_password">Nueva contraseña</label>
            <input type="password" name="new_password" id="new_password" class="form-input" required minlength="8" autocomplete="new-password">
            <button type="button" class="portal-password-toggle" data-target="new_password" aria-label="Mostrar contraseña"><i data-lucide="eye"></i></button>
        </div>
        <div class="portal-password-field">
            <label class="form-label" for="new_password_confirm">Confirmar nueva contraseña</label>
            <input type="password" name="new_password_confirm" id="new_password_confirm" class="form-input" required minlength="8" autocomplete="new-password">
            <button type="button" class="portal-password-toggle" data-target="new_password_confirm" aria-label="Mostrar contraseña"><i data-lucide="eye"></i></button>
        </div>
    </div>

    <div class="portal-form-actions">
        <button type="submit" class="btn btn-green"><i data-lucide="shield-check"></i> Cambiar contraseña</button>
    </div>
</form>

<section class="portal-card portal-notif-card" id="pa-notif-card" hidden>
    <div class="portal-password-card-head">
        <span><i data-lucide="bell"></i></span>
        <div>
            <h3>Notificaciones</h3>
            <p>Recibe un aviso cuando tu médico te escriba, aunque no tengas el portal abierto.</p>
        </div>
    </div>
    <div class="portal-notif-actions">
        <button type="button" class="btn btn-green" id="pa-notif-enable"><i data-lucide="bell-ring"></i> Activar notificaciones</button>
        <button type="button" class="btn btn-outline" id="pa-notif-disable" hidden><i data-lucide="bell-off"></i> Desactivar</button>
        <button type="button" class="btn btn-outline" id="pa-notif-test" hidden><i data-lucide="send"></i> Probar</button>
    </div>
    <p class="portal-notif-status" id="pa-notif-status" aria-live="polite"></p>
</section>
<script>
window.addEventListener('load', function () {
    var card = document.getElementById('pa-notif-card');
    if (!card || !window.HGLCPush || !HGLCPush.supported) return;
    card.hidden = false;
    var bEn = document.getElementById('pa-notif-enable');
    var bDis = document.getElementById('pa-notif-disable');
    var bTest = document.getElementById('pa-notif-test');
    var st = document.getElementById('pa-notif-status');
    function render(s) {
        var on = !!(s && s.subscribed);
        bEn.hidden = on; bDis.hidden = !on; bTest.hidden = !on;
        if (s && s.permission === 'denied') {
            st.textContent = 'Las notificaciones están bloqueadas en tu navegador. Habilítalas desde los ajustes del sitio.';
            bEn.disabled = true;
        } else {
            st.textContent = on ? 'Activadas en este dispositivo.' : 'Están desactivadas en este dispositivo.';
        }
    }
    HGLCPush.status().then(render).catch(function () {});
    bEn.addEventListener('click', function () {
        bEn.disabled = true; st.textContent = 'Activando…';
        HGLCPush.enable().then(function () { return HGLCPush.status(); }).then(render)
            .catch(function (e) { st.textContent = (e && e.message === 'denied') ? 'Permiso denegado.' : 'No se pudo activar.'; })
            .finally(function () { bEn.disabled = false; });
    });
    bDis.addEventListener('click', function () {
        HGLCPush.disable().then(function () { return HGLCPush.status(); }).then(render).catch(function () {});
    });
    bTest.addEventListener('click', function () {
        st.textContent = 'Enviando prueba…';
        HGLCPush.test().then(function () { st.textContent = 'Prueba enviada. Debería llegarte una notificación.'; })
            .catch(function () { st.textContent = 'No se pudo enviar la prueba.'; });
    });
    if (window.lucide) lucide.createIcons();
});
</script>
<?php portal_layout_end();
