@php $operator = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI')); @endphp
PANORA · {{ $operator }} — Notification commerciale

Rappel — Proposition en attente de validation

Bonjour {{ $client?->name ?? 'Client' }},

Vous n'avez pas encore répondu à votre proposition {{ $reservation->reference }}.

Détails :
- Référence : {{ $reservation->reference }}
- Période   : {{ $reservation->start_date->format('d/m/Y') }} → {{ $reservation->end_date->format('d/m/Y') }}
- Expire le : {{ $expiresAt?->format('d/m/Y à H:i') ?? '—' }}

Consulter et répondre : {{ $lien }}

Si vous avez déjà répondu à cette proposition, ignorez ce message.

—
Notification automatique — © {{ date('Y') }} PANORA · {{ $operator }}. Tous droits réservés.
