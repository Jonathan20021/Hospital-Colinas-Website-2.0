<?php
require_once __DIR__ . '/_layout.php';
portal_require_login();

$token = portal_token();
$meRes   = portal_api_call('GET', '/portal/me', [], $token);
$apptRes = portal_api_call('GET', '/portal/me/appointments', ['date_from' => date('Y-m-d')], $token);
if ($meRes['ok']) portal_set_verified(!empty($meRes['data']['email_verified_at']));

$patient  = $meRes['data'] ?? [];
$upcoming = is_array($apptRes['data'] ?? null) ? $apptRes['data'] : [];
$next     = $upcoming[0] ?? null;

// Conteos para las métricas (locales = rápidos; lab puede tardar un poco)
$cnt = function (string $path, string $key) use ($token): ?int {
    $r = portal_api_call('GET', $path, [], $token);
    if (!($r['ok'] ?? false)) return null;
    return isset($r['data'][$key]) ? (int)$r['data'][$key] : null;
};
$nConsultas = $cnt('/portal/me/consultations', 'count');
$nRecetas   = $cnt('/portal/me/prescriptions', 'count');
$nLab       = $cnt('/portal/me/lab', 'count');

$pName   = (string)($patient['name'] ?? (portal_patient()['name'] ?? ''));
$friendly= trim(mb_convert_case(mb_strtolower($pName, 'UTF-8'), MB_CASE_TITLE, 'UTF-8'));
$first   = trim(explode(' ', $friendly)[0] ?? '');
$parts   = preg_split('/\s+/', trim($pName)) ?: [];
$initials= '';
foreach ($parts as $p) { if ($p !== '' && mb_strlen($initials) < 2) $initials .= mb_substr($p, 0, 1, 'UTF-8'); }
$initials= $initials !== '' ? mb_strtoupper($initials, 'UTF-8') : '?';

$h = (int)date('H');
$saludo = $h < 12 ? 'Buenos días' : ($h < 19 ? 'Buenas tardes' : 'Buenas noches');

// edad
$edad = '';
if (!empty($patient['dob'])) { $t = strtotime((string)$patient['dob']); if ($t) $edad = (int)((time() - $t) / 31557600) . ' años'; }
$sexo = ['Male' => 'M', 'Female' => 'F'][$patient['gender'] ?? ''] ?? '';
$mesesES = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];

portal_layout_begin('Inicio', 'dashboard');
?>
<div class="pa-head">
    <h1><?= e($saludo) ?><?= $first !== '' ? ', ' . e($first) : '' ?></h1>
    <p>Bienvenido a tu portal. Aquí tienes tu historial médico en un solo lugar.</p>
</div>

