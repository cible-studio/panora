<x-admin-layout title="Réservation {{ $reservation->reference }}">

{{-- ══════════════════════════════════════════════════════
     TOPBAR ACTIONS
══════════════════════════════════════════════════════ --}}
<x-slot:topbarActions>
    <a href="{{ route('admin.reservations.index') }}"
       class="btn btn-ghost text-sm">← Retour aux réservations</a>

    @if($can['update'])
        <a href="{{ route('admin.reservations.edit', $reservation) }}"
           class="btn btn-ghost text-sm">✏️ Modifier</a>
    @endif

    @if($can['delete'])
        <form method="POST"
              action="{{ route('admin.reservations.destroy', $reservation) }}"
              onsubmit="return confirm('Supprimer définitivement cette réservation ?')">
            @csrf @method('DELETE')
            <button class="btn btn-danger text-sm">🗑️ Supprimer</button>
        </form>
    @endif
</x-slot>

{{-- ══════════════════════════════════════════════════════
     ALERTE CLIENT SUPPRIMÉ
══════════════════════════════════════════════════════ --}}
@if($reservation->client?->trashed())
<div class="mx-0 mb-4 px-4 py-3 rounded-lg border"
     style="background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.25)">
    <div class="flex items-center gap-2">
        <span style="color:var(--red)">⚠️</span>
        <span class="text-sm font-medium" style="color:var(--red)">
            Client supprimé — cette réservation est conservée à titre d'historique uniquement.
            Aucune modification n'est possible.
        </span>
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════
     EN-TÊTE RÉSERVATION
