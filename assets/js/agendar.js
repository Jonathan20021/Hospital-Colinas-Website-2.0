/* Wizard de agendamiento como invitado.
 * Calendar slot picker + form submit hacia api/guest-appointment.php
 */
(function () {
    'use strict';

    // Peticion en curso: si el paciente cambia de medico mientras carga, la
    // respuesta vieja se descarta en vez de pintar horarios de otro.
    let generacion = 0;

    /**
     * Prepara el calendario y el formulario para un medico.
     * Se puede llamar varias veces (el wizard de una sola pagina lo hace al
     * cambiar de medico); los listeners que solo deben atarse una vez van
     * marcados con dataset.bound.
     */
    function init(doctorId) {
        if (!doctorId) return;
        const miGeneracion = ++generacion;

    const loader  = document.querySelector('.portal-slot-loader');
    const picker  = document.getElementById('slot-picker');
    const form    = document.getElementById('guest-form');
    const confirmWhen = document.getElementById('confirm-when');
    const apptInput = document.getElementById('appointment_time');
    const submitBtn = document.getElementById('g-submit');
    const result    = document.getElementById('guest-result');

    // ARS: mostrar el campo de texto "Otra…" solo cuando corresponde.
    const arsSelect   = document.getElementById('g-ars');
    const arsOtraWrap = document.getElementById('g-ars-otra-wrap');
    const arsOtraInput = document.getElementById('g-ars-otra');
    if (arsSelect && !arsSelect.dataset.bound) {
        arsSelect.dataset.bound = '1';
        arsSelect.addEventListener('change', () => {
        const isOtra = arsSelect.value === '__otra__';
        if (arsOtraWrap) arsOtraWrap.hidden = !isOtra;
        if (isOtra) setTimeout(() => arsOtraInput?.focus(), 50);
        });
    }

    // Devuelve el ARS elegido: texto libre si es "Otra…", '' para pago directo o sin elegir.
    function getArsValue() {
        if (!arsSelect) return '';
        const v = arsSelect.value;
        if (v === '__otra__') return (arsOtraInput?.value || '').trim();
        if (v === '__directo__' || v === '') return '';
        return v;
    }

    let selectedDay = null;
    let daysData = {};
    const today = new Date(); today.setHours(0,0,0,0);
    let viewYear = today.getFullYear();
    let viewMonth = today.getMonth();
    const minDate = today.toISOString().slice(0,10);
    const maxDate = new Date(today.getTime() + 30 * 86400000).toISOString().slice(0,10);

    const slotsBase = window.AGENDAR_SLOTS_URL || '/api/agendar-slots.php';
    const slotsUrl  = `${slotsBase}?doctor_id=${doctorId}&date_from=${minDate}&date_to=${maxDate}&slot_minutes=30`;

    function showError(msg, canRetry) {
        loader.classList.add('hidden');
        picker.classList.remove('hidden');
        picker.innerHTML = `
            <div class="portal-empty">
                <i data-lucide="calendar-x" class="h-10 w-10"></i>
                <p>${msg}</p>
                ${canRetry ? '<button type="button" class="btn btn-outline" id="slot-retry"><i data-lucide="refresh-cw" class="h-4 w-4"></i> Reintentar</button>' : ''}
                <p class="portal-hint" style="margin-top:1rem">También puedes llamarnos al <a href="tel:18098060444" class="portal-text-link">(809) 806-0444</a>.</p>
            </div>`;
        if (window.lucide) lucide.createIcons();
        const retry = document.getElementById('slot-retry');
        if (retry) retry.addEventListener('click', loadSlots);
    }

    function resetLoaderToSpinner() {
        loader.classList.remove('portal-slot-slow');
        loader.innerHTML = `
            <i data-lucide="loader-2" class="h-5 w-5 animate-spin"></i>
            <span class="portal-slot-loader-text">Cargando horarios disponibles…</span>
        `;
        if (window.lucide) lucide.createIcons();
    }

    function showSlowState() {
        loader.classList.add('portal-slot-slow');
        loader.innerHTML = `
            <i data-lucide="clock" class="h-6 w-6"></i>
            <p>Esto está tardando más de lo normal.</p>
            <div class="portal-slot-slow-actions">
                <button type="button" class="btn btn-outline" id="slot-retry-soft">
                    <i data-lucide="refresh-cw" class="h-4 w-4"></i> Reintentar
                </button>
                <a href="tel:18098060444" class="btn btn-secondary">
                    <i data-lucide="phone" class="h-4 w-4"></i> Llamar (809) 806-0444
                </a>
            </div>
        `;
        if (window.lucide) lucide.createIcons();
        document.getElementById('slot-retry-soft')?.addEventListener('click', loadSlots);
    }

    function loadSlots() {
        resetLoaderToSpinner();
        loader.classList.remove('hidden');
        picker.classList.add('hidden');
        picker.innerHTML = '';

        const ctrl = new AbortController();
        // After 8s, swap the spinner for an actionable "tarda más de lo normal" panel.
        const softHintId = setTimeout(showSlowState, 8000);
        // Hard timeout: abort the fetch and show the full error state.
        const timeoutId = setTimeout(() => ctrl.abort(), 15000);

        fetch(slotsUrl, {
            signal: ctrl.signal,
            cache: 'no-store',
            headers: { 'Accept': 'application/json' },
        })
            .then(async r => {
                clearTimeout(timeoutId);
                clearTimeout(softHintId);
                const ct = r.headers.get('content-type') || '';
                if (!ct.includes('application/json')) {
                    throw new Error(`Respuesta inválida del servidor (HTTP ${r.status}).`);
                }
                return r.json();
            })
            .then(r => {
                // Si el paciente ya cambio de medico, esta respuesta es de otro.
                if (esViejo()) return;
                loader.classList.add('hidden');
                picker.classList.remove('hidden');
                if (!r.success) {
                    showError(r.message || 'No se pudieron cargar los horarios disponibles.', true);
                    return;
                }
                daysData = r.data?.days || {};
                if (Object.keys(daysData).length === 0) {
                    // Antes era una frase suelta y ahi se acababa el camino: el
                    // paciente tenia que deducir solo que podia volver atras.
                    const tel = window.AGENDAR_TEL || '';
                    const soloDigitos = tel.replace(/\D/g, '');
                    picker.innerHTML = `
                        <div class="ag-vacio">
                            <i data-lucide="calendar-x" class="h-9 w-9"></i>
                            <h3>Sin cupos en los próximos 30 días</h3>
                            <p>Este especialista no tiene agenda abierta ahora mismo.
                               Puedes elegir otro médico de la misma especialidad, o
                               llamarnos y te ubicamos una cita.</p>
                            <div class="ag-vacio-acciones">
                                <button type="button" class="btn btn-green" data-volver="2">Ver otros médicos</button>
                                ${tel ? `<a class="ag-vacio-tel" href="tel:1${soloDigitos}"><i data-lucide="phone" class="h-4 w-4"></i> ${tel}</a>` : ''}
                            </div>
                        </div>`;
                    if (window.lucide) lucide.createIcons();
                    return;
                }
                // El paciente aterrizaba en el mes actual, que puede tener 3 dias
                // sueltos o ninguno, y tenia que ir buscando mes a mes. Se abre
                // directamente en el PRIMER dia con hueco y ya seleccionado, asi
                // ve horarios de entrada.
                const conHueco = Object.keys(daysData).filter(d => (daysData[d] || []).length).sort();
                if (conHueco.length && !selectedDay) {
                    selectedDay = conHueco[0];
                    const partes = conHueco[0].split('-').map(Number);
                    viewYear = partes[0];
                    viewMonth = partes[1] - 1;
                }
                render();
            })
            .catch(e => {
                clearTimeout(timeoutId);
                clearTimeout(softHintId);
                const msg = e.name === 'AbortError'
                    ? 'La carga tomó demasiado tiempo. Verifica tu conexión e intenta de nuevo, o llámanos al hospital.'
                    : `No se pudieron cargar los horarios: ${e.message}`;
                showError(msg, true);
            });
    }

    loadSlots();

    function esViejo() { return miGeneracion !== generacion; }

    function render() {
        if (esViejo()) return;
        const monthName = new Date(viewYear, viewMonth, 1)
            .toLocaleDateString('es-DO', { month: 'long', year: 'numeric' });
        const firstDay = new Date(viewYear, viewMonth, 1);
        const lastDay  = new Date(viewYear, viewMonth + 1, 0);
        const daysInMonth = lastDay.getDate();
        let startOffset = firstDay.getDay() - 1;
        if (startOffset < 0) startOffset = 6;

        const canPrev = (new Date(viewYear, viewMonth, 1)) > new Date(today.getFullYear(), today.getMonth(), 1);
        const maxView = new Date(maxDate + 'T00:00:00');
        const canNext = (new Date(viewYear, viewMonth + 1, 1)) <= new Date(maxView.getFullYear(), maxView.getMonth(), 1);

        const cells = [];
        for (let i = 0; i < startOffset; i++) cells.push('<div class="cal-cell cal-empty"></div>');
        for (let d = 1; d <= daysInMonth; d++) {
            const dateStr = `${viewYear}-${String(viewMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
            const available = !!daysData[dateStr];
            const inRange = dateStr >= minDate && dateStr <= maxDate;
            const isSelected = selectedDay === dateStr;
            const isToday = dateStr === minDate;
            let cls = 'cal-cell';
            if (!inRange) cls += ' cal-out';
            else if (available) cls += ' cal-avail';
            else cls += ' cal-disabled';
            if (isSelected) cls += ' cal-selected';
            if (isToday) cls += ' cal-today';
            if (available && inRange) {
                cells.push(`<button type="button" class="${cls}" data-day="${dateStr}">${d}<span class="cal-dot"></span></button>`);
            } else {
                cells.push(`<div class="${cls}">${d}</div>`);
            }
        }

        // Con disponibilidad escasa el mes entero se ve gris y el paciente no
        // sabe donde mirar. Estos son los proximos dias con cupo, a un toque.
        const proximos = Object.keys(daysData)
            .filter(d => (daysData[d] || []).length && d >= minDate)
            .sort()
            .slice(0, 4);
        const atajos = proximos.length > 1 ? `
            <div class="cal-rapido">
                <p class="cal-rapido-label">Próximas fechas disponibles</p>
                <div class="cal-rapido-grid">
                    ${proximos.map(dd => {
                        const fd = new Date(dd + 'T00:00:00');
                        const n  = daysData[dd].length;
                        return `<button type="button" class="cal-rapido-chip${dd === selectedDay ? ' is-active' : ''}" data-day="${dd}">
                            <span class="cal-rapido-dia">${fd.toLocaleDateString('es-DO', { weekday: 'short' })}</span>
                            <span class="cal-rapido-num">${fd.getDate()}</span>
                            <span class="cal-rapido-mes">${fd.toLocaleDateString('es-DO', { month: 'short' })}</span>
                            <span class="cal-rapido-cupos">${n} ${n === 1 ? 'hora' : 'horas'}</span>
                        </button>`;
                    }).join('')}
                </div>
            </div>` : '';

        picker.innerHTML = atajos + `
            <div class="cal-shell">
                <div class="cal-head">
                    <button type="button" class="cal-nav" id="cal-prev" ${canPrev ? '' : 'disabled'}>‹</button>
                    <div class="cal-title">${monthName.charAt(0).toUpperCase() + monthName.slice(1)}</div>
                    <button type="button" class="cal-nav" id="cal-next" ${canNext ? '' : 'disabled'}>›</button>
                </div>
                <div class="cal-weekdays"><span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span>Sáb</span><span>Dom</span></div>
                <div class="cal-grid">${cells.join('')}</div>
                <div class="cal-legend">
                    <span><span class="cal-dot cal-dot-static"></span> Disponible</span>
                    <span><span class="cal-square cal-square-disabled"></span> Sin cupo</span>
                </div>
            </div>
            <div class="cal-times">
                <h3>Horarios ${selectedDay ? 'del ' + formatDayLabel(selectedDay) : ''}</h3>
                <div class="cal-times-grid">
                    ${selectedDay
                        ? daysData[selectedDay].map(ts => {
                            const t = new Date(ts.replace(' ', 'T'));
                            const lbl = t.toLocaleTimeString('es-DO', { hour: '2-digit', minute: '2-digit', hour12: true });
                            return `<button type="button" class="cal-time" data-time="${ts}">${lbl}</button>`;
                          }).join('')
                        : '<p class="portal-empty-text">Selecciona un día disponible en el calendario.</p>'
                    }
                </div>
            </div>
        `;

        document.getElementById('cal-prev')?.addEventListener('click', () => {
            viewMonth--; if (viewMonth < 0) { viewMonth = 11; viewYear--; }
            render();
        });
        document.getElementById('cal-next')?.addEventListener('click', () => {
            viewMonth++; if (viewMonth > 11) { viewMonth = 0; viewYear++; }
            render();
        });
        // [data-day] cubre las celdas del calendario Y los atajos de arriba.
        picker.querySelectorAll('[data-day]').forEach(b => {
            b.addEventListener('click', () => {
                selectedDay = b.dataset.day;
                // Un atajo puede apuntar al mes siguiente: sin mover la vista, el
                // calendario se quedaba en agosto sin marcar nada al pulsar "1 SEP".
                const partes = selectedDay.split('-').map(Number);
                viewYear = partes[0];
                viewMonth = partes[1] - 1;
                limpiarHora();      // cambiar de dia invalida la hora ya elegida
                render();
            });
        });
        picker.querySelectorAll('.cal-time').forEach(b => {
            b.addEventListener('click', () => {
                picker.querySelectorAll('.cal-time').forEach(x => x.classList.remove('is-active'));
                b.classList.add('is-active');
                const ts = b.dataset.time;
                apptInput.value = ts;
                const d = new Date(ts.replace(' ', 'T'));
                // Con text-transform:capitalize salia "Lunes, 31 De Agosto De
                // 2026, 08:00 A. M."; en espanol solo va la primera letra.
                const crudo = d.toLocaleDateString('es-DO', {
                    weekday: 'long', day: '2-digit', month: 'long', year: 'numeric',
                    hour: '2-digit', minute: '2-digit', hour12: true,
                });
                const cuando = crudo.charAt(0).toUpperCase() + crudo.slice(1);
                if (confirmWhen) confirmWhen.textContent = cuando;
                // El formulario ya no se despliega aqui: vive en el paso 4.
                // Esto solo habilita la salida, que es lo unico que queda.
                const elegido = document.getElementById('ag-elegido');
                const barra   = document.getElementById('ag-continuar');
                if (elegido) elegido.textContent = cuando;
                if (barra) {
                    barra.hidden = false;
                    if (barra.getBoundingClientRect().bottom > window.innerHeight) {
                        try { barra.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                        catch (e) { barra.scrollIntoView(); }
                    }
                }
            });
        });
    }

    /** Deja la seleccion de hora en blanco y esconde la salida al paso 4. */
    function limpiarHora() {
        if (apptInput) apptInput.value = '';
        const barra = document.getElementById('ag-continuar');
        if (barra) barra.hidden = true;
    }

    function formatDayLabel(dateStr) {
        const d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString('es-DO', { weekday: 'long', day: '2-digit', month: 'long' });
    }

    // ── Validación de cédula/teléfono (espeja la del servidor: bloquea basura) ──
    function digitsOnly(s) { return (s || '').replace(/\D/g, ''); }
    function junkDigits(d) {
        if (!d) return false;
        if (/^(\d)\1+$/.test(d)) return true;                      // 5555…, 0000…
        if (new Set(d.split('')).size <= 2) return true;           // ≤2 dígitos distintos
        const asc = '01234567890123456789', desc = '98765432109876543210';
        return asc.indexOf(d) !== -1 || desc.indexOf(d) !== -1;    // 12345…, 98765…
    }
    // Un validador POR CAMPO, para poder colgar el error del campo que falla
    // en vez de soltar un aviso generico al pie. Espejan a Validator.php del
    // servidor (api/helpers/Validator.php: checkCedula, checkPhone, junkDigits).
    function validarCedula(cedula) {
        const cRaw = (cedula || '').trim();
        if (cRaw === '') return 'Necesitamos tu cédula para registrarte.';
        if (/[A-Za-z]/.test(cRaw)) {                               // pasaporte / ID extranjero
            const al = cRaw.replace(/[^A-Za-z0-9]/g, '');
            if (al.length < 5 || al.length > 20 || /^(.)\1+$/.test(al)) return 'Ingresa una cédula o pasaporte válido.';
            return null;
        }
        const cd = digitsOnly(cRaw);
        if (cd.length < 8 || cd.length > 13) return 'La cédula no tiene un largo válido.';
        if (junkDigits(cd)) return 'Ingresa una cédula real (no números repetidos o en secuencia).';
        return null;
    }

    function validarTelefono(phone) {
        if (!(phone || '').trim()) return 'Necesitamos un teléfono para confirmarte la cita.';
        let pd = digitsOnly(phone);
        if (pd.length === 11 && pd[0] === '1') pd = pd.slice(1);
        if (pd.length !== 10) return 'El teléfono debe tener 10 dígitos.';
        if (junkDigits(pd)) return 'Ingresa un teléfono real (no números repetidos o en secuencia).';
        return null;
    }

    function validarNombre(v) {
        const t = (v || '').trim();
        if (t.length < 3) return 'Escribe tu nombre completo.';
        if (!/[A-Za-zÁÉÍÓÚÑáéíóúñ]{2}/.test(t)) return 'Escribe tu nombre completo.';
        return null;
    }

    function validarCorreo(v) {
        const t = (v || '').trim();
        if (t === '') return 'Necesitamos tu correo para enviarte la confirmación.';
        // Deliberadamente laxa: el correo lo verifica el hospital al enviar.
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(t) ? null : 'Revisa tu correo electrónico.';
    }

    const CAMPOS = {
        'g-name':   validarNombre,
        'g-cedula': validarCedula,
        'g-email':  validarCorreo,
        'g-phone':  validarTelefono,
    };

    /** Cuelga (o quita) el mensaje de error debajo del campo. Devuelve si es válido. */
    function marcarCampo(input, error) {
        if (!input) return true;
        const caja = input.parentElement;
        let msg = caja.querySelector('.ag-error');
        if (error) {
            if (!msg) {
                msg = document.createElement('p');
                msg.className = 'ag-error';
                msg.id = input.id + '-error';
                caja.appendChild(msg);
            }
            msg.textContent = error;
            input.classList.add('is-invalido');
            input.setAttribute('aria-invalid', 'true');
            input.setAttribute('aria-describedby', msg.id);
        } else {
            if (msg) msg.remove();
            input.classList.remove('is-invalido');
            input.removeAttribute('aria-invalid');
            input.removeAttribute('aria-describedby');
        }
        return !error;
    }

    /** Revisa los cuatro campos. Devuelve el primero que falle, o null. */
    function revisarFormulario() {
        let primero = null;
        for (const id of Object.keys(CAMPOS)) {
            const el = document.getElementById(id);
            if (!el) continue;
            const err = CAMPOS[id](el.value);
            marcarCampo(el, err);
            if (err && !primero) primero = el;
        }
        return primero;
    }

    // ── Mascaras de formato ───────────────────────────────────────────────
    // El servidor hace preg_replace('/\D/','') ANTES de validar, asi que dar
    // formato no puede provocar un rechazo; solo evita erratas al teclear.
    function formatoCedula(v) {
        if (/[A-Za-z]/.test(v)) return v;                  // pasaporte: no se toca
        const d = v.replace(/\D/g, '').slice(0, 11);
        if (d.length <= 3)  return d;
        if (d.length <= 10) return d.slice(0, 3) + '-' + d.slice(3);
        return d.slice(0, 3) + '-' + d.slice(3, 10) + '-' + d.slice(10);
    }

    function formatoTelefono(v) {
        let d = v.replace(/\D/g, '');
        if (d.length === 11 && d[0] === '1') d = d.slice(1);
        d = d.slice(0, 10);
        if (d.length <= 3) return d;
        if (d.length <= 6) return '(' + d.slice(0, 3) + ') ' + d.slice(3);
        return '(' + d.slice(0, 3) + ') ' + d.slice(3, 6) + '-' + d.slice(6);
    }

    function ponerMascara(el, formatea) {
        if (!el) return;
        el.addEventListener('input', (ev) => {
            // Al borrar no se reformatea: si no, quitar un digito devolvia el
            // parentesis o el guion y el cursor se quedaba peleando.
            if (ev.inputType && ev.inputType.indexOf('delete') === 0) return;
            // Solo con el cursor al final; editando en medio, recolocarlo bien
            // es fragil y estorba mas de lo que ayuda.
            if (el.selectionStart !== el.value.length) return;
            const nuevo = formatea(el.value);
            if (nuevo !== el.value) {
                el.value = nuevo;
                el.setSelectionRange(nuevo.length, nuevo.length);
            }
        });
    }

    // Se ata una sola vez aunque init() vuelva a correr al cambiar de medico.
    if (form && !form.dataset.validaBound) {
        form.dataset.validaBound = '1';
        ponerMascara(document.getElementById('g-cedula'), formatoCedula);
        ponerMascara(document.getElementById('g-phone'), formatoTelefono);
        for (const id of Object.keys(CAMPOS)) {
            const el = document.getElementById(id);
            if (!el) continue;
            // Al SALIR del campo, y solo si escribio algo: no se rine a nadie
            // por un campo que aun no ha tocado.
            el.addEventListener('blur', () => {
                if (el.value.trim() !== '') marcarCampo(el, CAMPOS[id](el.value));
            });
            // Ya marcado en rojo, se corrige en vivo mientras teclea.
            el.addEventListener('input', () => {
                if (el.classList.contains('is-invalido')) marcarCampo(el, CAMPOS[id](el.value));
            });
        }
    }

    if (form && !form.dataset.bound) {
        form.dataset.bound = '1';
        form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!apptInput.value) {
            result.innerHTML = '<div class="portal-flash portal-flash-error" style="margin-top:1rem">Selecciona un horario primero.</div>';
            return;
        }
        submitBtn.disabled = true;
        submitBtn.innerHTML = '⏳ Agendando...';
        result.innerHTML = '';

        const payload = {
            name:             form.name.value.trim(),
            cedula:           form.cedula.value.trim(),
            email:            form.email.value.trim(),
            phone:            form.phone.value.trim(),
            doctor_id:        Number(form.doctor_id.value),
            appointment_time: form.appointment_time.value,
            notes:            form.notes.value.trim(),
            ars:              getArsValue(),
        };

        // Antes esto soltaba un aviso generico al pie. Ahora cada campo malo
        // queda marcado y el foco va al primero, que es donde hay que escribir.
        const malo = revisarFormulario();
        if (malo) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '✓ Confirmar cita';
            malo.focus();
            try { malo.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
            catch (e) { malo.scrollIntoView(); }
            return;
        }

        if (window.AGENDAR_HCAPTCHA && typeof hcaptcha !== 'undefined') {
            const t = hcaptcha.getResponse();
            if (!t) {
                result.innerHTML = '<div class="portal-flash portal-flash-error" style="margin-top:1rem">Completa el CAPTCHA.</div>';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '✓ Confirmar cita';
                return;
            }
            payload.captcha_token = t;
        }

        try {
            const submitUrl = window.AGENDAR_SUBMIT_URL || '/api/guest-appointment.php';
            const r = await fetch(submitUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            });
            const j = await r.json();
            if (j.success) {
                renderConfirmation(j.data, payload.email);
            } else {
                // El servidor devuelve errors:{campo:[mensajes]}. Los que casan
                // con un campo del formulario se cuelgan de el; el resto va al pie.
                const mapa = { name: 'g-name', cedula: 'g-cedula', email: 'g-email', phone: 'g-phone' };
                const sueltos = [];
                let primero = null;
                for (const [campo, msgs] of Object.entries(j.errors || {})) {
                    const texto = Array.isArray(msgs) ? msgs.join(' ') : String(msgs);
                    const el = mapa[campo] ? document.getElementById(mapa[campo]) : null;
                    if (el) { marcarCampo(el, texto); if (!primero) primero = el; }
                    else sueltos.push(texto);
                }
                if (primero) primero.focus();
                const errs = sueltos.join(' · ');
                result.innerHTML = (j.message || errs)
                    ? `<div class="portal-flash portal-flash-error" style="margin-top:1rem">${j.message || 'Error.'} ${errs}</div>`
                    : '';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '✓ Confirmar cita';
                if (window.hcaptcha) hcaptcha.reset();
            }
        } catch (e) {
            result.innerHTML = `<div class="portal-flash portal-flash-error" style="margin-top:1rem">Error de conexión: ${e.message}</div>`;
            submitBtn.disabled = false;
            submitBtn.innerHTML = '✓ Confirmar cita';
        }
        });
    }

    function renderConfirmation(data, email) {
        const when = new Date(data.appointment_time.replace(' ', 'T'));
        const whenLabel = when.toLocaleDateString('es-DO', {
            weekday: 'long', day: '2-digit', month: 'long', year: 'numeric',
            hour: '2-digit', minute: '2-digit', hour12: true,
        });

        let accountBlock;
        if (data.account_created) {
            accountBlock = `
                <div style="background:linear-gradient(135deg,#ecfdf5,#fff);padding:1.5rem;border:1px solid #a7f3d0;border-radius:12px;margin-top:1.5rem;text-align:left">
                    <h3 style="margin:0 0 .75rem;color:#047857">🩺 Tu cuenta del portal está casi lista</h3>
                    <p style="margin:0 0 .35rem;color:#374151;font-size:.95rem">Usuario: <strong>${email || ''}</strong></p>
                    <p style="margin:0 0 1rem;color:#374151;font-size:.95rem">Contraseña: <strong>tu cédula</strong> (solo los números, sin guiones).</p>
                    <p style="margin:0 0 1rem;color:#475569;font-size:.9rem">📧 Te enviamos un correo para <strong>verificar tu cuenta</strong>. Ábrelo y, una vez verificada, inicia sesión.</p>
                    <a href="/portal/login.php" class="btn btn-green">Ir a iniciar sesión →</a>
                </div>`;
        } else if (data.has_account) {
            accountBlock = `
                <div style="background:#eff6ff;padding:1.5rem;border:1px solid #bfdbfe;border-radius:12px;margin-top:1.5rem;text-align:left">
                    <h3 style="margin:0 0 .5rem;color:#1e40af">🔑 Ya tienes cuenta en el portal</h3>
                    <p style="margin:0 0 1rem;color:#475569;font-size:.95rem">Inicia sesión con tu correo para ver o cancelar tus citas.</p>
                    <a href="/portal/login.php" class="btn btn-green">Iniciar sesión →</a>
                </div>`;
        } else {
            accountBlock = `
                <div style="background:linear-gradient(135deg,#ecfdf5,#fff);padding:1.5rem;border:1px solid #a7f3d0;border-radius:12px;margin-top:1.5rem">
                    <h3 style="margin:0 0 .5rem;color:#047857">🩺 Crea tu cuenta del portal</h3>
                    <p style="margin:0 0 1rem;color:#475569;font-size:.95rem">Para gestionar, ver y cancelar tus citas en línea.</p>
                    <a href="${data.register_url}" class="btn btn-green">Crear mi cuenta →</a>
                </div>`;
        }

        document.querySelector('.portal-main').innerHTML = `
            <div class="portal-card" style="text-align:center;padding:3rem 2rem">
                <div style="width:80px;height:80px;margin:0 auto 1.5rem;background:#dcfce7;color:#047857;border-radius:50%;display:grid;place-items:center;font-size:2.5rem">✓</div>
                <h1 style="font-size:2rem;color:#0f172a;margin-bottom:.5rem">¡Cita agendada!</h1>
                <p style="color:#475569;margin-bottom:2rem;font-size:1.1rem">Tu cita #${data.appointment_id} fue registrada correctamente.</p>

                <div style="background:#f8fafc;padding:1.5rem;border-radius:12px;max-width:480px;margin:0 auto;text-align:left">
                    <p style="margin:.25rem 0"><strong>Médico:</strong> ${data.doctor_name}</p>
                    <p style="margin:.25rem 0"><strong>Especialidad:</strong> ${data.specialty}</p>
                    <p style="margin:.25rem 0"><strong>Fecha y hora:</strong> ${whenLabel}</p>
                </div>

                ${data.email_sent
                    ? '<p style="color:#047857;margin:1.5rem 0;font-weight:600">📧 Te enviamos los detalles a tu correo.</p>'
                    : '<p style="color:#b45309;margin:1.5rem 0">⚠ No pudimos enviar el correo de confirmación. Guarda esta página o anota el número de cita.</p>'
                }

                ${accountBlock}

                <a href="/" style="display:block;margin-top:2rem;color:#6b7280;text-decoration:none">← Volver al inicio</a>
            </div>
        `;
        if (window.lucide) lucide.createIcons();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    } // fin de init()

    // API del modulo: el wizard de una sola pagina llama a init() cada vez que
    // el paciente elige (o cambia de) medico.
    window.AgendarSlots = { init: init };

    // Enlace directo al paso 3 (?doctor_id=...): el servidor ya deja el id puesto.
    if (window.PORTAL_DOCTOR_ID) init(window.PORTAL_DOCTOR_ID);
})();
