<?php
/**
 * Reparte app.css en un núcleo y paquetes por página, por FAMILIA de selector.
 *
 * POR QUÉ
 * -------
 * `app.css` (248 KB) lo carga toda página pública, pero medido con
 * CSS.startRuleUsageTracking de Chrome cada una usa entre el 2 % y el 16 %.
 * Dentro quedaron bloques que pertenecen a UNA página y viajan a las veinte:
 *
 *     profile-*   35,7 KB  -> solo /medico/*        (80 selectores)
 *     doctor-*    17,5 KB  -> solo el home
 *     hero-*      13,2 KB  -> solo el home
 *     repo-*       6,5 KB  -> solo /repositorio
 *     news-*       5,7 KB  -> home y /noticias
 *
 * El particionador anterior las dejó en el núcleo porque atribuía cada clase a
 * UNA plantilla y estas aparecen en varias.
 *
 * CÓMO
 * ----
 * `app.css` NO se modifica: sigue siendo la fuente editable. De ella salen
 * `app-core.css` (el núcleo sin las familias repartidas) y los paquetes. Igual
 * que `app-shell.css`, hay que REGENERAR tras cualquier cambio en app.css.
 *
 * Se reparte por FAMILIA COMPLETA, no por las reglas que la medición vio
 * aplicarse: los `:hover`, `:focus` y los estados que solo existen tras un clic
 * saldrían como "no usados" y se perderían.
 *
 *   php tools/split-families-css.php [--dry]
 */

$raiz    = dirname(__DIR__);
$entrada = $raiz . '/assets/css/app.css';
$dry     = in_array('--dry', $argv, true);

/**
 * familia => paquetes donde debe acabar.
 *
 * Una familia puede ir a VARIOS paquetes (se duplica): cuesta unos bytes en esas
 * páginas, pero evita que un tercer archivo compartido vuelva a crecer sin
 * control. Lo que importa es lo que descarga CADA página.
 */
$REPARTO = [
    // Ficha del médico
    'profile'    => ['medico'],
    // Portada
    'hero'       => ['home'],
    'care'       => ['home'],
    'capability' => ['home'],
    'finder'     => ['home'],
    'journey'    => ['home'],
    'tour'       => ['home'],
    'task'       => ['home'],
    'doctor'     => ['home'],          // tarjetas de médico de la portada
    // Directorio. `dir-*` lo usa tambien /empleos, que ya carga este paquete.
    'directory'  => ['directorio'],
    'dir'        => ['directorio'],
    // Secciones propias
    'repo'       => ['repositorio'],
    'news'       => ['noticias', 'home'],
];

/** Paquete => archivo destino. Los que ya existen se AMPLÍAN, no se pisan. */
$DESTINO = [
    'home'        => 'app-home.css',
    'directorio'  => 'app-directorio.css',
    'medico'      => 'app-medico.css',      // nuevo
    'repositorio' => 'app-repositorio.css',
    'noticias'    => 'app-noticias.css',
];

/**
 * Selectores que se quedan en el NUCLEO aunque su familia se reparta.
 *
 * `.directory-card` y `.directory-grid` son la rejilla de tarjetas que la
 * portada y la ficha del medico reutilizan: 1.391 B en 9 reglas. Duplicar por
 * ellos los 43.870 B del resto de `directory-*` en otros dos paquetes seria
 * absurdo, asi que la parte compartida se queda donde la ve todo el mundo.
 */
$EXCEPCIONES = [
    '/^\.directory-(card|grid)\b/',
    // La pagina de NOTICIA reutiliza la barra superior y las migas de la ficha
    // del medico: 8.719 B en 68 reglas. Se quedan donde las ve todo el mundo.
    '/^\.profile-(topbar|back|crumbs|cta)\b/',
];

if (!is_file($entrada)) { fwrite(STDERR, "No encuentro $entrada\n"); exit(1); }
$css = file_get_contents($entrada);
$n   = strlen($css);

/** Familia a la que pertenece un selector, o null si no es de ninguna repartida. */
$familiaDe = static function (string $selector) use ($REPARTO, $EXCEPCIONES): ?string {
    $encontrada = null;
    foreach (explode(',', $selector) as $parte) {
        $p = trim(preg_replace('/\s+/', ' ', $parte));
        if ($p === '') continue;
        foreach ($EXCEPCIONES as $re) { if (preg_match($re, $p)) return null; }
        // La familia la marca la PRIMERA clase del selector: `.profile-card a`
        // pertenece a profile; `.site-header .btn` no pertenece a ninguna.
        if (!preg_match('/^\.([A-Za-z][\w-]*)/', $p, $m)) return null;
        if (!preg_match('/^([a-z]+)[-_]/', $m[1], $mm)) return null;
        $fam = $mm[1];
        if (!isset($REPARTO[$fam])) return null;
        // Si un selector mezcla familias (`.hero-x, .repo-y`) se queda en el
        // nucleo: repartirlo romperia una de las dos.
        if ($encontrada !== null && $encontrada !== $fam) return null;
        $encontrada = $fam;
    }
    return $encontrada;
};

