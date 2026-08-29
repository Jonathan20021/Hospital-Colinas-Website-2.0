<?php
/**
 * Extrae de app.css la "cáscara" del sitio: cabecera, navegación, menú móvil,
 * pie, botones, campos de formulario y las variables de :root.
 *
 * POR QUÉ
 * -------
 * `app.css` pesa 248 KB (73 KB servidos) y lo carga toda página pública, pero
 * el uso real —medido con CSS.startRuleUsageTracking de Chrome, recorriendo la
 * página entera— es del 7 % al 16 %. En `/agendar`, que es LA página que
 * convierte, es del **2 %**: usa 36 selectores, todos de la cáscara. El resto
 * son bloques de otras páginas que se quedaron en el núcleo al partir el CSS
 * (`directory-page` 36 KB, `profile-*` 24 KB, `doctor-*` 18 KB, `repo-*`,
 * `news-*`…), porque sus clases aparecen en más de una plantilla.
 *
 * CÓMO
 * ----
 * Se extrae por FAMILIA de selector, no por cobertura. La cobertura solo ve las
 * reglas que llegaron a aplicarse durante el recorrido: los estados que no
 * visité (`:hover`, `:focus`, el menú móvil abierto…) saldrían como "no usados"
 * y se perderían. Quedarse con TODA la familia `.btn*` en vez de con las reglas
 * que casaron evita justamente eso.
 *
 *   php tools/extract-shell-css.php [--dry]
 */

$raiz    = dirname(__DIR__);
$entrada = $raiz . '/assets/css/app.css';
$salida  = $raiz . '/assets/css/app-shell.css';
$dry     = in_array('--dry', $argv, true);

if (!is_file($entrada)) { fwrite(STDERR, "No encuentro $entrada\n"); exit(1); }

/**
 * Familias que forman la cáscara. Se comparan contra CADA parte del selector
 * separada por comas: basta con que UNA parte pertenezca a la cáscara para
 * conservar la regla, porque descartarla rompería esa parte.
 */
$FAMILIAS = [
    // Base del documento y variables
    '/^:root\b/', '/^html\b/', '/^body\b/', '/^\*/', '/^::selection\b/',
    '/^a\b/', '/^img\b/', '/^svg\b/', '/^button\b/', '/^input\b/', '/^h[1-6]\b/',
    // Accesibilidad y estructura
    '/^\.skip-link\b/', '/^main\s+section\[id\]/', '/^\.sr-only\b/', '/^\.visually-hidden\b/',
    // Barra superior, cabecera y navegación
    '/^\.utility-/', '/^\.site-header\b/', '/^\.brand-/', '/^\.main-nav/',
    '/^\.nav-/', '/^\.mobile-/', '/^\.menu-/', '/^\.dropdown/', '/^\.search-/',
    // Pie
    '/^\.site-footer\b/', '/^\.footer-/',
    // Controles compartidos
    '/^\.btn\b/', '/^\.btn-/', '/^\.form-/', '/^\.section-label\b/',
    // Utilidades de layout que usa la cáscara
    '/^\.container\b/', '/^\.wrap\b/', '/^\.hidden\b/',
];

$esCascara = static function (string $selector) use ($FAMILIAS): bool {
    foreach (explode(',', $selector) as $parte) {
        $p = trim(preg_replace('/\s+/', ' ', $parte));
        if ($p === '') continue;
        foreach ($FAMILIAS as $re) {
            if (preg_match($re, $p)) return true;
        }
    }
    return false;
};

$css = file_get_contents($entrada);

/* ── Recorrido por bloques de primer nivel, contando llaves ────────────────
   Un parser de verdad sería excesivo; lo que hace falta es respetar los
   @media/@supports (que anidan) y no partir una regla por la mitad. */
$fuera   = [];       // trozos conservados, en orden
$n       = strlen($css);
$i       = 0;
$kept    = 0;
$dropped = 0;