<div class="pa-dash">
    <!-- Perfil -->
    <div class="pa-col">
        <section class="pa-card2 pa-profile">
            <div class="pa-avatar"><?= e($initials) ?></div>
            <div class="pa-name"><?= e($friendly ?: 'Paciente') ?></div>
            <div class="pa-sub"><?= e(trim($edad . ($edad && $sexo ? ' · ' : '') . ($sexo === 'M' ? 'Masculino' : ($sexo === 'F' ? 'Femenino' : '')))) ?: 'Paciente del hospital' ?></div>
            <div class="pa-facts">
                <?php if (!empty($patient['cedula'])): ?><div class="f"><span class="k">Documento</span><span class="v"><?= e($patient['cedula']) ?></span></div><?php endif; ?>
                <?php if (!empty($patient['phone'])): ?><div class="f"><span class="k">Teléfono</span><span class="v"><?= e($patient['phone']) ?></span></div><?php endif; ?>
                <div class="f"><span class="k">Seguro</span><span class="v"><?= e($patient['insurance_provider'] ?: 'No registrado') ?></span></div>
            </div>
            <a href="<?= e(base_url('portal/perfil.php')) ?>" class="pa-btn pa-btn-soft pa-btn-block pa-btn-sm"><i data-lucide="user-cog"></i> Mi perfil</a>
        </section>
    </div>

    <!-- Próxima cita + métricas -->
    <div class="pa-col">
        <section class="pa-card2 pa-next">
            <div class="pa-card2-head" style="padding:0 0 14px"><h2><i data-lucide="calendar-clock"></i> Tu próxima cita</h2>
                <a href="<?= e(base_url('portal/mis-citas.php')) ?>" class="pa-clink">Ver todas <i data-lucide="arrow-right"></i></a></div>
            <?php if ($next): $ts = strtotime($next['appointment_time']); ?>
                <div class="when">
                    <span class="d"><?= (int)date('j', $ts) ?> <?= e($mesesES[(int)date('n', $ts)]) ?></span>
                    <span class="t"><?= e(date('H:i', $ts)) ?></span>
                    <span class="pa-status"><?= e($next['status'] === 'scheduled' ? 'Confirmada' : $next['status']) ?></span>
                </div>
                <div class="who"><?= e($next['doctor_name'] ?? 'Médico') ?> <?php if (!empty($next['specialty'])): ?><span>· <?= e($next['specialty']) ?></span><?php endif; ?></div>
                <div class="pa-timeline">
                    <div class="pa-tl"><span class="pa-tl-dot"></span><span class="pa-tl-line"></span><div><div class="l">Llega 15 minutos antes</div><div class="h">Para registrarte con tranquilidad</div></div></div>
                    <div class="pa-tl"><span class="pa-tl-dot"></span><span class="pa-tl-line"></span><div><div class="l">Trae tu cédula y carnet del seguro</div><div class="h">Te agilizan la admisión</div></div></div>
                    <div class="pa-tl"><span class="pa-tl-dot"></span><div><div class="l">Consulta con <?= e($next['doctor_name'] ?? 'tu médico') ?></div><div class="h"><?= e(date('d/m/Y', $ts)) ?> · <?= e(date('H:i', $ts)) ?></div></div></div>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:14px 0 6px">
                    <p style="color:var(--pa-ink2);font-size:1.04rem;margin:0 0 14px">No tienes citas próximas.</p>
                    <a href="<?= e(base_url('portal/agendar.php')) ?>" class="pa-btn pa-btn-green"><i data-lucide="calendar-plus"></i> Agendar una cita</a>
                </div>
            <?php endif; ?>
        </section>

        <div class="pa-stats">
            <a class="pa-stat" href="<?= e(base_url('portal/consultas.php')) ?>">
                <div class="pa-stat-top"><span class="pa-stat-ic ic-violet"><i data-lucide="stethoscope"></i></span></div>
                <div class="pa-stat-val"><?= $nConsultas !== null ? $nConsultas : '—' ?></div><div class="pa-stat-lbl">Consultas</div>
            </a>
            <a class="pa-stat" href="<?= e(base_url('portal/recetas.php')) ?>">
                <div class="pa-stat-top"><span class="pa-stat-ic ic-green"><i data-lucide="file-text"></i></span></div>
                <div class="pa-stat-val"><?= $nRecetas !== null ? $nRecetas : '—' ?></div><div class="pa-stat-lbl">Recetas</div>
            </a>
            <a class="pa-stat" href="<?= e(base_url('portal/laboratorio.php')) ?>">
                <div class="pa-stat-top"><span class="pa-stat-ic ic-teal"><i data-lucide="flask-conical"></i></span></div>
                <div class="pa-stat-val"><?= $nLab !== null ? $nLab : '—' ?></div><div class="pa-stat-lbl">Resultados de lab</div>
            </a>
            <a class="pa-stat" href="<?= e(base_url('portal/estudios.php')) ?>">
                <div class="pa-stat-top"><span class="pa-stat-ic ic-amber"><i data-lucide="scan"></i></span></div>
                <div class="pa-stat-val" style="font-size:1.1rem;padding:6px 0">Ver</div><div class="pa-stat-lbl">Mis imágenes</div>
            </a>
        </div>
    </div>
</div>

<!-- Accesos -->
<div class="pa-head" style="margin:30px 0 16px"><h1 style="font-size:1.35rem">Todo tu historial</h1></div>
<div class="pa-grid">
    <a class="pa-card" href="<?= e(base_url('portal/agendar.php')) ?>"><span class="pa-card-ic ic-green"><i data-lucide="calendar-plus"></i></span><h2>Agendar una cita</h2><p>Reserva con el especialista que necesites.</p><span class="pa-go">Abrir <i data-lucide="arrow-right"></i></span></a>
    <a class="pa-card" href="<?= e(base_url('portal/mis-citas.php')) ?>"><span class="pa-card-ic ic-blue"><i data-lucide="calendar-check"></i></span><h2>Mis citas</h2><p>Tus próximas citas y las anteriores.</p><span class="pa-go">Abrir <i data-lucide="arrow-right"></i></span></a>
    <a class="pa-card" href="<?= e(base_url('portal/consultas.php')) ?>"><span class="pa-card-ic ic-violet"><i data-lucide="stethoscope"></i></span><h2>Mis consultas</h2><p>Lo que te indicó el médico en cada visita.</p><span class="pa-go">Abrir <i data-lucide="arrow-right"></i></span></a>
    <a class="pa-card" href="<?= e(base_url('portal/recetas.php')) ?>"><span class="pa-card-ic ic-green"><i data-lucide="file-text"></i></span><h2>Mis recetas</h2><p>Verlas o descargarlas en PDF.</p><span class="pa-go">Abrir <i data-lucide="arrow-right"></i></span></a>
    <a class="pa-card" href="<?= e(base_url('portal/laboratorio.php')) ?>"><span class="pa-card-ic ic-teal"><i data-lucide="flask-conical"></i></span><h2>Resultados de laboratorio</h2><p>Tus análisis de sangre, orina y más.</p><span class="pa-go">Abrir <i data-lucide="arrow-right"></i></span></a>
    <a class="pa-card" href="<?= e(base_url('portal/estudios.php')) ?>"><span class="pa-card-ic ic-amber"><i data-lucide="scan"></i></span><h2>Mis imágenes</h2><p>Radiografías, sonografías y otros estudios.</p><span class="pa-go">Abrir <i data-lucide="arrow-right"></i></span></a>
</div>
<?php portal_layout_end();
