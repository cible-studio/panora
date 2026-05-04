<x-admin-layout title="{{ $client->name }}">

<x-slot:topbarLeft>
    <a href="{{ route('admin.clients.index') }}" class="btn btn-ghost btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
        Retour
    </a>
</x-slot:topbarLeft>

<x-slot:topbarActions>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="{{ route('admin.clients.edit', $client) }}"
           class="btn btn-ghost btn-sm">✏️ Modifier</a>
        <button type="button"
                onclick="openDeleteClient({{ $client->id }}, '{{ addslashes($client->name) }}', {{ $client->hasActiveCampaigns() ? 1 : 0 }})"
                class="btn btn-ghost btn-sm"
                style="color:var(--red);border-color:var(--red);">
            🗑 Supprimer
        </button>
    </div>
</x-slot:topbarActions>

{{-- Breadcrumb --}}
<div style="font-size:12px;color:var(--text3);margin-bottom:16px;">
    <a href="{{ route('admin.clients.index') }}"
       style="color:var(--text3);text-decoration:none;">Clients</a>
    <span style="margin:0 6px;">›</span>
    <span style="color:var(--text);">{{ $client->name }}</span>
</div>

<div style="display:grid;grid-template-columns:300px 1fr;gap:16px;align-items:start;">

    {{-- Carte identité --}}
    <div style="background:var(--surface);border:1px solid var(--border);
                border-radius:14px;overflow:hidden;">

        {{-- Header carte --}}
        <div style="padding:24px;text-align:center;
                    border-bottom:1px solid var(--border);">
            <div style="width:60px;height:60px;border-radius:50%;background:var(--accent);
                        color:#000;display:flex;align-items:center;justify-content:center;
                        font-weight:800;font-size:24px;margin:0 auto 12px;">
                {{ strtoupper(substr($client->name, 0, 1)) }}
            </div>
            <div style="font-weight:800;font-size:16px;color:var(--text);margin-bottom:6px;">
                {{ $client->name }}
            </div>
            @if($client->ncc)
            <div style="font-family:monospace;font-size:12px;background:var(--surface2);
                        padding:3px 10px;border-radius:20px;display:inline-block;
                        color:var(--text2);margin-bottom:8px;">
                {{ $client->ncc }}
            </div>
            @endif
            @if($client->sector)
            <div>
                <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;
                             background:var(--surface3);color:var(--text2);">
                    {{ $client->sector }}
                </span>
            </div>
            @endif
        </div>

        {{-- Infos --}}
        <div style="padding:16px 20px;">
            @foreach([
                ['👤 Contact',   $client->contact_name],
                ['📧 Email',     $client->email],
                ['📞 Téléphone', $client->phone],
                ['📍 Adresse',   $client->address],
                ['📅 Depuis',    $client->created_at->format('d/m/Y')],
            ] as [$label, $value])
            <div style="display:flex;gap:10px;padding:8px 0;
                        border-bottom:1px solid var(--border);">
                <span style="font-size:11px;color:var(--text3);min-width:90px;
                             flex-shrink:0;padding-top:1px;">{{ $label }}</span>
                <span style="font-size:13px;color:var(--text2);word-break:break-word;">
                    {{ $value ?? '—' }}
                </span>
            </div>
            @endforeach
        </div>

        {{-- Lien nouvelle réservation --}}
        <div style="padding:16px 20px;border-top:1px solid var(--border);">
            <a href="{{ route('admin.reservations.disponibilites') }}"
               class="btn btn-primary" style="width:100%;text-align:center;display:block;">
                + Nouvelle réservation
            </a>
        </div>
    </div>

    {{-- Activité --}}
    <div style="display:flex;flex-direction:column;gap:16px;">

        {{-- Badges client récurrent / satisfaction --}}
        @php
            $campaignsCount     = $client->campaigns->count();
            $reservationsCount  = $client->reservations->count();
            $isRecurrent        = $campaignsCount > 1;
            // Note moyenne satisfaction (calculée si la table existe — sinon null)
            $satisfactionAvg = null;
            $satisfactionN   = 0;
            if (\Illuminate\Support\Facades\Schema::hasTable('satisfaction_surveys')) {
                $sStats = \DB::table('satisfaction_surveys')
                    ->where('client_id', $client->id)
                    ->whereNotNull('completed_at')
                    ->selectRaw('AVG(score_global) as avg, COUNT(*) as n')
                    ->first();
                if ($sStats && $sStats->n > 0) {
                    $satisfactionAvg = round((float) $sStats->avg, 1);
                    $satisfactionN   = (int) $sStats->n;
                }
            }
        @endphp

        @if($isRecurrent || $satisfactionAvg !== null)
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                @if($isRecurrent)
                    <span style="display:inline-flex;align-items:center;gap:6px;background:rgba(232,160,32,0.1);border:1px solid rgba(232,160,32,0.3);color:var(--accent);padding:6px 12px;border-radius:999px;font-size:12px;font-weight:600">
                        🔄 Client récurrent ({{ $campaignsCount }} campagnes)
                    </span>
                @endif
                @if($satisfactionAvg !== null)
                    @php
                        $satColor = $satisfactionAvg >= 4 ? '#22c55e' : ($satisfactionAvg >= 3 ? '#f59e0b' : '#ef4444');
                    @endphp
                    <span style="display:inline-flex;align-items:center;gap:6px;background:{{ $satColor }}1a;border:1px solid {{ $satColor }}55;color:{{ $satColor }};padding:6px 12px;border-radius:999px;font-size:12px;font-weight:600">
                        ⭐ {{ number_format($satisfactionAvg, 1, ',', '') }}/5 satisfaction ({{ $satisfactionN }} avis)
                    </span>
                @endif
            </div>
        @endif

        {{-- Analyse financière (déplacée depuis la liste) --}}
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
            @foreach([
                ['Réservations',  $reservationsCount,                                              'var(--text)'],
                ['Campagnes',     $campaignsCount,                                                 '#3b82f6'],
                ['CA Total',      number_format($totalFacture, 0, ',', ' ') . ' FCFA',             'var(--accent)'],
            ] as [$label, $value, $color])
            <div style="background:var(--surface);border:1px solid var(--border);
                        border-radius:12px;padding:16px;text-align:center;">
                <div style="font-size:20px;font-weight:800;color:{{ $color }};">
                    {{ $value }}
                </div>
                <div style="font-size:11px;color:var(--text3);margin-top:3px;font-weight:600;">
                    {{ $label }}
                </div>
            </div>
            @endforeach
        </div>

        {{-- Campagnes récentes --}}
        <div style="background:var(--surface);border:1px solid var(--border);
                    border-radius:14px;overflow:hidden;">
            <div style="padding:14px 18px;border-bottom:1px solid var(--border);
                        display:flex;align-items:center;justify-content:space-between;">
                <span style="font-weight:700;font-size:14px;">Campagnes récentes</span>
                <a href="{{ route('admin.campaigns.index', ['client_id' => $client->id]) }}"
                   style="font-size:12px;color:var(--accent);text-decoration:none;">
                    Voir toutes →
                </a>
            </div>
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:1px solid var(--border);">
                        @foreach(['Campagne','Période','Panneaux','Montant','Statut'] as $h)
                        <th style="padding:10px 16px;text-align:left;font-size:10px;
                                   font-weight:700;color:var(--text3);text-transform:uppercase;
                                   letter-spacing:.5px;">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($client->campaigns->take(8) as $campaign)
                    @php
                        $cs = match($campaign->status->value) {
                            'actif'   => ['#22c55e','rgba(34,197,94,0.1)','rgba(34,197,94,0.3)'],
                            'pose'    => ['#3b82f6','rgba(59,130,246,0.1)','rgba(59,130,246,0.3)'],
                            'termine' => ['#6b7280','rgba(107,114,128,0.1)','rgba(107,114,128,0.3)'],
                            'annule'  => ['#ef4444','rgba(239,68,68,0.1)','rgba(239,68,68,0.3)'],
                            default   => ['#6b7280','rgba(107,114,128,0.1)','rgba(107,114,128,0.3)'],
                        };
                    @endphp
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:12px 16px;">
                            <a href="{{ route('admin.campaigns.show', $campaign) }}"
                               style="font-weight:600;color:var(--text);text-decoration:none;
                                      font-size:13px;">
                                {{ $campaign->name }}
                            </a>
                        </td>
                        <td style="padding:12px 16px;font-size:12px;color:var(--text2);">
                            {{ $campaign->start_date->format('d/m/Y') }}
                            → {{ $campaign->end_date->format('d/m/Y') }}
                        </td>
                        <td style="padding:12px 16px;text-align:center;color:var(--text2);">
                            {{ $campaign->total_panels }}
                        </td>
                        <td style="padding:12px 16px;font-weight:700;color:var(--accent);
                                   font-size:13px;">
                            {{ number_format($campaign->total_amount, 0, ',', ' ') }}
                            <span style="font-size:10px;color:var(--text3);">FCFA</span>
                        </td>
                        <td style="padding:12px 16px;">
                            <span style="padding:3px 9px;border-radius:20px;font-size:11px;
                                         font-weight:600;background:{{ $cs[1] }};
                                         color:{{ $cs[0] }};border:1px solid {{ $cs[2] }};">
                                {{ ucfirst($campaign->status->value) }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5"
                            style="padding:40px;text-align:center;color:var(--text3);
                                   font-size:13px;">
                            Aucune campagne pour ce client.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Réservations récentes --}}
        @if($client->reservations->count() > 0)
        <div style="background:var(--surface);border:1px solid var(--border);
                    border-radius:14px;overflow:hidden;">
            <div style="padding:14px 18px;border-bottom:1px solid var(--border);
                        display:flex;align-items:center;justify-content:space-between;">
                <span style="font-weight:700;font-size:14px;">Réservations récentes</span>
                <a href="{{ route('admin.reservations.index', ['client_id' => $client->id]) }}"
                   style="font-size:12px;color:var(--accent);text-decoration:none;">
                    Voir toutes →
                </a>
            </div>
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:1px solid var(--border);">
                        @foreach(['Référence','Période','Panneaux','Montant','Statut'] as $h)
                        <th style="padding:10px 16px;text-align:left;font-size:10px;
                                   font-weight:700;color:var(--text3);text-transform:uppercase;
                                   letter-spacing:.5px;">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($client->reservations->take(5) as $reservation)
                    @php
                        $rs = match($reservation->status->value) {
                            'en_attente' => ['#e8a020','rgba(232,160,32,0.1)','rgba(232,160,32,0.3)'],
                            'confirme'   => ['#22c55e','rgba(34,197,94,0.1)','rgba(34,197,94,0.3)'],
                            'refuse'     => ['#ef4444','rgba(239,68,68,0.1)','rgba(239,68,68,0.3)'],
                            'annule'     => ['#6b7280','rgba(107,114,128,0.1)','rgba(107,114,128,0.3)'],
                            default      => ['#6b7280','rgba(107,114,128,0.1)','rgba(107,114,128,0.3)'],
                        };
                    @endphp
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:12px 16px;">
                            <a href="{{ route('admin.reservations.show', $reservation) }}"
                               style="font-family:monospace;font-size:12px;font-weight:700;
                                      color:var(--accent);text-decoration:none;">
                                {{ $reservation->reference }}
                            </a>
                        </td>
                        <td style="padding:12px 16px;font-size:12px;color:var(--text2);">
                            {{ $reservation->start_date->format('d/m/Y') }}
                            → {{ $reservation->end_date->format('d/m/Y') }}
                        </td>
                        <td style="padding:12px 16px;text-align:center;color:var(--text2);">
                            {{ $reservation->panels_count ?? '—' }}
                        </td>
                        <td style="padding:12px 16px;font-weight:700;color:var(--accent);
                                   font-size:13px;">
                            {{ number_format($reservation->total_amount, 0, ',', ' ') }}
                            <span style="font-size:10px;color:var(--text3);">FCFA</span>
                        </td>
                        <td style="padding:12px 16px;">
                            <span style="padding:3px 9px;border-radius:20px;font-size:11px;
                                         font-weight:600;background:{{ $rs[1] }};
                                         color:{{ $rs[0] }};border:1px solid {{ $rs[2] }};">
                                {{ $reservation->status->label() }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

    </div>
</div>

{{-- ══ INVENTAIRE PANNEAUX DU CLIENT ══ --}}
@if($panneauxClient->isNotEmpty())
<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <div class="card-title">🪧 Panneaux associés ({{ $panneauxClient->count() }})</div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Désignation</th>
                    <th>Commune</th>
                    <th>Format</th>
                    <th>Source</th>
                    <th>Période</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                @foreach($panneauxClient as $item)
                @php
                    $statusConfig = [
                        'actif'      => ['label' => 'Actif',       'bg' => 'rgba(34,197,94,0.1)',   'color' => '#22c55e', 'border' => 'rgba(34,197,94,0.3)'],
                        'confirme'   => ['label' => 'Confirmé',    'bg' => 'rgba(34,197,94,0.1)',   'color' => '#22c55e', 'border' => 'rgba(34,197,94,0.3)'],
                        'en_attente' => ['label' => 'Option',      'bg' => 'rgba(232,160,32,0.1)',  'color' => '#e8a020', 'border' => 'rgba(232,160,32,0.3)'],
                        'option'     => ['label' => 'Option',      'bg' => 'rgba(232,160,32,0.1)',  'color' => '#e8a020', 'border' => 'rgba(232,160,32,0.3)'],
                        'pose'       => ['label' => 'Pose en cours','bg'=> 'rgba(59,130,246,0.1)',  'color' => '#3b82f6', 'border' => 'rgba(59,130,246,0.3)'],
                        'termine'    => ['label' => 'Terminé',     'bg' => 'rgba(107,114,128,0.1)', 'color' => '#6b7280', 'border' => 'rgba(107,114,128,0.3)'],
                        'annule'     => ['label' => 'Annulé',      'bg' => 'rgba(239,68,68,0.1)',   'color' => '#ef4444', 'border' => 'rgba(239,68,68,0.3)'],
                    ];
                    $sc = $statusConfig[$item['status']] ?? ['label' => ucfirst($item['status']), 'bg' => 'rgba(107,114,128,0.1)', 'color' => '#6b7280', 'border' => 'rgba(107,114,128,0.3)'];
                @endphp
                <tr onmouseover="this.style.background='var(--surface2)'"
                    onmouseout="this.style.background=''">
                    <td>
                        <span style="font-family:monospace;font-weight:700;color:var(--accent);font-size:12px;">
                            {{ $item['panel']->reference }}
                        </span>
                    </td>
                    <td style="font-weight:500;font-size:13px;">
                        {{ $item['panel']->name }}
                    </td>
                    <td style="font-size:12px;color:var(--text2);">
                        {{ $item['panel']->commune?->name ?? '—' }}
                    </td>
                    <td style="font-size:12px;color:var(--text2);">
                        {{ $item['panel']->format?->name ?? '—' }}
                    </td>
                    <td>
                        <!-- @if($item['source'] === 'campaign') -->
                            <a href="{{ route('admin.campaigns.show', $item['source_id']) }}"
                               style="font-size:11px;color:#3b82f6;text-decoration:none;font-weight:600;">
                                📢 {{ $item['reference_source'] }}
                            </a>
                        <!-- @else
                            <a href="{{ route('admin.reservations.show', $item['source_id']) }}"
                               style="font-size:11px;color:var(--text2);text-decoration:none;font-weight:600;">
                                📋 {{ $item['reference_source'] }}
                            </a>
                        @endif -->
                    </td>
                    <td style="font-size:11px;color:var(--text3);white-space:nowrap;">
                        {{ \Carbon\Carbon::parse($item['start_date'])->format('d/m/Y') }}
                        → {{ \Carbon\Carbon::parse($item['end_date'])->format('d/m/Y') }}
                    </td>
                    <td>
                        <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;
                                     background:{{ $sc['bg'] }};color:{{ $sc['color'] }};
                                     border:1px solid {{ $sc['border'] }};
                                     text-transform:uppercase;letter-spacing:.5px;">
                            {{ $sc['label'] }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Modal suppression --}}
