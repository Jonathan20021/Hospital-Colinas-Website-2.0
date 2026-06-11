/* Repositorio Digital — búsqueda y filtros en cliente.
   Los documentos se renderizan en el servidor; sin JS la lista completa
   sigue visible. Este script solo filtra, ordena y sincroniza la URL. */
(function () {
    'use strict';

    const list = document.querySelector('[data-repo-list]');
    if (!list) return;

    const items = Array.from(list.querySelectorAll('[data-repo-item]'));
    const searchInput = document.getElementById('repoSearch');
    const clearSearchBtn = document.querySelector('[data-repo-clear-search]');
    const scopeButtons = Array.from(document.querySelectorAll('[data-repo-scope]'));
    const catButtons = Array.from(document.querySelectorAll('[data-repo-cat]'));
    const typeSelect = document.querySelector('[data-repo-filter="type"]');
    const sortSelect = document.querySelector('[data-repo-sort]');
    const resetButtons = Array.from(document.querySelectorAll('[data-repo-reset]'));
    const countLabel = document.querySelector('[data-repo-count]');
    const emptyState = document.querySelector('[data-repo-empty]');
    const emptyQuery = document.querySelector('[data-repo-empty-query]');
    const moreWrap = document.querySelector('[data-repo-more]');
    const moreLabel = document.querySelector('[data-repo-more-label]');

    const PAGE_SIZE = 30;
    const state = { q: '', scope: '', cat: '', type: '', sort: 'relevance', limit: PAGE_SIZE };

    function normalize(value) {
        return value
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function tokensOf(query) {
        return normalize(query).split(/\s+/).filter(Boolean);
    }

    function matches(item, tokens) {
        if (state.scope && item.dataset.scope !== state.scope) return false;
        if (state.cat && item.dataset.cat !== state.cat) return false;
        if (state.type && item.dataset.type !== state.type) return false;
        if (!tokens.length) return true;
        const haystack = item.dataset.search || '';
        return tokens.every((token) => haystack.includes(token));
    }

    function compare(a, b) {
        if (state.sort === 'recent') {
            return (Number(b.dataset.year) || 0) - (Number(a.dataset.year) || 0)
                || a.dataset.title.localeCompare(b.dataset.title, 'es');
        }
        if (state.sort === 'alpha') {
            return a.dataset.title.localeCompare(b.dataset.title, 'es');
        }
        // relevance: destacados primero, luego año, luego título
        return (Number(b.dataset.featured) - Number(a.dataset.featured))
            || (Number(b.dataset.year) || 0) - (Number(a.dataset.year) || 0)
            || a.dataset.title.localeCompare(b.dataset.title, 'es');
    }

    function hasActiveFilters() {
        return Boolean(state.q || state.scope || state.cat || state.type);
    }

    function apply() {
        const tokens = tokensOf(state.q);
        let matched = 0;

        const sorted = items.slice().sort(compare);
        sorted.forEach((item) => list.appendChild(item));

        sorted.forEach((item) => {
            const isMatch = matches(item, tokens);
            const show = isMatch && matched < state.limit;
            if (isMatch) matched++;
            item.classList.toggle('hidden', !show);
        });

        const remaining = matched - Math.min(matched, state.limit);

        if (countLabel) {
            countLabel.textContent = matched === 1 ? '1 documento' : matched + ' documentos';
        }
        if (moreWrap) {
            moreWrap.classList.toggle('hidden', remaining <= 0);
            if (moreLabel && remaining > 0) {
                moreLabel.textContent = 'Mostrar más documentos (' + remaining + ' restantes)';
            }
        }
        if (emptyState) {
            emptyState.classList.toggle('hidden', matched > 0);
            if (emptyQuery) {
                emptyQuery.textContent = state.q ? ' para «' + state.q + '»' : '';
            }
        }
        if (clearSearchBtn) {
            clearSearchBtn.classList.toggle('hidden', !state.q);
        }
        resetButtons.forEach((btn) => {
            if (!btn.closest('[data-repo-empty]')) {
                btn.classList.toggle('hidden', !hasActiveFilters());
            }
        });

        scopeButtons.forEach((btn) => {
            btn.setAttribute('aria-pressed', String(btn.dataset.repoScope === state.scope));
        });
        catButtons.forEach((btn) => {
            btn.setAttribute('aria-pressed', String(btn.dataset.repoCat === state.cat));
        });

        syncUrl();
    }

    function syncUrl() {
        const params = new URLSearchParams();
        if (state.q) params.set('q', state.q);
        if (state.scope) params.set('origen', state.scope);
        if (state.cat) params.set('especialidad', state.cat);
        if (state.type) params.set('tipo', state.type);
        if (state.sort !== 'relevance') params.set('orden', state.sort);
        const query = params.toString();
        const url = window.location.pathname + (query ? '?' + query : '');
        window.history.replaceState(null, '', url);
    }

    function readUrl() {
        const params = new URLSearchParams(window.location.search);
        state.q = params.get('q') || '';
        state.scope = params.get('origen') || '';
        state.cat = params.get('especialidad') || '';
        state.type = params.get('tipo') || '';
        state.sort = params.get('orden') || 'relevance';

        if (searchInput) searchInput.value = state.q;
        if (typeSelect) typeSelect.value = state.type;
        if (sortSelect) sortSelect.value = state.sort;
    }

    function applyFresh() {
        state.limit = PAGE_SIZE;
        apply();
    }

    function reset() {
        state.q = '';
        state.scope = '';
        state.cat = '';
        state.type = '';
        if (searchInput) searchInput.value = '';
        if (typeSelect) typeSelect.value = '';
        applyFresh();
    }

    let debounceTimer = null;
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            window.clearTimeout(debounceTimer);
            debounceTimer = window.setTimeout(() => {
                state.q = searchInput.value.trim();
                applyFresh();
            }, 120);
        });
        searchInput.closest('form')?.addEventListener('submit', (event) => {
            event.preventDefault();
            state.q = searchInput.value.trim();
            applyFresh();
        });
    }

    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', () => {
            state.q = '';
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
            }
            applyFresh();
        });
    }

    scopeButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            state.scope = btn.dataset.repoScope || '';
            applyFresh();
        });
    });

    catButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            state.cat = btn.dataset.repoCat || '';
            applyFresh();
        });
    });

    if (typeSelect) {
        typeSelect.addEventListener('change', () => {
            state.type = typeSelect.value;
            applyFresh();
        });
    }

    if (sortSelect) {
        sortSelect.addEventListener('change', () => {
            state.sort = sortSelect.value;
            apply();
        });
    }

    if (moreWrap) {
        moreWrap.querySelector('button')?.addEventListener('click', () => {
            state.limit += PAGE_SIZE;
            apply();
        });
    }

    resetButtons.forEach((btn) => btn.addEventListener('click', reset));

    document.querySelectorAll('[data-repo-chip]').forEach((chip) => {
        chip.addEventListener('click', () => {
            state.q = chip.dataset.repoChip || '';
            if (searchInput) searchInput.value = state.q;
            applyFresh();
            document.querySelector('[data-repo-toolbar]')?.scrollIntoView({
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                block: 'start',
            });
        });
    });

    // Atajo «/» para enfocar la búsqueda (como en bibliotecas y documentación)
    document.addEventListener('keydown', (event) => {
        if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) return;
        const target = event.target;
        if (target instanceof HTMLElement &&
            (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT' || target.isContentEditable)) {
            return;
        }
        event.preventDefault();
        searchInput?.focus();
    });

    readUrl();
    apply();
})();
