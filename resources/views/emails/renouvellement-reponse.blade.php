{{-- Mail interne : réponse d'un client sur son renouvellement (2026-10-22). --}}
@php
    $pill = match ($choix) {
        'renouveler' => 'pill-success',
        'refus'      => 'pill-danger',
        default      => 'pill-warning',
    };
    $title = 'Réponse de ' . ($client?->name ?? 'un client') . ' — renouvellement';
@endphp

<x-mail.layout :title="$title" :preheader="$libelle . ' — « ' . $campagne->name . ' »'">

    @if($noteTest)
        <p style="font-size:13px;"><strong>Mail de test.</strong> {{ $noteTest }}</p>
    @endif

    <span class="pill {{ $pill }}">{{ $libelle }}</span>

    <h1>{{ $client?->name ?? 'Un client' }} a répondu</h1>

    <p>
        Le client a répondu au mail de renouvellement de la campagne
        <strong>« {{ $campagne->name }} »</strong>.
        @if($choix === 'rappel')
            Il souhaite <strong>être rappelé</strong> : merci de le contacter rapidement.
        @elseif(in_array($choix, ['renouveler', 'ajuster']))
            Prépare la proposition ; le MP confirmera ensuite le renouvellement dans Panora.
        @elseif($choix === 'refus')
            Les emplacements seront libres après l'échéance.
        @endif
    </p>

    <div class="info">
        <div class="info-row">
            <div class="lbl">Réponse</div>
            <div class="val"><strong>{{ $libelle }}</strong></div>
        </div>
        @if($message)
        <div class="info-row">
            <div class="lbl">Message</div>
            <div class="val">« {{ $message }} »</div>
        </div>
        @endif
        <div class="info-row">
            <div class="lbl">Échéance</div>
            <div class="val">{{ $campagne->end_date?->format('d/m/Y') }}</div>
        </div>
        <div class="info-row">
            <div class="lbl">Client</div>
            <div class="val">
                {{ $client?->name ?? '—' }}
                @php $tel = \App\Services\Diffusion\RenouvellementService::telephone($client); @endphp
                @if($tel)<br>{{ $tel }}@endif
            </div>
        </div>
    </div>

    <div class="cta-wrap">
        <a href="{{ $lien }}" class="cta">Ouvrir les renouvellements</a>
    </div>

    <x-slot:footerNote>
        Notification Panora — renouvellement des campagnes annuelles.
    </x-slot:footerNote>

</x-mail.layout>
