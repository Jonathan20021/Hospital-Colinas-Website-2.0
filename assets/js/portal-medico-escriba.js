/* =============================================================================
   Escriba clínico — ayudas de escritura en la consulta del médico.
   - Dictado por voz (Web Speech API, es-DO).
   - Redactar/mejorar con IA por campo + resumen (vía api/ai-scribe.php).
   - Frases rápidas (chips) y expansión de abreviaturas.
   Se monta sobre los <textarea> de #consult-form. No envía identificadores del
   paciente a la IA (solo el texto clínico + edad/sexo/especialidad).
   ============================================================================= */
(function () {
  'use strict';

  var FIELD_PHRASES = {
    chief_complaint: ['Paciente refiere ', 'Acude por ', 'Asintomático.', 'Cuadro de ', 'Control de ', 'Refiere mejoría.'],
    diagnosis:       ['Diagnóstico presuntivo: ', 'Diagnóstico definitivo: ', 'A descartar ', 'Sin hallazgos patológicos.'],
    prescription:    ['Acetaminofén 500 mg — VO — c/8h — 5 días', 'Omeprazol 20 mg — VO — c/24h — 14 días', 'Amoxicilina 500 mg — VO — c/8h — 7 días'],
    lab_orders:      ['Hemograma completo', 'Glicemia en ayunas', 'Perfil lipídico', 'Creatinina', 'Examen de orina', 'TSH'],
    imaging_orders:  ['Radiografía de tórax PA', 'Ecografía abdominal', 'Sonografía pélvica', 'TAC de cráneo simple'],
    procedures:      ['Interconsulta con ', 'Referimiento a ', 'Curación', 'Retiro de puntos'],
    notes:           ['Se explica diagnóstico y plan al paciente.', 'Plan: control en ', 'Se indica reposo por ', 'Signos de alarma explicados.', 'Continuar tratamiento actual.']
  };

  // Abreviaturas seguras (no son palabras del español): se expanden al teclear espacio/enter.
  var ABBR = {
    dx: 'diagnóstico', tx: 'tratamiento', hta: 'hipertensión arterial', 'dm2': 'diabetes mellitus tipo 2',
    epoc: 'enfermedad pulmonar obstructiva crónica', ivu: 'infección de vías urinarias',
    iras: 'infección respiratoria aguda superior', sap: 'sin antecedentes patológicos de importancia',
    erc: 'enfermedad renal crónica', icc: 'insuficiencia cardíaca congestiva'
  };

  var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  var rec = null, recBtn = null, recCommitted = '';

  document.addEventListener('DOMContentLoaded', init);

  function init() {
    var form = document.getElementById('consult-form');
    if (!form) return;
    var CSRF = window.DM_CSRF || '';
    var EP   = window.DM_SCRIBE_EP || '';
    var CTX  = window.DM_SCRIBE_CTX || {};
    var privacyShown = false;

    function toast(msg) {
      var t = document.getElementById('scribe-toast');
      if (!t) { t = document.createElement('div'); t.id = 'scribe-toast'; t.className = 'scribe-toast'; document.body.appendChild(t); }
      t.textContent = msg; t.classList.add('show');
      clearTimeout(t._t); t._t = setTimeout(function () { t.classList.remove('show'); }, 2800);
    }
    function ctx() { return { age: CTX.age || 0, sex: CTX.sex || '', specialty: CTX.specialty || '' }; }
    function getTa(name) { return form.querySelector('textarea[name="' + name + '"]'); }
    function autoSize(ta) { try { ta.style.height = 'auto'; ta.style.height = Math.min(600, ta.scrollHeight + 2) + 'px'; } catch (e) {} }
    function insertAtCursor(ta, text) {
      var s = ta.selectionStart != null ? ta.selectionStart : ta.value.length;
      var eN = ta.selectionEnd != null ? ta.selectionEnd : ta.value.length;
      var before = ta.value.slice(0, s), after = ta.value.slice(eN);
      if (before && !/\s$/.test(before)) text = ' ' + text;
      ta.value = before + text + after;
      var pos = (before + text).length; ta.setSelectionRange(pos, pos);
      ta.dispatchEvent(new Event('input', { bubbles: true })); autoSize(ta); ta.focus();
    }
    function privacyNote() {
      if (privacyShown) return; privacyShown = true;
      toast('La IA procesa el texto sin enviar nombre, cédula ni fecha de nacimiento.');
    }

    function scribeFetch(payload) {
      if (!EP) return Promise.resolve({ success: false, message: 'Asistente no disponible.' });
      return fetch(EP, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify(payload)
      }).then(function (r) { return r.json().catch(function () { return { success: false, message: 'Respuesta inválida del servidor.' }; }); });
    }

    function applyWithUndo(ta, newText, barEl) {
      var prev = ta.value;
      ta.value = newText; ta.dispatchEvent(new Event('input', { bubbles: true })); autoSize(ta);
      // botón deshacer temporal
      var old = barEl.parentNode.querySelector('.scribe-undo'); if (old) old.remove();
      var u = document.createElement('button');
      u.type = 'button'; u.className = 'scribe-undo'; u.innerHTML = '<span>↶</span> Deshacer cambio de IA';
      u.addEventListener('click', function () { ta.value = prev; ta.dispatchEvent(new Event('input', { bubbles: true })); autoSize(ta); u.remove(); });
      barEl.insertAdjacentElement('afterend', u);
      setTimeout(function () { if (u.parentNode) u.remove(); }, 15000);
    }

    // ── Dictado por voz ──────────────────────────────────────────────────────
    function stopDictation() {
      if (rec) { try { rec.stop(); } catch (e) {} rec = null; }
      if (recBtn) { recBtn.classList.remove('on'); recBtn = null; }
    }
    function toggleDictation(ta, btn) {
      if (rec && recBtn === btn) { stopDictation(); return; }
      stopDictation();
      if (!SR) { toast('Tu navegador no permite dictado por voz.'); return; }
      rec = new SR(); rec.lang = 'es-DO'; rec.continuous = true; rec.interimResults = true;
      recBtn = btn; btn.classList.add('on');
      recCommitted = ta.value; if (recCommitted && !/\s$/.test(recCommitted)) recCommitted += ' ';
      rec.onresult = function (e) {
        var out = '';
        for (var i = 0; i < e.results.length; i++) {
          var t = e.results[i][0].transcript;
          out += e.results[i].isFinal ? (t.trim() + ' ') : t;
        }
        ta.value = recCommitted + out; autoSize(ta);
      };
      rec.onerror = function (ev) { toast('Dictado: ' + (ev.error === 'not-allowed' ? 'permiso de micrófono denegado' : (ev.error || 'error'))); stopDictation(); };
      rec.onend = function () { ta.dispatchEvent(new Event('input', { bubbles: true })); stopDictation(); };
      try { rec.start(); toast('Dictando… habla con normalidad. Toca de nuevo para detener.'); } catch (e) { stopDictation(); }
    }

    // ── Expansión de abreviaturas ──────────────────────────────────────────────
    function abbrHandler(e) {
      if (e.key !== ' ' && e.key !== 'Enter') return;
      var ta = e.target, pos = ta.selectionStart;
      if (pos == null || pos !== ta.selectionEnd) return;
      var before = ta.value.slice(0, pos);
      var m = before.match(/(^|\s)([A-Za-zÁÉÍÓÚÑáéíóúñ0-9]{2,6})$/);
      if (!m) return;
      var rep = ABBR[m[2].toLowerCase()];
      if (!rep) return;
      e.preventDefault();
      var start = pos - m[2].length;
      var tail = (e.key === 'Enter') ? '\n' : ' ';
      ta.value = ta.value.slice(0, start) + rep + tail + ta.value.slice(pos);
      var nc = start + rep.length + 1; ta.setSelectionRange(nc, nc);
      ta.dispatchEvent(new Event('input', { bubbles: true })); autoSize(ta);
    }

    // ── Montaje por campo ──────────────────────────────────────────────────────
    Object.keys(FIELD_PHRASES).forEach(function (name) {
      var ta = getTa(name);
      if (!ta) return;
      var field = name;

      var bar = document.createElement('div'); bar.className = 'scribe-bar';
      // Dictado
      if (SR) {
        var bMic = btn('🎤', 'Dictar', 'rec');
        bMic.addEventListener('click', function () { toggleDictation(ta, bMic); });
        bar.appendChild(bMic);
      }
      // Mejorar con IA
      var bAi = btn('✨', 'Mejorar', 'ai');
      bAi.addEventListener('click', function () {
        var text = ta.value.trim();
        if (!text) { toast('Escribe algo primero para mejorarlo.'); return; }
        privacyNote(); bAi.classList.add('busy'); bAi.disabled = true;
        scribeFetch({ mode: 'improve', field: field, text: text, context: ctx() }).then(function (j) {
          bAi.classList.remove('busy'); bAi.disabled = false;
          if (j && j.success && j.text) applyWithUndo(ta, j.text, bar);
          else toast((j && j.message) || 'No se pudo mejorar el texto.');
        }).catch(function () { bAi.classList.remove('busy'); bAi.disabled = false; toast('Error de red al contactar la IA.'); });
      });
      bar.appendChild(bAi);
      // Frases
      var phrases = FIELD_PHRASES[name] || [];
      var chips = null;
      if (phrases.length) {
        var bPh = btn('💬', 'Frases', '');
        var chipWrap = document.createElement('div'); chipWrap.className = 'scribe-chips';
        phrases.forEach(function (p) {
          var c = document.createElement('button'); c.type = 'button'; c.className = 'scribe-chip'; c.textContent = p.trim();
          c.addEventListener('click', function () { insertAtCursor(ta, p); });
          chipWrap.appendChild(c);
        });
        bPh.addEventListener('click', function () { chipWrap.classList.toggle('open'); });
        bar.appendChild(bPh);
        chips = chipWrap;
      }
      ta.parentNode.insertBefore(bar, ta);
      if (chips) ta.parentNode.insertBefore(chips, ta);
      ta.addEventListener('keydown', abbrHandler);
    });

    // ── Barra global: resumen con IA ──────────────────────────────────────────
    var gbar = document.createElement('div'); gbar.className = 'scribe-global';
    gbar.innerHTML = '<span class="lbl">✨ Escriba clínico</span>'
      + '<span class="hint">Dicta por voz 🎤, pule cada campo con ✨ o genera una nota de evolución y plan. La IA no recibe identificadores del paciente.</span>';
    var bSum = btn('🧠', 'Resumen con IA', 'ai');
    bSum.addEventListener('click', function () {
      var fields = {};
      var any = false;
      Object.keys(FIELD_PHRASES).forEach(function (k) { var t = getTa(k); if (t) { fields[k] = t.value; if (t.value.trim()) any = true; } });
      if (!any) { toast('Escribe en los campos primero.'); return; }
      privacyNote(); bSum.classList.add('busy'); bSum.disabled = true;
      scribeFetch({ mode: 'summary', fields: fields, context: ctx() }).then(function (j) {
        bSum.classList.remove('busy'); bSum.disabled = false;
        if (j && j.success && j.text) {
          var ta = getTa('notes'); if (!ta) { toast('No se encontró el campo de notas.'); return; }
          var merged = ta.value.trim() ? (ta.value.trim() + '\n\n' + j.text) : j.text;
          applyWithUndo(ta, merged, ta.previousElementSibling && ta.previousElementSibling.classList.contains('scribe-bar') ? ta.previousElementSibling : gbar);
          try { ta.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {}
          toast('Resumen generado en “Notas adicionales”. Revísalo antes de guardar.');
        } else toast((j && j.message) || 'No se pudo generar el resumen.');
      }).catch(function () { bSum.classList.remove('busy'); bSum.disabled = false; toast('Error de red al contactar la IA.'); });
    });
    gbar.appendChild(bSum);
    form.parentNode.insertBefore(gbar, form);

    function btn(icon, label, variant) {
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'scribe-btn' + (variant ? ' ' + variant : '');
      b.innerHTML = '<span class="ic">' + icon + '</span>' + label;
      b.setAttribute('aria-label', label);
      return b;
    }

    // Detener el dictado si el médico cambia de pestaña o navega.
    window.addEventListener('beforeunload', stopDictation);
  }
})();
