/**
 * Editor de documentos clínicos del Portal Médico.
 * Toolbar tipo Word (contentEditable), pegado limpio, plantillas con variables,
 * membrete en vivo (logo del médico + firma) y guardado al expediente.
 *
 * El HTML se vuelve a sanear en el backend (DoctorDocumentsTrait::sanitizeHtml);
 * la limpieza del cliente es para UX (pegar desde Word sin basura).
 */
(function () {
    'use strict';

    var cfg = window.DOC_EDITOR;
    if (!cfg) return;

    var $ = function (id) { return document.getElementById(id); };
    var body = $('doc-body');
    if (!body) return;

    var titleInput = $('doc-title');
    var paperTitle = $('doc-paper-title');
    var statusEl = $('doc-status');
    var tplSel = $('doc-tpl-sel');
    var tplSaveBtn = $('doc-tpl-save');
    var tplDelBtn = $('doc-tpl-del');

    var state = { docId: cfg.docId || null, dirty: false, saving: false, templates: [] };

    function whenApi(cb) { var n = 0; (function w() { if (window.doctorApi) return cb(); if (n++ < 200) setTimeout(w, 50); })(); }
    function escHtml(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

    // ── Placeholder ────────────────────────────────────────────────────────
    function refreshPlaceholder() {
        var empty = body.textContent.trim() === '' && body.querySelectorAll('img,table,hr').length === 0;
        body.classList.toggle('is-empty', empty);
    }

    // ── Estado / guardado ──────────────────────────────────────────────────
    function setStatus(kind, text) {
        if (!statusEl) return;
        statusEl.className = 'doc-ed-status' + (kind ? ' is-' + kind : '');
        statusEl.textContent = text || '';
    }
    function markDirty() { state.dirty = true; setStatus('', '· sin guardar'); refreshPlaceholder(); }

    // ── Formato: comandos básicos ──────────────────────────────────────────
    function focusBody() { body.focus(); }
    function exec(cmd, val) { document.execCommand(cmd, false, val || null); markDirty(); }
    function insertHTML(html) { document.execCommand('insertHTML', false, html); markDirty(); }

    try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}
    try { document.execCommand('styleWithCSS', false, false); } catch (e) {}

    function currentBlock() {
        var sel = window.getSelection();
        if (!sel || !sel.rangeCount) return null;
        var n = sel.getRangeAt(0).startContainer;
        if (n.nodeType === 3) n = n.parentNode;
        while (n && n !== body && !/^(P|DIV|H1|H2|H3|H4|LI|BLOCKQUOTE|TD|TH|PRE)$/.test(n.nodeName)) n = n.parentNode;
        return (n && n !== body) ? n : null;
    }
    function ensureBlock() {
        var b = currentBlock();
        if (b) return b;
        document.execCommand('formatBlock', false, 'p');
        return currentBlock();
    }
    function setAlign(dir) {
        focusBody();
        var b = ensureBlock();
        if (!b) return;
        b.style.textAlign = (dir === 'left') ? '' : dir;
        if (!b.getAttribute('style')) b.removeAttribute('style');
        markDirty();
        updateToolbarState();
    }

    var TABLE_HTML = '<table><thead><tr><th>Columna</th><th>Columna</th></tr></thead>' +
        '<tbody><tr><td>&nbsp;</td><td>&nbsp;</td></tr><tr><td>&nbsp;</td><td>&nbsp;</td></tr></tbody></table><p><br></p>';

    function handleCmd(cmd) {
        focusBody();
        switch (cmd) {
            case 'bold': case 'italic': case 'underline': case 'strikeThrough':
            case 'insertUnorderedList': case 'insertOrderedList': case 'removeFormat':
                exec(cmd); break;
            case 'blockquote':
                exec('formatBlock', currentBlock() && currentBlock().nodeName === 'BLOCKQUOTE' ? 'p' : 'blockquote'); break;
            case 'insertTable': insertHTML(TABLE_HTML); break;
            case 'insertRule': insertHTML('<hr><p><br></p>'); break;
            case 'pageBreak': insertHTML('<div class="page-break"></div><p><br></p>'); break;
            case 'paste-plain': pastePlain(); break;
        }
        updateToolbarState();
    }

    // ── Toolbar UI ─────────────────────────────────────────────────────────
    document.querySelectorAll('.doc-ed-tb[data-cmd]').forEach(function (b) {
        b.addEventListener('mousedown', function (e) { e.preventDefault(); });
        b.addEventListener('click', function () { handleCmd(b.dataset.cmd); });
    });
    document.querySelectorAll('.doc-ed-tb[data-align]').forEach(function (b) {
        b.addEventListener('mousedown', function (e) { e.preventDefault(); });
        b.addEventListener('click', function () { setAlign(b.dataset.align); });
    });
    var blockSel = $('doc-block');
    if (blockSel) blockSel.addEventListener('change', function () { focusBody(); exec('formatBlock', blockSel.value); updateToolbarState(); });

    function updateToolbarState() {
        var map = { bold: 'bold', italic: 'italic', underline: 'underline', strikeThrough: 'strikeThrough', insertUnorderedList: 'insertUnorderedList', insertOrderedList: 'insertOrderedList' };
        document.querySelectorAll('.doc-ed-tb[data-cmd]').forEach(function (b) {
            var c = map[b.dataset.cmd];
            if (!c) return;
            try { b.classList.toggle('is-active', document.queryCommandState(c)); } catch (e) {}
        });
        var blk = currentBlock();
        var align = blk ? (blk.style.textAlign || 'left') : 'left';
        document.querySelectorAll('.doc-ed-tb[data-align]').forEach(function (b) {
            b.classList.toggle('is-active', b.dataset.align === align);
        });
        if (blockSel && blk) {
            var t = blk.nodeName.toLowerCase();
            blockSel.value = /^(h1|h2|h3)$/.test(t) ? t : 'p';
        }
    }
    document.addEventListener('selectionchange', function () {
        if (document.activeElement === body) updateToolbarState();
    });

    // ── Pegado limpio (desde Word/otros) ───────────────────────────────────
    var PASTE_ALLOWED = {
        P: 1, BR: 1, SPAN: 1, DIV: 1, H1: 1, H2: 1, H3: 1, H4: 1, STRONG: 1, B: 1, EM: 1, I: 1, U: 1, S: 1, STRIKE: 1,
        SUB: 1, SUP: 1, SMALL: 1, UL: 1, OL: 1, LI: 1, BLOCKQUOTE: 1, HR: 1, PRE: 1,
        TABLE: 1, THEAD: 1, TBODY: 1, TR: 1, TH: 1, TD: 1, A: 1, IMG: 1
    };
    function cleanNode(node, out) {
        node.childNodes.forEach(function (child) {
            if (child.nodeType === 3) { out.push(escHtml(child.nodeValue)); return; }
            if (child.nodeType !== 1) return;
            var tag = child.nodeName;
            if (!PASTE_ALLOWED[tag]) { var inner = []; cleanNode(child, inner); out.push(inner.join('')); return; }
            var attrs = '';
            if (tag === 'A') { var href = child.getAttribute('href') || ''; if (/^(https?:|mailto:|tel:)/i.test(href)) attrs = ' href="' + escHtml(href) + '"'; }
            if (tag === 'IMG') { var src = child.getAttribute('src') || ''; if (!/^data:image\//i.test(src)) return; attrs = ' src="' + escHtml(src) + '"'; }
            if (tag === 'TD' || tag === 'TH') {
                ['colspan', 'rowspan'].forEach(function (a) { var v = child.getAttribute(a); if (v && /^\d{1,3}$/.test(v)) attrs += ' ' + a + '="' + v + '"'; });
                var ta = child.style && child.style.textAlign; if (/^(center|right|justify)$/.test(ta)) attrs += ' style="text-align:' + ta + '"';
            }
            if (tag === 'P' || tag === 'DIV' || /^H[1-4]$/.test(tag) || tag === 'LI') {
                var ta2 = child.style && child.style.textAlign; if (/^(center|right|justify)$/.test(ta2)) attrs += ' style="text-align:' + ta2 + '"';
            }
            if (tag === 'BR' || tag === 'HR') { out.push('<' + tag.toLowerCase() + '>'); return; }
            var inner2 = []; cleanNode(child, inner2);
            out.push('<' + tag.toLowerCase() + attrs + '>' + inner2.join('') + '</' + tag.toLowerCase() + '>');
        });
    }
    function cleanHtml(html) {
        var doc = new DOMParser().parseFromString('<div id="r">' + html + '</div>', 'text/html');
        var root = doc.getElementById('r');
        if (!root) return '';
        var out = []; cleanNode(root, out);
        return out.join('').replace(/(<p>\s*<\/p>){2,}/g, '<p></p>');
    }
    body.addEventListener('paste', function (e) {
        var cd = e.clipboardData || window.clipboardData;
        if (!cd) return;
        var html = cd.getData('text/html');
        e.preventDefault();
        if (html) insertHTML(cleanHtml(html));
        else insertHTML(escHtml(cd.getData('text/plain')).replace(/\n/g, '<br>'));
    });
    function pastePlain() {
        if (navigator.clipboard && navigator.clipboard.readText) {
            navigator.clipboard.readText().then(function (t) { insertHTML(escHtml(t).replace(/\n/g, '<br>')); })
                .catch(function () { setStatus('error', 'Permite el acceso al portapapeles o usa Ctrl+Shift+V.'); });
        } else { setStatus('error', 'Usa Ctrl+Shift+V para pegar sin formato.'); }
    }

    // ── Variables ──────────────────────────────────────────────────────────
    function varMap() {
        var P = cfg.patient || {}, D = cfg.doctor || {};
        var sexo = P.gender === 'Male' ? 'Masculino' : P.gender === 'Female' ? 'Femenino' : (P.gender || '');
        return {
            '{{paciente}}': P.name || '', '{{cedula}}': P.cedula || '',
            '{{edad}}': P.age ? (P.age + ' años') : '', '{{sexo}}': sexo,
            '{{fecha}}': cfg.today || '', '{{medico}}': D.name || '', '{{especialidad}}': D.specialty || ''
        };
    }
    function resolveVars(html) {
        var m = varMap();
        return html.replace(/\{\{(paciente|cedula|edad|sexo|fecha|medico|especialidad)\}\}/g, function (tok) {
            return (m[tok] != null && m[tok] !== '') ? escHtml(m[tok]) : tok;
        });
    }
    document.querySelectorAll('.doc-ed-chip[data-var]').forEach(function (b) {
        b.addEventListener('mousedown', function (e) { e.preventDefault(); });
        b.addEventListener('click', function () { focusBody(); document.execCommand('insertText', false, b.dataset.var); markDirty(); });
    });
    var fillBtn = $('doc-fill');
    if (fillBtn) fillBtn.addEventListener('click', function () {
        body.innerHTML = resolveVars(body.innerHTML);
        markDirty();
        if (window.lucide) lucide.createIcons();
    });

    // ── Plantillas ─────────────────────────────────────────────────────────
    function renderTemplates() {
        if (!tplSel) return;
        tplSel.innerHTML = '<option value="">▾ Aplicar plantilla…</option>' +
            state.templates.map(function (t) { return '<option value="' + t.id + '">' + escHtml(t.name) + '</option>'; }).join('');
        if (tplDelBtn) tplDelBtn.hidden = true;
    }
    function loadTemplates() {
        window.doctorApi('GET', '/portal-doctor/me/document-templates').then(function (r) {
            state.templates = (r.ok && Array.isArray(r.data)) ? r.data : [];
            renderTemplates();
        });
    }
    if (tplSel) tplSel.addEventListener('change', function () {
        var t = state.templates.find(function (x) { return String(x.id) === tplSel.value; });
        if (tplDelBtn) tplDelBtn.hidden = !tplSel.value;
        if (!t) return;
        var htmlToInsert = resolveVars(t.body_html || '');
        if (body.textContent.trim() === '' && body.querySelectorAll('img,table,hr').length === 0) {
            body.innerHTML = htmlToInsert;
        } else {
            focusBody(); insertHTML(htmlToInsert);
        }
        markDirty();
        if (window.lucide) lucide.createIcons();
    });
    if (tplSaveBtn) tplSaveBtn.addEventListener('click', function () {
        var raw = body.innerHTML.trim();
        if (body.textContent.trim() === '') { setStatus('error', 'El documento está vacío.'); return; }
        var name = prompt('Nombre de la plantilla (p. ej. "Carta de referimiento").\n\nTip: usa {{paciente}}, {{edad}}, {{fecha}}… para autollenar al reutilizarla.');
        if (!name || !name.trim()) return;
        window.doctorApi('POST', '/portal-doctor/me/document-templates', { name: name.trim(), body_html: raw }).then(function (r) {
            if (r.ok && Array.isArray(r.data)) { state.templates = r.data; renderTemplates(); setStatus('saved', '✓ Plantilla guardada'); }
            else setStatus('error', (r.message || 'No se pudo guardar la plantilla.'));
        });
    });
    if (tplDelBtn) tplDelBtn.addEventListener('click', function () {
        if (!tplSel.value) return;
        if (!confirm('¿Eliminar esta plantilla?')) return;
        window.doctorApi('DELETE', '/portal-doctor/me/document-templates/' + tplSel.value).then(function (r) {
            if (r.ok && Array.isArray(r.data)) { state.templates = r.data; renderTemplates(); }
        });
    });

    // ── Membrete en vivo: logo del médico + firma ──────────────────────────
    function loadLetterhead() {
        window.doctorApi('GET', '/portal-doctor/me/letterhead').then(function (r) {
            if (r.ok && r.data && r.data.has_logo && r.data.logo) {
                var wrap = $('doc-lh-own'), img = $('doc-lh-own-img');
                if (img) { img.src = r.data.logo; wrap.hidden = false; }
            }
        }).catch(function () {});
        window.doctorApi('GET', '/portal-doctor/me/signature').then(function (r) {
            if (r.ok && r.data && r.data.has_signature && r.data.image) {
                var img = $('doc-sign-img'), space = $('doc-sign-space');
                if (img) { img.src = r.data.image; img.hidden = false; if (space) space.hidden = true; }
            }
        }).catch(function () {});
    }

    // ── Título ─────────────────────────────────────────────────────────────
    function syncTitle() {
        var t = (titleInput.value || '').trim() || 'Documento clínico';
        if (paperTitle) paperTitle.textContent = t;
    }
    if (titleInput) titleInput.addEventListener('input', function () { syncTitle(); markDirty(); });

    // ── Cargar documento existente ─────────────────────────────────────────
    function loadExisting(id) {
        window.doctorApi('GET', '/portal-doctor/me/documents/' + id).then(function (r) {
            if (r.ok && r.data) {
                titleInput.value = r.data.title || '';
                syncTitle();
                body.innerHTML = r.data.body_html || '';
                state.docId = r.data.id;
                state.dirty = false;
                setStatus('saved', '✓ Guardado');
                refreshPlaceholder();
                if (window.lucide) lucide.createIcons();
            } else { setStatus('error', 'No se pudo cargar el documento.'); }
        });
    }

    // ── Guardar ────────────────────────────────────────────────────────────
    function save() {
        if (state.saving) return Promise.resolve(false);
        if (body.textContent.trim() === '' && body.querySelectorAll('img,table').length === 0) {
            setStatus('error', 'Escribe algo antes de guardar.'); return Promise.resolve(false);
        }
        state.saving = true;
        setStatus('saving', '· Guardando…');
        var payload = {
            patient_id: cfg.patient.id,
            appointment_id: cfg.apptId || null,
            title: (titleInput.value || '').trim() || 'Documento clínico',
            body_html: body.innerHTML
        };
        var method = state.docId ? 'PUT' : 'POST';
        var path = state.docId ? '/portal-doctor/me/documents/' + state.docId : '/portal-doctor/me/documents';
        return window.doctorApi(method, path, payload).then(function (r) {
            state.saving = false;
            if (r.ok && r.data) {
                if (!state.docId && r.data.id) {
                    state.docId = r.data.id;
                    try { history.replaceState(null, '', '?patient=' + cfg.patient.id + (cfg.apptId ? '&appt=' + cfg.apptId : '') + '&doc=' + state.docId); } catch (e) {}
                }
                state.dirty = false;
                setStatus('saved', '✓ Guardado en el expediente');
                return true;
            }
            setStatus('error', '⚠ ' + (r.message || 'No se pudo guardar.'));
            return false;
        }).catch(function () { state.saving = false; setStatus('error', '⚠ Error de conexión.'); return false; });
    }

    var saveBtn = $('doc-save');
    if (saveBtn) saveBtn.addEventListener('click', function () { save(); });

    // ── PDF (servidor) / Imprimir (navegador) ──────────────────────────────
    var pdfBtn = $('doc-pdf');
    if (pdfBtn) pdfBtn.addEventListener('click', function () {
        pdfBtn.disabled = true;
        var go = (state.docId && !state.dirty) ? Promise.resolve(true) : save();
        go.then(function (ok) {
            pdfBtn.disabled = false;
            if (ok && state.docId) window.open(cfg.pdfBase + '?doc=' + state.docId, '_blank', 'noopener');
        });
    });
    var printBtn = $('doc-print');
    if (printBtn) printBtn.addEventListener('click', function () { window.print(); });

    // ── Atajos + varios ────────────────────────────────────────────────────
    body.addEventListener('input', markDirty);
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); save(); }
    });
    window.addEventListener('beforeunload', function (e) {
        if (state.dirty) { e.preventDefault(); e.returnValue = ''; }
    });

    // ── Init ───────────────────────────────────────────────────────────────
    refreshPlaceholder();
    syncTitle();
    whenApi(function () {
        loadTemplates();
        loadLetterhead();
        if (state.docId) loadExisting(state.docId);
    });
})();
