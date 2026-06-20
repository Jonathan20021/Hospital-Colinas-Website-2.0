<?php

function render_public_header(array $assets, array $contact, string $active = ''): void
{
    $navClass = static fn(string $id): string => trim('nav-link ' . ($active === $id ? 'is-active' : ''));
    ?>
    <header id="siteHeader" class="site-header">
        <div class="utility-bar">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <a href="tel:18098060444" class="utility-link">
                    <i data-lucide="phone" class="h-4 w-4"></i>
                    <?= e($contact['phone']) ?>
                </a>
                <div class="hidden items-center gap-7 md:flex">
                    <a href="<?= e(base_url('servicios/emergencia-adulto-y-pediatrica')) ?>"
                        class="utility-link utility-emergency">
                        <i data-lucide="cross" class="h-4 w-4"></i>
                        Emergencias 24/7
                    </a>
                    <a href="<?= e(base_url('portal/login.php')) ?>" class="utility-link utility-portal">
                        <i data-lucide="user-round-cog" class="h-4 w-4"></i>
                        Portal del paciente
                    </a>
                    <a href="<?= e(base_url('portal/login.php')) ?>" class="utility-link">
                        <i data-lucide="users-round" class="h-4 w-4"></i>
                        Pacientes y visitantes
                    </a>
                    <a href="<?= e(base_url('portal-medico/login.php')) ?>" class="utility-link">
                        <i data-lucide="user-round-check" class="h-4 w-4"></i>
                        Profesionales médicos
                    </a>
                </div>
            </div>
        </div>

        <div class="main-nav">
            <div class="main-nav-inner mx-auto flex h-[110px] max-w-7xl items-center px-4 sm:px-6 lg:px-8">
                <a href="<?= e(base_url()) ?>" class="brand-link" aria-label="Hospital General Las Colinas">
                    <img src="<?= e(base_url($assets['logo'])) ?>" alt="Hospital General Las Colinas" class="brand-logo">
                </a>

                <nav class="nav-primary" aria-label="Navegación principal">
                    <a href="<?= e(base_url()) ?>" class="<?= e($navClass('inicio')) ?>">Inicio</a>
                    <div class="nav-dropdown" data-nav-dropdown>
                        <button type="button" class="<?= e($navClass('hospital')) ?> nav-dropdown-toggle"
                            aria-haspopup="true" aria-expanded="false">
                            Hospital
                            <i data-lucide="chevron-down" class="h-3.5 w-3.5"></i>
                        </button>
                        <div class="nav-dropdown-menu" role="menu">
                            <a href="<?= e(base_url('nosotros')) ?>" role="menuitem"><i data-lucide="building-2"
                                    class="h-4 w-4"></i>Nosotros</a>
                            <a href="<?= e(base_url('liderazgo-institucional')) ?>" role="menuitem"><i
                                    data-lucide="users-round" class="h-4 w-4"></i>Liderazgo institucional</a>
                            <a href="<?= e(base_url('instalaciones')) ?>" role="menuitem"><i data-lucide="hospital"
                                    class="h-4 w-4"></i>Instalaciones</a>
                            <a href="<?= e(base_url('pacientes')) ?>" role="menuitem"><i data-lucide="heart-handshake"
                                    class="h-4 w-4"></i>Pacientes</a>
                            <a href="<?= e(base_url('contacto')) ?>" role="menuitem"><i data-lucide="map-pin"
                                    class="h-4 w-4"></i>Contacto</a>
                        </div>
                    </div>
                    <a href="<?= e(base_url('servicios')) ?>" class="<?= e($navClass('servicios')) ?>">Servicios</a>
                    <a href="<?= e(base_url('directorio-medico')) ?>" class="<?= e($navClass('directorio')) ?>">Directorio
                        médico</a>
                    <a href="<?= e(base_url('repositorio')) ?>" class="<?= e($navClass('repositorio')) ?>">Repositorio</a>
                    <a href="<?= e(base_url('noticias')) ?>" class="<?= e($navClass('noticias')) ?>">Noticias</a>
                </nav>

                <div class="nav-actions">
                    <a href="<?= e(base_url('#buscar-atencion')) ?>" class="nav-search" aria-label="Buscar">
                        <i data-lucide="search" class="h-4 w-4"></i>
                    </a>
                    <a href="<?= e(base_url('agendar')) ?>" class="btn btn-green nav-cta">
                        <i data-lucide="calendar-days" class="h-4 w-4"></i>
                        Agendar cita
                    </a>
                </div>

                <button id="menuToggle" type="button" class="mobile-toggle" aria-label="Abrir menú" aria-expanded="false">
                    <i data-lucide="menu" class="menu-icon h-5 w-5"></i>
                    <i data-lucide="x" class="close-icon hidden h-5 w-5"></i>
                </button>
            </div>

            <div id="mobileMenu" class="mobile-menu hidden">
                <nav class="mobile-menu-inner" aria-label="Navegación móvil">
                    <a href="<?= e(base_url()) ?>" class="mobile-link">Inicio</a>
                    <details class="mobile-group">
                        <summary>Hospital <i data-lucide="chevron-down" class="h-4 w-4"></i></summary>
                        <div class="mobile-sub">
                            <a href="<?= e(base_url('nosotros')) ?>" class="mobile-sub-link">Nosotros</a>
                            <a href="<?= e(base_url('liderazgo-institucional')) ?>" class="mobile-sub-link">Liderazgo
                                institucional</a>
                            <a href="<?= e(base_url('instalaciones')) ?>" class="mobile-sub-link">Instalaciones</a>
                            <a href="<?= e(base_url('pacientes')) ?>" class="mobile-sub-link">Pacientes</a>
                            <a href="<?= e(base_url('contacto')) ?>" class="mobile-sub-link">Contacto</a>
                        </div>
                    </details>
                    <a href="<?= e(base_url('servicios')) ?>" class="mobile-link">Servicios</a>
                    <a href="<?= e(base_url('directorio-medico')) ?>" class="mobile-link">Directorio médico</a>
                    <a href="<?= e(base_url('repositorio')) ?>" class="mobile-link">Repositorio Digital</a>
                    <a href="<?= e(base_url('noticias')) ?>" class="mobile-link">Noticias</a>
                    <a href="<?= e(base_url('#buscar-atencion')) ?>" class="mobile-link">
                        <i data-lucide="search" class="h-4 w-4"></i> Buscar atención
                    </a>
                    <a href="<?= e(base_url('agendar')) ?>" class="mt-3 btn btn-green w-full justify-center">
                        <i data-lucide="calendar-days" class="h-4 w-4"></i>
                        Agendar cita en línea
                    </a>
                    <a href="<?= e(base_url('portal/login.php')) ?>" class="mt-2 btn btn-outline w-full justify-center">
                        <i data-lucide="user-round" class="h-4 w-4"></i>
                        Portal de paciente
                    </a>
                    <a href="<?= e(base_url('portal-medico/login.php')) ?>" class="mt-2 btn btn-outline w-full justify-center">
                        <i data-lucide="user-round-check" class="h-4 w-4"></i>
                        Portal médico
                    </a>
                </nav>
            </div>
        </div>
    </header>
    <?php
}

