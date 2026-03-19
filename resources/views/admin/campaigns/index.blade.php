<x-admin-layout title="Campagnes">

<x-slot:topbarActions>
    @can('create', App\Models\Campaign::class)
    <a href="{{ route('admin.campaigns.create') }}" class="btn btn-primary">
        + Nouvelle campagne
    </a>
    @endcan
</x-slot:topbarActions>

{{-- ══ ALERTE FIN PROCHE ══ --}}
@if(($endingSoonCount ?? 0) > 0)
<div style="background:rgba(232,160,32,0.08);border:1px solid rgba(232,160,32,0.3);
            border-radius:10px;padding:10px 16px;margin-bottom:14px;
            display:flex;align-items:center;gap:10px;">
    <span style="font-size:18px;">⚠️</span>
    <span style="font-size:13px;color:var(--accent);font-weight:600;">
        {{ $endingSoonCount }} campagne(s) se terminent dans moins de 14 jours
    </span>
    <a href="{{ route('admin.campaigns.index', ['status' => 'actif', 'date_to' => now()->addDays(14)->format('Y-m-d')]) }}"
       style="margin-left:auto;font-size:11px;color:var(--accent);text-decoration:none;
              padding:4px 10px;border:1px solid rgba(232,160,32,0.4);border-radius:6px;">
        Voir →
    </a>
</div>
@endif

{{-- ══ STATS ══ --}}
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:16px;">
    @php
    $statCards = [
        ['val'=>'all',    'label'=>'Total',      'icon'=>'📋', 'color'=>'var(--text)', 'bg'=>'var(--surface)',               'border'=>'var(--border)'],
        ['val'=>'actif',  'label'=>'En cours',   'icon'=>'📡', 'color'=>'#22c55e',    'bg'=>'rgba(34,197,94,0.08)',          'border'=>'rgba(34,197,94,0.3)'],
        ['val'=>'pose',   'label'=>'En pose',    'icon'=>'🔧', 'color'=>'#3b82f6',    'bg'=>'rgba(59,130,246,0.08)',         'border'=>'rgba(59,130,246,0.3)'],
        ['val'=>'termine','label'=>'Terminées',  'icon'=>'✅', 'color'=>'#6b7280',    'bg'=>'rgba(107,114,128,0.08)',        'border'=>'rgba(107,114,128,0.3)'],
        ['val'=>'annule', 'label'=>'Annulées',   'icon'=>'🚫', 'color'=>'#ef4444',    'bg'=>'rgba(239,68,68,0.08)',          'border'=>'rgba(239,68,68,0.3)'],
    ];
    @endphp

    @foreach($statCards as $sc)
    <a href="{{ route('admin.campaigns.index',
        $sc['val'] !== 'all'
            ? array_merge(request()->except(['status','page']), ['status' => $sc['val']])
            : request()->except(['status','page'])
        ) }}"
       style="text-decoration:none;">
        <div style="background:{{ $sc['bg'] }};border:1px solid {{ $sc['border'] }};
                    border-radius:12px;padding:12px 14px;cursor:pointer;
                    transition:transform .15s,box-shadow .15s;
                    {{ (request('status') === $sc['val'] || ($sc['val']==='all' && !request('status')))
                        ? 'box-shadow:0 0 0 2px '.($sc['val']==='all'?'var(--accent)':$sc['color']).';'
                        : '' }}"
             onmouseover="this.style.transform='translateY(-2px)'"
             onmouseout="this.style.transform='translateY(0)'">
            <div style="font-size:18px;margin-bottom:5px;">{{ $sc['icon'] }}</div>
            <div style="font-size:22px;font-weight:800;color:{{ $sc['color'] }};line-height:1;">
                {{ $sc['val'] === 'all' ? $campaigns->total() : ($counts[$sc['val']] ?? 0) }}
            </div>
            <div style="font-size:10px;color:var(--text3);font-weight:600;
                        letter-spacing:.4px;margin-top:3px;">
                {{ strtoupper($sc['label']) }}
            </div>
        </div>
    </a>
    @endforeach
</div>