══════════════════════════════════════════════════════ --}}
<div class="card mb-4">
    <div class="card-body">

        {{-- Référence + Badge statut --}}
        <div class="flex items-center gap-3 mb-5">
            <span class="font-mono font-bold text-base px-3 py-1 rounded"
                  style="background:var(--surface2);color:var(--text)">
                {{ $reservation->reference }}
            </span>
            <span class="badge badge-{{ $reservation->status->badgeClass() }}">
                {{ $reservation->status->label() }}
            </span>
            <span class="badge" style="background:var(--surface2);color:var(--text2)">
                {{ $reservation->type === 'ferme' ? '🔒 Ferme' : '⏳ Option' }}
            </span>
            @if($reservation->status->value === 'annule')
                <span class="text-xs px-2 py-1 rounded"
                      style="background:rgba(239,68,68,.1);color:var(--red)">
                    🗄️ Archivé — lecture seule
                </span>
            @endif
        </div>

        {{-- Infos principales --}}
        <div class="grid grid-cols-3 gap-6">
            <div>
                <div class="text-xs uppercase tracking-wider mb-1" style="color:var(--text3)">Client</div>
                @if($reservation->client?->trashed())
                    <div class="font-semibold" style="color:var(--text2)">
                        {{ $reservation->client->name }}
                        <span class="text-xs ml-1 px-1.5 py-0.5 rounded"
                              style="background:rgba(239,68,68,.1);color:var(--red)">Supprimé</span>
                    </div>
                @else
                    <a href="{{ route('admin.clients.show', $reservation->client) }}"
                       class="font-semibold hover:underline" style="color:var(--text)">
                        {{ $reservation->client?->name ?? '—' }}
                    </a>
                @endif
            </div>
            <div>
                <div class="text-xs uppercase tracking-wider mb-1" style="color:var(--text3)">Période</div>
                <div class="font-semibold">
                    {{ $reservation->start_date->format('d/m/Y') }}
                    → {{ $reservation->end_date->format('d/m/Y') }}
                </div>
                <div class="text-xs mt-0.5" style="color:var(--text2)">
                    {{ $reservation->start_date->diffInDays($reservation->end_date) }} jours
                </div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wider mb-1" style="color:var(--text3)">Montant total</div>
                <div class="font-bold text-lg" style="color:var(--accent)">
                    {{ number_format($reservation->total_amount, 0, ',', ' ') }} FCFA
                </div>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-6 mt-4 pt-4"
             style="border-top:1px solid var(--border)">
            <div>
                <div class="text-xs uppercase tracking-wider mb-1" style="color:var(--text3)">Créée par</div>
                <div class="text-sm">{{ $reservation->user?->name ?? '—' }}</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wider mb-1" style="color:var(--text3)">Date confirmation</div>
                <div class="text-sm">
                    {{ $reservation->confirmed_at?->format('d/m/Y H:i') ?? '—' }}
                </div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wider mb-1" style="color:var(--text3)">Campagne liée</div>
                @if($reservation->campaign)
                    <a href="{{ route('admin.campaigns.show', $reservation->campaign) }}"
                    class="text-sm font-medium hover:underline" style="color:var(--accent)">
                        {{ $reservation->campaign->name }} →
                    </a>
                @elseif($reservation->status->value === 'confirme')
                    <a href="{{ route('admin.campaigns.create', ['reservation_id' => $reservation->id]) }}"
                    class="btn btn-ghost btn-sm" style="font-size:11px;color:var(--green);
                            border-color:rgba(34,197,94,0.3);">
                        + Créer une campagne
                    </a>
                @else
                    <span class="text-sm" style="color:var(--text3)">
                        Disponible après confirmation
                    </span>
                @endif
            </div>
        </div>

        {{-- ✅ Motif d'annulation (si annulée avec motif documenté) --}}
        @if($reservation->status->value === 'annule' && ($reservation->cancel_reason ?? null))
        <div class="mt-4 pt-4" style="border-top:1px solid var(--border)">
            <div class="text-xs uppercase tracking-wider mb-2" style="color:var(--text3)">Motif d'annulation</div>
            <div style="background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);border-radius:10px;padding:12px 14px">
                <div class="flex items-center gap-2 mb-1">
                    <span style="font-size:12px;font-weight:600;color:#ef4444">
                        @php
                            $cancelLabels = [
                                'client_demande' => 'Client : Demande d\'annulation',
                                'budget'         => 'Client : Contrainte budgétaire',
                                'concurrent'     => 'Client : A choisi un concurrent',
                                'report'         => 'Report de campagne',
                                'autre'          => 'Autre motif',
                            ];
                        @endphp
                        {{ $cancelLabels[$reservation->cancel_type ?? 'autre'] ?? 'Autre motif' }}
                    </span>
                    @if($reservation->cancelled_at)
                        <span style="font-size:10px;color:var(--text3)">
                            · {{ $reservation->cancelled_at->format('d/m/Y à H:i') }}
                            @if($reservation->cancelledByUser)
                                par {{ $reservation->cancelledByUser->name }}
                            @endif
                        </span>
                    @endif
                </div>
                <div style="font-size:12px;color:var(--text2)">{{ $reservation->cancel_reason }}</div>
            </div>
        </div>
        @endif

        @if($reservation->notes)
        <div class="mt-4 pt-4" style="border-top:1px solid var(--border)">
            <div class="text-xs uppercase tracking-wider mb-1" style="color:var(--text3)">Notes</div>
            <p class="text-sm" style="color:var(--text2)">{{ $reservation->notes }}</p>
        </div>
        @endif
    </div>
</div>

@include('admin.reservations.partials.proposition-actions', ['reservation' => $reservation])

{{-- ══════════════════════════════════════════════════════
     VISUEL PANNEAUX + PDF (réservation en option)
══════════════════════════════════════════════════════ --}}
@if($reservation->status->value === 'en_attente')
<div class="card mb-4">
    <div class="card-header">
        <div class="card-title">📸 Visuels des panneaux</div>
        <div class="flex items-center gap-3">
            <span class="text-xs" style="color:var(--text3)">
                {{ $reservation->panels->count() }} panneau(x)
            </span>

            {{-- ✅ PDF images avec référence réservation --}}
            <form method="POST"
                  action="{{ route('admin.reservations.disponibilites.pdf-images') }}"
                  target="_blank">
                @csrf
                @foreach($reservation->panels as $p)
                    <input type="hidden" name="panel_ids[]" value="{{ $p->id }}">
                @endforeach
                <input type="hidden" name="start_date"     value="{{ $reservation->start_date->format('Y-m-d') }}">
                <input type="hidden" name="end_date"       value="{{ $reservation->end_date->format('Y-m-d') }}">
                <input type="hidden" name="reservation_ref" value="{{ $reservation->reference }}">
                <input type="hidden" name="client_name"    value="{{ $reservation->client?->name }}">
                <button type="submit"
                        class="btn btn-ghost btn-sm"
                        style="color:var(--red);border-color:rgba(239,68,68,.3)">
                    📋 Exporter PDF images
                </button>
            </form>

            {{-- ✅ PDF liste avec référence réservation --}}
            <form method="POST"
                  action="{{ route('admin.reservations.disponibilites.pdf-liste') }}"
                  target="_blank">
                @csrf
                @foreach($reservation->panels as $p)
                    <input type="hidden" name="panel_ids[]" value="{{ $p->id }}">
                @endforeach
                <input type="hidden" name="start_date"     value="{{ $reservation->start_date->format('Y-m-d') }}">
                <input type="hidden" name="end_date"       value="{{ $reservation->end_date->format('Y-m-d') }}">
                <input type="hidden" name="reservation_ref" value="{{ $reservation->reference }}">
                <input type="hidden" name="client_name"    value="{{ $reservation->client?->name }}">
                <button type="submit"
                        class="btn btn-ghost btn-sm"
                        style="color:var(--blue);border-color:rgba(59,130,246,.3)">
                    📄 Exporter PDF liste
                </button>
            </form>
        </div>
    </div>

    {{-- Grille photos --}}
    <div class="p-4">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;">
            @foreach($reservation->panels as $panel)
            @php $photo = $panel->photos->sortBy('ordre')->first(); @endphp
            <div style="background:var(--surface2);border:1px solid var(--border);border-radius:12px;overflow:hidden;">
                @if($photo)
                    <img src="{{ asset('storage/' . $photo->path) }}"
                         alt="{{ $panel->reference }}"
                         style="width:100%;height:140px;object-fit:cover;display:block;"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <div style="display:none;width:100%;height:140px;background:var(--surface3);align-items:center;justify-content:center;">
                        <span style="font-size:32px;opacity:.3">🪧</span>
                    </div>
                @else
                    <div style="width:100%;height:140px;background:var(--surface3);display:flex;align-items:center;justify-content:center;">
                        <span style="font-size:32px;opacity:.3">🪧</span>
                    </div>
                @endif
                <div style="padding:10px 12px;">
                    <div style="font-family:monospace;font-size:11px;font-weight:700;color:var(--accent);margin-bottom:4px;">
                        {{ $panel->reference }}
                    </div>
                    <div style="font-size:12px;font-weight:600;color:var(--text);margin-bottom:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        {{ $panel->name }}
                    </div>
                    <div style="font-size:11px;color:var(--text3);">
                        📍 {{ $panel->commune?->name ?? '—' }}
                        @if($panel->format?->name) · {{ $panel->format->name }} @endif
                    </div>
                    @php
                        $unitPrice  = (float)($panel->pivot->unit_price  ?? $panel->monthly_rate ?? 0);
                        $totalPrice = (float)($panel->pivot->total_price ?? 0);
                    @endphp
                    @if($unitPrice > 0)
                    <div style="margin-top:8px;padding-top:8px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:10px;color:var(--text3);">Tarif/mois</span>
                        <span style="font-size:13px;font-weight:700;color:var(--accent);">
                            {{ number_format($unitPrice, 0, ',', ' ') }} FCFA
                        </span>
                    </div>
                    @if($totalPrice > 0)
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:4px;">
                        <span style="font-size:10px;color:var(--text3);">Total période</span>
                        <span style="font-size:12px;font-weight:600;color:var(--text2);">
                            {{ number_format($totalPrice, 0, ',', ' ') }} FCFA
                        </span>
                    </div>
                    @endif
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════
     ACTIONS STATUT
══════════════════════════════════════════════════════ --}}
@if($can['updateStatus'] || $can['annuler'])
<div class="card mb-4">
    <div class="card-header">
        <div class="card-title">⚡ Actions</div>
    </div>
    <div class="card-body">
        <div class="flex flex-wrap gap-3">
            @if(in_array('confirme', \App\Models\Reservation::ALLOWED_TRANSITIONS[$reservation->status->value] ?? []) && $can['updateStatus'])
                <button onclick="openStatusModal('confirme')" class="btn btn-success">
                    ✅ Confirmer la réservation
                </button>
            @endif
            @if(in_array('refuse', \App\Models\Reservation::ALLOWED_TRANSITIONS[$reservation->status->value] ?? []) && $can['updateStatus'])
                <button onclick="openStatusModal('refuse')" class="btn btn-danger">
                    ❌ Refuser
                </button>
            @endif
            @if($can['annuler'])
                <button onclick="openCancelModal()" class="btn btn-danger">
                    🚫 Annuler la réservation
                </button>
            @endif
        </div>
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════
     PANNEAUX RÉSERVÉS — avec modification prix
