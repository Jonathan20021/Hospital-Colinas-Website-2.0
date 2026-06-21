<?php
require_once __DIR__ . '/_layout.php';
doctor_require_login();
doctor_layout_begin('Herramientas', 'herramientas');
?>
<header class="doctor-header" data-reveal>
    <div>
        <p class="doctor-eyebrow">Herramientas clínicas</p>
        <h1>Calculadoras médicas</h1>
        <p class="doctor-subtitle">Apoyo a la decisión, pre-llenadas con los datos de tus pacientes. Verifica siempre los valores antes de usarlos.</p>
    </div>
</header>

<div class="tool-tabs" data-reveal>
    <button type="button" class="tool-tab on" data-tab="calc"><i data-lucide="calculator"></i> Calculadoras</button>
    <button type="button" class="tool-tab" data-tab="cert"><i data-lucide="file-badge-2"></i> Certificados</button>
</div>

<div class="tool-tab-panel" id="tab-calc">

<!-- BUSCADOR DE PACIENTE (pre-llenado) -->
<section class="tool-patientbar" data-reveal data-reveal-d="1">
    <div class="tool-pb-search">
        <i data-lucide="user-search"></i>
        <input type="text" id="tool-patient-q" class="doctor-input" autocomplete="off"
               placeholder="Pre-llenar con un paciente: busca por nombre o cédula…">
        <div class="tool-pb-results" id="tool-patient-results" hidden></div>
    </div>
    <div class="tool-pb-chip" id="tool-patient-chip" hidden>
        <i data-lucide="user-check"></i>
        <div class="tool-pb-chip-info">
            <strong id="tool-patient-name">—</strong>
            <span id="tool-patient-meta">—</span>
        </div>
        <button type="button" id="tool-patient-clear" title="Quitar paciente" aria-label="Quitar paciente"><i data-lucide="x"></i></button>
    </div>
</section>

<!-- FILTROS POR CATEGORÍA -->
<div class="tool-filters" data-reveal data-reveal-d="1">
    <button type="button" class="tool-chip on" data-cat="all">Todas</button>
    <button type="button" class="tool-chip" data-cat="antro">Antropométricas</button>
    <button type="button" class="tool-chip" data-cat="renal">Renal</button>
    <button type="button" class="tool-chip" data-cat="obs">Obstetricia</button>
    <button type="button" class="tool-chip" data-cat="score">Scores de riesgo</button>
</div>

