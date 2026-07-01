<?php
/**
 * Editor de documentos clínicos ("Word" del Portal Médico).
 * Redacta/pega un documento para un paciente sobre un membrete profesional
 * (HGLC + logo del médico), lo guarda en el expediente y lo exporta a PDF.
 *
 * Parámetros:  ?patient=<id>   (requerido)
 *              &appt=<id>       (opcional, liga el documento a una cita)
 *              &doc=<id>        (opcional, reabre un documento guardado)
 */
require_once __DIR__ . '/_layout.php';
doctor_require_login();

$patientId = (int)($_GET['patient'] ?? 0);
$apptId    = (int)($_GET['appt'] ?? 0);
$docId     = (int)($_GET['doc'] ?? 0);

$patient = null;
if ($patientId) {
    $pr = portal_api_call('GET', '/portal-doctor/me/patients/' . $patientId, [], doctor_token());
    if ($pr['ok']) $patient = $pr['data'];
    else doctor_flash_set('error', $pr['message'] ?? 'Paciente no encontrado.');
}

// Perfil del médico (identidad del membrete). doctor_current() da nombre/espec.
$me = portal_api_call('GET', '/portal-doctor/me', [], doctor_token());
$meData = $me['data'] ?? [];
$doc = doctor_current() ?? [];

$docName     = trim(mb_convert_case(mb_strtolower((string)($doc['name'] ?? ($meData['name'] ?? '')), 'UTF-8'), MB_CASE_TITLE, 'UTF-8'));
$docSpec     = (string)($doc['specialty'] ?? ($meData['specialty'] ?? ''));
$docSubspec  = (string)($meData['subspecialty'] ?? '');
$docExequatur   = (string)($meData['exequatur'] ?? $meData['exequátur'] ?? '');
$docColegiatura = (string)($meData['colegiatura'] ?? $meData['cmd'] ?? '');

$specLine = trim($docSpec . ($docSubspec !== '' ? ' · ' . $docSubspec : ''));

// Paciente: edad + presentación
$pName = $patient ? mb_convert_case(mb_strtolower((string)$patient['name'], 'UTF-8'), MB_CASE_TITLE, 'UTF-8') : '';
$pAge  = '';
if ($patient && !empty($patient['dob'])) {
    try { $pAge = (string)((new DateTime())->diff(new DateTime($patient['dob']))->y); } catch (\Throwable $e) {}
}

$today = doctor_fecha_es(time());

global $assets;
$hglcLogo = base_url($assets['logo'] ?? 'assets/img/logo.png');

$backHref = $apptId
    ? base_url('portal-medico/consulta.php?appt=' . $apptId)
    : ($patientId ? base_url('portal-medico/paciente.php?id=' . $patientId) : base_url('portal-medico/pacientes.php'));

doctor_layout_begin('Editor de documentos', 'consulta');
?>

<?php if (!$patient): ?>
    <div class="doctor-empty-state" data-reveal>
        <div class="doctor-empty-state-ic"><i data-lucide="file-pen-line"></i></div>
        <h1>Elige un paciente para redactar</h1>
        <p>Abre el editor desde una consulta o desde la ficha de un paciente para crear una carta, un informe o unas indicaciones con tu membrete.</p>
        <div class="doctor-empty-state-actions">
            <a href="<?= e(base_url('portal-medico/pacientes.php')) ?>" class="doctor-btn doctor-btn-primary"><i data-lucide="users" class="h-4 w-4"></i> Ver pacientes</a>
            <a href="<?= e(base_url('portal-medico/agenda.php')) ?>" class="doctor-btn doctor-btn-outline"><i data-lucide="calendar-days" class="h-4 w-4"></i> Ir a la agenda</a>
        </div>
    </div>
<?php else: ?>

<a href="<?= e($backHref) ?>" class="doctor-back-link"><i data-lucide="arrow-left" class="h-4 w-4"></i> Volver</a>