<div id="modal-delete-client" class="modal-overlay" style="display:none"
     onclick="if(event.target===this) closeDeleteClient()">
    <div class="modal" style="max-width:420px" onclick="event.stopPropagation()">
        <div class="modal-header">
            <div class="modal-title" style="color:var(--red)">🗑 Supprimer le client</div>
            <button class="modal-close" onclick="closeDeleteClient()">✕</button>
        </div>
        <div class="modal-body" style="text-align:center;padding:28px 22px;">
            <div style="font-size:44px;margin-bottom:12px;">👥</div>
            <div style="font-weight:700;font-size:15px;margin-bottom:8px;">
                Supprimer <span id="del-client-name"
                                style="color:var(--accent);"></span> ?
            </div>
            <div id="del-client-warning"
                 style="display:none;background:rgba(239,68,68,.08);
                        border:1px solid rgba(239,68,68,.2);border-radius:8px;
                        padding:10px;font-size:12px;color:var(--red);margin-bottom:12px;">
                ⚠️ Ce client a des campagnes actives. La suppression sera bloquée par le serveur.
            </div>
            <div style="font-size:13px;color:var(--text2);margin-bottom:14px;">
                Le client sera archivé. Ses données historiques seront conservées.
            </div>
            <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);
                        border-radius:8px;padding:10px;font-size:12px;color:var(--red);">
                ⚠️ Ses réservations passeront en lecture seule.
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeDeleteClient()">Annuler</button>
            <form id="del-client-form" method="POST" style="display:inline">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger">🗑 Supprimer</button>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openDeleteClient(id, name, activeCampaigns) {
    document.getElementById('del-client-name').textContent = name;
    document.getElementById('del-client-form').action = `/admin/clients/${id}`;
    document.getElementById('del-client-warning').style.display =
        activeCampaigns > 0 ? 'block' : 'none';
    document.getElementById('modal-delete-client').style.display = 'flex';
}
function closeDeleteClient() {
    document.getElementById('modal-delete-client').style.display = 'none';
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeDeleteClient();
});
</script>
@endpush

</x-admin-layout>
