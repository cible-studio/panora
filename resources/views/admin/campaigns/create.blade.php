<x-admin-layout title="Nouvelle campagne">

<x-slot:topbarLeft>
  <a href="{{ route('admin.campaigns.index') }}" class="btn btn-ghost">← Retour</a>
</x-slot:topbarLeft>

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
                    {{ $preselectedReservation->panels->count() + $preselectedReservation->externalPanels->count() }} panneau(x) ·
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
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                        <label style="font-size:11px;font-weight:700;color:var(--text3);
                                      letter-spacing:.5px;">
                            CLIENT *
                        </label>
                        <button type="button" onclick="openQuickClient()"
                                style="font-size:11px;color:var(--accent);background:none;border:none;cursor:pointer;font-weight:700;text-decoration:underline;padding:0;">
                            + Créer un client
                        </button>
                    </div>
                    <select name="client_id" id="campaign-client-select" x-model="selectedClientId"
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
                        @php $rTotal = $r->panels->count() + $r->externalPanels->count(); @endphp
                        <option value="{{ $r->id }}"
                                data-start="{{ $r->start_date->format('Y-m-d') }}"
                                data-end="{{ $r->end_date->format('Y-m-d') }}"
                                data-client="{{ $r->client_id }}"
                                data-amount="{{ $r->total_amount }}"
                                data-panels="{{ $rTotal }}"
                            {{ old('reservation_id', $preselectedReservation?->id) == $r->id ? 'selected' : '' }}>
                            {{ $r->reference }} — {{ $r->client?->name }}
                            ({{ $rTotal }} pan. ·
                            {{ number_format($r->total_amount, 0, ',', ' ') }} FCFA)
                        </option>
                        @endforeach
                    </select>
                    @error('reservation_id')
                    <p style="font-size:11px;color:var(--red);margin-top:4px;">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Commercial assigné --}}
                <div>
                    <label style="font-size:11px;font-weight:700;color:var(--text3);
                                  letter-spacing:.5px;display:block;margin-bottom:6px;">
                        COMMERCIAL ASSIGNÉ
                    </label>
                    <select name="commercial_user_id"
                            style="width:100%;background:var(--surface2);
                                   border:1px solid {{ $errors->has('commercial_user_id') ? 'var(--red)' : 'var(--border2)' }};
                                   border-radius:8px;padding:10px 14px;color:var(--text);
                                   font-size:13px;outline:none;cursor:pointer;
                                   box-sizing:border-box;">
                        <option value="">— Par défaut : moi-même —</option>
                        @foreach($commerciaux ?? [] as $c)
                            <option value="{{ $c->id }}" {{ old('commercial_user_id', auth()->id()) == $c->id ? 'selected' : '' }}>
                                {{ $c->name }} ({{ strtoupper((string) ($c->role?->value ?? $c->role ?? '')) }})
                            </option>
                        @endforeach
                    </select>
                    <p style="font-size:10px;color:var(--text3);margin-top:4px;">
                        Reçoit les notifications email + en-app pour suivre la campagne (poses, piges, fin imminente, etc.).
                    </p>
                    @error('commercial_user_id')
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

