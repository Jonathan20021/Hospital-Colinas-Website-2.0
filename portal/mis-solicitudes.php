<?php
/**
 * "Mis solicitudes de estudios" — el paciente ve el estado de cada solicitud de
 * autorización (imágenes/laboratorio) y, cuando el agente la cotiza, su copago /
 * restante a pagar e indicaciones. Lista vía /portal/me/study-requests.
 */
require_once __DIR__ . '/_layout.php';
portal_require_login();

$res = portal_api_call('GET', '/portal/me/study-requests', [], portal_token());
$requests = $res['data']['requests'] ?? [];
$unavailable = !$res['ok'] && ($res['status'] ?? 0) >= 500;

$STATUS = [
    'received'    => ['Recibida',           'pending'],
    'reviewing'   => ['En revisión',        'pending'],
    'authorizing' => ['En autorización',    'pending'],
    'authorized'  => ['Autorizada',         'ok'],
    'need_info'   => ['Falta información',   'warn'],
    'rejected'    => ['No cubierta',         'bad'],
    'quoted'      => ['Autorización lista',  'ok'],
    'scheduled'   => ['Visita coordinada',   'ok'],
    'closed'      => ['Cerrada',             'muted'],
    'cancelled'   => ['Cancelada',           'muted'],
];
$TYPE = ['imaging' => 'Imágenes', 'lab' => 'Laboratorio', 'both' => 'Imágenes y laboratorio'];

if (!function_exists('se_money')) {
    function se_money($v, string $cur = 'DOP'): string {
        if ($v === null || $v === '') return '';
        $sym = $cur === 'DOP' ? 'RD$' : ($cur === 'USD' ? 'US$' : '');
        return $sym . number_format((float)$v, 2, '.', ',');
    }
}

$cssVersion = (string) (@filemtime(__DIR__ . '/../assets/css/estudios.css') ?: 0);
portal_layout_begin('Mis solicitudes', 'mis-solicitudes');
?>
<link rel="stylesheet" href="<?= e(base_url('assets/css/estudios.css')) ?>?v=<?= e($cssVersion) ?>">

<div class="pa-head pa-head-row">
    <div>
        <h1>Mis solicitudes de estudios</h1>
        <p>El estado de tus autorizaciones de imágenes y laboratorio, y tu copago cuando esté listo.</p>
    </div>
    <a href="<?= e(base_url('portal/solicitar-estudios.php')) ?>" class="btn btn-green"><i data-lucide="plus"></i> Nueva solicitud</a>
</div>

<?php if ($unavailable): ?>
    <div class="pa-empty">
        <div class="ic"><i data-lucide="server-off"></i></div>
        <h2>No disponible ahora</h2>
        <p>Intenta de nuevo en unos minutos. Si el problema continúa, contáctanos.</p>
    </div>
<?php elseif (!$requests): ?>
    <div class="pa-empty">
        <div class="ic"><i data-lucide="clipboard-list"></i></div>
        <h2>Aún no tienes solicitudes</h2>
        <p>¿Tienes una orden de imágenes o laboratorio? Súbela con tu seguro y gestionamos tu autorización.</p>
        <a href="<?= e(base_url('portal/solicitar-estudios.php')) ?>" class="btn btn-green"><i data-lucide="plus"></i> Solicitar autorización</a>
    </div>
<?php else: ?>
    <div class="se-req-list">
        <?php foreach ($requests as $r):
            $st = $STATUS[$r['status'] ?? 'received'] ?? ['En proceso', 'pending'];
            $items = $r['items'] ?? [];
            $names = array_map(fn($i) => $i['name'] ?? '', $items);
            $itemsTxt = implode(', ', array_slice($names, 0, 4)) . (count($names) > 4 ? ' y ' . (count($names) - 4) . ' más' : '');
            $q = $r['quote'] ?? null;
            $balance = $q['patient_balance'] ?? null;
        ?>
            <article class="se-req">
                <header class="se-req-head">
                    <div class="se-req-id">
                        <span class="se-req-type"><i data-lucide="<?= ($r['study_type'] ?? '') === 'lab' ? 'flask-conical' : 'scan' ?>"></i> <?= e($TYPE[$r['study_type'] ?? ''] ?? 'Estudios') ?></span>
                        <span class="se-req-code"><?= e($r['public_code'] ?? ('#' . (int)($r['id'] ?? 0))) ?></span>
                    </div>
                    <span class="se-status se-status-<?= e($st[1]) ?>"><?= e($st[0]) ?></span>
                </header>

                <div class="se-req-body">
                    <?php if ($itemsTxt): ?><p class="se-req-items"><?= e($itemsTxt) ?></p><?php endif; ?>
                    <p class="se-req-meta">
                        <?php if (!empty($r['insurer'])): ?><span><i data-lucide="shield"></i> <?= e($r['insurer']) ?></span><?php endif; ?>
                        <?php if (!empty($r['created_at'])): ?><span><i data-lucide="calendar"></i> <?= e(date('d/m/Y', strtotime((string)$r['created_at']))) ?></span><?php endif; ?>
                        <?php if (!empty($r['documents_count'])): ?><span><i data-lucide="paperclip"></i> <?= (int)$r['documents_count'] ?> documento(s)</span><?php endif; ?>
                    </p>
                </div>

                <?php if ($q && ($balance !== null || !empty($q['authorization_number']))): ?>
                    <div class="se-quote">
                        <?php if ($balance !== null): ?>
                            <div class="se-quote-balance">
                                <span>Tu restante a pagar</span>
                                <strong><?= e(se_money($balance, $q['currency'] ?? 'DOP')) ?></strong>
                            </div>
                        <?php endif; ?>
                        <dl class="se-quote-grid">
                            <?php if (($q['total_amount'] ?? null) !== null): ?><div><dt>Costo del estudio</dt><dd><?= e(se_money($q['total_amount'], $q['currency'] ?? 'DOP')) ?></dd></div><?php endif; ?>
                            <?php if (($q['covered_amount'] ?? null) !== null): ?><div><dt>Cubre tu seguro</dt><dd><?= e(se_money($q['covered_amount'], $q['currency'] ?? 'DOP')) ?></dd></div><?php endif; ?>
                            <?php if (!empty($q['authorization_number'])): ?><div><dt>Nº de autorización</dt><dd><?= e($q['authorization_number']) ?></dd></div><?php endif; ?>
                            <?php if (!empty($q['valid_until'])): ?><div><dt>Válida hasta</dt><dd><?= e(date('d/m/Y', strtotime((string)$q['valid_until']))) ?></dd></div><?php endif; ?>
                        </dl>
                        <?php if (!empty($q['agent_note'])): ?>
                            <p class="se-quote-note"><i data-lucide="info"></i> <?= e($q['agent_note']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php elseif (($r['status'] ?? '') === 'need_info'): ?>
                    <p class="se-req-hint se-req-hint-warn"><i data-lucide="alert-triangle"></i> Necesitamos información o un documento adicional. Te contactaremos, o llámanos al <?= e($contact['phone'] ?? '(809) 806-0444') ?>.</p>
                <?php elseif (($r['status'] ?? '') === 'rejected'): ?>
                    <p class="se-req-hint se-req-hint-warn"><i data-lucide="x-circle"></i> Tu seguro no cubrió este estudio. Llámanos para ver opciones.</p>
                <?php else: ?>
                    <p class="se-req-hint"><i data-lucide="clock"></i> Estamos gestionando tu autorización. Aquí verás tu copago cuando esté listo.</p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php portal_layout_end();
