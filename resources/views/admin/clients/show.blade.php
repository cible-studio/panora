<x-admin-layout title="Fiche client">

<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
  <a href="{{ route('admin.clients.index') }}" class="btn btn-ghost btn-sm">← Retour</a>
  <button class="btn btn-ghost btn-sm"
          @click="$dispatch('open-modal', {name: 'edit-client', data: {{ $client->toJson() }} })">
    ✏️ Modifier
  </button>
  <button class="btn btn-danger btn-sm"
          @click="$dispatch('open-modal', {name: 'delete-client', data: {id: {{ $client->id }}, name: '{{ addslashes($client->name) }}'}})">
    🗑️ Supprimer
  </button>
</div>

<div style="display:grid;grid-template-columns:280px 1fr;gap:16px;align-items:start;">

  {{-- Fiche identité --}}
  <div class="card" style="margin-bottom:0;">
    <div class="card-body" style="text-align:center;">
      <div class="avatar-circle" style="width:56px;height:56px;font-size:22px;margin:0 auto 12px;">
        {{ strtoupper(substr($client->name, 0, 1)) }}
      </div>
      <div style="font-family:var(--font-display);font-weight:700;font-size:16px;margin-bottom:4px;">
        {{ $client->name }}
      </div>
      @if($client->sector)
        <span class="badge badge-blue">{{ $client->sector }}</span>
      @endif
    </div>
    <div style="border-top:1px solid var(--border);padding:14px 17px;">
      @foreach([
        ['Contact',   $client->contact_name],
        ['Email',     $client->email],
        ['Téléphone', $client->phone],
        ['Adresse',   $client->address],
        ['Depuis',    $client->created_at->format('d/m/Y')],
      ] as [$label, $value])
        <div style="display:flex;gap:10px;padding:6px 0;border-bottom:1px solid var(--border);">
          <span style="font-size:11px;color:var(--text3);width:70px;flex-shrink:0;">{{ $label }}</span>
          <span style="font-size:13px;color:var(--text2);">{{ $value ?? '—' }}</span>
        </div>
      @endforeach
    </div>
  </div>

  {{-- Activité --}}
  <div>
    {{-- Stats --}}
    <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:16px;">
      <div class="stat-card">
        <div class="stat-label">Réservations</div>
        <div class="stat-value">{{ $client->reservations->count() }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Campagnes</div>
        <div class="stat-value" style="color:var(--blue);">{{ $client->campaigns->count() }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Factures</div>
        <div class="stat-value" style="color:var(--green);">{{ $client->invoices->count() }}</div>
      </div>
    </div>

    {{-- Campagnes récentes --}}
    <div class="card" style="margin-bottom:0;">
      <div class="card-header">
        <span class="card-title">Campagnes récentes</span>
      </div>
      <table>
        <thead>
          <tr>
            <th>Nom</th>
            <th>Période</th>
            <th>Panneaux</th>
            <th>Montant</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          @forelse($client->campaigns->take(8) as $campaign)
            <tr>
              <td style="font-weight:500;">{{ $campaign->name }}</td>
              <td style="color:var(--text2);font-size:12px;">
                {{ $campaign->start_date->format('d/m/Y') }} → {{ $campaign->end_date->format('d/m/Y') }}
              </td>
              <td style="color:var(--text2);">{{ $campaign->total_panels }}</td>
              <td style="color:var(--accent);font-weight:600;">
                {{ number_format($campaign->total_amount, 0, ',', ' ') }} FCFA
              </td>
              <td>
                @php
                  $statusMap = [
                    'actif'   => 'badge-green',
                    'pose'    => 'badge-blue',
                    'termine' => 'badge-gray',
                    'annule'  => 'badge-red',
                  ];
                @endphp
                <span class="badge {{ $statusMap[$campaign->status->value] ?? 'badge-gray' }}">
                  {{ ucfirst($campaign->status->value) }}
                </span>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" style="text-align:center;color:var(--text3);padding:24px;">
                Aucune campagne.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>

{{-- Modals réutilisées --}}
@include('admin.clients._modals')

</x-admin-layout>