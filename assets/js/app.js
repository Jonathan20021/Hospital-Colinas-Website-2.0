(function () {
    const ready = (fn) => {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    };

    ready(() => {
        if (window.lucide) {
            window.lucide.createIcons();
        }

        const header = document.getElementById('siteHeader');
        const menuToggle = document.getElementById('menuToggle');
        const mobileMenu = document.getElementById('mobileMenu');
        const menuIcon = menuToggle?.querySelector('.menu-icon');
        const closeIcon = menuToggle?.querySelector('.close-icon');
        const navLinks = [...document.querySelectorAll('.nav-link')];

        const setHeaderState = () => {
            header?.classList.toggle('is-scrolled', window.scrollY > 8);
        };

        setHeaderState();
        window.addEventListener('scroll', setHeaderState, { passive: true });

        menuToggle?.addEventListener('click', () => {
            const isOpen = !mobileMenu.classList.contains('hidden');
            mobileMenu.classList.toggle('hidden', isOpen);
            menuIcon?.classList.toggle('hidden', !isOpen);
            closeIcon?.classList.toggle('hidden', isOpen);
            menuToggle.setAttribute('aria-expanded', String(!isOpen));
        });

        document.querySelectorAll('.mobile-link, .mobile-sub-link').forEach((link) => {
            link.addEventListener('click', () => {
                mobileMenu?.classList.add('hidden');
                menuIcon?.classList.remove('hidden');
                closeIcon?.classList.add('hidden');
                menuToggle?.setAttribute('aria-expanded', 'false');
            });
        });

        // ===== Desktop nav dropdown =====
        document.querySelectorAll('[data-nav-dropdown]').forEach((dropdown) => {
            const toggle = dropdown.querySelector('.nav-dropdown-toggle');
            if (!toggle) return;

            toggle.addEventListener('click', (event) => {
                event.stopPropagation();
                const wasOpen = dropdown.classList.contains('is-open');
                document.querySelectorAll('[data-nav-dropdown].is-open').forEach((d) => {
                    d.classList.remove('is-open');
                    d.querySelector('.nav-dropdown-toggle')?.setAttribute('aria-expanded', 'false');
                });
                if (!wasOpen) {
                    dropdown.classList.add('is-open');
                    toggle.setAttribute('aria-expanded', 'true');
                }
            });

            // Close on link click inside menu
            dropdown.querySelectorAll('.nav-dropdown-menu a').forEach((link) => {
                link.addEventListener('click', () => {
                    dropdown.classList.remove('is-open');
                    toggle.setAttribute('aria-expanded', 'false');
                });
            });
        });

        // Click outside closes any open dropdown
        document.addEventListener('click', (event) => {
            if (!event.target.closest('[data-nav-dropdown]')) {
                document.querySelectorAll('[data-nav-dropdown].is-open').forEach((d) => {
                    d.classList.remove('is-open');
                    d.querySelector('.nav-dropdown-toggle')?.setAttribute('aria-expanded', 'false');
                });
            }
        });

        // Escape closes dropdowns
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                document.querySelectorAll('[data-nav-dropdown].is-open').forEach((d) => {
                    d.classList.remove('is-open');
                    d.querySelector('.nav-dropdown-toggle')?.setAttribute('aria-expanded', 'false');
                });
            }
        });

        const sections = navLinks
            .map((link) => document.getElementById(link.dataset.section))
            .filter(Boolean);

        const sectionObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                navLinks.forEach((link) => {
                    link.classList.toggle('is-active', link.dataset.section === entry.target.id);
                });
            });
        }, { rootMargin: '-40% 0px -52% 0px', threshold: 0 });

        sections.forEach((section) => sectionObserver.observe(section));

        const modal = document.getElementById('appointmentModal');
        const status = document.getElementById('appointmentStatus');
        const form = document.getElementById('appointmentForm');
        const nameInput = document.getElementById('name');

        const openModal = (event) => {
            event?.preventDefault();
            modal?.classList.remove('hidden');
            modal?.classList.add('flex');
            document.body.style.overflow = 'hidden';
            setTimeout(() => nameInput?.focus(), 60);
        };

        const closeModal = () => {
            modal?.classList.add('hidden');
            modal?.classList.remove('flex');
            document.body.style.overflow = '';
            if (status) {
                status.className = 'hidden rounded-md px-4 py-3 text-sm font-bold';
                status.textContent = '';
            }
        };

        // Todos los CTAs "Agendar cita" del sitio público redirigen al Portal
        // de Pacientes (login). El modal de solicitud por correo se mantiene
        // como fallback en el DOM, pero ya no se invoca.
        document.querySelectorAll('.js-open-appointment').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                // Si el botón tiene data-specialty, lo guardamos para el portal
                const spec = button.dataset.specialty || button.dataset.specialtyId;
                if (spec) {
                    try { sessionStorage.setItem('portal_default_specialty', spec); } catch (e) {}
                }
                window.location.href = '/portal/login.php';
            });
        });

        document.querySelectorAll('.js-close-appointment').forEach((button) => {
            button.addEventListener('click', closeModal);
        });

        modal?.addEventListener('click', (event) => {
            if (event.target === modal) closeModal();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeModal();
                closeCommand();
                closeLightbox();
            }
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                openCommand();
            }
            if (event.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName || '')) {
                event.preventDefault();
                openCommand();
            }
        });

        form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submit = form.querySelector('button[type="submit"]');
            submit.disabled = true;
            submit.classList.add('opacity-70');

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'Accept': 'application/json' }
                });
                const data = await response.json();
                status.className = `rounded-md px-4 py-3 text-sm font-bold ${data.ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'}`;
                status.textContent = data.message || (data.ok ? 'Solicitud enviada.' : 'No se pudo enviar la solicitud.');
                if (data.ok) {
                    form.reset();
                    setTimeout(closeModal, 1800);
                }
            } catch (error) {
                status.className = 'rounded-md bg-red-50 px-4 py-3 text-sm font-bold text-red-700';
                status.textContent = 'No se pudo conectar con el servidor. Intenta nuevamente.';
            } finally {
                submit.disabled = false;
                submit.classList.remove('opacity-70');
            }
        });

        document.querySelectorAll('[data-service-tab]').forEach((tab) => {
            tab.addEventListener('click', () => {
                const id = tab.dataset.serviceTab;
                document.querySelectorAll('[data-service-tab]').forEach((item) => {
                    const active = item === tab;
                    item.classList.toggle('is-active', active);
                    item.setAttribute('aria-selected', String(active));
                });
                document.querySelectorAll('[data-service-panel]').forEach((panel) => {
                    panel.classList.toggle('hidden', panel.dataset.servicePanel !== id);
                });
            });
        });

        const careInput = document.getElementById('careSearch');
        const careForm = document.getElementById('careSearchForm');
        const careResults = document.getElementById('careResults');
        const careEmpty = document.getElementById('careEmpty');
        const careClear = document.getElementById('careClear');
        const carePanel = careForm?.closest('.finder-panel');
        const careItems = [...document.querySelectorAll('.care-result')];
        const careGroups = [...document.querySelectorAll('.care-group')];

        const normalize = (value) => value
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();

        const filterCare = (query) => {
            const normalized = normalize(query);
            const isSearching = normalized.length > 0;
            let visible = 0;

            careItems.forEach((item) => {
                const key = normalize(item.dataset.careName || item.textContent);
                const match = isSearching && key.includes(normalized);
                item.classList.toggle('is-hidden', !match);
                if (match) visible += 1;
            });

            careGroups.forEach((group) => {
                const groupHasMatch = group.querySelector('.care-result:not(.is-hidden)') !== null;
                group.classList.toggle('is-hidden', isSearching && !groupHasMatch);
            });

            careResults?.classList.toggle('is-active', isSearching);
            carePanel?.classList.toggle('is-searching', isSearching);
            careEmpty?.classList.toggle('hidden', !isSearching || visible > 0);
            careClear?.classList.toggle('hidden', !isSearching);
        };

        careInput?.addEventListener('input', () => filterCare(careInput.value));
        careForm?.addEventListener('submit', (event) => {
            event.preventDefault();
            filterCare(careInput?.value || '');
        });
        careClear?.addEventListener('click', () => {
            if (!careInput) return;
            careInput.value = '';
            filterCare('');
            careInput.focus();
        });

        document.querySelectorAll('[data-fill-search]').forEach((button) => {
            button.addEventListener('click', () => {
                const query = button.dataset.fillSearch || '';
                if (careInput) {
                    careInput.value = query;
                    filterCare(query);
                    document.getElementById('buscar-atencion')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    if (query) setTimeout(() => careInput.focus(), 250);
                }
            });
        });

        const doctorSearch = document.getElementById('doctorSearch');
        const doctorSearchForm = document.getElementById('doctorSearchForm');
        const doctorCards = [...document.querySelectorAll('[data-doctor-card]')];
        const doctorLivePanel = document.getElementById('doctorLivePanel');
        const doctorLiveResults = [...document.querySelectorAll('[data-live-result]')];
        const doctorResultCount = document.getElementById('doctorResultCount');
        const doctorClear = document.getElementById('doctorClear');
        const doctorEmpty = document.getElementById('doctorEmpty');
        let activeDoctorFilter = 'all';

        const filterDoctors = () => {
            const query = normalize(doctorSearch?.value || '');
            let visible = 0;
            let liveVisible = 0;

            doctorCards.forEach((card) => {
                const key = normalize(card.dataset.search || card.textContent);
                const matchesQuery = !query || key.includes(query);
                const matchesFilter = activeDoctorFilter === 'all' || key.includes(activeDoctorFilter);
                const show = matchesQuery && matchesFilter;
                card.classList.toggle('is-hidden', !show);
                if (show) visible += 1;
            });

            doctorLiveResults.forEach((item) => {
                const key = normalize(item.dataset.search || item.textContent);
                const show = query.length > 0 && key.includes(query);
                item.classList.toggle('is-hidden', !show);
                if (show) liveVisible += 1;
            });

            doctorLivePanel?.classList.toggle('hidden', query.length === 0);
            doctorClear?.classList.toggle('hidden', query.length === 0);
            if (doctorResultCount) {
                doctorResultCount.textContent = `${query.length > 0 ? liveVisible : visible} Resultado/s`;
            }
            doctorEmpty?.classList.toggle('hidden', visible > 0);
        };

        doctorSearch?.addEventListener('input', filterDoctors);
        doctorSearchForm?.addEventListener('submit', (event) => {
            event.preventDefault();
            filterDoctors();
        });
        doctorClear?.addEventListener('click', () => {
            if (!doctorSearch) return;
            doctorSearch.value = '';
            filterDoctors();
            doctorSearch.focus();
        });
        document.addEventListener('click', (event) => {
            if (!doctorSearchForm?.contains(event.target)) {
                doctorLivePanel?.classList.add('hidden');
            }
        });
        doctorSearch?.addEventListener('focus', () => {
            if (normalize(doctorSearch.value).length > 0) {
                doctorLivePanel?.classList.remove('hidden');
            }
        });

        document.querySelectorAll('[data-doctor-filter]').forEach((button) => {
            button.addEventListener('click', () => {
                activeDoctorFilter = normalize(button.dataset.doctorFilter || 'all');
                document.querySelectorAll('[data-doctor-filter]').forEach((item) => {
                    item.classList.toggle('is-active', item === button);
                });
                filterDoctors();
            });
        });

        const command = document.getElementById('commandCenter');
        const commandInput = document.getElementById('commandInput');
        const commandClose = document.getElementById('commandClose');
        const commandItems = [...document.querySelectorAll('[data-command-name]')];

        function openCommand(event) {
            event?.preventDefault();
            if (!command) return;
            command?.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            setTimeout(() => commandInput?.focus(), 50);
        }

        function closeCommand() {
            command?.classList.add('hidden');
            if (modal?.classList.contains('hidden')) {
                document.body.style.overflow = '';
            }
            if (commandInput) {
                commandInput.value = '';
                commandItems.forEach((item) => item.classList.remove('is-hidden'));
            }
        }

        document.querySelectorAll('.js-open-command').forEach((button) => {
            button.addEventListener('click', openCommand);
        });

        commandClose?.addEventListener('click', closeCommand);
        command?.addEventListener('click', (event) => {
            if (event.target === command) closeCommand();
        });

        commandInput?.addEventListener('input', () => {
            const query = normalize(commandInput.value);
            commandItems.forEach((item) => {
                const key = normalize(item.dataset.commandName || item.textContent);
                item.classList.toggle('is-hidden', query.length > 0 && !key.includes(query));
            });
        });

        commandItems.forEach((item) => {
            item.addEventListener('click', () => {
                setTimeout(closeCommand, 120);
            });
        });

        const video = document.getElementById('hospitalVideo');
        const videoToggle = document.getElementById('videoToggle');
        const playIcon = videoToggle?.querySelector('.play-icon');
        const pauseIcon = videoToggle?.querySelector('.pause-icon');

        const syncVideoButton = () => {
            const paused = !video || video.paused;
            playIcon?.classList.toggle('hidden', !paused);
            pauseIcon?.classList.toggle('hidden', paused);
            videoToggle?.setAttribute('aria-label', paused ? 'Reproducir video' : 'Pausar video');
        };

        videoToggle?.addEventListener('click', async () => {
            if (!video) return;
            video.controls = true;
            try {
                if (video.paused) await video.play();
                else video.pause();
            } catch (error) {
                video.load();
                video.focus({ preventScroll: true });
            } finally {
                syncVideoButton();
            }
        });

        video?.addEventListener('loadedmetadata', syncVideoButton);
        video?.addEventListener('play', syncVideoButton);
        video?.addEventListener('pause', syncVideoButton);

        const rail = document.getElementById('galleryRail');
        document.querySelectorAll('[data-gallery-scroll]').forEach((button) => {
            button.addEventListener('click', () => {
                const direction = Number(button.dataset.galleryScroll);
                rail?.scrollBy({ left: direction * 380, behavior: 'smooth' });
            });
        });

        const lightbox = document.getElementById('lightbox');
        const lightboxImage = document.getElementById('lightboxImage');
        const lightboxTitle = document.getElementById('lightboxTitle');
        const lightboxText = document.getElementById('lightboxText');
        const lightboxClose = document.getElementById('lightboxClose');

        function closeLightbox() {
            lightbox?.classList.add('hidden');
            lightbox?.classList.remove('flex');
            if (lightboxImage) lightboxImage.src = '';
        }

        document.querySelectorAll('[data-gallery-src]').forEach((button) => {
            button.addEventListener('click', () => {
                lightboxImage.src = button.dataset.gallerySrc;
                lightboxImage.alt = button.dataset.galleryTitle || '';
                lightboxTitle.textContent = button.dataset.galleryTitle || '';
                lightboxText.textContent = button.dataset.galleryText || '';
                lightbox.classList.remove('hidden');
                lightbox.classList.add('flex');
            });
        });

        lightboxClose?.addEventListener('click', closeLightbox);
        lightbox?.addEventListener('click', (event) => {
            if (event.target === lightbox) closeLightbox();
        });
    });
})();
