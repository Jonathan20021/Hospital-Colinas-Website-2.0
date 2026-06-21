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

<!-- BARRA DE ESPECIALIDAD -->
<div class="tool-specbar" id="tool-specbar" data-reveal data-reveal-d="1" hidden>
    <div class="tool-specbar-info">
        <i data-lucide="stethoscope"></i>
        <span>Herramientas de <strong id="tool-spec-name">tu especialidad</strong> y generales</span>
    </div>
    <label class="tool-spec-toggle"><input type="checkbox" id="tool-spec-all"> Ver todas las especialidades</label>
</div>

<!-- FILTROS (dinámicos, según especialidad) -->
<div class="tool-filters" id="tool-filters" data-reveal data-reveal-d="1"></div>

<!-- GRID (renderizado dinámicamente desde el catálogo) -->
<div class="tool-grid" id="tool-grid" data-reveal data-reveal-d="2"></div>

<div id="tool-empty" hidden>
    <div class="doctor-empty" style="padding:42px 18px">
        <div class="doctor-empty-illustration"><i data-lucide="search-x" class="h-7 w-7"></i></div>
        <p class="doctor-empty-title">Sin calculadoras para este filtro</p>
        <p>Activa “Ver todas las especialidades” para explorar el resto del catálogo.</p>
    </div>
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

<!-- MODAL: cada herramienta en su espacio único -->
<div id="tool-modal" class="tool-modal" hidden>
    <div class="tool-modal-backdrop" data-modal-close></div>
    <div class="tool-modal-panel" role="dialog" aria-modal="true" aria-label="Calculadora">
        <header class="tool-modal-h">
            <span class="tool-modal-ic"><i data-lucide="calculator"></i></span>
            <div class="tool-modal-ht"><h3 class="tool-modal-title">—</h3><span class="tool-modal-tag">—</span></div>
            <button type="button" class="tool-modal-close" data-modal-close aria-label="Cerrar"><i data-lucide="x"></i></button>
        </header>
        <div class="tool-modal-body"></div>
    </div>
</div>

<script>
window.DM_TOOLS = { specialty: <?= json_encode((string)(doctor_current()['specialty'] ?? ''), JSON_UNESCAPED_UNICODE) ?> };
</script>
<script src="<?= e(base_url('assets/js/portal-medico-herramientas.js')) ?>?v=<?= e((string)(@filemtime(__DIR__ . '/../assets/js/portal-medico-herramientas.js') ?: time())) ?>"></script>
<?php doctor_layout_end();
