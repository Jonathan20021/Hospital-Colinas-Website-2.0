<?php
/**
 * Google Analytics 4 (gtag.js) — SOLO el sitio público de marketing.
 *
 * NO se incluye en los portales del paciente/médico ni en el admin: esas páginas
 * manejan datos clínicos (PHI) y sus URLs (con cédulas, tokens, ids) NO deben
 * enviarse a Google (Ley 172-13 / buenas prácticas de portales de salud).
 * Tampoco se pone en las páginas con token en la URL (verificar-receta/-certificado,
 * teleconsulta) — esas simplemente no incluyen este archivo.
 *
 * Además, aquí mismo hay dos candados de seguridad:
 *   1) No carga en desarrollo local (para no ensuciar las métricas).
 *   2) No carga bajo /portal, /portal-medico ni /admin, por si se incluye por error.
 */

$GA_MEASUREMENT_ID = 'G-9PZP9MV8M3';

$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$uri  = strtolower((string) ($_SERVER['REQUEST_URI'] ?? ''));

$isLocal = $host === ''
    || str_contains($host, 'localhost')
    || str_contains($host, '127.0.0.1')
    || str_starts_with($host, '192.168.')
    || str_ends_with($host, '.local')
    || str_ends_with($host, '.test');

$isSensitivePath = false;
foreach (['/portal', '/portal-medico', '/admin'] as $p) {
    if ($uri === $p || str_starts_with($uri, $p . '/')) {
        $isSensitivePath = true;
        break;
    }
}

if (!$isLocal && !$isSensitivePath):
?>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= $GA_MEASUREMENT_ID ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '<?= $GA_MEASUREMENT_ID ?>');
</script>
<?php endif; ?>
