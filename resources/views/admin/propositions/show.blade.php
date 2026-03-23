<x-admin-layout>
<x-slot name="title">{{ $proposition->numero }}</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.propositions.pdf', $proposition) }}" class="btn btn-ghost btn-sm">
        📄 Export PDF
    </a>
    <a href="{{ route('admin.propositions.edit', $proposition) }}" class="btn btn-ghost btn-sm">
        ✏️ Modifier
    </a>
</x-slot>

<div style="display:grid; grid-template-columns:1fr 300px; gap:20px;">

    {{-- COLONNE GAUCHE --}}
    <div>
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">{{ $proposition->numero }}</div>
                    <div style="font-size:12px; color:var(--text3); margin-top:3px;">
                        Créée le {{ $proposition->created_at->format('d/m/Y') }}
                        par {{ $proposition->creator->name }}
                    </div>
                </div>
                @if($proposition->statut === 'en_attente')
                    <span class="badge badge-orange" style="font-size:13px; padding:5px 14px;">En attente</span>
                @elseif($proposition->statut === 'acceptee')
                    <span class="badge badge-green" style="font-size:13px; padding:5px 14px;">Acceptée ✓</span>
                @elseif($proposition->statut === 'refusee')
                    <span class="badge badge-red" style="font-size:13px; padding:5px 14px;">Refusée</span>
                @else
                    <span class="badge badge-gray" style="font-size:13px; padding:5px 14px;">Expirée</span>
                @endif
            </div>
            <div class="card-body">
                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px;">
                    <div>
                        <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">CLIENT</div>
                        <div style="font-weight:600;">{{ $proposition->client->name }}</div>
                    </div>
                    <div>
                        <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">PANNEAUX</div>
                        <div style="font-weight:600;">{{ $proposition->nb_panneaux }}</div>
                    </div>
                    <div>
                        <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">MONTANT</div>
                        <div style="font-weight:600; color:var(--accent); font-size:16px;">
                            {{ number_format($proposition->montant, 0, ',', ' ') }} FCFA
                        </div>
                    </div>
                    <div>
                        <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">DATE DÉBUT</div>
                        <div style="font-weight:600;">{{ $proposition->date_debut->format('d/m/Y') }}</div>
                    </div>
                    <div>
                        <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">DATE FIN</div>
                        <div style="font-weight:600;">{{ $proposition->date_fin->format('d/m/Y') }}</div>
                    </div>
                    <div>
                        <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">DURÉE</div>
                        <div style="font-weight:600;">
                            {{ $proposition->date_debut->diffInDays($proposition->date_fin) }} jours
                        </div>
                    </div>
                </div>

                @if($proposition->notes)
                <div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--border);">
                    <div style="font-size:11px; color:var(--text3); margin-bottom:6px;">NOTES</div>
                    <div style="color:var(--text2);">{{ $proposition->notes }}</div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- COLONNE DROITE --}}
    <div>

        {{-- CHANGER STATUT --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">⚡ Changer statut</div>
            </div>
            <div class="card-body">
                <form method="POST"
                      action="{{ route('admin.propositions.update-status', $proposition) }}">
                    @csrf
                    @method('PATCH')
                    <div class="mfg">
                        <select name="statut">
                            <option value="en_attente" {{ $proposition->statut === 'en_attente' ? 'selected' : '' }}>
                                ⏳ En attente
                            </option>
                            <option value="acceptee" {{ $proposition->statut === 'acceptee' ? 'selected' : '' }}>
                                ✅ Acceptée
                            </option>
                            <option value="refusee" {{ $proposition->statut === 'refusee' ? 'selected' : '' }}>
                                ❌ Refusée
                            </option>
                            <option value="expiree" {{ $proposition->statut === 'expiree' ? 'selected' : '' }}>
                                ⌛ Expirée
                            </option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">
                        Mettre à jour
                    </button>
                </form>
            </div>
        </div>

        {{-- INFOS CLIENT --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">🏢 Client</div>
            </div>
            <div class="card-body">
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <div>
                        <div style="font-size:11px; color:var(--text3);">NOM</div>
                        <div style="font-weight:600;">{{ $proposition->client->name }}</div>
                    </div>
                    @if($proposition->client->email)
                    <div>
                        <div style="font-size:11px; color:var(--text3);">EMAIL</div>
                        <div>{{ $proposition->client->email }}</div>
                    </div>
                    @endif
                    @if($proposition->client->phone)
                    <div>
                        <div style="font-size:11px; color:var(--text3);">TÉLÉPHONE</div>
                        <div>{{ $proposition->client->phone }}</div>
                    </div>
                    @endif
                    <a href="{{ route('admin.clients.show', $proposition->client) }}"
                       class="btn btn-ghost btn-sm">
                        Voir la fiche client →
                    </a>
                </div>
            </div>
        </div>

    </div>

</div>

</x-admin-layout>
