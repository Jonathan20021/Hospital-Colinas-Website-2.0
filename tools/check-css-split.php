<?php
/**
 * Comprueba que ninguna página se quedó sin el CSS que necesita tras repartir
 * app.css con tools/split-families-css.php.
 *
 * POR QUÉ NO BASTA LA COBERTURA
 * -----------------------------
 * Medir con CSS.startRuleUsageTracking dice qué reglas se APLICARON en la página
 * que visitaste. No dice nada de las demás. Repartiendo por familias yo mandé
 * `profile-*` en exclusiva a la ficha del médico… y resulta que la página de
 * noticia reutiliza `profile-topbar`, `profile-back` y `profile-crumbs`. La
 * medición del LISTADO de noticias no podía verlo.
 *
 * Esto lee las CLASES DEL HTML de cada página y comprueba que, para cada clase
 * de una familia repartida, exista al menos una regla en alguna de las hojas que
 * esa página carga de verdad (se leen sus propios <link>).
 *
 *   php tools/check-css-split.php [base-url]
 */

$base = $argv[1] ?? 'http://localhost/Hospital-Colinas-Website-2.0';
$raiz = dirname(__DIR__);

/** Familias repartidas: si una clase empieza por una de estas, hay que vigilarla. */
$FAMILIAS = ['profile','hero','care','capability','finder','journey','tour','task',
             'doctor','directory','dir','repo','news'];

/** Una página por plantilla. Añadir aquí cualquier plantilla nueva. */
$PAGINAS = [
    'home'          => '/',
    'directorio'    => '/directorio-medico',
    'medico'        => '/medico/alicia-rodriguez',
    'servicios'     => '/servicios',
    'servicio'      => '/servicios/cardiologia',
    'noticias'      => '/noticias',
    'noticia'       => '/noticias/las-colinas-inaugura-unidades-de-hemodialisis-y-cuidado-del-pie-diabetico',
    'repositorio'   => '/repositorio',
    'testimonios'   => '/testimonios',
    'contacto'      => '/contacto',
    'faq'           => '/preguntas-frecuentes',
    'empleos'       => '/empleos',
    'estudios'      => '/solicitar-estudios',
    'resultados'    => '/ver-resultados',
    'agendar'       => '/agendar',
];

$original = preg_replace('#/\*.*?\*/#s', '', (string) @file_get_contents($raiz . '/assets/css/app.css'));

$fallos = 0;
printf("%-14s %6s %7s  %s\n", 'pagina', 'clases', 'sin CSS', 'hojas que carga');
echo str_repeat('-', 92), "\n";

foreach ($PAGINAS as $nombre => $ruta) {
    $html = @file_get_contents($base . $ruta);
    if ($html === false) { printf("%-14s  NO RESPONDE\n", $nombre); $fallos++; continue; }

    // Hojas que la página carga de verdad, leídas de sus propios <link>.
    preg_match_all('#assets/css/([a-z0-9.\-]+)\.css#i', $html, $mh);
    $hojas = array_values(array_unique($mh[1]));
    $css = '';
    foreach ($hojas as $h) {
        $f = $raiz . '/assets/css/' . $h . '.css';
        if (is_file($f)) $css .= "\n" . file_get_contents($f);
    }
    $css = preg_replace('#/\*.*?\*/#s', '', $css);

    // Clases del HTML que pertenecen a una familia repartida.
    preg_match_all('/class="([^"]*)"/i', $html, $mc);
    $clases = [];
    foreach ($mc[1] as $lista) {
        foreach (preg_split('/\s+/', $lista) as $c) {
            $c = trim($c);
            if ($c === '') continue;
            foreach ($FAMILIAS as $fam) {
                if (str_starts_with($c, $fam . '-')) { $clases[$c] = true; break; }
            }
        }
    }
    ksort($clases);

    $sinCss = [];
    foreach (array_keys($clases) as $c) {
        $re = '/\.' . preg_quote($c, '/') . '\b/';
        // Basta con que exista UNA regla que la mencione en alguna hoja cargada.
        if (preg_match($re, $css)) continue;
        // Si TAMPOCO la tenia el app.css original, no la perdio el reparto:
        // es una clase del HTML que nunca tuvo estilos (p. ej. .repo-results).
        if (!preg_match($re, $original)) continue;
        $sinCss[] = $c;
    }

    $fallos += count($sinCss);
    printf("%-14s %6d %7d  %s\n", $nombre, count($clases), count($sinCss), implode(' ', $hojas));
    foreach ($sinCss as $c) printf("%22s *** .%s SIN REGLAS\n", '', $c);
}



/* ── Portales: no se pueden visitar sin sesion, asi que se analiza el CODIGO ──
   Es mas completo que medir en runtime: ve TODAS las pantallas, incluidas las
   que hay tras el login. Se recogen las clases literales de sus .php/.js (mas
   los includes compartidos) y se comprueba que sigan teniendo reglas en las
   hojas que el portal carga. Las clases dinamicas se construyen con prefijos
   fijos (`doctor-save-`, `dm-k-`, `scribe-`, `tool-lvl-`, `mpr-`) que este
   mismo barrido recoge por su parte literal. */
$PORTALES = [
    'portal'        => ['hojas' => ['app-core','portal','portal-accessible','portal-pwa'],
                        'src'   => ['portal/*.php','portal/*.js','assets/js/portal-*.js']],
    'portal-medico' => ['hojas' => ['app-core','portal','portal-medico','portal-medico-ui',
                                    'portal-medico-pro','portal-medico-shell'],
                        'src'   => ['portal-medico/*.php','portal-medico/*.js','assets/js/portal-medico*.js']],
];
$COMPARTIDOS = ['includes/public-layout.php','includes/content.php','includes/data.php','includes/helpers.php'];

echo "\n";
printf("%-16s %6s %7s  %s\n", 'portal (codigo)', 'clases', 'sin CSS', 'hojas');
echo str_repeat('-', 92), "\n";
foreach ($PORTALES as $nombre => $cfg) {
    $codigo = '';
    foreach (array_merge($cfg['src'], $COMPARTIDOS) as $patron) {
        foreach (glob($raiz . '/' . $patron) as $ff) { $codigo .= "\n" . file_get_contents($ff); }
    }
    $cssP = '';
    foreach ($cfg['hojas'] as $h) {
        $ff = $raiz . '/assets/css/' . $h . '.css';
        if (is_file($ff)) { $cssP .= "\n" . file_get_contents($ff); }
    }
    $cssP = preg_replace('#/\*.*?\*/#s', '', $cssP);

    preg_match_all('/\b(' . implode('|', $FAMILIAS) . ')-[a-z0-9-]+/', $codigo, $mp);
    $clasesP = array_unique($mp[0]);
    $sinCssP = [];
    foreach ($clasesP as $c) {
        $re = '/\.' . preg_quote($c, '/') . '\b/';
        if (preg_match($re, $cssP)) { continue; }
        if (!preg_match($re, $original)) { continue; }   // nunca tuvo estilos
        $sinCssP[] = $c;
    }
    $fallos += count($sinCssP);
    printf("%-16s %6d %7d  %s\n", $nombre, count($clasesP), count($sinCssP), implode(' ', $cfg['hojas']));
    foreach ($sinCssP as $c) { printf("%24s *** .%s SIN REGLAS\n", '', $c); }
}

echo str_repeat('-', 92), "\n";
if ($fallos) { echo "$fallos problemas.\n"; exit(1); }
echo "Ninguna pagina se quedo sin CSS.\n";
