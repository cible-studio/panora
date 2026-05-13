@php
    $operator   = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    $ref        = $reservation->reference;
    $period     = $reservation->start_date->format('d/m/Y') . ' → ' . $reservation->end_date->format('d/m/Y');
    $amount     = (float) ($reservation->total_amount ?? 0);
    $clientName = $client?->name ?? 'Client';
@endphp
PANORA · {{ $operator }} — Campagne confirmée

Merci {{ $clientName }} !

Votre proposition {{ $ref }} est confirmée. Votre campagne d'affichage
est désormais planifiée.

Détails :
- Référence    : {{ $ref }}
@if($campaign)- Campagne     : {{ $campaign->name }}
@endif
- Période      : {{ $period }}
- Emplacements : {{ $totalPanels }} panneau{{ $totalPanels > 1 ? 'x' : '' }}
@if($amount > 0)- Montant      : {{ number_format($amount, 0, ',', ' ') }} FCFA
@endif

@if($hasAccount)
Suivez votre campagne en temps réel sur votre espace client :
{{ $loginUrl }}
@else
Pour suivre votre campagne en temps réel (poses, piges, factures),
contactez votre interlocuteur commercial pour obtenir votre accès
à l'espace client PANORA — c'est gratuit.
@endif

@if($contact)
Votre interlocuteur commercial :
- {{ $contact->name }}
@if($contact->email)- Email : {{ $contact->email }}
@endif
@if($contact->whatsapp_number)- WhatsApp : {{ $contact->whatsapp_number }}
@endif
@endif

—
© {{ date('Y') }} PANORA · {{ $operator }}. Tous droits réservés.
