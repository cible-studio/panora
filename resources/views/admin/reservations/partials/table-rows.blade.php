@forelse($reservations as $res)
@php
    $isNew         = isset($lastSeenAt) && $lastSeenAt && $res->created_at > $lastSeenAt;
    $clientDeleted = $res->client?->trashed();
    $canEdit       = $res->isEditable() && auth()->user()->can('update', $res);
    $canAnnuler    = $res->isCancellable() && auth()->user()->can('annuler', $res);
    $canDelete     = $res->isDeletable() && auth()->user()->can('delete', $res);
    $rc            = $res->status->uiConfig();
@endphp
<tr class="{{ $isNew ? 'new-row' : '' }}">
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
        <span class="badge">{{ $res->panels_count }} 🪧</span>
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
            <a href="{{ route('admin.reservations.show', $res) }}" class="btn-icon" title="Voir">👁</a>
            @if($canEdit)
            <a href="{{ route('admin.reservations.edit', $res) }}" class="btn-icon" title="Modifier">✏️</a>
            @endif
            @if($canAnnuler)
            <button class="btn-icon btn-cancel" 
                    onclick="openAnnulerModal({{ $res->id }}, '{{ $res->reference }}', '{{ addslashes($res->client?->name ?? '') }}', {{ $res->panels_count }})"
                    title="Annuler">🚫</button>
            @endif
           
            <button class="btn-icon btn-delete" 
                    onclick="openDeleteModal({{ $res->id }}, '{{ $res->reference }}')"
                    title="Supprimer">🗑️</button>
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