<?php
/**
 * Parte assets/css/app.css en un nucleo + trozos por pagina.
 *
 *   php tools/split-css.php --dry     # informa, no escribe
 *   php tools/split-css.php           # escribe el nucleo y los app-<trozo>.css
 *
 * POR QUE: app.css es el UNICO recurso que bloquea el pintado (106 KB con gzip,
 * 1248 ms) y arrastra en todas las paginas el CSS del directorio, del perfil
 * medico, del repositorio, etc.
 *
 * COMO DECIDE (propiedad real, no prefijos a ojo):
 *   1. Se indexa cada nombre de clase -> en que plantillas/JS aparece.
 *   2. Para cada regla, sus "duenos" son la union de los duenos de sus clases.
 *   3. Una regla se mueve a un trozo solo si TODOS sus duenos son plantillas que
 *      van a cargar ese trozo.
 * Con eso `.btn` (que sale en todas partes) se queda en el nucleo, y
 * `.doctor-card` —que usan el home, el directorio, agendar y el portal— tambien,
 * aunque el prefijo pareciera de una sola pagina.
 *
 * Reglas cuyas clases no aparecen en NINGUN sitio: se quedan en el nucleo. Puede
 * ser CSS muerto, pero tambien markup que inyecta el JS; no se borran a ciegas.
 *
 * @font-face, @keyframes y :root se quedan SIEMPRE en el nucleo.
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$raiz = dirname(__DIR__);
$src  = $raiz . '/assets/css/app.css';
$dry  = in_array('--dry', $argv, true);

/**
 * Trozos: nombre => plantillas que lo cargaran.
 * Si una regla pertenece SOLO a estas plantillas, se puede mover.
 */
$TROZOS = [
    'home'        => ['index.php'],
    'directorio'  => ['directorio-medico.php', 'empleo.php', 'empleos.php'],
    // 'perfil' se quedo FUERA a proposito: eran 2 KB y al separarlos el perfil
    // del medico se desplazaba 1-2px en 67 elementos. No compensa.
    'noticias'    => ['noticias.php', 'noticia.php'],
    'repositorio' => ['repositorio.php'],
    'contenido'   => ['pagina.php', 'servicio.php', 'seguros-page.php', 'leadership-page.php'],
    'testimonios' => ['testimonios.php'],
    'agendar'     => ['agendar.php'],
];

/** Plantillas compartidas: lo que salga aqui vale para TODAS las paginas. */
const COMPARTIDAS = ['public-layout.php', 'widget-colinas-ai.php', 'app.js', 'colinas-ai.js', 'content.php', 'helpers.php'];

/* ---------- 1. Indice clase -> plantillas ---------- */
$fuentes = array_merge(
    glob($raiz . '/*.php'),
    glob($raiz . '/includes/*.php'),
    glob($raiz . '/assets/js/*.js'),
    glob($raiz . '/portal/*.php'),
    glob($raiz . '/portal-medico/*.php')
);

$duenosDeClase = [];
foreach ($fuentes as $f) {
    $base = basename($f);
    $txt  = file_get_contents($f);
    // Cualquier token que parezca un nombre de clase dentro de atributos o strings.
    if (preg_match_all('/[a-zA-Z][a-zA-Z0-9_-]{2,}/', $txt, $m)) {
        foreach (array_unique($m[0]) as $tok) {
            $duenosDeClase[$tok][$base] = true;
        }
    }
}

/* ---------- 2. Tokenizador ---------- */
function css_items(string $css): array
{
    $items = []; $n = strlen($css); $i = 0; $ini = 0; $prof = 0;
    $enCom = false; $enStr = false; $q = '';
    while ($i < $n) {
        $c = $css[$i]; $s = $i + 1 < $n ? $css[$i + 1] : '';
        if ($enCom) { if ($c === '*' && $s === '/') { $enCom = false; $i++; } $i++; continue; }
        if ($enStr) { if ($c === '\\') { $i += 2; continue; } if ($c === $q) $enStr = false; $i++; continue; }
        if ($c === '/' && $s === '*') { $enCom = true; $i += 2; continue; }
        if ($c === '"' || $c === "'") { $enStr = true; $q = $c; $i++; continue; }
        if ($c === '{') { $prof++; $i++; continue; }
        if ($c === '}') { $prof--; $i++; if ($prof === 0) { $items[] = substr($css, $ini, $i - $ini); $ini = $i; } continue; }
        $i++;
    }
    if ($ini < $n && trim(substr($css, $ini)) !== '') $items[] = substr($css, $ini);
    return $items;
}

/* ---------- 3. Duenos de una regla ---------- */
function duenos_de(string $selector, array $idx): ?array
{
    if (!preg_match_all('/\.([a-zA-Z][a-zA-Z0-9_-]*)/', $selector, $m)) return null; // sin clases: nucleo
    $duenos = [];
    $algunaConocida = false;
    foreach (array_unique($m[1]) as $cl) {
        if (!isset($idx[$cl])) continue;          // clase que no aparece en ningun sitio
        $algunaConocida = true;
        foreach (array_keys($idx[$cl]) as $f) $duenos[$f] = true;
    }
    if (!$algunaConocida) return null;            // no se sabe: al nucleo
    return array_keys($duenos);
}

/* ---------- 4. Reparto ---------- */
$css = file_get_contents($src);
$items = css_items($css);

