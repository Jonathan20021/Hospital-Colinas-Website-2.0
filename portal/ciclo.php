<?php
/**
 * Mi Ciclo — control menstrual del Portal del Paciente.
 *
 * El shell se renderiza en el servidor; toda la interacción (rueda del ciclo,
 * calendario, registro diario, predicciones e insights) la maneja
 * assets/js/portal-ciclo.js, que sincroniza vía /api/portal-proxy.php contra
 * los endpoints /portal/me/cycle/* (datos en medical_call_center, NUNCA SGC).
 *
 * Las predicciones se calculan en el cliente para que la UI sea instantánea;
 * el backend solo persiste periodos, registros y preferencias.
 */
require_once __DIR__ . '/_layout.php';
portal_require_login();

$token   = portal_token();
$meRes   = portal_api_call('GET', '/portal/me', [], $token);
$patient = is_array($meRes['data'] ?? null) ? $meRes['data'] : (portal_patient() ?? []);

$pName    = (string) ($patient['name'] ?? (portal_patient()['name'] ?? ''));
$friendly = trim(mb_convert_case(mb_strtolower($pName, 'UTF-8'), MB_CASE_TITLE, 'UTF-8'));
$first    = trim(explode(' ', $friendly)[0] ?? '');
$gender   = strtolower(trim((string) ($patient['gender'] ?? '')));

$GLOBALS['portal_extra_css'] = ['portal-ciclo.css'];
$GLOBALS['portal_extra_js']  = ['portal-ciclo.js'];
portal_layout_begin('Mi Ciclo', 'ciclo');
?>
<div class="cyc" id="cyc-app" aria-busy="true">

    <!-- Aviso de vista previa: visible solo si el backend aún no responde -->
    <div class="cyc-preview" id="cyc-preview" hidden>
        <i data-lucide="cloud-off"></i>
        <div>
            <strong>Modo vista previa</strong>
            <span>Estás viendo la herramienta sin conexión con el servidor. Lo que registres aquí no se guardará todavía.</span>
        </div>
    </div>

    <header class="cyc-head">
        <a class="cyc-back" href="<?= e(base_url('portal/salud.php')) ?>" aria-label="Volver a Mi Salud"><i data-lucide="arrow-left"></i></a>
        <div class="cyc-head-titles">
            <h1>Mi Ciclo</h1>
            <p id="cyc-phase-sub" class="cyc-phase-sub">Cargando tu ciclo…</p>
        </div>
        <button type="button" class="cyc-goal" id="cyc-goal-btn" aria-haspopup="dialog">
            <i data-lucide="target" class="cyc-goal-ic"></i>
            <span id="cyc-goal-label">Seguir mi ciclo</span>
            <i data-lucide="chevron-down"></i>
        </button>
    </header>

    <!-- Tabs internas estilo app -->
    <nav class="cyc-tabs" role="tablist" aria-label="Secciones de Mi Ciclo">
        <button type="button" class="cyc-tab-btn is-active" role="tab" data-tab="hoy" aria-selected="true">
            <i data-lucide="sparkles"></i><span>Hoy</span>
        </button>
        <button type="button" class="cyc-tab-btn" role="tab" data-tab="calendario" aria-selected="false">
            <i data-lucide="calendar-days"></i><span>Calendario</span>
        </button>
        <button type="button" class="cyc-tab-btn" role="tab" data-tab="tendencias" aria-selected="false">
            <i data-lucide="chart-line"></i><span>Tendencias</span>
        </button>
        <span class="cyc-tabs-ink" aria-hidden="true"></span>
    </nav>

    <!-- ===================== TAB: HOY ===================== -->
    <section class="cyc-panel is-active" data-panel="hoy" role="tabpanel">
        <div class="cyc-wheel-card">
            <div class="cyc-wheel" id="cyc-wheel">
                <svg class="cyc-wheel-svg" viewBox="0 0 280 280" role="img" aria-label="Rueda del ciclo">
                    <defs>
                        <linearGradient id="cycGradPeriod" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="#ff6b8b"/><stop offset="1" stop-color="#e23d63"/>
                        </linearGradient>
                        <linearGradient id="cycGradFertile" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="#7fce53"/><stop offset="1" stop-color="#4c8a2a"/>
                        </linearGradient>
                        <linearGradient id="cycGradProgress" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="#3a35a0"/><stop offset="1" stop-color="#262161"/>
                        </linearGradient>
                    </defs>
                    <circle class="cyc-ring-bg" cx="140" cy="140" r="120"/>
                    <g id="cyc-ring-segments"></g>
                    <circle class="cyc-ring-progress" id="cyc-ring-progress" cx="140" cy="140" r="120"/>
                    <g id="cyc-ring-today"></g>
                </svg>
                <div class="cyc-wheel-center" id="cyc-wheel-center">
                    <span class="cyc-wheel-kicker" id="cyc-wheel-kicker">Tu ciclo</span>
                    <strong class="cyc-wheel-big" id="cyc-wheel-big">—</strong>
                    <span class="cyc-wheel-sub" id="cyc-wheel-sub">Configura tu ciclo</span>
                    <button type="button" class="cyc-wheel-cta" id="cyc-wheel-cta" hidden></button>
                </div>
            </div>

            <div class="cyc-today-actions">
                <button type="button" class="cyc-action cyc-action-period" id="cyc-action-period">
                    <i data-lucide="droplet"></i><span>Mi periodo empezó</span>
                </button>
                <button type="button" class="cyc-action cyc-action-log" id="cyc-action-log">
                    <i data-lucide="plus-circle"></i><span>Registrar hoy</span>
                </button>
            </div>
        </div>

        <div class="cyc-insight-cards" id="cyc-insight-cards"><!-- tarjetas de fase/tips (JS) --></div>
        <div id="cyc-reminders-slot"><!-- tarjeta de recordatorios (JS) --></div>
    </section>

    <!-- ===================== TAB: CALENDARIO ===================== -->
    <section class="cyc-panel" data-panel="calendario" role="tabpanel" hidden>
        <div class="cyc-card cyc-cal-card">
            <div class="cyc-cal-head">
                <button type="button" class="cyc-cal-nav" id="cyc-cal-prev" aria-label="Mes anterior"><i data-lucide="chevron-left"></i></button>
                <h2 id="cyc-cal-month">—</h2>
                <button type="button" class="cyc-cal-nav" id="cyc-cal-next" aria-label="Mes siguiente"><i data-lucide="chevron-right"></i></button>
            </div>
            <div class="cyc-cal-dows" aria-hidden="true">
                <span>L</span><span>M</span><span>M</span><span>J</span><span>V</span><span>S</span><span>D</span>
            </div>
            <div class="cyc-cal-grid" id="cyc-cal-grid"></div>
            <div class="cyc-cal-legend">
                <span><i class="dot dot-period"></i> Periodo</span>
                <span><i class="dot dot-pred"></i> Periodo previsto</span>
                <span><i class="dot dot-fertile"></i> Días fértiles</span>
                <span><i class="dot dot-ovu"></i> Ovulación</span>
            </div>
        </div>
        <div class="cyc-card cyc-day-detail" id="cyc-day-detail" hidden></div>
    </section>

    <!-- ===================== TAB: TENDENCIAS ===================== -->
    <section class="cyc-panel" data-panel="tendencias" role="tabpanel" hidden>
        <div class="cyc-stats" id="cyc-stats"></div>

        <div class="cyc-card cyc-summary-card">
            <div class="cyc-summary-head">
                <span class="cyc-summary-ic"><i data-lucide="stethoscope"></i></span>
                <div>
                    <h2>Lleva tu ciclo a la consulta</h2>
                    <p>Genera un resumen claro de tus últimos ciclos para mostrárselo a tu ginecólogo.</p>
                </div>
            </div>
            <button type="button" class="btn btn-green" id="cyc-summary-btn"><i data-lucide="file-text"></i> Generar resumen para mi cita</button>
        </div>
    </section>
