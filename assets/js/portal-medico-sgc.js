/* =============================================================================
   Expediente clínico (SGC) — panel READ-ONLY en la ficha del paciente.
   Llama al endpoint /portal-doctor/me/patients/{id}/sgc-record (que lee SGC en
   solo lectura) y muestra el historial hospitalario del paciente si su cédula
   coincide con SGC. No escribe nada.
   ============================================================================= */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', init);

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function fdate(s) { if (!s) return ''; var d = new Date(String(s).replace(' ', 'T')); if (isNaN(d)) return String(s).slice(0, 10); return ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + d.getFullYear(); }
  function nl(s) { return esc(s).replace(/\r?\n/g, '<br>'); }
  function has(a) { return Array.isArray(a) && a.length > 0; }
  function itemLine(it) {
    var d = [];
    if (it.dosis && Number(it.dosis) > 0) d.push('dosis ' + (+it.dosis) + (it.medida ? ' ' + esc(it.medida) : ''));
    else if (it.medida) d.push(esc(it.medida));
    if (it.via) d.push(esc(it.via));
    if (it.frecuencia) d.push(esc(it.frecuencia));
    if (it.cantidad && Number(it.cantidad) > 0) d.push('cant. ' + (+it.cantidad));
    return '<li><b>' + esc(it.nombre) + '</b>' + (d.length ? ' <span class="sgc-il">' + d.join(' · ') + '</span>' : '') + '</li>';
  }

  function init() {
    var card = document.getElementById('sgc-card');
    if (!card) return;
    var pid = card.getAttribute('data-pid');
    var body = document.getElementById('sgc-body');
    var head = document.getElementById('sgc-count');
    // Al abrir un acordeón largo, colapsarlo con "Ver todo" (toggle no burbujea → captura).
    body.addEventListener('toggle', function (e) { if (e.target.open) { clipIfLong(e.target.querySelector('.sgc-acc-b')); } }, true);

    (async function () {
      for (var i = 0; i < 100 && !window.doctorApi; i++) await new Promise(function (r) { setTimeout(r, 50); });
      var r;
      try { r = await window.doctorApi('GET', '/portal-doctor/me/patients/' + pid + '/sgc-record'); }
      catch (e) { r = { ok: false }; }
      if (!r || !r.ok) { body.innerHTML = soft('No se pudo consultar el expediente en SGC.'); return; }
      var d = r.data || {};
      if (!d.matched) {
        var reasons = { sin_cedula: 'El paciente no tiene cédula registrada para cruzar con SGC.', no_en_sgc: 'Este paciente no está registrado en SGC (sistema del hospital).', sgc_offline: 'SGC no está disponible en este momento.' };
        body.innerHTML = '<div class="doctor-empty" style="padding:26px 14px"><div class="doctor-empty-illustration"><i data-lucide="database" class="h-6 w-6"></i></div><p class="doctor-empty-title">Sin expediente en SGC</p><p>' + esc(reasons[d.reason] || 'No se encontró expediente en SGC.') + '</p></div>';
        if (window.lucide) lucide.createIcons();
        return;
      }
      render(d, body, head);
      if (window.lucide) lucide.createIcons();
      body.querySelectorAll('details[open] .sgc-acc-b').forEach(clipIfLong);
    })();
  }

  function soft(t) { return '<p class="doctor-text-soft">' + esc(t) + '</p>'; }

  function acc(icon, title, count, inner, open) {
    if (!inner) return '';
    return '<details class="sgc-acc"' + (open ? ' open' : '') + '>'
      + '<summary><span class="sgc-acc-t"><i data-lucide="' + icon + '" class="h-4 w-4"></i> ' + esc(title) + '</span>'
      + (count != null ? '<span class="sgc-acc-n">' + count + '</span>' : '') + '</summary>'
      + '<div class="sgc-acc-b">' + inner + '</div></details>';
  }
  function item(title, meta, text) {
    return '<div class="sgc-item"><div class="sgc-item-h"><strong>' + title + '</strong>' + (meta ? '<span>' + meta + '</span>' : '') + '</div>'
      + (text ? '<div class="sgc-item-tx">' + text + '</div>' : '') + '</div>';
  }
  // Colapsa una sección larga con un botón "Ver todo" (basado en altura; solo si el acordeón está visible).
  function clipIfLong(b) {
    if (!b || b.getAttribute('data-clip') === '1' || b.offsetParent === null) return;
    b.setAttribute('data-clip', '1');
    if (b.scrollHeight > 460) {
      b.classList.add('sgc-clip');
      var btn = document.createElement('button');
      btn.type = 'button'; btn.className = 'sgc-morebtn'; btn.textContent = 'Ver todo ▾';
      btn.addEventListener('click', function () { b.classList.remove('sgc-clip'); btn.remove(); });
      b.insertAdjacentElement('afterend', btn);
    }
  }

  function render(d, body, head) {
    var rec = d.record || {}, c = d.counts || {}, p = d.patient || {};
    var total = Object.keys(c).reduce(function (s, k) { return s + (c[k] || 0); }, 0);
    if (head) head.textContent = p.nombre ? ('Vinculado con SGC') : '';

    var html = '';

    // Resumen demográfico de SGC
    var demo = [];
    if (p.sexo) demo.push(esc(p.sexo));
    if (p.fecha_nac) demo.push('F. nac. ' + fdate(p.fecha_nac));
    if (p.telefono) demo.push('Tel. ' + esc(p.telefono));
    html += '<div class="sgc-head"><span class="sgc-badge"><i data-lucide="shield-check" class="h-3.5 w-3.5"></i> SGC</span>'
      + '<div><strong>' + esc(p.nombre || '') + '</strong>' + (demo.length ? '<span>' + demo.join('  ·  ') + '</span>' : '') + '</div></div>';

    // Antecedentes (texto + estructurados)
    var ant = '';
    if (p.antecedentes) ant += '<div class="sgc-item"><div class="sgc-item-h"><strong>Antecedentes personales</strong></div><div class="sgc-item-tx">' + nl(p.antecedentes) + '</div></div>';
    if (p.antecedentes_familiares) ant += '<div class="sgc-item"><div class="sgc-item-h"><strong>Antecedentes familiares</strong></div><div class="sgc-item-tx">' + nl(p.antecedentes_familiares) + '</div></div>';
    if (has(rec.alergias)) ant += '<div class="sgc-chips"><b>Alergias:</b> ' + rec.alergias.map(function (a) { return '<span class="sgc-chip sgc-chip-alert" title="' + esc((a.reaccion || '') + (a.nota ? ' · ' + a.nota : '')) + '">' + esc(a.nombre) + '</span>'; }).join(' ') + '</div>';
    if (has(rec.condiciones)) ant += '<div class="sgc-chips"><b>Condiciones:</b> ' + rec.condiciones.map(function (e) { return '<span class="sgc-chip" title="' + esc(e.nota || '') + '">' + esc(e.nombre) + (e.cie10 ? ' (' + esc(e.cie10) + ')' : '') + '</span>'; }).join(' ') + '</div>';
    if (has(rec.medicacion)) ant += '<div class="sgc-med"><b>Medicación habitual</b><ul class="sgc-ul">' + rec.medicacion.map(function (m) {
      var d2 = []; if (m.dosis && Number(m.dosis) > 0) d2.push('dosis ' + (+m.dosis)); if (m.momento) d2.push(esc(m.momento)); if (m.medico) d2.push('Dr/a. ' + esc(m.medico));
      return '<li>' + (m.nota ? esc(String(m.nota)) : 'Medicación registrada') + (d2.length ? ' <span class="sgc-il">' + d2.join(' · ') + '</span>' : '') + '</li>';
    }).join('') + '</ul></div>';
    html += acc('clipboard-list', 'Antecedentes', null, ant || soft('Sin antecedentes registrados en SGC.'), true);

    // Evoluciones (las notas) — lo más valioso
    if (has(rec.evoluciones)) {
      var ev = rec.evoluciones.map(function (e) {
        var meta = [fdate(e.fecha) + (e.hora ? ' ' + String(e.hora).slice(11, 16) : ''), e.medico ? 'Dr/a. ' + esc(e.medico) : '', e.admisionid ? 'Hosp. #' + e.admisionid : (e.emergenciaid ? 'Emerg. #' + e.emergenciaid : '')].filter(Boolean).join('  ·  ');
        return item('Evolución', meta, nl(e.detalle));
      }).join('');
      html += acc('notebook-pen', 'Notas de evolución', c.evoluciones, ev, true);
    }

    // Emergencias
    if (has(rec.emergencias)) {
      var em = rec.emergencias.map(function (e) {
        var meta = [fdate(e.fecha_entrada), e.medico ? 'Dr/a. ' + esc(e.medico) : '', e.triaje_nivel ? 'Triaje ' + esc(e.triaje_nivel) : ''].filter(Boolean).join('  ·  ');
        var dx = e.dx ? ('<div class="sgc-dx"><b>Dx:</b> ' + esc(e.dx) + (e.cie10 ? ' (' + esc(e.cie10) + ')' : '') + '</div>') : (e.cie10 ? '<div class="sgc-dx"><b>CIE-10:</b> ' + esc(e.cie10) + '</div>' : '');
        return item('Emergencia', meta, dx + (e.observacion ? nl(e.observacion) : '') + (e.nota ? '<div class="sgc-note">' + nl(e.nota) + '</div>' : ''));
      }).join('');
      html += acc('siren', 'Visitas a emergencia', c.emergencias, em);
    }

    // Hospitalizaciones + historia de ingreso
    if (has(rec.hospitalizaciones)) {
      var hist = {}; (rec.historias || []).forEach(function (h) { hist[h.admisionid] = h; });
      var ho = rec.hospitalizaciones.map(function (a) {
        var meta = [fdate(a.fecha_entrada) + (a.fecha_salida ? ' → ' + fdate(a.fecha_salida) : ''), a.medico ? 'Dr/a. ' + esc(a.medico) : '', a.habitacion_numero ? 'Hab. ' + esc(a.habitacion_numero) : ''].filter(Boolean).join('  ·  ');
        var dx = a.dx ? ('<div class="sgc-dx"><b>Dx:</b> ' + esc(a.dx) + (a.cie10 ? ' (' + esc(a.cie10) + ')' : '') + '</div>') : '';
        var h = hist[a.admisionid];
        var hh = '';
        if (h) {
          if (h.enfermedad_actual) hh += '<div class="sgc-sub"><b>Enfermedad actual:</b> ' + nl(h.enfermedad_actual) + '</div>';
          if (h.analisis) hh += '<div class="sgc-sub"><b>Análisis:</b> ' + nl(h.analisis) + '</div>';
        }
        return item('Hospitalización', meta, dx + (a.observacion ? nl(a.observacion) : '') + (a.comentario ? '<div class="sgc-note">' + nl(a.comentario) + '</div>' : '') + hh);
      }).join('');
      html += acc('bed', 'Hospitalizaciones', c.hospitalizaciones, ho);
    }

    // Recetas
    if (has(rec.recetas)) {
      var rx = rec.recetas.map(function (r) {
        var its = (r.items || []);
        var list = its.length ? ('<ul class="sgc-ul">' + its.map(function (it) { return '<li><b>' + esc(it.nombre) + '</b>' + (it.comentario ? ' <span class="sgc-il">' + esc(it.comentario) + '</span>' : '') + '</li>'; }).join('') + '</ul>') : '';
        return item('Receta', [fdate(r.fecha), r.medico ? 'Dr/a. ' + esc(r.medico) : ''].filter(Boolean).join('  ·  '), (r.receta ? nl(r.receta) : '') + list);
      }).join('');
      html += acc('pill', 'Recetas', c.recetas, rx);
    }
    // Órdenes médicas
    if (has(rec.ordenes)) {
      var or = rec.ordenes.map(function (o) {
        var its = (o.items || []);
        var list = its.length ? ('<ul class="sgc-ul">' + its.map(itemLine).join('') + '</ul>') : '';
        var b2 = list + (o.nota ? nl(o.nota) : '') + (o.dieta ? '<div class="sgc-sub"><b>Dieta:</b> ' + nl(o.dieta) + '</div>' : '');
        return item('Orden médica', [fdate(o.fecha), o.medico ? 'Dr/a. ' + esc(o.medico) : '', its.length ? (its.length + ' ítem' + (its.length === 1 ? '' : 's')) : ''].filter(Boolean).join('  ·  '), b2 || '<span class="sgc-il">Sin detalle</span>');
      }).join('');
      html += acc('list-checks', 'Órdenes médicas', c.ordenes, or);
    }
    // Procedimientos
    if (has(rec.procedimientos)) {
      var pr = rec.procedimientos.map(function (p2) { return item('Procedimiento', [fdate(p2.fecha), p2.cirujano ? 'Dr/a. ' + esc(p2.cirujano) : ''].filter(Boolean).join('  ·  '), (p2.dx ? '<div class="sgc-dx"><b>Dx:</b> ' + esc(p2.dx) + '</div>' : '') + (p2.descripcion ? nl(p2.descripcion) : '')); }).join('');
      html += acc('scissors', 'Procedimientos', c.procedimientos, pr);
    }
    // Vitales hospitalarios
    if (has(rec.vitales)) {
      var vt = '<div class="sgc-vt-wrap"><table class="sgc-vt"><thead><tr><th>Fecha</th><th>TA</th><th>FC</th><th>FR</th><th>T°</th><th>SatO₂</th><th>Gluc.</th></tr></thead><tbody>'
        + rec.vitales.map(function (v) { return '<tr><td>' + fdate(v.fecha) + '</td><td>' + esc(v.ta || '—') + '</td><td>' + esc(v.fc || '—') + '</td><td>' + esc(v.fr || '—') + '</td><td>' + esc(v.temperatura || '—') + '</td><td>' + esc(v.so || '—') + '</td><td>' + esc(v.glicemia || '—') + '</td></tr>'; }).join('')
        + '</tbody></table></div>';
      html += acc('activity', 'Signos vitales (hospitalización)', c.vitales, vt);
    }

    if (total === 0) html += soft('El paciente está en SGC pero no tiene encuentros clínicos registrados.');
    html += '<p class="sgc-foot"><i data-lucide="lock" class="h-3.5 w-3.5"></i> Solo lectura desde SGC (sistema del hospital). No se modifica nada.</p>';
    body.innerHTML = html;
  }
})();
