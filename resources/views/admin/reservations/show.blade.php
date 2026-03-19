<x-admin-layout title="Réservation {{ $reservation->reference }}">

{{-- ══════════════════════════════════════════════════════
     TOPBAR ACTIONS
══════════════════════════════════════════════════════ --}}
<x-slot:topbarActions>
    <a href="{{ route('admin.reservations.index') }}"
       class="btn btn-ghost text-sm">← Retour aux réservations</a>

    @if($can['update'])
        <a href="{{ route('admin.reservations.edit', $reservation) }}"
           class="btn btn-ghost text-sm">✏️ Modifier</a>
    @endif

    @if($can['delete'])
        <form method="POST"
              action="{{ route('admin.reservations.destroy', $reservation) }}"
              onsubmit="return confirm('Supprimer définitivement cette réservation ?')">
            @csrf @method('DELETE')
            <button class="btn btn-danger text-sm">🗑️ Supprimer</button>
        </form>
    @endif
</x-slot>

{{-- ══════════════════════════════════════════════════════
     ALERTE CLIENT SUPPRIMÉ
══════════════════════════════════════════════════════ --}}
@if($reservation->client?->trashed())
<div class="mx-0 mb-4 px-4 py-3 rounded-lg border"
     style="background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.25)">
    <div class="flex items-center gap-2">
        <span style="color:var(--red)">⚠️</span>
        <span class="text-sm font-medium" style="color:var(--red)">
            Client supprimé — cette réservation est conservée à titre d'historique uniquement.
            Aucune modification n'est possible.
        </span>
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════
     EN-TÊTE RÉSERVATION
══════════════════════════════════════════════════════ --}}
<div class="card mb-4">
    <div class="card-body">

        {{-- Référence + Badge statut --}}
        <div class="flex items-center gap-3 mb-5">
            <span class="font-mono font-bold text-base px-3 py-1 rounded"
                  style="background:var(--surface2);color:var(--text)">
                {{ $reservation->reference }}
            </span>
            <span class="badge badge-{{ $reservation->status->badgeClass() }}">
                {{ $reservation->status->label() }}
            </span>
            <span class="badge" style="background:var(--surface2);color:var(--text2)">
                {{ $reservation->type === 'ferme' ? '🔒 Ferme' : '⏳ Option' }}
            </span>
            @if($reservation->status->value === 'annule')
                <span class="text-xs px-2 py-1 rounded"
                      style="background:rgba(239,68,68,.1);color:var(--red)">
                    🗄️ Archivé — lecture seule
                </span>
            @endif
        </div>

        {{-- Infos principales --}}
        <div class="grid grid-cols-3 gap-6">
            <div>
                <div class="text-xs uppercase tracking-wider mb-1" style="color:var(--text3)">Client</div>
                @if($reservation->client?->trashed())
                    <div class="font-semibold" style="color:var(--text2)">
                        {{ $reservation->client->name }}
                        <span class="text-xs ml-1 px-1.5 py-0.5 rounded"
                              style="background:rgba(239,68,68,.1);color:var(--red)">Supprimé</span>
                    </div>
                @else
                    <a href="{{ route('admin.clients.show', $reservation->client) }}"
                       class="font-semibold hover:underline" style="color:var(--text)">
                        {{ $reservation->client?->name ?? '—' }}
                    </a>
                @endif
            </div>
            <div>
                <div class="text-xs uppercase tracking-wider mb-1" style="color:var(--text3)">Période</div>
                <div class="font-semibold">
                    {{ $reservation->start_date->format('d/m/Y') }}
                    → {{ $reservation->end_date->format('d/m/Y') }}
                </div>
                <div class="text-xs mt-0.5" style="color:var(--text2)">
                    {{ $reservation->start_date->diffInDays($reservation->end_date) }} jours
                </div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wider mb-1" style="color:var(--text3)">Montant total</div>
                <div class="font-bold text-lg" style="color:var(--accent)">
                    {{ number_format($reservation->total_amount, 0, ',', ' ') }} FCFA
                </div>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-6 mt-4 pt-4"
             style="border-top:1px solid var(--border)">
            <div>
                <div class="text-xs uppercase tracking-wider mb-1" style="color:var(--text3)">Créée par</div>
                <div class="text-sm">{{ $reservation->user?->name ?? '—' }}</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wider mb-1" style="color:var(--text3)">Date confirmation</div>
                <div class="text-sm">
                    {{ $reservation->confirmed_at?->format('d/m/Y H:i') ?? '—' }}
                </div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wider mb-1" style="color:var(--text3)">Campagne liée</div>
                @if($reservation->campaign)
                    <a href="{{ route('admin.campaigns.show', $reservation->campaign) }}"
                    class="text-sm font-medium hover:underline" style="color:var(--accent)">
                        {{ $reservation->campaign->name }} →
                    </a>
                @elseif($reservation->status->value === 'confirme')
                    <a href="{{ route('admin.campaigns.create', ['reservation_id' => $reservation->id]) }}"
                    class="btn btn-ghost btn-sm" style="font-size:11px;color:var(--green);
                            border-color:rgba(34,197,94,0.3);">
                        + Créer une campagne
                    </a>
                @else
                    <span class="text-sm" style="color:var(--text3)">
                        Disponible après confirmation
                    </span>
                @endif
            </div>
        </div>

        @if($reservation->notes)
        <div class="mt-4 pt-4" style="border-top:1px solid var(--border)">
            <div class="text-xs uppercase tracking-wider mb-1" style="color:var(--text3)">Notes</div>
            <p class="text-sm" style="color:var(--text2)">{{ $reservation->notes }}</p>
        </div>
        @endif
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     ACTIONS STATUT
══════════════════════════════════════════════════════ --}}
@if($can['updateStatus'] || $can['annuler'])
<div class="card mb-4">
    <div class="card-header">
        <div class="card-title">⚡ Actions</div>
    </div>
    <div class="card-body">
        <div class="flex flex-wrap gap-3">

            {{-- Bouton Confirmer --}}
            @if(in_array('confirme', \App\Models\Reservation::ALLOWED_TRANSITIONS[$reservation->status->value] ?? []) && $can['updateStatus'])
                <button onclick="openStatusModal('confirme')"
                        class="btn btn-success">
                    ✅ Confirmer la réservation
                </button>
            @endif

            {{-- Bouton Refuser --}}
            @if(in_array('refuse', \App\Models\Reservation::ALLOWED_TRANSITIONS[$reservation->status->value] ?? []) && $can['updateStatus'])
                <button onclick="openStatusModal('refuse')"
                        class="btn btn-danger">
                    ❌ Refuser
                </button>
            @endif

            {{-- Bouton Annuler --}}
            @if($can['annuler'])
                <button onclick="openCancelModal()"
                        class="btn btn-danger">
                    🚫 Annuler la réservation
                </button>
            @endif

        </div>
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════
     PANNEAUX RÉSERVÉS