</div>

<!-- ===================== Bottom sheet: registro diario ===================== -->
<dialog class="cyc-sheet" id="cyc-log-sheet" aria-labelledby="cyc-log-title">
    <form method="dialog" class="cyc-sheet-form" id="cyc-log-form">
        <div class="cyc-sheet-grip" aria-hidden="true"></div>
        <header class="cyc-sheet-head">
            <div>
                <h2 id="cyc-log-title">Registrar</h2>
                <p id="cyc-log-date" class="cyc-sheet-date">Hoy</p>
            </div>
            <button type="button" class="cyc-sheet-close" data-cyc-close aria-label="Cerrar"><i data-lucide="x"></i></button>
        </header>
        <div class="cyc-sheet-body" id="cyc-sheet-body"><!-- grupos de registro (JS) --></div>
        <footer class="cyc-sheet-foot">
            <button type="button" class="btn btn-outline" id="cyc-log-clear">Borrar día</button>
            <button type="submit" class="btn btn-green" id="cyc-log-save"><i data-lucide="check"></i> Guardar</button>
        </footer>
    </form>
</dialog>

<!-- ===================== Onboarding ===================== -->
<dialog class="cyc-onboard" id="cyc-onboard" aria-labelledby="cyc-onb-title">
    <div class="cyc-onboard-inner" id="cyc-onboard-inner"><!-- pasos (JS) --></div>
</dialog>

<!-- ===================== Objetivo ===================== -->
<dialog class="cyc-goal-dialog" id="cyc-goal-dialog" aria-labelledby="cyc-goal-title">
    <div class="cyc-sheet-grip" aria-hidden="true"></div>
    <header class="cyc-sheet-head">
        <h2 id="cyc-goal-title">¿Cuál es tu objetivo?</h2>
        <button type="button" class="cyc-sheet-close" data-cyc-close aria-label="Cerrar"><i data-lucide="x"></i></button>
    </header>
    <div class="cyc-goal-options" id="cyc-goal-options"></div>
</dialog>

<!-- ===================== Resumen médico ===================== -->
<dialog class="cyc-summary-dialog" id="cyc-summary-dialog" aria-labelledby="cyc-summary-title">
    <header class="cyc-sheet-head">
        <h2 id="cyc-summary-title">Resumen para tu cita</h2>
        <button type="button" class="cyc-sheet-close" data-cyc-close aria-label="Cerrar"><i data-lucide="x"></i></button>
    </header>
    <div class="cyc-summary-body" id="cyc-summary-body"></div>
    <footer class="cyc-summary-foot">
        <a class="btn btn-green" id="cyc-summary-pdf" href="<?= e(base_url('portal/ciclo-pdf.php')) ?>" target="_blank" rel="noopener"><i data-lucide="file-down"></i> Descargar PDF</a>
    </footer>
</dialog>

<script>
    window.CYC_BOOT = {
        firstName: <?= json_encode($first ?: 'paciente', JSON_UNESCAPED_UNICODE) ?>,
        gender: <?= json_encode($gender, JSON_UNESCAPED_UNICODE) ?>,
        today: <?= json_encode(date('Y-m-d')) ?>
    };
</script>
<?php portal_layout_end();
