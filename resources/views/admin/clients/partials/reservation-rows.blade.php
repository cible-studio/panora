@forelse($reservations as $reservation)
@php
    $statusClass = match($reservation->status->value) {
        'en_attente' => 'status-en_attente',
        'confirme' => 'status-confirme',
        'refuse' => 'status-refuse',
        'annule' => 'status-annule',
        'termine' => 'status-termine',
        default => 'status-termine',
    };
    $statusLabel = $reservation->status->label();
@endphp
<tr class="border-b border-border hover:bg-surface2 transition group">
    <td class="px-4 py-3">
        <a href="{{ route('admin.reservations.show', $reservation) }}" class="font-mono text-sm font-bold text-accent hover:text-accent/80 transition">
            {{ $reservation->reference }}
        </a>
    </td>
    <td class="px-4 py-3 text-sm text-text2 whitespace-nowrap">
        {{ $reservation->start_date->format('d/m/Y') }} → {{ $reservation->end_date->format('d/m/Y') }}
    </td>
    <td class="px-4 py-3 text-center text-text2">{{ $reservation->panels_count ?? '—' }}</td>
    <td class="px-4 py-3 text-right font-semibold text-accent whitespace-nowrap">
        {{ number_format($reservation->total_amount, 0, ',', ' ') }} <span class="text-xs text-text3">FCFA</span>
    </td>
    <td class="px-4 py-3">
        <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold {{ $statusClass }}">
            {{ $statusLabel }}
        </span>
    </td>
    <td class="px-4 py-3">
        <a href="{{ route('admin.reservations.show', $reservation) }}" class="text-accent hover:text-accent/80 text-sm transition group-hover:translate-x-0.5 inline-block">👁 Détails →</a>
    </td>
</tr>
@empty
<tr>
    <td colspan="6" class="text-center py-12 text-text3">
        <div class="text-4xl mb-2">✅</div>
        <div class="text-sm">Aucune réservation trouvée</div>
        <div class="text-xs mt-1">Créez une réservation depuis la page disponibilités</div>
    </td>
</tr>
@endforelse