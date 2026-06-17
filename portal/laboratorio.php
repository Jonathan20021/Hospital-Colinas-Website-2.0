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

$res = portal_api_call('GET', '/portal/me/lab', [], portal_token());
$orders = $res['data']['orders'] ?? [];
$unavailable = !empty($res['data']['unavailable']);

portal_layout_begin('Resultados de laboratorio', 'laboratorio');
?>
<div class="pa-head">
    <h1>Resultados de laboratorio</h1>
    <p>Tus análisis de sangre, orina y otros estudios del laboratorio.</p>
</div>

<?php if ($unavailable): ?>
    <div class="pa-empty">
        <div class="ic"><i data-lucide="flask-conical"></i></div>
        <h2>El laboratorio no está disponible ahora</h2>
        <p>Intenta de nuevo en unos minutos. Si el problema continúa, contáctanos.</p>
    </div>
<?php elseif (!$orders): ?>
    <div class="pa-empty">
        <div class="ic"><i data-lucide="flask-conical"></i></div>
        <h2>Aún no tienes resultados de laboratorio</h2>
        <p>Cuando el laboratorio valide tus análisis, aparecerán aquí para verlos o descargarlos.</p>
    </div>
<?php else: ?>
    <div class="pa-list">
        <?php foreach ($orders as $o):
            $exams = $o['examenes'] ?? [];
            $exTxt = implode(', ', array_slice($exams, 0, 3)) . (count($exams) > 3 ? ' y ' . (count($exams) - 3) . ' más' : '');
            $hasRes = (int)($o['num_resultados'] ?? 0) > 0;
        ?>
            <div class="pa-item">
                <span class="pa-item-ic portal-item-icon-lab"><i data-lucide="flask-conical"></i></span>
                <div class="pa-item-main">
                    <div class="t"><?= e(pa_fecha_larga($o['fecha'])) ?></div>
                    <div class="s"><?= e($exTxt ?: ('Orden #' . (int)$o['id'])) ?></div>
                    <div class="pa-chips">
                        <?php if ($hasRes): ?>
                            <span class="pa-chip lab"><?= (int)$o['num_resultados'] ?> resultado<?= (int)$o['num_resultados'] === 1 ? '' : 's' ?></span>
                        <?php else: ?>
                            <span class="pa-chip portal-chip-pending">En proceso</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($hasRes): ?>
                    <div class="pa-item-actions">
                        <a class="pa-btn pa-btn-primary pa-btn-sm" href="<?= e(base_url('portal/resultado-lab.php?order=' . (int)$o['id'])) ?>"><i data-lucide="file-text"></i> Ver resultados</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php portal_layout_end();
