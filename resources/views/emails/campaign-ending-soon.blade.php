@php
    $operator  = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    $title     = "Votre campagne se termine dans {$daysRemaining} jour(s)";
    $preheader = "Campagne « {$campaign->name} » — fin prévue le "
        . \Carbon\Carbon::parse($campaign->end_date)->format('d/m/Y') . '.';
    $quoteSubject = urlencode('Prolongation — ' . $campaign->name);
@endphp

<x-mail.layout :title="$title" :preheader="$preheader">

    <span class="pill pill-warning">⏰ Campagne se termine bientôt</span>

    <h1>Votre campagne arrive à échéance</h1>

    <p>Bonjour {{ $client?->name ?? '—' }},</p>

    <p>
        Votre campagne <strong>« {{ $campaign->name }} »</strong> arrive à
        échéance dans <strong>{{ $daysRemaining }} jour{{ $daysRemaining > 1 ? 's' : '' }}</strong>.
    </p>

    <div class="info">
        <div class="info-row">
            <div class="lbl">Campagne</div>
            <div class="val"><strong>{{ $campaign->name }}</strong></div>
        </div>
        <div class="info-row">
            <div class="lbl">Date de fin</div>
            <div class="val">{{ \Carbon\Carbon::parse($campaign->end_date)->format('d/m/Y') }}</div>
        </div>
        <div class="info-row">
            <div class="lbl">Jours restants</div>
            <div class="val"><strong>{{ $daysRemaining }} jour{{ $daysRemaining > 1 ? 's' : '' }}</strong></div>
        </div>
    </div>

    <p>
        Souhaitez-vous <strong>prolonger</strong> votre campagne ou planifier
        la prochaine ? Contactez-nous dès maintenant pour bénéficier d'une
        <strong>offre de fidélité</strong>.
    </p>

    <div class="cta-wrap">
        <a href="mailto:contact@cible-ci.com?subject={{ $quoteSubject }}" class="cta">Demander un devis</a>
        <div class="cta-fallback">
            Ou répondez directement à cet email — notre équipe commerciale vous rappelle sous 24 h.
        </div>
    </div>

    <x-slot:footerNote>
        Notification automatique Panora — opérée par {{ $operator }}.
    </x-slot:footerNote>

</x-mail.layout>
