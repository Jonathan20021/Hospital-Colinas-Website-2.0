<?php
require_once __DIR__ . '/_layout.php';
doctor_require_login();

// Cargar citas amplias (3 meses atras / 3 adelante) para el calendario.
$from = date('Y-m-d', strtotime('-90 days'));
$to   = date('Y-m-d', strtotime('+90 days'));
$apptRes = portal_api_call('GET', '/portal-doctor/me/appointments', [
    'date_from' => $from,
    'date_to'   => $to,
    'per_page'  => 500,
], doctor_token());

$appointments = $apptRes['data']['items'] ?? [];

// Construir eventos para FullCalendar
$events = [];
foreach ($appointments as $a) {
    $color = '#322d82'; // navy de marca (agendada)
    if ($a['status'] === 'completed') $color = '#5da334'; // verde de marca
    if ($a['status'] === 'cancelled') $color = '#be123c';
    $events[] = [
        'id'    => (int)$a['id'],
        'title' => $a['patient_name'],
        'start' => date('c', strtotime($a['appointment_time'])),
        'color' => $color,
        'extendedProps' => [
            'status'  => $a['status'],
            'phone'   => $a['patient_phone'] ?? '',
            'cedula'  => $a['patient_cedula'] ?? '',
            'note_id' => $a['note_id'] ?? null,
        ],
    ];
}

doctor_layout_begin('Mi agenda', 'agenda');
?>
<header class="doctor-header" data-reveal>
    <div>
        <p class="doctor-eyebrow">Mi agenda</p>
        <h1>Calendario de citas</h1>
        <p class="doctor-subtitle">Vista mensual, semanal y diaria. Haz clic en una cita para abrir la consulta.</p>
    </div>
    <div class="doctor-header-actions">
        <button type="button" class="doctor-btn doctor-btn-primary" data-nueva-cita>
            <i data-lucide="calendar-plus" class="h-4 w-4"></i> Nueva cita
        </button>
        <button type="button" class="doctor-btn doctor-btn-outline" id="filter-scheduled" data-status="">
            <i data-lucide="filter" class="h-4 w-4"></i> Todas
        </button>
    </div>
</header>

<section class="doctor-card" data-reveal data-reveal-d="1">
    <div id="doctor-agenda" data-events='<?= e(json_encode($events, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?>' style="padding: 1.25rem; min-height: 640px;"></div>
    <div class="doctor-calendar-legend">
        <span><i class="doctor-dot" style="background:#322d82"></i> Agendada</span>
        <span><i class="doctor-dot" style="background:#5da334"></i> Completada</span>
        <span><i class="doctor-dot" style="background:#be123c"></i> Cancelada</span>
    </div>
</section>

<!-- Modal de detalle de cita -->
<div id="appt-modal" class="doctor-modal" hidden>
    <div class="doctor-modal-backdrop" data-close></div>
    <div class="doctor-modal-card">
        <header class="doctor-modal-header">
            <h3 id="appt-modal-title">Detalle de la cita</h3>
            <button type="button" class="doctor-modal-close" data-close aria-label="Cerrar">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </header>
        <div class="doctor-modal-body" id="appt-modal-body">Cargando...</div>
        <footer class="doctor-modal-footer" id="appt-modal-footer"></footer>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/locales/es.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    window.openApptModal = async function (id) {
        const modal = document.getElementById('appt-modal');
        const body  = document.getElementById('appt-modal-body');
        const foot  = document.getElementById('appt-modal-footer');
        modal.hidden = false;
        body.innerHTML = '<p style="color:#64748b">Cargando...</p>';
        foot.innerHTML = '';

        const r = await window.doctorApi('GET', '/portal-doctor/me/appointments/' + id);
        if (!r.ok) {
            body.innerHTML = '<p class="doctor-flash doctor-flash-error">' + (r.message || 'Error') + '</p>';
            return;
        }
        const a = r.data;
        const dt = new Date(a.appointment_time);
        const estadoEs = { scheduled: 'Agendada', completed: 'Completada', cancelled: 'Cancelada', pending: 'Pendiente' };
        body.innerHTML = `
            <dl class="doctor-dl">
                <dt>Paciente</dt><dd><strong>${esc(a.patient_name)}</strong> ${a.patient_cedula ? '· ' + esc(a.patient_cedula) : ''}</dd>
                <dt>Teléfono</dt><dd>${esc(a.patient_phone || '—')}</dd>
                <dt>Fecha</dt><dd>${dt.toLocaleString('es-DO', {dateStyle:'full', timeStyle:'short'})}</dd>
                <dt>Estado</dt><dd><span class="doctor-pill doctor-pill-${esc(a.status)}">${esc(estadoEs[a.status] || a.status)}</span></dd>
                ${a.diagnosis ? `<dt>Diagnóstico</dt><dd>${esc(a.diagnosis)}</dd>` : ''}
                ${a.prescription ? `<dt>Receta</dt><dd style="white-space:pre-wrap">${esc(a.prescription)}</dd>` : ''}
            </dl>`;

        let actions = '';
        if (a.status === 'scheduled') {
            actions += `<button type="button" class="doctor-btn doctor-btn-outline" data-action="cancel" data-id="${a.id}"><i data-lucide="x-circle" class="h-4 w-4"></i>Cancelar</button>`;
        }
        actions += `<a class="doctor-btn doctor-btn-primary" href="/portal-medico/consulta.php?appt=${a.id}"><i data-lucide="stethoscope" class="h-4 w-4"></i> Abrir consulta</a>`;
        foot.innerHTML = actions;
        if (window.lucide) window.lucide.createIcons();

        foot.querySelectorAll('[data-action="cancel"]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const reason = prompt('Motivo de cancelación (opcional):') || '';
                const c = await window.doctorApi('POST', '/portal-doctor/me/appointments/' + btn.dataset.id + '/cancel', { reason });
                if (c.ok) location.reload();
                else alert(c.message || 'Error al cancelar.');
            });
        });
    };

    document.querySelectorAll('[data-close]').forEach(b => {
        b.addEventListener('click', () => { document.getElementById('appt-modal').hidden = true; });
    });

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }
});
</script>

