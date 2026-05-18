@php
    $clientName  = $client?->name ?? 'Client';
    $ref         = $reservation->reference;
    $totalAmount = (float) ($reservation->total_amount ?? 0);
    $panelCount  = $reservation->panels->count() + $reservation->externalPanels->count();
    $expiresAtLabel = $expiresAt?->format('d/m/Y à H:i') ?? '—';
    $operator = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
@endphp
PANORA · {{ $operator }} — Rappel proposition

Bonjour {{ $clientName }},

@if($reminderStep <= 2)
Vous n'avez pas encore validé la proposition commerciale que nous vous
avons transmise. Vous avez jusqu'au {{ $expiresAtLabel }} pour répondre.
@else
Votre proposition arrive à expiration ({{ $daysLeft ?? '—' }} jour(s) restants).
Au-delà du {{ $expiresAtLabel }}, le lien ne sera plus accessible.
@endif

Détails :
- Référence    : {{ $ref }}
- Période      : {{ $reservation->start_date->format('d/m/Y') }} → {{ $reservation->end_date->format('d/m/Y') }}
- Emplacements : {{ $panelCount }} panneau{{ $panelCount > 1 ? 'x' : '' }}
- Expire le    : {{ $expiresAtLabel }}

Consulter et répondre : {{ $lien }}

@if($commercial)
Votre interlocuteur : {{ $commercial->name }}{{ $commercial->email ? ' — '.$commercial->email : '' }}
@endif

—
Rappel automatique. Vous ne recevrez plus de relance après le {{ $expiresAtLabel }}.
© {{ date('Y') }} PANORA · {{ $operator }}. Tous droits réservés.
