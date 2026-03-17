<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            📊 Tableau de bord
        </h2>
    </x-slot>

    <div class="p-6">

        {{-- Stat Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

            {{-- Panneaux total --}}
            <div class="bg-white rounded-xl shadow p-5 border-l-4 border-blue-500">
                <div class="text-sm text-gray-500">Total Panneaux</div>
                <div class="text-3xl font-bold text-blue-600">{{ $totalPanneaux }}</div>
                <div class="text-xs text-gray-400 mt-1">Taux occupation : {{ $tauxOccupation }}%</div>
            </div>

            {{-- Panneaux libres --}}
            <div class="bg-white rounded-xl shadow p-5 border-l-4 border-green-500">
                <div class="text-sm text-gray-500">Panneaux Libres</div>
                <div class="text-3xl font-bold text-green-600">{{ $panneauxLibres }}</div>
                <div class="text-xs text-gray-400 mt-1">Disponibles maintenant</div>
            </div>

            {{-- Panneaux occupés --}}
            <div class="bg-white rounded-xl shadow p-5 border-l-4 border-yellow-500">
                <div class="text-sm text-gray-500">Panneaux Occupés</div>
                <div class="text-3xl font-bold text-yellow-600">{{ $panneauxOccupes }}</div>
                <div class="text-xs text-gray-400 mt-1">Option + Confirmé</div>
            </div>

            {{-- Maintenance --}}
            <div class="bg-white rounded-xl shadow p-5 border-l-4 border-red-500">
                <div class="text-sm text-gray-500">En Maintenance</div>
                <div class="text-3xl font-bold text-red-600">{{ $panneauxMaintenance }}</div>
                @if($maintenancesUrgentes > 0)
                    <div class="text-xs text-red-500 mt-1">⚠️ {{ $maintenancesUrgentes }} urgente(s)</div>
                @endif
            </div>

        </div>

        {{-- Deuxième ligne stats --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

            {{-- Réservations --}}
            <div class="bg-white rounded-xl shadow p-5">
                <div class="font-semibold text-gray-700 mb-3">📅 Réservations</div>
                <div class="flex justify-between">
                    <div>
                        <div class="text-2xl font-bold text-yellow-600">{{ $reservationsEnAttente }}</div>
                        <div class="text-xs text-gray-400">En attente</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-green-600">{{ $reservationsConfirmees }}</div>
                        <div class="text-xs text-gray-400">Confirmées</div>
                    </div>
                </div>
            </div>

            {{-- Campagnes --}}
            <div class="bg-white rounded-xl shadow p-5">
                <div class="font-semibold text-gray-700 mb-3">📢 Campagnes</div>
                <div class="flex justify-between">
                    <div>
                        <div class="text-2xl font-bold text-blue-600">{{ $campagnesActives }}</div>
                        <div class="text-xs text-gray-400">Actives</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-gray-600">{{ $campagnesTerminees }}</div>
                        <div class="text-xs text-gray-400">Terminées</div>
                    </div>
                </div>
            </div>

            {{-- Clients + Alertes --}}
            <div class="bg-white rounded-xl shadow p-5">
                <div class="font-semibold text-gray-700 mb-3">🏢 Clients & Alertes</div>
                <div class="flex justify-between">
                    <div>
                        <div class="text-2xl font-bold text-purple-600">{{ $totalClients }}</div>
                        <div class="text-xs text-gray-400">Clients</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-red-600">{{ $alertesNonLues }}</div>
                        <div class="text-xs text-gray-400">Alertes</div>
                    </div>
                </div>
            </div>

        </div>

        {{-- Tableaux --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Dernières réservations --}}
            <div class="bg-white rounded-xl shadow p-5">
                <div class="font-semibold text-gray-700 mb-4">📅 Dernières Réservations</div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-gray-400 border-b">
                            <th class="text-left pb-2">Référence</th>
                            <th class="text-left pb-2">Client</th>
                            <th class="text-left pb-2">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dernieresReservations as $reservation)
                        <tr class="border-b last:border-0">
                            <td class="py-2 font-mono text-xs">
                                {{ $reservation->reference }}
                            </td>
                            <td class="py-2">
                                {{ $reservation->client->name }}
                            </td>
                            <td class="py-2">
                                @php $status = $reservation->status->value; @endphp
                                @if($status === 'confirme')
                                    <span class="px-2 py-1 rounded-full text-xs bg-green-100 text-green-700">
                                        Confirmé
                                    </span>
                                @elseif($status === 'en_attente')
                                    <span class="px-2 py-1 rounded-full text-xs bg-yellow-100 text-yellow-700">
                                        En attente
                                    </span>
                                @elseif($status === 'refuse')
                                    <span class="px-2 py-1 rounded-full text-xs bg-red-100 text-red-700">
                                        Refusé
                                    </span>
                                @else
                                    <span class="px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-700">
                                        Annulé
                                    </span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="py-4 text-center text-gray-400">
                                Aucune réservation
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Maintenances en cours --}}
            <div class="bg-white rounded-xl shadow p-5">
                <div class="font-semibold text-gray-700 mb-4">🔧 Maintenances en cours</div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-gray-400 border-b">
                            <th class="text-left pb-2">Panneau</th>
                            <th class="text-left pb-2">Panne</th>
                            <th class="text-left pb-2">Priorité</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dernieresMaintenances as $maintenance)
                        <tr class="border-b last:border-0">
                            <td class="py-2">
                                {{ $maintenance->panel->reference }}
                            </td>
                            <td class="py-2">
                                {{ $maintenance->type_panne }}
                            </td>
                            <td class="py-2">
                                @if($maintenance->priorite === 'urgente')
                                    <span class="px-2 py-1 rounded-full text-xs bg-red-100 text-red-700">
                                        Urgente
                                    </span>
                                @elseif($maintenance->priorite === 'haute')
                                    <span class="px-2 py-1 rounded-full text-xs bg-yellow-100 text-yellow-700">
                                        Haute
                                    </span>
                                @elseif($maintenance->priorite === 'normale')
                                    <span class="px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-700">
                                        Normale
                                    </span>
                                @else
                                    <span class="px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-700">
                                        Faible
                                    </span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="py-4 text-center text-gray-400">
                                Aucune maintenance
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>

    </div>
</x-app-layout>
