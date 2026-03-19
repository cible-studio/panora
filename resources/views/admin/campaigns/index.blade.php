<x-admin-layout title="Campagnes">

<x-slot:topbarActions>
    @can('create', App\Models\Campaign::class)
    <a href="{{ route('admin.campaigns.create') }}" class="btn btn-primary">
        + Nouvelle campagne
    </a>
    @endcan
</x-slot:topbarActions>

{{-- ══════════════════════════════════════════════════════════════
     STATS BADGES
══════════════════════════════════════════════════════════════ --}}
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:20px;">
    @php
    $statCards = [
        ['val'=>'all',    'label'=>'Total',       'icon'=>'📋', 'color'=>'var(--text)',   'bg'=>'var(--surface)',  'border'=>'var(--border)'],
        ['val'=>'actif',  'label'=>'En cours',    'icon'=>'📡', 'color'=>'#22c55e',       'bg'=>'rgba(34,197,94,0.08)',  'border'=>'rgba(34,197,94,0.3)'],
        ['val'=>'pose',   'label'=>'En pose',     'icon'=>'🔧', 'color'=>'#3b82f6',       'bg'=>'rgba(59,130,246,0.08)', 'border'=>'rgba(59,130,246,0.3)'],
        ['val'=>'termine','label'=>'Terminées',   'icon'=>'✅', 'color'=>'#6b7280',       'bg'=>'rgba(107,114,128,0.08)','border'=>'rgba(107,114,128,0.3)'],
        ['val'=>'annule', 'label'=>'Annulées',    'icon'=>'🚫', 'color'=>'#ef4444',       'bg'=>'rgba(239,68,68,0.08)',  'border'=>'rgba(239,68,68,0.3)'],
    ];
    @endphp

    @foreach($statCards as $sc)
    <a href="{{ route('admin.campaigns.index', $sc['val'] !== 'all' ? ['status' => $sc['val']] : []) }}"
       style="text-decoration:none;">
        <div style="background:{{ $sc['bg'] }};border:1px solid {{ $sc['border'] }};
                    border-radius:12px;padding:14px 16px;cursor:pointer;
                    transition:transform 0.15s,box-shadow 0.15s;
                    {{ request('status') === $sc['val'] || ($sc['val'] === 'all' && !request('status'))
                        ? 'box-shadow:0 0 0 2px '.($sc['val']==='all'?'var(--accent)':$sc['color']).';'
                        : '' }}"
             onmouseover="this.style.transform='translateY(-2px)'"
             onmouseout="this.style.transform='translateY(0)'">
            <div style="font-size:20px;margin-bottom:6px;">{{ $sc['icon'] }}</div>
            <div style="font-size:24px;font-weight:800;color:{{ $sc['color'] }};line-height:1;">
                {{ $sc['val'] === 'all' ? $campaigns->total() : ($counts[$sc['val']] ?? 0) }}
            </div>
            <div style="font-size:11px;color:var(--text3);font-weight:600;
                        letter-spacing:.4px;margin-top:4px;">
                {{ strtoupper($sc['label']) }}
            </div>
        </div>
    </a>
    @endforeach
</div>

