@php
    $start = $campaign->start_date?->format('d/m/Y');
    $end   = $campaign->end_date?->format('d/m/Y');
    $amount = $campaign->total_amount ? number_format($campaign->total_amount, 0, ',', ' ') . ' FCFA' : null;
@endphp

<x-mail.layout title="Démarrage de votre campagne"
               preheader="Votre campagne {{ $campaign->name }} démarre — {{ $totalPanels }} panneau(x) du {{ $start }} au {{ $end }}.">

    <span class="pill pill-success">Campagne démarrée</span>

    <h1>Bonjour {{ $client?->name ?? '' }},</h1>

    <p>Nous avons le plaisir de vous confirmer le démarrage de votre campagne
       d'affichage avec CIBLE CI.</p>

    <div class="info">
        <div class="info-row">
            <div class="lbl">Campagne</div>
            <div class="val"><strong>{{ $campaign->name }}</strong></div>
        </div>
        <div class="info-row">
            <div class="lbl">Période</div>
            <div class="val">Du {{ $start }} au {{ $end }}</div>
        </div>
        <div class="info-row">
            <div class="lbl">Panneaux mobilisés</div>
            <div class="val">{{ $totalPanels }} support(s) d'affichage</div>
        </div>
        @if($amount)
        <div class="info-row">
            <div class="lbl">Montant total</div>
            <div class="val"><strong>{{ $amount }}</strong></div>
        </div>
        @endif
    </div>

    <p>Vous pouvez à tout moment suivre l'état de votre campagne (pose
       des bâches, photos terrain, factures) depuis votre espace client.</p>

    <div class="cta-wrap">
        <a href="{{ route('client.dashboard') }}" class="cta">Accéder à mon espace</a>
        <div class="cta-fallback">
            Si le bouton ne fonctionne pas, copiez ce lien :<br>
            <a href="{{ route('client.dashboard') }}">{{ route('client.dashboard') }}</a>
        </div>
    </div>

    <p style="margin-top:28px;color:#6b7280;font-size:13px;">
        Pour toute question concernant cette campagne, n'hésitez pas à
        contacter votre commercial CIBLE CI.
    </p>

    <x-slot:footerNote>
        Notification automatique — merci de ne pas répondre à cette adresse.
    </x-slot:footerNote>

</x-mail.layout>
