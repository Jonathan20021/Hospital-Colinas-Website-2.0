<?php
require_once __DIR__ . '/_layout.php';
portal_require_login();

if (!function_exists('pa_fecha_larga')) {
    function pa_fecha_larga($s): string {
        $t = strtotime((string)$s); if (!$t) return '';
        $m = [1=>'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        return (int)date('j',$t) . ' de ' . $m[(int)date('n',$t)] . ' de ' . date('Y',$t);
    }
}

$res = portal_api_call('GET', '/portal/me/prescriptions', [], portal_token());
$recetas = $res['data']['prescriptions'] ?? [];

portal_layout_begin('Mis recetas', 'recetas');
?>
<div class="pa-head">
    <h1>Mis recetas</h1>
    <p>Aquí están tus recetas. Puedes verlas o descargarlas en PDF.</p>
</div>

<?php if (!$recetas): ?>
    <div class="pa-empty">
        <div class="ic"><i data-lucide="file-text"></i></div>
        <h2>Aún no tienes recetas</h2>
        <p>Cuando un médico te emita una receta, aparecerá aquí lista para descargar.</p>
    </div>
<?php else: ?>
    <div class="pa-list">
        <?php foreach ($recetas as $r): $pdf = base_url('portal/receta-pdf.php?note=' . (int)$r['note_id']); ?>
            <div class="pa-item">
                <span class="pa-item-ic portal-item-icon-green"><i data-lucide="file-text"></i></span>
                <div class="pa-item-main">
                    <div class="t">Receta del <?= e(pa_fecha_larga($r['date'])) ?></div>
                    <div class="s"><strong><?= e($r['doctor']) ?></strong><?= $r['specialty'] ? ' · ' . e($r['specialty']) : '' ?></div>
                    <?php if (!empty($r['preview'])): ?><div class="s portal-muted-copy"><?= e($r['preview']) ?>…</div><?php endif; ?>
                </div>
                <div class="pa-item-actions">
                    <a class="pa-btn pa-btn-primary pa-btn-sm" href="<?= e($pdf) ?>" target="_blank" rel="noopener"><i data-lucide="eye"></i> Ver</a>
                    <a class="pa-btn pa-btn-soft pa-btn-sm" href="<?= e($pdf . '&dl=1') ?>"><i data-lucide="download"></i> Descargar</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php portal_layout_end();
