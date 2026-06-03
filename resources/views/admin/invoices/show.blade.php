<x-admin-layout>
<x-slot name="title">{{ $invoice->reference }}</x-slot>

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

                {{-- MONTANTS --}}
                <div style="margin-top:20px; padding:16px; background:var(--surface2);
                            border-radius:10px; border:1px solid var(--border);">
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                        <span style="color:var(--text3);">Montant HT</span>
                        <span style="font-weight:600;">
                            {{ number_format($invoice->amount, 0, ',', ' ') }} FCFA
                        </span>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                        <span style="color:var(--text3);">TVA ({{ rtrim(rtrim(number_format($invoice->tva, 2, ',', ''), '0'), ',') }}%)</span>
                        <span style="font-weight:600;">
                            {{ number_format($invoice->amount * $invoice->tva / 100, 0, ',', ' ') }} FCFA
                        </span>
                    </div>
                    <div style="display:flex; justify-content:space-between;
                                padding-top:10px; border-top:1px solid var(--border);">
                        <span style="font-weight:700; font-size:14px;">TOTAL TTC</span>
                        <span style="font-weight:800; font-size:18px; color:var(--accent);">
                            {{ number_format($invoice->amount_ttc, 0, ',', ' ') }} FCFA
                        </span>
                    </div>
                </div>

                @if($invoice->paid_at)
                <div style="margin-top:16px; padding:12px; background:rgba(34,197,94,.1);
                            border:1px solid rgba(34,197,94,.3); border-radius:8px;">
                    <div style="color:var(--green); font-weight:600; font-size:12px;">
                        ✅ Payée le {{ $invoice->paid_at->format('d/m/Y') }}
                    </div>
                </div>
                @endif
            </div>
        </div>

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
                                   style="font-family:monospace;font-weight:700;color:var(--accent);text-decoration:none;font-size:13px"
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
                                <div style="font-family:monospace;font-weight:700;font-size:12px;color:var(--accent)">
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