══════════════════════════════════════════════════════ --}}
<div class="card" id="panels-card">
    <div class="card-header">
        <span class="card-title">🪧 Panneaux réservés</span>
        <div class="flex items-center gap-3">
            <span class="text-xs text-gray-400">
                {{ $reservation->panels->count() }} panneau(x)
            </span>
            @if($can['update'])
            <span class="text-xs text-[#e8a020] bg-[#e8a020]/10 px-2 py-1 rounded-lg">
                ✏️ Prix modifiables
            </span>
            @endif
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full border-collapse">
            <thead>
                <tr class="border-b border-[#2a2a35]">
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Référence</th>
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Emplacement</th>
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Commune</th>
                    <th class="text-left p-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Format</th>
                    <th class="text-right p-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Tarif catalogue</th>
                    <th class="text-right p-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Prix négocié</th>
                    <th class="text-right p-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Total période</th>
                    @if($can['update'])<th class="p-3 w-24"></th>@endif
                </tr>
            </thead>
            <tbody id="panels-tbody">
                @foreach($reservation->panels as $panel)
                @php
                    $unitPrice    = (float)($panel->pivot->unit_price  ?? $panel->monthly_rate);
                    $totalPrice   = (float)($panel->pivot->total_price ?? 0);
                    $catalogue    = (float)($panel->monthly_rate ?? 0);
                    $isPriceModif = abs($unitPrice - $catalogue) > 0.01;
                @endphp
                <tr class="border-b border-[#1e1e2e]" id="panel-row-{{ $panel->id }}">
                    <td class="p-3">
                        <span class="font-mono text-xs font-bold px-2 py-1 rounded-lg bg-[#e8a020]/10 text-[#e8a020]">
                            {{ $panel->reference }}
                        </span>
                    </td>
                    <td class="p-3">
                        <div class="text-sm font-medium text-gray-500">{{ $panel->name }}</div>
                        @if($panel->zone_description)
                        <div class="text-xs text-gray-500 mt-0.5 truncate max-w-[180px]">📍 {{ $panel->zone_description }}</div>
                        @endif
                    </td>
                    <td class="p-3 text-sm text-gray-400">{{ $panel->commune?->name ?? '—' }}</td>
                    <td class="p-3 text-sm text-gray-500">
                        {{ $panel->format?->name ?? '—' }}
                        @if($panel->format?->width && $panel->format?->height)
                        <div class="text-xs text-gray-600">{{ $panel->format->width }}×{{ $panel->format->height }}m</div>
                        @endif
                    </td>
                    <td class="p-3 text-right">
                        <span class="text-sm text-gray-500">{{ number_format($catalogue, 0, ',', ' ') }} FCFA</span>
                    </td>
                    <td class="p-3 text-right">
                        <div id="price-display-{{ $panel->id }}" class="flex items-center justify-end gap-2">
                            <span class="font-bold {{ $isPriceModif ? 'text-green-400' : 'text-[#e8a020]' }} text-sm">
                                {{ number_format($unitPrice, 0, ',', ' ') }} FCFA
                            </span>
                            @if($isPriceModif)
                            <span class="text-xs bg-green-500/10 text-green-400 border border-green-500/20 px-1.5 py-0.5 rounded-md" title="Prix modifié">✓ négocié</span>
                            @endif
                            @if($can['update'])
                            <button type="button" onclick="showPriceEdit({{ $panel->id }}, {{ $unitPrice }}, {{ $catalogue }})"
                                    class="ml-1 p-1 rounded-lg text-gray-600 hover:text-[#e8a020] hover:bg-[#e8a020]/10 transition-all" title="Modifier">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                </svg>
                            </button>
                            @endif
                        </div>
                        @if($can['update'])
                        <div id="price-edit-{{ $panel->id }}" class="hidden">
                            <form method="POST" action="{{ route('admin.reservations.panels.price', [$reservation, $panel]) }}"
                                  onsubmit="return validatePriceForm({{ $panel->id }})">
                                @csrf @method('PATCH')
                                <div class="flex items-center gap-2 justify-end">
                                    <div class="relative">
                                        <input type="number" id="price-input-{{ $panel->id }}" name="unit_price"
                                               value="{{ $unitPrice }}" min="0" step="1000" required
                                               class="w-32 pr-10 pl-3 py-1.5 bg-[#1a1a2a] border border-[#e8a020]/40 rounded-lg text-sm text-[#e8a020] font-bold text-right focus:border-[#e8a020] focus:outline-none"
                                               onfocus="this.select()"
                                               onkeydown="if(event.key==='Escape') hidePriceEdit({{ $panel->id }})">
                                        <span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-600 text-xs pointer-events-none">F</span>
                                    </div>
                                    <button type="submit" class="p-1.5 bg-green-500/15 border border-green-500/30 rounded-lg text-green-400 hover:bg-green-500/25" title="Valider">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                    <button type="button" onclick="hidePriceEdit({{ $panel->id }})" class="p-1.5 bg-[#252530] border border-[#3a3a48] rounded-lg text-gray-500 hover:text-red-400" title="Annuler">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                                @if($isPriceModif)
                                <div class="mt-1.5 text-right">
                                    <form method="POST" action="{{ route('admin.reservations.panels.price.reset', [$reservation, $panel]) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs text-gray-600 hover:text-gray-400"
                                                onclick="return confirm('Remettre au tarif catalogue\u00a0?')">
                                            ↺ Revenir au tarif catalogue ({{ number_format($catalogue, 0, ',', ' ') }} FCFA)
                                        </button>
                                    </form>
                                </div>
                                @endif
                            </form>
                        </div>
                        @endif
                    </td>
                    <td class="p-3 text-right">
                        <span class="text-sm font-bold text-gray-400">
                            {{ number_format($totalPrice, 0, ',', ' ') }}
                            <span class="text-xs font-normal text-gray-500">FCFA</span>
                        </span>
                    </td>
                    @if($can['update'])<td class="p-3"></td>@endif
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="bg-[#0f0f1a]">
                    <td colspan="{{ $can['update'] ? 6 : 5 }}" class="p-3 text-right text-xs font-semibold text-gray-400 uppercase tracking-wider">
                        TOTAL RÉSERVATION
                    </td>
                    <td class="p-3 text-right" id="total-reservation-cell">
                        <span class="text-lg font-black text-[#e8a020]">
                            {{ number_format($reservation->total_amount, 0, ',', ' ') }}
                            <span class="text-xs font-normal text-gray-500">FCFA</span>
                        </span>
                    </td>
                    @if($can['update'])<td class="p-3"></td>@endif
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- ✅ AJOUTER UN PANNEAU depuis la fiche --}}
@if($can['update'] && !in_array($reservation->status->value, ['annule','refuse','termine']))
<div class="card mt-4" id="add-panel-card">
    <div class="card-header">
        <div style="display:flex;align-items:center;gap:10px">
            <svg width="15" height="15" fill="none" stroke="var(--accent)" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/><line x1="12" y1="7" x2="12" y2="13"/><line x1="9" y1="10" x2="15" y2="10"/></svg>
            <span class="card-title">Ajouter un panneau</span>
        </div>
        <span class="text-xs" style="color:var(--text3)">Vérification anti double-booking automatique</span>
    </div>
    <div class="card-body">
        <div id="add-panel-toast" style="display:none;padding:10px 14px;border-radius:10px;margin-bottom:12px;font-size:13px;font-weight:600"></div>

        <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
            <div style="flex:1;min-width:200px">
                <label style="display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:5px">Référence ou nom du panneau</label>
                <select id="add-panel-select" style="width:100%"></select>
            </div>
            <div style="width:160px">
                <label style="display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:5px">Prix/mois <span style="font-weight:400">(opt.)</span></label>
                <div style="position:relative">
                    <input type="number" id="add-panel-price" min="0" step="1000" placeholder="Tarif catalogue"
                           style="width:100%;height:38px;padding:0 40px 0 12px;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;font-size:12px;color:var(--text);box-sizing:border-box">
                    <span style="position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:10px;color:var(--text3);pointer-events:none">F</span>
                </div>
            </div>
            <button type="button" id="add-panel-btn" onclick="AddPanel.submit()"
                    style="height:38px;padding:0 18px;background:var(--accent);color:#0f172a;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;white-space:nowrap">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Ajouter
            </button>
        </div>
        <div style="margin-top:8px;font-size:11px;color:var(--text3)">
            Seuls les panneaux <span style="color:#22c55e;font-weight:600">disponibles</span>
            sur la période {{ $reservation->start_date->format('d/m/Y') }} → {{ $reservation->end_date->format('d/m/Y') }} sont proposés.
        </div>
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════
     MODAL — CHANGEMENT DE STATUT
