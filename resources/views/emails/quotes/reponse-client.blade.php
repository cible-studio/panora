{{-- Mail interne : le client a répondu à un devis (2026-11-23).
     ⚠ $message est réservé par Laravel dans les vues de mail : le texte du client s'appelle $messageClient. --}}
@php
    use App\Services\QuoteDecisionService as Reponse;
    $pill = match ($reponse) {
        Reponse::ACCEPTATION  => 'pill-success',
        Reponse::REFUS        => 'pill-danger',
        default               => 'pill-warning',
    };
    $nomClient = $client?->name ?: 'Le client';
    $title = 'Réponse de ' . $nomClient . ' — devis ' . $quote->reference;
@endphp

<x-mail.layout :title="$title" :preheader="$libelle . ' — ' . $quote->reference . ' « ' . $quote->title . ' »'">

    <span class="pill {{ $pill }}">{{ $libelle }}</span>

    <h1>{{ $nomClient }} a répondu au devis {{ $quote->reference }}</h1>

    <p>
        @if($reponse === Reponse::MODIFICATION)
            Le client <strong>demande une modification</strong>. Le devis est passé « En négociation » :
            modifie-le dans Panora, puis renvoie-le au client.
        @elseif($reponse === Reponse::REFUS)
            Le client <strong>a refusé</strong> le devis.
        @else
            Le client <strong>a accepté</strong> le devis.
        @endif
    </p>

    @if($suite)
        <p>{{ $suite }}</p>
    @endif

    <div class="info">
        <div class="info-row">
            <div class="lbl">Devis</div>
            <div class="val">{{ $quote->reference }} — {{ $quote->title }}</div>
        </div>
        <div class="info-row">
            <div class="lbl">Client</div>
            <div class="val">{{ $nomClient }}</div>
        </div>
        @if($messageClient)
        <div class="info-row">
            <div class="lbl">{{ $reponse === Reponse::MODIFICATION ? 'Demande du client' : 'Motif' }}</div>
            <div class="val">« {{ $messageClient }} »</div>
        </div>
        @endif
        <div class="info-row">
            <div class="lbl">Total à payer</div>
            <div class="val">{{ number_format((int) $quote->total_a_payer, 0, ',', ' ') }} FCFA</div>
        </div>
        <div class="info-row">
            <div class="lbl">Réponse reçue par</div>
            <div class="val">{{ $canalLibelle }}, le {{ now()->format('d/m/Y à H:i') }}</div>
        </div>
    </div>

    <div class="cta-wrap">
        <a href="{{ $lien }}" class="cta">Ouvrir le devis</a>
    </div>

    <x-slot:footerNote>
        Notification Panora — réponses des clients aux devis.{{ $repondreAuClient ? ' Répondre à ce mail écrit au client.' : '' }}
    </x-slot:footerNote>

</x-mail.layout>
