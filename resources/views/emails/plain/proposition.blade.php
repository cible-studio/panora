@php
    $clientName = $client?->name ?? 'Client';
    $panelCount = $panels->count();
    $months     = max(1.0, (function ($s, $e) {
        $s = \Carbon\Carbon::parse($s)->startOfDay();
        $e = \Carbon\Carbon::parse($e)->endOfDay();
        $m = (int) abs($s->diffInMonths($e));
        $r = abs($s->copy()->addMonths($m)->diffInDays($e));
        return (float) ($r > 0 ? $m + 1 : $m);
    })($reservation->start_date, $reservation->end_date));
    $totalAmount = $panels->sum(fn($p) => (float) ($p->monthly_rate ?? 0) * $months);
@endphp
CIBLE CI — Régie Publicitaire (Abidjan, Côte d'Ivoire)

Bonjour {{ $clientName }},

Nous avons sélectionné {{ $panelCount }} emplacement{{ $panelCount > 1 ? 's' : '' }} pour votre prochaine
campagne d'affichage. Vous pouvez consulter le détail et confirmer ou refuser.

Détails :
- Référence    : {{ $reservation->reference }}
- Période      : {{ $reservation->start_date->format('d/m/Y') }} → {{ $reservation->end_date->format('d/m/Y') }}
- Emplacements : {{ $panelCount }}
@if($totalAmount > 0)
- Montant      : {{ number_format($totalAmount, 0, ',', ' ') }} FCFA (indicatif)
@endif

Consulter et répondre : {{ $lien }}

@if($expiresAt)
Cette proposition expire le {{ $expiresAt->format('d/m/Y à H:i') }}.
@endif

Vous pouvez également nous appeler ou répondre à cet email pour toute question.
Notre équipe commerciale est à votre disposition.

—
© {{ date('Y') }} CIBLE CI. Tous droits réservés.