<div class="doc-ed" id="doc-ed">
    <!-- Barra de acciones -->
    <header class="doc-ed-bar" data-reveal>
        <div class="doc-ed-bar-l">
            <input type="text" id="doc-title" class="doc-ed-title" maxlength="200"
                   placeholder="Título del documento (p. ej. Carta de referimiento)" value="">
            <div class="doc-ed-ctx">
                <span><i data-lucide="user" class="h-3.5 w-3.5"></i> <?= e($pName) ?></span>
                <?php if (!empty($patient['cedula'])): ?><span><i data-lucide="id-card" class="h-3.5 w-3.5"></i> <?= e($patient['cedula']) ?></span><?php endif; ?>
                <?php if ($pAge !== ''): ?><span><i data-lucide="cake" class="h-3.5 w-3.5"></i> <?= e($pAge) ?> años</span><?php endif; ?>
                <span id="doc-status" class="doc-ed-status"></span>
            </div>
        </div>
        <div class="doc-ed-bar-r">
            <button type="button" class="doctor-btn doctor-btn-ghost" id="doc-print" title="Imprimir o guardar como PDF"><i data-lucide="printer" class="h-4 w-4"></i> Imprimir</button>
            <button type="button" class="doctor-btn doctor-btn-outline" id="doc-pdf" title="Descargar PDF con membrete"><i data-lucide="file-down" class="h-4 w-4"></i> PDF</button>
            <button type="button" class="doctor-btn doctor-btn-primary" id="doc-save"><i data-lucide="save" class="h-4 w-4"></i> Guardar en expediente</button>
        </div>
    </header>

    <!-- Herramientas: plantillas + variables -->
    <div class="doc-ed-tools" data-reveal data-reveal-d="1">
        <div class="doc-ed-tools-grp">
            <select class="doctor-input doc-ed-sel" id="doc-tpl-sel" aria-label="Aplicar plantilla"><option value="">▾ Aplicar plantilla…</option></select>
            <button type="button" class="doctor-btn doctor-btn-outline doc-ed-tbtn" id="doc-tpl-save"><i data-lucide="bookmark-plus" class="h-4 w-4"></i> Guardar como plantilla</button>
            <button type="button" class="doctor-btn doctor-btn-ghost doc-ed-tbtn" id="doc-tpl-del" hidden title="Eliminar plantilla seleccionada"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
        </div>
        <div class="doc-ed-tools-grp doc-ed-vars" id="doc-vars">
            <span class="doc-ed-vars-lbl"><i data-lucide="braces" class="h-3.5 w-3.5"></i> Insertar dato:</span>
            <button type="button" class="doc-ed-chip" data-var="{{paciente}}">Paciente</button>
            <button type="button" class="doc-ed-chip" data-var="{{cedula}}">Cédula</button>
            <button type="button" class="doc-ed-chip" data-var="{{edad}}">Edad</button>
            <button type="button" class="doc-ed-chip" data-var="{{sexo}}">Sexo</button>
            <button type="button" class="doc-ed-chip" data-var="{{fecha}}">Fecha</button>
            <button type="button" class="doc-ed-chip" data-var="{{medico}}">Médico</button>
            <button type="button" class="doc-ed-chip" data-var="{{especialidad}}">Especialidad</button>
            <button type="button" class="doc-ed-chip doc-ed-chip-fill" id="doc-fill" title="Sustituye las variables {{…}} por los datos reales de este paciente"><i data-lucide="wand-sparkles" class="h-3.5 w-3.5"></i> Rellenar datos</button>
        </div>
    </div>

    <!-- Toolbar de formato -->
    <div class="doc-ed-toolbar" id="doc-toolbar" data-reveal data-reveal-d="1">
        <div class="doc-ed-tb-grp">
            <select class="doc-ed-block" id="doc-block" aria-label="Estilo de párrafo">
                <option value="p">Texto normal</option>
                <option value="h1">Título 1</option>
                <option value="h2">Título 2</option>
                <option value="h3">Subtítulo</option>
            </select>
        </div>
        <div class="doc-ed-tb-grp">
            <button type="button" class="doc-ed-tb" data-cmd="bold" title="Negrita (Ctrl+B)"><i data-lucide="bold"></i></button>
            <button type="button" class="doc-ed-tb" data-cmd="italic" title="Cursiva (Ctrl+I)"><i data-lucide="italic"></i></button>
            <button type="button" class="doc-ed-tb" data-cmd="underline" title="Subrayado (Ctrl+U)"><i data-lucide="underline"></i></button>
            <button type="button" class="doc-ed-tb" data-cmd="strikeThrough" title="Tachado"><i data-lucide="strikethrough"></i></button>
        </div>
        <div class="doc-ed-tb-grp">
            <button type="button" class="doc-ed-tb" data-cmd="insertUnorderedList" title="Lista con viñetas"><i data-lucide="list"></i></button>
            <button type="button" class="doc-ed-tb" data-cmd="insertOrderedList" title="Lista numerada"><i data-lucide="list-ordered"></i></button>
            <button type="button" class="doc-ed-tb" data-cmd="blockquote" title="Cita / nota"><i data-lucide="quote"></i></button>
        </div>
        <div class="doc-ed-tb-grp">
            <button type="button" class="doc-ed-tb" data-align="left" title="Alinear a la izquierda"><i data-lucide="align-left"></i></button>
            <button type="button" class="doc-ed-tb" data-align="center" title="Centrar"><i data-lucide="align-center"></i></button>
            <button type="button" class="doc-ed-tb" data-align="right" title="Alinear a la derecha"><i data-lucide="align-right"></i></button>
            <button type="button" class="doc-ed-tb" data-align="justify" title="Justificar"><i data-lucide="align-justify"></i></button>
        </div>
        <div class="doc-ed-tb-grp">
            <button type="button" class="doc-ed-tb" data-cmd="insertTable" title="Insertar tabla 2×2"><i data-lucide="table"></i></button>
            <button type="button" class="doc-ed-tb" data-cmd="insertRule" title="Línea divisoria"><i data-lucide="minus"></i></button>
            <button type="button" class="doc-ed-tb" data-cmd="pageBreak" title="Salto de página"><i data-lucide="scissors-line-dashed"></i></button>
        </div>
        <div class="doc-ed-tb-grp doc-ed-tb-right">
            <button type="button" class="doc-ed-tb" data-cmd="paste-plain" title="Pegar sin formato"><i data-lucide="clipboard-type"></i></button>
            <button type="button" class="doc-ed-tb" data-cmd="removeFormat" title="Quitar formato"><i data-lucide="eraser"></i></button>
        </div>
    </div>

    <!-- Papel WYSIWYG: membrete + cuerpo + firma -->
    <div class="doc-ed-canvas">
        <div class="doc-paper" id="doc-paper">
            <div class="doc-lh">
                <div class="doc-lh-logos">
                    <div class="doc-lh-inst">
                        <img src="<?= e($hglcLogo) ?>" alt="Hospital General Las Colinas" class="doc-lh-inst-img">
                    </div>
                    <div class="doc-lh-own" id="doc-lh-own" hidden>
                        <img id="doc-lh-own-img" alt="Membrete del médico">
                    </div>
                </div>
                <div class="doc-lh-id">
                    <div class="doc-lh-name"><?= e($docName) ?></div>
                    <?php if ($specLine !== ''): ?><div class="doc-lh-spec"><?= e($specLine) ?></div><?php endif; ?>
                    <?php
                    $credParts = [];
                    if ($docExequatur !== '')   $credParts[] = 'Exequátur ' . e($docExequatur);
                    if ($docColegiatura !== '') $credParts[] = 'CMD ' . e($docColegiatura);
                    ?>
                    <?php if ($credParts): ?><div class="doc-lh-cred"><?= implode(' &nbsp;·&nbsp; ', $credParts) ?></div><?php endif; ?>
                </div>
                <div class="doc-lh-rule"></div>
            </div>

            <div class="doc-meta">
                <div class="doc-meta-l">
                    Paciente: <strong><?= e($pName) ?></strong>
                    <?php if (!empty($patient['cedula'])): ?> &nbsp;·&nbsp; Cédula: <?= e($patient['cedula']) ?><?php endif; ?>
                    <?php if ($pAge !== ''): ?> &nbsp;·&nbsp; Edad: <?= e($pAge) ?> años<?php endif; ?>
                </div>
                <div class="doc-meta-r">Santiago de los Caballeros, R.D.<br><?= e($today) ?></div>
            </div>

            <h1 class="doc-paper-title" id="doc-paper-title" data-placeholder="Título del documento">Documento clínico</h1>

            <div class="doc-body" id="doc-body" contenteditable="true" spellcheck="true"
                 data-placeholder="Escribe aquí o pega tu formato desde Word. El texto se acomoda solo sobre tu membrete…"></div>

            <div class="doc-sign">
                <div class="doc-sign-inner">
                    <img id="doc-sign-img" class="doc-sign-img" alt="Firma" hidden>
                    <div class="doc-sign-space" id="doc-sign-space"></div>
                    <div class="doc-sign-line"></div>
                    <div class="doc-sign-name"><?= e($docName) ?></div>
                    <?php if ($specLine !== ''): ?><div class="doc-sign-sub"><?= e($specLine) ?></div><?php endif; ?>
                    <?php if ($credParts): ?><div class="doc-sign-sub"><?= implode(' · ', $credParts) ?></div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="<?= e(base_url('assets/css/portal-medico-editor.css')) ?>?v=<?= e((string)(@filemtime(__DIR__ . '/../assets/css/portal-medico-editor.css') ?: time())) ?>">
