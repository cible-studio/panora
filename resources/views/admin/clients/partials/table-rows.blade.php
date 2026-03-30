@forelse($clients as $client)
@php
    $hasActive = ($client->active_campaigns_count ?? 0) > 0;
@endphp
<tr>
    <td>
        <div class="flex items-center gap-3">
            <div class="client-avatar">{{ strtoupper(substr($client->name, 0, 1)) }}</div>
            <div>
                <a href="{{ route('admin.clients.show', $client) }}" class="client-name">
                    {{ $client->name }}
                </a>
                <div class="text-xs text-text3 mt-1">
                    @if($client->ncc)
                        <span class="client-ncc">{{ $client->ncc }}</span>
                    @endif
                    · Depuis {{ $client->created_at->format('d/m/Y') }}
                </div>
            </div>
        </div>
    </td>
    <td>
        @if($client->sector)
            <span class="sector-badge">{{ $client->sector }}</span>
        @else
            <span class="text-text3">—</span>
        @endif
    </td>
    <td>
        <div class="campaign-count">{{ $client->campaigns_count ?? 0 }}</div>
        @if($hasActive)
            <div class="active-badge">{{ $client->active_campaigns_count }} active(s)</div>
        @endif
    </td>
    <td class="reservation-count">{{ $client->reservations_count ?? 0 }}</td>
    <td>
        @if($client->contact_name)
            <div class="contact-name">{{ $client->contact_name }}</div>
        @endif
        @if($client->email)
            <div class="contact-detail">{{ $client->email }}</div>
        @endif
        @if($client->phone)
            <div class="contact-detail">{{ $client->phone }}</div>
        @endif
        @if(!$client->contact_name && !$client->email && !$client->phone)
            <span class="text-text3">—</span>
        @endif
    </td>
    <td>
        <div class="actions">
            <a href="{{ route('admin.clients.show', $client) }}" class="btn-icon" title="Voir">👁</a>
            <a href="{{ route('admin.clients.edit', $client) }}" class="btn-icon" title="Modifier">✏️</a>
            <button type="button" onclick="openDeleteClient({{ $client->id }}, '{{ addslashes($client->name) }}', {{ $client->active_campaigns_count ?? 0 }})" class="btn-icon btn-delete" title="Supprimer">🗑</button>
        </div>
    </td>
</tr>
@empty
<tr>
    <td colspan="6" class="empty-state text-center">
        <div class="text-5xl mb-3">👥</div>
        <div class="text-base font-medium mb-1">Aucun client trouvé</div>
        <div class="text-sm text-text3">
            <a href="{{ route('admin.clients.create') }}" class="text-accent">+ Créer le premier client</a>
        </div>
    </td>
</tr>
@endforelse