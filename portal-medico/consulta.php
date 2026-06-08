<?php
require_once __DIR__ . '/_layout.php';
doctor_require_login();

$apptId = (int)($_GET['appt'] ?? 0);
$appt = null;

if ($apptId) {
    $r = portal_api_call('GET', '/portal-doctor/me/appointments/' . $apptId, [], doctor_token());
    if ($r['ok']) {
        $appt = $r['data'];
    } else {
        doctor_flash_set('error', $r['message'] ?? 'Cita no encontrada.');
    }
}

doctor_layout_begin('Consulta médica', 'consulta');
?>

<?php if (!$appt): ?>
    <div class="doctor-empty-state">
        <div class="doctor-empty-state-ic"><i data-lucide="stethoscope"></i></div>
        <h1>Selecciona una cita para iniciar</h1>
        <p>Abre una cita desde tu agenda o desde el listado de pacientes para registrar la consulta, la receta y las indicaciones.</p>
        <div class="doctor-empty-state-actions">
            <a href="<?= e(base_url('portal-medico/agenda.php')) ?>" class="doctor-btn doctor-btn-primary"><i data-lucide="calendar-days" class="h-4 w-4"></i> Ir a la agenda</a>
            <a href="<?= e(base_url('portal-medico/pacientes.php')) ?>" class="doctor-btn doctor-btn-outline"><i data-lucide="users" class="h-4 w-4"></i> Ver pacientes</a>
        </div>
    </div>
<?php else:
    $ts = strtotime($appt['appointment_time']);
    $age = '';
    if (!empty($appt['patient_dob'])) {
        $age = (int)((new DateTime())->diff(new DateTime($appt['patient_dob']))->y);
    }