{{-- ══════════════════════════════════════════════════════════════
     FILTRES
══════════════════════════════════════════════════════════════ --}}
<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="padding:14px 16px;">
        <form method="GET" action="{{ route('admin.campaigns.index') }}"
              style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">

            <div style="flex:2;min-width:200px;">
                <label style="display:block;font-size:11px;color:var(--text3);
                               text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px;">
                    Recherche
                </label>
                <input type="text" name="search" value="{{ request('search') }}"
                       class="filter-input" style="width:100%;"
                       placeholder="Nom de campagne, référence…"/>
            </div>

            <div>
                <label style="display:block;font-size:11px;color:var(--text3);
                               text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px;">
                    Client
                </label>
                <select name="client_id" class="filter-select">
                    <option value="">Tous les clients</option>
                    @foreach($clients as $client)
                    <option value="{{ $client->id }}"
                            {{ request('client_id') == $client->id ? 'selected' : '' }}>
                        {{ $client->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="display:block;font-size:11px;color:var(--text3);
                               text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px;">
                    Statut
                </label>
                <select name="status" class="filter-select">
                    <option value="">Tous</option>
                    @foreach(\App\Enums\CampaignStatus::cases() as $s)
                    <option value="{{ $s->value }}"
                            {{ request('status') === $s->value ? 'selected' : '' }}>
                        {{ $s->label() }}
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- Filtre période --}}
            <div>
                <label style="display:block;font-size:11px;color:var(--text3);
                               text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px;">
                    Du
                </label>
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="filter-input"/>
            </div>
            <div>
                <label style="display:block;font-size:11px;color:var(--text3);
                               text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px;">
                    Au
                </label>
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="filter-input"/>
            </div>

            {{-- Filtre non facturées --}}
            <div style="display:flex;align-items:center;gap:6px;padding-bottom:2px;">
                <input type="checkbox" name="non_facturee" value="1" id="cb-nf"
                       {{ request('non_facturee') ? 'checked' : '' }}
                       style="accent-color:var(--accent);width:15px;height:15px;">
                <label for="cb-nf" style="font-size:12px;color:var(--text2);cursor:pointer;
                                          white-space:nowrap;">
                    Non facturées
                </label>
            </div>

            <div style="display:flex;gap:6px;">
                <button type="submit" class="btn btn-primary">🔍 Filtrer</button>
                @if(request()->hasAny(['search','client_id','status','date_from','date_to','non_facturee']))
                    <a href="{{ route('admin.campaigns.index') }}" class="btn btn-ghost">↺ Reset</a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     TABLEAU
