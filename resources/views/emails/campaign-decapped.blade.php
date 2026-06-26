@php
    $operator  = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    $title     = "Décapage terminé — {$campaign->name}";
    $preheader = "Votre campagne « {$campaign->name} » est officiellement terminée — {$decappedCount} panneau(x) retiré(s).";
@endphp

<x-mail.layout :title="$title" :preheader="$preheader">

    <span class="pill pill-success">✅ Campagne décapée</span>

    <h1>Votre campagne est terminée</h1>

    <p>Bonjour {{ $client?->name ?? '—' }},</p>

    <p>
        Votre campagne <strong>« {{ $campaign->name }} »</strong> est officiellement
        terminée : les <strong>{{ $decappedCount }} panneau{{ $decappedCount > 1 ? 'x' : '' }}</strong>
        ont été retirés du terrain par nos équipes.
    </p>

    <div class="info">
        <div class="info-row">
            <div class="lbl">Campagne</div>
            <div class="val"><strong>{{ $campaign->name }}</strong></div>
        </div>
        <div class="info-row">
            <div class="lbl">Période</div>
            <div class="val">
                {{ $campaign->start_date->format('d/m/Y') }}
                → {{ $campaign->end_date->format('d/m/Y') }}
            </div>
        </div>
        <div class="info-row">
            <div class="lbl">Panneaux décapés</div>
            <div class="val"><strong>{{ $decappedCount }}</strong></div>
        </div>
    </div>

    <p>
        Merci de votre confiance ! Nous restons à votre disposition pour
        vos prochaines campagnes d'affichage.
    </p>

    <div class="cta-wrap">
        <a href="{{ $url }}" class="cta">Voir le récapitulatif</a>
        <div class="cta-fallback">
            🔒 Lien sécurisé valable jusqu'au {{ $link->expires_at?->format('d/m/Y') ?? '—' }}.<br>
            Si le bouton ne fonctionne pas : <a href="{{ $url }}">{{ $url }}</a>
        </div>
    </div>

    <x-slot:footerNote>
        Notification automatique Panora — opérée par {{ $operator }}.
    </x-slot:footerNote>

</x-mail.layout>
