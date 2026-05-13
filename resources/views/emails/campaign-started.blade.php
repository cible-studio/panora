@php
    $start    = $campaign->start_date?->format('d/m/Y');
    $end      = $campaign->end_date?->format('d/m/Y');
    $amount   = $campaign->total_amount ? number_format($campaign->total_amount, 0, ',', ' ') . ' FCFA' : null;
    $operator = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    $isFuture = $isFuture ?? false;
    $hasAccount = $client && !empty($client->password);

    $title    = $isFuture ? 'Votre campagne est planifiée' : 'Démarrage de votre campagne';
    $preheader = $isFuture
        ? "Campagne {$campaign->name} planifiée du {$start} au {$end} ({$totalPanels} panneau(x))."
        : "Votre campagne {$campaign->name} démarre — {$totalPanels} panneau(x) du {$start} au {$end}.";
@endphp

<x-mail.layout :title="$title" :preheader="$preheader">

    @if($isFuture)
        <span class="pill pill-info">📅 Campagne planifiée</span>
    @else
        <span class="pill pill-success">🚀 Campagne démarrée</span>
    @endif

    <h1>Bonjour {{ $client?->name ?? '' }},</h1>

    @if($isFuture)
        <p>Nous avons le plaisir de vous confirmer la planification de votre
           campagne d'affichage avec <strong>PANORA · {{ $operator }}</strong>. Elle
           démarrera le <strong>{{ $start }}</strong>.</p>
    @else
        <p>Nous avons le plaisir de vous confirmer le démarrage de votre
           campagne d'affichage avec <strong>PANORA · {{ $operator }}</strong>.</p>
    @endif

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

    @if($hasAccount)
        <p>Vous pouvez à tout moment suivre l'état de votre campagne (pose
           des bâches, photos terrain, factures) depuis votre espace client.</p>

        <div class="cta-wrap">
            <a href="{{ route('client.dashboard') }}" class="cta">Accéder à mon espace</a>
            <div class="cta-fallback">
                Si le bouton ne fonctionne pas, copiez ce lien :<br>
                <a href="{{ route('client.dashboard') }}">{{ route('client.dashboard') }}</a>
            </div>
        </div>
    @else
        <p style="margin-top:18px;color:#374151;">
            PANORA met à votre disposition un <strong>espace client gratuit</strong>
            pour suivre l'avancement de votre campagne en temps réel (poses,
            photos d'affichage validées, factures).
        </p>
        <p style="color:#4b5563;font-size:13px;">
            Pour activer votre espace, contactez votre interlocuteur commercial
            @if($contact)
                <strong>{{ $contact->name }}</strong>@if($contact->email) à
                <a href="mailto:{{ $contact->email }}" style="color:#c2570d;">{{ $contact->email }}</a>@endif
            @endif
            — il vous transmettra vos identifiants sécurisés.
        </p>
    @endif

    @if($contact)
    <div class="info" style="margin-top:20px;">
        <div class="info-row">
            <div class="lbl">Votre commercial</div>
            <div class="val">
                <strong>{{ $contact->name }}</strong>
                @if($contact->email)<br><a href="mailto:{{ $contact->email }}" style="color:#c2570d;">{{ $contact->email }}</a>@endif
                @if($contact->whatsapp_number)<br><a href="https://wa.me/{{ preg_replace('/\D/', '', $contact->whatsapp_number) }}" style="color:#16a34a;">📱 {{ $contact->whatsapp_number }}</a>@endif
            </div>
        </div>
    </div>
    @endif

    <x-slot:footerNote>
        Vous recevrez d'autres notifications au fil de votre campagne (début
        des poses, photos d'affichage validées, fin de campagne).
    </x-slot:footerNote>

</x-mail.layout>