<!-- WIZARD: Nueva cita (auto-agenda) -->
<div id="nv-modal" class="nv-modal" hidden>
    <div class="nv-backdrop" data-nv-close></div>
    <aside class="nv-drawer" role="dialog" aria-modal="true" aria-label="Nueva cita">
        <header class="nv-head">
            <div class="nv-head-t">
                <h3><i data-lucide="calendar-plus"></i> Nueva cita</h3>
                <div class="nv-steps" id="nv-steps">
                    <span class="nv-stp on" data-s="1">Paciente</span>
                    <span class="nv-stp" data-s="2">Fecha y hora</span>
                    <span class="nv-stp" data-s="3">Confirmar</span>
                </div>
            </div>
            <button type="button" class="nv-x" data-nv-close aria-label="Cerrar"><i data-lucide="x"></i></button>
        </header>
        <div class="nv-body" id="nv-body"></div>
        <footer class="nv-foot" id="nv-foot"></footer>
    </aside>
</div>

<style>
/* Nueva cita — wizard (prefijo nv-), marca navy #262161 / verde #5da334 */
#nv-modal *{box-sizing:border-box}
.nv-modal{position:fixed;inset:0;z-index:1000;overflow:hidden}
/* Al abrir el wizard se bloquea el scroll del documento: evita el quirk de iOS
   en el que un fondo más ancho que el viewport ensancha el bloque contenedor
   del position:fixed y empuja el drawer fuera de la pantalla. */