?>
    <a href="<?= e(base_url('portal-medico/agenda.php')) ?>" class="doctor-back-link"><i data-lucide="arrow-left" class="h-4 w-4"></i> Volver a la agenda</a>

    <header class="doctor-consult-header">
        <div>
            <p class="doctor-eyebrow">Consulta · <?= e(doctor_fecha_corta($ts, true)) ?></p>
            <h1><?= e($appt['patient_name']) ?></h1>
            <div class="doctor-patient-meta">
                <?php if (!empty($appt['patient_cedula'])): ?><span><i data-lucide="id-card" class="h-3.5 w-3.5"></i> <?= e($appt['patient_cedula']) ?></span><?php endif; ?>
                <?php if (!empty($appt['patient_gender'])): ?><span><i data-lucide="user" class="h-3.5 w-3.5"></i> <?= e($appt['patient_gender']) ?></span><?php endif; ?>
                <?php if ($age !== ''): ?><span><i data-lucide="cake" class="h-3.5 w-3.5"></i> <?= e($age) ?> años</span><?php endif; ?>
                <?php if (!empty($appt['patient_phone'])): ?><span><i data-lucide="phone" class="h-3.5 w-3.5"></i> <?= e($appt['patient_phone']) ?></span><?php endif; ?>
                <span class="doctor-pill doctor-pill-<?= e($appt['status']) ?>"><?= e(doctor_estado_es($appt['status'])) ?></span>
            </div>
        </div>
        <div class="doctor-consult-actions">
            <a href="<?= e(base_url('portal-medico/teleconsulta.php?appt=' . (int)$appt['id'])) ?>" class="doctor-btn doctor-btn-outline" target="_blank" rel="noopener">
                <i data-lucide="video" class="h-4 w-4"></i> Teleconsulta
            </a>
            <a href="<?= e(base_url('portal-medico/paciente.php?id=' . (int)$appt['patient_id'])) ?>" class="doctor-btn doctor-btn-outline">
                <i data-lucide="user" class="h-4 w-4"></i> Ver historial
            </a>
            <?php if ($appt['status'] === 'scheduled'): ?>
                <button type="button" class="doctor-btn doctor-btn-primary" id="btn-save-complete">
                    <i data-lucide="check-circle-2" class="h-4 w-4"></i> Guardar y completar
                </button>
            <?php endif; ?>
        </div>
    </header>

    <?php
        $cAllergies  = $appt['allergies'] ?? [];
        $cConditions = $appt['conditions'] ?? [];
        $cBlood      = $appt['patient_blood_type'] ?? '';
        $sevCls      = ['severa' => 'sev-high', 'moderada' => 'sev-mid', 'leve' => 'sev-low'];
    ?>
    <?php if ($cAllergies || $cConditions || $cBlood): ?>
    <div class="doctor-safety-banner <?= $cAllergies ? 'is-alert' : '' ?>">
        <span class="doctor-safety-ic"><i data-lucide="<?= $cAllergies ? 'alert-triangle' : 'shield-check' ?>"></i></span>
        <div class="doctor-safety-content">
            <?php if ($cAllergies): ?>
                <div class="doctor-safety-line">
                    <span class="doctor-safety-tag">Alergias</span>
                    <?php foreach ($cAllergies as $a): ?>
                        <span class="doctor-chip <?= $sevCls[$a['severity']] ?? 'sev-mid' ?>" title="<?= e(ucfirst($a['severity']) . ($a['reaction'] ? ' · ' . $a['reaction'] : '')) ?>"><?= e($a['allergen']) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($cConditions): ?>
                <div class="doctor-safety-line">
                    <span class="doctor-safety-tag">Antecedentes</span>
                    <?php foreach ($cConditions as $c): ?>
                        <span class="doctor-chip sev-cond" title="<?= e($c['note'] ?? '') ?>"><?= e($c['name']) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($cBlood): ?><span class="doctor-safety-blood" title="Tipo de sangre"><i data-lucide="droplet" class="h-3.5 w-3.5"></i> <?= e($cBlood) ?></span><?php endif; ?>
        <a href="<?= e(base_url('portal-medico/paciente.php?id=' . (int)$appt['patient_id'])) ?>" class="doctor-safety-edit" title="Gestionar en la ficha del paciente"><i data-lucide="pencil" class="h-3.5 w-3.5"></i></a>
    </div>
    <?php endif; ?>

    <form id="consult-form" class="doctor-consult-form">
        <input type="hidden" name="appointment_id" value="<?= (int)$appt['id'] ?>">

        <?php $v = $appt['vitals'] ?? []; ?>
        <section class="doctor-card">
            <header class="doctor-card-header"><h2><i data-lucide="activity" class="h-5 w-5"></i> Signos vitales</h2></header>
            <div class="doctor-form-pad doctor-vitals-form">
                <label>T/A (mmHg)<span class="doctor-vital-ta"><input type="number" class="doctor-input" name="systolic" min="40" max="300" placeholder="Sis" value="<?= e($v['systolic'] ?? '') ?>"><b>/</b><input type="number" class="doctor-input" name="diastolic" min="20" max="200" placeholder="Dia" value="<?= e($v['diastolic'] ?? '') ?>"></span></label>
                <label>FC (lpm)<input type="number" class="doctor-input" name="heart_rate" min="20" max="300" value="<?= e($v['heart_rate'] ?? '') ?>"></label>
                <label>FR (rpm)<input type="number" class="doctor-input" name="resp_rate" min="5" max="80" value="<?= e($v['resp_rate'] ?? '') ?>"></label>
                <label>Temp (°C)<input type="number" step="0.1" class="doctor-input" name="temperature" min="30" max="45" value="<?= e($v['temperature'] ?? '') ?>"></label>
                <label>SatO₂ (%)<input type="number" class="doctor-input" name="spo2" min="50" max="100" value="<?= e($v['spo2'] ?? '') ?>"></label>
                <label>Peso (kg)<input type="number" step="0.1" class="doctor-input" name="weight_kg" id="v-weight" min="0" max="400" value="<?= e($v['weight_kg'] ?? '') ?>"></label>
                <label>Talla (cm)<input type="number" step="0.1" class="doctor-input" name="height_cm" id="v-height" min="0" max="260" value="<?= e($v['height_cm'] ?? '') ?>"></label>
                <label>IMC<input type="text" class="doctor-input" id="v-bmi" readonly placeholder="—" value="<?= e($v['bmi'] ?? '') ?>"></label>
            </div>
        </section>

        <section class="doctor-card mt-4">
            <header class="doctor-card-header"><h2><i data-lucide="message-square" class="h-5 w-5"></i> Motivo de consulta</h2></header>
            <div class="doctor-form-pad">
                <textarea name="chief_complaint" class="doctor-input" rows="2" placeholder="Síntoma principal o razón de la visita"><?= e($appt['chief_complaint'] ?? '') ?></textarea>
            </div>
        </section>

        <section class="doctor-card mt-4">
            <header class="doctor-card-header"><h2><i data-lucide="clipboard-list" class="h-5 w-5"></i> Diagnóstico</h2></header>
            <div class="doctor-form-pad">
                <div class="doctor-ac" data-ac-target="diagnosis" data-ac-ep="/portal-doctor/me/cie10">
                    <span class="doctor-ac-ic"><i data-lucide="search" class="h-4 w-4"></i></span>
                    <input type="text" class="doctor-input" placeholder="Buscar diagnóstico CIE-10 (código o nombre)…" data-ac-input autocomplete="off">
                    <div class="doctor-ac-list" data-ac-list hidden></div>
                </div>
                <textarea name="diagnosis" id="dx-field" class="doctor-input" rows="3" placeholder="Diagnóstico clínico, códigos CIE, etc."><?= e($appt['diagnosis'] ?? '') ?></textarea>
            </div>
        </section>

        <div id="rx-allergy-alert" class="doctor-rx-alert mt-4" hidden></div>

        <section class="doctor-grid-2 mt-4">
            <div class="doctor-card">
                <header class="doctor-card-header"><h2><i data-lucide="pill" class="h-5 w-5"></i> Receta médica</h2></header>
                <div class="doctor-form-pad">
                    <div class="doctor-ac" data-ac-target="prescription" data-ac-ep="/portal-doctor/me/medications">
                        <span class="doctor-ac-ic"><i data-lucide="search" class="h-4 w-4"></i></span>
                        <input type="text" class="doctor-input" placeholder="Buscar medicamento del vademécum…" data-ac-input autocomplete="off">
                        <div class="doctor-ac-list" data-ac-list hidden></div>
                    </div>
                    <div class="doctor-rx-tools">
                        <select class="doctor-input" id="rx-tpl-sel" aria-label="Aplicar plantilla"><option value="">▾ Aplicar plantilla…</option></select>
                        <button type="button" class="doctor-btn doctor-btn-outline" id="rx-tpl-save"><i data-lucide="bookmark-plus" class="h-4 w-4"></i> Guardar plantilla</button>
                        <button type="button" class="doctor-btn doctor-btn-ghost" id="rx-tpl-del" hidden title="Eliminar plantilla seleccionada"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                    </div>
                    <textarea name="prescription" class="doctor-input" rows="6" placeholder="Medicamento - dosis - vía - frecuencia - duración"><?= e($appt['prescription'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="doctor-card">
                <header class="doctor-card-header"><h2><i data-lucide="flask-conical" class="h-5 w-5"></i> Laboratorios</h2></header>
                <div class="doctor-form-pad">
                    <textarea name="lab_orders" class="doctor-input" rows="6" placeholder="Pruebas de laboratorio solicitadas"><?= e($appt['lab_orders'] ?? '') ?></textarea>
                </div>
            </div>
        </section>

        <section class="doctor-grid-2 mt-4">
            <div class="doctor-card">
                <header class="doctor-card-header"><h2><i data-lucide="scan" class="h-5 w-5"></i> Imágenes</h2></header>
                <div class="doctor-form-pad">
                    <textarea name="imaging_orders" class="doctor-input" rows="4" placeholder="Radiografías, ecografías, RM, TAC..."><?= e($appt['imaging_orders'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="doctor-card">
                <header class="doctor-card-header"><h2><i data-lucide="syringe" class="h-5 w-5"></i> Procedimientos / interconsultas</h2></header>
                <div class="doctor-form-pad">
                    <textarea name="procedures" class="doctor-input" rows="4" placeholder="Procedimientos, referimientos, interconsultas"><?= e($appt['procedures'] ?? '') ?></textarea>
                </div>
            </div>
        </section>

        <section class="doctor-card mt-4">
            <header class="doctor-card-header"><h2><i data-lucide="file-text" class="h-5 w-5"></i> Notas adicionales</h2></header>
            <div class="doctor-form-pad">
                <textarea name="notes" class="doctor-input" rows="3" placeholder="Observaciones, plan, evolución..."><?= e($appt['note_notes'] ?? '') ?></textarea>
            </div>
        </section>

    </form>

    <div class="doctor-sticky-save">
        <span id="save-status" class="doctor-save-status"></span>
        <div class="doctor-consult-actions">
            <button type="button" class="doctor-btn doctor-btn-outline" id="btn-save">
                <i data-lucide="save" class="h-4 w-4"></i> Guardar nota
            </button>
            <?php if ($appt['status'] === 'scheduled'): ?>
                <button type="button" class="doctor-btn doctor-btn-primary" id="btn-save-complete-bottom">
                    <i data-lucide="check-circle-2" class="h-4 w-4"></i> Guardar y completar cita
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php $hasNote = !empty($appt['note_id']); ?>
    <section class="doctor-card mt-4" id="docs-section">
        <header class="doctor-card-header">
            <h2><i data-lucide="file-output" class="h-5 w-5"></i> Generar documentos</h2>
            <span class="doctor-pdf-theme" title="Color del documento">
                <label><input type="radio" name="pdf-theme" value="bw" checked> B/N</label>
                <label><input type="radio" name="pdf-theme" value="color"> Color</label>
            </span>
        </header>
        <?php if (!$hasNote): ?>
            <div class="doctor-pdf-hint">
                <i data-lucide="info" class="h-4 w-4"></i>
                <span>La receta y las indicaciones se habilitan al <strong>guardar la nota</strong>. La <strong>constancia</strong> está disponible en cualquier momento.</span>
            </div>
        <?php endif; ?>
        <div class="doctor-form-pad doctor-pdf-grid">
            <?php
            $docTypes = [
                ['rx', 'pill', 'Receta médica', 'Prescripción de medicamentos'],
                ['diagnosis_lab', 'clipboard-list', 'Diagnóstico + Laboratorios', 'Orden de pruebas de laboratorio'],
                ['lab', 'scan', 'Imágenes', 'Rx, ecografía, RM, TAC'],
                ['procedures', 'syringe', 'Procedimientos', 'Interconsultas y referimientos'],
            ];
            foreach ($docTypes as [$t, $ic, $title, $sub]):
                $href = e(base_url('portal-medico/documento.php?appt=' . (int)$appt['id'] . '&type=' . $t));
            ?>
                <?php if ($hasNote): ?>
                    <a class="doctor-pdf-card js-pdf-link" data-type="<?= $t ?>" target="_blank" rel="noopener" href="<?= $href ?>">
                        <i data-lucide="<?= $ic ?>" class="h-6 w-6"></i>
                        <div><strong><?= $title ?></strong><span><?= $sub ?></span></div>
                    </a>
                <?php else: ?>
                    <span class="doctor-pdf-card is-disabled" aria-disabled="true" title="Guarda la nota para habilitar este documento">
                        <i data-lucide="<?= $ic ?>" class="h-6 w-6"></i>
                        <div><strong><?= $title ?></strong><span>Disponible al guardar la nota</span></div>
                        <i data-lucide="lock" class="h-4 w-4 doctor-pdf-lock"></i>
                    </span>
                <?php endif; ?>
            <?php endforeach; ?>
            <a class="doctor-pdf-card doctor-pdf-card-accent js-pdf-link" data-type="constancia" target="_blank" rel="noopener" href="<?= e(base_url('portal-medico/documento.php?appt=' . (int)$appt['id'] . '&type=constancia')) ?>">
                <i data-lucide="file-check-2" class="h-6 w-6"></i>
                <div><strong>Constancia médica</strong><span>Comprobante de asistencia</span></div>
            </a>
        </div>
    </section>
    <script>
    document.querySelectorAll('input[name="pdf-theme"]').forEach(r => {
        r.addEventListener('change', () => {
            const theme = document.querySelector('input[name="pdf-theme"]:checked').value;
            document.querySelectorAll('.js-pdf-link').forEach(a => {
                const url = new URL(a.href, location.origin);
                url.searchParams.set('theme', theme);
                a.href = url.toString();
            });
        });
    });
    </script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('consult-form');
    if (!form) return;
    const status = document.getElementById('save-status');

    // IMC en vivo a partir de peso/talla
    const vW = document.getElementById('v-weight');
    const vH = document.getElementById('v-height');
    const vB = document.getElementById('v-bmi');
    function calcBmi() {
        const w = parseFloat(vW?.value), h = parseFloat(vH?.value);
        if (vB) vB.value = (w > 0 && h > 0) ? (w / Math.pow(h / 100, 2)).toFixed(1) : '';
    }
    vW?.addEventListener('input', calcBmi);
    vH?.addEventListener('input', calcBmi);

    // ── Alerta de interacción: alergia del paciente ↔ receta ──
    const rxField = form.querySelector('textarea[name="prescription"]');
    const rxAlert = document.getElementById('rx-allergy-alert');
    const allergies = <?= json_encode(array_values(array_map(fn($a) => ['allergen' => $a['allergen'], 'severity' => $a['severity']], $appt['allergies'] ?? [])), JSON_UNESCAPED_UNICODE) ?>;
    const FAM = {
        penicilina: ['penicilina','amoxicilina','ampicilina','dicloxacilina','cloxacilina','oxacilina','piperacilina','clavulanico','augmentin'],
        cefalosporina: ['cefalexina','cefadroxilo','cefuroxima','ceftriaxona','cefotaxima','cefixima','cefepime','cefaclor'],
        sulfa: ['sulfa','sulfas','sulfametoxazol','trimetoprim','cotrimoxazol','bactrim','sulfadiazina'],
        aine: ['aine','aines','ibuprofeno','naproxeno','diclofenaco','ketorolaco','aspirina','acido acetilsalicilico','meloxicam','piroxicam','indometacina','celecoxib','ketoprofeno'],
        dipirona: ['dipirona','metamizol','novalgina'],
        macrolido: ['azitromicina','claritromicina','eritromicina'],
        quinolona: ['ciprofloxacino','levofloxacino','moxifloxacino','norfloxacino','ofloxacino'],
        opioide: ['morfina','tramadol','codeina','fentanilo','meperidina']
    };
    // Familias/principios para detectar interacciones en el texto de la receta
    const IKEYS = {
        aine: FAM.aine, macrolido: FAM.macrolido, quinolona: FAM.quinolona, sulfa: FAM.sulfa, opioide: FAM.opioide,
        isrs: ['fluoxetina','sertralina','paroxetina','escitalopram','citalopram','fluvoxamina'],
        estatina: ['atorvastatina','simvastatina','rosuvastatina','lovastatina','pravastatina'],
        ieca: ['enalapril','lisinopril','captopril','ramipril','perindopril','losartan','valsartan','irbesartan','telmisartan'],
        nitrato: ['nitroglicerina','isosorbide','dinitrato','mononitrato'],
        benzodiacepina: ['diazepam','alprazolam','clonazepam','lorazepam','midazolam','bromazepam'],
        warfarina: ['warfarina'], digoxina: ['digoxina'], amiodarona: ['amiodarona'], clopidogrel: ['clopidogrel'],
        omeprazol: ['omeprazol'], metotrexato: ['metotrexato','methotrexate'], litio: ['litio'],
        espironolactona: ['espironolactona'], potasio: ['cloruro de potasio','gluconato de potasio'],
        sildenafil: ['sildenafil','tadalafil','vardenafil'], tramadol: ['tramadol']
    };
    const INTERACTIONS = [
        ['warfarina','aine','mayor riesgo de sangrado'], ['warfarina','macrolido','aumenta el INR (sangrado)'],
        ['warfarina','quinolona','aumenta el INR (sangrado)'], ['warfarina','sulfa','aumenta el INR (sangrado)'],
        ['ieca','espironolactona','hiperpotasemia'], ['ieca','aine','daño renal / menor efecto'],
        ['tramadol','isrs','síndrome serotoninérgico'], ['isrs','aine','mayor riesgo de sangrado'],
        ['nitrato','sildenafil','hipotensión grave'], ['digoxina','macrolido','toxicidad por digoxina'],
        ['digoxina','amiodarona','toxicidad por digoxina'], ['estatina','macrolido','riesgo de rabdomiólisis'],
        ['clopidogrel','omeprazol','reduce el efecto de clopidogrel'], ['metotrexato','aine','toxicidad por metotrexato'],
        ['litio','aine','toxicidad por litio'], ['litio','ieca','toxicidad por litio'],
        ['espironolactona','potasio','hiperpotasemia'], ['benzodiacepina','opioide','depresión respiratoria']
    ];
    const norm = s => (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').trim();
    const present = (txt, key) => (IKEYS[key] || []).some(m => { const mn = norm(m); return mn.length >= 4 && txt.includes(mn); });
    const watch = allergies.map(a => {
        const an = norm(a.allergen);
        const terms = new Set([an]);
        if (an.length >= 4) {
            for (const fam in FAM) {
                const members = FAM[fam].map(norm);
                if (an === norm(fam) || members.some(m => an.includes(m) || m.includes(an))) {
                    members.forEach(m => terms.add(m));
                    terms.add(norm(fam));
                }
            }
        }
        return { allergen: a.allergen, severity: a.severity, terms: [...terms].filter(t => t && t.length >= 4) };
    });
    function checkRx() {
        if (!rxAlert) return;
        const txt = norm(rxField && rxField.value);
        let html = '';
        if (txt) {
            const aHits = [];
            watch.forEach(w => { const m = w.terms.find(t => txt.includes(t)); if (m) aHits.push({ allergen: w.allergen, severity: w.severity, match: m }); });
            const iHits = [];
            INTERACTIONS.forEach(([a, b, note]) => { if (present(txt, a) && present(txt, b)) iHits.push({ a, b, note }); });
            if (aHits.length) {
                html += '<div class="doctor-rx-row alert"><i data-lucide="alert-octagon"></i><div><strong>Posible conflicto con una alergia</strong>'
                    + aHits.map(h => '<span>La receta menciona <b>' + h.match + '</b>; el paciente es alérgico a <b>' + h.allergen + '</b> (' + h.severity + ').</span>').join('') + '</div></div>';
            }
            if (iHits.length) {
                html += '<div class="doctor-rx-row warn"><i data-lucide="git-merge"></i><div><strong>Posible interacción entre medicamentos</strong>'
                    + iHits.map(h => '<span><b>' + h.a + '</b> + <b>' + h.b + '</b>: ' + h.note + '.</span>').join('') + '</div></div>';
            }
        }
        rxAlert.dataset.hit = html ? '1' : '';
        rxAlert.hidden = !html;
        rxAlert.innerHTML = html;
        if (html && window.lucide) window.lucide.createIcons();
    }
    rxField?.addEventListener('input', checkRx);
    checkRx();

    // ── Autocompletado (CIE-10 en diagnóstico, medicamentos en receta) ──
    function attachAC(wrap) {
        const input = wrap.querySelector('[data-ac-input]');
        const list = wrap.querySelector('[data-ac-list]');
        const ep = wrap.dataset.acEp;
        const target = form.querySelector('[name="' + wrap.dataset.acTarget + '"]');
        const isCie = ep.indexOf('cie10') !== -1;
        const escA = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        let t, items = [];
        const close = () => { list.hidden = true; list.innerHTML = ''; };
        const render = it => isCie
            ? '<b>' + escA(it.code) + '</b> ' + escA(it.name)
            : '<b>' + escA(it.name) + '</b>' + (it.presentacion ? ' · ' + escA(it.presentacion) : '') + (it.principio ? ' <span class="doctor-ac-sub">(' + escA(it.principio) + ')</span>' : '');
        const lineFor = it => isCie
            ? it.code + ' - ' + it.name
            : it.name + (it.presentacion ? ' (' + it.presentacion + ')' : '') + ' - ';
        input.addEventListener('input', () => {
            clearTimeout(t);
            const q = input.value.trim();
            if (q.length < 2) { close(); return; }
            t = setTimeout(async () => {
                const r = await window.doctorApi('GET', ep, { q });
                items = (r.ok && Array.isArray(r.data)) ? r.data : [];
                list.innerHTML = items.length
                    ? items.map((it, i) => '<button type="button" class="doctor-ac-item" data-i="' + i + '">' + render(it) + '</button>').join('')
                    : '<div class="doctor-ac-empty">Sin resultados</div>';
                list.hidden = false;
            }, 250);
        });
        list.addEventListener('click', e => {
            const b = e.target.closest('[data-i]');
            if (!b || !target) return;
            const it = items[+b.dataset.i];
            const cur = target.value.trim();
            target.value = (cur ? cur + '\n' : '') + lineFor(it);
            input.value = ''; close(); target.focus();
            if (typeof checkRx === 'function') checkRx();
        });
        input.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
        document.addEventListener('click', e => { if (!wrap.contains(e.target)) close(); });
    }
    document.querySelectorAll('[data-ac-target]').forEach(attachAC);

    // ── Plantillas de receta (propias del médico) ──
    const tplSel = document.getElementById('rx-tpl-sel');
    const tplSave = document.getElementById('rx-tpl-save');
    const tplDel = document.getElementById('rx-tpl-del');
    let rxTpls = [];
    const escT = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    function renderTpls() {
        if (!tplSel) return;
        tplSel.innerHTML = '<option value="">▾ Aplicar plantilla…</option>' + rxTpls.map(t => '<option value="' + t.id + '">' + escT(t.name) + '</option>').join('');
        if (tplDel) tplDel.hidden = true;
    }
    async function loadTpls() {
        const r = await window.doctorApi('GET', '/portal-doctor/me/rx-templates');
        rxTpls = (r.ok && Array.isArray(r.data)) ? r.data : [];
        renderTpls();
    }
    tplSel?.addEventListener('change', () => {
        const t = rxTpls.find(x => String(x.id) === tplSel.value);
        if (tplDel) tplDel.hidden = !tplSel.value;
        if (!t || !rxField) return;
        const cur = rxField.value.trim();
        rxField.value = (cur ? cur + '\n' : '') + t.body;
        checkRx();
    });
    tplSave?.addEventListener('click', async () => {
        const body = (rxField && rxField.value.trim()) || '';
        if (!body) { alert('La receta está vacía.'); return; }
        const name = prompt('Nombre de la plantilla (p.ej. "Faringitis adulto"):');
        if (!name || !name.trim()) return;
        const r = await window.doctorApi('POST', '/portal-doctor/me/rx-templates', { name: name.trim(), body });
        if (r.ok && Array.isArray(r.data)) { rxTpls = r.data; renderTpls(); }
        else alert(r.message || 'No se pudo guardar la plantilla.');
    });
    tplDel?.addEventListener('click', async () => {
        if (!tplSel.value) return;
        if (!confirm('¿Eliminar esta plantilla?')) return;
        const r = await window.doctorApi('DELETE', '/portal-doctor/me/rx-templates/' + tplSel.value);
        if (r.ok && Array.isArray(r.data)) { rxTpls = r.data; renderTpls(); }
    });
    loadTpls();

    async function save(andComplete) {
        if (rxAlert && rxAlert.dataset.hit === '1' &&
            !confirm('⚠ La receta tiene una alerta de seguridad (alergia o interacción). ¿Deseas guardar de todos modos?')) return;
        const fd = new FormData(form);
        const data = {};
        fd.forEach((v, k) => { data[k] = v; });

        window.doctorAutoSaveHint(status, 'saving');
        const r = await window.doctorApi('POST', '/portal-doctor/me/notes', data);
        if (!r.ok) {
            window.doctorAutoSaveHint(status, 'error');
            alert(r.message || 'Error al guardar.');
            return;
        }
        if (andComplete) {
            const c = await window.doctorApi('POST', '/portal-doctor/me/appointments/<?= (int)($appt['id'] ?? 0) ?>/complete', {});
            if (!c.ok) {
                alert(c.message || 'Nota guardada, pero no se pudo completar la cita.');
                return;
            }
            location.reload();
        } else {
            window.doctorAutoSaveHint(status, 'saved');
        }
    }

    document.getElementById('btn-save')?.addEventListener('click', () => save(false));
    document.getElementById('btn-save-complete')?.addEventListener('click', () => save(true));
    document.getElementById('btn-save-complete-bottom')?.addEventListener('click', () => save(true));
});
</script>
<?php doctor_layout_end();
