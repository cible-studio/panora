@forelse($reservations as $res)
@php
    $isNew         = isset($lastSeenAt) && $lastSeenAt && $res->created_at > $lastSeenAt;
    $clientDeleted = $res->client?->trashed();
    $canEdit       = $res->isEditable() && auth()->user()->can('update', $res);
    $canAnnuler    = $res->isCancellable() && auth()->user()->can('annuler', $res);
    $canDelete     = $res->isDeletable() && auth()->user()->can('delete', $res);
    $canConfirm    = $res->isConfirmable() && auth()->user()->can('updateStatus', $res);
    $canRefuse     = $res->isRefusable() && auth()->user()->can('updateStatus', $res);
    $rc            = $res->status->uiConfig();
@endphp
@php
    $intCountRow = (int) ($res->panels_count ?? 0);
    $extCountRow = (int) ($res->external_panels_count ?? 0);
    $totalRow    = $intCountRow + $extCountRow;
    $hasCampaign = $res->campaign !== null;
@endphp
<tr class="{{ $isNew ? 'new-row' : '' }}"
    data-status="{{ $res->status->value }}"
    data-reference="{{ $res->reference }}"
    data-client="{{ $res->client?->name ?? '—' }}"
    data-panels-count="{{ $totalRow }}"
    data-has-campaign="{{ $hasCampaign ? '1' : '0' }}"
    data-campaign-name="{{ $hasCampaign ? $res->campaign->name : '' }}"
    data-cancellable="{{ $canAnnuler ? '1' : '0' }}"
    data-deletable="{{ $canDelete ? '1' : '0' }}"
    data-confirmable="{{ $canConfirm ? '1' : '0' }}"
    data-refusable="{{ $canRefuse ? '1' : '0' }}">
    <td class="bulk-cell">
        @if($canEdit || $canAnnuler || $canDelete || $canConfirm || $canRefuse)
            <input type="checkbox" class="bulk-checkbox" value="{{ $res->id }}" aria-label="Sélectionner {{ $res->reference }}">
        @endif
    </td>
    <td style="padding:0 4px 0 10px;">
        @if($isNew)
        <span class="new-dot"></span>
        @endif
    </td>
    <td>
        <a href="{{ route('admin.reservations.show', $res) }}" class="reference-link">
            {{ $res->reference }}
        </a>
        <div class="date-humans">{{ $res->created_at->diffForHumans() }}</div>
    </td>
    <td>
        @if($clientDeleted)
        <span class="client-deleted">{{ $res->client?->name ?? '—' }}</span>
        <span class="deleted-badge">Supprimé</span>
        @else
        <a href="{{ route('admin.clients.show', $res->client) }}" class="client-link">
            {{ $res->client?->name ?? '—' }}
        </a>
        @endif
    </td>
    <td class="date-range">
        {{ $res->start_date->format('d/m/Y') }}
        <span>→</span>
        {{ $res->end_date->format('d/m/Y') }}
    </td>
    <td>
        <span class="badge" title="{{ $intCountRow }} interne(s){{ $extCountRow > 0 ? ' + '.$extCountRow.' externe(s)' : '' }}">
            {{ $totalRow }} 🪧@if($extCountRow > 0)<span style="font-size:9px;margin-left:4px;color:#60a5fa;font-weight:700">🤝{{ $extCountRow }}</span>@endif
        </span>
    </td>
    <td class="amount">
        {{ number_format($res->total_amount, 0, ',', ' ') }}
        <span>FCFA</span>
    </td>
    <td>
        <span class="type-badge {{ $res->type === 'ferme' ? 'type-ferme' : 'type-option' }}">
            {{ $res->type === 'ferme' ? '🔒 Ferme' : '⏳ Option' }}
        </span>
    </td>
    <td>
        <span class="status-badge" style="background:{{ $rc['bg'] }};color:{{ $rc['color'] }};border-color:{{ $rc['border'] }}">
            {{ $res->status->label() }}
        </span>
        @if($clientDeleted)
        <div class="readonly-note">lecture seule</div>
        @endif
    </td>
    <td>
        @if($res->campaign)
        <a href="{{ route('admin.campaigns.show', $res->campaign) }}" class="campaign-link">
            📁 {{ $res->campaign->name }}
        </a>
        @elseif($res->status->value === 'confirme')
        <a href="{{ route('admin.campaigns.create', ['reservation_id' => $res->id]) }}" class="create-campaign">
            + Créer
        </a>
        @else
        <span class="no-campaign">—</span>
        @endif
    </td>
    <td>
        <div class="actions">
            <a href="{{ route('admin.reservations.show', $res) }}" class="btn-icon" title="Voir la fiche">👁</a>
            <button type="button" class="btn-icon"
                    onclick="openPanelsModal({{ $res->id }}, @js($res->reference))"
                    title="Voir les panneaux">🪧</button>
            @if($canEdit)
            <a href="{{ route('admin.reservations.edit', $res) }}" class="btn-icon" title="Modifier">✏️</a>
            @endif
            @if($canConfirm)
            <button class="btn-icon btn-confirm"
                    onclick="openResaStatusModal({{ $res->id }}, 'confirme', '{{ $res->reference }}', '{{ addslashes($res->client?->name ?? '') }}', {{ $totalRow }})"
                    title="Confirmer la réservation">✅</button>
            @endif
            @if($canRefuse)
            <button class="btn-icon btn-refuse"
                    onclick="openResaStatusModal({{ $res->id }}, 'refuse', '{{ $res->reference }}', '{{ addslashes($res->client?->name ?? '') }}', {{ $totalRow }})"
                    title="Refuser la réservation">❌</button>
            @endif
            @if($canAnnuler)
            <button class="btn-icon btn-cancel"
                    onclick="openAnnulerModal({{ $res->id }}, '{{ $res->reference }}', '{{ addslashes($res->client?->name ?? '') }}', {{ $totalRow }}, {{ $hasCampaign ? 'true' : 'false' }}, '{{ addslashes($hasCampaign ? $res->campaign->name : '') }}')"
                    title="Annuler la réservation">🚫</button>
            @endif

            @if($canDelete)
            <button class="btn-icon btn-delete"
                    onclick="openDeleteModal({{ $res->id }}, '{{ $res->reference }}', '{{ addslashes($res->client?->name ?? '') }}', {{ $totalRow }}, {{ $hasCampaign ? 'true' : 'false' }}, '{{ addslashes($hasCampaign ? $res->campaign->name : '') }}')"
                    title="Supprimer définitivement">🗑️</button>
            @endif
        </div>
    </td>
</tr>
@empty
<tr>
    <td colspan="10" class="empty-state text-center">
        <div>📋</div>
        <div>Aucune réservation trouvée.</div>
        <div class="empty-action">
            <a href="{{ route('admin.reservations.disponibilites') }}">+ Créer une réservation</a>
        </div>
    </td>
</tr>
@endforelse