<div class="tool-grid" id="tool-grid" data-reveal data-reveal-d="2">

    <!-- IMC -->
    <article class="tool-card" data-cat="antro" data-tool="imc">
        <header class="tool-card-h">
            <div class="tool-ic"><i data-lucide="scale"></i></div>
            <div class="tool-card-t"><h3>Índice de masa corporal</h3><span class="tool-tag">Antropométrica</span></div>
        </header>
        <div class="tool-fields tool-cols-2">
            <label class="tool-f">Peso (kg)<input type="number" class="doctor-input" data-k="weight" data-fill="weight" step="0.1" min="0" max="400" inputmode="decimal"></label>
            <label class="tool-f">Talla (cm)<input type="number" class="doctor-input" data-k="height" data-fill="height" step="0.1" min="0" max="260" inputmode="decimal"></label>
        </div>
        <div class="tool-out" data-out>
            <div class="tool-out-row"><span class="tool-out-val">—</span><span class="tool-out-unit">kg/m²</span></div>
            <div class="tool-out-tag">Introduce peso y talla</div>
        </div>
        <p class="tool-note"><i data-lucide="info"></i> Clasificación OMS para adultos.</p>
    </article>

    <!-- SUPERFICIE CORPORAL -->
    <article class="tool-card" data-cat="antro" data-tool="bsa">
        <header class="tool-card-h">
            <div class="tool-ic"><i data-lucide="ruler"></i></div>
            <div class="tool-card-t"><h3>Superficie corporal</h3><span class="tool-tag">Antropométrica</span></div>
        </header>
        <div class="tool-fields tool-cols-2">
            <label class="tool-f">Peso (kg)<input type="number" class="doctor-input" data-k="weight" data-fill="weight" step="0.1" min="0" max="400" inputmode="decimal"></label>
            <label class="tool-f">Talla (cm)<input type="number" class="doctor-input" data-k="height" data-fill="height" step="0.1" min="0" max="260" inputmode="decimal"></label>
        </div>
        <div class="tool-out" data-out>
            <div class="tool-out-row"><span class="tool-out-val">—</span><span class="tool-out-unit">m²</span></div>
            <div class="tool-out-tag">Fórmula de Mosteller</div>
        </div>
        <p class="tool-note"><i data-lucide="info"></i> Útil para dosificación (quimioterapia, etc.).</p>
    </article>

    <!-- COCKCROFT-GAULT -->
    <article class="tool-card" data-cat="renal" data-tool="cg">
        <header class="tool-card-h">
            <div class="tool-ic"><i data-lucide="droplets"></i></div>
            <div class="tool-card-t"><h3>Aclaramiento de creatinina</h3><span class="tool-tag">Renal · Cockcroft-Gault</span></div>
        </header>
        <div class="tool-fields tool-cols-2">
            <label class="tool-f">Edad (años)<input type="number" class="doctor-input" data-k="age" data-fill="age" min="0" max="120" inputmode="numeric"></label>
            <label class="tool-f">Sexo
                <select class="doctor-input" data-k="sex" data-fill="sex">
                    <option value="">—</option><option value="M">Masculino</option><option value="F">Femenino</option>
                </select>
            </label>
            <label class="tool-f">Peso (kg)<input type="number" class="doctor-input" data-k="weight" data-fill="weight" step="0.1" min="0" max="400" inputmode="decimal"></label>
            <label class="tool-f">Creatinina (mg/dL)<input type="number" class="doctor-input" data-k="cr" step="0.01" min="0.1" max="25" inputmode="decimal"></label>
        </div>
        <div class="tool-out" data-out>
            <div class="tool-out-row"><span class="tool-out-val">—</span><span class="tool-out-unit">mL/min</span></div>
            <div class="tool-out-tag">Completa los 4 campos</div>
        </div>
        <p class="tool-note"><i data-lucide="info"></i> Usa peso real; valida en obesidad/edema. La creatinina se introduce manualmente.</p>
    </article>

    <!-- GESTACIÓN -->
    <article class="tool-card" data-cat="obs" data-tool="ga">
        <header class="tool-card-h">
            <div class="tool-ic"><i data-lucide="baby"></i></div>
            <div class="tool-card-t"><h3>Gestación: FPP y edad</h3><span class="tool-tag">Obstetricia · Naegele</span></div>
        </header>
        <div class="tool-fields">
            <label class="tool-f">Fecha de última menstruación (FUM)<input type="date" class="doctor-input" data-k="lmp"></label>
        </div>
        <div class="tool-out" data-out>
            <div class="tool-out-row"><span class="tool-out-val">—</span></div>
            <div class="tool-out-tag">Introduce la FUM</div>
        </div>
        <p class="tool-note"><i data-lucide="info"></i> Asume ciclos regulares de 28 días.</p>
    </article>

    <!-- CHA2DS2-VASc -->
    <article class="tool-card tool-card-wide" data-cat="score" data-tool="chads">
        <header class="tool-card-h">
            <div class="tool-ic"><i data-lucide="heart-pulse"></i></div>
            <div class="tool-card-t"><h3>CHA₂DS₂-VASc</h3><span class="tool-tag">Score · Riesgo de ACV en FA</span></div>
        </header>
        <div class="tool-fields tool-cols-2">
            <label class="tool-f">Edad (años)<input type="number" class="doctor-input" data-k="age" data-fill="age" min="0" max="120" inputmode="numeric"></label>
            <label class="tool-f">Sexo
                <select class="doctor-input" data-k="sex" data-fill="sex">
                    <option value="">—</option><option value="M">Masculino</option><option value="F">Femenino</option>
                </select>
            </label>
        </div>
        <div class="tool-checks">
            <label class="tool-check"><input type="checkbox" data-pts="1"><span>Insuficiencia cardíaca / disfunción del VI</span><b>+1</b></label>
            <label class="tool-check"><input type="checkbox" data-pts="1"><span>Hipertensión arterial</span><b>+1</b></label>
            <label class="tool-check"><input type="checkbox" data-pts="1"><span>Diabetes mellitus</span><b>+1</b></label>
            <label class="tool-check"><input type="checkbox" data-pts="2"><span>ACV / AIT / tromboembolia previa</span><b>+2</b></label>
            <label class="tool-check"><input type="checkbox" data-pts="1"><span>Enfermedad vascular (IAM, EAP, placa aórtica)</span><b>+1</b></label>
        </div>
        <div class="tool-out" data-out>
            <div class="tool-out-row"><span class="tool-out-val">0</span><span class="tool-out-unit">/ 9 puntos</span></div>
            <div class="tool-out-tag">La edad y el sexo suman automáticamente</div>
        </div>
        <p class="tool-note"><i data-lucide="info"></i> Edad ≥75 = 2 pts; 65-74 = 1 pt; sexo femenino = 1 pt.</p>
    </article>

    <!-- WELLS TVP -->
    <article class="tool-card tool-card-wide" data-cat="score" data-tool="wells">
        <header class="tool-card-h">
            <div class="tool-ic"><i data-lucide="activity"></i></div>
            <div class="tool-card-t"><h3>Wells — TVP</h3><span class="tool-tag">Score · Probabilidad de trombosis</span></div>
        </header>
        <div class="tool-checks">
            <label class="tool-check"><input type="checkbox" data-pts="1"><span>Cáncer activo (tratamiento ≤6 meses o paliativo)</span><b>+1</b></label>
            <label class="tool-check"><input type="checkbox" data-pts="1"><span>Parálisis, paresia o inmovilización reciente de MII</span><b>+1</b></label>
            <label class="tool-check"><input type="checkbox" data-pts="1"><span>Encamado &gt;3 días o cirugía mayor &lt;12 semanas</span><b>+1</b></label>
            <label class="tool-check"><input type="checkbox" data-pts="1"><span>Dolor localizado en trayecto venoso profundo</span><b>+1</b></label>
            <label class="tool-check"><input type="checkbox" data-pts="1"><span>Edema de toda la pierna</span><b>+1</b></label>
            <label class="tool-check"><input type="checkbox" data-pts="1"><span>Edema de pantorrilla &gt;3 cm vs. contralateral</span><b>+1</b></label>
            <label class="tool-check"><input type="checkbox" data-pts="1"><span>Edema con fóvea (pierna sintomática)</span><b>+1</b></label>
            <label class="tool-check"><input type="checkbox" data-pts="1"><span>Venas superficiales colaterales (no varicosas)</span><b>+1</b></label>
            <label class="tool-check"><input type="checkbox" data-pts="1"><span>TVP previa documentada</span><b>+1</b></label>
            <label class="tool-check"><input type="checkbox" data-pts="-2"><span>Diagnóstico alternativo tan o más probable que TVP</span><b>−2</b></label>
        </div>
        <div class="tool-out" data-out>
            <div class="tool-out-row"><span class="tool-out-val">0</span><span class="tool-out-unit">puntos</span></div>
            <div class="tool-out-tag">Marca los criterios presentes</div>
        </div>
        <p class="tool-note"><i data-lucide="info"></i> ≥2 puntos: TVP probable. &lt;2: improbable (considerar dímero-D).</p>
    </article>

    <!-- CURB-65 -->
    <article class="tool-card" data-cat="score" data-tool="curb">
        <header class="tool-card-h">
            <div class="tool-ic"><i data-lucide="wind"></i></div>
            <div class="tool-card-t"><h3>CURB-65</h3><span class="tool-tag">Score · Severidad de neumonía</span></div>
        </header>
        <div class="tool-fields">
            <label class="tool-f">Edad (años)<input type="number" class="doctor-input" data-k="age" data-fill="age" min="0" max="120" inputmode="numeric"></label>
        </div>
        <div class="tool-checks">
            <label class="tool-check"><input type="checkbox" data-pts="1"><span>Confusión (desorientación nueva)</span><b>+1</b></label>
            <label class="tool-check"><input type="checkbox" data-pts="1"><span>Urea &gt;7 mmol/L (BUN &gt;19 mg/dL)</span><b>+1</b></label>
            <label class="tool-check"><input type="checkbox" data-pts="1"><span>Frecuencia respiratoria ≥30/min</span><b>+1</b></label>
            <label class="tool-check"><input type="checkbox" data-pts="1"><span>TA sistólica &lt;90 o diastólica ≤60 mmHg</span><b>+1</b></label>
        </div>
        <div class="tool-out" data-out>
            <div class="tool-out-row"><span class="tool-out-val">0</span><span class="tool-out-unit">/ 5 puntos</span></div>
            <div class="tool-out-tag">Edad ≥65 suma automáticamente</div>
        </div>
        <p class="tool-note"><i data-lucide="info"></i> 0-1: ambulatorio · 2: observación · 3-5: ingreso/UCI.</p>
    </article>