{{-- ══ FILTRES ══ --}}
<div style="background:var(--surface);border:1px solid var(--border);
            border-radius:12px;padding:14px 16px;margin-bottom:16px;">
    <form method="GET" action="{{ route('admin.campaigns.index') }}"
          style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">

        {{-- Recherche --}}
        <div style="flex:2;min-width:180px;">
            <label style="display:block;font-size:10px;color:var(--text3);font-weight:700;
                           text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">
                Recherche
            </label>
            <input type="text" name="search" value="{{ request('search') }}"
                   class="filter-input" style="width:100%;height:35px;"
                   placeholder="Nom de campagne…"/>
        </div>

        {{-- Client --}}
        <div>
            <label style="display:block;font-size:10px;color:var(--text3);font-weight:700;
                           text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">
                Client
            </label>
            <select name="client_id" class="filter-select" style="height:35px;">
                <option value="">Tous les clients</option>
                @foreach($clients as $client)
                <option value="{{ $client->id }}"
                        {{ request('client_id') == $client->id ? 'selected' : '' }}>
                    {{ $client->name }}
                </option>
                @endforeach
            </select>
        </div>

        {{-- Statut --}}
        <div>
            <label style="display:block;font-size:10px;color:var(--text3);font-weight:700;
                           text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">
                Statut
            </label>
            <select name="status" class="filter-select" style="height:35px;">
                <option value="">Tous</option>
                @foreach(\App\Enums\CampaignStatus::cases() as $s)
                <option value="{{ $s->value }}"
                        {{ request('status') === $s->value ? 'selected' : '' }}>
                    {{ $s->label() }}
                </option>
                @endforeach
            </select>
        </div>

        {{-- Du --}}
        <div>
            <label style="display:block;font-size:10px;color:var(--text3);font-weight:700;
                           text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">
                Du
            </label>
            <input type="date" name="date_from" value="{{ request('date_from') }}"
                   class="filter-input" style="height:35px;"/>
        </div>

        {{-- Au --}}
        <div>
            <label style="display:block;font-size:10px;color:var(--text3);font-weight:700;
                           text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">
                Au
            </label>
            <input type="date" name="date_to" value="{{ request('date_to') }}"
                   class="filter-input" style="height:35px;"/>
        </div>

        {{-- Non facturées — aligné avec les autres --}}
        <div>
            <label style="display:block;font-size:10px;color:transparent;font-weight:700;
                           text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">
                _
            </label>
            <label style="display:flex;align-items:center;gap:6px;height:35px;
                           padding:0 12px;background:var(--surface2);
                           border:1px solid var(--border2);border-radius:8px;
                           cursor:pointer;font-size:12px;color:var(--text2);
                           white-space:nowrap;">
                <input type="checkbox" name="non_facturee" value="1"
                       {{ request('non_facturee') ? 'checked' : '' }}
                       style="accent-color:var(--accent);width:14px;height:14px;">
                💰 Non facturées
            </label>
        </div>

        {{-- Boutons — alignés --}}
        <div>
            <label style="display:block;font-size:10px;color:transparent;font-weight:700;
                           text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">
                _
            </label>
            <div style="display:flex;gap:6px;">
                <button type="submit" class="btn btn-primary btn-sm"
                        style="height:35px;padding:0 16px;">
                    🔍 Filtrer
                </button>
                @if(request()->hasAny(['search','client_id','status','date_from','date_to','non_facturee']))
                <a href="{{ route('admin.campaigns.index') }}"
                   class="btn btn-ghost btn-sm"
                   style="height:35px;padding:0 12px;">✕</a>
                @endif
            </div>
        </div>
    </form>
</div>

