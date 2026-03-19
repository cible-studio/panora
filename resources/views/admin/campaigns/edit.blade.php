<x-admin-layout title="Modifier — {{ $campaign->name }}">

<div style="max-width:720px;margin:0 auto;">

    <div style="font-size:12px;color:var(--text3);margin-bottom:16px;">
        <a href="{{ route('admin.campaigns.index') }}"
           style="color:var(--text3);text-decoration:none;">Campagnes</a>
        <span style="margin:0 6px;">›</span>
        <a href="{{ route('admin.campaigns.show', $campaign) }}"
           style="color:var(--text3);text-decoration:none;">{{ $campaign->name }}</a>
        <span style="margin:0 6px;">›</span>
        <span style="color:var(--text);">Modifier</span>
    </div>

    @if(session('error'))
    <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);
                border-radius:10px;padding:14px 16px;margin-bottom:16px;
                color:var(--red);font-size:13px;">
        ✕ {{ session('error') }}
    </div>
    @endif

    <div style="background:var(--surface);border:1px solid var(--border);
                border-radius:14px;padding:28px 32px;">

        <h2 style="font-size:18px;font-weight:700;color:var(--text);margin-bottom:4px;">
            Modifier la campagne
        </h2>
        <p style="font-size:12px;color:var(--text3);margin-bottom:24px;">
            Créée le {{ $campaign->created_at->format('d/m/Y') }}
            — {{ $campaign->panels_count ?? $campaign->panels->count() }} panneau(x)
        </p>

        <form method="POST" action="{{ route('admin.campaigns.update', $campaign) }}">
            @csrf @method('PUT')

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

                {{-- Nom --}}
                <div style="grid-column:1/-1;">
                    <label style="font-size:11px;font-weight:700;color:var(--text3);
                                  letter-spacing:.5px;display:block;margin-bottom:6px;">
                        NOM DE LA CAMPAGNE *
                    </label>
                    <input type="text" name="name"
                           value="{{ old('name', $campaign->name) }}"
                           style="width:100%;background:var(--surface2);
                                  border:1px solid {{ $errors->has('name') ? 'var(--red)' : 'var(--border2)' }};
                                  border-radius:8px;padding:10px 14px;color:var(--text);
                                  font-size:13px;outline:none;box-sizing:border-box;">
                    @error('name')
                    <p style="font-size:11px;color:var(--red);margin-top:4px;">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Client --}}
                <div>
                    <label style="font-size:11px;font-weight:700;color:var(--text3);
                                  letter-spacing:.5px;display:block;margin-bottom:6px;">
                        CLIENT *
                    </label>
                    <select name="client_id"
                            style="width:100%;background:var(--surface2);
                                   border:1px solid {{ $errors->has('client_id') ? 'var(--red)' : 'var(--border2)' }};
                                   border-radius:8px;padding:10px 14px;color:var(--text);
                                   font-size:13px;outline:none;">
                        @foreach($clients as $client)
                        <option value="{{ $client->id }}"
                            {{ old('client_id', $campaign->client_id) == $client->id ? 'selected' : '' }}>
                            {{ $client->name }}
                        </option>
                        @endforeach
                    </select>
                    @error('client_id')
                    <p style="font-size:11px;color:var(--red);margin-top:4px;">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Statut -- lecture seule --}}
                <div>
                    <label style="font-size:11px;font-weight:700;color:var(--text3);
                                  letter-spacing:.5px;display:block;margin-bottom:6px;">
                        STATUT ACTUEL
                    </label>
                    @php
                        $sc = match($campaign->status->value) {
                            'actif'   => ['#22c55e','rgba(34,197,94,0.1)','rgba(34,197,94,0.3)'],
                            'pose'    => ['#3b82f6','rgba(59,130,246,0.1)','rgba(59,130,246,0.3)'],
                            'termine' => ['#6b7280','rgba(107,114,128,0.1)','rgba(107,114,128,0.3)'],
                            'annule'  => ['#ef4444','rgba(239,68,68,0.1)','rgba(239,68,68,0.3)'],
                            default   => ['#6b7280','rgba(107,114,128,0.1)','rgba(107,114,128,0.3)'],
                        };
                    @endphp
                    <div style="padding:10px 14px;background:{{ $sc[1] }};
                                border:1px solid {{ $sc[2] }};border-radius:8px;
                                font-size:13px;font-weight:600;color:{{ $sc[0] }};">
                        {{ ucfirst($campaign->status->value) }}
                        <span style="font-size:11px;font-weight:400;color:var(--text3);
                                     margin-left:8px;">
                            — Changer depuis la fiche campagne
                        </span>
                    </div>
                </div>

                {{-- Date début --}}
                <div>
                    <label style="font-size:11px;font-weight:700;color:var(--text3);
                                  letter-spacing:.5px;display:block;margin-bottom:6px;">
                        DATE DÉBUT *
                    </label>
                    <input type="date" name="start_date"
                           value="{{ old('start_date', $campaign->start_date->format('Y-m-d')) }}"
                           style="width:100%;background:var(--surface2);
                                  border:1px solid {{ $errors->has('start_date') ? 'var(--red)' : 'var(--border2)' }};
                                  border-radius:8px;padding:10px 14px;color:var(--text);
                                  font-size:13px;outline:none;box-sizing:border-box;">
                    @error('start_date')
                    <p style="font-size:11px;color:var(--red);margin-top:4px;">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Date fin --}}
                <div>
                    <label style="font-size:11px;font-weight:700;color:var(--text3);
                                  letter-spacing:.5px;display:block;margin-bottom:6px;">
                        DATE FIN *
                    </label>
                    <input type="date" name="end_date"
                           value="{{ old('end_date', $campaign->end_date->format('Y-m-d')) }}"
                           min="{{ $campaign->start_date->addDay()->format('Y-m-d') }}"
                           style="width:100%;background:var(--surface2);
                                  border:1px solid {{ $errors->has('end_date') ? 'var(--red)' : 'var(--border2)' }};
                                  border-radius:8px;padding:10px 14px;color:var(--text);
                                  font-size:13px;outline:none;box-sizing:border-box;">
                    @error('end_date')
                    <p style="font-size:11px;color:var(--red);margin-top:4px;">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Notes --}}
                <div style="grid-column:1/-1;">
                    <label style="font-size:11px;font-weight:700;color:var(--text3);
                                  letter-spacing:.5px;display:block;margin-bottom:6px;">
                        NOTES INTERNES
                    </label>
                    <textarea name="notes" rows="4"
                              placeholder="Informations complémentaires, instructions de pose…"
                              style="width:100%;background:var(--surface2);
                                     border:1px solid {{ $errors->has('notes') ? 'var(--red)' : 'var(--border2)' }};
                                     border-radius:8px;padding:10px 14px;color:var(--text);
                                     font-size:13px;outline:none;resize:vertical;
                                     box-sizing:border-box;">{{ old('notes', $campaign->notes) }}</textarea>
                    @error('notes')
                    <p style="font-size:11px;color:var(--red);margin-top:4px;">{{ $message }}</p>
                    @enderror
                </div>

            </div>

            {{-- Avertissement si terminée --}}
            @if($campaign->status->value === 'termine')
            <div style="margin-top:16px;padding:12px 14px;
                        background:rgba(107,114,128,0.1);border:1px solid rgba(107,114,128,0.3);
                        border-radius:8px;font-size:12px;color:var(--text2);">
                ℹ️ Cette campagne est terminée. Vous pouvez la modifier puis la prolonger
                depuis la fiche campagne pour la réactiver.
            </div>
            @endif

            <div style="display:flex;justify-content:space-between;align-items:center;
                        gap:10px;margin-top:24px;padding-top:20px;
                        border-top:1px solid var(--border);">
                <a href="{{ route('admin.campaigns.show', $campaign) }}"
                   class="btn btn-ghost">Annuler</a>
                <button type="submit" class="btn btn-primary">
                    ✓ Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>
</div>

</x-admin-layout>