══════════════════════════════════════════════════════ --}}
<div class="card mb-4">
    <div class="card-header">
        <div class="card-title">🪧 Panneaux réservés</div>
        <span class="text-xs" style="color:var(--text2)">
            {{ $reservation->panels->count() }} panneau(x)
        </span>
        {{-- Badge état de l'attribution --}}
        @if($reservation->status->value === 'annule')
            <span class="badge ml-auto" style="background:rgba(239,68,68,.1);color:var(--red)">
                🔓 Panneaux libérés
            </span>
        @elseif($reservation->status->value === 'refuse')
            <span class="badge ml-auto" style="background:rgba(239,68,68,.1);color:var(--red)">
                🔓 Panneaux libérés
            </span>
        @elseif($reservation->status->value === 'confirme')
            <span class="badge ml-auto" style="background:rgba(34,197,94,.1);color:var(--green)">
                🔒 Panneaux confirmés
            </span>
        @elseif($reservation->status->value === 'en_attente')
            <span class="badge ml-auto" style="background:rgba(232,160,32,.1);color:var(--accent)">
                ⏳ Panneaux sous option
            </span>
        @endif
    </div>

    {{-- Message explicatif si annulé/refusé --}}
    @if(in_array($reservation->status->value, ['annule', 'refuse']))
    <div class="px-4 py-3 mx-4 mt-3 rounded-lg text-sm"
         style="background:var(--surface2);color:var(--text2);border:1px solid var(--border2)">
        ℹ️ Ces panneaux figurent ici à titre d'<strong>historique</strong>.
        Ils ont été libérés lors de l'{{ $reservation->status->value === 'annule' ? 'annulation' : 'refus' }}
        et sont à nouveau disponibles pour de nouvelles réservations.
    </div>
    @endif

    <div class="overflow-x-auto mt-3">
        <table>
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Nom</th>
                    <th>Commune</th>
                    <th>Format</th>
                    <th>Éclairé</th>
                    <th>Prix / mois</th>
                    <th>Total</th>
                    {{-- Colonne statut actuel uniquement si réservation active --}}
                    @if(in_array($reservation->status->value, ['en_attente', 'confirme']))
                        <th>Statut actuel</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($reservation->panels as $panel)
                <tr>
                    <td>
                        <span class="badge badge-blue font-mono text-xs">
                            {{ $panel->reference }}
                        </span>
                    </td>
                    <td class="font-medium">{{ $panel->name }}</td>
                    <td>{{ $panel->commune?->name ?? '—' }}</td>
                    <td>{{ $panel->format?->name ?? '—' }}</td>
                    <td>
                        @if($panel->is_lit)
                            <span style="color:var(--accent)">✦ Oui</span>
                        @else
                            <span style="color:var(--text3)">Non</span>
                        @endif
                    </td>
                    <td class="text-right">
                        {{ number_format($panel->pivot->unit_price, 0, ',', ' ') }} FCFA
                    </td>
                    <td class="text-right font-semibold" style="color:var(--accent)">
                        {{ number_format($panel->pivot->total_price, 0, ',', ' ') }} FCFA
                    </td>
                    @if(in_array($reservation->status->value, ['en_attente', 'confirme']))
                    <td>
                        @php $ps = $panel->status->value; @endphp
                        <span class="badge badge-{{
                            $ps === 'confirme' ? 'green' :
                            ($ps === 'option'  ? 'orange' :
                            ($ps === 'maintenance' ? 'red' : 'blue'))
                        }}">{{ $panel->status->label() }}</span>
                    </td>
                    @endif
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="{{ in_array($reservation->status->value, ['en_attente','confirme']) ? 7 : 6 }}"
                        class="text-right font-semibold pt-3" style="color:var(--text2)">
                        Total
                    </td>
                    <td class="text-right font-bold text-base pt-3" style="color:var(--accent)">
                        {{ number_format($reservation->total_amount, 0, ',', ' ') }} FCFA
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     MODAL — CHANGEMENT DE STATUT (avec avertissement)
══════════════════════════════════════════════════════ --}}
<div id="modal-status" class="modal-overlay" style="display:none" onclick="closeStatusModal(event)">
    <div class="modal" style="max-width:460px" onclick="event.stopPropagation()">
        <div class="modal-header">
            <div class="modal-title" id="modal-status-title">Confirmer l'action</div>
            <button class="modal-close" onclick="closeStatusModal()">✕</button>
        </div>
        <div class="modal-body">

            {{-- Icône dynamique --}}
            <div class="text-center mb-4">
                <div id="modal-status-icon"
                     class="inline-flex items-center justify-center w-14 h-14 rounded-full text-2xl mb-3"
                     style="background:var(--surface2)">
                </div>
                <div id="modal-status-desc" class="text-sm" style="color:var(--text2)"></div>
            </div>

            {{-- Bloc conséquences --}}
            <div id="modal-status-consequences"
                 class="p-3 rounded-lg text-sm mb-4"
                 style="background:var(--surface2);border:1px solid var(--border2)">
            </div>

            {{-- Avertissement irréversible --}}
            <div id="modal-status-warning"
                 class="p-3 rounded-lg text-xs flex items-start gap-2"
                 style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:var(--red)">
                <span class="mt-0.5">⚠️</span>
                <span id="modal-status-warning-text"></span>
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeStatusModal()" class="btn btn-ghost">Annuler</button>
            <form id="modal-status-form" method="POST">
                @csrf @method('PATCH')
                <input type="hidden" name="status" id="modal-status-input">
                <button type="submit" id="modal-status-btn" class="btn">Confirmer</button>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     MODAL — ANNULATION (avec avertissement)
