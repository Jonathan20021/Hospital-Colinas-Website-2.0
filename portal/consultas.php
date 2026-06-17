<?php
require_once __DIR__ . '/_layout.php';
portal_require_login();

if (!function_exists('pa_fecha_larga')) {
    function pa_fecha_larga($s): string {
        $t = strtotime((string)$s); if (!$t) return '';
        $d = ['Sunday'=>'Domingo','Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado'];
        $m = [1=>'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        return ($d[date('l',$t)] ?? '') . ' ' . (int)date('j',$t) . ' de ' . $m[(int)date('n',$t)] . ' de ' . date('Y',$t);
    }
}

$res = portal_api_call('GET', '/portal/me/consultations', [], portal_token());
$consultas = $res['data']['consultations'] ?? [];

portal_layout_begin('Mis consultas', 'consultas');
?>
<div class="pa-head">
    <h1>Mis consultas</h1>
    <p>El resumen de lo que te indicó el médico en cada visita.</p>
</div>

<?php if (!$consultas): ?>
    <div class="pa-empty">
        <div class="ic"><i data-lucide="stethoscope"></i></div>
        <h2>Aún no tienes consultas registradas</h2>
        <p>Cuando un médico registre una consulta tuya, aparecerá aquí con su diagnóstico e indicaciones.</p>
    </div>
<?php else: ?>
    <?php foreach ($consultas as $c): ?>
        <article class="pa-panel">
            <h2><i data-lucide="stethoscope"></i> <?= e(pa_fecha_larga($c['date'])) ?></h2>
            <p class="meta"><strong><?= e($c['doctor']) ?></strong><?= $c['specialty'] ? ' · ' . e($c['specialty']) : '' ?></p>
            <div class="pa-dl">
                <?php if (!empty($c['motivo'])): ?>
                    <div class="row"><div class="k">Motivo de la visita</div><div class="v"><?= e($c['motivo']) ?></div></div>
                <?php endif; ?>
                <?php if (!empty($c['diagnostico'])): ?>
                    <div class="row"><div class="k">Diagnóstico</div><div class="v"><?= e($c['diagnostico']) ?></div></div>
                <?php endif; ?>
                <?php if (!empty($c['indicaciones'])): ?>
                    <div class="row"><div class="k">Indicaciones del médico</div><div class="v"><?= e($c['indicaciones']) ?></div></div>
                <?php endif; ?>
                <?php if (!empty($c['procedimientos'])): ?>
                    <div class="row"><div class="k">Procedimientos</div><div class="v"><?= e($c['procedimientos']) ?></div></div>
                <?php endif; ?>
                <?php if (empty($c['motivo']) && empty($c['diagnostico']) && empty($c['indicaciones']) && empty($c['procedimientos'])): ?>
                    <div class="row"><div class="v" style="color:var(--pa-muted)">Esta consulta no tiene notas registradas.</div></div>
                <?php endif; ?>
            </div>
            <div class="pa-chips">
                <?php if (!empty($c['has_rx'])): ?><a class="pa-chip rx" href="<?= e(base_url('portal/recetas.php')) ?>" style="text-decoration:none">🧾 Tiene receta — verla</a><?php endif; ?>
                <?php if (!empty($c['lab_orders'])): ?><a class="pa-chip lab" href="<?= e(base_url('portal/laboratorio.php')) ?>" style="text-decoration:none">🧪 Ordenó laboratorio</a><?php endif; ?>
                <?php if (!empty($c['imaging_orders'])): ?><a class="pa-chip" href="<?= e(base_url('portal/estudios.php')) ?>" style="text-decoration:none">🩻 Ordenó imágenes</a><?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
<?php portal_layout_end();
