@forelse($campaigns as $campaign)
@php
    $statusClass = match($campaign->status->value) {
        'actif' => 'status-actif',
        'pose' => 'status-pose',
        'termine' => 'status-termine',
        'annule' => 'status-annule',
        default => 'status-termine',
    };
@endphp
<tr class="border-b border-border hover:bg-surface2 transition group">
    <td class="px-4 py-3">
        <a href="{{ route('admin.campaigns.show', $campaign) }}" class="font-semibold text-white hover:text-accent transition">
            {{ $campaign->name }}
        </a>
    </td>
    <td class="px-4 py-3 text-sm text-text2 whitespace-nowrap">
        {{ $campaign->start_date->format('d/m/Y') }} → {{ $campaign->end_date->format('d/m/Y') }}
    </td>
    <td class="px-4 py-3 text-center text-text2">{{ $campaign->total_panels ?? $campaign->panels_count }}</td>
    <td class="px-4 py-3 text-right font-semibold text-accent whitespace-nowrap">
        {{ number_format($campaign->total_amount, 0, ',', ' ') }} <span class="text-xs text-text3">FCFA</span>
    </td>
    <td class="px-4 py-3">
        <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold {{ $statusClass }}">
            {{ $campaign->status->label() }}
        </span>
    </td>
    <td class="px-4 py-3">
        <a href="{{ route('admin.campaigns.show', $campaign) }}" class="text-accent hover:text-accent/80 text-sm transition group-hover:translate-x-0.5 inline-block">👁 Détails →</a>
    </td>
</tr>
@empty
<tr>
    <td colspan="6" class="text-center py-12 text-text3">
        <div class="text-4xl mb-2">📁</div>
        <div class="text-sm">Aucune campagne trouvée</div>
        <div class="text-xs mt-1">Créez votre première campagne depuis l'espace réservations</div>
    </td>
</tr>
@endforelse