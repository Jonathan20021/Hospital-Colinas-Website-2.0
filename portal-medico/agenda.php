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
    $color = '#2563eb';
    if ($a['status'] === 'completed') $color = '#16a34a';
    if ($a['status'] === 'cancelled') $color = '#dc2626';
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
<header class="doctor-header">
    <div>
        <p class="doctor-eyebrow">Mi agenda</p>
        <h1>Calendario de citas</h1>
        <p class="doctor-subtitle">Vista mensual, semanal y diaria. Haz clic en una cita para abrir la consulta.</p>
    </div>
    <div class="doctor-header-actions">
        <button type="button" class="doctor-btn doctor-btn-outline" id="filter-scheduled" data-status="">
            <i data-lucide="filter" class="h-4 w-4"></i> Todas
        </button>
    </div>
</header>

<section class="doctor-card">
    <div id="doctor-agenda" data-events='<?= e(json_encode($events, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?>' style="padding: 1.25rem; min-height: 640px;"></div>
    <div class="doctor-calendar-legend">
        <span><i class="doctor-dot" style="background:#2563eb"></i> Agendada</span>
        <span><i class="doctor-dot" style="background:#16a34a"></i> Completada</span>
        <span><i class="doctor-dot" style="background:#dc2626"></i> Cancelada</span>
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
<script>
document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('doctor-agenda');
    if (!el) return;
    let events = [];
    try { events = JSON.parse(el.dataset.events || '[]'); } catch (_) {}

    const calendar = new FullCalendar.Calendar(el, {
        initialView: 'dayGridMonth',
        locale: 'es',
        firstDay: 1,
        height: 640,
        buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', day: 'Dia', list: 'Lista' },
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
        },
        events,
        eventClick: (info) => {
            info.jsEvent.preventDefault();
            openApptModal(info.event.id);
        },
        slotMinTime: '06:00:00',
        slotMaxTime: '21:00:00',
        nowIndicator: true,
    });
    calendar.render();

    async function openApptModal(id) {
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
        body.innerHTML = `
            <dl class="doctor-dl">
                <dt>Paciente</dt><dd><strong>${esc(a.patient_name)}</strong> ${a.patient_cedula ? '· ' + esc(a.patient_cedula) : ''}</dd>
                <dt>Telefono</dt><dd>${esc(a.patient_phone || '—')}</dd>
                <dt>Fecha</dt><dd>${dt.toLocaleString('es-DO', {dateStyle:'full', timeStyle:'short'})}</dd>
                <dt>Estado</dt><dd><span class="doctor-pill doctor-pill-${esc(a.status)}">${esc(a.status)}</span></dd>
                ${a.diagnosis ? `<dt>Diagnostico</dt><dd>${esc(a.diagnosis)}</dd>` : ''}
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
                const reason = prompt('Motivo de cancelacion (opcional):') || '';
                const c = await window.doctorApi('POST', '/portal-doctor/me/appointments/' + btn.dataset.id + '/cancel', { reason });
                if (c.ok) location.reload();
                else alert(c.message || 'Error al cancelar.');
            });
        });
    }

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
<?php doctor_layout_end();
