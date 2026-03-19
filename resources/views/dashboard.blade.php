<x-admin-layout>
<x-slot name="title">Tableau de bord</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.panels.create') }}" class="btn btn-primary btn-sm">
        ＋ Nouveau panneau
    </a>
</x-slot>

<div class="mt-4">
<div style="display:flex; gap:20px; align-items:flex-start;">

    {{-- COLONNE GAUCHE --}}
    <div style="flex:1; min-width:0;">

        {{-- STAT CARDS --}}
        <div class="stats-grid" style="grid-template-columns: repeat(3,1fr);">

            <div class="stat-card">
                <div class="stat-label">Panneaux Actifs</div>
                <div class="stat-value">{{ $totalPanneaux }}</div>
                <div class="stat-delta up">↑ +12 ce trimestre</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Disponibles</div>
                <div class="stat-value" style="color:var(--green);">{{ $panneauxLibres }}</div>
                <div class="stat-delta" style="color:var(--text2);">Libres à la réservation</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">CA Mensuel (FCFA)</div>
                <div class="stat-value">48.2M</div>
                <div class="stat-delta up">↑ +8.4% vs N-1</div>
            </div>

        </div>

        {{-- CAMPAGNES ACTIVES --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">Campagnes actives</div>
                <div style="display:flex; gap:8px;">
                    <button class="btn btn-ghost btn-sm">📊 Excel</button>
                    <button class="btn btn-ghost btn-sm">Voir tout</button>
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
                    <tbody>
                        @forelse($campagnesRecentes as $campagne)
                        <tr>
                            <td><strong>{{ $campagne->client->name }}</strong></td>
                            <td>{{ $campagne->total_panels }}</td>
                            <td>{{ $campagne->end_date->format('d/m/y') }}</td>
                            <td>{{ $campagne->start_date->diffInDays($campagne->end_date) }}j</td>
                            <td style="width:100px;">
                                <div style="background:var(--surface3); border-radius:4px; height:6px;">
                                    <div style="background:var(--accent); height:6px; border-radius:4px; width:70%;"></div>
                                </div>
                            </td>
                            <td>
                                @if($campagne->status->value === 'actif')
                                    <span class="badge badge-green">Actif</span>
                                @elseif($campagne->status->value === 'pose')
                                    <span class="badge badge-blue">En pose</span>
                                @elseif($campagne->status->value === 'termine')
                                    <span class="badge badge-gray">Terminé</span>
                                @else
                                    <span class="badge badge-red">Annulé</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" style="text-align:center; color:var(--text3); padding:24px;">
                                Aucune campagne active
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
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
                <span class="badge badge-orange">{{ $reservationsEnAttente }} en attente</span>
            </div>
            <div class="card-body">
                @forelse($dernieresReservations as $reservation)
                <div style="padding-bottom:12px; margin-bottom:12px; border-bottom:1px solid var(--border);">
                    <div style="font-weight:600; font-size:13px;">
                        {{ $reservation->client->name }}
                    </div>
                    <div style="color:var(--text3); font-size:11px; margin-top:3px;">
                        {{ $reservation->panels->count() }} panneaux — En attente confirmation
                    </div>
                </div>
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
                <button class="btn btn-ghost btn-sm">Voir tout</button>
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
            </div>
            <div class="card-body">
                @forelse($dernieresMaintenances as $maintenance)
                <div style="padding-bottom:12px; margin-bottom:12px; border-bottom:1px solid var(--border);">
                    <div style="font-weight:600; font-size:13px;">
                        {{ $maintenance->panel->reference }}
                    </div>
                    <div style="color:var(--text3); font-size:11px; margin-top:2px;">
                        {{ $maintenance->type_panne }}
                    </div>
                    <div style="margin-top:5px;">
                        @if($maintenance->priorite === 'urgente')
                            <span class="badge badge-red">Urgente</span>
                        @elseif($maintenance->priorite === 'haute')
                            <span class="badge badge-orange">Haute</span>
                        @else
                            <span class="badge badge-gray">{{ ucfirst($maintenance->priorite) }}</span>
                        @endif
                    </div>
                </div>
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

</x-admin-layout>