$nucleo = ''; $salida = []; $stats = ['nucleo' => 0];
foreach (array_keys($TROZOS) as $t) { $salida[$t] = ''; $stats[$t] = 0; }

/* Selectores repetidos: no se mueven nunca, porque separarlos cambiaria cual
   de las copias gana. Hay que contar TAMBIEN las que viven dentro de @media.
   Se cuenta CADA PARTE del selector por separado, no la cadena entera:
   `.directory-page .dir-hero-copy` y
   `.directory-page .dir-hero-copy, .directory-page .dir-hero-visual`
   son cadenas distintas pero apuntan al mismo elemento; comparando cadenas
   completas se colaban las dos y la del trozo, al cargar despues, ganaba. */
$vistos = [];
$contar = function (array $lista) use (&$contar, &$vistos) {
    foreach ($lista as $it) {
        $t = ltrim($it);
        if (preg_match('/^(@(?:media|supports)[^{]*)\{(.*)\}\s*$/s', $t, $mm)) {
            $contar(css_items($mm[2]));
            continue;
        }
        if ($t !== '' && $t[0] === '@') continue;
        if (preg_match('/^\s*([^@{}]+)\{/s', $it, $m)) {
            foreach (explode(',', $m[1]) as $parte) {
                $p = trim(preg_replace('/\s+/', ' ', $parte));
                if ($p !== '') $vistos[$p] = ($vistos[$p] ?? 0) + 1;
            }
        }
    }
};
$contar($items);
$dup = array_flip(array_keys(array_filter($vistos, fn($v) => $v > 1)));

$destinoDe = function (string $bloque) use ($TROZOS, $duenosDeClase, $dup): ?string {
    if (!preg_match('/^\s*([^@{}]+)\{/s', $bloque, $m)) return null;
    $sel = trim(preg_replace('/\s+/', ' ', $m[1]));

    // Si CUALQUIERA de las partes del selector aparece tambien en otra regla,
    // la regla no se mueve: separarlas cambiaria cual gana.
    foreach (explode(',', $m[1]) as $parte) {
        $p = trim(preg_replace('/\s+/', ' ', $parte));
        if ($p !== '' && isset($dup[$p])) return null;
    }

    $duenos = duenos_de($sel, $duenosDeClase);
    if ($duenos === null || !$duenos) return null;

    // Si algun dueno es una plantilla compartida, la regla vale para todo el sitio.
    foreach ($duenos as $d) if (in_array($d, COMPARTIDAS, true)) return null;

    foreach ($TROZOS as $nombre => $plantillas) {
        $todos = true;
        foreach ($duenos as $d) if (!in_array($d, $plantillas, true)) { $todos = false; break; }
        if ($todos) return $nombre;
    }
    return null;
};

foreach ($items as $it) {
    $trim = ltrim($it);
    if (preg_match('/^(@(?:media|supports)[^{]*)\{(.*)\}\s*$/s', $trim, $m)) {
        $cab = rtrim($m[1]);
        $porTrozo = []; $quedan = '';
        foreach (css_items($m[2]) as $sub) {
            $d = $destinoDe($sub);
            if ($d === null) $quedan .= $sub; else $porTrozo[$d] = ($porTrozo[$d] ?? '') . $sub;
        }
        if (trim($quedan) !== '') { $nucleo .= "\n$cab {" . $quedan . "\n}\n"; $stats['nucleo'] += strlen($quedan); }
        foreach ($porTrozo as $t => $cuerpo) { $salida[$t] .= "\n$cab {" . $cuerpo . "\n}\n"; $stats[$t] += strlen($cuerpo); }
        continue;
    }
    if ($trim !== '' && $trim[0] === '@') { $nucleo .= $it; $stats['nucleo'] += strlen($it); continue; }

    $d = $destinoDe($it);
    if ($d === null) { $nucleo .= $it; $stats['nucleo'] += strlen($it); }
    else { $salida[$d] .= $it; $stats[$d] += strlen($it); }
}

/* ---------- 5. Informe ---------- */
printf("%-14s %9s\n", 'DESTINO', 'KB');
echo str_repeat('-', 26), "\n";
printf("%-14s %8.0fK   (antes)\n", 'app.css', strlen($css) / 1024);
echo str_repeat('-', 26), "\n";
printf("%-14s %8.0fK\n", 'nucleo', $stats['nucleo'] / 1024);
$mov = 0;
foreach (array_keys($TROZOS) as $t) { printf("%-14s %8.0fK\n", 'app-' . $t, $stats[$t] / 1024); $mov += $stats[$t]; }
printf("\nMovido fuera del nucleo: %.0f KB (%d%%)\n", $mov / 1024, round($mov * 100 / strlen($css)));
printf("Selectores repetidos que no se mueven: %d\n", count($dup));

if ($dry) { echo "\n(--dry: no se ha escrito nada)\n"; exit; }

$aviso = "/* Generado por tools/split-css.php desde app.css. No editar a mano:\n"
       . "   los cambios van en app.css y despues se vuelve a ejecutar el script. */\n";
file_put_contents($src, $nucleo);
printf("\nEscrito app.css (nucleo, %.0f KB)\n", filesize($src) / 1024);
foreach ($salida as $t => $cuerpo) {
    if (trim($cuerpo) === '') continue;
    $f = $raiz . "/assets/css/app-$t.css";
    file_put_contents($f, $aviso . $cuerpo);
    printf("Escrito app-%s.css (%.0f KB)\n", $t, filesize($f) / 1024);
}
