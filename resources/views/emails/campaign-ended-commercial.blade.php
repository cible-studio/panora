@php
    $operator     = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    $title        = "Campagne terminée — « {$campaign->name} »";
    $preheader    = "Campagne « {$campaign->name} » pour "
        . ($client?->name ?? '—')
        . " est terminée. À toi le suivi client.";
    $fichCampagne = route('admin.campaigns.show', $campaign);
    $fichClient   = $client ? route('admin.clients.show', $client) : null;
@endphp

<x-mail.layout :title="$title" :preheader="$preheader">

    <span class="pill pill-success">✅ Campagne terminée</span>

    <h1>À toi de gérer le suivi client</h1>

    <p>Bonjour {{ $campaign->user?->name ?? '' }},</p>

    <p>
        La campagne <strong>« {{ $campaign->name }} »</strong> pour
        <strong>{{ $client?->name ?? '—' }}</strong> est officiellement terminée
        depuis aujourd'hui. Prépare et envoie ton mail de suivi post-campagne
        au client (satisfaction, opportunités, prochaine action).
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
        @if($campaign->total_amount)
        <div class="info-row">
            <div class="lbl">Montant</div>
            <div class="val">{{ number_format($campaign->total_amount, 0, ',', ' ') }} FCFA</div>
        </div>
        @endif
        @if($campaign->total_panels)
        <div class="info-row">
            <div class="lbl">Panneaux</div>
            <div class="val">{{ $campaign->total_panels }}</div>
        </div>
        @endif
    </div>

    <div class="cta-wrap">
        <a href="{{ $fichCampagne }}" class="cta">Ouvrir la fiche campagne</a>
        @if($fichClient)
            <div class="cta-fallback">
                Fiche client : <a href="{{ $fichClient }}">{{ $client->name }}</a>
                @if($client && !empty($client->email))
                    · Contact : <a href="mailto:{{ $client->email }}">{{ $client->email }}</a>
                @endif
            </div>
        @endif
    </div>

    <p style="margin-top:16px;color:#6b7280;font-size:13px;">
        💡 <strong>Rappel</strong> : un mail de satisfaction automatique est déjà
        parti au client (avec lien vers le questionnaire). Ton mail de suivi
        personnel vient en complément pour maintenir la relation commerciale.
    </p>

    <x-slot:footerNote>
        Notification automatique Panora — suivi commercial post-campagne.
    </x-slot:footerNote>

</x-mail.layout>