/** Devuelve el offset del '}' que cierra el '{' que empieza en $desde. */
$cierre = static function (string $s, int $desde) use ($n): int {
    $prof = 0;
    for ($j = $desde; $j < $n; $j++) {
        if ($s[$j] === '{') $prof++;
        elseif ($s[$j] === '}') { $prof--; if ($prof === 0) return $j; }
    }
    return $n - 1;
};

while ($i < $n) {
    // Comentarios de primer nivel: se descartan (el archivo lleva 5,8 KB).
    if (substr($css, $i, 2) === '/*') {
        $fin = strpos($css, '*/', $i + 2);
        $i = ($fin === false) ? $n : $fin + 2;
        continue;
    }
    if (ctype_space($css[$i])) { $i++; continue; }

    $llave = strpos($css, '{', $i);
    if ($llave === false) break;
    $cabecera = trim(substr($css, $i, $llave - $i));
    $fin      = $cierre($css, $llave);
    $bloque   = substr($css, $i, $fin - $i + 1);

    if ($cabecera !== '' && $cabecera[0] === '@') {
        $tipo = strtolower(strtok(substr($cabecera, 1), " \t\n({"));
        if (in_array($tipo, ['keyframes', '-webkit-keyframes', 'font-face', 'import', 'charset'], true)) {
            // Pequeños y con dependencias difíciles de rastrear: se conservan.
            $fuera[] = $bloque;
            $kept++;
        } elseif (in_array($tipo, ['media', 'supports', 'layer'], true)) {
            // Se filtra POR DENTRO y solo se conserva el envoltorio si queda algo.
            $interior = substr($css, $llave + 1, $fin - $llave - 1);
            $dentro   = [];
            $k = 0; $m = strlen($interior);
            while ($k < $m) {
                if (substr($interior, $k, 2) === '/*') {
                    $f = strpos($interior, '*/', $k + 2);
                    $k = ($f === false) ? $m : $f + 2;
                    continue;
                }
                if (ctype_space($interior[$k])) { $k++; continue; }
                $l2 = strpos($interior, '{', $k);
                if ($l2 === false) break;
                $sel2 = trim(substr($interior, $k, $l2 - $k));
                // cierre relativo dentro del interior
                $prof = 0; $f2 = $m - 1;
                for ($j = $l2; $j < $m; $j++) {
                    if ($interior[$j] === '{') $prof++;
                    elseif ($interior[$j] === '}') { $prof--; if ($prof === 0) { $f2 = $j; break; } }
                }
                $reg = substr($interior, $k, $f2 - $k + 1);
                if (($sel2 !== '' && $sel2[0] === '@') || $esCascara($sel2)) { $dentro[] = $reg; $kept++; }
                else $dropped++;
                $k = $f2 + 1;
            }
            if ($dentro) {
                $fuera[] = $cabecera . " {\n" . implode("\n", $dentro) . "\n}";
            }
        } else {
            $fuera[] = $bloque;
            $kept++;
        }
    } else {
        if ($esCascara($cabecera)) { $fuera[] = $bloque; $kept++; }
        else $dropped++;
    }
    $i = $fin + 1;
}

$res = "/* ============================================================================\n"
     . "   app-shell.css — GENERADO por tools/extract-shell-css.php. NO editar a mano.\n"
     . "\n"
     . "   La cascara del sitio (cabecera, nav, menu movil, pie, botones, campos y las\n"
     . "   variables de :root) extraida de app.css. La carga /agendar, que del nucleo\n"
     . "   de 248 KB solo usaba el 2 %: 36 selectores, todos de aqui.\n"
     . "\n"
     . "   Si se toca app.css hay que volver a generarlo:\n"
     . "       php tools/extract-shell-css.php\n"
     . "   ========================================================================== */\n\n"
     . implode("\n\n", $fuera) . "\n";

printf("app.css      : %7d B\n", strlen($css));
printf("app-shell.css: %7d B  (%.1f%% del original)\n", strlen($res), strlen($res) / strlen($css) * 100);
printf("reglas        : %d conservadas / %d descartadas\n", $kept, $dropped);

if ($dry) { echo "\n--dry: no se escribe nada.\n"; exit; }

file_put_contents($salida, $res);
echo "\nEscrito: $salida\n";
