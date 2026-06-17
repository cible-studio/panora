/**
 * rapports-live.js — Page Rapports & Analyses pilotée par les filtres en AJAX.
 *
 * ARCHITECTURE :
 *   - Écoute les change/input sur #form-periode (filtres + dates + presets).
 *   - Debounce 300ms — un seul fetch même si l'utilisateur enchaîne 5 clics.
 *   - AbortController — annule la requête précédente si une nouvelle arrive.
 *   - Réponse JSON multi-sections — chaque section est injectée via innerHTML
 *     dans son conteneur (#rpt-summary, #rpt-topcards, #rpt-kpis, #panel-*).
 *   - data-export-route — chaque bouton d'export voit son href reconstruit
 *     avec la query string courante (filter_zone, preset, etc.).
 *   - data-route-base — les 3 cartes top (Rapport campagnes/annulations/taxes)
 *     voient leur href reconstruit pour propager les filtres aux sous-pages.
 *   - Charts Chart.js — recrées à partir des configs renvoyées par l'endpoint
 *     (data.charts), via `reinitCharts()`. Évite les memory leaks.
 *
 * PROGRESSIVE ENHANCEMENT (Garde-fou A) :
 *   - Si ce JS n'est pas chargé, le form garde son submit GET classique →
 *     la page se recharge entièrement comme avant. Aucun blocage utilisateur.
 *   - Si une requête AJAX échoue (hors AbortError), un toast d'erreur
 *     apparaît mais les anciennes données restent affichées (pas d'écran blanc).
 *
 * PERF (Garde-fou B) :
 *   - L'endpoint /admin/rapports/ajax renvoie un header X-Rapports-Ms — visible
 *     dans DevTools réseau pour diagnostiquer la latence.
 *   - Warning serveur si >2000ms (cf. controller).
 */
