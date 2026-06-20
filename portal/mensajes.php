<?php
require_once __DIR__ . '/_layout.php';

if (!portal_is_logged_in()) {
    header('Location: ' . base_url('portal/login.php?next=' . urlencode('portal/mensajes.php')));
    exit;
}

$cssVersion = (string) (@filemtime(__DIR__ . '/../assets/css/portal-chat.css') ?: 0);
$jsVersion  = (string) (@filemtime(__DIR__ . '/../assets/js/portal-chat.js') ?: 0);

portal_layout_begin('Mensajes', 'mensajes');
?>
<link rel="stylesheet" href="<?= e(base_url('assets/css/portal-chat.css')) ?>?v=<?= e($cssVersion) ?>">

<div class="hgchat" data-hgchat style="--hg-h: calc(100dvh - 150px); --hg-chrome: 150px;">

    <aside class="hgchat-list" aria-label="Conversaciones">
        <div class="hgchat-list-head">
            <h1>Mensajes</h1>
            <button type="button" class="hgchat-new" data-hg-new><i data-lucide="plus"></i> Nuevo</button>
        </div>
        <div class="hgchat-search">
            <i data-lucide="search"></i>
            <input type="search" data-hg-search placeholder="Buscar médico…" aria-label="Buscar conversación">
        </div>
        <div class="hgchat-threads" data-hg-threads role="list">
            <div class="hgchat-skeleton"><div class="hgchat-sk"></div><div class="hgchat-sk"></div><div class="hgchat-sk"></div></div>
        </div>
    </aside>

    <section class="hgchat-thread is-empty" data-hg-thread aria-label="Conversación">
        <div class="hgchat-empty">
            <i data-lucide="messages-square"></i>
            <strong>Tus mensajes con el equipo médico</strong>
            <span>Selecciona una conversación, o pulsa <b>Nuevo</b> para escribirle a un médico con el que tienes una cita.</span>
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
        </header>

        <div class="hgchat-banner">
            <i data-lucide="alert-triangle"></i>
            Este chat no es para urgencias. Ante una emergencia llama al (809) 806-0444 o acude a Emergencias.
        </div>

        <div class="hgchat-msgs" data-hg-msgs></div>

        <form class="hgchat-composer" data-hg-composer>
            <button type="button" class="hgchat-attach" data-hg-share aria-label="Compartir mi expediente"><i data-lucide="share-2"></i></button>
            <button type="button" class="hgchat-attach" data-hg-attach aria-label="Adjuntar archivo"><i data-lucide="paperclip"></i></button>
            <input type="file" data-hg-file hidden accept="image/jpeg,image/png,image/webp,image/gif,application/pdf">
            <textarea class="hgchat-input" data-hg-input rows="1" placeholder="Escribe un mensaje…" aria-label="Mensaje"></textarea>
            <button type="submit" class="hgchat-send" data-hg-send disabled aria-label="Enviar"><i data-lucide="send-horizontal"></i></button>
        </form>
        <div class="hgchat-flash" data-hg-flash role="status" aria-live="polite"></div>
    </section>

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
                <h3>Compartir mi expediente</h3>
                <button type="button" class="hgchat-icon-btn" data-hg-share-close aria-label="Cerrar"><i data-lucide="x"></i></button>
            </div>
            <div class="hgchat-sheet-list" data-hg-share-list></div>
        </div>
    </div>
</div>

<script>
    var P_URLS = {
        prescription: <?= json_encode(base_url('portal/receta-pdf.php'), JSON_UNESCAPED_SLASHES) ?>,
        lab: <?= json_encode(base_url('portal/laboratorio.php'), JSON_UNESCAPED_SLASHES) ?>,
        imaging: <?= json_encode(base_url('portal/estudios.php'), JSON_UNESCAPED_SLASHES) ?>,
        appointment: <?= json_encode(base_url('portal/mis-citas.php'), JSON_UNESCAPED_SLASHES) ?>,
        consultation: <?= json_encode(base_url('portal/consultas.php'), JSON_UNESCAPED_SLASHES) ?>
    };
    var P_PROXY = <?= json_encode(base_url('api/portal-proxy.php'), JSON_UNESCAPED_SLASHES) ?>;
    function esc(s) { var d = document.createElement('div'); d.textContent = (s == null ? '' : s); return d.innerHTML; }
    function papi(method, path, payload) {
        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        return fetch(P_PROXY, {
            method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ method: method, path: path, query: method === 'GET' ? (payload || {}) : undefined, body: method === 'GET' ? undefined : payload })
        }).then(function (r) { return r.json().catch(function () { return null; }).then(function (j) { return { ok: r.ok && j && j.success, data: j && j.data, message: j && j.message }; }); });
    }

    window.HGCHAT_CONFIG = {
        role: 'patient',
        proxyUrl: P_PROXY,
        apiBase: '/portal/me/chat',
        peerIdKey: 'doctor_id',
        fileUrl: <?= json_encode(base_url('api/portal-chat-file.php'), JSON_UNESCAPED_SLASHES) ?>,
        cardHref: function (card) {
            if (card.type === 'prescription' && card.ref) return P_URLS.prescription + '?note=' + encodeURIComponent(card.ref);
            return P_URLS[card.type] || '';
        }
    };

    // Hoja "Compartir mi expediente" (paciente → médico).
    (function () {
        var btn = document.querySelector('[data-hg-share]');
        var sheet = document.querySelector('[data-hg-share-sheet]');
        var list = document.querySelector('[data-hg-share-list]');
        if (!btn || !sheet || !list) return;
        function close() { sheet.classList.remove('open'); }
        btn.addEventListener('click', function () {
            if (!window.HGChat || !HGChat.activeThreadId()) return;
            sheet.classList.add('open');
            list.innerHTML = '<div class="hgchat-skeleton"><div class="hgchat-sk"></div><div class="hgchat-sk"></div></div>';
            Promise.all([
                papi('GET', '/portal/me/prescriptions'),
                papi('GET', '/portal/me/consultations'),
                papi('GET', '/portal/me/lab')
            ]).then(function (res) {
                var rx = (res[0] && res[0].data && res[0].data.prescriptions) || [];
                var co = (res[1] && res[1].data && res[1].data.consultations) || [];
                var lb = (res[2] && res[2].data && res[2].data.orders) || [];
                var items = [];
                rx.forEach(function (p) { if (p.note_id) items.push({ type: 'prescription', ref: p.note_id, meta: { title: 'Mi receta', subtitle: String(p.appointment_time || p.created_at || '').slice(0, 10) + (p.specialty ? ' · ' + p.specialty : '') } }); });
                co.forEach(function (c) { if (c.note_id) items.push({ type: 'consultation', ref: c.note_id, meta: { title: 'Mi consulta', subtitle: String(c.date || '').slice(0, 10) + (c.specialty ? ' · ' + c.specialty : '') } }); });
                lb.forEach(function (o) { var oid = o.id || o.order || o.orderId; if (oid) items.push({ type: 'lab', ref: oid, meta: { title: 'Mi resultado de laboratorio', subtitle: String(o.date || o.fecha || '').slice(0, 10) } }); });
                if (!items.length) { list.innerHTML = '<p class="hgchat-list-empty">No tienes documentos para compartir todavía.</p>'; return; }
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

    window.addEventListener('load', function () {
        var s = document.createElement('script');
        s.src = <?= json_encode(base_url('assets/js/portal-chat.js'), JSON_UNESCAPED_SLASHES) ?> + '?v=<?= e($jsVersion) ?>';
        document.body.appendChild(s);
    });
</script>
<?php
portal_layout_end();