══════════════════════════════════════════════════════ --}}
<div id="modal-cancel" class="modal-overlay" style="display:none" onclick="closeCancelModal(event)">
    <div class="modal" style="max-width:460px" onclick="event.stopPropagation()">
        <div class="modal-header">
            <div class="modal-title">🚫 Annuler la réservation</div>
            <button class="modal-close" onclick="closeCancelModal()">✕</button>
        </div>
        <div class="modal-body">
            <div class="text-center mb-4">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full text-2xl mb-3"
                     style="background:rgba(239,68,68,.1)">🚫</div>
                <div class="font-semibold mb-1">Annuler {{ $reservation->reference }} ?</div>
                <div class="text-sm" style="color:var(--text2)">
                    Cette réservation pour
                    <strong style="color:var(--text)">{{ $reservation->client?->name }}</strong>
                    sera annulée.
                </div>
            </div>

            <div class="p-3 rounded-lg text-sm mb-4" style="background:var(--surface2);border:1px solid var(--border2)">
                <div class="font-medium mb-2" style="color:var(--text)">Ce qui va se passer :</div>
                <ul class="space-y-1.5" style="color:var(--text2)">
                    <li class="flex items-start gap-2">
                        <span style="color:var(--green)">✓</span>
                        <span>Les {{ $reservation->panels->count() }} panneau(x) réservé(s) seront <strong>immédiatement libérés</strong> et disponibles pour d'autres réservations.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span style="color:var(--green)">✓</span>
                        <span>La réservation sera conservée en <strong>historique</strong> avec le statut « Annulé ».</span>
                    </li>
                    @if($reservation->campaign)
                    <li class="flex items-start gap-2">
                        <span style="color:var(--red)">⚠</span>
                        <span>La campagne liée <strong>{{ $reservation->campaign->reference }}</strong> devra être gérée séparément.</span>
                    </li>
                    @endif
                </ul>
            </div>

            <div class="p-3 rounded-lg text-xs flex items-start gap-2"
                 style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:var(--red)">
                <span class="mt-0.5">⚠️</span>
                <span>Cette action est <strong>irréversible</strong>. Une réservation annulée ne peut pas être réactivée.</span>
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeCancelModal()" class="btn btn-ghost">Conserver la réservation</button>
            <form method="POST" action="{{ route('admin.reservations.annuler', $reservation) }}">
                @csrf @method('PATCH')
                <button type="submit" class="btn btn-danger">🚫 Confirmer l'annulation</button>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     SCRIPT