══════════════════════════════════════════════════════ --}}
<div id="modal-status" class="modal-overlay" style="display:none" onclick="closeStatusModal(event)">
    <div class="modal" style="max-width:460px" onclick="event.stopPropagation()">
        <div class="modal-header">
            <div class="modal-title" id="modal-status-title">Confirmer l'action</div>
            <button class="modal-close" onclick="closeStatusModal()">✕</button>
        </div>
        <div class="modal-body">
            <div class="text-center mb-4">
                <div id="modal-status-icon" class="inline-flex items-center justify-center w-14 h-14 rounded-full text-2xl mb-3" style="background:var(--surface2)"></div>
                <div id="modal-status-desc" class="text-sm" style="color:var(--text2)"></div>
            </div>
            <div id="modal-status-consequences" class="p-3 rounded-lg text-sm mb-4" style="background:var(--surface2);border:1px solid var(--border2)"></div>
            <div id="modal-status-warning" class="p-3 rounded-lg text-xs flex items-start gap-2" style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:var(--red)">
                <span class="mt-0.5">⚠️</span><span id="modal-status-warning-text"></span>
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeStatusModal()" class="btn btn-ghost">Annuler</button>
            <form id="modal-status-form" method="POST">
                @csrf @method('PATCH')
                <input type="hidden" name="status" id="modal-status-input">
                <button type="submit" id="modal-status-btn" class="btn">Confirmer</button>
            </form>
        </div>
    </div>
