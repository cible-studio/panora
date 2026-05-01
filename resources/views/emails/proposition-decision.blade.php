@php
    $title = $isAccepted ? 'Proposition acceptée' : 'Proposition refusée';
    $pillClass = $isAccepted ? 'pill pill-success' : 'pill pill-danger';
    $pillText  = $isAccepted ? 'Acceptée' : 'Refusée';
    $intro     = $isAccepted
        ? 'Bonne nouvelle — le client a validé votre proposition.'
        : 'Le client a décliné votre proposition.';
    $totalAmount = (float) ($reservation->total_amount ?? 0);
    $preheader = $isAccepted
        ? "{$client?->name} a accepté votre proposition {$reservation->reference}."
        : "{$client?->name} a refusé votre proposition {$reservation->reference}.";
@endphp

<x-mail.layout :title="$title" :preheader="$preheader">

    <span class="{{ $pillClass }}">{{ $pillText }}</span>

    <h1>{{ $title }}</h1>
    <p>{{ $intro }}</p>

    <div class="info">
        <div class="info-row">
            <div class="lbl">Référence</div>
            <div class="val"><code>{{ $reservation->reference }}</code></div>
        </div>
        <div class="info-row">
            <div class="lbl">Client</div>
            <div class="val">{{ $client?->name ?? '—' }}</div>
        </div>
        <div class="info-row">
            <div class="lbl">Période</div>
            <div class="val">{{ $reservation->start_date->format('d/m/Y') }} → {{ $reservation->end_date->format('d/m/Y') }}</div>
        </div>
        <div class="info-row">
            <div class="lbl">Panneaux</div>
            <div class="val">{{ $reservation->panels->count() }} emplacement{{ $reservation->panels->count() > 1 ? 's' : '' }}</div>
        </div>
        @if($totalAmount > 0)
            <div class="info-row">
                <div class="lbl">Montant</div>
                <div class="val"><strong style="color:#c2570d;">{{ number_format($totalAmount, 0, ',', ' ') }} FCFA</strong></div>
            </div>
        @endif
        <div class="info-row">
            <div class="lbl">Décision</div>
            <div class="val">{{ now()->format('d/m/Y à H:i') }}</div>
        </div>
    </div>

    @if(!$isAccepted && $reason)
        <h2>Motif du refus</h2>
        <div class="alert alert-danger">
            {{ $reason }}
        </div>
    @endif

    @if($isAccepted)
        <h2>Prochaines étapes</h2>
        <ul class="steps">
            <li>La réservation a été automatiquement marquée comme <strong>confirmée</strong>.</li>
            <li>Vous pouvez créer la <strong>campagne</strong> depuis la fiche réservation.</li>
            <li>Pensez à préparer la <strong>facturation</strong> et le planning de pose.</li>
        </ul>
    @else
        <h2>Que faire ensuite</h2>
        <ul class="steps">
            <li>Contactez le client pour comprendre le motif et proposer des alternatives.</li>
            <li>Les panneaux ont été automatiquement <strong>libérés</strong>.</li>
            <li>Vous pouvez créer une <strong>nouvelle proposition</strong> ajustée.</li>
        </ul>
    @endif

    <div class="cta-wrap">
        <a href="{{ $showLink }}" class="cta">Ouvrir la fiche réservation</a>
        <div class="cta-fallback">
            Si le bouton ne fonctionne pas, copiez ce lien :<br>
            <a href="{{ $showLink }}">{{ $showLink }}</a>
        </div>
    </div>

    <x-slot:footerNote>
        Notification automatique — décision prise par le client sur la proposition {{ $reservation->reference }}.
    </x-slot:footerNote>

</x-mail.layout>
