<x-admin-layout>
<x-slot name="title">Taxes Communales</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.rapports.taxes') }}" class="btn btn-ghost btn-sm" title="Rapport agrégé matrice mois × commune">
        📊 Rapport
    </a>
    <a href="{{ route('admin.taxes.historique') }}" class="btn btn-ghost btn-sm" title="Historique des écritures legacy">
        📋 Historique
    </a>
    <a href="{{ route('admin.taxes.export.pdf') }}" class="btn btn-ghost btn-sm">📄 PDF</a>
</x-slot>

{{-- ════ EN-TÊTE PÉRIODE ════ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px 20px;margin-bottom:18px;">
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:6px;">
            <span style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;">📅 Période</span>
        </div>

        {{-- Tabs type période --}}
        <div class="tax-tabs">
            <button type="button" class="tax-tab {{ $periodType === 'mensuel' ? 'active' : '' }}" data-period-type="mensuel">Mensuel</button>
            <button type="button" class="tax-tab {{ $periodType === 'trimestriel' ? 'active' : '' }}" data-period-type="trimestriel">Trimestriel</button>
            <button type="button" class="tax-tab {{ $periodType === 'annuel' ? 'active' : '' }}" data-period-type="annuel">Annuel</button>
        </div>

        {{-- Sélecteur valeur période (mois 1-12 / trimestre 1-4 / vide pour annuel) --}}
        <select id="period-value-select" class="filter-select" style="width:160px;">
            {{-- Rempli en JS selon le type --}}
        </select>

        <select id="period-year-select" class="filter-select" style="width:110px;">
            @foreach($anneesDispos as $a)
                <option value="{{ $a }}" {{ $a == $year ? 'selected' : '' }}>{{ $a }}</option>
            @endforeach
        </select>

        <span id="period-label" style="margin-left:auto;font-size:12px;color:var(--text3);"></span>
    </div>

    <div style="font-size:11px;color:var(--text3);margin-top:8px;line-height:1.6;">
        <strong>Formules :</strong> ODP = tarif commune × surface m² × nb panneaux × nb mois ·
        TM = 1 000 × surface m² × nb panneaux × nb mois (fixe national).
        Calcul en temps réel sur le parc actuel (panneaux internes hors maintenance).
    </div>
</div>

{{-- ════ KPI CARDS ════ --}}
<div id="tax-kpis" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:18px;">
    @php
        $kpiCfg = [
            ['key'=>'odp_total',   'label'=>'ODP théorique', 'color'=>'#3b82f6', 'icon'=>'🏛️'],
            ['key'=>'tm_total',    'label'=>'TM théorique',  'color'=>'#a855f7', 'icon'=>'🏢'],
            ['key'=>'grand_total', 'label'=>'Grand total',   'color'=>'#e8a020', 'icon'=>'💰'],
            ['key'=>'paye_total',  'label'=>'Déjà payé',     'color'=>'#22c55e', 'icon'=>'✅'],
            ['key'=>'solde_total', 'label'=>'Solde restant', 'color'=>'#ef4444', 'icon'=>'⏳'],
        ];
    @endphp
    @foreach($kpiCfg as $k)
    <div class="tax-kpi-card" style="background:var(--surface);border:1px solid var(--border);border-left:4px solid {{ $k['color'] }};border-radius:12px;padding:14px 16px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
            <span style="font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1.2px;">{{ $k['label'] }}</span>
            <span style="font-size:14px;">{{ $k['icon'] }}</span>
        </div>
        <div class="tax-kpi-value" data-kpi="{{ $k['key'] }}" style="font-size:18px;font-weight:800;color:{{ $k['color'] }};font-variant-numeric:tabular-nums;">—</div>
        <div style="font-size:10px;color:var(--text3);margin-top:3px;">FCFA</div>
    </div>
    @endforeach
</div>

{{-- ════ TABLEAU LIVE ════ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;">
    <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
        <span style="font-size:13px;font-weight:700;color:var(--text);">🏛️ Détail par commune</span>
        <div style="display:flex;align-items:center;gap:8px;">
            <input type="text" id="tax-search" class="filter-input"
                   placeholder="Rechercher commune…" style="height:34px;width:200px;font-size:12px;">
            <span id="tax-loading" style="display:none;font-size:11px;color:var(--text3);">⏳ Calcul…</span>
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table class="tax-table" style="width:100%;border-collapse:collapse;font-size:12px;min-width:1100px;">
            <thead>
                <tr>
                    <th style="text-align:left;padding:10px 14px;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border);background:var(--surface2);">Commune</th>
                    <th style="text-align:right;padding:10px 14px;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border);background:var(--surface2);">Panneaux</th>
                    <th style="text-align:right;padding:10px 14px;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border);background:var(--surface2);">Surface (m²)</th>
                    <th style="text-align:right;padding:10px 14px;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border);background:var(--surface2);">Tarif ODP</th>
                    <th style="text-align:right;padding:10px 14px;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border);background:var(--surface2);">ODP</th>
                    <th style="text-align:right;padding:10px 14px;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border);background:var(--surface2);">TM</th>
                    <th style="text-align:right;padding:10px 14px;font-size:9px;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border);background:var(--surface2);">Total</th>
                    <th style="text-align:center;padding:10px 14px;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border);background:var(--surface2);">Statut</th>
                    <th style="text-align:center;padding:10px 14px;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border);background:var(--surface2);">Action</th>
                </tr>
            </thead>
            <tbody id="tax-table-body">
                <tr><td colspan="9" style="text-align:center;padding:60px;color:var(--text3);">Chargement du calcul…</td></tr>
            </tbody>
            <tfoot id="tax-table-foot">
                {{-- Rempli via JS --}}
            </tfoot>
        </table>
    </div>
</div>

{{-- ════ MODAL ENREGISTRER PAIEMENT ════ --}}
<div id="payment-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9000;align-items:center;justify-content:center;padding:16px;"
     onclick="if(event.target===this)TaxModule.closePaymentModal()">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;width:100%;max-width:560px;max-height:92vh;display:flex;flex-direction:column;overflow:hidden;"
         onclick="event.stopPropagation()">
        <div style="padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
            <h3 style="font-size:15px;font-weight:700;">💰 Enregistrer paiement — <span id="pm-commune">—</span></h3>
            <button type="button" onclick="TaxModule.closePaymentModal()" style="background:none;border:none;cursor:pointer;font-size:18px;color:var(--text3);">✕</button>
        </div>
        <div style="padding:18px 22px;overflow-y:auto;flex:1;">
            <div style="background:var(--surface2);border-radius:10px;padding:12px 14px;margin-bottom:14px;font-size:12px;color:var(--text2);">
                <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px;">Période</div>
                <div id="pm-period-label" style="font-weight:600;">—</div>
                <div style="display:flex;gap:14px;margin-top:8px;flex-wrap:wrap;">
                    <div>
                        <span style="font-size:9px;color:var(--text3);text-transform:uppercase;letter-spacing:.7px;display:block;">ODP théorique</span>
                        <strong id="pm-odp-theorique" style="color:#3b82f6;">—</strong>
                    </div>
                    <div>
                        <span style="font-size:9px;color:var(--text3);text-transform:uppercase;letter-spacing:.7px;display:block;">TM théorique</span>
                        <strong id="pm-tm-theorique" style="color:#a855f7;">—</strong>
                    </div>
                    <div>
                        <span style="font-size:9px;color:var(--text3);text-transform:uppercase;letter-spacing:.7px;display:block;">Total</span>
                        <strong id="pm-total-theorique" style="color:var(--accent);">—</strong>
                    </div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label class="filter-label">ODP payé (FCFA)</label>
                    <input type="number" id="pm-odp-paye" min="0" step="1" class="filter-input" style="width:100%;">
                </div>
                <div>
                    <label class="filter-label">TM payé (FCFA)</label>
                    <input type="number" id="pm-tm-paye" min="0" step="1" class="filter-input" style="width:100%;">
                </div>
                <div>
                    <label class="filter-label">Date paiement</label>
                    <input type="date" id="pm-paid-at" class="filter-input" style="width:100%;" value="{{ now()->format('Y-m-d') }}">
                </div>
                <div style="display:flex;align-items:center;gap:8px;padding-top:18px;">
                    <input type="checkbox" id="pm-attestation" style="width:16px;height:16px;accent-color:var(--accent);cursor:pointer;">
                    <label for="pm-attestation" style="font-size:12px;color:var(--text2);cursor:pointer;">Attestation reçue</label>
                </div>
                <div style="grid-column:1/3;">
                    <label class="filter-label">Notes (optionnel)</label>
                    <textarea id="pm-notes" rows="2" class="filter-input" style="width:100%;resize:vertical;font-family:inherit;" maxlength="1000" placeholder="N° quittance, observations…"></textarea>
                </div>
            </div>

            <div style="margin-top:12px;padding:8px 12px;background:rgba(232,160,32,.08);border:1px solid rgba(232,160,32,.2);border-radius:8px;font-size:11px;color:var(--text2);">
                💡 Astuce : laissez 0 si seul un type de taxe est payé. Vous pouvez compléter plus tard.
            </div>
        </div>
        <div style="padding:14px 22px;border-top:1px solid var(--border);background:var(--surface2);display:flex;gap:8px;justify-content:flex-end;">
            <button type="button" onclick="TaxModule.closePaymentModal()" class="btn btn-ghost btn-sm">Annuler</button>
            <button type="button" id="pm-submit-btn" onclick="TaxModule.submitPayment()" class="btn btn-primary btn-sm">💾 Enregistrer</button>
        </div>
    </div>
</div>

<style>
.tax-tabs { display: flex; gap: 4px; background: var(--surface2); border-radius: 9px; padding: 4px; }
.tax-tab {
    padding: 8px 14px; border: none; background: transparent;
    color: var(--text3); font-weight: 600; font-size: 12px;
    border-radius: 7px; cursor: pointer; transition: all .15s;
}
.tax-tab:hover { background: var(--surface); color: var(--text); }
.tax-tab.active { background: var(--accent); color: #0a0c10; }

.tax-table tbody tr:hover td { background: var(--surface2); }
.tax-table tbody td { padding: 10px 14px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.tax-table tfoot td { padding: 12px 14px; background: #0f172a; color: #e8a020; font-weight: 700; font-size: 12px; }

.tax-status-pill {
    display: inline-block; padding: 3px 10px; border-radius: 12px;
    font-size: 10px; font-weight: 700; letter-spacing: .3px;
}
.tax-status-non_paye { background: rgba(239,68,68,.12); color: #b91c1c; }
.tax-status-partiel  { background: rgba(232,160,32,.12); color: #c2570d; }
.tax-status-paye     { background: rgba(34,197,94,.12); color: #15803d; }
.tax-status-aucun    { background: var(--surface2); color: var(--text3); }

.tax-attestation-badge {
    display: inline-block; padding: 1px 7px;
    background: rgba(59,130,246,.12); color: #1d4ed8;
    border-radius: 9px; font-size: 9px; font-weight: 700;
    margin-left: 4px;
}

.tax-pay-btn {
    padding: 5px 10px;
    background: rgba(34,197,94,.1); color: #15803d;
    border: 1px solid rgba(34,197,94,.25);
    border-radius: 7px; font-size: 11px; font-weight: 600;
    cursor: pointer;
}
.tax-pay-btn:hover { background: rgba(34,197,94,.18); }
.tax-pay-btn-edit {
    background: rgba(59,130,246,.1); color: #1d4ed8;
    border-color: rgba(59,130,246,.25);
}
.tax-pay-btn-edit:hover { background: rgba(59,130,246,.18); }
</style>

@push('scripts')
<script>
window.TaxModule = (function () {
    const csrf = '{{ csrf_token() }}';
    let currentPeriodType  = '{{ $periodType }}';
    let currentPeriodYear  = {{ $year }};
    let currentPeriodValue = {{ $periodValue }};
    let currentData = null;
    let pmContext = null; // contexte de la modale paiement

    const fmt = n => new Intl.NumberFormat('fr-FR').format(Math.round(n || 0));

    const monthNames = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    const quarterNames = ['Q1 (Jan-Mars)','Q2 (Avr-Juin)','Q3 (Juil-Sept)','Q4 (Oct-Déc)'];

    function rebuildPeriodValueOptions() {
        const sel = document.getElementById('period-value-select');
        sel.innerHTML = '';
        if (currentPeriodType === 'mensuel') {
            sel.style.display = '';
            for (let i = 1; i <= 12; i++) {
                const o = document.createElement('option');
                o.value = i;
                o.textContent = monthNames[i - 1];
                if (i === currentPeriodValue) o.selected = true;
                sel.appendChild(o);
            }
        } else if (currentPeriodType === 'trimestriel') {
            sel.style.display = '';
            for (let i = 1; i <= 4; i++) {
                const o = document.createElement('option');
                o.value = i;
                o.textContent = quarterNames[i - 1];
                if (i === Math.min(currentPeriodValue, 4)) o.selected = true;
                sel.appendChild(o);
            }
            // si l'ancienne valeur > 4 (mois), reset à 1
            if (currentPeriodValue > 4) currentPeriodValue = Math.ceil(currentPeriodValue / 3);
        } else {
            sel.style.display = 'none';
            currentPeriodValue = 0;
        }
    }

    function buildPeriodLabel() {
        if (currentPeriodType === 'mensuel') {
            return `${monthNames[currentPeriodValue - 1]} ${currentPeriodYear}`;
        }
        if (currentPeriodType === 'trimestriel') {
            return `${quarterNames[currentPeriodValue - 1]} ${currentPeriodYear}`;
        }
        return `Année ${currentPeriodYear}`;
    }

    async function loadCalcul() {
        document.getElementById('tax-loading').style.display = '';
        const params = new URLSearchParams({
            period_type:  currentPeriodType,
            period_year:  String(currentPeriodYear),
            period_value: String(currentPeriodValue),
        });

        try {
            const r = await fetch(`{{ route('admin.taxes.calcul') }}?${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            });
            const data = await r.json();
            currentData = data;
            renderKpis(data.kpi);
            renderTable(data.communes);
            document.getElementById('period-label').textContent =
                `${buildPeriodLabel()} · ${data.kpi.communes_actives} commune(s) · ${data.kpi.panneaux_total} panneau(x)`;
        } catch (e) {
            console.error(e);
            document.getElementById('tax-table-body').innerHTML =
                '<tr><td colspan="9" style="text-align:center;padding:40px;color:#ef4444;">Erreur de chargement.</td></tr>';
        } finally {
            document.getElementById('tax-loading').style.display = 'none';
        }
    }

    function renderKpis(kpi) {
        document.querySelectorAll('.tax-kpi-value').forEach(el => {
            const key = el.dataset.kpi;
            el.textContent = fmt(kpi[key] || 0);
        });
    }

    function statusPill(statut) {
        const labels = {
            paye:     '✓ Payé',
            partiel:  '◐ Partiel',
            non_paye: '⏳ Non payé',
            aucun:    '— Aucun',
        };
        return `<span class="tax-status-pill tax-status-${statut}">${labels[statut] || statut}</span>`;
    }

    function renderTable(communes) {
        const tbody = document.getElementById('tax-table-body');
        if (!communes || !communes.length) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text3);">Aucune commune avec des panneaux installés.</td></tr>';
            document.getElementById('tax-table-foot').innerHTML = '';
            return;
        }

        const rowsHtml = communes.map(c => {
            const attestationBadge = c.attestation
                ? '<span class="tax-attestation-badge">📜 Attestation</span>' : '';
            const surface = c.surface_totale > 0 ? c.surface_totale.toFixed(2) : '0';
            const lignesTooltip = (c.lignes || [])
                .map(l => `${l.format} (${l.dimensions}) ×${l.qty}`)
                .join(' · ') || '—';

            return `
                <tr data-commune-id="${c.commune_id}" data-search="${(c.commune || '').toLowerCase()}">
                    <td>
                        <strong>${c.commune}</strong>
                        <div style="font-size:10px;color:var(--text3);margin-top:2px;" title="${lignesTooltip}">
                            ${(c.lignes || []).length} format(s) · ${lignesTooltip.length > 60 ? lignesTooltip.substr(0,60) + '…' : lignesTooltip}
                        </div>
                    </td>
                    <td style="text-align:right;font-variant-numeric:tabular-nums;">${c.nb_panneaux}</td>
                    <td style="text-align:right;font-variant-numeric:tabular-nums;color:var(--text3);">${surface}</td>
                    <td style="text-align:right;font-variant-numeric:tabular-nums;color:var(--text3);">${fmt(c.odp_taux)}/m²</td>
                    <td style="text-align:right;font-variant-numeric:tabular-nums;color:#3b82f6;font-weight:600;">${fmt(c.odp_theorique)}</td>
                    <td style="text-align:right;font-variant-numeric:tabular-nums;color:#a855f7;font-weight:600;">${fmt(c.tm_theorique)}</td>
                    <td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:800;color:var(--accent);">${fmt(c.total_theorique)}</td>
                    <td style="text-align:center;">
                        ${statusPill(c.statut)}
                        ${attestationBadge}
                        ${c.paid_at ? `<div style="font-size:9px;color:var(--text3);margin-top:3px;">${c.paid_at}</div>` : ''}
                        ${c.total_paye > 0 && c.statut !== 'paye'
                            ? `<div style="font-size:9px;color:var(--text3);margin-top:2px;">payé : ${fmt(c.total_paye)}</div>`
                            : ''}
                    </td>
                    <td style="text-align:center;">
                        <button type="button"
                                class="tax-pay-btn ${c.payment_id ? 'tax-pay-btn-edit' : ''}"
                                onclick="TaxModule.openPaymentModal(${c.commune_id})">
                            ${c.payment_id ? '✏️ Modifier' : '💰 Payer'}
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        tbody.innerHTML = rowsHtml;

        // Footer totaux
        const k = currentData.kpi;
        document.getElementById('tax-table-foot').innerHTML = `
            <tr>
                <td colspan="4" style="text-align:right;">TOTAUX</td>
                <td style="text-align:right;font-variant-numeric:tabular-nums;">${fmt(k.odp_total)}</td>
                <td style="text-align:right;font-variant-numeric:tabular-nums;">${fmt(k.tm_total)}</td>
                <td style="text-align:right;font-variant-numeric:tabular-nums;background:rgba(232,160,32,.15);">${fmt(k.grand_total)}</td>
                <td colspan="2" style="text-align:right;color:#22c55e;">Payé : ${fmt(k.paye_total)} · Solde : ${fmt(k.solde_total)}</td>
            </tr>
        `;

        applySearchFilter();
    }

    function applySearchFilter() {
        const term = (document.getElementById('tax-search').value || '').trim().toLowerCase();
        document.querySelectorAll('#tax-table-body tr[data-commune-id]').forEach(tr => {
            const visible = !term || tr.dataset.search.includes(term);
            tr.style.display = visible ? '' : 'none';
        });
    }

    return {
        init() {
            rebuildPeriodValueOptions();

            // Listeners
            document.querySelectorAll('.tax-tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    document.querySelectorAll('.tax-tab').forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    currentPeriodType = tab.dataset.periodType;
                    rebuildPeriodValueOptions();
                    loadCalcul();
                });
            });
            document.getElementById('period-value-select').addEventListener('change', e => {
                currentPeriodValue = parseInt(e.target.value, 10) || 0;
                loadCalcul();
            });
            document.getElementById('period-year-select').addEventListener('change', e => {
                currentPeriodYear = parseInt(e.target.value, 10);
                loadCalcul();
            });
            document.getElementById('tax-search').addEventListener('input', applySearchFilter);

            // Premier chargement
            loadCalcul();
        },

        openPaymentModal(communeId) {
            const row = (currentData?.communes || []).find(c => c.commune_id === communeId);
            if (!row) return;
            pmContext = row;

            document.getElementById('pm-commune').textContent = row.commune;
            document.getElementById('pm-period-label').textContent = buildPeriodLabel();
            document.getElementById('pm-odp-theorique').textContent = fmt(row.odp_theorique) + ' FCFA';
            document.getElementById('pm-tm-theorique').textContent = fmt(row.tm_theorique) + ' FCFA';
            document.getElementById('pm-total-theorique').textContent = fmt(row.total_theorique) + ' FCFA';

            document.getElementById('pm-odp-paye').value = row.odp_paye || row.odp_theorique;
            document.getElementById('pm-tm-paye').value  = row.tm_paye  || row.tm_theorique;
            document.getElementById('pm-attestation').checked = !!row.attestation;
            document.getElementById('pm-notes').value = '';
            // paid_at : si déjà payé, restaurer la date saisie ; sinon today
            // (on n'a pas la date back en JSON pour l'instant — date du jour OK).
            document.getElementById('pm-paid-at').value = '{{ now()->format("Y-m-d") }}';

            document.getElementById('payment-modal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        },

        closePaymentModal() {
            document.getElementById('payment-modal').style.display = 'none';
            document.body.style.overflow = '';
            pmContext = null;
        },

        async submitPayment() {
            if (!pmContext) return;
            const btn = document.getElementById('pm-submit-btn');
            btn.disabled = true;
            btn.textContent = 'Enregistrement…';

            const fd = new URLSearchParams({
                _token:           csrf,
                commune_id:       String(pmContext.commune_id),
                period_type:      currentPeriodType,
                period_year:      String(currentPeriodYear),
                period_value:     String(currentPeriodValue),
                odp_paye:         document.getElementById('pm-odp-paye').value || '0',
                tm_paye:          document.getElementById('pm-tm-paye').value  || '0',
                odp_theorique:    String(pmContext.odp_theorique),
                tm_theorique:     String(pmContext.tm_theorique),
                paid_at:          document.getElementById('pm-paid-at').value || '',
                attestation_recue: document.getElementById('pm-attestation').checked ? '1' : '0',
                notes:            document.getElementById('pm-notes').value || '',
            });

            try {
                const r = await fetch(`{{ route('admin.taxes.payments.record') }}`, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: fd,
                });
                if (r.status === 422) {
                    const data = await r.json().catch(() => ({}));
                    const first = data.errors ? Object.values(data.errors).flat()[0] : (data.message || 'Données invalides.');
                    alert(first);
                    return;
                }
                const data = await r.json();
                if (!r.ok || !data.ok) {
                    alert(data.message || 'Erreur.');
                    return;
                }
                this.closePaymentModal();
                await loadCalcul(); // refresh KPIs + table
            } catch (e) {
                console.error(e);
                alert('Erreur réseau.');
            } finally {
                btn.disabled = false;
                btn.textContent = '💾 Enregistrer';
            }
        },
    };
})();

document.addEventListener('DOMContentLoaded', () => TaxModule.init());
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && document.getElementById('payment-modal')?.style.display === 'flex') {
        TaxModule.closePaymentModal();
    }
});
</script>
@endpush

</x-admin-layout>
