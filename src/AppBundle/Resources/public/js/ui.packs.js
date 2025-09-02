document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('fanpacks-sort');
    const grid = document.querySelector('.pack-plane-grid-3');
    const packs = Array.from(grid.children);
    const packsPerPage = 9;
    let currentPage = 1;

    function getActiveTypes() {
        return Array.from(document.querySelectorAll('.fanpacks-type-btn.active')).map(btn => btn.dataset.type);
    }
    function getActiveStatuses() {
        return Array.from(document.querySelectorAll('.fanpacks-status-btn.active')).map(btn => btn.dataset.status);
    }
    function getActiveCustoms() {
        const btns = document.querySelectorAll('.fanpacks-custom-btn.active');
        // Si aucun bouton actif, on considère tous actifs (affichage par défaut)
        if (btns.length === 0) {
            return ['official', 'fanmade', 'private'];
        }
        return Array.from(btns).map(btn => btn.dataset.custom);
    }

    function sortPacks(mode, filtered) {
        filtered.sort(function(a, b) {
            if (mode === 'date') {
                return a.dataset.release < b.dataset.release ? 1 : -1;
            }
            if (mode === 'creator') {
                return a.dataset.creator.localeCompare(b.dataset.creator);
            }
            if (mode === 'alpha') {
                return a.dataset.alpha.localeCompare(b.dataset.alpha);
            }
            return 0;
        });
    }

    function getFilteredAndSorted() {
        const activeTypes = getActiveTypes();
        const activeStatuses = getActiveStatuses();
        const activeCustoms = getActiveCustoms();
        const filtered = packs.filter(p =>
            activeTypes.includes(p.dataset.type) &&
            activeStatuses.includes(p.dataset.status) &&
            (p.dataset.custom ? activeCustoms.includes(p.dataset.custom) : true)
        );
        sortPacks(select.value, filtered);
        filtered.forEach(p => grid.appendChild(p));
        return filtered;
    }

    function showPage(page, filtered) {
        filtered.forEach((p, i) => {
            p.style.display = (i >= (page-1)*packsPerPage && i < page*packsPerPage) ? '' : 'none';
        });
        // Hide others
        packs.filter(p => !filtered.includes(p)).forEach(p => p.style.display = 'none');
        // Update pagination links (top and bottom)
        const totalPages = Math.ceil(filtered.length / packsPerPage);
        ['fanpacks-pagination-top', 'fanpacks-pagination'].forEach(function(navId) {
            const pagNav = document.getElementById(navId);
            if (pagNav) {
                pagNav.innerHTML = '';
                for (let p=1; p<=totalPages; ++p) {
                    if (p === page) {
                        pagNav.innerHTML += `<span style="font-weight:bold; color:#1976d2; margin:0 0.5em;">${p}</span>`;
                    } else {
                        pagNav.innerHTML += `<a href="#" class="fanpacks-page-link" data-page="${p}" style="margin:0 0.5em;">${p}</a>`;
                    }
                }
                Array.from(pagNav.querySelectorAll('.fanpacks-page-link')).forEach(link => {
                    link.onclick = function(e) {
                        e.preventDefault();
                        currentPage = parseInt(this.dataset.page);
                        showPage(currentPage, filtered);
                    };
                });
            }
        });
    }

    function updateDisplay(resetPage = false) {
        const filtered = getFilteredAndSorted();
        const totalPages = Math.ceil(filtered.length / packsPerPage);
        if (resetPage || currentPage > totalPages) currentPage = 1;
        showPage(currentPage, filtered);
    }

    if (select && grid) {
        select.addEventListener('change', function() {
            updateDisplay(true); // reset page on sort
        });
        document.querySelectorAll('.fanpacks-type-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                btn.classList.toggle('active');
                updateDisplay(true); // reset page on filter
            });
        });
        document.querySelectorAll('.fanpacks-status-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                btn.classList.toggle('active');
                updateDisplay(true); // reset page on filter
            });
        });
        // Gestion des boutons custom
        document.querySelectorAll('.fanpacks-custom-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                this.classList.toggle('active');
                // Toujours au moins un bouton actif
                if (![...document.querySelectorAll('.fanpacks-custom-btn')].some(b => b.classList.contains('active'))) {
                    this.classList.add('active');
                }
                updateDisplay(true);
            });
        });
        // Tri initial par date et tous types cochés
        updateDisplay(true);
    }
});