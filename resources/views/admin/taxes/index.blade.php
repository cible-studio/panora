<x-admin-layout>
<x-slot name="title">Taxes Communales</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.taxes.details', ['year' => $year, 'period_type' => $periodType, 'period_value' => $periodValue]) }}"
       class="btn btn-primary btn-sm" title="Détail par panneau (justification commune par commune)">
        🔍 Détail par panneau
    </a>
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
            {{-- FIX 2026-06-22 — Période personnalisée (mois début → mois fin) --}}
            <button type="button" class="tax-tab {{ $periodType === 'personnalise' ? 'active' : '' }}" data-period-type="personnalise">Personnalisée</button>
        </div>

        {{-- Sélecteur valeur période (mois 1-12 / trimestre 1-4 / vide pour annuel) --}}
        <select id="period-value-select" class="filter-select" style="width:160px;">
            {{-- Rempli en JS selon le type --}}
        </select>
        {{-- FIX 2026-06-22 — Sélecteur "mois fin" affiché uniquement en mode personnalisé --}}
        <span id="period-end-arrow" style="display:none;font-size:12px;color:var(--text3);">→</span>
        <select id="period-end-select" class="filter-select" style="width:160px;display:none;">
            {{-- Rempli en JS quand mode = personnalise --}}
        </select>

        <select id="period-year-select" class="filter-select" style="width:110px;">
            @foreach($anneesDispos as $a)
                <option value="{{ $a }}" {{ $a == $year ? 'selected' : '' }}>{{ $a }}</option>
            @endforeach
        </select>

        <span id="period-label" style="margin-left:auto;font-size:12px;color:var(--text3);"></span>
    </div>

    <div style="font-size:11px;color:var(--text3);margin-top:8px;line-height:1.6;">
        <strong>Formules :</strong>
        ODP = tarif commune × m² × nb panneaux × nb mois ·
        TM = tarif commune × m² × nb panneaux × nb mois.
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
    <div class="tax-kpi-card"
         data-kpi-filter="{{ $k['key'] }}"
         style="background:var(--surface);border:1px solid var(--border);border-left:4px solid {{ $k['color'] }};border-radius:12px;padding:14px 16px;cursor:pointer;transition:box-shadow .15s,transform .1s;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
            <span style="font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1.2px;">{{ $k['label'] }}</span>
            <span style="font-size:14px;">{{ $k['icon'] }}</span>
        </div>
        <div class="tax-kpi-value" data-kpi="{{ $k['key'] }}" style="font-size:18px;font-weight:800;color:{{ $k['color'] }};font-variant-numeric:tabular-nums;">—</div>
        <div class="kpi-hint" style="font-size:10px;color:var(--text3);margin-top:3px;">FCFA · cliquer pour filtrer</div>
    </div>
    @endforeach
    {{-- LOT 4 (cahier 2026-06-19) — KPI Taux de couverture (% payé / dû). --}}
    <div class="tax-kpi-card" style="background:var(--surface);border:1px solid var(--border);border-left:4px solid #6366f1;border-radius:12px;padding:14px 16px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
            <span style="font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1.2px">Taux couverture</span>
            <span style="font-size:14px">📊</span>
        </div>
        <div data-kpi="taux_couverture" style="font-size:18px;font-weight:800;color:#6366f1;font-variant-numeric:tabular-nums">—</div>
        <div style="font-size:10px;color:var(--text3);margin-top:3px">% payé / dû sur la période</div>
    </div>
    {{-- FIX 2026-06-22 — Compteur communes SOLDÉES (cliquable pour filtrer).
         La classe tax-kpi-value est requise pour que renderKpis() remplisse la valeur. --}}
    <div class="tax-kpi-card" data-kpi-filter="communes_soldees"
         style="background:var(--surface);border:1px solid var(--border);border-left:4px solid #22c55e;border-radius:12px;padding:14px 16px;cursor:pointer;transition:box-shadow .15s,transform .1s;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
            <span style="font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1.2px">✓ Soldé</span>
            <span style="font-size:14px">🟢</span>
        </div>
        <div class="tax-kpi-value" data-kpi="communes_soldees" style="font-size:18px;font-weight:800;color:#15803d;font-variant-numeric:tabular-nums">—</div>
        <div style="font-size:10px;color:var(--text3);margin-top:3px">commune(s) entièrement payée(s)</div>
    </div>
    {{-- FIX 2026-06-22 — Compteur communes PARTIELLES (cliquable pour filtrer). --}}
    <div class="tax-kpi-card" data-kpi-filter="communes_partielles"
         style="background:var(--surface);border:1px solid var(--border);border-left:4px solid #f59e0b;border-radius:12px;padding:14px 16px;cursor:pointer;transition:box-shadow .15s,transform .1s;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
            <span style="font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1.2px">◐ Partiel</span>
            <span style="font-size:14px">🟠</span>
        </div>
        <div class="tax-kpi-value" data-kpi="communes_partielles" style="font-size:18px;font-weight:800;color:#b45309;font-variant-numeric:tabular-nums">—</div>
        <div style="font-size:10px;color:var(--text3);margin-top:3px">commune(s) payée(s) en partie</div>
    </div>
