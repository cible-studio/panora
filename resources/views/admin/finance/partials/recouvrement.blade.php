{{-- Onglet RECOUVREMENT : clients à relancer + modale "+ Enregistrer une relance" --}}
@php $relancesReady = $relancesReady ?? true; @endphp

<div class="fin-card">
    <div class="fin-card-head">
        <div>
            <div class="fin-card-title">📞 Clients à relancer</div>
            <div class="fin-card-sub">{{ $clientsToFollow->count() }} client(s) avec reste à payer · triable</div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <select onchange="window.location.href = '?tab=recouvrement&sort=' + this.value" class="fin-sort">
                <option value="total_du"           {{ request('sort') === 'total_du' || !request('sort') ? 'selected' : '' }}>Trier par montant dû</option>
                <option value="ancien"             {{ request('sort') === 'ancien' ? 'selected' : '' }}>Trier par ancienneté</option>
                <option value="prochaine_echeance" {{ request('sort') === 'prochaine_echeance' ? 'selected' : '' }}>Trier par prochaine échéance</option>
            </select>
            @if($relancesReady)
            <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('modal-relance').style.display='flex'">
                + Enregistrer une relance
            </button>
            @endif
        </div>
    </div>
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
                            <th>Prochaine échéance</th>
                            <th>Dernière relance</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($clientsToFollow as $row)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.clients.show', $row['client_id']) }}" style="color:var(--accent);text-decoration:none;font-weight:600">{{ $row['client_name'] }}</a>
                                    @if($row['en_retard'])
                                        <span style="display:inline-block;margin-left:6px;padding:1px 6px;background:rgba(239,68,68,.15);color:#ef4444;border-radius:999px;font-size:10px;font-weight:700">🔴 EN RETARD</span>
                                    @endif
                                </td>
                                <td style="font-size:12px;color:var(--text3)">
                                    @if($row['client_phone'])📞 {{ $row['client_phone'] }}<br>@endif
                                    @if($row['client_email']){{ $row['client_email'] }}@endif
                                </td>
                                <td class="num">{{ $row['factures_count'] }}</td>
                                <td class="num strong">{{ $fmt($row['total_du']) }}</td>
                                <td class="num" style="color:{{ $row['plus_ancien_jours'] >= 60 ? '#ef4444' : ($row['plus_ancien_jours'] >= 30 ? '#f97316' : 'var(--text2)') }}">
                                    {{ $row['plus_ancien_jours'] }}j
                                </td>
                                <td style="color:{{ $row['prochaine_echeance'] && $row['prochaine_echeance']->isPast() ? '#ef4444' : 'var(--text2)' }}">
                                    {{ $row['prochaine_echeance']?->format('d/m/Y') ?? '—' }}
                                </td>
                                <td style="color:var(--text2);font-size:12px">
                                    {{ $row['derniere_relance'] ? \Carbon\Carbon::parse($row['derniere_relance'])->format('d/m/Y') : '— Jamais' }}
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
                        @foreach(\App\Models\Relance::CANAUX as $val => $lbl)
                            <option value="{{ $val }}">{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="fne-field">
                <label>Note de la relance <span class="req">*</span></label>
                <textarea name="note" rows="3" required placeholder="Résumé de l'échange…"></textarea>
            </div>
            <div class="fne-field">
                <label>Suite donnée <span class="opt">— optionnel</span></label>
                <textarea name="suite_donnee" rows="2" placeholder="Ex: rappeler le 15/06, dossier transmis au juridique…"></textarea>
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
}
</script>