{{-- Select2 (recherche client + réservation) — CDN, isolé à cette page --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<style>
/* Harmonisation du visuel Select2 avec le thème Panora (variables CSS). */
.select2-container--default .select2-selection--single {
    background: var(--surface2) !important;
    border: 1px solid var(--border2) !important;
    border-radius: 8px !important;
    height: 42px !important;
    padding: 4px 6px !important;
    color: var(--text) !important;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: var(--text) !important;
    line-height: 32px !important;
    padding-left: 8px !important;
    font-size: 13px !important;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 40px !important;
    right: 6px !important;
}
.select2-container--default .select2-selection--single .select2-selection__placeholder {
    color: var(--text3) !important;
}
.select2-dropdown {
    background: var(--surface) !important;
    border: 1px solid var(--border) !important;
    border-radius: 10px !important;
    overflow: hidden !important;
}
.select2-container--default .select2-search--dropdown .select2-search__field {
    background: var(--surface2) !important;
    border: 1px solid var(--border2) !important;
    border-radius: 8px !important;
    color: var(--text) !important;
    padding: 8px 12px !important;
    font-size: 13px !important;
}
.select2-container--default .select2-results__option {
    padding: 9px 14px !important;
    font-size: 13px !important;
    color: var(--text) !important;
}
.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background: var(--accent) !important;
    color: #fff !important;
}
.select2-container--default .select2-results__option[aria-selected=true] {
    background: var(--surface2) !important;
    color: var(--text) !important;
}
.select2-container--open .select2-dropdown {
    box-shadow: 0 8px 24px rgba(0,0,0,0.18) !important;
}
.select2-container { width: 100% !important; }
</style>

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

            // Initialise Select2 sur le client + réservation (recherche).
            // On garde une référence "self" car jQuery casse le binding 'this'.
            const self = this;
            $(function() {
                const $client = $('#campaign-client-select');
                $client.select2({
                    placeholder: '🔍 Rechercher un client…',
                    allowClear: false,
                    width: '100%',
                    language: {
                        noResults: () => 'Aucun client trouvé',
                        searching: () => 'Recherche…',
                    },
                });
                // Quand on choisit via Select2, Alpine reçoit le 'change' natif
                // que select2 émet déjà — il n'y a donc rien à propager en plus.
                // On synchronise tout de même selectedClientId par sécurité.
                $client.on('change', function() { self.selectedClientId = this.value; });

                $('select[name="reservation_id"]').select2({
                    placeholder: 'Aucune réservation liée',
                    allowClear: true,
                    width: '100%',
                    language: {
                        noResults: () => 'Aucune réservation',
                        searching: () => 'Recherche…',
                    },
                }).on('change', function(e) {
                    // Réémet l'événement vers le handler Alpine (x-model
                    // intercepte 'change' DOM ; Select2 le dispatch lui-même).
                    const opt = this.options[this.selectedIndex];
                    self.fillFromOption(opt);
                });
            });
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
            if (client) {
                this.selectedClientId = client;
                // Force Select2 à afficher la nouvelle valeur sélectionnée.
                $('#campaign-client-select').val(client).trigger('change.select2');
            }
        }
    }
}
</script>
@endpush

{{-- ══ MODALE CRÉATION CLIENT RAPIDE ══════════════════════════════
     Permet de créer un client à la volée pendant la création d'une
     campagne sans quitter la page. Endpoint : admin.clients.quick-store
     (déjà utilisé sur /admin/disponibilites). ─────────────────────── --}}
<div id="quick-client-modal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);
            backdrop-filter:blur(4px);z-index:9999;align-items:center;
            justify-content:center;padding:16px;"
     onclick="if(event.target===this)closeQuickClient()">
    <div style="background:var(--surface);border:1px solid var(--border);
                border-radius:14px;width:100%;max-width:480px;padding:24px;
                position:relative;"
         onclick="event.stopPropagation()">
        <button type="button" onclick="closeQuickClient()"
                style="position:absolute;top:12px;right:14px;background:none;
                       border:none;color:var(--text3);cursor:pointer;font-size:20px;">✕</button>
        <h3 style="font-size:17px;font-weight:700;color:var(--text);margin-bottom:6px;">
            + Créer un nouveau client
        </h3>
        <p style="font-size:12px;color:var(--text3);margin-bottom:18px;">
            Le client sera créé et automatiquement sélectionné pour cette campagne.
        </p>
        <div id="quick-client-errors"
             style="display:none;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);
                    border-radius:8px;padding:10px 12px;font-size:12px;color:#ef4444;margin-bottom:12px;"></div>
        <div style="display:flex;flex-direction:column;gap:10px;">
            <div>
                <label style="font-size:11px;font-weight:700;color:var(--text3);display:block;margin-bottom:4px;">NOM *</label>
                <input id="qc-name" type="text" required
                       style="width:100%;background:var(--surface2);border:1px solid var(--border2);
                              border-radius:8px;padding:9px 12px;font-size:13px;color:var(--text);">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div>
                    <label style="font-size:11px;font-weight:700;color:var(--text3);display:block;margin-bottom:4px;">NCC</label>
                    <input id="qc-ncc" type="text"
                           style="width:100%;background:var(--surface2);border:1px solid var(--border2);
                                  border-radius:8px;padding:9px 12px;font-size:13px;color:var(--text);">
                </div>
                <div>
                    <label style="font-size:11px;font-weight:700;color:var(--text3);display:block;margin-bottom:4px;">TÉLÉPHONE</label>
                    <input id="qc-phone" type="text"
                           style="width:100%;background:var(--surface2);border:1px solid var(--border2);
                                  border-radius:8px;padding:9px 12px;font-size:13px;color:var(--text);">
                </div>
            </div>
            <div>
                <label style="font-size:11px;font-weight:700;color:var(--text3);display:block;margin-bottom:4px;">EMAIL</label>
                <input id="qc-email" type="email"
                       style="width:100%;background:var(--surface2);border:1px solid var(--border2);
                              border-radius:8px;padding:9px 12px;font-size:13px;color:var(--text);">
            </div>
            <div>
                <label style="font-size:11px;font-weight:700;color:var(--text3);display:block;margin-bottom:4px;">INTERLOCUTEUR</label>
                <input id="qc-contact" type="text"
                       style="width:100%;background:var(--surface2);border:1px solid var(--border2);
                              border-radius:8px;padding:9px 12px;font-size:13px;color:var(--text);">
            </div>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:18px;">
            <button type="button" onclick="closeQuickClient()"
                    style="padding:9px 16px;background:var(--surface2);border:1px solid var(--border2);
                           border-radius:8px;font-size:13px;color:var(--text2);cursor:pointer;">
                Annuler
            </button>
            <button id="qc-submit" type="button" onclick="submitQuickClient()"
                    style="padding:9px 20px;background:var(--accent);color:#fff;font-weight:700;
                           border-radius:8px;font-size:13px;border:none;cursor:pointer;
                           display:inline-flex;align-items:center;gap:6px;">
                <span id="qc-submit-icon">+</span>
                <span id="qc-submit-txt">Créer le client</span>
            </button>
        </div>
    </div>
