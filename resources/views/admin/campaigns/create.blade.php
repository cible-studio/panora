<x-admin-layout title="Nouvelle campagne">

<div style="max-width:720px;margin:0 auto;">

    <div style="font-size:12px;color:var(--text3);margin-bottom:16px;">
        <a href="{{ route('admin.campaigns.index') }}"
           style="color:var(--text3);text-decoration:none;">Campagnes</a>
        <span style="margin:0 6px;">›</span>
        <span style="color:var(--text);">Nouvelle campagne</span>
    </div>

    <div style="background:var(--surface);border:1px solid var(--border);
                border-radius:14px;padding:28px 32px;">

        <h2 style="font-size:18px;font-weight:700;color:var(--text);margin-bottom:24px;">
            Nouvelle campagne
        </h2>

        {{-- Alerte pré-remplissage --}}
        @if($preselectedReservation)
        <div style="margin-bottom:20px;padding:12px 16px;
                    background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.3);
                    border-radius:10px;font-size:13px;color:var(--green);
                    display:flex;align-items:center;gap:10px;">
            <span style="font-size:18px;">✓</span>
            <div>
                <strong>Pré-rempli depuis la réservation {{ $preselectedReservation->reference }}</strong><br>
                <span style="font-size:12px;opacity:.8;">
                    {{ $preselectedReservation->client?->name }} ·
                    {{ $preselectedReservation->panels->count() }} panneau(x) ·
                    {{ number_format($preselectedReservation->total_amount, 0, ',', ' ') }} FCFA ·
                    {{ $preselectedReservation->start_date->format('d/m/Y') }}
                    → {{ $preselectedReservation->end_date->format('d/m/Y') }}
                </span>
            </div>
        </div>
        @endif

        <form method="POST" action="{{ route('admin.campaigns.store') }}"
              x-data="campaignCreate()" x-init="init()">
            @csrf

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

                {{-- Nom --}}
                <div style="grid-column:1/-1;">
                    <label style="font-size:11px;font-weight:700;color:var(--text3);
                                  letter-spacing:.5px;display:block;margin-bottom:6px;">
                        NOM DE LA CAMPAGNE *
                    </label>
                    <input type="text" name="name"
                           value="{{ old('name', $preselectedReservation
                               ? 'Campagne ' . $preselectedReservation->client?->name . ' — ' . now()->format('Y')
                               : '') }}"
                           placeholder="Ex : Campagne NOËL 2026 — CLIENT X"
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
                    <select name="client_id" x-model="selectedClientId"
                            style="width:100%;background:var(--surface2);
                                   border:1px solid {{ $errors->has('client_id') ? 'var(--red)' : 'var(--border2)' }};
                                   border-radius:8px;padding:10px 14px;color:var(--text);
                                   font-size:13px;outline:none;">
                        <option value="">— Sélectionner —</option>
                        @foreach($clients as $client)
                        <option value="{{ $client->id }}"
                            {{ old('client_id', $preselectedReservation?->client_id) == $client->id ? 'selected' : '' }}>
                            {{ $client->name }}
                        </option>
                        @endforeach
                    </select>
                    @error('client_id')
                    <p style="font-size:11px;color:var(--red);margin-top:4px;">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Réservation liée --}}
                <div>
                    <label style="font-size:11px;font-weight:700;color:var(--text3);
                                  letter-spacing:.5px;display:block;margin-bottom:6px;">
                        RÉSERVATION CONFIRMÉE
                        <span style="font-weight:400;color:var(--text3);"> (optionnel)</span>
                    </label>
                    <select name="reservation_id"
                            @change="onReservationChange($event)"
                            style="width:100%;background:var(--surface2);
                                   border:1px solid var(--border2);border-radius:8px;
                                   padding:10px 14px;color:var(--text);font-size:13px;outline:none;">
                        <option value="">Aucune réservation liée</option>
                        @foreach($reservations as $r)
                        <option value="{{ $r->id }}"
                                data-start="{{ $r->start_date->format('Y-m-d') }}"
                                data-end="{{ $r->end_date->format('Y-m-d') }}"
                                data-client="{{ $r->client_id }}"
                                data-amount="{{ $r->total_amount }}"
                                data-panels="{{ $r->panels->count() }}"
                            {{ old('reservation_id', $preselectedReservation?->id) == $r->id ? 'selected' : '' }}>
                            {{ $r->reference }} — {{ $r->client?->name }}
                            ({{ $r->panels->count() }} pan. ·
                            {{ number_format($r->total_amount, 0, ',', ' ') }} FCFA)
                        </option>
                        @endforeach
                    </select>
                    @error('reservation_id')
                    <p style="font-size:11px;color:var(--red);margin-top:4px;">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Date début --}}
                <div>
                    <label style="font-size:11px;font-weight:700;color:var(--text3);
                                  letter-spacing:.5px;display:block;margin-bottom:6px;">
                        DATE DÉBUT *
                    </label>
                    <input type="date" name="start_date" x-ref="startDate"
                           value="{{ old('start_date', $preselectedReservation?->start_date->format('Y-m-d')) }}"
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
                    <input type="date" name="end_date" x-ref="endDate"
                           value="{{ old('end_date', $preselectedReservation?->end_date->format('Y-m-d')) }}"
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
                    <textarea name="notes" rows="3"
                              placeholder="Informations complémentaires..."
                              style="width:100%;background:var(--surface2);
                                     border:1px solid var(--border2);border-radius:8px;
                                     padding:10px 14px;color:var(--text);font-size:13px;
                                     outline:none;resize:vertical;box-sizing:border-box;">{{ old('notes') }}</textarea>
                </div>

            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;
                        margin-top:24px;padding-top:20px;border-top:1px solid var(--border);">
                <a href="{{ route('admin.campaigns.index') }}"
                   class="btn btn-ghost">Annuler</a>
                <button type="submit" class="btn btn-primary">
                    Créer la campagne
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function campaignCreate() {
    return {
        selectedClientId: '{{ old('client_id', $preselectedReservation?->client_id ?? '') }}',

        init() {
            // Si réservation pré-sélectionnée au chargement, déclencher le remplissage
            const select = this.$el.querySelector('select[name="reservation_id"]');
            if (select && select.value) {
                this.fillFromOption(select.options[select.selectedIndex]);
            }
        },

        onReservationChange(event) {
            const option = event.target.options[event.target.selectedIndex];
            this.fillFromOption(option);
        },

        fillFromOption(option) {
            if (! option || ! option.value) return;

            const start  = option.dataset.start;
            const end    = option.dataset.end;
            const client = option.dataset.client;

            if (start) this.$refs.startDate.value = start;
            if (end)   this.$refs.endDate.value   = end;
            if (client) this.selectedClientId     = client;
        }
    }
}
</script>
@endpush

</x-admin-layout>