</div>

{{-- ════ LOT 4 — KPIs supplémentaires (Abidjan/Intérieur + Top communes) ════ --}}
<div style="display:grid;grid-template-columns:1fr 1.5fr;gap:12px;margin-bottom:18px" class="tax-extras-grid">
    {{-- Répartition Abidjan / Intérieur --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px 18px">
        <div style="font-size:12px;font-weight:800;color:var(--text);margin-bottom:10px;display:flex;align-items:center;gap:6px">
            🌍 Répartition géographique
        </div>
        <div style="display:flex;flex-direction:column;gap:8px">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.20);border-radius:8px">
                <div>
                    <div style="font-size:11px;font-weight:700;color:#1d4ed8;text-transform:uppercase;letter-spacing:.4px">Abidjan</div>
                    <div data-kpi-extra="abidjan_count" style="font-size:10.5px;color:var(--text3);margin-top:2px">— communes · — panneaux</div>
                </div>
                <div style="text-align:right">
                    <div data-kpi-extra="abidjan_du" style="font-size:13px;font-weight:800;color:#1d4ed8;font-variant-numeric:tabular-nums">—</div>
                    <div data-kpi-extra="abidjan_paye" style="font-size:10px;color:#15803d;margin-top:1px">payé : —</div>
                </div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.20);border-radius:8px">
                <div>
                    <div style="font-size:11px;font-weight:700;color:#15803d;text-transform:uppercase;letter-spacing:.4px">Intérieur</div>
                    <div data-kpi-extra="interieur_count" style="font-size:10.5px;color:var(--text3);margin-top:2px">— communes · — panneaux</div>
                </div>
                <div style="text-align:right">
                    <div data-kpi-extra="interieur_du" style="font-size:13px;font-weight:800;color:#15803d;font-variant-numeric:tabular-nums">—</div>
                    <div data-kpi-extra="interieur_paye" style="font-size:10px;color:#15803d;margin-top:1px">payé : —</div>
                </div>
            </div>
        </div>
    </div>
    {{-- Top 5 communes par montant dû --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px 18px">
        <div style="font-size:12px;font-weight:800;color:var(--text);margin-bottom:10px;display:flex;align-items:center;gap:6px">
            🏆 Top 5 communes les plus taxées (période)
        </div>
        <div id="top-communes-list" style="display:flex;flex-direction:column;gap:5px">
            <div style="text-align:center;color:var(--text3);font-size:11px;font-style:italic;padding:10px">Chargement…</div>
        </div>
    </div>
</div>

{{-- LOT 4 (cahier 2026-06-19) — Évolution mensuelle des paiements de taxes
     sur l'année courante. Source : CommuneTaxPayment.paid_at agrégé par mois. --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 18px;margin-bottom:18px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;gap:10px;flex-wrap:wrap">
        <div>
            <div style="font-size:12px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:6px">
                📈 Évolution mensuelle des paiements
            </div>
            <div style="font-size:10.5px;color:var(--text3);margin-top:2px">Versements taxes communales — année en cours</div>
        </div>
        <div id="payments-evol-total" style="font-size:11px;color:var(--text3);font-weight:600">—</div>
    </div>
    <div style="position:relative;height:160px">
        <canvas id="chart-payments-evolution" role="img" aria-label="Évolution mensuelle des paiements de taxes"></canvas>
    </div>
</div>

{{-- ════ TABLEAU LIVE ════ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;">
    <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
        <span style="font-size:13px;font-weight:700;color:var(--text);">🏛️ Détail par commune</span>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            {{-- 2026-06-19 — Filtre Zone (Abidjan / Intérieur). Le filtrage
                 est côté JS car les données de toutes les communes sont déjà
                 chargées dans currentData après loadCalcul(). --}}
            <select id="tax-filter-zone" class="filter-input" style="height:34px;font-size:12px;font-weight:600;min-width:130px"
                    title="Filtrer par zone géographique">
                <option value="">Toutes zones</option>
                <option value="abidjan">Abidjan</option>
                <option value="interieur">Intérieur</option>
            </select>
            {{-- Filtre Commune spécifique — restreint le tableau à une seule
                 commune (utile pour préparer un paiement ou justifier un montant). --}}
            <select id="tax-filter-commune" class="filter-input" style="height:34px;font-size:12px;min-width:160px"
                    title="Filtrer sur une commune précise">
                <option value="">Toutes communes</option>
                @foreach($communes as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
            <input type="text" id="tax-search" class="filter-input"
                   placeholder="Rechercher commune…" style="height:34px;width:180px;font-size:12px;">
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
                    <th style="text-align:right;padding:10px 14px;font-size:9px;font-weight:700;color:#3b82f6;text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border);background:var(--surface2);">ODP</th>
                    <th style="text-align:right;padding:10px 14px;font-size:9px;font-weight:700;color:#a855f7;text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border);background:var(--surface2);">TM</th>
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
                {{-- LOT 1 — Modes de paiement (cahier 2026-06-19). Le mode est
                     fortement recommandé pour la traçabilité comptable (export
                     vers le cabinet). Référence = N° chèque / bordereau /
                     transaction Mobile Money. --}}
                <div>
                    <label class="filter-label">Mode de paiement <span style="color:var(--accent)">*</span></label>
                    <select id="pm-mode" class="filter-input" style="width:100%;height:38px;font-weight:600">
                        <option value="">— Choisir un mode —</option>
                        @foreach(\App\Models\CommuneTaxPayment::MODES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="filter-label">Référence</label>
                    <input type="text" id="pm-reference" class="filter-input" maxlength="100"
                           style="width:100%;" placeholder="N° chèque / bordereau / transaction">
                </div>
                <div style="grid-column:1/3;">
                    <label class="filter-label">Commentaire (banque, motif, observation)</label>
                    <textarea id="pm-comment" rows="2" class="filter-input" style="width:100%;resize:vertical;font-family:inherit;" maxlength="2000" placeholder="Ex : Chèque BICICI déposé le 18/06, à l'attention de la mairie de Cocody…"></textarea>
                </div>
                <div style="grid-column:1/3;">
                    <label class="filter-label">Notes internes (optionnel)</label>
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
.tax-kpi-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.1); transform: translateY(-1px); }
.tax-kpi-card.kpi-active { box-shadow: 0 0 0 2px currentColor, 0 4px 14px rgba(0,0,0,.12); transform: translateY(-1px); }
.tax-kpi-card.kpi-active .kpi-hint { font-weight: 700; }

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
{{-- LOT 4 (cahier 2026-06-19) — Chart.js pour le mini-chart d'évolution
     mensuelle des paiements. Doit être chargé AVANT le script TaxModule
     qui l'utilise (sinon typeof Chart === 'undefined' au 1er rendu). --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
window.TaxModule = (function () {
    const csrf = '{{ csrf_token() }}';
    let currentPeriodType  = '{{ $periodType }}';
    let currentPeriodYear  = {{ $year }};
    let currentPeriodValue = {{ $periodValue }};
    // FIX 2026-06-22 — Mode personnalisé : mois fin (mois début = currentPeriodValue).
    let currentPeriodEndValue = (new Date()).getMonth() + 1; // défaut = mois courant
    let currentData = null;
    let pmContext = null;       // contexte de la modale paiement
    let activeKpiFilter = null; // filtre KPI actif (null = tous)

    const fmt = n => new Intl.NumberFormat('fr-FR').format(Math.round(n || 0));

    const monthNames = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    const quarterNames = ['Q1 (Jan-Mars)','Q2 (Avr-Juin)','Q3 (Juil-Sept)','Q4 (Oct-Déc)'];

    function rebuildPeriodValueOptions() {
        const sel = document.getElementById('period-value-select');
        sel.innerHTML = '';
        if (currentPeriodType === 'mensuel') {
            sel.style.display = '';
            // Hotfix TX-3 (2026-06-22) : si on vient d'annuel (=0), forcer
            // à 1 pour ne pas envoyer period_value=0 en mensuel (qui
            // produisait monthNames[-1] = undefined → "undefined 2026").
            if (currentPeriodValue < 1 || currentPeriodValue > 12) {
                currentPeriodValue = (new Date()).getMonth() + 1;
            }
            for (let i = 1; i <= 12; i++) {
                const o = document.createElement('option');
                o.value = i;
                o.textContent = monthNames[i - 1];
                if (i === currentPeriodValue) o.selected = true;
                sel.appendChild(o);
            }
        } else if (currentPeriodType === 'trimestriel') {
            sel.style.display = '';
            // Hotfix TX-3 : si valeur hors [1..4] (vient d'annuel=0 ou
            // d'un mensuel > 4), on convertit/borne avant la boucle pour
            // ne JAMAIS rendre une option sans selected.
            if (currentPeriodValue < 1) {
                currentPeriodValue = 1;
            } else if (currentPeriodValue > 4) {
                currentPeriodValue = Math.min(4, Math.ceil(currentPeriodValue / 3));
            }
            for (let i = 1; i <= 4; i++) {
                const o = document.createElement('option');
                o.value = i;
                o.textContent = quarterNames[i - 1];
                if (i === currentPeriodValue) o.selected = true;
                sel.appendChild(o);
            }
        } else if (currentPeriodType === 'personnalise') {
            // FIX 2026-06-22 — Mode personnalisé : 2 sélecteurs mois (début → fin).
            sel.style.display = '';
            if (currentPeriodValue < 1 || currentPeriodValue > 12) {
                currentPeriodValue = 1;
            }
            for (let i = 1; i <= 12; i++) {
                const o = document.createElement('option');
                o.value = i;
                o.textContent = monthNames[i - 1];
                if (i === currentPeriodValue) o.selected = true;
                sel.appendChild(o);
            }
            // Sélecteur "mois fin"
            const endSel = document.getElementById('period-end-select');
            endSel.innerHTML = '';
            if (currentPeriodEndValue < currentPeriodValue || currentPeriodEndValue > 12) {
                currentPeriodEndValue = currentPeriodValue;
            }
            for (let i = 1; i <= 12; i++) {
                const o = document.createElement('option');
                o.value = i;
                o.textContent = monthNames[i - 1];
                // Pas de mois fin antérieur au mois début
                if (i < currentPeriodValue) o.disabled = true;
                if (i === currentPeriodEndValue) o.selected = true;
                endSel.appendChild(o);
            }
            endSel.style.display = '';
            document.getElementById('period-end-arrow').style.display = '';
        } else {
            sel.style.display = 'none';
            currentPeriodValue = 0;
        }
        // Cache le sélecteur "mois fin" hors mode personnalisé.
        if (currentPeriodType !== 'personnalise') {
            document.getElementById('period-end-select').style.display = 'none';
            document.getElementById('period-end-arrow').style.display = 'none';
        }
    }

    function buildPeriodLabel() {
        // Hotfix TX-3 (2026-06-22) : garde-fou contre les valeurs hors
        // bornes pour ne JAMAIS afficher "undefined 2026" dans le sous-titre.
        if (currentPeriodType === 'mensuel') {
            const idx = (currentPeriodValue >= 1 && currentPeriodValue <= 12) ? currentPeriodValue - 1 : 0;
            return `${monthNames[idx]} ${currentPeriodYear}`;
        }
        if (currentPeriodType === 'trimestriel') {
            const idx = (currentPeriodValue >= 1 && currentPeriodValue <= 4) ? currentPeriodValue - 1 : 0;
            return `${quarterNames[idx]} ${currentPeriodYear}`;
        }
        if (currentPeriodType === 'personnalise') {
            const startIdx = (currentPeriodValue    >= 1 && currentPeriodValue    <= 12) ? currentPeriodValue    - 1 : 0;
            const endIdx   = (currentPeriodEndValue >= 1 && currentPeriodEndValue <= 12) ? currentPeriodEndValue - 1 : 0;
            return `${monthNames[startIdx]} → ${monthNames[endIdx]} ${currentPeriodYear}`;
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
        // FIX 2026-06-22 — period_end_value envoyé uniquement en mode personnalisé.
        if (currentPeriodType === 'personnalise') {
            params.set('period_end_value', String(currentPeriodEndValue));
        }

        try {
            const r = await fetch(`{{ route('admin.taxes.calcul') }}?${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin',
            });

            // ── Session expirée / non authentifié ─────────────────────
            // Laravel renvoie 401 quand le cookie session est mort. On
            // affiche un message clair invitant à rafraîchir, plutôt que
            // de planter sur data.kpi indéfini.
            if (r.status === 401 || r.status === 419) {
                document.getElementById('tax-table-body').innerHTML =
                    '<tr><td colspan="9" style="text-align:center;padding:40px;color:#ef4444;">⏱️ Session expirée — <a href="" onclick="event.preventDefault();window.location.reload()" style="color:#3b82f6;text-decoration:underline">rafraîchir la page</a> et se reconnecter.</td></tr>';
                return;
            }

            // Autres erreurs HTTP : message générique sans crash JS.
            if (!r.ok) {
                document.getElementById('tax-table-body').innerHTML =
                    `<tr><td colspan="9" style="text-align:center;padding:40px;color:#ef4444;">Erreur serveur (HTTP ${r.status}). Réessaie ou contacte un admin.</td></tr>`;
                return;
            }

            const data = await r.json().catch(() => null);
            if (!data || !data.kpi) {
                document.getElementById('tax-table-body').innerHTML =
                    '<tr><td colspan="9" style="text-align:center;padding:40px;color:#ef4444;">Réponse serveur invalide. Réessaie.</td></tr>';
                return;
            }

            currentData = data;
            renderKpis(data.kpi);
            renderTable(getFilteredSortedCommunes());
            document.getElementById('period-label').textContent =
                `${buildPeriodLabel()} · ${data.kpi.communes_actives ?? 0} commune(s) · ${data.kpi.panneaux_total ?? 0} panneau(x)`;
        } catch (e) {
            console.error(e);
            document.getElementById('tax-table-body').innerHTML =
                '<tr><td colspan="9" style="text-align:center;padding:40px;color:#ef4444;">Erreur de chargement (réseau).</td></tr>';
        } finally {
            document.getElementById('tax-loading').style.display = 'none';
        }
    }

    function renderKpis(kpi) {
        if (!kpi) return;
        document.querySelectorAll('.tax-kpi-value').forEach(el => {
            const key = el.dataset.kpi;
            el.textContent = fmt(kpi[key] || 0);
        });
        // LOT 4 — Taux de couverture (% payé / dû).
        const cov = document.querySelector('[data-kpi="taux_couverture"]');
        if (cov) cov.textContent = (kpi.taux_couverture ?? 0) + ' %';

        // LOT 4 — Répartition Abidjan / Intérieur.
        const setExtra = (id, val) => {
            const el = document.querySelector(`[data-kpi-extra="${id}"]`);
            if (el) el.textContent = val;
        };
        const ab = kpi.breakdown?.abidjan   || {};
        const it = kpi.breakdown?.interieur || {};
        setExtra('abidjan_count',   `${ab.communes ?? 0} communes · ${ab.panneaux ?? 0} panneaux`);
        setExtra('abidjan_du',      fmt(ab.total_du ?? 0) + ' FCFA');
        setExtra('abidjan_paye',    'payé : ' + fmt(ab.total_paye ?? 0));
        setExtra('interieur_count', `${it.communes ?? 0} communes · ${it.panneaux ?? 0} panneaux`);
        setExtra('interieur_du',    fmt(it.total_du ?? 0) + ' FCFA');
        setExtra('interieur_paye',  'payé : ' + fmt(it.total_paye ?? 0));

        // LOT 4 — Top 5 communes (cliquable vers la fiche).
        const topEl = document.getElementById('top-communes-list');
        if (topEl) {
            const top = kpi.top_communes || [];
            if (top.length === 0) {
                topEl.innerHTML = '<div style="text-align:center;color:var(--text3);font-size:11px;font-style:italic;padding:10px">Aucune commune sur la période.</div>';
            } else {
                topEl.innerHTML = top.map((c, i) => {
                    const medal = i === 0 ? '🥇' : (i === 1 ? '🥈' : (i === 2 ? '🥉' : (i + 1) + '.'));
                    const pctPaye = c.total_theorique > 0 ? Math.round((c.total_paye / c.total_theorique) * 100) : 0;
                    return `
                        <a href="/admin/taxes/commune/${c.commune_id}"
                           style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:var(--surface2);border-radius:8px;text-decoration:none;color:inherit;transition:transform .15s"
                           onmouseenter="this.style.transform='translateX(2px)'"
                           onmouseleave="this.style.transform=''"
                           title="Ouvrir la fiche détaillée de ${c.commune}">
                            <span style="font-size:12px;width:22px;flex-shrink:0;text-align:center">${medal}</span>
                            <span style="flex:1;font-size:12px;font-weight:700;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${c.commune}</span>
                            <span style="font-size:11.5px;font-weight:700;color:var(--accent);font-variant-numeric:tabular-nums;text-align:right">${fmt(c.total_theorique)}</span>
                            <span style="font-size:9.5px;color:${pctPaye >= 80 ? '#15803d' : (pctPaye >= 30 ? '#b45309' : '#b91c1c')};font-weight:700;width:32px;text-align:right">${pctPaye}%</span>
                        </a>
                    `;
                }).join('');
            }
        }

        // LOT 4 — Évolution mensuelle des paiements (mini chart 12 mois).
        renderPaymentsEvolution(kpi.payments_evolution || []);
    }

    let paymentsEvolutionChart = null;
    function renderPaymentsEvolution(series) {
        const canvas = document.getElementById('chart-payments-evolution');
        if (!canvas || typeof Chart === 'undefined') return;
        const labels = series.map(s => s.label);
        const data   = series.map(s => s.total);
        const totalYear = data.reduce((a, b) => a + b, 0);
        const totalEl = document.getElementById('payments-evol-total');
        if (totalEl) totalEl.textContent = 'Total année : ' + fmt(totalYear) + ' FCFA';

        if (paymentsEvolutionChart) {
            paymentsEvolutionChart.data.labels = labels;
            paymentsEvolutionChart.data.datasets[0].data = data;
            paymentsEvolutionChart.update();
            return;
        }
        paymentsEvolutionChart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Paiements (FCFA)',
                    data,
                    backgroundColor: 'rgba(34,197,94,.45)',
                    borderColor: '#16a34a',
                    borderWidth: 1.5,
                    borderRadius: 4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => fmt(ctx.parsed.y) + ' FCFA',
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: v => v >= 1e6 ? (v/1e6).toFixed(1) + 'M' : (v >= 1e3 ? (v/1e3).toFixed(0) + 'k' : v) },
                    },
                },
            },
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
            tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:40px;color:var(--text3);">Aucune commune avec des panneaux installés.</td></tr>';
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
                {{-- FIX 2026-06-22 — On expose is_abidjan calculé côté serveur
                     (via la liste blanche officielle Commune::ABIDJAN_COMMUNES)
                     plutôt que de se fier au champ city qui n'est pas fiable
                     selon l'import (ex : Bingerville pouvait être city='Bingerville'
                     au lieu de city='Abidjan'). --}}
                <tr data-commune-id="${c.commune_id}" data-abidjan="${c.is_abidjan ? '1' : '0'}" data-search="${(c.commune || '').toLowerCase()}">
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
                        ${c.statut === 'partiel'
                            ? `<div style="margin-top:4px;font-weight:800;font-size:13px;color:#c2570d;font-variant-numeric:tabular-nums;" title="Solde restant à payer">
                                  Solde&nbsp;: ${fmt(c.solde)}
                               </div>
                               <div style="font-size:9px;color:var(--text3);">payé&nbsp;: ${fmt(c.total_paye)}</div>`
                            : (c.total_paye > 0 && c.statut !== 'paye'
                                ? `<div style="font-size:9px;color:var(--text3);margin-top:2px;">payé : ${fmt(c.total_paye)}</div>`
                                : '')
                        }
                    </td>
                    <td style="text-align:center;white-space:nowrap;">
                        {{-- FIX 2026-06-22 — En mode "personnalisé" (période multi-mois libre)
                             le bouton "Payer" est désactivé : un paiement doit être rattaché à
                             une période standard (mensuel/trimestriel/annuel) pour rester
                             traçable. L'utilisateur passe par la fiche commune ou bascule
                             le tab pour enregistrer un versement. --}}
                        ${currentPeriodType === 'personnalise' ? `
                            <button type="button" class="tax-pay-btn" disabled
                                    style="opacity:.45;cursor:not-allowed"
                                    title="Pour enregistrer un paiement, choisis d'abord une période standard (Mensuel / Trimestriel / Annuel). Les paiements sont rattachés à des périodes officielles pour la traçabilité.">
                                💰 Payer
                            </button>
                        ` : `
                            <button type="button"
                                    class="tax-pay-btn ${c.payment_id ? 'tax-pay-btn-edit' : ''}"
                                    onclick="TaxModule.openPaymentModal(${c.commune_id})">
                                ${c.payment_id ? '✏️ Modifier' : '💰 Payer'}
                            </button>
                        `}
                        {{-- LOT 2 — Fiche commune (suivi mensuel + cumul trim/annuel). --}}
                        <a href="/admin/taxes/commune/${c.commune_id}"
                           class="tax-pay-btn"
                           style="margin-left:4px;background:rgba(232,160,32,.10);color:#b45309;border:1px solid rgba(232,160,32,.30);text-decoration:none;display:inline-block"
                           title="Fiche détaillée de ${c.commune} (suivi mensuel + trimestriel + annuel)">
                            🏛️ Fiche
                        </a>
                        {{-- LOT 3 — Historique paiements détaillé de cette commune. --}}
                        <a href="/admin/taxes/commune/${c.commune_id}/payments-history"
                           class="tax-pay-btn"
                           style="margin-left:4px;background:rgba(59,130,246,.10);color:#1d4ed8;border:1px solid rgba(59,130,246,.30);text-decoration:none;display:inline-block"
                           title="Voir l'historique complet des versements de ${c.commune}">
                            📋 Historique
                        </a>
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
        // 2026-06-19 — Combine 3 filtres : recherche texte + zone + commune.
        // Cohérence avec le reste de l'app (rapports, finance) : Abidjan =
        // ville='Abidjan', Intérieur = tout le reste. Le dataset des lignes
        // contient data-commune-id et data-city pour matcher sans recalculer.
        const term       = (document.getElementById('tax-search').value || '').trim().toLowerCase();
        const zone       = document.getElementById('tax-filter-zone').value;       // '' | 'abidjan' | 'interieur'
        const communeId  = document.getElementById('tax-filter-commune').value;    // '' | id
        document.querySelectorAll('#tax-table-body tr[data-commune-id]').forEach(tr => {
            const matchTerm    = !term      || tr.dataset.search.includes(term);
            const matchCommune = !communeId || tr.dataset.communeId === communeId;
            // FIX 2026-06-22 — On utilise data-abidjan (booléen calculé côté
            // serveur via la liste blanche officielle des 13 communes du
            // District Autonome d'Abidjan), pas le champ city qui n'est
            // pas fiable selon l'import (Bingerville/Anyama/Songon notamment).
            const isAbidjan    = tr.dataset.abidjan === '1';
            const matchZone    = !zone
                || (zone === 'abidjan'   && isAbidjan)
                || (zone === 'interieur' && !isAbidjan);
            tr.style.display = (matchTerm && matchCommune && matchZone) ? '' : 'none';
        });
    }

    // FIX 2026-06-22 — Tri alphabétique JavaScript accent-insensitive
    // (Adjamé, Attécoubé, Port-Bouët triés correctement). Cohérent avec
    // le tri serveur (Commune::normalizeForMatch).
    function sortAlpha(data) {
        return data.sort((a, b) => (a.commune || '').localeCompare(b.commune || '', 'fr', { sensitivity: 'base' }));
    }

    function getFilteredSortedCommunes() {
        if (!currentData?.communes) return [];
        let data = [...currentData.communes];
        switch (activeKpiFilter) {
            case 'odp_total':   return data.sort((a, b) => b.odp_theorique - a.odp_theorique);
            case 'tm_total':    return data.sort((a, b) => b.tm_theorique - a.tm_theorique);
            case 'grand_total': return data.sort((a, b) => b.total_theorique - a.total_theorique);
            case 'paye_total':  return data.filter(c => c.total_paye > 0).sort((a, b) => b.total_paye - a.total_paye);
            case 'solde_total': return data.filter(c => c.solde > 0).sort((a, b) => b.solde - a.solde);
            // FIX 2026-06-22 v2 — Filtres basés sur statut_year (ANNUEL).
            // Permet de voir les communes en cours de règlement même en
            // mode Mensuel/Trim/Personnalisé (sans ça, le compteur Partiel
            // restait à 0 hors mode Annuel — alors qu'on a clairement des
            // communes en cours de règlement sur l'année).
            case 'communes_soldees':    return sortAlpha(data.filter(c => c.statut_year === 'paye'));
            case 'communes_partielles': return sortAlpha(data.filter(c => c.statut_year === 'partiel'));
            case 'communes_non_payees': return sortAlpha(data.filter(c => c.statut_year === 'non_paye'));
            // Défaut : tri alphabétique propre (avec accents) si data n'est pas
            // déjà trié côté serveur (sécurité supplémentaire).
            default:            return sortAlpha(data);
        }
    }

    function setKpiSubtitle() {
        const labels = {
            odp_total:           'Triées par ODP théorique',
            tm_total:            'Triées par TM théorique',
            grand_total:         'Triées par total décroissant',
            paye_total:          'Filtrées : avec paiement · triées par montant payé',
            solde_total:         'Filtrées : avec solde restant · triées par solde',
            communes_soldees:    'Filtrées : communes SOLDÉES · ordre alphabétique',
            communes_partielles: 'Filtrées : communes en paiement PARTIEL · ordre alphabétique',
            communes_non_payees: 'Filtrées : communes NON PAYÉES · ordre alphabétique',
        };
        const el = document.querySelector('#tax-table-body')
            ?.closest('div[style*="border-radius:14px"]')
            ?.querySelector('span[style*="font-weight:700"]');
        if (el) {
            el.textContent = activeKpiFilter
                ? `🏛️ Détail par commune — ${labels[activeKpiFilter] || ''}`
                : '🏛️ Détail par commune';
        }
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
                // FIX 2026-06-22 — En mode personnalisé, si le mois début passe
                // au-dessus du mois fin, on aligne automatiquement le mois fin.
                if (currentPeriodType === 'personnalise' && currentPeriodEndValue < currentPeriodValue) {
                    currentPeriodEndValue = currentPeriodValue;
                    rebuildPeriodValueOptions();
                }
                loadCalcul();
            });
            // FIX 2026-06-22 — Listener mois fin (mode personnalisé uniquement).
            document.getElementById('period-end-select').addEventListener('change', e => {
                currentPeriodEndValue = parseInt(e.target.value, 10) || currentPeriodValue;
                loadCalcul();
            });
            document.getElementById('period-year-select').addEventListener('change', e => {
                currentPeriodYear = parseInt(e.target.value, 10);
                loadCalcul();
            });
            document.getElementById('tax-search').addEventListener('input', applySearchFilter);
            // 2026-06-19 — Listeners pour les 2 nouveaux filtres (Zone + Commune).
            document.getElementById('tax-filter-zone').addEventListener('change', applySearchFilter);
            document.getElementById('tax-filter-commune').addEventListener('change', applySearchFilter);

            // Filtres KPI
            document.querySelectorAll('.tax-kpi-card[data-kpi-filter]').forEach(card => {
                card.addEventListener('click', () => {
                    const key = card.dataset.kpiFilter;
                    activeKpiFilter = (activeKpiFilter === key) ? null : key;
                    document.querySelectorAll('.tax-kpi-card').forEach(c => c.classList.remove('kpi-active'));
                    if (activeKpiFilter) card.classList.add('kpi-active');
                    setKpiSubtitle();
                    renderTable(getFilteredSortedCommunes());
                });
            });

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
            document.getElementById('pm-tm-theorique').textContent  = fmt(row.tm_theorique)  + ' FCFA';
            document.getElementById('pm-total-theorique').textContent = fmt(row.total_theorique) + ' FCFA';

            // Hotfix Phase 3 (2026-06-22) : saisie LIBRE des montants (brief
            // règle métier : "pas d'auto-calcul Payé en totalité"). On ne
            // pré-remplit qu'en mode édition d'un paiement existant
            // (row.odp_paye > 0 ou row.tm_paye > 0). Sinon : vide pour que
            // la patronne tape précisément ce qu'elle a versé à la mairie.
            const isEdit = (row.odp_paye > 0 || row.tm_paye > 0);
            document.getElementById('pm-odp-paye').value = isEdit ? row.odp_paye : '';
            document.getElementById('pm-tm-paye').value  = isEdit ? row.tm_paye  : '';
            document.getElementById('pm-attestation').checked = !!row.attestation;
            document.getElementById('pm-notes').value = '';
            // LOT 1 — Reset des champs de traçabilité (le user remplit à chaque
            // versement, on ne pré-remplit pas avec l'ancien mode car ça
            // pousserait à des erreurs si le moyen de paiement change).
            document.getElementById('pm-mode').value      = row.mode      || '';
            document.getElementById('pm-reference').value = row.reference || '';
            document.getElementById('pm-comment').value   = row.comment   || '';
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

            // Hotfix TX-9 (2026-06-22) — Alerte non-bloquante "paiement
            // annuel à mi-année". Si la patronne enregistre un paiement
            // de type ANNUEL avec une date avant juillet, on lui demande
            // confirmation : c'est suspect (la mairie ne facture pas le
            // plein annuel avant juin/juillet typiquement). C'est une
            // confirmation, pas un blocage : elle peut toujours valider.
            const paidAtVal = document.getElementById('pm-paid-at').value;
            if (currentPeriodType === 'annuel' && paidAtVal) {
                const paidDate = new Date(paidAtVal);
                if (paidDate.getMonth() < 6) { // jan..juin (mois 0..5)
                    const moisFr = ['janvier','février','mars','avril','mai','juin'];
                    const mLabel = moisFr[paidDate.getMonth()] || (paidDate.getMonth() + 1);
                    const ok = confirm(
                        '⚠️ Tu enregistres un paiement ANNUEL daté du ' +
                        paidDate.toLocaleDateString('fr-FR') +
                        ' (' + mLabel + ').\n\n' +
                        'Habituellement les mairies facturent le plein annuel après juin. ' +
                        "C'est bien volontaire ?\n\n" +
                        '✓ OK pour continuer · Annuler pour rectifier la date ou la périodicité.'
                    );
                    if (!ok) return;
                }
            }

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
                // LOT 1 — Traçabilité paiements (cahier 2026-06-19).
                mode:             document.getElementById('pm-mode').value      || '',
                reference:        document.getElementById('pm-reference').value || '',
                comment:          document.getElementById('pm-comment').value   || '',
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
