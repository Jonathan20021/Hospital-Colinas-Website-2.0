<?php
/**
 * DIAGNÓSTICO DEL PORTAL DE PACIENTES
 * --------------------------------------------------------------
 * Visítalo UNA vez desde el navegador para confirmar que todo
 * está bien configurado en este entorno. BÓRRALO después.
 *
 *   https://colinashospital.com/portal/_diagnostic.php
 *
 * Verifica:
 *   - PHP + extensiones requeridas (curl, openssl, pdo, session)
 *   - Constantes PORTAL_API_BASE / PORTAL_API_VERIFY_TLS definidas
 *   - Que el sitio puede ALCANZAR la VIP de Fortinet (red/firewall)
 *   - Que la API responde y los endpoints públicos / protegidos
 *     se comportan como deben (200 vs 401)
 *   - CORS, sesión PHP, escritura
 */

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/portal_client.php';
require_once __DIR__ . '/../includes/portal_session.php';

$checks = [];
$add = function (string $name, bool $ok, string $detail = '') use (&$checks) {
    $checks[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];
};

// ── 1. PHP + extensiones ────────────────────────────────────────────────────
$add('PHP ≥ 8.0', version_compare(PHP_VERSION, '8.0', '>='), 'Detectado: ' . PHP_VERSION);
foreach (['curl', 'openssl', 'pdo', 'pdo_mysql', 'session', 'json'] as $ext) {
    $add("Extensión PHP: $ext", extension_loaded($ext), extension_loaded($ext) ? 'cargada' : 'NO cargada');
}

// ── 2. Constantes de configuración ──────────────────────────────────────────
$base = defined('PORTAL_API_BASE') ? PORTAL_API_BASE : null;
$add('Constante PORTAL_API_BASE', $base !== null && $base !== '', $base ?: 'NO definida en includes/config.local.php');
$add('Constante PORTAL_API_VERIFY_TLS', defined('PORTAL_API_VERIFY_TLS'),
    defined('PORTAL_API_VERIFY_TLS') ? 'definida = ' . (PORTAL_API_VERIFY_TLS ? 'true' : 'false') : 'NO definida (usa true por defecto, fallará con cert autofirmado)');

// ── 3. Conectividad TCP a la VIP ────────────────────────────────────────────
$parsed = $base ? parse_url($base) : [];
$host   = $parsed['host'] ?? '';
$port   = $parsed['port'] ?? (($parsed['scheme'] ?? '') === 'https' ? 443 : 80);

// IP saliente del hosting (útil cuando hay que pedir apertura en Fortinet)
$outboundIp = '?';
$ipCh = curl_init('https://api.ipify.org');
curl_setopt_array($ipCh, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_SSL_VERIFYPEER => false]);
$ipRes = curl_exec($ipCh);
if ($ipRes && filter_var(trim($ipRes), FILTER_VALIDATE_IP)) $outboundIp = trim($ipRes);
curl_close($ipCh);

if ($host) {
    $errno = 0; $errstr = '';
    $sock = @fsockopen($host, $port, $errno, $errstr, 8);
    if ($sock) {
        fclose($sock);
        $add("Conectividad TCP a $host:$port", true, "puerto abierto. IP saliente de este servidor: $outboundIp");
    } else {
        $hint = '';
        if ($errno === 111) {
            $hint = " · Connection refused = el servidor remoto rechaza activamente (no es timeout). Causa probable: la VIP de Fortinet filtra por IP origen. Pide al equipo de TI del hospital agregar esta IP del hosting al policy de la VIP: $outboundIp → 186.149.243.228:20443.";
        } elseif ($errno === 110 || stripos($errstr, 'timed out') !== false) {
            $hint = " · Timeout = no hay respuesta. Causa probable: la IP no es alcanzable desde Internet o el firewall descarta paquetes (no responde). Verifica que la VIP esté UP y que la regla NAT esté habilitada.";
        }
        $add("Conectividad TCP a $host:$port", false, "no responde — $errstr (errno $errno). IP saliente del hosting: $outboundIp." . $hint);
    }
} else {
    $add('Conectividad TCP a la VIP', false, 'No hay host en PORTAL_API_BASE.');
}

// ── 4. Llamada a la API pública: GET /portal/specialties ────────────────────
$specRes = $base ? portal_api_call('GET', '/portal/specialties') : ['ok' => false, 'status' => 0, 'message' => 'sin PORTAL_API_BASE', 'data' => null, 'raw' => ''];
$count = is_array($specRes['data'] ?? null) ? count($specRes['data']) : 0;
$add('GET /portal/specialties responde 200 con datos',
    $specRes['ok'] && $count > 0,
    $specRes['ok']
        ? "Recibidas $count especialidades (status {$specRes['status']})"
        : "Falló (status {$specRes['status']}) — " . ($specRes['message'] ?: substr($specRes['raw'] ?: '', 0, 200)));

// ── 5. GET /portal/me sin auth debe responder 401 ───────────────────────────
$meRes = $base ? portal_api_call('GET', '/portal/me') : ['status' => 0, 'message' => 'sin PORTAL_API_BASE'];
$add('GET /portal/me sin auth responde 401 (correcto)',
    ($meRes['status'] ?? 0) === 401,
    "status {$meRes['status']} — " . ($meRes['message'] ?? ''));

// ── 6. Punto de información de la API ───────────────────────────────────────
$rootRes = $base ? portal_api_call('GET', '/') : ['ok' => false, 'data' => null, 'status' => 0, 'message' => ''];
$hasPortalModule = is_array($rootRes['data']['modules'] ?? null) && in_array('portal', $rootRes['data']['modules'], true);
$add('API expone módulo "portal" en GET /api/v1/',
    $rootRes['ok'] && $hasPortalModule,
    $rootRes['ok']
        ? ('Módulos: ' . implode(', ', $rootRes['data']['modules'] ?? []))
        : "no responde — " . ($rootRes['message'] ?? ''));