function render_public_footer(array $assets, array $contact, string $year): void
{
    ?>
    <footer class="site-footer">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-4 lg:px-8">
            <div class="md:col-span-1">
                <img src="<?= e(base_url($assets['logo'])) ?>" alt="Hospital General Las Colinas" class="footer-logo">
                <p class="mt-5 text-sm leading-7 text-white/68">Atención médica integral, tecnología avanzada y
                    especialistas para Santiago.</p>
                <div class="mt-5 flex gap-3">
                    <a href="<?= e($contact['instagram']) ?>" target="_blank" rel="noopener" class="footer-social"
                        aria-label="Instagram"><i data-lucide="camera" class="h-4 w-4"></i></a>
                    <a href="<?= e($contact['facebook']) ?>" target="_blank" rel="noopener" class="footer-social"
                        aria-label="Facebook"><i data-lucide="thumbs-up" class="h-4 w-4"></i></a>
                </div>
                <ul class="footer-brand-contact">
                    <li><a href="tel:18098060444"><?= e($contact['phone']) ?></a></li>
                    <li><a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a></li>
                    <li><?= e($contact['address']) ?></li>
                </ul>
            </div>
            <div>
                <h3 class="footer-title">Hospital</h3>
                <ul class="footer-list">
                    <li><a href="<?= e(base_url('nosotros')) ?>">Sobre nosotros</a></li>
                    <li><a href="<?= e(base_url('liderazgo-institucional')) ?>">Liderazgo institucional</a></li>
                    <li><a href="<?= e(base_url('instalaciones')) ?>">Instalaciones</a></li>
                    <li><a href="<?= e(base_url('contacto')) ?>">Contacto</a></li>
                </ul>
            </div>
            <div>
                <h3 class="footer-title">Servicios</h3>
                <ul class="footer-list">
                    <li><a href="<?= e(base_url('servicios')) ?>">Todos los servicios</a></li>
                    <li><a href="<?= e(base_url('repositorio')) ?>">Repositorio Digital</a></li>
                    <li><a href="<?= e(base_url('servicios/emergencia-adulto-y-pediatrica')) ?>">Emergencias 24/7</a></li>
                    <li><a href="<?= e(base_url('servicios/laboratorio-clinico')) ?>">Laboratorio clínico</a></li>
                    <li><a href="<?= e(base_url('servicios/tomografia')) ?>">Tomografía</a></li>
                    <li><a href="<?= e(base_url('servicios/farmacia')) ?>">Farmacia hospitalaria</a></li>
                </ul>
            </div>
            <div>
                <h3 class="footer-title">Pacientes y visitantes</h3>
                <ul class="footer-list">
                    <li><a href="<?= e(base_url('portal/login.php')) ?>"><strong>🩺 Portal del paciente</strong></a></li>
                    <li><a href="<?= e(base_url('tu-visita')) ?>">Tu visita</a></li>
                    <li><a href="<?= e(base_url('preparacion-para-tu-cita')) ?>">Preparación para tu cita</a></li>
                    <li><a href="<?= e(base_url('seguros-aceptados')) ?>">Seguros aceptados</a></li>
                    <li><a href="<?= e(base_url('derechos-y-deberes')) ?>">Derechos y deberes</a></li>
                    <li><a href="<?= e(base_url('preguntas-frecuentes')) ?>">Preguntas frecuentes</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© <?= e($year) ?> Hospital General Las Colinas. Todos los derechos reservados.</span>
            <a href="<?= e(base_url('politica-de-privacidad')) ?>">Política de privacidad</a>
            <a href="<?= e(base_url('terminos-de-uso')) ?>">Términos de uso</a>
            <a href="<?= e(base_url('mapa-del-sitio')) ?>">Mapa del sitio</a>
        </div>
    </footer>
    <?php
}