(function () {
    'use strict';

    const ENDPOINT      = '/admin/rapports/ajax';
    const DEBOUNCE_MS   = 300;
    const SECTION_IDS   = ['rpt-summary', 'rpt-topcards', 'rpt-kpis'];
    const TAB_IDS       = ['occupation', 'performance', 'periodes', 'campagnes', 'ca', 'zones', 'clients', 'decappages', 'insights'];
    // Mapping des tab keys de l'endpoint vers les ids DOM des panneaux.
    // La nav d'onglets utilise des ids id="panel-X" (ex. panel-occupation),
    // mais panel-panneaux correspond à la clé 'performance'/'panneaux'.
    const TAB_DOM_MAP   = {
        occupation:  'panel-occupation',
        performance: 'panel-panneaux',
        periodes:    'panel-periodes',
        campagnes:   'panel-campagnes',
        ca:          'panel-ca',
        zones:       'panel-zones',
        clients:     'panel-clients',
        decappages:  'panel-decap',
        insights:    'panel-insights',
    };

    const RapportsLive = {
        form: null,
        abortController: null,
        debounceTimer: null,
        lastFingerprint: null,

        init() {
            this.form = document.getElementById('form-periode');
            if (!this.form) {
                console.warn('[rapports-live] #form-periode introuvable — JS désactivé.');
                return;
            }

            // Intercepte le submit pour éviter le full reload.
            this.form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.reload(true);
            });

            // Tout changement de filtre déclenche un reload debounce.
            // On capture aussi 'click' pour les boutons preset (Today, Week, …)
            // qui sont des <a> à l'intérieur du form — leur href doit être
            // intercepté pour rester en AJAX.
            this.form.addEventListener('change', () => this.scheduleReload());
            this.form.addEventListener('input',  () => this.scheduleReload());

            // Boutons presets : ce sont des <a href="?preset=…"> dans le form.
            // On intercepte le clic, on injecte le preset comme hidden input
            // temporaire dans le form, on relance, et on annule la navigation.
            this.form.querySelectorAll('a[href*="preset="]').forEach(a => {
                a.addEventListener('click', (e) => {
                    e.preventDefault();
                    const url = new URL(a.href, window.location.origin);
                    const preset = url.searchParams.get('preset');
                    this.setHidden('preset', preset || '');
                    // Clear from/to si on switch sur un preset
                    const fromInp = this.form.querySelector('[name="from"]');
                    const toInp   = this.form.querySelector('[name="to"]');
                    if (fromInp) fromInp.value = '';
                    if (toInp)   toInp.value = '';
                    this.reload(true);
                });
            });

            // Lien "Réinitialiser" (✕) : on capture, on vide les filtres,
            // on recharge.
            const resetLink = this.form.querySelector('a[href$="/admin/rapports"]');
            if (resetLink) {
                resetLink.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.form.reset();
                    this.setHidden('preset', '');
                    this.reload(true);
                });
            }

            // Sync initial des href dynamiques (3 cartes + 4 exports) avec
            // les filtres actuellement sélectionnés (utile si la page a été
            // chargée avec des filtres dans la query string).
            this.syncDynamicHrefs(this.collectQueryString());
        },

        setHidden(name, value) {
            let inp = this.form.querySelector(`input[type="hidden"][name="${name}"]`);
            if (!inp) {
                inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = name;
                this.form.appendChild(inp);
            }
            inp.value = value;
        },

        scheduleReload() {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => this.reload(), DEBOUNCE_MS);
        },

        collectQueryString() {
            const fd = new FormData(this.form);
            // Strip valeurs vides
            const params = new URLSearchParams();
            for (const [k, v] of fd.entries()) {
                if (v !== '' && v !== null) params.set(k, v);
            }
            return params.toString();
        },

        async reload(immediate = false) {
            if (!immediate) clearTimeout(this.debounceTimer);

            if (this.abortController) this.abortController.abort();
            this.abortController = new AbortController();

            this.showLoading();
            const qs = this.collectQueryString();

            try {
                const r = await fetch(`${ENDPOINT}?${qs}`, {
                    signal: this.abortController.signal,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                });
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                const data = await r.json();

                // Dédup : même fingerprint = même réponse, on ignore
                if (data.fingerprint && data.fingerprint === this.lastFingerprint) {
                    this.hideLoading();
                    return;
                }
                this.lastFingerprint = data.fingerprint;

                this.applyResponse(data, qs);
            } catch (e) {
                if (e.name !== 'AbortError') {
                    console.error('[rapports-live]', e);
                    this.showError();
                }
            } finally {
                this.hideLoading();
            }
        },

        applyResponse(data, qs) {
            // Sections globales
            SECTION_IDS.forEach(id => {
                const el = document.getElementById(id);
                if (el && data[id.replace('rpt-', '')] !== undefined) {
                    el.innerHTML = data[id.replace('rpt-', '')];
                }
            });

            // Onglets — un seul est visible à la fois, mais on rerender TOUS
            // pour qu'un switchTab() ultérieur trouve les bonnes données.
            if (data.tabs) {
                Object.entries(data.tabs).forEach(([key, html]) => {
                    const domId = TAB_DOM_MAP[key];
                    if (!domId) return;
                    const el = document.getElementById(domId);
                    if (el) el.outerHTML = html;
                });
            }

            // Update href des boutons d'export + 3 cartes top
            this.syncDynamicHrefs(data.exports_qs || qs);

            // Rafraîchit TOUS les graphiques Chart.js + bar custom de la page.
            // window.RPT est défini par le script inline en bas d'index.blade.php
            // — il porte refreshAllCharts() qui détruit + recrée chaque chart
            // depuis le nouveau dataset, sans rechargement.
            if (data.chartData && window.RPT && typeof window.RPT.refreshAllCharts === 'function') {
                try {
                    window.RPT.refreshAllCharts(data.chartData);
                } catch (e) {
                    console.warn('[rapports-live] refreshAllCharts failed:', e);
                }
            }
        },

        syncDynamicHrefs(qs) {
            const sep = qs ? (qs.startsWith('?') ? qs : '?' + qs) : '';
            // Boutons export : <a data-export-route="…">…</a>
            document.querySelectorAll('[data-export-route]').forEach(a => {
                const base = a.dataset.exportRoute;
                if (base) a.href = base + sep;
            });
            // 3 cartes top : <a data-route-base="…">…</a>
            document.querySelectorAll('[data-route-base]').forEach(a => {
                const base = a.dataset.routeBase;
                if (base) a.href = base + sep;
            });
        },

        showLoading() {
            // Opacité réduite + curseur waiting sur les sections concernées
            ['rpt-summary', 'rpt-topcards', 'rpt-kpis'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.style.opacity = '.55';
            });
            document.querySelectorAll('.rpt-panel').forEach(el => {
                el.style.opacity = '.55';
            });
            document.body.style.cursor = 'progress';
        },

        hideLoading() {
            ['rpt-summary', 'rpt-topcards', 'rpt-kpis'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.style.opacity = '';
            });
            document.querySelectorAll('.rpt-panel').forEach(el => {
                el.style.opacity = '';
            });
            document.body.style.cursor = '';
        },

        showError() {
            // Toast discret en bas-droite — ne masque pas les anciennes données
            let toast = document.getElementById('rpt-live-error');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'rpt-live-error';
                toast.style.cssText = 'position:fixed;bottom:20px;right:20px;background:#dc2626;color:#fff;padding:10px 16px;border-radius:8px;font-size:13px;font-weight:600;z-index:9999;box-shadow:0 6px 20px rgba(0,0,0,.3);display:flex;align-items:center;gap:10px';
                toast.innerHTML = '⚠ Mise à jour échouée. <button type="button" style="background:#fff;color:#dc2626;border:none;border-radius:4px;padding:3px 8px;font-weight:700;cursor:pointer;font-size:11px">Réessayer</button>';
                document.body.appendChild(toast);
                toast.querySelector('button').addEventListener('click', () => {
                    toast.remove();
                    this.reload(true);
                });
            }
            setTimeout(() => { if (toast.parentNode) toast.remove(); }, 8000);
        },
    };

    // Auto-init au DOMContentLoaded — seulement si on est bien sur la page
    // Rapports (présence du form-periode).
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => RapportsLive.init());
    } else {
        RapportsLive.init();
    }

    // Expose pour debug console
    window.RapportsLive = RapportsLive;
})();
