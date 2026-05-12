@php $operator = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI')); @endphp
PANORA · {{ $operator }} — Notification commerciale

{{ $isAccepted ? 'Proposition acceptée' : 'Proposition refusée' }}

@if($isAccepted)Bonne nouvelle, le client a validé votre proposition.
@else Le client a décliné votre proposition.@endif

Détails :
- Référence : {{ $reservation->reference }}
- Client    : {{ $client?->name ?? '—' }}
@if($campaignName ?? null)- Campagne  : {{ $campaignName }}
@endif
- Période   : {{ $reservation->start_date->format('d/m/Y') }} → {{ $reservation->end_date->format('d/m/Y') }}
@php $panelCount = $reservation->panels->count() + $reservation->externalPanels->count(); @endphp
- Panneaux  : {{ $panelCount }} emplacement{{ $panelCount > 1 ? 's' : '' }}
@if($reservation->total_amount > 0)
- Montant   : {{ number_format((float) $reservation->total_amount, 0, ',', ' ') }} FCFA
@endif
@if(!empty($campaignName))
- Campagne  : {{ $campaignName }}
@endif
- Décision  : {{ now()->format('d/m/Y à H:i') }}

@if(!$isAccepted && ($reasonLabel ?? null))
Motif du refus : {{ $reasonLabel }}
@if($reason)Précisions client : {{ $reason }}
@endif

@elseif(!$isAccepted && $reason)
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
@if(!empty($campaignLink))
Voir la campagne « {{ $campaignName }} » : {{ $campaignLink }}
@endif

—
Notification automatique — décision prise par le client.
© {{ date('Y') }} PANORA · {{ $operator }}. Tous droits réservés.