══════════════════════════════════════════════════════════════ --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">Campagnes</span>
        <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:12px;color:var(--text2);">
                {{ $campaigns->total() }} résultat(s)
            </span>
            @if(isset($nonFactureesCount) && $nonFactureesCount > 0)
                <span style="font-size:12px;font-weight:600;color:var(--orange);
                             background:rgba(249,115,22,0.1);padding:3px 10px;
                             border-radius:20px;border:1px solid rgba(249,115,22,0.3);">
                    💰 {{ $nonFactureesCount }} non facturée(s)
                </span>
            @endif
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Campagne</th>
                    <th>Client</th>
                    <th>Période</th>
                    <th>Durée</th>
                    <th>Panneaux</th>
                    <th>Montant</th>
                    <th>Statut</th>
                    <th>Facturation</th>
                    <th>Créée par</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($campaigns as $campaign)
                @php
                    $statusCfg = $campaign->status->uiConfig();
                    $isNonFacturee = in_array($campaign->status->value, ['actif','pose','termine'])
                                    && $campaign->invoices->isEmpty();
                @endphp
                <tr style="transition:background 0.12s;"
                    onmouseover="this.style.background='var(--surface2)'"
                    onmouseout="this.style.background=''">

                    <td>
                        <a href="{{ route('admin.campaigns.show', $campaign) }}"
                           style="font-weight:700;color:var(--text);text-decoration:none;
                                  font-size:14px;">
                            {{ $campaign->name }}
                        </a>
                        @if($campaign->reservation)
                        <div style="font-size:10px;color:var(--text3);margin-top:2px;
                                    font-family:monospace;">
                            {{ $campaign->reservation->reference }}
                        </div>
                        @endif
                    </td>

                    <td>
                        @if($campaign->client?->trashed())
                            <span style="color:var(--text2);">{{ $campaign->client->name }}</span>
                            <span style="font-size:10px;padding:1px 5px;
                                         background:rgba(239,68,68,.1);color:var(--red);
                                         border-radius:4px;margin-left:4px;">Supprimé</span>
                        @else
                            <a href="{{ route('admin.clients.show', $campaign->client) }}"
                               style="color:var(--text);text-decoration:none;font-weight:500;">
                                {{ $campaign->client?->name ?? '—' }}
                            </a>
                        @endif
                    </td>

                    <td style="font-size:12px;color:var(--text2);white-space:nowrap;">
                        {{ $campaign->start_date->format('d/m/Y') }}
                        <span style="color:var(--text3);margin:0 2px;">→</span>
                        {{ $campaign->end_date->format('d/m/Y') }}
                    </td>

                    <td style="font-size:12px;color:var(--text3);white-space:nowrap;">
                        {{ $campaign->durationInMonths() }} mois
                    </td>

                    <td style="text-align:center;">
                        <span class="badge badge-blue">
                            {{ $campaign->total_panels }} 🪧
                        </span>
                    </td>

                    <td style="font-weight:700;color:var(--accent);font-size:13px;
                               white-space:nowrap;">
                        {{ number_format($campaign->total_amount, 0, ',', ' ') }}
                        <span style="font-size:10px;font-weight:400;color:var(--text3);">FCFA</span>
                    </td>

                    <td>
                        {{-- Badge statut avec label UX clair --}}
                        <span style="padding:4px 10px;border-radius:20px;font-size:11px;
                                     font-weight:600;white-space:nowrap;
                                     background:{{ $statusCfg['bg'] }};
                                     color:{{ $statusCfg['color'] }};
                                     border:1px solid {{ $statusCfg['border'] }};">
                            {{ $statusCfg['icon'] }} {{ $campaign->status->label() }}
                        </span>
                        {{-- Barre progression si actif --}}
                        @if($campaign->status->value === 'actif')
                            @php
                                $total = $campaign->start_date->diffInDays($campaign->end_date);
                                $elapsed = $campaign->start_date->diffInDays(now());
                                $pct = $total > 0 ? min(100, round($elapsed / $total * 100)) : 0;
                            @endphp
                            <div style="margin-top:5px;background:var(--surface3);
                                        border-radius:3px;height:4px;width:100%;overflow:hidden;">
                                <div style="background:#22c55e;width:{{ $pct }}%;height:100%;
                                            border-radius:3px;transition:width 0.3s;"></div>
                            </div>
                            <div style="font-size:9px;color:var(--text3);margin-top:2px;">
                                {{ $pct }}% écoulé
                            </div>
                        @endif
                    </td>

                    <td>
                        @if($isNonFacturee)
                            <span style="font-size:11px;padding:3px 8px;border-radius:6px;
                                         background:rgba(249,115,22,0.1);color:var(--orange);
                                         border:1px solid rgba(249,115,22,0.3);white-space:nowrap;">
                                💰 À facturer
                            </span>
                        @elseif($campaign->invoices->isNotEmpty())
                            <span style="font-size:11px;color:var(--green);">
                                ✅ {{ $campaign->invoices->count() }} facture(s)
                            </span>
                        @else
                            <span style="font-size:11px;color:var(--text3);">—</span>
                        @endif
                    </td>

                    <td style="font-size:11px;color:var(--text3);">
                        <div>{{ $campaign->user?->name ?? '—' }}</div>
                        <div style="font-size:10px;">
                            {{ $campaign->created_at->format('d/m/Y H:i') }}
                        </div>
                    </td>

                    <td>
                        <div style="display:flex;gap:4px;">
                            <a href="{{ route('admin.campaigns.show', $campaign) }}"
                               class="btn btn-ghost btn-sm" title="Voir">👁</a>
                            @can('update', $campaign)
                            <a href="{{ route('admin.campaigns.edit', $campaign) }}"
                               class="btn btn-ghost btn-sm" title="Modifier">✏️</a>
                            @endcan
                            @can('delete', $campaign)
                            <button type="button"
                                    onclick="openDeleteCampaign({{ $campaign->id }}, '{{ addslashes($campaign->name) }}')"
                                    class="btn btn-danger btn-sm" title="Supprimer">🗑️</button>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" style="text-align:center;padding:60px;color:var(--text3);">
                        <div style="font-size:32px;margin-bottom:8px;">📋</div>
                        Aucune campagne trouvée.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($campaigns->hasPages())
        <div style="padding:14px 17px;border-top:1px solid var(--border);
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
            <div class="modal-title" style="color:var(--red);">🗑️ Supprimer la campagne</div>
            <button class="modal-close" onclick="closeDeleteCampaign()">✕</button>
        </div>
        <div class="modal-body" style="text-align:center;padding:28px 22px;">
            <div style="font-size:44px;margin-bottom:12px;">🗑️</div>
            <div style="font-weight:700;font-size:15px;margin-bottom:8px;">
                Supprimer <span id="del-campaign-name" style="color:var(--accent);"></span> ?
            </div>
            <div style="font-size:13px;color:var(--text2);margin-bottom:14px;">
                Cette campagne sera supprimée définitivement.
                Les panneaux liés seront libérés.
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
                <button type="submit" class="btn btn-danger">🗑️ Supprimer</button>
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