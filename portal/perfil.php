<?php
require_once __DIR__ . '/_layout.php';
portal_require_login();

$message = null;
$errors  = null;
$pwMessage = null;
$pwErrors  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    portal_csrf_check();
    if (($_POST['form'] ?? '') === 'password') {
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
    } else {
        $payload = [];
        foreach (['name','phone','dob','gender','address','province','neighborhood','insurance_provider','insurance_policy'] as $f) {
            if (array_key_exists($f, $_POST)) $payload[$f] = trim((string)$_POST[$f]);
        }
        $res = portal_api_call('PUT', '/portal/me', $payload, portal_token());
        if ($res['ok']) {
            portal_flash_set('success', 'Perfil actualizado.');
            header('Location: ' . base_url('portal/perfil.php'));
            exit;
        }
        $message = $res['message'] ?? 'No se pudo actualizar el perfil.';
        $errors  = $res['errors'];
    }
}

$me = portal_api_call('GET', '/portal/me', [], portal_token());
$p  = $me['data'] ?? [];

portal_layout_begin('Mi perfil', 'perfil');
?>
<header class="portal-header">
    <div><p class="section-label">Cuenta</p><h1>Mi perfil</h1></div>
</header>

<?php if ($message): ?>
    <div class="portal-flash portal-flash-error"><i data-lucide="alert-circle" class="h-4 w-4"></i><span><?= e($message) ?></span></div>
<?php endif; ?>
<?= portal_render_errors($errors) ?>

<form method="POST" class="portal-card portal-form">
    <input type="hidden" name="_csrf" value="<?= e(portal_csrf_token()) ?>">

    <div class="portal-grid-2">
        <div>
            <label class="form-label" for="name">Nombre completo</label>
            <input type="text" name="name" id="name" class="form-input" value="<?= e($p['name'] ?? '') ?>" required>
        </div>
        <div>
            <label class="form-label" for="phone">Teléfono</label>
            <input type="tel" name="phone" id="phone" class="form-input" value="<?= e($p['phone'] ?? '') ?>" required>
        </div>
    </div>

    <div class="portal-grid-2">
        <div>
            <label class="form-label" for="dob">Fecha de nacimiento</label>
            <input type="date" name="dob" id="dob" class="form-input" value="<?= e($p['dob'] ?? '') ?>">
        </div>
        <div>
            <label class="form-label" for="gender">Sexo</label>
            <select name="gender" id="gender" class="form-input">
                <option value="">Prefiero no decir</option>
                <option value="Male"   <?= ($p['gender'] ?? '') === 'Male'   ? 'selected' : '' ?>>Masculino</option>
                <option value="Female" <?= ($p['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Femenino</option>
                <option value="Other"  <?= ($p['gender'] ?? '') === 'Other'  ? 'selected' : '' ?>>Otro</option>
            </select>
        </div>
    </div>

    <label class="form-label" for="address">Dirección</label>
    <input type="text" name="address" id="address" class="form-input" value="<?= e($p['address'] ?? '') ?>">

    <div class="portal-grid-2">
        <div>
            <label class="form-label" for="province">Provincia</label>
            <input type="text" name="province" id="province" class="form-input" value="<?= e($p['province'] ?? '') ?>">
        </div>
        <div>
            <label class="form-label" for="neighborhood">Sector / Barrio</label>
            <input type="text" name="neighborhood" id="neighborhood" class="form-input" value="<?= e($p['neighborhood'] ?? '') ?>">
        </div>
    </div>

    <div class="portal-grid-2">
        <div>
            <label class="form-label" for="insurance_provider">Seguro médico</label>
            <input type="text" name="insurance_provider" id="insurance_provider" class="form-input" value="<?= e($p['insurance_provider'] ?? '') ?>">
        </div>
        <div>
            <label class="form-label" for="insurance_policy">No. de póliza</label>
            <input type="text" name="insurance_policy" id="insurance_policy" class="form-input" value="<?= e($p['insurance_policy'] ?? '') ?>">
        </div>
    </div>

    <p class="portal-hint">Tu cédula y correo son datos críticos: si necesitas cambiarlos contacta al hospital.</p>

    <button type="submit" class="btn btn-green mt-3">Guardar cambios</button>
</form>

<header class="portal-header" style="margin-top:2rem">
    <div><p class="section-label">Seguridad</p><h2>Cambiar contraseña</h2></div>
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

<form method="POST" class="portal-card portal-form">
    <input type="hidden" name="_csrf" value="<?= e(portal_csrf_token()) ?>">
    <input type="hidden" name="form" value="password">

    <label class="form-label" for="current_password">Contraseña actual</label>
    <input type="password" name="current_password" id="current_password" class="form-input" required autocomplete="current-password">

    <div class="portal-grid-2">
        <div>
            <label class="form-label" for="new_password">Nueva contraseña</label>
            <input type="password" name="new_password" id="new_password" class="form-input" required minlength="8" autocomplete="new-password">
        </div>
        <div>
            <label class="form-label" for="new_password_confirm">Confirmar nueva contraseña</label>
            <input type="password" name="new_password_confirm" id="new_password_confirm" class="form-input" required minlength="8" autocomplete="new-password">
        </div>
    </div>

    <button type="submit" class="btn btn-green mt-3">Cambiar contraseña</button>
</form>
<?php portal_layout_end();
