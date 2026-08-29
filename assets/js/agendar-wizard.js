/**
 * Wizard de /agendar en UNA SOLA PÁGINA.
 *
 * Antes cada paso (especialidad → médico → fecha) era una recarga completa: el
 * paciente perdía el contexto y volvía arriba del todo tres veces. Ahora los
 * tres pasos viven en la misma página y esto solo cambia cuál se ve.
 *
 * El servidor sigue pintando el paso que diga la URL, así que los enlaces
 * directos, el botón atrás y el caso sin JavaScript siguen funcionando igual.
 * Este archivo es mejora progresiva: si falla, la navegación por enlaces normal
 * sigue ahí.
 */
(function () {
    'use strict';

    const doctores = window.AGENDAR_DOCTORS || [];
    const especialidades = window.AGENDAR_SPECIALTIES || {};
    const baseUrl = window.AGENDAR_BASE_URL || '/agendar';
    const estado = window.AGENDAR_STATE || { specialtyId: 0, doctorId: 0, step: 1 };

    const secciones = {
        1: document.getElementById('ag-paso-1'),
        2: document.getElementById('ag-paso-2'),
        3: document.getElementById('ag-paso-3'),
        4: document.getElementById('ag-paso-4'),
    };
    if (!secciones[1] || !secciones[2] || !secciones[3] || !secciones[4]) return;

    const pasos = document.querySelectorAll('.portal-steps li');
    const listaDocs = document.getElementById('ag-doctors');
    const vacioDocs = document.getElementById('ag-doctors-empty');
    const resumenDoc = document.getElementById('ag-doctor-summary');
    const tarjetaSlots = document.getElementById('ag-slot-card');
    const form = document.getElementById('guest-form');
    const cabecera = document.getElementById('ag-cabecera');
    const barraSeguir = document.getElementById('ag-continuar');
    const confirmMedico = document.getElementById('ag-confirm-medico');

    /* ---------------------------------------------------------------- utils */

    const esc = (t) => String(t == null ? '' : t)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    function pintarIconos() {
        if (window.lucide) window.lucide.createIcons();
    }

    /**
     * Lleva la vista al principio del paso, sin brusquedad.
     *
     * scrollIntoView({block:'start'}) dejaba el destino DEBAJO de la cabecera
     * del sitio, que es sticky y mide ~153 px en escritorio: al cambiar de paso
     * el indicador quedaba tapado entero y el resumen a medias. Se mide la
     * cabecera en vivo en vez de restar un numero fijo, porque su alto cambia
     * con el ancho de pantalla.
     */
    function irA(el) {
        if (!el) return;
        const cab = document.querySelector('.site-header');
        const tapa = cab && getComputedStyle(cab).position === 'sticky'
            ? cab.getBoundingClientRect().height
            : 0;
        const y = Math.max(0, window.scrollY + el.getBoundingClientRect().top - tapa - 12);
        try {
            window.scrollTo({ top: y, behavior: 'smooth' });
        } catch (e) {
            window.scrollTo(0, y);
        }
    }

    /* ------------------------------------------------------------ navegación */

    function marcarPasos(n) {
        pasos.forEach((li, i) => {
            const num = i + 1;
            li.classList.toggle('is-current', num === n);
            li.classList.toggle('is-done', num < n);
            if (num === n) li.setAttribute('aria-current', 'step');
            else li.removeAttribute('aria-current');
        });
    }

    /**
     * Muestra un paso. `empujar` decide si se añade al historial: al navegar
     * hacia delante sí, al responder al botón atrás no.
     */
    function mostrarPaso(n, empujar) {
        [1, 2, 3, 4].forEach((i) => { secciones[i].hidden = i !== n; });
        marcarPasos(n);
        estado.step = n;

        // A partir del paso 2 la cabecera se encoge: repetia en el paso 3 lo que
        // el paciente ya leyo, y ocupaba 300 px de pantalla en movil.
        if (cabecera) cabecera.classList.toggle('is-compacta', n > 1);

        if (empujar) {
            let url = baseUrl;
            if (n === 2 && estado.specialtyId) url += '?specialty_id=' + estado.specialtyId;
            if (n >= 3 && estado.doctorId) url += '?specialty_id=' + estado.specialtyId + '&doctor_id=' + estado.doctorId;
            history.pushState({ ...estado }, '', url);
        }

        irA(document.querySelector('.portal-steps') || secciones[n]);

        // Que el lector de pantalla anuncie el paso nuevo.
        const titulo = secciones[n].querySelector('h2');
        if (titulo) {
            titulo.setAttribute('tabindex', '-1');
            titulo.focus({ preventScroll: true });
        }
    }

    /* -------------------------------------------------- paso 2: los médicos */

    function tarjetaMedico(d) {
        const sub = d.subspecialty ? ' · ' + esc(d.subspecialty) : '';
        const oficina = d.office
            ? '<p><i data-lucide="map-pin" class="h-3.5 w-3.5"></i> ' + esc(d.office) + '</p>'
            : '';
        return '' +
            '<article class="portal-doctor">' +
            '<img src="' + esc(d.photo) + '" alt="" width="56" height="56" loading="lazy" decoding="async"' +
            ' style="width:56px;height:56px;border-radius:50%;object-fit:cover">' +
            '<div>' +
            '<h3>' + esc(d.name) + '</h3>' +
            '<p><i data-lucide="stethoscope" class="h-3.5 w-3.5"></i> ' + esc(d.specialty) + sub + '</p>' +
            oficina +
            '<p class="portal-hint">Horario: ' + esc(d.from) + '–' + esc(d.to) + '</p>' +
            '<p class="ag-prox" data-prox="' + d.id + '" hidden></p>' +
            '</div>' +
            '<button type="button" class="btn btn-green" data-elegir-medico="' + d.id + '">Ver fechas →</button>' +
            '</article>';
    }

    function pintarMedicos(specialtyId) {
        if (!listaDocs) return 0;
        const míos = doctores.filter((d) => d.specialtyId === specialtyId);
        listaDocs.innerHTML = míos.map(tarjetaMedico).join('');
        listaDocs.hidden = míos.length === 0;
        if (vacioDocs) vacioDocs.hidden = míos.length !== 0;
        pintarIconos();
        cargarProximas(specialtyId);
        return míos.length;
    }

    /* ------------------------------------------- paso 2: quién atiende antes */

    // Se guarda por especialidad: ir y volver no vuelve a preguntar.
    const proximasPorEsp = {};

    function huecos() {
        return listaDocs ? [...listaDocs.querySelectorAll('[data-prox]')] : [];
    }

    function pintarProximas(datos) {
        const hoy = new Date(); hoy.setHours(0, 0, 0, 0);
        huecos().forEach((el) => {
            const info = datos[el.dataset.prox];
            if (info === undefined) { el.hidden = true; return; }   // no se pudo consultar
            if (info === null) {
                el.className = 'ag-prox es-sincupo';
                el.textContent = 'Sin cupos próximamente';
                el.hidden = false;
                return;
            }
            const f = new Date(info.date + 'T00:00:00');
            const dias = Math.round((f - hoy) / 86400000);
            let cuando;
            if (dias <= 0)      cuando = 'hoy';
            else if (dias === 1) cuando = 'mañana';
            else cuando = 'el ' + f.toLocaleDateString('es-DO', { weekday: 'long', day: 'numeric', month: 'long' });
            el.className = 'ag-prox es-libre';
            el.textContent = 'Puede atenderte ' + cuando;
            el.hidden = false;
        });
    }

    /**
     * Pide la primera fecha con cupo de TODOS los médicos de la especialidad en
     * una sola petición (el servidor las hace en paralelo con curl_multi).
     *
     * Es información de apoyo, no un requisito: se pinta DESPUÉS de las tarjetas
     * y si falla simplemente no aparece. El paso 2 nunca se queda esperándola.
     */
    function cargarProximas(specialtyId) {
        const ids = huecos().map((el) => el.dataset.prox);
        if (!ids.length || !window.AGENDAR_PROXIMAS_URL) return;

        if (proximasPorEsp[specialtyId]) { pintarProximas(proximasPorEsp[specialtyId]); return; }

        huecos().forEach((el) => {
            el.className = 'ag-prox es-cargando';
            el.textContent = 'Consultando disponibilidad…';
            el.hidden = false;
        });

        fetch(window.AGENDAR_PROXIMAS_URL + '?doctor_ids=' + ids.join(','))
            .then((r) => r.json())
            .then((j) => {
                if (!j || !j.success || !j.data) throw new Error('sin datos');
                // Si el paciente ya cambió de especialidad, esto pinta sobre
                // tarjetas que ya no existen: huecos() no las encuentra y no pasa nada.
                proximasPorEsp[specialtyId] = j.data.doctors || {};
                pintarProximas(proximasPorEsp[specialtyId]);
            })
            .catch(() => { huecos().forEach((el) => { el.hidden = true; }); });
    }

    /* --------------------------------------------- paso 3: médico + horarios */

    function pintarResumen(d) {
        if (!resumenDoc) return;
        const sub = d.subspecialty ? ' · ' + esc(d.subspecialty) : '';
        resumenDoc.hidden = false;
        resumenDoc.innerHTML = '' +
            '<img src="' + esc(d.photo) + '" alt="" width="56" height="56"' +
            ' style="width:56px;height:56px;border-radius:50%;object-fit:cover">' +
            '<div>' +
            '<p class="section-label">Agendando con</p>' +
            '<h2>' + esc(d.name) + '</h2>' +
            '<p class="portal-hint"><i data-lucide="stethoscope" class="h-3.5 w-3.5"></i> ' + esc(d.specialty) + sub + '</p>' +
            '</div>' +
            '<button type="button" class="portal-text-link portal-change-link" data-volver="2">Cambiar médico</button>';
        pintarIconos();
    }

    function elegirMedico(id, empujar) {
        const d = doctores.find((x) => x.id === id);
        if (!d) return;

        estado.doctorId = id;
        estado.specialtyId = d.specialtyId;

        pintarResumen(d);
        if (confirmMedico) {
            const sub2 = d.subspecialty ? ' · ' + d.subspecialty : '';
            confirmMedico.textContent = d.name + ' · ' + d.specialty + sub2;
        }
        if (tarjetaSlots) tarjetaSlots.dataset.doctorId = String(id);
        if (barraSeguir) barraSeguir.hidden = true;   // otra agenda, otra hora
        if (form) {
            const oculto = form.querySelector('input[name="doctor_id"]');
            if (oculto) oculto.value = String(id);
            const hora = document.getElementById('appointment_time');
            if (hora) hora.value = '';
        }

        mostrarPaso(3, empujar);

        if (window.AgendarSlots && typeof window.AgendarSlots.init === 'function') {
            window.AgendarSlots.init(id);
        }
    }

    function elegirEspecialidad(id, empujar) {
        estado.specialtyId = id;
        estado.doctorId = 0;
        pintarMedicos(id);
        const titulo = secciones[2].querySelector('.portal-section-title');
        if (titulo && especialidades[id]) {
            titulo.textContent = 'Médicos de ' + especialidades[id];
        }
        mostrarPaso(2, empujar);
    }

    /* ----------------------------------------------------------- escuchadores */

    // Paso 1: las especialidades son botones de un <form> GET. Se interceptan
    // para no recargar; si algo fallara, el submit normal sigue funcionando.
    secciones[1].addEventListener('click', (ev) => {
        const btn = ev.target.closest('.specialty-card');
        if (!btn) return;
        const id = parseInt(btn.value, 10);
        if (!id) return;
        ev.preventDefault();
        elegirEspecialidad(id, true);
    });

    // Paso 2: elegir médico (tarjetas pintadas por JS) y volver atrás.
    document.addEventListener('click', (ev) => {
        const elegir = ev.target.closest('[data-elegir-medico]');
        if (elegir) {
            ev.preventDefault();
            elegirMedico(parseInt(elegir.dataset.elegirMedico, 10), true);
            return;
        }
        if (ev.target.closest('#ag-ir-datos')) {
            ev.preventDefault();
            // Sin hora elegida no hay nada que confirmar.
            const hora = document.getElementById('appointment_time');
            if (!hora || !hora.value) return;
            mostrarPaso(4, true);
            return;
        }
        const volver = ev.target.closest('[data-volver]');
        if (volver) {
            ev.preventDefault();
            const n = parseInt(volver.dataset.volver, 10);
            if (n === 1) { estado.specialtyId = 0; estado.doctorId = 0; }
            if (n === 2) { estado.doctorId = 0; }
            mostrarPaso(n, true);
            return;
        }
        // Enlaces del servidor "← Cambiar especialidad" / "Cambiar médico"
        const enlace = ev.target.closest('a[href*="agendar"], a[href^="?specialty_id"]');
        if (enlace && secciones[2].contains(enlace)) {
            ev.preventDefault();
            estado.specialtyId = 0; estado.doctorId = 0;
            mostrarPaso(1, true);
        }
    });

    // Al elegir un día, los horarios aparecen bastante más abajo en móvil
    // (~1300 px) y no había nada que llevara al paciente hasta ellos.
    const picker = document.getElementById('slot-picker');
    if (picker) {
        picker.addEventListener('click', (ev) => {
            if (!ev.target.closest('[data-day]')) return;
            setTimeout(() => {
                const horarios = picker.querySelector('.cal-times');
                if (!horarios) return;
                const caja = horarios.getBoundingClientRect();
                // Solo si de verdad se ha quedado fuera de la pantalla.
                if (caja.top > window.innerHeight * 0.75 || caja.bottom < 0) irA(horarios);
            }, 80);
        });
    }

    // Botón atrás/adelante del navegador.
    window.addEventListener('popstate', (ev) => {
        const st = ev.state;
        if (!st) { mostrarPaso(1, false); return; }
        // Volver del 4 al 3 no debe recargar horarios ni borrar la seleccion.
        if (st.step === 4 && st.doctorId) { mostrarPaso(4, false); return; }
        if (st.step === 3 && st.doctorId) {
            if (estado.doctorId === st.doctorId) { mostrarPaso(3, false); return; }
            elegirMedico(st.doctorId, false);
            return;
        }
        if (st.step === 2 && st.specialtyId) { elegirEspecialidad(st.specialtyId, false); return; }
        estado.specialtyId = 0; estado.doctorId = 0;
        mostrarPaso(1, false);
    });

    // Estado inicial en el historial, para que el primer "atrás" funcione.
    // Enlace directo al paso 3: el resumen de la cita tambien necesita el medico.
    if (estado.doctorId && confirmMedico) {
        const dIni = doctores.find((x) => x.id === estado.doctorId);
        if (dIni) {
            const subIni = dIni.subspecialty ? ' · ' + dIni.subspecialty : '';
            confirmMedico.textContent = dIni.name + ' · ' + dIni.specialty + subIni;
        }
    }

    history.replaceState({ ...estado }, '', location.href);

    // Si el servidor ya pintó el paso 2, los médicos vienen del HTML; si el
    // paciente llegó por enlace directo al 2 sin lista, se pinta aquí.
    if (estado.step === 2 && listaDocs) {
        if (!listaDocs.children.length) pintarMedicos(estado.specialtyId);
        else cargarProximas(estado.specialtyId);   // ya pintadas por el servidor
    }
})();