</div>

{{-- ✅ MODAL — ANNULATION AVEC MOTIF DOCUMENTÉ --}}
<div id="modal-cancel" class="modal-overlay" style="display:none" onclick="closeCancelModal(event)">
    <div class="modal" style="max-width:500px" onclick="event.stopPropagation()">
        <div class="modal-header">
            <div class="modal-title">🚫 Annuler la réservation</div>
            <button class="modal-close" onclick="closeCancelModal()">✕</button>
        </div>
        <div class="modal-body">
            <div class="text-center mb-4">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full text-2xl mb-3"
                     style="background:rgba(239,68,68,.1)">🚫</div>
                <div class="font-semibold mb-1">Annuler {{ $reservation->reference }} ?</div>
                <div class="text-sm" style="color:var(--text2)">
                    Réservation de <strong style="color:var(--text)">{{ $reservation->client?->name }}</strong>
                    · {{ $reservation->panels->count() }} panneau(x)
                </div>
            </div>

            <div class="p-3 rounded-lg text-sm mb-4" style="background:var(--surface2);border:1px solid var(--border2)">
                <div class="font-medium mb-2" style="color:var(--text)">Ce qui va se passer :</div>
                <ul class="space-y-1.5" style="color:var(--text2)">
                    <li class="flex items-start gap-2">
                        <span style="color:var(--green)">✓</span>
                        <span>Les {{ $reservation->panels->count() }} panneau(x) seront <strong>immédiatement libérés</strong>.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span style="color:var(--green)">✓</span>
                        <span>La réservation sera conservée en <strong>historique</strong> avec le statut « Annulé ».</span>
                    </li>
                    @if($reservation->campaign)
                    <li class="flex items-start gap-2">
                        <span style="color:var(--red)">⚠</span>
                        <span>La campagne <strong>{{ $reservation->campaign->reference }}</strong> devra être gérée séparément.</span>
                    </li>
                    @endif
                </ul>
            </div>

            {{-- ✅ Motif d'annulation --}}
            <div style="background:var(--surface2);border:1px solid var(--border2);border-radius:12px;padding:14px;margin-bottom:14px">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:10px">
                    📋 Motif d'annulation (suivi qualité)
                </div>
                <div style="margin-bottom:10px">
                    <label style="font-size:11px;font-weight:600;color:var(--text2);display:block;margin-bottom:4px">Type de motif</label>
                    <select id="cancel-type-select"
                            style="width:100%;height:38px;padding:0 12px;background:var(--surface);border:1px solid var(--border2);border-radius:8px;font-size:12px;color:var(--text);cursor:pointer">
                        <option value="client_demande">Client : Demande d'annulation</option>
                        <option value="budget">Client : Contrainte budgétaire</option>
                        <option value="concurrent">Client : A choisi un concurrent</option>
                        <option value="report">Report de campagne</option>
                        <option value="autre">Autre motif</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:11px;font-weight:600;color:var(--text2);display:block;margin-bottom:4px">
                        Précisions <span style="font-weight:400;color:var(--text3)">(optionnel)</span>
                    </label>
                    <textarea id="cancel-reason-text" rows="3" maxlength="500"
                              placeholder="Décrivez le contexte de l'annulation…"
                              style="width:100%;padding:8px 12px;background:var(--surface);border:1px solid var(--border2);border-radius:8px;font-size:12px;color:var(--text);resize:vertical;box-sizing:border-box;outline:none;transition:border-color .2s"
                              onfocus="this.style.borderColor='var(--accent)'"
                              onblur="this.style.borderColor='var(--border2)'"></textarea>
                    <div style="font-size:10px;color:var(--text3);margin-top:3px">Utilisé pour le suivi qualité et les statistiques d'annulation.</div>
                </div>
            </div>

            <div class="p-3 rounded-lg text-xs flex items-start gap-2"
                 style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:var(--red)">
                <span class="mt-0.5">⚠️</span>
                <span>Cette action est <strong>irréversible</strong>. Une réservation annulée ne peut pas être réactivée.</span>
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeCancelModal()" class="btn btn-ghost">Conserver la réservation</button>
            <form method="POST" action="{{ route('admin.reservations.annuler', $reservation) }}"
                  id="cancel-form" onsubmit="return prepareCancelSubmit(this)">
                @csrf @method('PATCH')
                <input type="hidden" name="cancel_type"   id="cancel-type-hidden">
                <input type="hidden" name="cancel_reason" id="cancel-reason-hidden">
                <button type="submit" class="btn btn-danger">🚫 Confirmer l'annulation</button>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     SCRIPTS
══════════════════════════════════════════════════════ --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

@push('scripts')
<script>
// ── Prix panneaux ────────────────────────────────────────────────
function showPriceEdit(panelId, currentPrice, cataloguePrice) {
    document.getElementById(`price-display-${panelId}`).classList.add('hidden');
    document.getElementById(`price-edit-${panelId}`).classList.remove('hidden');
    const input = document.getElementById(`price-input-${panelId}`);
    if (input) { input.value = currentPrice; setTimeout(() => { input.focus(); input.select(); }, 50); }
}
function hidePriceEdit(panelId) {
    document.getElementById(`price-display-${panelId}`).classList.remove('hidden');
    document.getElementById(`price-edit-${panelId}`).classList.add('hidden');
}
function validatePriceForm(panelId) {
    const input = document.getElementById(`price-input-${panelId}`);
    const val   = parseFloat(input.value);
    if (isNaN(val) || val < 0) { input.style.borderColor = '#ef4444'; input.focus(); return false; }
    return true;
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('[id^="price-edit-"]').forEach(el => {
            if (!el.classList.contains('hidden')) hidePriceEdit(el.id.replace('price-edit-', ''));
        });
    }
});

