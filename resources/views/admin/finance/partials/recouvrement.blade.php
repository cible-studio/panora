{{-- Onglet RECOUVREMENT : clients à relancer + modale "+ Enregistrer une relance" --}}

<div class="fin-card">
    <div class="fin-card-head">
        <div>
            <div class="fin-card-title">📞 Clients à relancer</div>
            <div class="fin-card-sub">{{ $clientsToFollow->count() }} client(s) avec reste à payer · triable</div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            {{-- Lien explicite vers la page complète : on a déjà la liste ici
                 mais c'est la version "Clients à relancer" (1 ligne / client).
                 La page admin.finance.relances montre l'historique complet
                 (1 ligne / relance enregistrée) + filtres + stats par canal. --}}
            <a href="{{ route('admin.finance.relances') }}"
               class="btn btn-ghost btn-sm"
               title="Voir l'historique complet des relances (toutes traces enregistrées, filtres avancés)">
                📋 Voir l'historique complet
            </a>
            <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('modal-relance').style.display='flex'">
                + Enregistrer une relance
            </button>
        </div>
    </div>

    {{-- ═══ Phase 8D cahier §8 — Filtres recouvrement complets ═══
         Cahier : "Filtres par client, commercial, commune, statut."
         Tri "par montant dû / ancienneté" déplacé dans la même barre.
    ═══════════════════════════════════════════════════════════════ --}}
    <form method="GET" action="{{ route('admin.finance.index') }}" style="padding:10px 16px;background:var(--surface2);border-bottom:1px solid var(--border);display:flex;gap:10px;flex-wrap:wrap;align-items:center;font-size:12px">
        <input type="hidden" name="tab" value="recouvrement">
        <div style="display:flex;flex-direction:column;gap:2px">
            <label style="font-size:9.5px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.4px">Client</label>
            <select name="filter_client" class="fin-sort" style="min-width:160px">
                <option value="">Tous</option>
                @foreach($clientsList as $cl)
                    <option value="{{ $cl->id }}" {{ (string) request('filter_client') === (string) $cl->id ? 'selected' : '' }}>{{ $cl->name }}</option>
                @endforeach
            </select>
        </div>
        @if(!empty($commerciaux))
        <div style="display:flex;flex-direction:column;gap:2px">
            <label style="font-size:9.5px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.4px">Commercial</label>
            <select name="filter_commercial" class="fin-sort" style="min-width:140px">
                <option value="">Tous</option>
                @foreach($commerciaux as $u)
                    <option value="{{ $u->id }}" {{ (string) request('filter_commercial') === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        @if(!empty($communes))
        <div style="display:flex;flex-direction:column;gap:2px">
            <label style="font-size:9.5px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.4px">Commune</label>
            <select name="filter_commune" class="fin-sort" style="min-width:130px">
                <option value="">Toutes</option>
                @foreach($communes as $c)
                    <option value="{{ $c->id }}" {{ (string) request('filter_commune') === (string) $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div style="display:flex;flex-direction:column;gap:2px">
            <label style="font-size:9.5px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.4px">Tri</label>
            <select name="sort" class="fin-sort" style="min-width:170px">
                <option value="total_du"           {{ request('sort') === 'total_du' || !request('sort') ? 'selected' : '' }}>Par montant dû ↓</option>
                <option value="ancien"             {{ request('sort') === 'ancien' ? 'selected' : '' }}>Par ancienneté ↓</option>
                <option value="prochaine_echeance" {{ request('sort') === 'prochaine_echeance' ? 'selected' : '' }}>Par prochaine échéance</option>
            </select>
        </div>
        <div style="display:flex;flex-direction:column;gap:2px">
            <label style="visibility:hidden;font-size:9.5px">.</label>
            <button type="submit" class="btn btn-ghost btn-sm" style="height:34px;font-size:11.5px">🔍 Filtrer</button>
        </div>
        @if(request()->hasAny(['filter_client', 'filter_commercial', 'filter_commune', 'sort']))
        <div style="display:flex;flex-direction:column;gap:2px">
            <label style="visibility:hidden;font-size:9.5px">.</label>
            <a href="{{ route('admin.finance.index', ['tab' => 'recouvrement']) }}" class="btn btn-ghost btn-sm" style="height:34px;font-size:11px;color:var(--text3)">✕ Réinitialiser</a>
        </div>
        @endif
    </form>
    <div class="fin-card-body fin-card-body--flush">
        @if($clientsToFollow->isEmpty())
            <div class="fin-empty" style="margin:18px">Aucun client à relancer. 🎉</div>
        @else
            <div style="overflow-x:auto">
                <table class="fin-table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Contact</th>
                            <th class="num">Factures</th>
                            <th class="num">Total dû</th>
                            <th class="num">Ancien.</th>
                            <th title="Date d'échéance la plus proche d'une facture non payée (vient de l'échéancier)">Prochaine échéance facture</th>
                            <th title="Date de la dernière relance enregistrée">Dernière relance</th>
                            <th title="Suite donnée par le commercial après la dernière relance (rappeler le X, RDV le Y…)">Suite à donner</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($clientsToFollow as $row)
                            @php
                                // Phase 5 cahier §8 — priorité 4 niveaux selon l'ancienneté
                                $prio = \App\Services\ReminderService::priorityForOverdueDays((int) $row['plus_ancien_jours']);
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('admin.clients.show', ['client' => $row['client_id'], 'back' => 'finance']) }}" style="color:var(--accent);text-decoration:none;font-weight:600">{{ $row['client_name'] }}</a>
                                    {{-- Badge priorité 4 niveaux (§8 cahier) --}}
                                    <span style="display:inline-flex;align-items:center;gap:3px;margin-left:6px;padding:1px 8px;background:{{ $prio['bg'] }};color:{{ $prio['color'] }};border-radius:999px;font-size:9.5px;font-weight:800;letter-spacing:.3px;text-transform:uppercase">
                                        {{ $prio['icon'] }} {{ $prio['label'] }}
                                    </span>
                                    @if($row['en_retard'])
                                        <span style="display:inline-block;margin-left:4px;padding:1px 6px;background:rgba(239,68,68,.10);color:#b91c1c;border-radius:999px;font-size:9.5px;font-weight:700">EN RETARD</span>
                                    @endif
                                </td>
                                <td style="font-size:12px;color:var(--text3)">
                                    @if($row['client_phone'])
                                        📞 <a href="tel:{{ preg_replace('/[^\d+]/', '', $row['client_phone']) }}"
                                              style="color:var(--text3);text-decoration:none;border-bottom:1px dotted var(--border)"
                                              title="Appeler le client">{{ $row['client_phone'] }}</a><br>
                                    @endif
                                    @if($row['client_email'])
                                        <a href="mailto:{{ $row['client_email'] }}"
                                           style="color:var(--text3);text-decoration:none;border-bottom:1px dotted var(--border)"
                                           title="Envoyer un email au client">{{ $row['client_email'] }}</a>
                                    @endif
                                </td>
                                <td class="num">
                                    {{-- Lien vers la liste des factures impayées de ce client. --}}
                                    <a href="{{ route('admin.invoices.index', ['client_id' => $row['client_id'], 'pay_status' => 'non_payee']) }}"
                                       style="color:var(--accent);text-decoration:none;font-weight:600"
                                       title="Voir les factures impayées du client">{{ $row['factures_count'] }}</a>
                                </td>
                                <td class="num strong">{{ $fmt($row['total_du']) }}</td>
                                <td class="num" style="color:{{ $prio['color'] }};font-weight:700">
                                    {{ $row['plus_ancien_jours'] }}j
                                </td>
                                <td style="color:{{ $row['prochaine_echeance'] && $row['prochaine_echeance']->isPast() ? '#ef4444' : 'var(--text2)' }}"
                                    title="Date de la prochaine échéance non payée d'une facture du client (depuis l'échéancier). « — » = aucune facture n'a d'échéancier configuré.">
                                    @if($row['prochaine_echeance'])
                                        {{ $row['prochaine_echeance']->format('d/m/Y') }}
                                    @else
                                        <span style="color:var(--text3);font-size:11px;font-style:italic"
                                              title="Aucune des factures impayées de ce client n'a d'échéancier configuré. Génère un échéancier depuis la fiche facture pour activer le suivi.">
                                            Pas d'échéancier
                                        </span>
                                    @endif
                                </td>
                                <td style="color:var(--text2);font-size:12px">
                                    @if($row['derniere_relance'])
                                        <a href="{{ route('admin.finance.relances', ['client_id' => $row['client_id']]) }}"
                                           style="color:var(--text2);text-decoration:none;border-bottom:1px dashed var(--border)"
                                           title="Voir toutes les relances de ce client">
                                            {{ \Carbon\Carbon::parse($row['derniere_relance'])->format('d/m/Y') }}
                                        </a>
                                        @if(!empty($row['derniere_relance_user']))
                                            <div style="font-size:10.5px;color:var(--text3);margin-top:2px">
                                                par {{ $row['derniere_relance_user'] }}
                                            </div>
                                        @endif
                                    @else
                                        <span style="color:var(--text3)">— Jamais</span>
                                    @endif
                                </td>
                                <td style="font-size:12px;color:var(--text2);max-width:220px">
                                    @if(!empty($row['derniere_relance_suite']))
                                        <span title="Suite donnée par le commercial après la dernière relance">
                                            💬 {{ \Illuminate\Support\Str::limit($row['derniere_relance_suite'], 60) }}
                                        </span>
                                    @elseif(!empty($row['derniere_relance_outcome']))
                                        @php
                                            $outcomeLabel = match($row['derniere_relance_outcome']) {
                                                'promesse_paiement' => '📅 Promesse de paiement',
                                                'paiement_recu'     => '✅ Paiement reçu',
                                                'a_relancer'        => '🔁 À relancer',
                                                'sans_reponse'      => '📵 Sans réponse',
                                                'desaccord'         => '⚠ Désaccord',
                                                'autre'             => '📝 Autre',
                                                default             => $row['derniere_relance_outcome'],
                                            };
                                        @endphp
                                        <span style="color:var(--text3)">{{ $outcomeLabel }}</span>
                                    @else
                                        <span style="color:var(--text3)">—</span>
                                    @endif
                                </td>
                                <td style="text-align:right">
                                    <button type="button" class="btn btn-ghost btn-sm"
                                            onclick="finOpenRelanceModal({{ $row['client_id'] }}, '{{ addslashes($row['client_name']) }}')">
                                        + Relancer
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- ════ MODALE ENREGISTRER UNE RELANCE ════ --}}
<div id="modal-relance" class="fin-modal-overlay" onclick="if(event.target===this)this.style.display='none'" style="display:none">
    <div class="fin-modal">
        <div class="fin-modal-head">
            <div style="font-weight:800;font-size:15px">📞 Enregistrer une relance</div>
            <button type="button" onclick="document.getElementById('modal-relance').style.display='none'" style="background:none;border:none;font-size:18px;cursor:pointer;color:var(--text3)">✕</button>
        </div>
        <form method="POST" action="{{ route('admin.finance.relances.store') }}" class="fin-modal-body">
            @csrf
            <div class="fne-field">
                <label>Client <span class="req">*</span></label>
                <select name="client_id" id="modal-client-id" required>
                    <option value="">— Sélectionner —</option>
                    @foreach($clientsList as $cl)
                        <option value="{{ $cl->id }}">{{ $cl->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="fne-grid-2">
                <div class="fne-field">
                    <label>Date de la relance <span class="req">*</span></label>
                    <input type="date" name="relance_date" value="{{ now()->format('Y-m-d') }}" required>
                </div>
                <div class="fne-field">
                    <label>Canal <span class="req">*</span></label>
                    <select name="canal" required>
                        @foreach(\App\Services\ReminderService::CANALS as $val)
                            <option value="{{ $val }}">{{ \App\Services\ReminderService::canalLabel($val) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="fne-field">
                <label>Note de la relance <span class="req">*</span></label>
                <textarea name="note" rows="3" required placeholder="Résumé de l'échange…"></textarea>
            </div>
            <div class="fne-grid-2">
                <div class="fne-field">
                    <label>Résultat <span class="opt">— optionnel</span></label>
                    <select name="outcome">
                        <option value="">—</option>
                        <option value="promesse_paiement">📅 Promesse de paiement</option>
                        <option value="paiement_recu">✅ Paiement reçu</option>
                        <option value="a_relancer">🔁 À relancer</option>
                        <option value="sans_reponse">📵 Sans réponse</option>
                        <option value="desaccord">⚠ Désaccord</option>
                        <option value="autre">📝 Autre</option>
                    </select>
                </div>
                <div class="fne-field">
                    <label>Suite donnée <span class="opt">— optionnel</span></label>
                    <input type="text" name="suite_donnee" placeholder="Ex: rappeler le 15/06…" maxlength="200">
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:14px;padding-top:14px;border-top:1px solid var(--border)">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-relance').style.display='none'">Annuler</button>
                <button type="submit" class="btn btn-primary">✅ Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<style>
.fin-sort {
    height: 34px;
    padding: 0 28px 0 10px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text);
    font-size: 12.5px;
    cursor: pointer;
    -webkit-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
    background-repeat: no-repeat;
    background-position: right 8px center;
}
.fin-modal-overlay {
    position: fixed; inset: 0;
    background: rgba(15, 23, 42, .6);
    backdrop-filter: blur(2px);
    z-index: 9999;
    display: flex; align-items: center; justify-content: center;
    padding: 16px;
}
.fin-modal {
    background: var(--surface);
    border-radius: 14px;
    max-width: 520px;
    width: 100%;
    box-shadow: 0 30px 80px -20px rgba(0, 0, 0, .4);
    overflow: hidden;
}
.fin-modal-head {
    padding: 14px 18px;
    border-bottom: 1px solid var(--border);
    background: var(--surface2);
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: var(--text);
}
.fin-modal-body { padding: 18px; }
.fin-modal-body .fne-field { margin-bottom: 12px; }
.fin-modal-body .fne-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.fin-modal-body label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--text2); margin-bottom: 6px; }
.fin-modal-body label .req { color: #ef4444; }
.fin-modal-body label .opt { font-size: 10px; color: var(--text3); font-weight: 500; text-transform: none; letter-spacing: 0; }
.fin-modal-body input, .fin-modal-body select, .fin-modal-body textarea {
    width: 100%;
    padding: 8px 10px;
    height: 38px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 13px;
    background: var(--surface);
    color: var(--text);
    font-family: inherit;
    outline: none;
}
.fin-modal-body textarea { height: auto; min-height: 60px; resize: vertical; line-height: 1.5; padding: 8px 10px; }
.fin-modal-body input:focus, .fin-modal-body select:focus, .fin-modal-body textarea:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(232, 160, 32, .15);
}
</style>

<script>
function finOpenRelanceModal(clientId, clientName) {
    const sel = document.getElementById('modal-client-id');
    if (sel) sel.value = clientId;
    document.getElementById('modal-relance').style.display = 'flex';
    // Focus auto sur le champ note pour gagner du temps de saisie
    setTimeout(() => {
        const note = document.querySelector('#modal-relance textarea[name="note"]');
        if (note) note.focus();
    }, 50);
}

// Auto-ouverture si l'URL contient ?open_relance=1&client_id=X — déclenché
// depuis la page Historique des relances quand on clique "Relancer" sur
// une ligne "à donner suite". Permet d'enchaîner sans clic supplémentaire.
document.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);
    if (params.get('open_relance') === '1') {
        const cid = parseInt(params.get('client_id'), 10);
        if (cid) finOpenRelanceModal(cid, '');
    }
});
</script>
