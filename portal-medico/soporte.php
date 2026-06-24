<?php
require_once __DIR__ . '/_layout.php';
doctor_require_login();

$doctor = doctor_current() ?? [];
doctor_layout_begin('Soporte TI', 'soporte');
?>
<header class="doctor-header" data-reveal>
    <div>
        <p class="doctor-eyebrow">Soporte técnico · Tecnología de la Información</p>
        <h1>Reportar una incidencia</h1>
        <p class="doctor-subtitle">Reporta fallas del sistema, de los equipos de tu consultorio o una solicitud puntual. El ticket llega directo al equipo de Soporte TI con la ubicación de tu consultorio para que puedan ayudarte rápido.</p>
    </div>
</header>

<div class="dm-grid sop-grid" data-reveal data-reveal-d="1">
    <!-- COLUMNA: FORMULARIO -->
    <section class="dm-panel">
        <header class="dm-card-h"><h2><i data-lucide="life-buoy"></i> Nuevo ticket de soporte</h2></header>
        <div class="sop-pad">
            <form id="sop-form" novalidate>

                <!-- 1. TIPO DE INCIDENCIA -->
                <p class="doctor-label sop-step">1 · ¿Qué necesitas reportar?</p>
                <div class="sop-types" id="sop-types" role="radiogroup" aria-label="Tipo de incidencia">
                    <button type="button" class="sop-type on" data-cat="sistema" role="radio" aria-checked="true">
                        <i data-lucide="monitor"></i>
                        <span class="sop-type-t">Sistema</span>
                        <span class="sop-type-s">Portal, SIGMA, correo, red, impresión…</span>
                    </button>
                    <button type="button" class="sop-type" data-cat="equipo" role="radio" aria-checked="false">
                        <i data-lucide="hard-drive"></i>
                        <span class="sop-type-t">Equipo del consultorio</span>
                        <span class="sop-type-s">Computadora, impresora, teléfono, A/C…</span>
                    </button>
                    <button type="button" class="sop-type" data-cat="solicitud" role="radio" aria-checked="false">
                        <i data-lucide="clipboard-list"></i>
                        <span class="sop-type-t">Solicitud puntual</span>
                        <span class="sop-type-s">Instalación, acceso, otra petición</span>
                    </button>
                </div>

                <!-- 2. DETALLE -->
                <div class="tool-fields tool-cols-2" style="margin-top:18px">
                    <label class="tool-f">
                        <span id="sop-sub-label">Categoría específica</span>
                        <select class="doctor-input" id="sop-subcategory"></select>
                    </label>
                    <label class="tool-f">Prioridad
                        <select class="doctor-input" id="sop-priority">
                            <option value="baja">Baja — puede esperar</option>
                            <option value="media" selected>Media — me afecta el trabajo</option>
                            <option value="alta">Alta — urgente</option>
                            <option value="critica">Crítica — no puedo atender pacientes</option>
                        </select>
                    </label>
                </div>

                <label class="tool-f" style="margin-top:12px">Asunto
                    <input type="text" class="doctor-input" id="sop-subject" maxlength="160" autocomplete="off"
                           placeholder="Ej.: La impresora no imprime recetas">
                </label>

                <label class="tool-f" style="margin-top:12px">Descripción del problema
                    <textarea class="doctor-input" id="sop-description" rows="4" maxlength="2000"
                              placeholder="Describe qué pasa, desde cuándo, y qué has intentado. Mientras más detalle, más rápido te ayudamos."></textarea>
                </label>

                <!-- 3. UBICACIÓN DEL CONSULTORIO -->
                <p class="doctor-label sop-step" style="margin-top:22px">2 · ¿Dónde estás? (para que soporte llegue al lugar exacto)</p>
                <div class="tool-fields tool-cols-2">
                    <label class="tool-f">Edificio / Torre / Área
                        <input type="text" class="doctor-input" id="sop-building" maxlength="120" autocomplete="off"
                               placeholder="Ej.: Torre médica, Edificio A">
                    </label>
                    <label class="tool-f">Piso / Nivel
                        <input type="text" class="doctor-input" id="sop-floor" maxlength="60" autocomplete="off"
                               placeholder="Ej.: 3er piso">
                    </label>
                    <label class="tool-f">Consultorio / Oficina N.º
                        <input type="text" class="doctor-input" id="sop-office" maxlength="80" autocomplete="off"
                               placeholder="Ej.: Consultorio 312">
                    </label>
                    <label class="tool-f">Extensión telefónica
                        <input type="text" class="doctor-input" id="sop-ext" maxlength="40" autocomplete="off"
                               placeholder="Ej.: ext. 2312">
                    </label>
                    <label class="tool-f">Teléfono de contacto directo
                        <input type="tel" class="doctor-input" id="sop-phone" maxlength="40" autocomplete="off"
                               placeholder="Por si necesitan llamarte">
                    </label>
                    <label class="tool-f">Punto de referencia (opcional)
                        <input type="text" class="doctor-input" id="sop-reference" maxlength="255" autocomplete="off"
                               placeholder="Ej.: frente a enfermería, al lado del ascensor">
                    </label>
                </div>

                <!-- 4. FOTO OPCIONAL -->
                <p class="doctor-label sop-step" style="margin-top:22px">3 · Adjuntar foto (opcional)</p>
                <p class="doctor-subtitle" style="margin-top:0;font-size:.85rem">Una imagen del equipo dañado o de la pantalla de error ayuda mucho a soporte. (JPG/PNG, máx. 6 MB.)</p>
                <div class="sop-attach">
                    <label class="doctor-btn doctor-btn-outline" style="cursor:pointer;margin:0">
                        <i data-lucide="camera"></i> Elegir / tomar foto
                        <input type="file" id="sop-file" accept="image/png,image/jpeg" capture="environment" hidden>
                    </label>
                    <div class="sop-attach-preview" id="sop-attach-preview" hidden>
                        <img id="sop-attach-img" alt="Vista previa del adjunto">
                        <button type="button" id="sop-attach-clear" aria-label="Quitar foto"><i data-lucide="x"></i></button>
                    </div>
                </div>

                <button type="submit" class="doctor-btn doctor-btn-primary" id="sop-submit"
                        style="margin-top:20px;width:100%;justify-content:center">
                    <i data-lucide="send"></i> Enviar ticket a Soporte TI
                </button>
                <p id="sop-status" class="doctor-save-status" style="margin-top:8px"></p>
            </form>
        </div>
    </section>

    <!-- COLUMNA: MIS TICKETS + INFO -->
    <aside class="sop-side">
        <section class="dm-panel dm-card">
            <header class="dm-card-h"><h2><i data-lucide="history"></i> Mis tickets</h2></header>
            <div class="sop-list" id="sop-list">
                <div class="doctor-empty" style="padding:28px 16px"><p>Cargando…</p></div>
            </div>
        </section>

        <section class="dm-panel dm-card sop-help">
            <header class="dm-card-h"><h2><i data-lucide="info"></i> ¿Cómo funciona?</h2></header>
            <div class="sop-pad">
                <ul class="doctor-tips" style="margin:0">
                    <li><i data-lucide="check"></i> Tu ticket llega de inmediato a <strong>Soporte TI</strong> por correo, con tu nombre y ubicación.</li>
                    <li><i data-lucide="check"></i> Recibes un <strong>folio</strong> (SOP-#####) para dar seguimiento.</li>
                    <li><i data-lucide="check"></i> Si es <strong>crítico</strong>, llama además a la extensión de soporte para acelerar.</li>
                </ul>
            </div>
        </section>
    </aside>
</div>

<!-- MODAL: detalle de un ticket -->
<div id="sop-modal" class="tool-modal" hidden>
    <div class="tool-modal-backdrop" data-sop-close></div>
    <div class="tool-modal-panel" role="dialog" aria-modal="true" aria-label="Detalle del ticket">
        <header class="tool-modal-h">
            <span class="tool-modal-ic"><i data-lucide="life-buoy"></i></span>
            <div class="tool-modal-ht"><h3 class="tool-modal-title" id="sop-m-title">—</h3><span class="tool-modal-tag" id="sop-m-folio">—</span></div>
            <button type="button" class="tool-modal-close" data-sop-close aria-label="Cerrar"><i data-lucide="x"></i></button>
        </header>
        <div class="tool-modal-body" id="sop-m-body"></div>
    </div>
</div>

<style>
/* Soporte TI — estilos locales del módulo (prefijo sop-) */
.sop-grid{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);gap:18px;align-items:start}
.sop-pad{padding:18px 20px}
.sop-step{margin:0 0 10px;color:var(--hg-navy,#262161);font-weight:700}
.sop-types{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.sop-type{display:flex;flex-direction:column;align-items:flex-start;gap:4px;text-align:left;padding:14px;border:1.5px solid #e2e8f0;border-radius:14px;background:#fff;cursor:pointer;transition:border-color .18s,background .18s,box-shadow .18s}
.sop-type:hover{border-color:#cbd5e1}
.sop-type.on{border-color:var(--hg-green,#5da334);background:#f4faef;box-shadow:0 1px 0 rgba(93,163,52,.15)}
.sop-type i{width:22px;height:22px;color:var(--hg-navy,#262161)}
.sop-type.on i{color:var(--hg-green,#5da334)}
.sop-type-t{font-weight:700;font-size:.94rem;color:#0f172a}
.sop-type-s{font-size:.78rem;color:#64748b;line-height:1.3}
.sop-side{display:flex;flex-direction:column;gap:18px}
.sop-list{display:flex;flex-direction:column}
.sop-ticket{display:flex;gap:12px;align-items:flex-start;width:100%;text-align:left;padding:14px 16px;border:0;border-top:1px solid #f1f5f9;background:transparent;cursor:pointer;transition:background .15s}
.sop-list .sop-ticket:first-child{border-top:0}
.sop-ticket:hover{background:#f8fafc}
.sop-ticket-ic{flex:0 0 auto;width:34px;height:34px;border-radius:10px;display:grid;place-items:center;background:#eef2ff;color:#262161}
.sop-ticket-ic i{width:17px;height:17px}
.sop-ticket-main{min-width:0;flex:1}
.sop-ticket-subj{font-weight:600;color:#0f172a;font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sop-ticket-meta{font-size:.76rem;color:#64748b;margin-top:2px}
.sop-ticket-folio{font-variant-numeric:tabular-nums;font-weight:700;color:#262161}
.sop-pri{display:inline-block;font-size:.68rem;font-weight:700;padding:2px 7px;border-radius:999px;text-transform:uppercase;letter-spacing:.02em}
.sop-pri-baja{background:#f1f5f9;color:#475569}
.sop-pri-media{background:#e0f2fe;color:#0369a1}
.sop-pri-alta{background:#fef3c7;color:#b45309}
.sop-pri-critica{background:#fee2e2;color:#b91c1c}
.sop-attach{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.sop-attach-preview{position:relative;display:inline-block}
.sop-attach-preview img{display:block;max-width:160px;max-height:120px;border-radius:10px;border:1px solid #e2e8f0}
.sop-attach-clear,#sop-attach-clear{position:absolute;top:-8px;right:-8px;width:24px;height:24px;border-radius:999px;border:0;background:#0f172a;color:#fff;cursor:pointer;display:grid;place-items:center}
.sop-attach-clear i,#sop-attach-clear i{width:14px;height:14px}
.sop-md-row{display:flex;gap:10px;padding:9px 0;border-bottom:1px solid #f1f5f9;font-size:.9rem}
.sop-md-row .k{flex:0 0 130px;color:#64748b;font-weight:600}
.sop-md-row .v{flex:1;color:#0f172a;min-width:0;white-space:pre-wrap;overflow-wrap:anywhere}
.sop-help .doctor-tips li{align-items:flex-start}
@media (max-width:900px){
    .sop-grid{grid-template-columns:1fr}
    .sop-types{grid-template-columns:1fr}
}
</style>

<script>
window.SOP_PROFILE = {
    name: <?= json_encode((string)($doctor['name'] ?? ''), JSON_UNESCAPED_UNICODE) ?>,
    email: <?= json_encode((string)($doctor['email'] ?? ''), JSON_UNESCAPED_UNICODE) ?>,
    specialty: <?= json_encode((string)($doctor['specialty'] ?? ''), JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="<?= e(base_url('assets/js/portal-medico-soporte.js')) ?>?v=<?= e((string)(@filemtime(__DIR__ . '/../assets/js/portal-medico-soporte.js') ?: time())) ?>"></script>
<?php doctor_layout_end();
