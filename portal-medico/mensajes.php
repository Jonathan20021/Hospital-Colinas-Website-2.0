<?php
require_once __DIR__ . '/_layout.php';

if (!doctor_is_logged_in()) {
    header('Location: ' . base_url('portal-medico/login.php?next=' . urlencode('portal-medico/mensajes.php')));
    exit;
}

$assetVersion = (string) (@filemtime(__DIR__ . '/../assets/css/portal-chat.css') ?: 0);
$jsVersion    = (string) (@filemtime(__DIR__ . '/../assets/js/portal-chat.js') ?: 0);

doctor_layout_begin('Mensajes', 'mensajes');
?>
<link rel="stylesheet" href="<?= e(base_url('assets/css/portal-chat.css')) ?>?v=<?= e($assetVersion) ?>">

<div class="hgchat has-context" data-hgchat style="--hg-h: calc(100dvh - 130px); --hg-chrome: 130px;">

    <aside class="hgchat-list" aria-label="Conversaciones">
        <div class="hgchat-list-head">
            <h1>Mensajes</h1>
            <button type="button" class="hgchat-new" data-hg-new><i data-lucide="plus"></i> Nuevo</button>
        </div>
        <div class="hgchat-search">
            <i data-lucide="search"></i>
            <input type="search" data-hg-search placeholder="Buscar paciente…" aria-label="Buscar conversación">
        </div>
        <div class="hgchat-threads" data-hg-threads role="list">
            <div class="hgchat-skeleton"><div class="hgchat-sk"></div><div class="hgchat-sk"></div><div class="hgchat-sk"></div></div>
        </div>
    </aside>

    <section class="hgchat-thread is-empty" data-hg-thread aria-label="Conversación">
        <div class="hgchat-empty">
            <i data-lucide="messages-square"></i>
            <strong>Tu bandeja de mensajes</strong>
            <span>Selecciona un paciente para ver la conversación, o pulsa <b>Nuevo</b> para escribirle a un paciente de tu agenda.</span>
        </div>

        <header class="hgchat-thread-head">
            <button type="button" class="hgchat-back" data-hg-back aria-label="Volver"><i data-lucide="arrow-left"></i></button>
            <div class="hgchat-peer">
                <span class="hgchat-av" data-hg-peer-av></span>
                <div class="hgchat-peer-meta">
                    <div class="hgchat-peer-name" data-hg-peer-name></div>
                    <div class="hgchat-peer-sub" data-hg-peer-sub></div>
                </div>
            </div>
            <button type="button" class="hgchat-icon-btn" data-hg-ctx-toggle aria-label="Ver contexto clínico"><i data-lucide="clipboard-list"></i></button>
        </header>

        <div class="hgchat-banner">
            <i data-lucide="alert-triangle"></i>
            No usar para urgencias. Ante una emergencia el paciente debe llamar al (809) 806-0444 o acudir a Emergencias.
        </div>

        <div class="hgchat-msgs" data-hg-msgs></div>

        <form class="hgchat-composer" data-hg-composer>
            <button type="button" class="hgchat-attach" data-hg-share aria-label="Compartir del expediente"><i data-lucide="share-2"></i></button>
            <button type="button" class="hgchat-attach" data-hg-attach aria-label="Adjuntar archivo"><i data-lucide="paperclip"></i></button>
            <input type="file" data-hg-file hidden accept="image/jpeg,image/png,image/webp,image/gif,application/pdf">
            <textarea class="hgchat-input" data-hg-input rows="1" placeholder="Escribe un mensaje…" aria-label="Mensaje"></textarea>
            <button type="submit" class="hgchat-send" data-hg-send disabled aria-label="Enviar"><i data-lucide="send-horizontal"></i></button>
        </form>
        <div class="hgchat-flash" data-hg-flash role="status" aria-live="polite"></div>
    </section>

    <aside class="hgchat-context empty" data-hg-context aria-label="Contexto clínico">
        <div class="hgchat-ctx-hint"><i data-lucide="clipboard-list"></i><p>Abre una conversación para ver el contexto clínico del paciente.</p></div>
    </aside>

    <div class="hgchat-sheet" data-hg-sheet>
        <div class="hgchat-sheet-panel">
            <div class="hgchat-sheet-head">
                <h3>Nuevo mensaje</h3>
                <button type="button" class="hgchat-icon-btn" data-hg-sheet-close aria-label="Cerrar"><i data-lucide="x"></i></button>
            </div>
            <div class="hgchat-sheet-list" data-hg-sheet-list></div>
        </div>
    </div>

    <div class="hgchat-sheet" data-hg-share-sheet>
        <div class="hgchat-sheet-panel">
            <div class="hgchat-sheet-head">
                <h3>Compartir del expediente</h3>
                <button type="button" class="hgchat-icon-btn" data-hg-share-close aria-label="Cerrar"><i data-lucide="x"></i></button>
            </div>
            <div class="hgchat-sheet-list" data-hg-share-list></div>
        </div>
    </div>
