<x-admin-layout>
<x-slot name="title">Tableau de bord</x-slot>

<x-slot name="topbarActions">
    {{-- Barre de recherche --}}
    <div style="position:relative;">
        <input type="text" id="search-input"
               placeholder="🔍 Rechercher campagne, client..."
               oninput="filterTable(this.value)"
               style="
                  width:260px; height:36px; padding:0 12px 0 12px;
                  border:1px solid var(--border2); border-radius:8px;
                  background:var(--surface2); color:var(--text1);
                  font-size:13px; outline:none;
               ">
    </div>
    <a href="{{ route('admin.panels.create') }}" class="btn btn-primary btn-sm">
        ＋ Nouveau panneau
    </a>
</x-slot>

<div class="">
<div style="display:flex; gap:20px; align-items:flex-start;">

    {{-- COLONNE GAUCHE --}}
    <div style="flex:1; min-width:0;">

        {{-- STAT CARDS CLIQUABLES --}}
        <div class="stats-grid" style="grid-template-columns: repeat(3,1fr);">

            {{-- Card 1 : Total panneaux --}}
            <a href="{{ route('admin.panels.index') }}"
               id="card-all"
               onclick="filterByStatus('all', this)"
               style="text-decoration:none;"
               class="stat-card stat-card-clickable">
                <div class="stat-label">Panneaux Actifs</div>
                <div class="stat-value">{{ $totalPanneaux }}</div>
                <div class="stat-delta up">↑ Voir tous les panneaux →</div>
            </a>

            {{-- Card 2 : Disponibles --}}
            <a href="{{ route('admin.panels.index', ['status' => 'libre']) }}"
               id="card-libre"
               style="text-decoration:none;"
               class="stat-card stat-card-clickable">
                <div class="stat-label">Disponibles</div>
                <div class="stat-value" style="color:var(--green);">{{ $panneauxLibres }}</div>
                <div class="stat-delta" style="color:var(--text2);">Libres à la réservation →</div>
            </a>

            {{-- Card 3 : CA Mensuel --}}
            <a href="{{ route('admin.panels.index', ['status' => 'occupe']) }}"
               id="card-occupe"
               style="text-decoration:none;"
               class="stat-card stat-card-clickable">
                <div class="stat-label">CA Mensuel (FCFA)</div>
                <div class="stat-value">
                    @php
                        $ca = $caMensuel ?? 0;
                        echo $ca >= 1000000
                            ? number_format($ca/1000000, 1, '.', '').'M'
                            : number_format($ca, 0, ',', ' ');
                    @endphp
                </div>
                <div class="stat-delta up">
                    @if(isset($variationCA) && $variationCA !== null)
                        {{ $variationCA >= 0 ? '↑' : '↓' }} {{ abs($variationCA) }}% vs mois précédent →
                    @else
                        ↑ Voir panneaux occupés →
                    @endif
                </div>
            </a>

        </div>

        {{-- CAMPAGNES ACTIVES --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">Campagnes actives</div>
                <div style="display:flex; gap:8px;">
                    <span id="search-count" style="font-size:12px;color:var(--text3);align-self:center;"></span>
                    <a href="{{ route('admin.campaigns.index') }}" class="btn btn-ghost btn-sm">Voir tout</a>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Panneaux</th>
                            <th>Fin</th>
                            <th>Durée</th>
                            <th>Taux</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody id="campaigns-tbody">
                        @forelse($campagnesRecentes as $campagne)
                        <tr class="campaign-row"
                            data-client="{{ strtolower($campagne->client->name) }}"
                            data-status="{{ $campagne->status->value ?? $campagne->status }}">
                            <td><strong>{{ $campagne->client->name }}</strong></td>
                            <td>{{ $campagne->total_panels ?? $campagne->panels->count() }}</td>
                            <td>{{ $campagne->end_date->format('d/m/y') }}</td>
                            <td>{{ $campagne->start_date->diffInDays($campagne->end_date) }}j</td>
                            <td style="width:100px;">
                                <div style="background:var(--surface3); border-radius:4px; height:6px;">
                                    <div style="background:var(--accent); height:6px; border-radius:4px; width:70%;"></div>
                                </div>
                            </td>
                            <td>
                                @php $s = $campagne->status->value ?? $campagne->status; @endphp
                                @if($s === 'actif')
                                    <span class="badge badge-green">Actif</span>
                                @elseif($s === 'pose')
                                    <span class="badge badge-blue">En pose</span>
                                @elseif($s === 'termine')
                                    <span class="badge badge-gray">Terminé</span>
                                @else
                                    <span class="badge badge-red">Annulé</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr id="empty-row">
                            <td colspan="6" style="text-align:center; color:var(--text3); padding:24px;">
                                Aucune campagne active
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                {{-- Message aucun résultat --}}
                <div id="no-results"
                     style="display:none; text-align:center; color:var(--text3); padding:24px; font-size:13px;">
                    🔍 Aucun résultat pour cette recherche
                </div>
            </div>
        </div>

        {{-- TAUX D'OCCUPATION --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📊 Taux d'occupation par commune</div>
                <span class="badge badge-green">Mois en cours</span>
            </div>
            <div class="card-body">
                <div style="display:flex; flex-direction:column; gap:14px;">
                    @foreach($tauxParCommune as $commune)
                    <div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                            <span style="color:var(--text2); font-size:13px;">{{ $commune['nom'] }}</span>
                            @if($commune['taux'] >= 80)
                                <span style="color:var(--accent); font-weight:700; font-size:13px;">{{ $commune['taux'] }}%</span>
                            @elseif($commune['taux'] >= 60)
                                <span style="color:var(--green); font-weight:700; font-size:13px;">{{ $commune['taux'] }}%</span>
                            @else
                                <span style="color:var(--blue); font-weight:700; font-size:13px;">{{ $commune['taux'] }}%</span>
                            @endif
                        </div>
                        <div style="background:var(--surface3); border-radius:6px; height:20px;">
                            @if($commune['taux'] >= 80)
                                <div style="background:var(--accent); height:20px; border-radius:6px; width:{{ $commune['taux'] }}%; display:flex; align-items:center; padding-left:8px;">
                                    <span style="color:#000; font-size:11px; font-weight:700;">{{ $commune['taux'] }}%</span>
                                </div>
                            @elseif($commune['taux'] >= 60)
                                <div style="background:var(--green); height:20px; border-radius:6px; width:{{ $commune['taux'] }}%; display:flex; align-items:center; padding-left:8px;">
                                    <span style="color:#000; font-size:11px; font-weight:700;">{{ $commune['taux'] }}%</span>
                                </div>
                            @else
                                <div style="background:var(--blue); height:20px; border-radius:6px; width:{{ $commune['taux'] }}%; display:flex; align-items:center; padding-left:8px;">
                                    <span style="color:#fff; font-size:11px; font-weight:700;">{{ $commune['taux'] }}%</span>
                                </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

    </div>

    {{-- COLONNE DROITE --}}
    <div style="width:270px; flex-shrink:0; display:flex; flex-direction:column; gap:16px;">

        {{-- CONFIRMATIONS --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">⏳ Confirmations</div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <span class="badge badge-orange">{{ $reservationsEnAttente }} en attente</span>
                    <a href="{{ route('admin.reservations.index') }}" class="btn btn-ghost btn-sm">Voir tout</a>
                </div>
            </div>
            <div class="card-body">
                @forelse($dernieresReservations as $reservation)
                <a href="{{ route('admin.reservations.show', $reservation) }}"
                   style="text-decoration:none;display:block;padding-bottom:12px;margin-bottom:12px;
                          border-bottom:1px solid var(--border);border-radius:8px;padding:10px;
                          transition:background .15s;"
                   onmouseover="this.style.background='var(--surface2)'"
                   onmouseout="this.style.background=''">
                    <div style="font-weight:600;font-size:13px;color:var(--text);">
                        {{ $reservation->client->name }}
                    </div>
                    <div style="color:var(--text3);font-size:11px;margin-top:3px;">
                        {{ $reservation->panels->count() }} panneaux — En attente confirmation
                    </div>
                    <div style="font-size:10px;color:var(--accent);margin-top:4px;">
                        {{ $reservation->reference }} →
                    </div>
                </a>
                @empty
                <div style="color:var(--text3); font-size:13px; text-align:center; padding:8px 0;">
                    Aucune confirmation en attente
                </div>
                @endforelse
            </div>
        </div>

        {{-- ALERTES --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">🔔 Alertes</div>
                <a href="{{ route('admin.alerts.index') }}" class="btn btn-ghost btn-sm">Voir tout</a>
            </div>
            <div class="card-body">
                <div style="display:flex; flex-direction:column; gap:8px;">
                    @forelse($dernieresAlertes as $alerte)
                    @if($alerte->type === 'maintenance')
                        <div style="font-size:12px; padding:8px 10px; border-radius:6px; border-left:3px solid var(--red); background:var(--surface2); color:var(--red);">
                            {{ $alerte->title }}
                        </div>
                    @elseif($alerte->type === 'reservation')
                        <div style="font-size:12px; padding:8px 10px; border-radius:6px; border-left:3px solid var(--blue); background:var(--surface2); color:var(--blue);">
                            {{ $alerte->title }}
                        </div>
                    @elseif($alerte->type === 'facture')
                        <div style="font-size:12px; padding:8px 10px; border-radius:6px; border-left:3px solid var(--accent); background:var(--surface2); color:var(--accent);">
                            {{ $alerte->title }}
                        </div>
                    @else
                        <div style="font-size:12px; padding:8px 10px; border-radius:6px; border-left:3px solid var(--text3); background:var(--surface2); color:var(--text2);">
                            {{ $alerte->title }}
                        </div>
                    @endif
                    @empty
                    <div style="color:var(--text3); font-size:13px; text-align:center; padding:8px 0;">
                        Aucune alerte
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- MAINTENANCES --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">🔧 Maintenance</div>
                <a href="{{ route('admin.maintenances.index') }}" class="btn btn-ghost btn-sm">Voir tout</a>
            </div>
            <div class="card-body">
                @forelse($dernieresMaintenances as $maintenance)
                <a href="{{ route('admin.maintenances.show', $maintenance) }}"
                   style="text-decoration:none;display:block;padding:10px;border-radius:8px;
                          margin-bottom:8px;border-bottom:1px solid var(--border);
                          transition:background .15s;"
                   onmouseover="this.style.background='var(--surface2)'"
                   onmouseout="this.style.background=''">
                    <div style="font-weight:600;font-size:13px;color:var(--accent);">
                        {{ $maintenance->panel->reference }}
                    </div>
                    <div style="color:var(--text3);font-size:11px;margin-top:2px;">
                        {{ $maintenance->type_panne }}
                    </div>
                    <div style="margin-top:5px;display:flex;align-items:center;justify-content:space-between;">
                        @if($maintenance->priorite === 'urgente')
                            <span class="badge badge-red">Urgente</span>
                        @elseif($maintenance->priorite === 'haute')
                            <span class="badge badge-orange">Haute</span>
                        @else
                            <span class="badge badge-gray">{{ ucfirst($maintenance->priorite) }}</span>
                        @endif
                        <span style="font-size:10px;color:var(--text3);">Voir →</span>
                    </div>
                </a>
                @empty
                <div style="color:var(--text3); font-size:13px; text-align:center; padding:8px 0;">
                    Aucune maintenance urgente
                </div>
                @endforelse
            </div>
        </div>

    </div>

</div>
</div>

<style>
.stat-card-clickable {
    cursor: pointer;
    transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
    border: 2px solid transparent;
    display: block;
}
.stat-card-clickable:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0,0,0,.12);
    border-color: var(--accent);
}
.stat-card-clickable:active {
    transform: translateY(0);
}
</style>

<script>
function filterTable(query) {
    const rows     = document.querySelectorAll('.campaign-row');
    const noRes    = document.getElementById('no-results');
    const counter  = document.getElementById('search-count');
    const q        = query.trim().toLowerCase();
    let visible    = 0;

    rows.forEach(row => {
        const client = row.dataset.client || '';
        const match  = !q || client.includes(q);
        row.style.display = match ? '' : 'none';
        if (match) visible++;
    });

    // Message aucun résultat
    noRes.style.display = (visible === 0 && q) ? 'block' : 'none';

    // Compteur
    if (q) {
        counter.textContent = visible + ' résultat' + (visible > 1 ? 's' : '');
    } else {
        counter.textContent = '';
    }
}
</script>

</x-admin-layout>
