CIBLE CI — Régie Publicitaire (Abidjan, Côte d'Ivoire)

Bonjour {{ $client?->name ?? 'Client' }},

Votre campagne "{{ $campaign->name }}" s'est achevée aujourd'hui.
Merci de nous avoir fait confiance pour votre communication extérieure.

Récapitulatif :
- Campagne : {{ $campaign->name }}
- Période  : {{ $campaign->start_date?->format('d/m/Y') }} → {{ $campaign->end_date?->format('d/m/Y') }}
- Durée    : {{ $campaign->durationInDays() }} jours
@if($campaign->total_panels)
- Panneaux : {{ $campaign->total_panels }} emplacement{{ $campaign->total_panels > 1 ? 's' : '' }}
@endif
@if($campaign->total_amount > 0)
- Montant  : {{ number_format((float) $campaign->total_amount, 0, ',', ' ') }} FCFA
@endif

──────────────────────────────────────
VOTRE AVIS COMPTE
──────────────────────────────────────

Prenez 1 minute pour répondre à notre mini-questionnaire de satisfaction.
Votre retour nous permet de mieux vous servir à l'avenir.

Donner mon avis : {{ $lien }}

Vous avez des questions ou souhaitez planifier une prochaine campagne ?
Contactez votre chargé de compte — nous sommes à votre disposition.

—
Lien personnel sécurisé — ne le partagez pas.
© {{ date('Y') }} CIBLE CI. Tous droits réservés.
