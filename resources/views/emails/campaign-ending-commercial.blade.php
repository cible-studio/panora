@php
    $operator  = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    $title     = "J-{$daysRemaining} — Fin de la campagne « {$campaign->name} »";
    $preheader = "Campagne « {$campaign->name} » pour "
        . ($client?->name ?? '—')
        . " — fin prévue le "
        . \Carbon\Carbon::parse($campaign->end_date)->format('d/m/Y') . '.';
    $fichCampagne = route('admin.campaigns.show', $campaign);
    $fichClient   = $client ? route('admin.clients.show', $client) : null;
@endphp

<x-mail.layout :title="$title" :preheader="$preheader">

    <span class="pill pill-warning">⏰ J-{{ $daysRemaining }} avant fin de campagne</span>

    <h1>Prépare le suivi post-campagne</h1>

    <p>Bonjour {{ $campaign->user?->name ?? '' }},</p>

    <p>
        La campagne <strong>« {{ $campaign->name }} »</strong> pour
        <strong>{{ $client?->name ?? '—' }}</strong> se termine dans
        <strong>{{ $daysRemaining }} jour{{ $daysRemaining > 1 ? 's' : '' }}</strong>.
        C'est le bon moment pour préparer ton mail de suivi client (satisfaction,
        prolongation, prochaine campagne).
    </p>

    <div class="info">
        <div class="info-row">
            <div class="lbl">Campagne</div>
            <div class="val"><strong>{{ $campaign->name }}</strong></div>
        </div>
        <div class="info-row">
            <div class="lbl">Client</div>
            <div class="val">{{ $client?->name ?? '—' }}</div>
        </div>
        <div class="info-row">
            <div class="lbl">Période</div>
            <div class="val">
                {{ \Carbon\Carbon::parse($campaign->start_date)->format('d/m/Y') }}
                → {{ \Carbon\Carbon::parse($campaign->end_date)->format('d/m/Y') }}
            </div>
        </div>
        <div class="info-row">
            <div class="lbl">Fin dans</div>
            <div class="val"><strong>{{ $daysRemaining }} jour{{ $daysRemaining > 1 ? 's' : '' }}</strong></div>
        </div>
        @if($campaign->total_amount)
        <div class="info-row">
            <div class="lbl">Montant</div>
            <div class="val">{{ number_format($campaign->total_amount, 0, ',', ' ') }} FCFA</div>
        </div>
        @endif
    </div>

    <div class="cta-wrap">
        <a href="{{ $fichCampagne }}" class="cta">Ouvrir la fiche campagne</a>
        @if($fichClient)
            <div class="cta-fallback">
                Fiche client : <a href="{{ $fichClient }}">{{ $client->name }}</a>
            </div>
        @endif
    </div>

    <x-slot:footerNote>
        Notification automatique Panora — préparation suivi commercial.
    </x-slot:footerNote>

</x-mail.layout>
