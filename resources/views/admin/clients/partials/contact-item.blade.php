{{--
    Une ligne d'interlocuteur sur la fiche client (refonte compacte 2026-10-29).
    Rendue au chargement de la fiche (foreach contacts) ; après un ajout,
    une modification ou « principal », la fiche se recharge.

    Toutes les valeurs du formulaire sont portées par les data-* : la
    modale de modification (ClientContacts.openEdit, show.blade.php) les
    lit telles quelles, sans dépendre de la mise en page. data-recherche et
    data-source servent à la recherche et aux filtres de la liste.
--}}
@php
    $estProspect = ($contact->statut ?? 'client') === 'prospect';
    $duSheet     = $contact->source === \App\Services\Contacts\ContactsGmailService::SOURCE;
    $initiales   = collect(preg_split('/\s+/', trim((string) $contact->name)))->filter()->take(2)
        ->map(fn ($m) => mb_strtoupper(mb_substr($m, 0, 1)))->implode('');
    $fonction    = $contact->position ?: $contact->role_label;
    $recherche   = \Illuminate\Support\Str::ascii(mb_strtolower(implode(' ', array_filter([
        $contact->name, $contact->email, $contact->phone, $contact->position, $contact->role_label,
    ]))));
    $badge = 'font-size:9px;font-weight:700;padding:1px 6px;border-radius:9px;letter-spacing:.3px;white-space:nowrap;';
@endphp
<div class="contact-item ci-ligne" data-contact-id="{{ $contact->id }}"
     data-name="{{ $contact->name }}" data-email="{{ $contact->email }}" data-phone="{{ $contact->phone }}"
     data-notes="{{ $contact->notes }}" data-primary="{{ $contact->is_primary ? 1 : 0 }}"
     data-statut="{{ $contact->statut ?? 'client' }}" data-notif="{{ $contact->receives_notifications ? 1 : 0 }}"
     data-role="{{ $contact->role }}" data-position="{{ $contact->position }}"
     data-source="{{ $duSheet ? 'gmail' : '' }}" data-recherche="{{ $recherche }}">

    <div class="ci-avatar" style="{{ $contact->is_primary ? 'background:rgba(232,160,32,.15);color:var(--accent);' : ($estProspect ? 'background:rgba(59,130,246,.12);color:#3b82f6;' : '') }}"
         aria-hidden="true">{{ $initiales ?: '?' }}</div>

    <div class="ci-corps">
        <div class="ci-l1">
            <span class="ci-nom">{{ $contact->name }}</span>
            @if($contact->is_primary)
                <span style="{{ $badge }}background:rgba(232,160,32,.15);color:var(--accent);" title="Contact principal">PRINCIPAL</span>
            @endif
            {{-- Statut propre à chaque adresse (2026-10-28) --}}
            @if($estProspect)
                <span style="{{ $badge }}background:rgba(59,130,246,.12);color:#3b82f6;">PROSPECT</span>
            @endif
            @if($duSheet)
                <span style="{{ $badge }}background:rgba(234,67,53,.12);color:#ea4335;cursor:help;"
                      title="Arrivé du Google Sheets le {{ $contact->created_at?->format('d/m/Y') }}">GMAIL</span>
            @endif
            @if(!$contact->receives_notifications)
                <span style="{{ $badge }}font-weight:600;background:var(--surface3);color:var(--text3);cursor:help;"
                      title="Ne reçoit pas les mails de campagne (renouvellement, fin de campagne)">sans mails de campagne</span>
            @endif
        </div>
        <div class="ci-l2" title="{{ implode(' · ', array_filter([$contact->email, $contact->phone, $contact->position, $contact->role_label])) }}">
            @if($contact->email)<a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a>@endif
            @if($contact->phone)<span>{{ $contact->phone }}</span>@endif
            @if($fonction)<span>{{ $fonction }}</span>@endif
            @if(!$fonction && !$contact->email && !$contact->phone)<span style="font-style:italic;">Aucune coordonnée</span>@endif
        </div>
    </div>

    <div class="ci-actions">
        <button type="button" class="ci-menu-btn" data-ci-menu aria-haspopup="menu" aria-expanded="false"
                aria-label="Actions pour {{ $contact->name }}" title="Actions">⋯</button>
        <div class="ci-menu" role="menu" hidden>
            <button type="button" role="menuitem" onclick="ClientContacts.openEdit({{ $contact->id }})">✏️ Modifier</button>
            @if(!$contact->is_primary)
                <button type="button" role="menuitem" onclick="ClientContacts.setPrimary({{ $contact->id }})">⭐ Définir comme principal</button>
            @endif
            <button type="button" role="menuitem" class="ci-menu-danger"
                    onclick="ClientContacts.remove({{ $contact->id }}, {{ \Illuminate\Support\Js::from($contact->name) }})">🗑 Supprimer</button>
        </div>
    </div>
</div>