// ── Modals statut ────────────────────────────────────────────────
const STATUS_CONFIG = {
    confirme: {
        title: '✅ Confirmer la réservation', icon: '✅', iconBg: 'rgba(34,197,94,.1)',
        desc: 'Vous êtes sur le point de confirmer la réservation {{ $reservation->reference }}.',
        consequences: [
            { icon: '🔒', text: 'Les panneaux seront <strong>définitivement bloqués</strong> pour la période.' },
            { icon: '📄', text: 'La réservation passera en <strong>Ferme</strong> — plus modifiable.' },
            { icon: '📅', text: 'La date de confirmation sera enregistrée automatiquement.' },
        ],
        warning: 'La confirmation est irréversible. Le statut ne pourra plus revenir à "En attente".',
        btnClass: 'btn-success', btnLabel: '✅ Confirmer',
    },
    refuse: {
        title: '❌ Refuser la réservation', icon: '❌', iconBg: 'rgba(239,68,68,.1)',
        desc: 'Vous êtes sur le point de refuser la réservation {{ $reservation->reference }}.',
        consequences: [
            { icon: '🔓', text: 'Les {{ $reservation->panels->count() }} panneau(x) seront <strong>immédiatement libérés</strong>.' },
            { icon: '🗄️', text: 'La réservation sera conservée en <strong>historique</strong> avec le statut « Refusé ».' },
        ],
        warning: 'Le refus est irréversible.',
        btnClass: 'btn-danger', btnLabel: '❌ Confirmer le refus',
    },
};

