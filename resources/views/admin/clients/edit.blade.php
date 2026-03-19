<x-admin-layout title="Modifier — {{ $client->name }}">

<x-slot:topbarActions>
    <a href="{{ route('admin.clients.show', $client) }}" class="btn btn-ghost">← Retour</a>
</x-slot:topbarActions>

<div style="max-width:680px;margin:0 auto;">

    <div style="font-size:12px;color:var(--text3);margin-bottom:16px;">
        <a href="{{ route('admin.clients.index') }}"
           style="color:var(--text3);text-decoration:none;">Clients</a>
        <span style="margin:0 6px;">›</span>
        <a href="{{ route('admin.clients.show', $client) }}"
           style="color:var(--text3);text-decoration:none;">{{ $client->name }}</a>
        <span style="margin:0 6px;">›</span>
        <span style="color:var(--text);">Modifier</span>
    </div>

    @if($errors->any())
    <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);
                border-radius:10px;padding:14px 16px;margin-bottom:16px;">
        @foreach($errors->all() as $error)
        <div style="color:var(--red);font-size:13px;display:flex;gap:6px;margin-bottom:3px;">
            <span>⚠️</span><span>{{ $error }}</span>
        </div>
        @endforeach
    </div>
    @endif

    <div style="background:var(--surface);border:1px solid var(--border);
                border-radius:14px;padding:28px 32px;">

        <h2 style="font-size:18px;font-weight:700;color:var(--text);margin-bottom:4px;">
            Modifier le client
        </h2>
        <p style="font-size:12px;color:var(--text3);margin-bottom:24px;">
            NCC : <span style="font-family:monospace;background:var(--surface3);
                               padding:2px 6px;border-radius:4px;">
                {{ $client->ncc ?? '—' }}
            </span>
            · Créé le {{ $client->created_at->format('d/m/Y') }}
        </p>

        <form method="POST" action="{{ route('admin.clients.update', $client) }}">
            @csrf @method('PUT')

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

                {{-- Nom --}}
                <div style="grid-column:1/-1;">
                    <label style="font-size:11px;font-weight:700;color:var(--text3);
                                  letter-spacing:.5px;display:block;margin-bottom:6px;">
                        NOM DE L'ENTREPRISE *
                    </label>
                    <input type="text" name="name"
                           value="{{ old('name', $client->name) }}"
                           style="width:100%;background:var(--surface2);
                                  border:1px solid {{ $errors->has('name') ? 'var(--red)' : 'var(--border2)' }};
                                  border-radius:8px;padding:10px 14px;color:var(--text);
                                  font-size:13px;outline:none;box-sizing:border-box;
                                  text-transform:uppercase;">
                    @error('name')
                    <p style="font-size:11px;color:var(--red);margin-top:4px;">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Secteur --}}
                <div>
                    <label style="font-size:11px;font-weight:700;color:var(--text3);
                                  letter-spacing:.5px;display:block;margin-bottom:6px;">
                        SECTEUR D'ACTIVITÉ
                    </label>
                    <select name="sector"
                            style="width:100%;background:var(--surface2);
                                   border:1px solid var(--border2);border-radius:8px;
                                   padding:10px 14px;color:var(--text);font-size:13px;outline:none;">
                        <option value="">— Sélectionner —</option>
                        @foreach($sectors as $sector)
                        <option value="{{ $sector }}"
                            {{ old('sector', $client->sector) === $sector ? 'selected' : '' }}>
                            {{ $sector }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Nom contact --}}
                <div>
                    <label style="font-size:11px;font-weight:700;color:var(--text3);
                                  letter-spacing:.5px;display:block;margin-bottom:6px;">
                        NOM DU CONTACT
                    </label>
                    <input type="text" name="contact_name"
                           value="{{ old('contact_name', $client->contact_name) }}"
                           style="width:100%;background:var(--surface2);border:1px solid var(--border2);
                                  border-radius:8px;padding:10px 14px;color:var(--text);
                                  font-size:13px;outline:none;box-sizing:border-box;">
                </div>

                {{-- Email --}}
                <div>
                    <label style="font-size:11px;font-weight:700;color:var(--text3);
                                  letter-spacing:.5px;display:block;margin-bottom:6px;">
                        EMAIL
                    </label>
                    <input type="email" name="email"
                           value="{{ old('email', $client->email) }}"
                           style="width:100%;background:var(--surface2);
                                  border:1px solid {{ $errors->has('email') ? 'var(--red)' : 'var(--border2)' }};
                                  border-radius:8px;padding:10px 14px;color:var(--text);
                                  font-size:13px;outline:none;box-sizing:border-box;">
                    @error('email')
                    <p style="font-size:11px;color:var(--red);margin-top:4px;">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Téléphone --}}
                <div>
                    <label style="font-size:11px;font-weight:700;color:var(--text3);
                                  letter-spacing:.5px;display:block;margin-bottom:6px;">
                        TÉLÉPHONE
                    </label>
                    <input type="text" name="phone"
                           value="{{ old('phone', $client->phone) }}"
                           style="width:100%;background:var(--surface2);
                                  border:1px solid {{ $errors->has('phone') ? 'var(--red)' : 'var(--border2)' }};
                                  border-radius:8px;padding:10px 14px;color:var(--text);
                                  font-size:13px;outline:none;box-sizing:border-box;">
                    @error('phone')
                    <p style="font-size:11px;color:var(--red);margin-top:4px;">{{ $message }}</p>
                    @enderror
                </div>

                {{-- NCC --}}
                <div>
                    <label style="font-size:11px;font-weight:700;color:var(--text3);
                                  letter-spacing:.5px;display:block;margin-bottom:6px;">
                        NCC
                    </label>
                    <input type="text" name="ncc"
                           value="{{ old('ncc', $client->ncc) }}"
                           style="width:100%;background:var(--surface2);
                                  border:1px solid {{ $errors->has('ncc') ? 'var(--red)' : 'var(--border2)' }};
                                  border-radius:8px;padding:10px 14px;color:var(--text);
                                  font-size:13px;outline:none;box-sizing:border-box;
                                  font-family:monospace;">
                    @error('ncc')
                    <p style="font-size:11px;color:var(--red);margin-top:4px;">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Adresse --}}
                <div style="grid-column:1/-1;">
                    <label style="font-size:11px;font-weight:700;color:var(--text3);
                                  letter-spacing:.5px;display:block;margin-bottom:6px;">
                        ADRESSE
                    </label>
                    <textarea name="address" rows="3"
                              style="width:100%;background:var(--surface2);
                                     border:1px solid var(--border2);border-radius:8px;
                                     padding:10px 14px;color:var(--text);font-size:13px;
                                     outline:none;resize:vertical;box-sizing:border-box;">{{ old('address', $client->address) }}</textarea>
                </div>

            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;
                        margin-top:24px;padding-top:20px;border-top:1px solid var(--border);">
                <a href="{{ route('admin.clients.show', $client) }}"
                   class="btn btn-ghost">Annuler</a>
                <button type="submit" class="btn btn-primary">
                    ✓ Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>
</div>

</x-admin-layout>