</div>

<p class="tool-disclaimer" data-reveal>
    <i data-lucide="shield-alert"></i>
    Estas calculadoras son una <strong>ayuda a la decisión clínica</strong> y no sustituyen el juicio del médico tratante.
    Verifica los datos y las unidades antes de aplicarlas.
</p>
</div><!-- /#tab-calc -->

<div class="tool-tab-panel" id="tab-cert" hidden>
    <div class="dm-grid cert-grid">
        <section class="dm-panel">
            <header class="dm-card-h"><h2><i data-lucide="file-badge-2"></i> Emitir certificado</h2></header>
            <div class="cert-pad">
                <div class="cert-types" id="cert-types">
                    <button type="button" class="cert-type on" data-type="reposo"><i data-lucide="bed"></i><span>Reposo médico</span></button>
                    <button type="button" class="cert-type" data-type="asistencia"><i data-lucide="calendar-check"></i><span>Asistencia</span></button>
                    <button type="button" class="cert-type" data-type="aptitud"><i data-lucide="badge-check"></i><span>Aptitud</span></button>
                </div>

                <label class="doctor-label" style="margin-top:4px">Paciente</label>
                <div class="tool-pb-search">
                    <i data-lucide="user-search"></i>
                    <input type="text" id="cert-patient-q" class="doctor-input" autocomplete="off" placeholder="Busca por nombre o cédula…">
                    <div class="tool-pb-results" id="cert-patient-results" hidden></div>
                </div>
                <div class="tool-pb-chip" id="cert-patient-chip" hidden>
                    <i data-lucide="user-check"></i>
                    <div class="tool-pb-chip-info"><strong id="cert-patient-name">—</strong><span id="cert-patient-meta">—</span></div>
                    <button type="button" id="cert-patient-clear" aria-label="Quitar paciente"><i data-lucide="x"></i></button>
                </div>
                <label class="cert-ext-toggle"><input type="checkbox" id="cert-ext"> Paciente externo (no está en mi agenda)</label>
                <div id="cert-ext-fields" hidden>
                    <div class="tool-fields tool-cols-2">
                        <label class="tool-f">Nombre completo<input type="text" class="doctor-input" id="cert-ext-name"></label>
                        <label class="tool-f">Cédula<input type="text" class="doctor-input" id="cert-ext-ced"></label>
                    </div>
                </div>

                <div class="cert-fields" id="cert-fields"></div>

                <label class="tool-f" style="margin-top:12px">Observaciones (opcional)<textarea class="doctor-input" id="cert-obs" rows="2" placeholder="Texto adicional para el certificado"></textarea></label>

                <button type="button" class="doctor-btn doctor-btn-primary" id="cert-emit" style="margin-top:14px;width:100%;justify-content:center"><i data-lucide="file-check-2"></i> Emitir certificado</button>
                <p id="cert-status" class="doctor-save-status"></p>
            </div>
        </section>

        <section class="dm-panel dm-card">
            <header class="dm-card-h"><h2><i data-lucide="history"></i> Emitidos recientemente</h2></header>
            <div class="cert-list" id="cert-list"><div class="doctor-empty" style="padding:30px 16px"><p>Cargando…</p></div></div>
        </section>
    </div>
    <p class="tool-disclaimer">
        <i data-lucide="shield-alert"></i>
        Los certificados llevan tu <strong>firma electrónica</strong> y un <strong>QR de verificación</strong>. Revisa los datos antes de emitir; quedan registrados a tu nombre.
    </p>
</div><!-- /#tab-cert -->

<script>
window.DM_TOOLS = { proxyReady: true };
</script>
<script src="<?= e(base_url('assets/js/portal-medico-herramientas.js')) ?>?v=<?= e((string)(@filemtime(__DIR__ . '/../assets/js/portal-medico-herramientas.js') ?: time())) ?>"></script>
<?php doctor_layout_end();