function modalShow(id) { document.getElementById(id).style.display = 'flex'; }
function modalHide(id) { document.getElementById(id).style.display = 'none'; }

function openStatusModal(newStatus) {
    const cfg = STATUS_CONFIG[newStatus];
    if (!cfg) return;
    document.getElementById('modal-status-title').textContent = cfg.title;
    const iconEl = document.getElementById('modal-status-icon');
    iconEl.textContent = cfg.icon; iconEl.style.background = cfg.iconBg;
    document.getElementById('modal-status-desc').textContent = cfg.desc;
    const consEl = document.getElementById('modal-status-consequences');
    consEl.innerHTML = '<div style="font-weight:600;margin-bottom:8px;color:var(--text)">Ce qui va se passer :</div>'
        + '<ul style="display:flex;flex-direction:column;gap:6px;color:var(--text2)">'
        + cfg.consequences.map(c => `<li style="display:flex;gap:8px;align-items:flex-start"><span>${c.icon}</span><span>${c.text}</span></li>`).join('')
        + '</ul>';
    document.getElementById('modal-status-warning-text').textContent = cfg.warning;
    document.getElementById('modal-status-input').value = newStatus;
    const btn = document.getElementById('modal-status-btn');
    btn.className = 'btn ' + cfg.btnClass; btn.textContent = cfg.btnLabel;
    document.getElementById('modal-status-form').action = '{{ route("admin.reservations.update-status", $reservation) }}';
    modalShow('modal-status');
}
function closeStatusModal(e) { if (!e || e.target === document.getElementById('modal-status')) modalHide('modal-status'); }
function openCancelModal()    { modalShow('modal-cancel'); }
function closeCancelModal(e)  { if (!e || e.target === document.getElementById('modal-cancel')) modalHide('modal-cancel'); }

// ✅ Préparer la soumission du formulaire d'annulation avec motif
function prepareCancelSubmit(form) {
    const typeEl   = document.getElementById('cancel-type-select');
    const reasonEl = document.getElementById('cancel-reason-text');
    if (typeEl)   document.getElementById('cancel-type-hidden').value   = typeEl.value;
    if (reasonEl) document.getElementById('cancel-reason-hidden').value = reasonEl.value;
    return true;
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { modalHide('modal-status'); modalHide('modal-cancel'); }
});

