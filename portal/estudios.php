<?php
/**
 * "Mis imágenes" — el paciente ve y abre SUS propios estudios de imagen.
 * Lista vía /portal/me/imaging (proxy) y abre el visor del paciente.
 */
require_once __DIR__ . '/_layout.php';
portal_require_login();

portal_layout_begin('Mis imágenes', 'estudios');
?>
<div class="pa-head">
    <h1>Mis imágenes</h1>
    <p>Tus estudios de imagen (radiografías, tomografías, sonografías…) registrados en el hospital.</p>
</div>

<div class="pa-note">
    <i data-lucide="info"></i>
    <span>Estas son <strong>tus</strong> imágenes médicas, pero <strong>solo tu médico o radiólogo puede interpretarlas</strong>. Evita sacar conclusiones por tu cuenta; ante cualquier duda, consulta con tu especialista.</span>
</div>

<div id="img-loading" class="pa-loading"><span class="pa-spin"></span> Consultando tus estudios…</div>
<div id="img-list" class="pa-list" hidden></div>
<div id="img-empty" class="pa-empty" hidden>
    <div class="ic"><i data-lucide="scan-line"></i></div>
    <h2>Aún no hay imágenes</h2>
    <p>No encontramos estudios de imagen asociados a tu identidad. Si te realizaste uno recientemente, podría tardar en aparecer.</p>
</div>

<script>
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var proxyUrl = <?= json_encode(base_url('api/portal-proxy.php'), JSON_UNESCAPED_SLASHES) ?>;
    var viewerBase = <?= json_encode(base_url('portal/visor-imagen.php'), JSON_UNESCAPED_SLASHES) ?>;
    var reportBase = <?= json_encode(base_url('api/portal-imaging-report.php'), JSON_UNESCAPED_SLASHES) ?>;
    var loadEl = document.getElementById('img-loading');
    var listEl = document.getElementById('img-list');
    var emptyEl = document.getElementById('img-empty');

    function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g,function(c){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];}); }
    function fdate(d){ return (d&&d.length>=8)?(d.slice(6,8)+'/'+d.slice(4,6)+'/'+d.slice(0,4)):'—'; }

    function proxy(method, path, payload){
        return fetch(proxyUrl,{method:'POST',credentials:'same-origin',
            headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},
            body:JSON.stringify({method:method,path:path,query:method==='GET'?(payload||{}):undefined})
        }).then(function(r){ return r.text().then(function(t){ var j; try{j=JSON.parse(t);}catch(e){j={success:false};} return {ok:r.ok&&j.success,status:r.status,data:j.data,message:j.message}; }); });
    }
    function openViewer(uid, scope, unq){
        var u = viewerBase + '?study=' + encodeURIComponent(uid) + '&scope=' + encodeURIComponent(scope);
        if (unq != null && unq !== '') u += '&report=1&runq=' + encodeURIComponent(unq);
        else if (unq === '') u += '&report=1';
        var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
        if (standalone) { window.location.href = u; } else { window.open(u, '_blank', 'noopener'); }
    }
    function openReport(uid, scope, unq){
        var u = reportBase + '/' + encodeURIComponent(scope) + '/' + encodeURIComponent(uid);
        if (unq) u += '?u=' + encodeURIComponent(unq);
        var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
        if (standalone) { window.location.href = u; } else { window.open(u, '_blank', 'noopener'); }
    }

    proxy('GET','/portal/me/imaging').then(function(r){
        loadEl.hidden = true;
        if (!r.ok || !r.data) { emptyEl.hidden = false; return; }
        var studies = r.data.studies || [];
        var scope = r.data.scope || '';
        if (!studies.length) { emptyEl.hidden = false; return; }
        listEl.hidden = false;
        listEl.innerHTML = studies.map(function(s){
            return '<div class="pa-item">'
                + '<div class="pa-item-ic portal-item-icon-image"><i data-lucide="scan"></i></div>'
                + '<div class="pa-item-main"><div class="t">' + esc(s.description || 'Estudio de imagen') + '</div>'
                + '<div class="s">' + fdate(s.date) + ' · ' + (s.instances || 0) + ' imágenes · ' + (s.series || 0) + ' serie(s)</div>'
                + '<div class="pa-chips"><span class="pa-chip portal-chip-image">' + esc(s.modality || 'Estudio') + '</span></div></div>'
                + '<div class="pa-item-actions">'
                + (s.report ? '<button type="button" class="pa-btn pa-btn-soft pa-btn-sm" data-report-uid="' + esc(s.studyUID) + '" data-report-unq="' + esc((s.reports && s.reports[0]) ? s.reports[0].unq : '') + '"><i data-lucide="file-text"></i> Ver informe</button>' : '')
                + '<button type="button" class="pa-btn pa-btn-primary pa-btn-sm" data-uid="' + esc(s.studyUID) + '"' + (s.report ? ' data-hasreport="1" data-runq="' + esc((s.reports && s.reports[0]) ? s.reports[0].unq : '') + '"' : '') + '><i data-lucide="eye"></i> Ver imágenes</button>'
                + '</div>'
                + '</div>';
        }).join('');
        listEl.querySelectorAll('button[data-uid]').forEach(function(b){ b.addEventListener('click', function(){ openViewer(b.getAttribute('data-uid'), scope, b.getAttribute('data-hasreport') ? (b.getAttribute('data-runq') || '') : null); }); });
        listEl.querySelectorAll('button[data-report-uid]').forEach(function(b){ b.addEventListener('click', function(){ openReport(b.getAttribute('data-report-uid'), scope, b.getAttribute('data-report-unq')); }); });
        if (window.lucide) lucide.createIcons();
    }).catch(function(){ loadEl.hidden = true; emptyEl.hidden = false; });
})();
</script>
<?php portal_layout_end();
