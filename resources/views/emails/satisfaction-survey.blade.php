<x-mail.layout title="Votre avis sur la campagne" preheader="Merci de prendre 1 minute pour nous donner votre retour sur la campagne qui vient de se terminer.">

    <h1>Bonjour {{ $client?->name ?? 'Client' }},</h1>

    <p>
        Votre campagne <strong>{{ $campaign?->name }}</strong> vient de se terminer.
        Nous espérons qu'elle a bien rempli ses objectifs.
    </p>

    <p>
        Pour nous aider à améliorer nos services, nous serions très reconnaissants
        si vous preniez <strong>1 minute</strong> pour répondre à quelques questions.
    </p>

    <div class="info">
        <div class="info-row">
            <div class="lbl">Campagne</div>
            <div class="val">{{ $campaign?->name ?? '—' }}</div>
        </div>
        <div class="info-row">
            <div class="lbl">Période</div>
            <div class="val">{{ $campaign?->start_date?->format('d/m/Y') }} → {{ $campaign?->end_date?->format('d/m/Y') }}</div>
        </div>
        <div class="info-row">
            <div class="lbl">Durée du questionnaire</div>
            <div class="val">≈ 60 secondes</div>
        </div>
    </div>

    <div class="cta-wrap">
        <a href="{{ $lien }}" class="cta">Donner mon avis</a>
        <div class="cta-fallback">
            Si le bouton ne fonctionne pas, copiez ce lien :<br>
            <a href="{{ $lien }}">{{ $lien }}</a>
        </div>
    </div>

    <p style="color:#6b7280;font-size:13px;margin-top:24px;">
        Vos réponses nous aident à améliorer la qualité de nos services pour vous
        et tous nos clients. Merci d'avance pour votre retour.
    </p>

    <x-slot:footerNote>
        Lien personnel sécurisé — ne le partagez pas. Vous pouvez répondre quand vous voulez.
    </x-slot:footerNote>

</x-mail.layout>