══════════════════════════════════════════════════════ --}}
<script>
const STATUS_CONFIG = {
    confirme: {
        title:    '✅ Confirmer la réservation',
        icon:     '✅',
        iconBg:   'rgba(34,197,94,.1)',
        desc:     'Vous êtes sur le point de confirmer la réservation {{ $reservation->reference }}.',
        consequences: [
            { icon: '🔒', text: 'Les panneaux seront <strong>définitivement bloqués</strong> pour la période.' },
            { icon: '📄', text: 'La réservation passera en <strong>Ferme</strong> — plus modifiable.' },
            { icon: '📅', text: 'La date de confirmation sera enregistrée automatiquement.' },
        ],
        warning: 'La confirmation est irréversible. Le statut ne pourra plus revenir à "En attente".',
        btnClass: 'btn-success',
        btnLabel: '✅ Confirmer',
    },
    refuse: {
        title:    '❌ Refuser la réservation',
        icon:     '❌',
        iconBg:   'rgba(239,68,68,.1)',
        desc:     'Vous êtes sur le point de refuser la réservation {{ $reservation->reference }}.',
        consequences: [
            { icon: '🔓', text: 'Les {{ $reservation->panels->count() }} panneau(x) seront <strong>immédiatement libérés</strong>.' },
            { icon: '🗄️', text: 'La réservation sera conservée en <strong>historique</strong> avec le statut « Refusé ».' },
        ],
        warning: 'Le refus est irréversible. Cette réservation ne pourra plus être modifiée ni confirmée.',
        btnClass: 'btn-danger',
        btnLabel: '❌ Confirmer le refus',
    },
};

function modalShow(id) {
    document.getElementById(id).style.display = 'flex';
}
function modalHide(id) {
    document.getElementById(id).style.display = 'none';
}

function openStatusModal(newStatus) {
    const cfg = STATUS_CONFIG[newStatus];
    if (! cfg) return;

    document.getElementById('modal-status-title').textContent = cfg.title;

    const iconEl = document.getElementById('modal-status-icon');
    iconEl.textContent      = cfg.icon;
    iconEl.style.background = cfg.iconBg;

    document.getElementById('modal-status-desc').textContent = cfg.desc;

    const consEl = document.getElementById('modal-status-consequences');
    consEl.innerHTML =
        '<div style="font-weight:600;margin-bottom:8px;color:var(--text)">Ce qui va se passer :</div>'
        + '<ul style="display:flex;flex-direction:column;gap:6px;color:var(--text2)">'
        + cfg.consequences.map(c =>
            `<li style="display:flex;gap:8px;align-items:flex-start"><span>${c.icon}</span><span>${c.text}</span></li>`
          ).join('')
        + '</ul>';

    document.getElementById('modal-status-warning-text').textContent = cfg.warning;
    document.getElementById('modal-status-input').value = newStatus;

    const btn = document.getElementById('modal-status-btn');
    btn.className   = 'btn ' + cfg.btnClass;
    btn.textContent = cfg.btnLabel;

    document.getElementById('modal-status-form').action =
        '{{ route("admin.reservations.update-status", $reservation) }}';

    modalShow('modal-status');
}

function closeStatusModal(e) {
    if (! e || e.target === document.getElementById('modal-status')) {
        modalHide('modal-status');
    }
}

function openCancelModal() {
    modalShow('modal-cancel');
}

function closeCancelModal(e) {
    if (! e || e.target === document.getElementById('modal-cancel')) {
        modalHide('modal-cancel');
    }
}

// Fermer avec Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        modalHide('modal-status');
        modalHide('modal-cancel');
    }
});
</script>

</x-admin-layout>