{{-- ══ TABLEAU ══ --}}
<div style="background:var(--surface);border:1px solid var(--border);
            border-radius:12px;overflow:hidden;">
    <div style="padding:12px 16px;border-bottom:1px solid var(--border);
                display:flex;align-items:center;justify-content:space-between;">
        <span style="font-weight:700;font-size:14px;color:var(--text);">
            Campagnes
        </span>
        <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:12px;color:var(--text2);">
                {{ $campaigns->total() }} résultat(s)
            </span>
            @if(($nonFactureesCount ?? 0) > 0)
            <span style="font-size:11px;font-weight:600;color:var(--orange);
                         background:rgba(249,115,22,0.1);padding:3px 10px;
                         border-radius:20px;border:1px solid rgba(249,115,22,0.3);">
                💰 {{ $nonFactureesCount }} non facturée(s)
            </span>
            @endif
        </div>
    </div>

    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="border-bottom:1px solid var(--border);background:var(--surface2);">
                @foreach(['Campagne','Client','Période','Durée','Panneaux','Montant','Statut','Facturation','Créée par','Actions'] as $h)
                <th style="padding:10px 12px;text-align:left;font-size:10px;font-weight:700;
                           color:var(--text3);letter-spacing:.5px;text-transform:uppercase;
                           white-space:nowrap;">
                    {{ $h }}
                </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($campaigns as $campaign)
            @php
                $statusCfg = $campaign->status->uiConfig();
                // Utiliser invoices_count au lieu de $campaign->invoices->isEmpty()
                $isNonFacturee = in_array($campaign->status->value, ['actif','pose','termine'])
                                 && ($campaign->invoices_count ?? 0) === 0;

                // Progression
                $start   = $campaign->start_date->startOfDay();
                $end     = $campaign->end_date->startOfDay();
                $total   = (int) $start->diffInDays($end);
                $elapsed = (int) $start->diffInDays(now()->startOfDay());
                $pct     = $total > 0 ? min(100, max(0, (int)round($elapsed/$total*100))) : 0;

                // Fin proche
                $daysLeft   = max(0, (int)now()->startOfDay()->diffInDays($end, false));
                $endingSoon = $campaign->status->value === 'actif'
                              && $daysLeft > 0 && $daysLeft <= 14;
            @endphp
            <tr style="{{ $endingSoon ? 'background:rgba(232,160,32,0.03);' : '' }}
                        transition:background .12s;"
                onmouseover="this.style.background='var(--surface2)'"
                onmouseout="this.style.background='{{ $endingSoon ? 'rgba(232,160,32,0.03)' : '' }}'">

                {{-- Campagne --}}
                <td style="padding:12px 12px;">
                    <a href="{{ route('admin.campaigns.show', $campaign) }}"
                       style="font-weight:700;color:var(--text);text-decoration:none;
                              font-size:14px;display:block;">
                        {{ $campaign->name }}
                    </a>
                    @if($endingSoon)
                    <div style="font-size:10px;color:var(--accent);margin-top:2px;font-weight:600;">
                        ⚠️ Dans {{ $daysLeft }} jour(s)
                    </div>
                    @endif
                </td>

                {{-- Client --}}
                <td style="padding:12px 12px;">
                    @if($campaign->client?->trashed())
                    <span style="color:var(--text2);font-size:13px;">
                        {{ $campaign->client->name }}
                    </span>
                    <span style="font-size:9px;padding:1px 5px;
                                 background:rgba(239,68,68,.1);color:var(--red);
                                 border-radius:3px;margin-left:4px;">Supprimé</span>
                    @else
                    <a href="{{ route('admin.clients.show', $campaign->client) }}"
                       style="color:var(--text);text-decoration:none;font-weight:500;
                              font-size:13px;">
                        {{ $campaign->client?->name ?? '—' }}
                    </a>
                    @endif
                </td>

                {{-- Période --}}
                <td style="padding:12px 12px;font-size:12px;color:var(--text2);white-space:nowrap;">
                    {{ $campaign->start_date->format('d/m/Y') }}
                    <span style="color:var(--text3);margin:0 2px;">→</span>
                    {{ $campaign->end_date->format('d/m/Y') }}
                </td>

                {{-- Durée --}}
                <td style="padding:12px 12px;font-size:12px;color:var(--text3);white-space:nowrap;">
                    {{ $campaign->durationHuman() }}
                </td>

                {{-- Panneaux --}}
                <td style="padding:12px 12px;text-align:center;">
                    <span style="background:rgba(59,130,246,0.1);color:#60a5fa;
                                 padding:2px 8px;border-radius:6px;font-size:12px;
                                 font-weight:600;">
                        {{ $campaign->panels_count ?? 0 }} 🪧
                    </span>
                </td>

                {{-- Montant --}}
                <td style="padding:12px 12px;font-weight:700;color:var(--accent);
                           font-size:13px;white-space:nowrap;">
                    {{ number_format($campaign->total_amount, 0, ',', ' ') }}
                    <span style="font-size:10px;font-weight:400;color:var(--text3);">FCFA</span>
                </td>

                {{-- Statut --}}
                <td style="padding:12px 12px;">
                    <span style="padding:3px 9px;border-radius:20px;font-size:11px;
                                 font-weight:600;white-space:nowrap;
                                 background:{{ $statusCfg['bg'] }};
                                 color:{{ $statusCfg['color'] }};
                                 border:1px solid {{ $statusCfg['border'] }};">
                        {{ $statusCfg['icon'] }} {{ $campaign->status->label() }}
                    </span>
                    @if($campaign->status->value === 'actif')
                    <div style="margin-top:5px;background:var(--surface3);
                                border-radius:3px;height:3px;width:100%;overflow:hidden;">
                        <div style="background:{{ $pct >= 90 ? '#ef4444' : ($pct >= 70 ? '#e8a020' : '#22c55e') }};
                                    width:{{ $pct }}%;height:100%;border-radius:3px;">
                        </div>
                    </div>
                    <div style="font-size:9px;color:var(--text3);margin-top:1px;">
                        {{ $pct }}% écoulé
                        @if($daysLeft > 0 && $daysLeft <= 30)
                        · <span style="color:{{ $daysLeft <= 7 ? 'var(--red)' : 'var(--text3)' }};">
                            {{ $daysLeft }}j restants
                        </span>
                        @endif
                    </div>
                    @endif
                </td>

                {{-- Facturation --}}
                <td style="padding:12px 12px;">
                    @if($isNonFacturee)
                    <span style="font-size:11px;padding:2px 7px;border-radius:5px;
                                 background:rgba(249,115,22,0.1);color:var(--orange);
                                 border:1px solid rgba(249,115,22,0.3);white-space:nowrap;">
                        💰 À facturer
                    </span>
                    @elseif(($campaign->invoices_count ?? 0) > 0)
                    <span style="font-size:11px;color:var(--green);">
                        ✅ {{ $campaign->invoices_count }} facture(s)
                    </span>
                    @else
                    <span style="font-size:11px;color:var(--text3);">—</span>
                    @endif
                </td>

                {{-- Créée par --}}
                <td style="padding:12px 12px;font-size:11px;color:var(--text3);">
                    <div>{{ $campaign->user?->name ?? '—' }}</div>
                    <div style="font-size:10px;margin-top:1px;">
                        {{ $campaign->created_at->format('d/m/Y H:i') }}
                    </div>
                </td>

                {{-- Actions --}}
                <td style="padding:12px 12px;">
                    <div style="display:flex;gap:4px;">
                        <a href="{{ route('admin.campaigns.show', $campaign) }}"
                           class="btn btn-ghost btn-sm" title="Voir">👁</a>
                        @can('update', $campaign)
                        <a href="{{ route('admin.campaigns.edit', $campaign) }}"
                           class="btn btn-ghost btn-sm" title="Modifier">✏️</a>
                        @endcan
                        @can('delete', $campaign)
                        <button type="button"
                                onclick="openDeleteCampaign({{ $campaign->id }},
                                    '{{ addslashes($campaign->name) }}')"
                                class="btn btn-danger btn-sm" title="Supprimer">🗑</button>
                        @endcan
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="10"
                    style="text-align:center;padding:60px;color:var(--text3);">
                    <div style="font-size:32px;margin-bottom:8px;">📋</div>
                    Aucune campagne trouvée.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @if($campaigns->hasPages())
    <div style="padding:14px 16px;border-top:1px solid var(--border);
                display:flex;justify-content:flex-end;">
        {{ $campaigns->links() }}
    </div>
    @endif