function render_appointment_modal(array $services, string $defaultSpecialty = ''): void
{
    ?>
    <div id="appointmentModal" class="modal-shell hidden" role="dialog" aria-modal="true"
        aria-labelledby="appointmentTitle">
        <div class="modal-panel">
            <div class="modal-header">
                <div>
                    <h2 id="appointmentTitle">Agendar cita</h2>
                    <p>Completa tus datos y nuestro equipo te contactará.</p>
                </div>
                <button type="button" class="js-close-appointment modal-close" aria-label="Cerrar">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <form id="appointmentForm" class="space-y-4 p-6" action="<?= e(base_url('api/appointment.php')) ?>"
                method="post">
                <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
                <div>
                    <label for="name" class="form-label">Nombre completo</label>
                    <input id="name" name="name" type="text" required class="form-input" placeholder="Ej. Juan Pérez">
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="phone" class="form-label">Teléfono</label>
                        <input id="phone" name="phone" type="tel" required class="form-input" placeholder="(809) 000-0000">
                    </div>
                    <div>
                        <label for="date" class="form-label">Fecha preferida</label>
                        <input id="date" name="date" type="date" required class="form-input">
                    </div>
                </div>
                <div>
                    <label for="specialty" class="form-label">Especialidad</label>
                    <select id="specialty" name="specialty" required class="form-input">
                        <option value="">Seleccionar</option>
                        <?php foreach ($services['consultas']['items'] as $specialty): ?>
                            <option value="<?= e($specialty) ?>" <?= $defaultSpecialty === $specialty ? 'selected' : '' ?>>
                                <?= e($specialty) ?></option>
                        <?php endforeach; ?>
                        <option value="<?= e($defaultSpecialty !== '' ? $defaultSpecialty : 'Otra') ?>" <?= $defaultSpecialty !== '' && !in_array($defaultSpecialty, $services['consultas']['items'], true) ? 'selected' : '' ?>>
                            <?= e($defaultSpecialty !== '' ? $defaultSpecialty : 'Otra') ?>
                        </option>
                    </select>
                </div>
                <div>
                    <label for="message" class="form-label">Mensaje opcional</label>
                    <textarea id="message" name="message" rows="3" class="form-input"
                        placeholder="Describe brevemente tu necesidad"></textarea>
                </div>
                <button type="submit" class="btn btn-green w-full justify-center py-4">
                    Enviar solicitud
                    <i data-lucide="send" class="h-4 w-4"></i>
                </button>
                <p id="appointmentStatus" class="hidden rounded-md px-4 py-3 text-sm font-bold"></p>
                <p class="text-center text-xs leading-6 text-slate-400">Al enviar, aceptas ser contactado por el equipo de
                    atención del hospital.</p>
            </form>
        </div>
    </div>
    <?php
}
