@php
    $clientName = $client?->name ?? 'Client';
    $panelCount = $panels->count();

    // RÈGLE PATRONNE v2 2026-06-25 — délègue à Campaign::computeBillableMonths
    // (SOURCE UNIQUE : mois civils + tolérance +1j).
    $months      = \App\Models\Campaign::computeBillableMonths(
        \Carbon\Carbon::parse($reservation->start_date),
        \Carbon\Carbon::parse($reservation->end_date)
    );
    $monthsLabel = rtrim(rtrim(number_format($months, 1, ',', ''), '0'), ',');

@endphp
@php $operator = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI')); @endphp
PANORA · {{ $operator }} — Régie Publicitaire (Abidjan, Côte d'Ivoire)

Bonjour {{ $clientName }},

Nous avons sélectionné {{ $panelCount }} emplacement{{ $panelCount > 1 ? 's' : '' }} pour votre prochaine
campagne d'affichage. Vous pouvez consulter le détail et confirmer ou refuser.

Détails :
- Référence    : {{ $reservation->reference }}
- Période      : {{ $reservation->start_date->format('d/m/Y') }} → {{ $reservation->end_date->format('d/m/Y') }}
- Durée        : {{ $totalDays }} jour{{ $totalDays > 1 ? 's' : '' }} ({{ $monthsLabel }} mois facturé{{ $months > 1 ? 's' : '' }})
- Emplacements : {{ $panelCount }} panneau{{ $panelCount > 1 ? 'x' : '' }}

Le tarif et les conditions complètes sont disponibles sur la page de la proposition.

Consulter et répondre : {{ $lien }}

@if($expiresAt)
Cette proposition expire le {{ $expiresAt->format('d/m/Y à H:i') }}.
@endif

@php
    $com = $reservation->resolveCommercialContact();
    $comRole = $com?->role?->value ?? null;
    $hideInternalRole = !in_array($comRole, ['admin', 'commercial'], true);
@endphp
@if($com)
Votre interlocuteur commercial :
- {{ $com->name }}@if(!$hideInternalRole && $com->role?->label()) ({{ $com->role->label() }})@endif

@if($com->email)- Email : {{ $com->email }}
@endif
@if($com->whatsapp_number)- WhatsApp : {{ $com->whatsapp_number }}
@endif
@endif

Pour toute question, contactez votre interlocuteur commercial dont les coordonnées figurent ci-dessus.

—
© {{ date('Y') }} PANORA · {{ $operator }}. Tous droits réservés.