// ── 7. CORS configurado en la API ───────────────────────────────────────────
$ch = curl_init(rtrim($base ?: '', '/') . '/portal/specialties');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => true,
    CURLOPT_NOBODY         => true,
    CURLOPT_CUSTOMREQUEST  => 'OPTIONS',
    CURLOPT_HTTPHEADER     => [
        'Origin: ' . (($_SERVER['HTTPS'] ?? '') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'colinashospital.com'),
        'Access-Control-Request-Method: POST',
    ],
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
]);
$corsRaw = curl_exec($ch);
$corsStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$corsOk = $corsStatus === 204 && stripos((string)$corsRaw, 'Access-Control-Allow-Origin') !== false;
$add('CORS preflight (OPTIONS) responde 204 con cabeceras correctas',
    $corsOk,
    $corsOk ? "Status $corsStatus, cabeceras presentes" : "Status $corsStatus");

// ── 8. Sesión PHP funciona ──────────────────────────────────────────────────
portal_session_start();
$_SESSION['_diag'] = 'ok';
$add('Sesión PHP funciona', session_status() === PHP_SESSION_ACTIVE, 'Nombre de sesión: ' . session_name());

// ── 9. Permisos lectura/escritura proxy ─────────────────────────────────────
$proxy = __DIR__ . '/../api/portal-proxy.php';
$add('Existe api/portal-proxy.php', is_file($proxy), $proxy);

// ── 10. CSS y JS del portal ─────────────────────────────────────────────────
$css = __DIR__ . '/../assets/css/portal.css';
$js  = __DIR__ . '/../assets/js/portal.js';
$add('Existe assets/css/portal.css', is_file($css));
$add('Existe assets/js/portal.js',  is_file($js));

// ── 11. .htaccess routes ────────────────────────────────────────────────────
$ht = file_exists(__DIR__ . '/../.htaccess') ? file_get_contents(__DIR__ . '/../.htaccess') : '';
$add('.htaccess incluye ruta limpia /portal/...',
    strpos($ht, '^portal/([a-zA-Z0-9_\\-]+)/?$ portal/$1.php') !== false,
    strpos($ht, '^portal/([a-zA-Z0-9_\\-]+)/?$ portal/$1.php') !== false ? 'OK' : 'FALTA — sin esto las URLs limpias no funcionan');

// ── Render ──────────────────────────────────────────────────────────────────
$total = count($checks);
$pass  = count(array_filter($checks, fn($c) => $c['ok']));
$fail  = $total - $pass;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Diagnóstico - Portal de Pacientes</title>
<style>
body { font-family: ui-sans-serif, system-ui, -apple-system, sans-serif; background:#0f172a; color:#e2e8f0; padding:2rem; margin:0; }
.wrap { max-width: 900px; margin: 0 auto; }
h1 { font-size: 1.5rem; margin-bottom: .25rem; }
.summary { padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; font-weight:700; }
.summary.ok { background: #065f46; color: #d1fae5; }
.summary.fail { background: #7f1d1d; color: #fee2e2; }
.check { display: grid; grid-template-columns: 32px 1fr; gap: .75rem; padding: .85rem 1rem; border-radius: 10px; background:#1e293b; margin-bottom:.5rem; align-items:start; }
.check.ok    { border-left: 4px solid #10b981; }
.check.fail  { border-left: 4px solid #ef4444; }
.icon { font-size: 1.2rem; line-height: 1; }
.icon.ok   { color: #10b981; }
.icon.fail { color: #ef4444; }
.name { font-weight: 600; color: #f1f5f9; }
.detail { color: #94a3b8; font-size: .85rem; margin-top:.15rem; font-family: ui-monospace, monospace; }
.notice { padding: 1rem 1.25rem; background:#1e3a8a; color:#dbeafe; border-radius:10px; margin-top: 2rem; font-size: .9rem; }
.notice b { color:#fff; }
</style>
</head>
<body>
<div class="wrap">
    <h1>🩺 Diagnóstico del Portal de Pacientes</h1>
    <p style="color:#94a3b8;margin-top:.25rem">Host: <code style="color:#e2e8f0"><?= e($_SERVER['HTTP_HOST'] ?? '?') ?></code> · API: <code style="color:#e2e8f0"><?= e(portal_api_base()) ?></code></p>

    <div class="summary <?= $fail === 0 ? 'ok' : 'fail' ?>">
        <?= $fail === 0
            ? "✓ Los $total chequeos pasaron. El portal está listo para uso."
            : "✗ $fail de $total chequeos fallaron. Revisa abajo." ?>
    </div>

    <?php foreach ($checks as $c): ?>
        <div class="check <?= $c['ok'] ? 'ok' : 'fail' ?>">
            <div class="icon <?= $c['ok'] ? 'ok' : 'fail' ?>"><?= $c['ok'] ? '✓' : '✗' ?></div>
            <div>
                <div class="name"><?= e($c['name']) ?></div>
                <?php if ($c['detail']): ?><div class="detail"><?= e($c['detail']) ?></div><?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="notice">
        <b>⚠️ Borra este archivo cuando termines de verificar.</b><br>
        Expone detalles de configuración. Cuando todos los chequeos estén verdes, elimina
        <code>portal/_diagnostic.php</code> del servidor.
    </div>
</div>
</body>
</html>
