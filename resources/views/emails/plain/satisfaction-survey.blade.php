@php $operator = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI')); @endphp
PANORA · {{ $operator }} — Régie Publicitaire (Abidjan, Côte d'Ivoire)

Bonjour {{ $client?->name ?? 'Client' }},

Votre campagne "{{ $campaign?->name }}" vient de se terminer.

Pour nous aider à améliorer nos services, prendriez-vous 1 minute pour
répondre à notre questionnaire de satisfaction ?

Détails :
- Campagne : {{ $campaign?->name ?? '—' }}
- Période  : {{ $campaign?->start_date?->format('d/m/Y') }} → {{ $campaign?->end_date?->format('d/m/Y') }}
- Durée    : ≈ 60 secondes

Donner mon avis : {{ $lien }}

Vos réponses nous aident à améliorer la qualité de nos services.
Merci d'avance pour votre retour.

—
Lien personnel sécurisé — ne le partagez pas.
© {{ date('Y') }} PANORA · {{ $operator }}. Tous droits réservés.
