CIBLE CI — Notification commerciale

{{ $isAccepted ? 'Proposition acceptée' : 'Proposition refusée' }}

@if($isAccepted)Bonne nouvelle, le client a validé votre proposition.
@else Le client a décliné votre proposition.@endif

Détails :
- Référence : {{ $reservation->reference }}
- Client    : {{ $client?->name ?? '—' }}
- Période   : {{ $reservation->start_date->format('d/m/Y') }} → {{ $reservation->end_date->format('d/m/Y') }}
- Panneaux  : {{ $reservation->panels->count() }} emplacement{{ $reservation->panels->count() > 1 ? 's' : '' }}
@if($reservation->total_amount > 0)
- Montant   : {{ number_format((float) $reservation->total_amount, 0, ',', ' ') }} FCFA
@endif
- Décision  : {{ now()->format('d/m/Y à H:i') }}

@if(!$isAccepted && $reason)
Motif du refus :
{{ $reason }}

@endif

@if($isAccepted)Prochaines étapes :
- La réservation est marquée comme confirmée.
- Vous pouvez créer la campagne associée depuis la fiche.
- Préparez la facturation et le planning de pose.
@else Que faire ensuite :
- Contactez le client pour comprendre et proposer des alternatives.
- Les panneaux ont été automatiquement libérés.
- Vous pouvez créer une nouvelle proposition ajustée.
@endif

Ouvrir la fiche réservation : {{ $showLink }}

—
Notification automatique — décision prise par le client.
© {{ date('Y') }} CIBLE CI. Tous droits réservés.
