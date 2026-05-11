<x-mail.layout
    title="Fin de campagne"
    preheader="Votre campagne {{ $campaign->name }} vient de se terminer. Merci de votre confiance — partagez votre avis en 1 minute.">

    <h1>Bonjour {{ $client?->name ?? 'Client' }},</h1>

    <p>
        Votre campagne <strong>{{ $campaign->name }}</strong> s'est achevée aujourd'hui.
        Merci de nous avoir fait confiance pour votre communication extérieure.
    </p>

    <div class="info">
        <div class="info-row">
            <div class="lbl">Campagne</div>
            <div class="val"><strong>{{ $campaign->name }}</strong></div>
        </div>
        <div class="info-row">
            <div class="lbl">Période</div>
            <div class="val">
                {{ $campaign->start_date?->format('d/m/Y') }} → {{ $campaign->end_date?->format('d/m/Y') }}
            </div>
        </div>
        <div class="info-row">
            <div class="lbl">Durée</div>
            <div class="val">{{ $campaign->durationInDays() }} jours</div>
        </div>
        @if($campaign->total_panels)
        <div class="info-row">
            <div class="lbl">Panneaux</div>
            <div class="val">{{ $campaign->total_panels }} emplacement{{ $campaign->total_panels > 1 ? 's' : '' }}</div>
        </div>
        @endif
        @if($campaign->total_amount > 0)
        <div class="info-row">
            <div class="lbl">Montant</div>
            <div class="val"><strong>{{ number_format((float) $campaign->total_amount, 0, ',', ' ') }} FCFA</strong></div>
        </div>
        @endif
    </div>

    <h2>Votre avis compte</h2>
    <p>
        Pour nous aider à améliorer nos prestations, prenez <strong>1 minute</strong>
        pour répondre à notre mini-questionnaire de satisfaction.
        Votre retour est précieux et nous permet de mieux vous servir à l'avenir.
    </p>

    <div class="cta-wrap">
        <a href="{{ $lien }}" class="cta">Donner mon avis (1 min)</a>
        <div class="cta-fallback">
            Si le bouton ne fonctionne pas, copiez ce lien :<br>
            <a href="{{ $lien }}">{{ $lien }}</a>
        </div>
    </div>

    <p style="color:#6b7280;font-size:13px;margin-top:24px;">
        Vous avez des questions ou souhaitez planifier une prochaine campagne ?
        Contactez votre chargé de compte — nous sommes à votre disposition.
    </p>

    <x-slot:footerNote>
        Lien personnel sécurisé — ne le partagez pas. Vous pouvez répondre quand vous voulez.
    </x-slot:footerNote>

</x-mail.layout>