// ✅ Ajouter un panneau — Select2 + AJAX
@if($can['update'] && !in_array($reservation->status->value, ['annule','refuse','termine']))
(function() {
    // Styles Select2
    const s = document.createElement('style');
    s.textContent = `.select2-container--default .select2-selection--single{height:38px!important;border-radius:8px!important;border:1px solid var(--border2)!important;background:var(--surface2)!important;display:flex;align-items:center}.select2-container--default .select2-selection--single .select2-selection__rendered{line-height:38px!important;color:var(--text)!important;padding-left:12px!important;font-size:12px}.select2-container--default.select2-container--focus .select2-selection--single{border-color:var(--accent)!important}.select2-dropdown{background:var(--surface)!important;border:1px solid var(--border2)!important;border-radius:12px!important;box-shadow:0 8px 24px rgba(0,0,0,.3)!important;overflow:hidden}.select2-container--default .select2-search--dropdown .select2-search__field{background:var(--surface2)!important;border:1px solid var(--border2)!important;border-radius:8px!important;color:var(--text)!important;padding:6px 10px!important;font-size:12px;outline:none}.select2-results__option{padding:0!important}.select2-results__option--highlighted{background:rgba(232,160,32,.08)!important}.select2-results__message{color:var(--text3)!important;font-size:12px;padding:12px!important;text-align:center}`;
    document.head.appendChild(s);

    $('#add-panel-select').select2({
        placeholder: 'Tapez la référence ou le nom du panneau…',
        allowClear: true,
        minimumInputLength: 2,
        language: {
            inputTooShort: () => 'Tapez au moins 2 caractères…',
            searching:     () => 'Recherche…',
            noResults:     () => 'Aucun panneau disponible sur cette période',
        },
        ajax: {
            url:     '{{ route("admin.reservations.available-panels") }}',
            dataType: 'json',
            delay:    250,
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            data: params => ({
                q:                      params.term,
                start_date:             '{{ $reservation->start_date->format("Y-m-d") }}',
                end_date:               '{{ $reservation->end_date->format("Y-m-d") }}',
                exclude_reservation_id: {{ $reservation->id }},
            }),
            processResults: data => ({
                results: (Array.isArray(data) ? data : [])
                    .filter(p => p.available)
                    .map(p => ({ id: p.id, text: `${p.reference} — ${p.name}`, ...p })),
            }),
            cache: true,
        },
        templateResult: p => {
            if (!p.id) return $(`<span style="color:var(--text3)">${p.text}</span>`);
            return $(`<div style="padding:8px 12px;border-bottom:1px solid var(--border)">
                <div style="display:flex;align-items:center;gap:8px">
                    <span style="font-family:monospace;font-size:12px;font-weight:700;color:var(--accent)">${p.reference||''}</span>
                    <span style="font-size:12px;color:var(--text)">${p.name||p.text}</span>
                </div>
                <div style="font-size:10px;color:var(--text3);margin-top:2px">
                    📍 ${p.commune||'—'}${p.monthly_rate?' · '+Number(p.monthly_rate).toLocaleString('fr-FR')+' FCFA/mois':''}
                </div>
            </div>`);
        },
        templateSelection: p => p.id ? `${p.reference||''} — ${p.name||p.text}` : p.text,
        dropdownParent: $('#add-panel-card'),
        width: '100%',
    });

    // Pré-remplir le prix
    $('#add-panel-select').on('select2:select', function(e) {
        const d = e.params.data;
        if (d.monthly_rate) document.getElementById('add-panel-price').placeholder = Number(d.monthly_rate).toLocaleString('fr-FR');
    });

    window.AddPanel = {
        async submit() {
            const panelId = $('#add-panel-select').val();
            const price   = document.getElementById('add-panel-price').value;
            const toast   = document.getElementById('add-panel-toast');
            const btn     = document.getElementById('add-panel-btn');

            if (!panelId) { this._toast('Sélectionnez un panneau.', 'error', toast); return; }

            btn.disabled = true;
            btn.innerHTML = '⏳ Vérification…';
            toast.style.display = 'none';

            try {
                const res = await fetch('{{ route("admin.reservations.panels.add", $reservation) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept':       'application/json',
                    },
                    body: JSON.stringify({ panel_id: parseInt(panelId), unit_price: price ? parseFloat(price) : null }),
                });

                const data = await res.json();

                if (data.success) {
                    this._toast(`✅ ${data.message}`, 'success', toast);
                    setTimeout(() => location.reload(), 1200);
                } else {
                    this._toast(`⚠️ ${data.message}`, 'error', toast);
                    btn.disabled = false;
                    btn.innerHTML = '<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Ajouter';
                }
            } catch(e) {
                this._toast(`❌ Erreur réseau : ${e.message}`, 'error', toast);
                btn.disabled = false;
                btn.innerHTML = '<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Ajouter';
            }
        },
        _toast(msg, type, el) {
            el.textContent = msg;
            el.style.display = 'block';
            el.style.background = type === 'success' ? 'rgba(34,197,94,.1)'  : 'rgba(239,68,68,.1)';
            el.style.border     = type === 'success' ? '1px solid rgba(34,197,94,.3)' : '1px solid rgba(239,68,68,.3)';
            el.style.color      = type === 'success' ? '#22c55e' : '#ef4444';
        },
    };
})();
@endif
</script>
@endpush

</x-admin-layout>