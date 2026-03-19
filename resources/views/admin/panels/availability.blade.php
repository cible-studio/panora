<x-admin-layout>
<x-slot name="title">Disponibilités — {{ $panel->reference }}</x-slot>

<div style="max-width:700px;">
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">📅 Disponibilités — {{ $panel->name }}</div>
                <div style="font-size:12px; color:var(--text3);">
                    Réf : <span style="color:var(--accent);">{{ $panel->reference }}</span>
                </div>
            </div>
            <a href="{{ route('admin.panels.show', $panel) }}" class="btn btn-ghost btn-sm">
                ← Retour
            </a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Début</th>
                        <th>Fin</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reservations as $reservation)
                    <tr>
                        <td><strong>{{ $reservation->client->name }}</strong></td>
                        <td>{{ $reservation->start_date->format('d/m/Y') }}</td>
                        <td>{{ $reservation->end_date->format('d/m/Y') }}</td>
                        <td>
                            @if($reservation->status->value === 'confirme')
                                <span class="badge badge-blue">Confirmé</span>
                            @elseif($reservation->status->value === 'en_attente')
                                <span class="badge badge-orange">En attente</span>
                            @else
                                <span class="badge badge-gray">{{ $reservation->status->value }}</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align:center; color:var(--text3); padding:24px;">
                            Aucune réservation — Panneau disponible ! 🎉
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

</x-admin-layout>
