<?php
/**
 * Genera versiones responsivas y en WebP de las fotos pesadas del sitio.
 *
 *   php tools/optimize-images.php            # solo las que hagan falta
 *   php tools/optimize-images.php --force    # regenerar todo
 *   php tools/optimize-images.php --list     # ver que haria, sin escribir
 *
 * Deja los derivados en assets/site/assets/opt/ con el patron
 * <nombre>-<ancho>.webp y <nombre>-<ancho>.jpg (respaldo para navegadores
 * viejos). Es idempotente: si el derivado es mas nuevo que el original, lo
 * salta. Los originales NO se tocan: siguen siendo la fuente.
 *
 * En las plantillas se usan con picture_tag() (includes/helpers.php).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/helpers.php';

const ANCHOS       = [768, 1280, 1920];
const CALIDAD_WEBP = 80;
const CALIDAD_JPEG = 82;

/** Fotos que se sirven en el sitio publico y pesan de mas. */
const FUENTES = [
    'assets/site/assets/DSC09992.jpg',           // hero del home: 11.2 MB
    'assets/site/assets/DSC00177-DrupFA59.jpg',
    'assets/site/assets/DSC09402-CuAoyZOa.jpg',
    'assets/site/assets/DSC09415-CLWrawks.jpg',
    'assets/site/assets/DSC09393-BPhKj8Nc.jpg',
    'assets/site/assets/DSC09599-C_i1oDLF.jpg',
    'assets/site/assets/DSC00179-Rb-eJ1cT.jpg',
    'assets/site/assets/DSC09538-CyfojmYI.jpg',   // tomografia
    'assets/site/assets/DSC09497-DLi1Fpy-.jpg',   // mamografia
    'assets/site/assets/DSC09525-vE_gZgLx.jpg',   // rayos x
    'assets/site/assets/DSC09544-CJJT60MC.jpg',   // consultorios
];

$raiz    = dirname(__DIR__);
$destRel = 'assets/site/assets/opt';
$destAbs = $raiz . '/' . $destRel;

$force = in_array('--force', $argv, true);
$list  = in_array('--list', $argv, true);

if (!$list && !is_dir($destAbs) && !@mkdir($destAbs, 0775, true)) {
    fwrite(STDERR, "No se pudo crear $destAbs\n");
    exit(1);
}

$totalOrig = 0;
$totalNuevo = 0;

foreach (FUENTES as $rel) {
    $src = $raiz . '/' . $rel;
    if (!is_file($src)) {
        printf("  SALTA  %-34s (no existe)\n", basename($rel));
        continue;
    }

    $info = @getimagesize($src);
    if (!$info) {
        printf("  SALTA  %-34s (no es una imagen legible)\n", basename($rel));
        continue;
    }

    [$anchoOrig, $altoOrig] = $info;
    $pesoOrig = filesize($src);
    $totalOrig += $pesoOrig;
    $base = pathinfo($rel, PATHINFO_FILENAME);

    printf("\n%s  (%dx%d, %.1f MB)\n", basename($rel), $anchoOrig, $altoOrig, $pesoOrig / 1048576);

    // Nunca agrandar: solo anchos menores que el original (mas el original si es menor que el mayor).
    $anchos = array_values(array_filter(ANCHOS, fn($w) => $w < $anchoOrig));
    if (!$anchos || max($anchos) < min($anchoOrig, max(ANCHOS))) {
        $anchos[] = min($anchoOrig, max(ANCHOS));
    }
    $anchos = array_values(array_unique($anchos));
    sort($anchos);

    $origen = null; // se carga solo si hay algo que generar

    foreach ($anchos as $w) {
        $h = (int) round($altoOrig * ($w / $anchoOrig));

        foreach (['webp', 'jpg'] as $fmt) {
            $outRel = "$destRel/$base-$w.$fmt";
            $outAbs = "$raiz/$outRel";

            if (!$force && is_file($outAbs) && filemtime($outAbs) >= filemtime($src)) {
                printf("    ya estaba  %-30s %7.0f KB\n", basename($outRel), filesize($outAbs) / 1024);
                $totalNuevo += filesize($outAbs);
                continue;
            }
            if ($list) {
                printf("    generaria  %-30s %dx%d\n", basename($outRel), $w, $h);
                continue;
            }

            if ($origen === null) {
                $origen = @imagecreatefromjpeg($src);
                if (!$origen) {
                    fwrite(STDERR, "    No se pudo abrir $rel\n");
                    continue 3;
                }
            }

            $dst = imagecreatetruecolor($w, $h);
            imagecopyresampled($dst, $origen, 0, 0, 0, 0, $w, $h, $anchoOrig, $altoOrig);

            $ok = $fmt === 'webp'
                ? imagewebp($dst, $outAbs, CALIDAD_WEBP)
                : imagejpeg($dst, $outAbs, CALIDAD_JPEG);
            imagedestroy($dst);

            if ($ok) {
                $peso = filesize($outAbs);
                $totalNuevo += $peso;
                printf("    OK         %-30s %7.0f KB  (%dx%d)\n", basename($outRel), $peso / 1024, $w, $h);
            } else {
                fwrite(STDERR, "    FALLO al escribir $outRel\n");
            }
        }
    }

    if ($origen) {
        imagedestroy($origen);
    }
}

printf(
    "\n─────\nOriginales: %.1f MB    Derivados: %.1f MB\n",
    $totalOrig / 1048576,
    $totalNuevo / 1048576
);
if (!$list) {
    echo "Las plantillas los usan por picture_tag(); los originales siguen intactos.\n";
}
