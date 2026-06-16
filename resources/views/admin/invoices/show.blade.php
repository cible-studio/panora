<x-admin-layout>
<x-slot name="title">{{ $invoice->reference }}</x-slot>

{{-- ─────────────────────────────────────────────────────────────
    Fix critique : le CSS global .modal-overlay a `display:flex`
    permanent (cf. resources/css/app.css:660). Sans cette règle
    locale, nos modaux (.show toggle pattern) restent ouverts en
    permanence et leurs boutons Annuler / × ne semblent rien faire.
    L'inline `display:flex` ajouté par le JS au moment de l'ouverture
    surclasse ce :not(.show), donc l'ouverture fonctionne aussi.
─────────────────────────────────────────────────────────────── --}}
<style>
    #modal-add-payment:not(.show),
    #modal-schedule:not(.show) {
        display: none !important;
    }
</style>

<x-slot name="topbarLeft">
    {{-- Bouton retour : back() si referer, sinon liste des factures (fallback fiable) --}}
    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('admin.invoices.index') }}"
       class="btn btn-ghost btn-sm" title="Retour"
       style="display:inline-flex;align-items:center;gap:4px;">
        ← Retour
    </a>
</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.invoices.pdf', $invoice) }}" class="btn btn-ghost btn-sm">
        📄 Export PDF
    </a>
    {{-- Phase 7 cahier §13 — Consultation timeline d'audit --}}
    <a href="{{ route('admin.invoices.audit', $invoice) }}" class="btn btn-ghost btn-sm" title="Historique complet des modifications">
        📋 Audit
    </a>
    @if($invoice->status === 'brouillon')
    <form method="POST" action="{{ route('admin.invoices.send', $invoice) }}">
        @csrf
        @method('PATCH')
        <button type="submit" class="btn btn-blue btn-sm">📤 Envoyer au client</button>
    </form>
    @endif
    @if($invoice->status === 'envoyee')
    <form method="POST" action="{{ route('admin.invoices.pay', $invoice) }}">
        @csrf
        @method('PATCH')
        <button type="submit" class="btn btn-success btn-sm">✅ Marquer payée</button>
    </form>
    @endif
    @if(in_array($invoice->status, ['envoyee', 'payee']))
    <form method="POST" action="{{ route('admin.invoices.revert-draft', $invoice) }}"
          onsubmit="return confirm('Rebasculer en brouillon ? La date de paiement sera effacée.')">
        @csrf @method('PATCH')
        <button type="submit" class="btn btn-ghost btn-sm" title="Rebasculer en brouillon">↩ Brouillon</button>
    </form>
    @endif
    <a href="{{ route('admin.invoices.edit', $invoice) }}" class="btn btn-ghost btn-sm">
        ✏️ Modifier
    </a>
</x-slot>

<div style="display:grid; grid-template-columns:1fr 300px; gap:20px;">

    {{-- ════════════════════ COLONNE GAUCHE ════════════════════ --}}
    <div style="display:flex;flex-direction:column;gap:16px">

        {{-- ⚠ Bandeau incohérence : campagne liée annulée. La facture ne
             devrait plus pouvoir suivre son cycle normal (envoi/paiement).
             L'admin doit décider : annuler la facture (🚫 traçable) ou
             clarifier la situation s'il y a eu un paiement réel hors flow. --}}
        @if($invoice->campaign?->status?->value === 'annule' && !in_array($invoice->status, ['annulee', 'payee']))
            <div style="background:linear-gradient(180deg,#fef2f2,#fee2e2);border:1px solid #fca5a5;border-radius:12px;padding:14px 18px;display:flex;align-items:flex-start;gap:14px">
                <div style="width:38px;height:38px;border-radius:10px;background:#ef4444;color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">⚠️</div>
                <div style="flex:1;min-width:0;line-height:1.5">
                    <div style="font-weight:800;color:#991b1b;font-size:14px;margin-bottom:3px">
                        Campagne liée annulée
                    </div>
                    <div style="font-size:12.5px;color:#7f1d1d">
                        La campagne <a href="{{ route('admin.campaigns.show', $invoice->campaign) }}" style="color:#991b1b;font-weight:700">{{ $invoice->campaign->name }}</a>
                        a été annulée. Le client ne devrait plus rien à ce titre.
                        Annule cette facture (bouton 🚫) ou recrée-en une si tu reprends la campagne.
                        L'envoi au client et le marquage payé sont bloqués pour éviter une facturation fantôme.
                    </div>
                </div>
            </div>
        @endif

        {{-- ════════════════════ CARD STATUT & ACTIONS ════════════════════
             Section dédiée au cycle de vie de la facture : pipeline visuel
             Brouillon → Envoyée → Payée + boutons de transition contextuels.
             Avant cette section, seul un bouton "Envoyer au client" dans
             la topbar permettait de changer de statut — peu découvrable.
        ════════════════════════════════════════════════════════════════ --}}
        @php
            $status = $invoice->status;
            // Étapes du workflow + état (done / current / upcoming / off)
            $steps = [
                ['key' => 'brouillon', 'label' => 'Brouillon', 'icon' => '📝'],
                ['key' => 'envoyee',   'label' => 'Envoyée',   'icon' => '📤'],
                ['key' => 'payee',     'label' => 'Payée',     'icon' => '✅'],
            ];
            $cancelled = $status === 'annulee';
            $currentIdx = array_search($status, array_column($steps, 'key'));
        @endphp
        @can('markPaid', $invoice)
        <div class="card">
            <div class="card-header">
                <div class="card-title">🔄 Statut &amp; actions</div>
                @if($cancelled)
                    <span class="badge badge-red" style="font-size:13px;padding:5px 14px">🚫 Annulée</span>
                @endif
            </div>
            <div class="card-body">

                {{-- Pipeline visuel : Brouillon → Envoyée → Payée --}}
                <div style="display:flex;align-items:center;gap:0;margin-bottom:18px;{{ $cancelled ? 'opacity:.45;filter:grayscale(.6)' : '' }}">
                    @foreach($steps as $i => $step)
                        @php
                            $isDone     = !$cancelled && $currentIdx !== false && $i < $currentIdx;
                            $isCurrent  = !$cancelled && $i === $currentIdx;
                            $bg     = $isCurrent ? 'var(--accent)' : ($isDone ? '#16a34a' : 'var(--surface2)');
                            $color  = ($isCurrent || $isDone) ? '#fff' : 'var(--text3)';
                            $border = $isCurrent ? '3px solid rgba(232,160,32,.30)' : 'none';
                        @endphp
                        <div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0;min-width:78px">
                            <div style="width:44px;height:44px;border-radius:50%;background:{{ $bg }};color:{{ $color }};display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:800;box-shadow:{{ $border ? '0 0 0 3px rgba(232,160,32,.30)' : 'none' }}">
                                {{ $isDone ? '✓' : $step['icon'] }}
                            </div>
                            <div style="font-size:11px;font-weight:800;color:{{ $isCurrent ? 'var(--accent-dark)' : ($isDone ? '#15803d' : 'var(--text3)') }};margin-top:6px;text-transform:uppercase;letter-spacing:.4px">{{ $step['label'] }}</div>
                        </div>
                        @if($i < count($steps) - 1)
                            @php
                                $segDone = !$cancelled && $currentIdx !== false && $i < $currentIdx;
                            @endphp
                            <div style="flex:1;height:3px;background:{{ $segDone ? '#16a34a' : 'var(--surface2)' }};border-radius:2px;margin:0 -6px;transform:translateY(-12px)"></div>
                        @endif
                    @endforeach
                </div>

                {{-- Boutons d'action contextuels (Phase 8A — cycle complet) --}}
                <div style="display:flex;flex-wrap:wrap;gap:8px">
                    @if($status === 'brouillon')
                        <form method="POST" action="{{ route('admin.invoices.generated', $invoice) }}" style="margin:0">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-ghost btn-sm" title="Bascule en GÉNÉRÉE (validation visuelle avant lock)">
                                📋 Marquer générée
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.invoices.validated', $invoice) }}" style="margin:0"
                              onsubmit="return confirm('VALIDER cette facture ?\n\nLa facture sera VERROUILLÉE automatiquement et les taux ODP/TM des lignes seront figés. Pour modifier ensuite, il faudra déverrouiller (action tracée).');">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-blue btn-sm" title="Valide + VERROUILLE la facture + fige les taux">
                                🔒 Valider (verrouille)
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.invoices.send', $invoice) }}" style="margin:0">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-success btn-sm" title="Bascule directement en Envoyée + verrouille">
                                📤 Envoyer au client
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.invoices.pay', $invoice) }}" style="margin:0"
                              onsubmit="return confirm('Marquer cette facture comme payée maintenant ? (saute Envoyée)');">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-ghost btn-sm" title="Cas paiement comptant — saute Envoyée">
                                ✅ Marquer payée
                            </button>
                        </form>
                    @endif

                    @if($status === 'generee')
                        <form method="POST" action="{{ route('admin.invoices.validated', $invoice) }}" style="margin:0"
                              onsubmit="return confirm('VALIDER cette facture ?\n\nLa facture sera VERROUILLÉE automatiquement et les taux ODP/TM des lignes seront figés.');">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-blue btn-sm">🔒 Valider (verrouille)</button>
                        </form>
                        <form method="POST" action="{{ route('admin.invoices.revert-draft', $invoice) }}" style="margin:0">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-ghost btn-sm">↩ Retour en brouillon</button>
                        </form>
                    @endif

                    @if($status === 'validee')
                        <form method="POST" action="{{ route('admin.invoices.send', $invoice) }}" style="margin:0">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-success btn-sm">📤 Envoyer au client</button>
                        </form>
                        <form method="POST" action="{{ route('admin.invoices.litige', $invoice) }}" style="margin:0"
                              onsubmit="var r = prompt('Motif du litige (optionnel) :'); if (r === null) return false; this.querySelector('[name=reason]').value = r;">
                            @csrf @method('PATCH')
                            <input type="hidden" name="reason" value="">
                            <button type="submit" class="btn btn-ghost btn-sm" style="color:#b45309">⚠ Litige</button>
                        </form>
                    @endif

                    @if($status === 'envoyee')
                        <form method="POST" action="{{ route('admin.invoices.pay', $invoice) }}" style="margin:0"
                              onsubmit="return confirm('Marquer cette facture comme payée ?');">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-success btn-sm">✅ Marquer payée</button>
                        </form>
                        <form method="POST" action="{{ route('admin.invoices.litige', $invoice) }}" style="margin:0"
                              onsubmit="var r = prompt('Motif du litige (optionnel) :'); if (r === null) return false; this.querySelector('[name=reason]').value = r;">
                            @csrf @method('PATCH')
                            <input type="hidden" name="reason" value="">
                            <button type="submit" class="btn btn-ghost btn-sm" style="color:#b45309">⚠ Litige</button>
                        </form>
                    @endif

                    @if($status === 'litige')
                        <form method="POST" action="{{ route('admin.invoices.send', $invoice) }}" style="margin:0"
                              onsubmit="return confirm('Sortir du litige et remettre en Envoyée ?');">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-blue btn-sm">↩ Sortir du litige</button>
                        </form>
                    @endif

                    @if(in_array($status, ['envoyee', 'payee', 'validee', 'partiellement_payee', 'en_retard', 'generee', 'litige']))
                        <form method="POST" action="{{ route('admin.invoices.revert-draft', $invoice) }}" style="margin:0"
                              onsubmit="return confirm('Rebasculer en brouillon ?\n\nLa facture sera déverrouillée et redeviendra modifiable. La date de paiement (si présente) sera effacée. Action tracée.');">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--text2)">↩ Retour brouillon</button>
                        </form>
                    @endif

                    @if(!in_array($status, ['annulee', 'payee']))
                        <form method="POST" action="{{ route('admin.invoices.cancel', $invoice) }}" style="margin:0;margin-left:auto"
                              onsubmit="return confirm('Annuler cette facture ?\n\nLa facture restera dans l\'historique (traçable) mais ne pourra plus être réglée ni envoyée.');">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-ghost btn-sm" style="color:#ef4444">🚫 Annuler la facture</button>
                        </form>
                    @endif

                    @if($status === 'annulee')
                        <form method="POST" action="{{ route('admin.invoices.revert-draft', $invoice) }}" style="margin:0"
                              onsubmit="return confirm('Réactiver cette facture en brouillon ?');">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-ghost btn-sm">♻ Réactiver en brouillon</button>
                        </form>
                    @endif
                </div>

                {{-- Tip contextuel sous les boutons --}}
                <div style="margin-top:14px;padding:10px 12px;background:var(--surface2);border-radius:8px;font-size:11.5px;color:var(--text3);line-height:1.5">
                    @if($status === 'brouillon')
                        💡 Tant que la facture est en brouillon, elle est modifiable. <strong>L'envoi au client la verrouille</strong> et ouvre le suivi paiements.
                    @elseif($status === 'envoyee')
                        💡 Facture verrouillée car envoyée au client. Rebascule en brouillon pour modifier, ou enregistre un versement pour solder.
                    @elseif($status === 'payee')
                        💡 Facture soldée. Tu peux toujours rebasculer en brouillon si besoin de correction, ou émettre un avoir.
                    @else
                        💡 Facture annulée — figée dans l'historique. Réactive-la en brouillon si l'annulation était une erreur.
                    @endif
                </div>
            </div>
        </div>
        @endcan

        {{-- CARD PRINCIPALE : référence + statut + montants --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">{{ $invoice->reference }}</div>
                    <div style="font-size:12px; color:var(--text3); margin-top:3px;">
                        Émise le <strong style="color:var(--text2)">{{ $invoice->issued_at->format('d/m/Y') }}</strong>
                        par <strong style="color:var(--text2)">{{ $invoice->creator?->name ?? '—' }}</strong>
                    </div>
                </div>
                @if($invoice->status === 'brouillon')
                    <span class="badge badge-gray" style="font-size:13px; padding:5px 14px;">📝 Brouillon</span>
                @elseif($invoice->status === 'envoyee')
                    <span class="badge badge-blue" style="font-size:13px; padding:5px 14px;">📤 Envoyée</span>
                @elseif($invoice->status === 'payee')
                    <span class="badge badge-green" style="font-size:13px; padding:5px 14px;">✅ Payée</span>
                @else
                    <span class="badge badge-red" style="font-size:13px; padding:5px 14px;">🚫 Annulée</span>
                @endif
            </div>
            <div class="card-body">
                {{-- Triplet CLIENT / CAMPAGNE / DATE — chaque cellule cliquable si possible --}}
                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px;">
                    <div>
                        <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">CLIENT</div>
                        @if($invoice->client)
                            <a href="{{ route('admin.clients.show', $invoice->client) }}"
                               style="font-weight:700;color:var(--text);text-decoration:none;border-bottom:1px dashed var(--border2);display:inline-flex;align-items:center;gap:4px"
                               title="Ouvrir la fiche client">
                                {{ $invoice->client->name }}
                                <span style="color:var(--accent);font-size:11px">↗</span>
                            </a>
                        @else
                            <span style="color:var(--text3)">—</span>
                        @endif
                    </div>
                    <div>
                        <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">CAMPAGNE</div>
                        @if($invoice->campaign)
                            <a href="{{ route('admin.campaigns.show', $invoice->campaign) }}"
                               style="font-weight:700;color:var(--text);text-decoration:none;border-bottom:1px dashed var(--border2);display:inline-flex;align-items:center;gap:4px"
                               title="Ouvrir la fiche campagne">
                                {{ $invoice->campaign->name }}
                                <span style="color:var(--accent);font-size:11px">↗</span>
                            </a>
                        @else
                            <span style="color:var(--text3)">—</span>
                        @endif
                    </div>
                    <div>
                        <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">DATE ÉMISSION</div>
                        <div style="font-weight:700;">{{ $invoice->issued_at->format('d/m/Y') }}</div>
                    </div>
                </div>

                {{-- ════ LIGNES FACTURE (FNE) ════
                     Affichées seulement s'il y a au moins une ligne en
                     base (factures historiques avant la refonte n'en
                     ont pas — l'affichage bascule alors sur l'ancien
                     montant unique). --}}
                @php
                    $hasLines = $invoice->lines && $invoice->lines->isNotEmpty();
                    $fmt = fn($v) => number_format((float) $v, 0, ',', ' ');
                @endphp
                @if($hasLines)
                <div style="margin-top:20px;border:1px solid var(--border);border-radius:10px;overflow:hidden">
                    <div style="background:var(--surface2);padding:8px 12px;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">
                        Détail des lignes ({{ $invoice->lines->count() }})
                    </div>
                    <div style="overflow-x:auto">
                        <table style="width:100%;border-collapse:collapse;font-size:12px">
                            <thead>
                                <tr style="background:var(--surface2);color:var(--text3);text-align:left">
                                    <th style="padding:8px 10px;font-weight:700;font-size:10px;text-transform:uppercase">Désignation</th>
                                    <th style="padding:8px 10px;font-weight:700;font-size:10px;text-transform:uppercase;text-align:right">PU HT</th>
                                    <th style="padding:8px 10px;font-weight:700;font-size:10px;text-transform:uppercase;text-align:center">Qté</th>
                                    <th style="padding:8px 10px;font-weight:700;font-size:10px;text-transform:uppercase;text-align:center">Mois</th>
                                    <th style="padding:8px 10px;font-weight:700;font-size:10px;text-transform:uppercase;text-align:center">m²</th>
                                    <th style="padding:8px 10px;font-weight:700;font-size:10px;text-transform:uppercase;text-align:right">Montant HT</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoice->lines as $l)
                                    <tr style="border-top:1px solid var(--border)">
                                        <td style="padding:8px 10px">
                                            <div style="font-weight:600">{{ $l->designation }}</div>
                                            @if($l->snapshot_commune_name)
                                                <div style="font-size:10px;color:var(--text3);margin-top:1px">📍 {{ $l->snapshot_commune_name }}</div>
                                            @endif
                                        </td>
                                        <td style="padding:8px 10px;text-align:right;">{{ $fmt($l->pu_ht_mensuel) }}</td>
                                        <td style="padding:8px 10px;text-align:center">{{ $l->quantite }}</td>
                                        <td style="padding:8px 10px;text-align:center">{{ rtrim(rtrim(number_format($l->duree_mois, 2, ',', ''), '0'), ',') }}</td>
                                        <td style="padding:8px 10px;text-align:center">{{ rtrim(rtrim(number_format($l->dimension_m2, 2, ',', ''), '0'), ',') }}</td>
                                        <td style="padding:8px 10px;text-align:right;font-weight:700;color:var(--accent)">{{ $fmt($l->montant_ht_ligne) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                {{-- ════ VENTILATION FNE ════
                     Conformément à la facture normalisée électronique CI :
                     TOTAL HT → TVA → TOTAL TTC, puis AUTRES TAXES détaillées
                     (TSP/TM/ODP), puis services TTC, puis TOTAL À PAYER. --}}
                <div style="margin-top:20px;padding:16px;background:var(--surface2);border-radius:10px;border:1px solid var(--border)">
                    @if($invoice->remise_pct > 0)
                        <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:12px;color:var(--text3)">
                            <span>Total HT brut</span>
                            <span>{{ $fmt($invoice->amount) }} FCFA</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:12px;color:#b45309">
                            <span>Remise ({{ rtrim(rtrim(number_format($invoice->remise_pct, 2, ',', ''), '0'), ',') }}%)</span>
                            <span>− {{ $fmt(($invoice->amount * $invoice->remise_pct / 100)) }} FCFA</span>
                        </div>
                    @endif

                    <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                        <span style="color:var(--text2);font-weight:600">TOTAL HT</span>
                        <span style="font-weight:700;">{{ $fmt($invoice->net_ht ?: $invoice->amount) }} FCFA</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                        <span style="color:var(--text2);font-weight:600">TVA ({{ rtrim(rtrim(number_format($invoice->tva, 2, ',', ''), '0'), ',') }} %)</span>
                        <span style="font-weight:700;">{{ $fmt($invoice->tva_amount ?: ($invoice->amount_ttc - $invoice->amount)) }} FCFA</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding-top:10px;border-top:1px solid var(--border);margin-bottom:14px">
                        <span style="font-weight:800;font-size:13px">TOTAL TTC</span>
                        <span style="font-weight:800;font-size:14px;">{{ $fmt($invoice->amount_ttc) }} FCFA</span>
                    </div>

                    @php
                        $autres = (float) $invoice->tsp_amount + (float) $invoice->tm_total + (float) $invoice->odp_total;
                        // Services annexes (prompt v2) : N lignes libres. Fallback
                        // sur les 2 champs legacy si la facture n'a pas encore été
                        // migrée vers invoice_services.
                        $serviceLines = $invoice->services;
                        if ($serviceLines->isEmpty()) {
                            $tmp = collect();
                            if ((float) $invoice->services_impression > 0) {
                                $tmp->push((object) ['label' => "Frais d'impression", 'prix_ht' => (float) $invoice->services_impression]);
                            }
                            if ((float) $invoice->services_pose_depose > 0) {
                                $tmp->push((object) ['label' => 'Frais de pose et dépose', 'prix_ht' => (float) $invoice->services_pose_depose]);
                            }
                            $serviceLines = $tmp;
                        }
                        $servicesHt  = $serviceLines->sum('prix_ht');
                        $servicesTtc = $servicesHt * (1 + (float) $invoice->tva / 100);
                    @endphp

                    @if($autres > 0)
                        <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:10px 12px;margin-bottom:12px">
                            <div style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px">Autres taxes</div>
                            @if($invoice->tsp_amount > 0)
                                <div style="display:flex;justify-content:space-between;font-size:11.5px;color:var(--text2);margin-bottom:4px">
                                    <span>TSP — Taxe de Soutien à la Production (3 %)</span>
                                    <span>{{ $fmt($invoice->tsp_amount) }}</span>
                                </div>
                            @endif
                            @if($invoice->tm_total > 0)
                                <div style="display:flex;justify-content:space-between;font-size:11.5px;color:var(--text2);margin-bottom:4px">
                                    <span>TM — Taxe Municipale</span>
                                    <span>{{ $fmt($invoice->tm_total) }}</span>
                                </div>
                            @endif
                            @if($invoice->odp_total > 0)
                                <div style="display:flex;justify-content:space-between;font-size:11.5px;color:var(--text2);margin-bottom:4px">
                                    <span>ODP — Occupation Domaine Public</span>
                                    <span>{{ $fmt($invoice->odp_total) }}</span>
                                </div>
                            @endif
                            <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:700;padding-top:6px;border-top:1px dashed var(--border);margin-top:6px">
                                <span>Sous-total autres taxes</span>
                                <span>{{ $fmt($autres) }} FCFA</span>
                            </div>
                        </div>
                    @endif

                    @if($servicesHt > 0)
                        <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:10px 12px;margin-bottom:12px">
                            <div style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px">
                                Services annexes ({{ $serviceLines->count() }})
                            </div>
                            @foreach($serviceLines as $svc)
                                <div style="display:flex;justify-content:space-between;font-size:11.5px;color:var(--text2);margin-bottom:4px">
                                    <span>{{ $svc->label }} <span style="color:var(--text3);font-size:10.5px">(HT)</span></span>
                                    <span>{{ $fmt($svc->prix_ht) }}</span>
                                </div>
                            @endforeach
                            <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:700;padding-top:6px;border-top:1px dashed var(--border);margin-top:6px">
                                <span>Sous-total services TTC (TVA 18 %)</span>
                                <span>{{ $fmt($servicesTtc) }} FCFA</span>
                            </div>
                        </div>
                    @endif

                    {{-- Bandeau TOTAL À PAYER : fond sombre solide + texte clair
                         garanti lisible quel que soit le thème (clair / sombre).
                         Avant : linear-gradient(var(--accent)…) → texte blanc
                         devenait invisible dans certains thèmes. --}}
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 18px;background:linear-gradient(135deg,#1f2937,#0f172a);color:#fbbf24;border-radius:10px;box-shadow:0 4px 14px rgba(0,0,0,.18);border:1px solid rgba(251,191,36,.25)">
                        <span style="font-weight:800;font-size:14px;letter-spacing:.4px;color:#fbbf24">💰 TOTAL À PAYER</span>
                        <span style="font-weight:800;font-size:19px;color:#fff;text-shadow:0 1px 2px rgba(0,0,0,.4)">{{ $fmt($invoice->total_a_payer ?: $invoice->amount_ttc) }} FCFA</span>
                    </div>
                </div>

                {{-- ════ STATUT PAIEMENT (dérivé des versements + échéancier) ════ --}}
                @php
                    $paid      = $invoice->paidAmount();
                    $remaining = $invoice->remainingAmount();
                    $pct       = $invoice->paidPercentage();
                    $payStatus = $invoice->paymentStatus();
                    $payConfig = match($payStatus) {
                        'soldee'    => ['bg' => 'rgba(34,197,94,.10)',  'border' => 'rgba(34,197,94,.35)',  'color' => '#16a34a', 'bar' => '#16a34a', 'icon' => '✅', 'label' => 'Soldée'],
                        'partielle' => ['bg' => 'rgba(245,158,11,.10)', 'border' => 'rgba(245,158,11,.35)', 'color' => '#b45309', 'bar' => '#f59e0b', 'icon' => '⏳', 'label' => 'Partiellement payée'],
                        'en_retard' => ['bg' => 'rgba(239,68,68,.10)',  'border' => 'rgba(239,68,68,.40)',  'color' => '#b91c1c', 'bar' => '#ef4444', 'icon' => '🔴', 'label' => 'En retard'],
                        'annulee'   => ['bg' => 'rgba(107,114,128,.10)','border' => 'rgba(107,114,128,.35)','color' => '#4b5563', 'bar' => '#9ca3af', 'icon' => '🚫', 'label' => 'Annulée'],
                        default     => ['bg' => 'rgba(239,68,68,.08)',  'border' => 'rgba(239,68,68,.30)',  'color' => '#b91c1c', 'bar' => '#ef4444', 'icon' => '❌', 'label' => 'Non payée'],
                    };
                @endphp
                <div style="margin-top:16px;padding:12px 14px;background:{{ $payConfig['bg'] }};border:1px solid {{ $payConfig['border'] }};border-radius:10px;color:{{ $payConfig['color'] }}">
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <span style="font-weight:800;font-size:13px">{{ $payConfig['icon'] }} {{ $payConfig['label'] }}</span>
                        <span style="font-size:13px;font-weight:700;">
                            {{ $fmt($paid) }} / {{ $fmt($invoice->total_a_payer ?: $invoice->amount_ttc) }} FCFA
                            <span style="margin-left:8px;font-size:11px;opacity:.75">({{ rtrim(rtrim(number_format($pct, 1, ',', ''), '0'), ',') }} %)</span>
                        </span>
                    </div>

                    {{-- Barre de progression — prompt v2 § 4.2 --}}
                    <div style="margin-top:8px;height:6px;background:rgba(0,0,0,.06);border-radius:999px;overflow:hidden">
                        <div style="height:100%;width:{{ max(0, min(100, $pct)) }}%;background:{{ $payConfig['bar'] }};border-radius:999px;transition:width .25s"></div>
                    </div>

                    @if($remaining > 0)
                        <div style="margin-top:6px;font-size:11.5px;opacity:.85">
                            Reste à payer : <strong>{{ $fmt($remaining) }} FCFA</strong>
                            @if($payStatus === 'en_retard')
                                @php $nextDue = $invoice->nextDueSchedule(); @endphp
                                @if($nextDue)
                                    · 🔴 Échéance « {{ $nextDue->label ?? 'Échéance' }} » dépassée depuis {{ abs($nextDue->daysUntilDue()) }} j
                                @endif
                            @endif
                        </div>
                    @endif
                </div>

                {{-- ════ LOCK BADGE + DÉVERROUILLAGE ÉVENTUEL ════ --}}
                @if($invoice->isLocked())
                    <div style="margin-top:12px;padding:10px 14px;background:rgba(107,114,128,.10);border:1px dashed rgba(107,114,128,.35);border-radius:10px;font-size:11.5px;color:#4b5563">
                        🔒 <strong>Facture verrouillée</strong> le {{ $invoice->locked_at->format('d/m/Y à H:i') }}@if($invoice->lockedBy) par {{ $invoice->lockedBy->name }}@endif.
                        Document fiscal — modifications bloquées.
                        @can('update', $invoice)
                            <form method="POST" action="{{ route('admin.invoices.unlock', $invoice) }}" style="display:inline;margin-left:8px"
                                  onsubmit="return confirm('Déverrouiller la facture ? Cette action sera tracée.');">
                                @csrf @method('PATCH')
                                <button type="submit" style="background:transparent;border:0;color:#b45309;font-weight:700;cursor:pointer;font-size:11.5px;text-decoration:underline">
                                    🔓 Déverrouiller
                                </button>
                            </form>
                        @endcan
                    </div>
                @endif
            </div>
        </div>

        {{-- ════════════════════ CARD VERSEMENTS ════════════════════
             Timeline des paiements + bouton "Ajouter un versement".
             Visible uniquement si la facture n'est PAS un brouillon
             (pas de paiement possible avant envoi).
        ═════════════════════════════════════════════════════════════ --}}
        @if(!in_array($invoice->status, ['brouillon', 'annulee']))
            @php $payments = $invoice->payments ?? collect(); @endphp
            <div class="card">
                <div class="card-header">
                    <div class="card-title">💸 Versements <span style="font-weight:400;color:var(--text3);font-size:12px;margin-left:6px">({{ $payments->count() }})</span></div>
                    @can('markPaid', $invoice)
                        @if($remaining > 0)
                            <button type="button" onclick="document.getElementById('modal-add-payment').classList.add('show')"
                                    class="btn btn-primary btn-sm">
                                + Ajouter un versement
                            </button>
                        @endif
                    @endcan
                </div>
                <div class="card-body">
                    @if($payments->isEmpty())
                        <div style="padding:24px;text-align:center;color:var(--text3);font-size:13px;background:var(--surface2);border-radius:10px">
                            Aucun versement enregistré pour cette facture.
                        </div>
                    @else
                        <div style="display:flex;flex-direction:column;gap:8px">
                            @foreach($payments as $p)
                                <div style="display:flex;align-items:center;gap:12px;padding:10px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:10px">
                                    <div style="font-size:20px">
                                        {{ $p->mode_icon }}
                                    </div>
                                    <div style="flex:1;min-width:0">
                                        <div style="font-size:13px;font-weight:700;display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                                            <span>{{ $fmt($p->montant) }} FCFA</span>
                                            <span style="font-weight:400;color:var(--text3);font-size:11.5px">{{ $p->mode_label }}</span>
                                            @if($p->is_acompte)
                                                @php
                                                    // Phase 8D cahier §5 — "Identifier pour chaque acompte :
                                                    // montant, date, pourcentage de la facture couvert."
                                                    $totalDue = (float) ($invoice->total_a_payer ?: $invoice->amount_ttc ?: 0);
                                                    $pctCovered = $totalDue > 0 ? round((float) $p->montant / $totalDue * 100, 1) : 0;
                                                @endphp
                                                <span style="background:rgba(245,158,11,.12);color:#b45309;padding:1px 8px;border-radius:999px;font-size:9.5px;font-weight:800;letter-spacing:.3px">🅰 ACOMPTE {{ rtrim(rtrim(number_format($pctCovered, 1, ',', ''), '0'), ',') }} %</span>
                                            @endif
                                        </div>
                                        <div style="font-size:11px;color:var(--text3);margin-top:1px">
                                            {{ $p->paid_at->format('d/m/Y') }}
                                            @if($p->reference) · Réf. <strong>{{ $p->reference }}</strong>@endif
                                            @if($p->bank) · 🏦 {{ $p->bank }}@endif
                                            @if($p->creator) · {{ $p->creator->name }}@endif
                                        </div>
                                        @if($p->note)
                                            <div style="font-size:11px;color:var(--text2);margin-top:3px;font-style:italic">{{ $p->note }}</div>
                                        @endif
                                        @if($p->attachment_path)
                                            <div style="margin-top:5px">
                                                <a href="{{ route('admin.invoices.payments.attachment', [$invoice, $p]) }}"
                                                   style="display:inline-flex;align-items:center;gap:5px;background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.25);color:#1d4ed8;padding:3px 9px;border-radius:6px;font-size:10.5px;font-weight:700;text-decoration:none">
                                                    📎 {{ \Illuminate\Support\Str::limit($p->attachment_original_name ?: 'Pièce jointe', 32) }}
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                    @can('markPaid', $invoice)
                                        <form method="POST" action="{{ route('admin.invoices.payments.remove', [$invoice, $p]) }}"
                                              onsubmit="return confirm('Supprimer ce versement de {{ $fmt($p->montant) }} FCFA ?');">
                                            @csrf @method('DELETE')
                                            <button type="button" type="submit" class="btn btn-ghost btn-sm" style="color:var(--red);font-size:11px"
                                                    onclick="if(confirm('Supprimer ?')) this.form.submit();">
                                                🗑
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- ════════════════════ CARD ÉCHÉANCIER PRÉVISIONNEL ════════════════════
                 Engagement de paiement planifié (acompte / solde / mensualités).
                 Distinct des versements réels — sert au recouvrement (qui doit
                 relancer, quand). L'admin marque chaque échéance "payée" quand
                 le versement réel correspondant a été enregistré.
            ═══════════════════════════════════════════════════════════════════════ --}}
            {{-- ════════════════════ CARD RELANCES DE CETTE FACTURE ════════════════════
                 Phase 8B cahier §9 : "Historique des relances... consultable
                 par facture ET par client." Le dashboard finance/recouvrement
                 permet la vue par client ; ici on a la vue par facture.
            ═══════════════════════════════════════════════════════════════════════ --}}
            @if(!empty($invoiceRelances) && $invoiceRelances->isNotEmpty())
            <div class="card">
                <div class="card-header">
                    <div class="card-title">📞 Relances de cette facture <span style="font-weight:400;color:var(--text3);font-size:12px;margin-left:6px">({{ $invoiceRelances->count() }})</span></div>
                    <a href="{{ route('admin.clients.show', $invoice->client_id) }}" style="font-size:11px;color:var(--accent);text-decoration:none;font-weight:700">Toutes les relances client →</a>
                </div>
                <div class="card-body" style="display:flex;flex-direction:column;gap:6px">
                    @foreach($invoiceRelances as $r)
                        @php
                            $outcomeIcon = match($r->outcome) {
                                'promesse_paiement' => '📅',
                                'paiement_recu'     => '✅',
                                'a_relancer'        => '🔁',
                                'sans_reponse'      => '📵',
                                'desaccord'         => '⚠',
                                default             => null,
                            };
                            $outcomeLbl = match($r->outcome) {
                                'promesse_paiement' => 'Promesse de paiement',
                                'paiement_recu'     => 'Paiement reçu',
                                'a_relancer'        => 'À relancer',
                                'sans_reponse'      => 'Sans réponse',
                                'desaccord'         => 'Désaccord',
                                'autre'             => 'Autre',
                                default             => null,
                            };
                        @endphp
                        <div style="padding:10px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:9px">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;flex-wrap:wrap">
                                <div style="display:flex;align-items:center;gap:8px;flex:1;min-width:200px">
                                    <span style="font-weight:700;font-size:12.5px">{{ \App\Services\ReminderService::canalLabel($r->canal) }}</span>
                                    <span style="font-size:11px;color:var(--text3)">{{ $r->relance_date->format('d/m/Y') }}</span>
                                    @if($r->user) <span style="font-size:11px;color:var(--text3)">· {{ $r->user->name }}</span>@endif
                                </div>
                                @if($outcomeIcon)
                                    <span style="background:rgba(99,102,241,.10);color:#4338ca;padding:2px 8px;border-radius:6px;font-size:10.5px;font-weight:700">{{ $outcomeIcon }} {{ $outcomeLbl }}</span>
                                @endif
                            </div>
                            <div style="font-size:12px;color:var(--text2);margin-top:5px;line-height:1.5">{{ $r->note }}</div>
                            @if($r->suite_donnee)
                                <div style="font-size:11px;color:var(--text3);margin-top:3px;font-style:italic">↳ Suite : {{ $r->suite_donnee }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- ════════════════════ VENTILATION PAIEMENT PAR COMMUNE ════════════════════
                 Phase 8 finalisation cahier §11 : « répartir chaque encaissement au
                 prorata du HT des lignes ». Affichage sur la fiche facture (visible
                 uniquement si paiements > 0 ET ≥ 2 communes — sinon pas d'intérêt).
            ═══════════════════════════════════════════════════════════════════════ --}}
            @if(!empty($paymentAllocation) && $paymentAllocation->isNotEmpty())
            <div class="card">
                <div class="card-header">
                    <div class="card-title">🗺 Ventilation paiement par commune <span style="font-weight:400;color:var(--text3);font-size:12px;margin-left:6px">(prorata HT)</span></div>
                </div>
                <div class="card-body">
                    <div style="font-size:11.5px;color:var(--text3);margin-bottom:10px;line-height:1.5">
                        Total encaissé {{ $fmt($invoice->paidAmount()) }} FCFA réparti au prorata du HT des lignes (règle cahier §11) — utile au comptable pour la déclaration TM/ODP par commune.
                    </div>
                    <div style="display:flex;flex-direction:column;gap:6px">
                        @foreach($paymentAllocation as $row)
                            <div style="display:flex;align-items:center;gap:10px;padding:8px 12px;background:var(--surface2);border-radius:8px">
                                <div style="flex:1;font-size:13px;font-weight:700">📍 {{ $row['commune_name'] }}</div>
                                <div style="font-size:11px;color:var(--text3);min-width:60px;text-align:right">{{ rtrim(rtrim(number_format($row['share_pct'], 2, ',', ''), '0'), ',') }} %</div>
                                <div style="font-size:13px;font-weight:800;color:var(--accent);min-width:120px;text-align:right">{{ $fmt($row['amount']) }} FCFA</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            @php $schedules = $invoice->schedules ?? collect(); @endphp
            <div class="card">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
                    <div class="card-title">📅 Échéancier prévisionnel <span style="font-weight:400;color:var(--text3);font-size:12px;margin-left:6px">({{ $schedules->count() }})</span></div>
                    @can('markPaid', $invoice)
                        <button type="button" onclick="document.getElementById('modal-schedule').classList.add('show')"
                                class="btn btn-ghost btn-sm">
                            @if($schedules->isEmpty()) + Configurer @else 🔄 Reconfigurer @endif
                        </button>
                    @endcan
                </div>
                <div class="card-body">
                    @if($schedules->isEmpty())
                        <div style="padding:18px;text-align:center;color:var(--text3);font-size:13px;background:var(--surface2);border-radius:10px">
                            Aucun échéancier configuré. Configure un acompte/solde ou des mensualités pour faciliter le recouvrement.
                        </div>
                    @else
                        <div style="display:flex;flex-direction:column;gap:8px">
                            @foreach($schedules as $s)
                                @php
                                    $state = $s->state();
                                    $stateConfig = match($state) {
                                        'paid'     => ['bg' => 'rgba(34,197,94,.08)',  'border' => 'rgba(34,197,94,.30)', 'color' => '#15803d', 'icon' => '✓'],
                                        'overdue'  => ['bg' => 'rgba(239,68,68,.10)',  'border' => 'rgba(239,68,68,.40)', 'color' => '#b91c1c', 'icon' => '🔴'],
                                        'soon'     => ['bg' => 'rgba(245,158,11,.10)', 'border' => 'rgba(245,158,11,.35)','color' => '#b45309', 'icon' => '⏰'],
                                        default    => ['bg' => 'var(--surface2)',      'border' => 'var(--border)',       'color' => 'var(--text2)','icon' => '📅'],
                                    };
                                @endphp
                                <div style="display:flex;align-items:center;gap:12px;padding:10px 12px;background:{{ $stateConfig['bg'] }};border:1px solid {{ $stateConfig['border'] }};border-radius:10px">
                                    <div style="font-size:18px">{{ $stateConfig['icon'] }}</div>
                                    <div style="flex:1;min-width:0">
                                        <div style="font-size:13px;font-weight:700;color:{{ $stateConfig['color'] }}">
                                            {{ $s->label ?? 'Échéance' }}
                                            <span style="font-weight:400;color:var(--text3);margin-left:6px;font-size:11.5px">— {{ $s->due_date->format('d/m/Y') }}</span>
                                            @if($state === 'overdue')
                                                <span style="background:rgba(239,68,68,.18);color:#b91c1c;padding:1px 7px;border-radius:6px;font-size:9.5px;font-weight:800;margin-left:6px">RETARD {{ abs($s->daysUntilDue()) }}j</span>
                                            @elseif($state === 'soon' && !$s->isPaid())
                                                <span style="background:rgba(245,158,11,.15);color:#b45309;padding:1px 7px;border-radius:6px;font-size:9.5px;font-weight:800;margin-left:6px">DANS {{ $s->daysUntilDue() }}j</span>
                                            @elseif($state === 'paid')
                                                <span style="background:rgba(34,197,94,.15);color:#15803d;padding:1px 7px;border-radius:6px;font-size:9.5px;font-weight:800;margin-left:6px">PAYÉE LE {{ $s->paid_at->format('d/m') }}</span>
                                            @endif
                                        </div>
                                        <div style="font-size:11.5px;color:var(--text3);margin-top:2px;font-weight:700">
                                            {{ $fmt($s->amount) }} FCFA
                                            @if($s->reminder_count > 0)
                                                · 🔔 {{ $s->reminder_count }} relance{{ $s->reminder_count > 1 ? 's' : '' }}
                                            @endif
                                        </div>
                                    </div>
                                    @can('markPaid', $invoice)
                                        @if($s->isPaid())
                                            <form method="POST" action="{{ route('admin.invoices.schedule.unpay', [$invoice, $s]) }}">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="btn btn-ghost btn-sm" style="font-size:11px" title="Marquer comme non payée">↩ Annuler</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.invoices.schedule.pay', [$invoice, $s]) }}">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="btn btn-ghost btn-sm" style="color:#16a34a;font-size:11px" title="Marquer payée">✓ Payée</button>
                                            </form>
                                        @endif
                                    @endcan
                                </div>
                            @endforeach
                        </div>
                        @can('markPaid', $invoice)
                            <form method="POST" action="{{ route('admin.invoices.schedule.delete', $invoice) }}"
                                  style="margin-top:10px;text-align:right"
                                  onsubmit="return confirm('Supprimer tout l\'échéancier ?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--text3);font-size:11px">🗑 Supprimer l'échéancier</button>
                            </form>
                        @endcan
                    @endif
                </div>
            </div>

            {{-- Modal configuration échéancier --}}
            @can('markPaid', $invoice)
            <div class="modal-overlay" id="modal-schedule" role="dialog">
                <div class="modal" style="max-width:540px">
                    <div class="modal-header">
                        <h3>📅 Configurer l'échéancier</h3>
                        <button type="button" onclick="document.getElementById('modal-schedule').classList.remove('show')" class="modal-close">×</button>
                    </div>
                    <form method="POST" action="{{ route('admin.invoices.schedule.generate', $invoice) }}" id="schedule-form">
                        @csrf
                        <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">
                            @php
                                // L'échéancier ne planifie QUE le solde restant dû — pas le total
                                // brut. Si la facture a déjà reçu des acomptes, on ne re-planifie pas
                                // ce qui est déjà encaissé. Cohérent avec ScheduleGenerator côté
                                // backend (cf. utilisation de remainingAmount()).
                                $schedTotalBrut = (int) ($invoice->total_a_payer ?: $invoice->amount_ttc);
                                $schedPaid      = (int) round($invoice->paidAmount());
                                $schedRemaining = (int) round($invoice->remainingAmount());
                            @endphp
                            <div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 12px;font-size:12.5px">
                                @if($schedPaid > 0)
                                    Total facture : <strong>{{ $fmt($schedTotalBrut) }} FCFA</strong><br>
                                    <span style="color:var(--text3)">Déjà encaissé : {{ $fmt($schedPaid) }} FCFA</span><br>
                                    Solde à planifier : <strong id="sched-total" data-total="{{ $schedRemaining }}" style="color:var(--accent)">{{ $fmt($schedRemaining) }} FCFA</strong>
                                @else
                                    Total à payer : <strong id="sched-total" data-total="{{ $schedRemaining }}">{{ $fmt($schedRemaining) }} FCFA</strong>
                                @endif
                            </div>

                            {{-- ═══ Choix du mode (Phase 3 — cahier §6) ═══ --}}
                            <div class="mfg">
                                <label>Mode d'échéancier <span style="color:var(--red)">*</span></label>
                                <select name="mode" id="sched-mode" required onchange="window.schedSwitchMode(this.value)">
                                    <optgroup label="Modes principaux (cahier §6)">
                                        <option value="custom_milestones">🎯 Personnalisé par jalon (acompte signature, solde livraison…)</option>
                                        <option value="quarterly">📅 Trimestriel calendaire (01/01, 01/04, 01/07, 01/10)</option>
                                        <option value="monthly">🗓 Mensuel (N échéances libres)</option>
                                    </optgroup>
                                    <optgroup label="Presets rapides">
                                        <option value="30_70">🅰 Acompte 30 % + Solde 70 % à J+30</option>
                                        <option value="50_50">🅱 Acompte 50 % + Solde 50 % à J+30</option>
                                        <option value="monthly_3">🅲 3 mensualités (J0, J+30, J+60)</option>
                                    </optgroup>
                                </select>
                            </div>

                            {{-- Bloc date de départ : visible sauf en custom_milestones --}}
                            <div class="mfg" id="sched-block-startdate">
                                <label>Date de départ <span style="color:var(--red)">*</span></label>
                                <input type="date" name="start_date" value="{{ date('Y-m-d') }}">
                            </div>

                            {{-- Bloc nombre de mensualités : visible uniquement en mode 'monthly' --}}
                            <div class="mfg" id="sched-block-count" style="display:none">
                                <label>Nombre de mensualités <span style="color:var(--red)">*</span> <span style="color:var(--text3);font-weight:400;font-size:11px">(entre 2 et 24)</span></label>
                                <input type="number" name="count" min="2" max="24" value="6">
                            </div>

                            {{-- Bloc jalons : visible uniquement en mode 'custom_milestones' --}}
                            <div id="sched-block-milestones" style="display:none">
                                <label style="font-size:11px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;display:block">Jalons libres (le total doit égaler le total à payer)</label>
                                <div id="milestones-tbody" style="display:flex;flex-direction:column;gap:8px"></div>
                                <button type="button" onclick="window.schedAddMilestone()" class="btn btn-ghost btn-sm" style="margin-top:8px;font-size:11px">+ Ajouter un jalon</button>
                                <div id="milestones-sum-bar" style="margin-top:10px;padding:8px 12px;background:var(--surface2);border-radius:8px;font-size:11.5px;display:flex;justify-content:space-between">
                                    <span style="color:var(--text3)">Total des jalons</span>
                                    <span id="milestones-sum" style="font-weight:800">0 FCFA</span>
                                </div>
                            </div>

                            @if($schedules->isNotEmpty())
                                <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.3);border-radius:8px;padding:10px 12px;font-size:11.5px;color:#b45309">
                                    ⚠ L'échéancier existant ({{ $schedules->count() }} échéances) sera REMPLACÉ.
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-schedule').classList.remove('show')">Annuler</button>
                            <button type="submit" class="btn btn-primary">✅ Générer l'échéancier</button>
                        </div>
                    </form>

                    <script>
                    (function() {
                        const fmt = n => Number(n).toLocaleString('fr-FR') + ' FCFA';
                        const tbody = document.getElementById('milestones-tbody');

                        // Helper : désactive tous les <input>/<select> d'un
                        // bloc caché pour qu'ils ne soient PAS envoyés au form
                        // ET ne déclenchent PAS la validation HTML5 native.
                        // Bug avant : un milestone créé puis caché restait
                        // 'required' → le navigateur refusait la soumission
                        // sur un input invisible, en silence côté UI.
                        function setBlockEnabled(block, enabled) {
                            block.style.display = enabled ? '' : 'none';
                            block.querySelectorAll('input, select, textarea').forEach(el => {
                                el.disabled = !enabled;
                            });
                        }

                        window.schedSwitchMode = function(mode) {
                            const start = document.getElementById('sched-block-startdate');
                            const count = document.getElementById('sched-block-count');
                            const mile  = document.getElementById('sched-block-milestones');
                            // start visible pour tous SAUF custom_milestones
                            setBlockEnabled(start, mode !== 'custom_milestones');
                            // count visible UNIQUEMENT pour monthly
                            setBlockEnabled(count, mode === 'monthly');
                            // milestones visible UNIQUEMENT pour custom_milestones
                            setBlockEnabled(mile,  mode === 'custom_milestones');

                            if (mode === 'custom_milestones' && tbody.children.length === 0) {
                                window.schedAddMilestone();
                                window.schedAddMilestone();
                            }
                        };

                        let nextMileIdx = 0;
                        window.schedAddMilestone = function() {
                            const i = nextMileIdx++;
                            const row = document.createElement('div');
                            row.className = 'milestone-row';
                            row.style.cssText = 'display:grid;grid-template-columns:1fr 130px 140px 36px;gap:8px;align-items:center;padding:8px;background:var(--surface);border:1px solid var(--border);border-radius:8px';
                            row.innerHTML = `
                                <input type="text" name="milestones[${i}][label]" placeholder="Ex: Acompte signature" maxlength="100"
                                       style="padding:7px 10px;border:1px solid var(--border);border-radius:7px;font-size:12.5px;background:var(--surface2)">
                                <input type="date" name="milestones[${i}][due_date]" required
                                       style="padding:7px 10px;border:1px solid var(--border);border-radius:7px;font-size:12.5px;background:var(--surface2)">
                                <input type="number" name="milestones[${i}][amount]" placeholder="Montant" min="1" step="1" required
                                       class="milestone-amount"
                                       style="padding:7px 10px;border:1px solid var(--border);border-radius:7px;font-size:12.5px;background:var(--surface2);text-align:right">
                                <button type="button" onclick="window.schedRemoveMilestone(this)" class="btn btn-ghost btn-sm" style="color:#ef4444;font-weight:700;padding:4px 8px">✕</button>
                            `;
                            tbody.appendChild(row);
                            row.querySelector('.milestone-amount').addEventListener('input', window.schedRecomputeMilestoneSum);
                            window.schedRecomputeMilestoneSum();
                        };

                        window.schedRemoveMilestone = function(btn) {
                            btn.closest('.milestone-row')?.remove();
                            window.schedRecomputeMilestoneSum();
                        };

                        window.schedRecomputeMilestoneSum = function() {
                            const sum = Array.from(document.querySelectorAll('.milestone-amount'))
                                .reduce((acc, el) => acc + (parseInt(el.value, 10) || 0), 0);
                            const total = parseInt(document.getElementById('sched-total')?.dataset.total, 10) || 0;
                            const bar = document.getElementById('milestones-sum');
                            bar.textContent = fmt(sum);
                            const diff = Math.abs(sum - total);
                            bar.style.color = (sum === 0 || diff > 1) ? '#b91c1c' : '#16a34a';
                        };

                        // Init mode par défaut
                        // Init synchrone : si DOMContentLoaded est déjà passé
                        // (cas où le script est dans le <body>), on init
                        // immédiatement le mode par défaut. Sinon, on attend.
                        const initSchedMode = () => {
                            const sel = document.getElementById('sched-mode');
                            if (sel) window.schedSwitchMode(sel.value);
                        };
                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', initSchedMode);
                        } else {
                            initSchedMode();
                        }
                    })();
                    </script>
                </div>
            </div>
            @endcan

            {{-- Modal ajout versement --}}
            @can('markPaid', $invoice)
            <div class="modal-overlay" id="modal-add-payment" role="dialog">
                <div class="modal">
                    <div class="modal-header">
                        <h3>💸 Enregistrer un versement</h3>
                        <button type="button" onclick="document.getElementById('modal-add-payment').classList.remove('show')" class="modal-close">×</button>
                    </div>
                    <form method="POST" action="{{ route('admin.invoices.payments.add', $invoice) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">
                            <div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 12px;font-size:12px">
                                <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                                    <span style="color:var(--text3)">Total dû</span>
                                    <span style="font-weight:700;">{{ $fmt($invoice->total_a_payer ?: $invoice->amount_ttc) }} FCFA</span>
                                </div>
                                <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                                    <span style="color:var(--text3)">Déjà payé</span>
                                    <span style="font-weight:700;color:#16a34a;">{{ $fmt($paid) }} FCFA</span>
                                </div>
                                <div style="display:flex;justify-content:space-between;padding-top:6px;border-top:1px dashed var(--border)">
                                    <span style="color:var(--text2);font-weight:700">Reste à payer</span>
                                    <span style="font-weight:800;color:var(--accent);">{{ $fmt($remaining) }} FCFA</span>
                                </div>
                            </div>

                            <div>
                                <label style="display:block;font-size:11px;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Montant (FCFA)</label>
                                <input type="number" name="montant" step="1" min="1" required
                                       value="{{ $remaining > 0 ? round($remaining) : '' }}"
                                       style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--surface);font-size:14px;font-weight:700;">
                            </div>

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                                <div>
                                    <label style="display:block;font-size:11px;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Date</label>
                                    <input type="date" name="paid_at" required value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}"
                                           style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--surface);font-size:13px">
                                </div>
                                <div>
                                    <label style="display:block;font-size:11px;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Mode</label>
                                    <select name="mode" required style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--surface);font-size:13px">
                                        <option value="virement">🏦 Virement</option>
                                        <option value="cheque">📝 Chèque</option>
                                        <option value="mobile_money">📱 Mobile money</option>
                                        <option value="carte_bancaire">💳 Carte bancaire</option>
                                        <option value="especes">💵 Espèces</option>
                                        <option value="compensation">🔄 Compensation (avoir)</option>
                                        <option value="autre">💰 Autre</option>
                                    </select>
                                </div>
                            </div>

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                                <div>
                                    <label style="display:block;font-size:11px;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Référence <span style="color:var(--text3);font-weight:400">(facultatif)</span></label>
                                    <input type="text" name="reference" maxlength="100" placeholder="N° chèque, ID transaction…"
                                           style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--surface);font-size:13px">
                                </div>
                                <div>
                                    <label style="display:block;font-size:11px;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Banque <span style="color:var(--text3);font-weight:400">(chèque/virement)</span></label>
                                    <input type="text" name="bank" maxlength="100" placeholder="SGCI, BICICI, UBA…"
                                           style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--surface);font-size:13px">
                                </div>
                            </div>

                            {{-- Acompte (§5 cahier) + Pièce jointe (§4 cahier) --}}
                            <div style="display:flex;flex-direction:column;gap:10px;padding:10px 12px;background:var(--surface2);border:1px dashed var(--border);border-radius:9px">
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:12.5px;color:var(--text2)">
                                    <input type="checkbox" name="is_acompte" value="1" style="width:16px;height:16px;cursor:pointer">
                                    <span><strong>Marquer comme acompte</strong> <span style="color:var(--text3);font-size:11px">— pour le suivi des paiements partiels avant solde</span></span>
                                </label>
                                <div>
                                    <label style="display:block;font-size:11px;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Pièce justificative <span style="color:var(--text3);font-weight:400">(scan chèque, reçu — PDF/PNG/JPG, max 5 Mo)</span></label>
                                    <input type="file" name="attachment" accept="application/pdf,image/png,image/jpeg,image/webp"
                                           style="width:100%;padding:8px;border:1px solid var(--border);border-radius:8px;background:var(--surface);font-size:12px">
                                </div>
                            </div>

                            <div>
                                <label style="display:block;font-size:11px;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Note <span style="color:var(--text3);font-weight:400">(facultatif)</span></label>
                                <textarea name="note" rows="2" maxlength="1000" placeholder="Contexte, mention compta…"
                                          style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--surface);font-size:13px;resize:vertical"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-add-payment').classList.remove('show')">Annuler</button>
                            <button type="submit" class="btn btn-primary">✅ Enregistrer le versement</button>
                        </div>
                    </form>
                </div>
            </div>
            @endcan
        @endif

        {{-- CARD CAMPAGNE : détails + bilan facturation si liée --}}
        @if($invoice->campaign)
            @php $c = $invoice->campaign; @endphp
            <div class="card">
                <div class="card-header">
                    <div class="card-title">📢 Campagne liée</div>
                    <a href="{{ route('admin.campaigns.show', $c) }}" class="btn btn-ghost btn-sm">
                        Ouvrir la campagne →
                    </a>
                </div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px">
                        <div>
                            <div style="font-size:11px;color:var(--text3);margin-bottom:4px">PÉRIODE</div>
                            <div style="font-weight:700;font-size:13px">
                                @if($c->start_date && $c->end_date)
                                    {{ $c->start_date->format('d/m/Y') }}<br>
                                    <span style="color:var(--text3)">→</span> {{ $c->end_date->format('d/m/Y') }}
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:var(--text3);margin-bottom:4px">PANNEAUX</div>
                            <div style="font-weight:700;font-size:16px">{{ $c->total_panels ?? '—' }}</div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:var(--text3);margin-bottom:4px">STATUT</div>
                            @php $sc = $c->status?->uiConfig() ?? ['color'=>'#6b7280','bg'=>'rgba(107,114,128,.10)','border'=>'rgba(107,114,128,.30)','icon'=>'•']; @endphp
                            <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;background:{{ $sc['bg'] }};color:{{ $sc['color'] }};border:1px solid {{ $sc['border'] }};font-size:12px;font-weight:700">
                                {{ $sc['icon'] }} {{ $c->status?->label() ?? '—' }}
                            </span>
                        </div>
                        @if($c->reservation)
                            <div>
                                <div style="font-size:11px;color:var(--text3);margin-bottom:4px">RÉSERVATION</div>
                                <a href="{{ route('admin.reservations.show', $c->reservation->id) }}"
                                   style="font-weight:700;color:var(--accent);text-decoration:none;font-size:13px"
                                   title="Ouvrir la réservation source">
                                    {{ $c->reservation->reference }} ↗
                                </a>
                            </div>
                        @endif
                    </div>

                    {{-- Bilan facturation campagne --}}
                    @if($campaignBilling && $campaignBilling['expected_ht'] > 0)
                        @php
                            $pctBilled = min(100, round($campaignBilling['billed_ht'] / $campaignBilling['expected_ht'] * 100));
                        @endphp
                        <div style="margin-top:18px;padding:14px;background:var(--surface2);border:1px solid var(--border);border-radius:10px">
                            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:8px;color:var(--text2)">
                                <span>Facturation campagne</span>
                                <span><strong>{{ $pctBilled }}%</strong> · {{ number_format($campaignBilling['billed_ht'], 0, ',', ' ') }} / {{ number_format($campaignBilling['expected_ht'], 0, ',', ' ') }} HT</span>
                            </div>
                            <div style="height:8px;background:#f1f5f9;border-radius:999px;overflow:hidden">
                                <div style="height:100%;width:{{ $pctBilled }}%;background:linear-gradient(90deg,#3aa835,#22c55e);transition:width .4s"></div>
                            </div>
                            @if($campaignBilling['remaining_ht'] > 0)
                                <div style="margin-top:8px;font-size:11.5px;color:var(--text3)">
                                    Reste à facturer :
                                    <strong style="color:var(--accent)">{{ number_format($campaignBilling['remaining_ht'], 0, ',', ' ') }} FCFA HT</strong>
                                    <a href="{{ route('admin.invoices.create', ['campaign_id' => $c->id]) }}"
                                       style="margin-left:8px;color:var(--accent);font-weight:700;text-decoration:none">
                                        ➕ Facture complémentaire
                                    </a>
                                </div>
                            @endif
                        </div>

                        {{-- ⚠ Drift detection : la campagne a changé après
                             l'émission de la facture (override admin OU
                             modification des dates). Le montant attendu
                             aujourd'hui diffère du montant facturé. On
                             expose le delta pour décision admin. --}}
                        @if(!empty($billingDrift))
                            @php
                                $bd = $billingDrift;
                                $sign = $bd['diff'] > 0 ? '+' : '−';
                                $abs  = number_format(abs($bd['diff']), 0, ',', ' ');
                                $inv  = number_format($bd['invoice_amount_ht'], 0, ',', ' ');
                                $exp  = number_format($bd['expected_now_ht'], 0, ',', ' ');
                            @endphp
                            <div style="margin-top:14px;padding:12px 14px;background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.3);border-radius:10px;font-size:12.5px;line-height:1.5;color:#9a3412">
                                <strong>⚠ Écart facturé / attendu</strong>
                                @if($bd['overridden_after'])
                                    — un <em>montant manuel</em> a été appliqué sur la campagne après l'émission.
                                @else
                                    — la campagne a été modifiée après l'émission de cette facture.
                                @endif
                                <div style="margin-top:6px;color:#7c2d12">
                                    Facturé : <strong>{{ $inv }} FCFA HT</strong>
                                    · Attendu aujourd'hui : <strong>{{ $exp }} FCFA HT</strong>
                                    · Delta : <strong>{{ $sign }}{{ $abs }} FCFA</strong>
                                </div>
                                <div style="margin-top:4px;color:#92400e;font-size:11.5px">
                                    Décide : annuler cette facture et en émettre une nouvelle, ou conserver
                                    en l'état (avec une note interne).
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- ════════════════════ COLONNE DROITE ════════════════════ --}}
    <div style="display:flex;flex-direction:column;gap:16px">

        {{-- Card client enrichie --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">🏢 Client</div>
                @if($invoice->client)
                    <a href="{{ route('admin.clients.show', $invoice->client) }}" class="btn btn-ghost btn-sm">Fiche →</a>
                @endif
            </div>
            <div class="card-body">
                @if($invoice->client)
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <div>
                            <div style="font-size:11px; color:var(--text3);">NOM</div>
                            <a href="{{ route('admin.clients.show', $invoice->client) }}"
                               style="font-weight:700;color:var(--text);text-decoration:none">
                                {{ $invoice->client->name }}
                            </a>
                        </div>
                        @if($invoice->client->email)
                        <div>
                            <div style="font-size:11px; color:var(--text3);">EMAIL</div>
                            <a href="mailto:{{ $invoice->client->email }}"
                               style="font-size:12.5px;color:var(--accent);text-decoration:none">
                                {{ $invoice->client->email }}
                            </a>
                        </div>
                        @endif
                        @if($invoice->client->phone)
                        <div>
                            <div style="font-size:11px; color:var(--text3);">TÉLÉPHONE</div>
                            <a href="tel:{{ $invoice->client->phone }}" style="color:var(--text);text-decoration:none">
                                {{ $invoice->client->phone }}
                            </a>
                        </div>
                        @endif
                    </div>

                    @if($clientStats)
                        <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border);display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:11.5px">
                            <div>
                                <div style="color:var(--text3);font-size:10px;text-transform:uppercase;letter-spacing:.5px">Factures totales</div>
                                <div style="font-weight:800;font-size:14px;color:var(--text)">{{ $clientStats['count_total'] }}</div>
                            </div>
                            <div>
                                <div style="color:var(--text3);font-size:10px;text-transform:uppercase;letter-spacing:.5px">Payées</div>
                                <div style="font-weight:800;font-size:14px;color:#22c55e">{{ $clientStats['count_paid'] }}</div>
                            </div>
                            <div>
                                <div style="color:var(--text3);font-size:10px;text-transform:uppercase;letter-spacing:.5px">CA payé TTC</div>
                                <div style="font-weight:800;font-size:13px;color:var(--accent)">{{ number_format($clientStats['sum_paid_ttc'], 0, ',', ' ') }}</div>
                            </div>
                            <div>
                                <div style="color:var(--text3);font-size:10px;text-transform:uppercase;letter-spacing:.5px">En attente TTC</div>
                                <div style="font-weight:800;font-size:13px;color:#f59e0b">{{ number_format($clientStats['sum_pending_ttc'], 0, ',', ' ') }}</div>
                            </div>
                        </div>
                    @endif
                @else
                    <div style="color:var(--text3);font-size:13px">—</div>
                @endif
            </div>
        </div>

        {{-- Autres factures du même client --}}
        @if($otherInvoices->isNotEmpty())
            <div class="card">
                <div class="card-header">
                    <div class="card-title" style="font-size:13px">📋 Autres factures du client</div>
                    <a href="{{ route('admin.invoices.index', ['client_id' => $invoice->client_id]) }}"
                       class="btn btn-ghost btn-sm" style="font-size:11px">
                        Toutes ({{ $clientStats['count_total'] ?? 0 }}) →
                    </a>
                </div>
                <div class="card-body" style="padding:6px">
                    @foreach($otherInvoices as $oi)
                        @php
                            $bg = match($oi->status) {
                                'payee'     => 'rgba(34,197,94,.06)',
                                'envoyee'   => 'rgba(59,130,246,.06)',
                                'brouillon' => 'var(--surface2)',
                                'annulee'   => 'rgba(239,68,68,.04)',
                                default     => 'transparent',
                            };
                            $colDot = match($oi->status) {
                                'payee'     => '#22c55e',
                                'envoyee'   => '#3b82f6',
                                'brouillon' => '#9ca3af',
                                'annulee'   => '#ef4444',
                                default     => '#6b7280',
                            };
                        @endphp
                        <a href="{{ route('admin.invoices.show', $oi) }}"
                           style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;border-radius:8px;background:{{ $bg }};text-decoration:none;color:var(--text);margin-bottom:4px;transition:transform .12s"
                           onmouseover="this.style.transform='translateX(2px)'"
                           onmouseout="this.style.transform=''">
                            <div style="min-width:0;flex:1">
                                <div style="font-weight:700;font-size:12px;color:var(--accent)">
                                    <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:{{ $colDot }};margin-right:5px"></span>
                                    {{ $oi->reference }}
                                </div>
                                <div style="font-size:10.5px;color:var(--text3);margin-top:1px">
                                    {{ $oi->issued_at->format('d/m/Y') }}
                                </div>
                            </div>
                            <div style="text-align:right">
                                <div style="font-weight:700;font-size:12.5px;color:var(--text)">
                                    {{ number_format($oi->amount_ttc, 0, ',', ' ') }}
                                </div>
                                <div style="font-size:10px;color:var(--text3)">FCFA TTC</div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

</div>

</x-admin-layout>