/** Offset del '}' que cierra el '{' que empieza en $desde. */
$cierre = static function (string $s, int $desde) use ($n): int {
    $prof = 0;
    for ($j = $desde; $j < $n; $j++) {
        if ($s[$j] === '{') $prof++;
        elseif ($s[$j] === '}') { $prof--; if ($prof === 0) return $j; }
    }
    return $n - 1;
};

$nucleo   = [];
$paquetes = array_fill_keys(array_keys($DESTINO), []);
$movidos  = 0; $bytesMovidos = 0;

$i = 0;
while ($i < $n) {
    if (substr($css, $i, 2) === '/*') {
        $fin = strpos($css, '*/', $i + 2);
        $trozo = substr($css, $i, ($fin === false ? $n : $fin + 2) - $i);
        $nucleo[] = $trozo;                       // los comentarios se conservan
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
        if (in_array($tipo, ['media', 'supports', 'layer'], true)) {
            // Se reparte POR DENTRO: el envoltorio se replica en cada destino
            // que se lleve alguna regla, y el resto se queda en el nucleo.
            $interior = substr($css, $llave + 1, $fin - $llave - 1);
            $quedan = []; $llevan = array_fill_keys(array_keys($DESTINO), []);
            $k = 0; $m2 = strlen($interior);
            while ($k < $m2) {
                if (substr($interior, $k, 2) === '/*') {
                    $f = strpos($interior, '*/', $k + 2);
                    $k = ($f === false) ? $m2 : $f + 2;
                    continue;
                }
                if (ctype_space($interior[$k])) { $k++; continue; }
                $l2 = strpos($interior, '{', $k);
                if ($l2 === false) break;
                $sel2 = trim(substr($interior, $k, $l2 - $k));
                $prof = 0; $f2 = $m2 - 1;
                for ($j = $l2; $j < $m2; $j++) {
                    if ($interior[$j] === '{') $prof++;
                    elseif ($interior[$j] === '}') { $prof--; if ($prof === 0) { $f2 = $j; break; } }
                }
                $reg = substr($interior, $k, $f2 - $k + 1);
                $fam = ($sel2 !== '' && $sel2[0] === '@') ? null : $familiaDe($sel2);
                if ($fam === null) { $quedan[] = $reg; }
                else {
                    foreach ($REPARTO[$fam] as $dest) { $llevan[$dest][] = $reg; }
                    $movidos++; $bytesMovidos += strlen($reg);
                }
                $k = $f2 + 1;
            }
            if ($quedan) $nucleo[] = $cabecera . " {\n" . implode("\n", $quedan) . "\n}";
            foreach ($llevan as $dest => $regs) {
                if ($regs) $paquetes[$dest][] = $cabecera . " {\n" . implode("\n", $regs) . "\n}";
            }
        } else {
            $nucleo[] = $bloque;                  // keyframes, font-face, import…
        }
    } else {
        $fam = $familiaDe($cabecera);
        if ($fam === null) { $nucleo[] = $bloque; }
        else {
            foreach ($REPARTO[$fam] as $dest) { $paquetes[$dest][] = $bloque; }
            $movidos++; $bytesMovidos += strlen($bloque);
        }
    }
    $i = $fin + 1;
}

$aviso = static function (string $que): string {
    return "/* ============================================================================\n"
         . "   $que\n"
         . "   GENERADO por tools/split-families-css.php — NO editar a mano.\n"
         . "   La fuente es assets/css/app.css. Tras cualquier cambio ahi:\n"
         . "       php tools/split-families-css.php\n"
         . "   ========================================================================== */\n\n";
};

$core = $aviso('app-core.css — app.css sin las familias repartidas por pagina.')
      . implode("\n\n", $nucleo) . "\n";

printf("app.css        %8d B\n", strlen($css));
printf("app-core.css   %8d B   (%.0f%% del original)\n", strlen($core), strlen($core) / strlen($css) * 100);
printf("reglas movidas %8d   (%d B)\n\n", $movidos, $bytesMovidos);

$salidas = ['assets/css/app-core.css' => $core];
foreach ($DESTINO as $paquete => $archivo) {
    $ruta   = $raiz . '/assets/css/' . $archivo;
    $previo = is_file($ruta) ? file_get_contents($ruta) : '';
    // Si ya lo generamos antes, se descarta el trozo anterior para no acumular.
    $marca  = "\n\n/* ==== repartido desde app.css (split-families-css.php) ==== */\n";
    $pos    = strpos($previo, $marca);
    if ($pos !== false) $previo = substr($previo, 0, $pos);
    $nuevo  = $previo . ($paquetes[$paquete] ? $marca . implode("\n\n", $paquetes[$paquete]) . "\n" : '');
    if ($paquete === 'medico' && $previo === '') {
        $nuevo = $aviso('app-medico.css — lo que solo usa la ficha del medico.') . ltrim($nuevo);
    }
    $salidas['assets/css/' . $archivo] = $nuevo;
    printf("%-26s %8d B  (antes %d)\n", $archivo, strlen($nuevo), strlen($previo));
}

if ($dry) { echo "\n--dry: no se escribe nada.\n"; exit; }
foreach ($salidas as $rel => $contenido) { file_put_contents($raiz . '/' . $rel, $contenido); }
echo "\nEscritos " . count($salidas) . " archivos.\n";