</div>

<script>
    window.HGCHAT_CONFIG = {
        role: 'doctor',
        proxyUrl: <?= json_encode(base_url('api/doctor-proxy.php'), JSON_UNESCAPED_SLASHES) ?>,
        apiBase: '/portal-doctor/me/chat',
        peerIdKey: 'patient_id',
        fileUrl: <?= json_encode(base_url('api/doctor-chat-file.php'), JSON_UNESCAPED_SLASHES) ?>,
        cardHref: function (card) {
            var pid = window.HGChat && HGChat.state.active && HGChat.state.active.peerId;
            return pid ? (DM_PACIENTE_URL + '?id=' + encodeURIComponent(pid)) : '';
        },
        onThreadOpen: function (thread) { loadPatientContext(thread.peerId, thread.name); }
    };

    var DM_PACIENTE_URL = <?= json_encode(base_url('portal-medico/paciente.php'), JSON_UNESCAPED_SLASHES) ?>;

    function esc(s) { var d = document.createElement('div'); d.textContent = (s == null ? '' : s); return d.innerHTML; }

    function loadPatientContext(patientId, name) {
        var ctx = document.querySelector('[data-hg-context]');
        if (!ctx || !patientId || !window.doctorApi) return;
        ctx.classList.remove('empty');
        ctx.innerHTML = '<div class="hgchat-skeleton"><div class="hgchat-sk"></div><div class="hgchat-sk"></div></div>';
        window.doctorApi('GET', '/portal-doctor/me/patients/' + patientId).then(function (r) {
            var p = (r && r.data) || {};
            var allergies = p.allergies || [];
            var conditions = p.conditions || [];
            var blood = p.blood_type || p.patient_blood_type || '';
            var html = '';

            html += '<div class="hgchat-ctx-card">' +
                '<div class="hgchat-ctx-name">' + esc(p.name || name || 'Paciente') + '</div>' +
                (p.cedula ? '<div class="hgchat-ctx-sub">Cédula ' + esc(p.cedula) + '</div>' : '') +
                (blood ? '<div class="hgchat-ctx-sub">Tipo de sangre: <b>' + esc(blood) + '</b></div>' : '') +
                '</div>';

            html += '<div class="hgchat-ctx-card"><h4><i data-lucide="alert-octagon"></i> Alergias</h4>';
            if (allergies.length) {
                html += allergies.map(function (a) {
                    var sev = (a.severity || 'moderada');
                    return '<span class="hgchat-chip sev-' + esc(sev) + '">' + esc(a.allergen || a.name || '') + '</span>';
                }).join('');
            } else { html += '<div class="hgchat-ctx-sub">Sin alergias registradas.</div>'; }
            html += '</div>';

            html += '<div class="hgchat-ctx-card"><h4><i data-lucide="activity"></i> Antecedentes</h4>';
            if (conditions.length) {
                html += conditions.map(function (c) { return '<span class="hgchat-chip">' + esc(c.name || '') + '</span>'; }).join('');
            } else { html += '<div class="hgchat-ctx-sub">Sin antecedentes registrados.</div>'; }
            html += '</div>';

            html += '<div class="hgchat-ctx-card"><h4><i data-lucide="folder-open"></i> Expediente</h4><div class="hgchat-ctx-links">' +
                '<a class="hgchat-ctx-link" href="' + DM_PACIENTE_URL + '?id=' + encodeURIComponent(patientId) + '"><i data-lucide="user-round"></i> Abrir expediente completo<span class="go"><i data-lucide="chevron-right"></i></span></a>' +
                '</div></div>';

            ctx.innerHTML = html;
            if (window.lucide) window.lucide.createIcons();
        });
    }

    // Toggle del riel de contexto en móvil.
    document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-hg-ctx-toggle]');
        if (t) { document.querySelector('[data-hgchat]').classList.toggle('show-context'); }
    });

    // Hoja "Compartir del expediente" (médico → paciente).
    (function () {
        var btn = document.querySelector('[data-hg-share]');
        var sheet = document.querySelector('[data-hg-share-sheet]');
        var list = document.querySelector('[data-hg-share-list]');
        if (!btn || !sheet || !list) return;
        function close() { sheet.classList.remove('open'); }
        btn.addEventListener('click', function () {
            if (!window.HGChat || !HGChat.activeThreadId()) return;
            var pid = HGChat.state.active && HGChat.state.active.peerId;
            if (!pid) return;
            sheet.classList.add('open');
            list.innerHTML = '<div class="hgchat-skeleton"><div class="hgchat-sk"></div><div class="hgchat-sk"></div></div>';
            Promise.all([
                window.doctorApi('GET', '/portal-doctor/me/patients/' + pid + '/history'),
                window.doctorApi('GET', '/portal-doctor/me/patients/' + pid + '/lab')
            ]).then(function (res) {
                var hist = (res[0] && res[0].data) || [];
                var lab = (res[1] && res[1].data && res[1].data.orders) || [];
                var items = [];
                hist.forEach(function (h) {
                    if (!h.note_id) return;
                    var date = String(h.appointment_time || '').slice(0, 10);
                    if (h.prescription && String(h.prescription).trim() !== '') {
                        items.push({ type: 'prescription', ref: h.note_id, meta: { title: 'Receta', subtitle: date + (h.specialty ? ' · ' + h.specialty : ''), pid: pid } });
                    }
                    items.push({ type: 'consultation', ref: h.note_id, meta: { title: 'Consulta', subtitle: date + (h.diagnosis ? ' · ' + String(h.diagnosis).slice(0, 28) : ''), pid: pid } });
                });
                lab.forEach(function (o) {
                    var oid = o.id || o.order || o.orderId || o.IDorden;
                    if (oid) items.push({ type: 'lab', ref: oid, meta: { title: 'Resultado de laboratorio', subtitle: String(o.date || o.fecha || '').slice(0, 10), pid: pid } });
                });
                if (!items.length) { list.innerHTML = '<p class="hgchat-list-empty">No hay documentos para compartir todavía.</p>'; return; }
                list._items = items;
                list.innerHTML = items.map(function (it, i) {
                    var ic = it.type === 'prescription' ? 'file-text' : (it.type === 'lab' ? 'flask-conical' : 'stethoscope');
                    return '<button type="button" class="hgchat-item" data-share-i="' + i + '">' +
                        '<span class="hgchat-av"><i data-lucide="' + ic + '"></i></span>' +
                        '<span class="hgchat-item-body"><span class="hgchat-item-top"><span class="hgchat-item-name">' + esc(it.meta.title) + '</span></span>' +
                        '<span class="hgchat-item-preview">' + esc(it.meta.subtitle || '') + '</span></span></button>';
                }).join('');
                if (window.lucide) lucide.createIcons();
            });
        });
        sheet.addEventListener('click', function (e) {
            if (e.target === sheet || e.target.closest('[data-hg-share-close]')) { close(); return; }
            var b = e.target.closest('[data-share-i]');
            if (b && list._items) { var it = list._items[+b.getAttribute('data-share-i')]; if (it) { window.HGChat.sendCard(it); close(); } }
        });
    })();

    // Cargar el motor del chat cuando lucide ya esté disponible (lo carga el layout más abajo).
    window.addEventListener('load', function () {
        var s = document.createElement('script');
        s.src = <?= json_encode(base_url('assets/js/portal-chat.js'), JSON_UNESCAPED_SLASHES) ?> + '?v=<?= e($jsVersion) ?>';
        document.body.appendChild(s);
    });
</script>
<?php
doctor_layout_end();