html.nv-locked{overflow:hidden}
html.nv-locked,html.nv-locked body{overflow-x:hidden;max-width:100%}
.nv-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.5);backdrop-filter:blur(2px);animation:nvFade .25s ease}
.nv-drawer{position:absolute;top:0;right:0;height:100%;width:min(480px,100%);max-width:100vw;background:#fff;display:flex;flex-direction:column;box-shadow:-18px 0 50px rgba(15,23,42,.22);animation:nvIn .3s cubic-bezier(.32,.72,0,1)}
@keyframes nvFade{from{opacity:0}to{opacity:1}}
@keyframes nvIn{from{transform:translateX(100%)}to{transform:translateX(0)}}
.nv-head{display:flex;align-items:flex-start;gap:10px;padding:18px 20px 14px;border-bottom:1px solid #eef2f7;background:linear-gradient(180deg,#fafbff,#fff)}
.nv-head-t{flex:1;min-width:0}
.nv-head h3{display:flex;align-items:center;gap:8px;margin:0;font-size:1.12rem;font-weight:800;color:#262161}
.nv-head h3 i{width:19px;height:19px;color:#5da334}
.nv-steps{display:flex;gap:6px;margin-top:10px;flex-wrap:wrap}
.nv-stp{font-size:.72rem;font-weight:700;color:#94a3b8;background:#f1f5f9;border-radius:999px;padding:4px 10px;position:relative}
.nv-stp.on{color:#fff;background:#262161}
.nv-stp.done{color:#3f7d23;background:#eaf6e1}
.nv-x{flex:0 0 auto;width:34px;height:34px;border:0;border-radius:10px;background:#f1f5f9;color:#334155;cursor:pointer;display:grid;place-items:center}
.nv-x:hover{background:#e2e8f0}
.nv-body{flex:1;overflow-y:auto;overflow-x:hidden;overscroll-behavior:contain;padding:18px 20px;-webkit-overflow-scrolling:touch}
.nv-foot{display:flex;gap:10px;align-items:center;padding:14px 20px;border-top:1px solid #eef2f7;background:#fff}
.nv-foot .doctor-btn{flex:1;justify-content:center}
.nv-foot .doctor-btn-ghost{flex:0 0 auto}
.nv-foot .doctor-btn:disabled{opacity:.5;cursor:not-allowed}
.nv-foot-msg{flex-basis:100%;order:-1;margin:0 0 2px;font-size:.84rem;color:#64748b}
.nv-foot-msg.err{color:#b91c1c}
/* Segmented */
.nv-seg{display:grid;grid-template-columns:1fr 1fr;gap:6px;background:#f1f5f9;border-radius:12px;padding:4px;margin-bottom:14px}
.nv-seg-b{display:flex;align-items:center;justify-content:center;gap:6px;border:0;background:transparent;border-radius:9px;padding:9px;font-weight:700;font-size:.88rem;color:#64748b;cursor:pointer}
.nv-seg-b i{width:16px;height:16px}
.nv-seg-b.on{background:#fff;color:#262161;box-shadow:0 1px 3px rgba(15,23,42,.1)}
/* Búsqueda + resultados */
.nv-search{position:relative;display:flex;align-items:center}
.nv-search i{position:absolute;left:12px;width:17px;height:17px;color:#94a3b8}
.nv-search input{padding-left:38px}
.nv-results{margin-top:10px;display:flex;flex-direction:column;gap:6px}
.nv-hint{color:#94a3b8;font-size:.86rem;margin:8px 2px}
.nv-res{display:flex;align-items:center;gap:11px;width:100%;text-align:left;border:1px solid #eef2f7;background:#fff;border-radius:12px;padding:10px 12px;cursor:pointer;transition:border-color .15s,background .15s}
.nv-res:hover{border-color:#cbd5e1;background:#f8fafc}
.nv-res-av{flex:0 0 auto;width:36px;height:36px;border-radius:10px;display:grid;place-items:center;font-weight:800;color:#fff;background:linear-gradient(135deg,#322d82,#5da334)}
.nv-res-main{flex:1;min-width:0;display:flex;flex-direction:column}
.nv-res-main strong{color:#0f172a;font-size:.92rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.nv-res-main span{font-size:.78rem;color:#64748b}
.nv-res i{width:16px;height:16px;color:#cbd5e1}
/* Formulario nuevo paciente */
.nv-form{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.nv-f{display:flex;flex-direction:column;gap:5px;font-size:.82rem;font-weight:600;color:#334155}
.nv-f .doctor-input{font-weight:500}
.nv-col2{grid-column:1/-1}
.nv-note{display:flex;align-items:center;gap:7px;font-size:.8rem;font-weight:500;color:#3f7d23;background:#eaf6e1;border-radius:10px;padding:8px 11px;margin:2px 0 0}
.nv-note i{width:15px;height:15px;flex:0 0 auto}
/* Chip paciente seleccionado */
.nv-chip{display:flex;align-items:center;gap:11px;background:#f4faef;border:1px solid #cce8b8;border-radius:12px;padding:10px 12px;margin-bottom:14px}
.nv-chip>i{width:20px;height:20px;color:#5da334;flex:0 0 auto}
.nv-chip div{flex:1;min-width:0;display:flex;flex-direction:column}
.nv-chip strong{color:#0f172a;font-size:.92rem}
.nv-chip span{font-size:.78rem;color:#64748b}
.nv-chip-x{border:0;background:#fff;width:26px;height:26px;border-radius:8px;cursor:pointer;color:#64748b}
/* Paso 2 */
.nv-patbar{display:flex;align-items:center;gap:7px;font-size:.9rem;color:#334155;background:#f8fafc;border-radius:10px;padding:9px 12px;margin-bottom:14px}
.nv-patbar i{width:16px;height:16px;color:#262161}
.nv-slot-loader{display:flex;align-items:center;gap:8px;color:#64748b;font-size:.9rem;padding:24px 4px}
.nv-slot-loader[hidden]{display:none}
.nv-spin{width:18px;height:18px;animation:nvSpin 1s linear infinite}
@keyframes nvSpin{to{transform:rotate(360deg)}}
.nv-cal-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.nv-cal-title{font-weight:800;color:#262161;font-size:.96rem}
.nv-nav{width:32px;height:32px;border:1px solid #e2e8f0;background:#fff;border-radius:9px;font-size:1.2rem;line-height:1;color:#334155;cursor:pointer}
.nv-nav:disabled{opacity:.35;cursor:not-allowed}
.nv-wd{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-bottom:4px}
.nv-wd span{text-align:center;font-size:.72rem;font-weight:700;color:#94a3b8}
.nv-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:4px}
.nv-cell{aspect-ratio:1;display:grid;place-items:center;position:relative;border:0;border-radius:10px;font-size:.86rem;font-weight:600;color:#cbd5e1;background:transparent}
.nv-cell.nv-off{color:#cbd5e1}
.nv-cell.nv-avail{color:#0f172a;background:#f4faef;cursor:pointer;border:1px solid #e3f0d6}
.nv-cell.nv-avail:hover{border-color:#5da334}
.nv-cell.nv-today{outline:2px solid #c7d2fe;outline-offset:-2px}
.nv-cell.on{background:#262161;color:#fff;border-color:#262161}
.nv-dot{position:absolute;bottom:5px;width:5px;height:5px;border-radius:50%;background:#5da334}
.nv-cell.on .nv-dot{background:#fff}
.nv-times-h{margin:14px 0 8px;font-weight:700;color:#334155;font-size:.86rem}
.nv-times{display:grid;grid-template-columns:repeat(auto-fill,minmax(82px,1fr));gap:7px}
.nv-time{border:1px solid #e2e8f0;background:#fff;border-radius:10px;padding:9px 6px;font-weight:700;font-size:.82rem;color:#262161;cursor:pointer;text-align:center}
.nv-time:hover{border-color:#5da334}
.nv-time.on{background:#5da334;border-color:#5da334;color:#fff}
.nv-manual{margin-top:16px;border-top:1px dashed #e2e8f0;padding-top:14px}
.nv-manual-tog{display:flex;align-items:center;gap:8px;font-size:.86rem;font-weight:600;color:#334155;cursor:pointer}
.nv-manual-inp,#nv-manual-inp{margin-top:10px}
.nv-empty{text-align:center;color:#64748b;padding:26px 10px}
.nv-empty i{width:34px;height:34px;color:#cbd5e1;margin-bottom:8px}
/* Paso 3 */
.nv-sum{border:1px solid #eef2f7;border-radius:14px;overflow:hidden;margin-bottom:14px}
.nv-sum-row{display:flex;gap:10px;padding:13px 16px;border-bottom:1px solid #f1f5f9}
.nv-sum-row:last-child{border-bottom:0}
.nv-sum-row .k{flex:0 0 42%;display:flex;align-items:center;gap:7px;color:#64748b;font-size:.82rem;font-weight:700}
.nv-sum-row .k i{width:15px;height:15px;color:#262161}
.nv-sum-row .v{flex:1;color:#0f172a;font-weight:600;font-size:.92rem}
.nv-done{text-align:center;padding:28px 12px}
.nv-done-ic{width:64px;height:64px;margin:0 auto 14px;border-radius:50%;display:grid;place-items:center;background:#eaf6e1;color:#5da334}
.nv-done-ic i{width:30px;height:30px}
.nv-done h3{margin:0 0 4px;color:#0f172a}
.nv-done p{margin:2px 0;color:#475569}
.nv-done-when{font-weight:700;color:#262161}
@media (max-width:640px){
    /* Anclar el drawer por AMBOS lados: el ancho deja de depender de width:100%
       (que en iOS puede resolver a un contenedor más ancho que la pantalla). */
    .nv-drawer{left:0;right:0;width:auto;max-width:none;animation:nvUp .3s cubic-bezier(.32,.72,0,1)}
    .nv-form{grid-template-columns:1fr}
}
@keyframes nvUp{from{transform:translateY(100%)}to{transform:translateY(0)}}
</style>
<script src="<?= e(base_url('assets/js/portal-medico-agendar.js')) ?>?v=<?= e((string)(@filemtime(__DIR__ . '/../assets/js/portal-medico-agendar.js') ?: time())) ?>"></script>
<?php if (isset($_GET['nueva'])): ?>
<script>document.addEventListener('DOMContentLoaded', function () { if (window.openNuevaCita) window.openNuevaCita(); });</script>
<?php endif; ?>
<?php doctor_layout_end();
