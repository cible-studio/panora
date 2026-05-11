@php
    $clientName = $client?->name ?? 'Client';
    $panelCount = $panels->count();

    $sd = \Carbon\Carbon::parse($reservation->start_date)->startOfDay();
    $ed = \Carbon\Carbon::parse($reservation->end_date)->startOfDay();
    $totalDays = max(1, (int) $sd->diffInDays($ed));
    $fullMonths = (int) floor($totalDays / 30);
    $remainDays = $totalDays % 30;
    $fraction   = $remainDays === 0 ? 0 : ($remainDays <= 15 ? 0.5 : 1);
    $months     = max(0.5, $fullMonths + $fraction);
    $monthsLabel = rtrim(rtrim(number_format($months, 1, ',', ''), '0'), ',');

    // total_amount === null → ancienne réservation, on recompose.
    // total_amount === 0 → choix explicite (campagne offerte) → on respecte.
    $totalAmount = $reservation->total_amount === null
        ? $panels->sum(fn($p) => (float) ($p['monthly_rate'] ?? 0) * $months)
        : (float) $reservation->total_amount;
    $hasAmount = $reservation->total_amount !== null;
    $isOffert  = $hasAmount && $totalAmount === 0.0;
@endphp
CIBLE CI — Régie Publicitaire (Abidjan, Côte d'Ivoire)

Bonjour {{ $clientName }},

Nous avons sélectionné {{ $panelCount }} emplacement{{ $panelCount > 1 ? 's' : '' }} pour votre prochaine
campagne d'affichage. Vous pouvez consulter le détail et confirmer ou refuser.

Détails :
- Référence    : {{ $reservation->reference }}
- Période      : {{ $reservation->start_date->format('d/m/Y') }} → {{ $reservation->end_date->format('d/m/Y') }}
- Durée        : {{ $totalDays }} jour{{ $totalDays > 1 ? 's' : '' }} ({{ $monthsLabel }} mois facturé{{ $months > 1 ? 's' : '' }})
- Emplacements : {{ $panelCount }} panneau{{ $panelCount > 1 ? 'x' : '' }}
@if($hasAmount)
- Montant total : @if($isOffert)0 FCFA — Offert (campagne offerte par CIBLE CI)@else{{ number_format($totalAmount, 0, ',', ' ') }} FCFA (pour la totalité de la campagne)@endif

@endif

Consulter et répondre : {{ $lien }}

@if($expiresAt)
Cette proposition expire le {{ $expiresAt->format('d/m/Y à H:i') }}.
@endif

@if($reservation->user)
Votre interlocuteur commercial :
- {{ $reservation->user->name }}@if($reservation->user->role?->label()) ({{ $reservation->user->role->label() }})@endif

@if($reservation->user->email)- Email : {{ $reservation->user->email }}
@endif
@if($reservation->user->whatsapp_number)- WhatsApp : {{ $reservation->user->whatsapp_number }}
@endif
@endif

Vous pouvez également répondre à cet email pour toute question.
Notre équipe commerciale est à votre disposition.

—
© {{ date('Y') }} CIBLE CI. Tous droits réservés.
