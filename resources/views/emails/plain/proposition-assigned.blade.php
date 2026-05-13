@php
    $operator   = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    $ref        = $reservation->reference;
    $period     = $reservation->start_date->format('d/m/Y') . ' → ' . $reservation->end_date->format('d/m/Y');
    $amount     = (float) ($reservation->total_amount ?? 0);
@endphp
PANORA · {{ $operator }} — Proposition à envoyer

Bonjour {{ $commercial->name }},

{{ $submittedBy->name }} a finalisé la proposition {{ $ref }} et vous l'a
confiée pour envoi au client.

Détails :
- Référence : {{ $ref }}
- Client    : {{ $client?->name ?? '—' }}
- Période   : {{ $period }}
- Panneaux  : {{ $panelCount }} emplacement{{ $panelCount > 1 ? 's' : '' }}
@if($amount > 0)- Montant   : {{ number_format($amount, 0, ',', ' ') }} FCFA
@endif

Connectez-vous à PANORA pour vérifier puis cliquer "Envoyer au client" :
{{ $showUrl }}

Le mail au client sera signé avec vos coordonnées commerciales.

—
Pour répondre au MP {{ $submittedBy->name }} : {{ $submittedBy->email ?? '—' }}
© {{ date('Y') }} PANORA · {{ $operator }}. Tous droits réservés.
