/**
 * Helper réutilisable pour la sélection multiple + barre d'action flottante.
 *
 * Usage côté Blade :
 *   <table>
 *     <thead><th><input data-bulk-select-all></th>...</thead>
 *     <tbody>
 *       <tr><td><input class="bulk-checkbox" value="{{ $row->id }}"></td>...</tr>
 *     </tbody>
 *   </table>
 *
 *   <div class="bulk-bar" id="my-bar">
 *     <div class="bulk-count"><strong class="bulk-count-num">0</strong> sélectionné(s)</div>
 *     <div class="bulk-actions">
 *       <button type="button" data-bulk-clear class="secondary">Désélectionner</button>
 *       <button type="button" onclick="doBulkXyz()">Action XYZ</button>
 *     </div>
 *   </div>
 *
 *   <script>
 *     const helper = window.BulkBar.init({
 *       barId: 'my-bar',
 *       checkboxSelector: '.bulk-checkbox',
 *       selectAllSelector: '[data-bulk-select-all]',
 *     });
 *     function doBulkXyz() {
 *       const ids = helper.getSelectedIds();
 *       if (ids.length === 0) return;
 *       // soumettre via fetch ou form
 *     }
 *   </script>
 */
window.BulkBar = {
    init(opts) {
        const bar = document.getElementById(opts.barId);
        if (!bar) return null;
        const countEl = bar.querySelector('.bulk-count-num');
        const checkboxSel = opts.checkboxSelector || '.bulk-checkbox';
        const allSel = opts.selectAllSelector || '[data-bulk-select-all]';

        const all = () => Array.from(document.querySelectorAll(checkboxSel));

        function sync() {
            const selected = all().filter((cb) => cb.checked);
            countEl.textContent = selected.length;
            bar.classList.toggle('open', selected.length > 0);
            // Highlight des lignes
            all().forEach((cb) => {
                const tr = cb.closest('tr');
                if (tr) tr.classList.toggle('bulk-selected', cb.checked);
            });
            // État indéterminé du select-all
            const selectAll = document.querySelector(allSel);
            if (selectAll) {
                const total = all().length;
                selectAll.checked = selected.length === total && total > 0;
                selectAll.indeterminate =
                    selected.length > 0 && selected.length < total;
            }
            if (opts.onChange) opts.onChange(selected);
        }

        all().forEach((cb) => cb.addEventListener('change', sync));
        bar.querySelector('[data-bulk-clear]')?.addEventListener('click', () => {
            all().forEach((cb) => (cb.checked = false));
            sync();
        });

        const selectAll = document.querySelector(allSel);
        if (selectAll) {
            selectAll.addEventListener('change', () => {
                all().forEach((cb) => (cb.checked = selectAll.checked));
                sync();
            });
        }

        return {
            getSelectedIds: () =>
                all()
                    .filter((cb) => cb.checked)
                    .map((cb) => cb.value),
            clear: () => {
                all().forEach((cb) => (cb.checked = false));
                sync();
            },
            sync,
        };
    },

    /**
     * Soumet une action bulk via POST avec CSRF.
     * options.url, options.ids[], options.payload (data extra), options.confirm (string)
     * Retourne une promesse résolue après reload de la page.
     */
    async submit(options) {
        if (options.confirm && !confirm(options.confirm)) return false;
        const fd = new FormData();
        (options.ids || []).forEach((id) => fd.append('ids[]', id));
        for (const [k, v] of Object.entries(options.payload || {})) {
            fd.append(k, v);
        }
        const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
        try {
            const r = await fetch(options.url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/json',
                },
                body: fd,
            });
            if (r.redirected) {
                window.location = r.url;
                return true;
            }
            const data = await r.json().catch(() => ({}));
            if (data.ok === false) {
                alert(data.error || 'Action impossible.');
                return false;
            }
            window.location.reload();
            return true;
        } catch (e) {
            alert('Erreur réseau : ' + e.message);
            return false;
        }
    },
};