</div>

<script>
function openQuickClient() {
    document.getElementById('quick-client-errors').style.display = 'none';
    ['qc-name','qc-ncc','qc-phone','qc-email','qc-contact'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    document.getElementById('qc-submit').disabled = false;
    document.getElementById('qc-submit-txt').textContent = 'Créer le client';
    document.getElementById('qc-submit-icon').textContent = '+';
    document.getElementById('quick-client-modal').style.display = 'flex';
    setTimeout(() => document.getElementById('qc-name')?.focus(), 100);
}
function closeQuickClient() {
    document.getElementById('quick-client-modal').style.display = 'none';
}
async function submitQuickClient() {
    const errBox = document.getElementById('quick-client-errors');
    const name = document.getElementById('qc-name').value.trim();
    if (!name) {
        errBox.textContent = '⚠️ Le nom est obligatoire.';
        errBox.style.display = 'block';
        document.getElementById('qc-name').focus();
        return;
    }
    const btn = document.getElementById('qc-submit');
    btn.disabled = true;
    document.getElementById('qc-submit-icon').textContent = '⟳';
    document.getElementById('qc-submit-txt').textContent = 'Création…';
    try {
        const r = await fetch('{{ route('admin.clients.quick-store') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            },
            body: JSON.stringify({
                name,
                ncc: document.getElementById('qc-ncc').value.trim() || null,
                phone: document.getElementById('qc-phone').value.trim() || null,
                email: document.getElementById('qc-email').value.trim() || null,
                contact_name: document.getElementById('qc-contact').value.trim() || null,
            }),
        });
        const data = await r.json();
        if (!r.ok || data.success === false) {
            errBox.textContent = '⚠️ ' + (data.message || 'Erreur lors de la création.');
            errBox.style.display = 'block';
            btn.disabled = false;
            document.getElementById('qc-submit-icon').textContent = '+';
            document.getElementById('qc-submit-txt').textContent = 'Créer le client';
            return;
        }
        // Le payload peut être { client: {...} } ou {id, name, ...} directement
        const created = data.client || data;
        if (!created || !created.id || !created.name) {
            errBox.textContent = '⚠️ Réponse inattendue du serveur — client créé mais non sélectionnable.';
            errBox.style.display = 'block';
            btn.disabled = false;
            document.getElementById('qc-submit-icon').textContent = '+';
            document.getElementById('qc-submit-txt').textContent = 'Créer le client';
            return;
        }
        // Injecte la nouvelle option dans le select + sélectionne
        const sel = document.getElementById('campaign-client-select');
        const opt = new Option(created.name, created.id, true, true);
        sel.add(opt);
        // Déclenche le binding Alpine (x-model) + rafraîchit Select2.
        sel.dispatchEvent(new Event('change', { bubbles: true }));
        if (window.jQuery) jQuery(sel).trigger('change.select2');
        closeQuickClient();
    } catch (e) {
        errBox.textContent = '⚠️ Erreur réseau : ' + e.message;
        errBox.style.display = 'block';
        btn.disabled = false;
        document.getElementById('qc-submit-icon').textContent = '+';
        document.getElementById('qc-submit-txt').textContent = 'Créer le client';
    }
}
</script>

</x-admin-layout>