<script>
window.DOC_EDITOR = {
    patient: {
        id: <?= (int)$patientId ?>,
        name: <?= json_encode($pName, JSON_UNESCAPED_UNICODE) ?>,
        cedula: <?= json_encode((string)($patient['cedula'] ?? ''), JSON_UNESCAPED_UNICODE) ?>,
        age: <?= json_encode($pAge, JSON_UNESCAPED_UNICODE) ?>,
        gender: <?= json_encode((string)($patient['gender'] ?? ''), JSON_UNESCAPED_UNICODE) ?>
    },
    apptId: <?= $apptId ?: 'null' ?>,
    docId: <?= $docId ?: 'null' ?>,
    doctor: {
        name: <?= json_encode($docName, JSON_UNESCAPED_UNICODE) ?>,
        specialty: <?= json_encode($specLine, JSON_UNESCAPED_UNICODE) ?>
    },
    today: <?= json_encode($today, JSON_UNESCAPED_UNICODE) ?>,
    pdfBase: <?= json_encode(base_url('portal-medico/documento-editor.php'), JSON_UNESCAPED_SLASHES) ?>,
    backHref: <?= json_encode($backHref, JSON_UNESCAPED_SLASHES) ?>
};
</script>
<script src="<?= e(base_url('assets/js/portal-medico-editor.js')) ?>?v=<?= e((string)(@filemtime(__DIR__ . '/../assets/js/portal-medico-editor.js') ?: time())) ?>"></script>

<?php endif; ?>
<?php doctor_layout_end();
