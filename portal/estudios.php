<?php
/**
 * "Mis imágenes" — el paciente ve y abre SUS propios estudios de imagen.
 * Lista vía /portal/me/imaging (proxy) y abre el visor del paciente.
 */
require_once __DIR__ . '/_layout.php';
portal_require_login();

portal_layout_begin('Mis imágenes', 'estudios');
?>
<section class="portal-card">
    <header class="portal-card-header">
        <div>
            <h1 class="portal-card-title"><i data-lucide="scan" class="h-5 w-5"></i> Mis imágenes</h1>
            <p class="portal-card-subtitle">Tus estudios de imagen (radiografías, tomografías, resonancias…) registrados en el hospital.</p>
        </div>
    </header>

    <div class="estudios-disc">
        <i data-lucide="info" class="h-4 w-4"></i>
        <span>Estas imágenes son de <strong>referencia</strong>. Quien las interpreta es tu médico o radiólogo. Si tienes dudas sobre un estudio, consúltalo con tu especialista.</span>
    </div>

    <div id="img-loading" class="estudios-loading">
        <span class="estudios-spin"></span> Consultando tus estudios…
    </div>
    <div id="img-list" class="estudios-list" hidden></div>
    <div id="img-empty" class="portal-empty" hidden>
        <i data-lucide="scan-line" class="h-8 w-8"></i>
        <p class="portal-empty-title">Aún no hay imágenes</p>
        <p>No encontramos estudios de imagen asociados a tu identidad. Si te realizaste uno recientemente, podría tardar en aparecer.</p>
    </div>
</section>

<style>
.estudios-disc{display:flex;gap:9px;align-items:flex-start;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;border-radius:12px;padding:11px 14px;font-size:.86rem;line-height:1.45;margin-bottom:16px}
.estudios-disc strong{font-weight:700}
.estudios-disc svg{flex:none;margin-top:2px}
.estudios-loading{display:flex;align-items:center;gap:10px;color:#64748b;font-size:.9rem;padding:18px 4px}
.estudios-spin{width:18px;height:18px;border:2px solid #cbd5e1;border-top-color:#2563eb;border-radius:50%;display:inline-block;animation:espin 1s linear infinite}
@keyframes espin{to{transform:rotate(360deg)}}
.estudios-list{display:flex;flex-direction:column;gap:10px}
.estudio-row{display:flex;align-items:center;gap:14px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;transition:border-color .12s,box-shadow .12s}
.estudio-row:hover{border-color:#c7d2fe;box-shadow:0 4px 16px rgba(37,99,235,.08)}
.estudio-mod{flex:none;width:54px;height:54px;border-radius:12px;background:#eef2ff;color:#3730a3;display:grid;place-items:center;font-weight:800;font-size:.85rem;letter-spacing:.02em}
.estudio-meta{flex:1;min-width:0}
.estudio-meta strong{display:block;color:#0f172a;font-weight:700;font-size:.98rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.estudio-meta span{display:block;color:#64748b;font-size:.82rem;margin-top:2px}
.estudio-btn{flex:none;display:inline-flex;align-items:center;gap:7px;background:#2563eb;color:#fff;border:0;border-radius:10px;padding:10px 16px;font:inherit;font-weight:600;font-size:.88rem;cursor:pointer;transition:background .12s;text-decoration:none}
.estudio-btn:hover{background:#1d4ed8}
@media(max-width:560px){
  .estudio-row{flex-wrap:wrap}
  .estudio-mod{width:46px;height:46px}
  .estudio-btn{width:100%;justify-content:center;margin-top:4px}
}
</style>

<script>
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var viewerBase = <?= json_encode(base_url('portal/visor-imagen.php'), JSON_UNESCAPED_SLASHES) ?>;
    var loadEl = document.getElementById('img-loading');
    var listEl = document.getElementById('img-list');
    var emptyEl = document.getElementById('img-empty');

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); }
    function fdate(d) { return (d && d.length >= 8) ? (d.slice(6, 8) + '/' + d.slice(4, 6) + '/' + d.slice(0, 4)) : '—'; }

    function proxy(method, path, payload) {
        return fetch('/api/portal-proxy.php', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ method: method, path: path, query: method === 'GET' ? (payload || {}) : undefined })
        }).then(function (r) {
            return r.text().then(function (t) {
                var j; try { j = JSON.parse(t); } catch (e) { j = { success: false }; }
                return { ok: r.ok && j.success, status: r.status, data: j.data, message: j.message };
            });
        });
    }

    function openViewer(uid, scope) {
        var u = viewerBase + '?study=' + encodeURIComponent(uid) + '&scope=' + encodeURIComponent(scope);
        var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
        if (standalone) { window.location.href = u; } else { window.open(u, '_blank', 'noopener'); }
    }

    proxy('GET', '/portal/me/imaging').then(function (r) {
        loadEl.hidden = true;
        if (!r.ok || !r.data) { emptyEl.hidden = false; return; }
        var studies = r.data.studies || [];
        var scope = r.data.scope || '';
        if (!studies.length) { emptyEl.hidden = false; return; }
        listEl.hidden = false;
        listEl.innerHTML = studies.map(function (s) {
            return '<div class="estudio-row">'
                + '<div class="estudio-mod">' + esc(s.modality || '—') + '</div>'
                + '<div class="estudio-meta"><strong>' + esc(s.description || 'Estudio de imagen') + '</strong>'
                + '<span>' + fdate(s.date) + ' · ' + (s.instances || 0) + ' imágenes · ' + (s.series || 0) + ' serie(s)</span></div>'
                + '<button type="button" class="estudio-btn" data-uid="' + esc(s.studyUID) + '"><i data-lucide="eye" class="h-4 w-4"></i> Ver imágenes</button>'
                + '</div>';
        }).join('');
        listEl.querySelectorAll('button[data-uid]').forEach(function (b) {
            b.addEventListener('click', function () { openViewer(b.getAttribute('data-uid'), scope); });
        });
        if (window.lucide) lucide.createIcons();
    }).catch(function () { loadEl.hidden = true; emptyEl.hidden = false; });
})();
</script>
<?php portal_layout_end();