</div>

{{-- Modal suppression --}}
<div id="modal-delete-campaign" class="modal-overlay" style="display:none;"
     onclick="if(event.target===this) closeDeleteCampaign()">
    <div class="modal" style="max-width:420px;" onclick="event.stopPropagation()">
        <div class="modal-header">
            <div class="modal-title" style="color:var(--red);">🗑 Supprimer la campagne</div>
            <button class="modal-close" onclick="closeDeleteCampaign()">✕</button>
        </div>
        <div class="modal-body" style="text-align:center;padding:28px 22px;">
            <div style="font-size:44px;margin-bottom:12px;">🗑</div>
            <div style="font-weight:700;font-size:15px;margin-bottom:8px;">
                Supprimer <span id="del-campaign-name"
                                style="color:var(--accent);"></span> ?
            </div>
            <div style="font-size:13px;color:var(--text2);margin-bottom:14px;">
                Suppression définitive. Les panneaux liés seront libérés.
            </div>
            <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);
                        border-radius:8px;padding:10px;font-size:12px;color:var(--red);">
                ⚠️ Uniquement possible si la campagne est annulée.
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeDeleteCampaign()">Annuler</button>
            <form id="del-campaign-form" method="POST" style="display:inline;">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger">🗑 Supprimer</button>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openDeleteCampaign(id, name) {
    document.getElementById('del-campaign-name').textContent = name;
    document.getElementById('del-campaign-form').action = `/admin/campaigns/${id}`;
    document.getElementById('modal-delete-campaign').style.display = 'flex';
}
function closeDeleteCampaign() {
    document.getElementById('modal-delete-campaign').style.display = 'none';
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeDeleteCampaign();
});
</script>
@endpush

</x-admin-layout>