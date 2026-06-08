<?php
/**
 * Escenario de video de la teleconsulta — compartido por la pantalla del médico
 * (portal-medico/teleconsulta.php) y la del paciente (teleconsulta.php).
 * Los IDs los cablea assets/js/portal-medico-teleconsult.js.
 */
?>
<div class="tele-wrap">

    <!-- Prueba previa -->
    <div id="tele-precall" class="tele-precall">
        <h2>Prueba tu cámara y micrófono</h2>
        <div class="tele-preview-box">
            <video id="tele-local-preview" class="tele-preview" autoplay muted playsinline></video>
        </div>
        <p id="tele-precall-msg" class="tele-precall-msg">Solicitando permiso de cámara y micrófono…</p>
        <button type="button" id="tele-enter" class="tele-btn-primary" disabled>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 8-6 4 6 4V8Z"/><rect width="14" height="12" x="2" y="6" rx="2"/></svg>
            Entrar a la consulta
        </button>
    </div>

    <!-- Sala -->
    <div id="tele-room" class="tele-room" hidden>
        <div id="tele-banner" class="tele-banner"></div>
        <div id="tele-stage" class="tele-stage">
            <div id="tele-waiting" class="tele-waiting">
                <div class="tele-spin"></div>
                <p>Esperando a que se conecte la otra persona…</p>
            </div>
        </div>
        <video id="tele-localpip" class="tele-localpip" autoplay muted playsinline></video>
        <div class="tele-controls">
            <button type="button" id="tele-mic" class="tele-ctl" aria-label="Micrófono">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" x2="12" y1="19" y2="22"/></svg>
            </button>
            <button type="button" id="tele-cam" class="tele-ctl" aria-label="Cámara">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 8-6 4 6 4V8Z"/><rect width="14" height="12" x="2" y="6" rx="2"/></svg>
            </button>
            <button type="button" id="tele-end" class="tele-ctl danger" aria-label="Finalizar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.68 13.31a16 16 0 0 0 3.41 2.6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7 2 2 0 0 1 1.72 2v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.42 19.42 0 0 1-3.33-2.67m-2.67-3.34a19.79 19.79 0 0 1-3.07-8.63A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91"/><line x1="22" x2="2" y1="2" y2="22"/></svg>
            </button>
            <span id="tele-quality" class="tele-quality"></span>
        </div>
    </div>

    <!-- Finalizada -->
    <div id="tele-closed" class="tele-closed" hidden>
        <div class="tele-closed-ic">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        </div>
        <h2>Teleconsulta finalizada</h2>
        <p>Gracias. Puedes cerrar esta ventana.</p>
    </div>

</div>
