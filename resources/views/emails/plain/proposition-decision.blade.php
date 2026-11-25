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
- Montant   : {{ number_format((float) $reservation->total_amount, 0, ',', ' ') }} FCFA HT
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
- Les panneaux sont tenus en option{{ !empty($bcEcheance) ? " jusqu'au {$bcEcheance}" : '' }} : le bon de commande du client est attendu.
- Récupérez le bon de commande, puis cliquez sur « 📥 BC reçu » sur la fiche réservation : elle sera confirmée et ses panneaux bloqués.
- Le média planner créera ensuite la campagne. Sans bon de commande à l'échéance, les panneaux repartent au catalogue.
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
