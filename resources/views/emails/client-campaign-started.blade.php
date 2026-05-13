@php
    $operator   = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    $ref        = $reservation->reference;
    $period     = $reservation->start_date->format('d/m/Y') . ' → ' . $reservation->end_date->format('d/m/Y');
    $amount     = (float) ($reservation->total_amount ?? 0);
    $clientName = $client?->name ?? 'Client';
    $preheader  = "Bienvenue {$clientName}, votre campagne {$ref} démarre. Suivez-la en temps réel.";
@endphp

<x-mail.layout title="Votre campagne est confirmée" :preheader="$preheader">

    <span class="pill pill-success">🎉 Campagne confirmée</span>

    <h1>Merci {{ $clientName }} !</h1>

    <p>
        Votre proposition <strong>{{ $ref }}</strong> est confirmée. Votre
        campagne d'affichage est désormais planifiée et démarrera selon les
        dates convenues.
    </p>

    <div class="info">
        <div class="info-row">
            <div class="lbl">Référence</div>
            <div class="val"><code>{{ $ref }}</code></div>
        </div>
        @if($campaign)
        <div class="info-row">
            <div class="lbl">Campagne</div>
            <div class="val"><strong>{{ $campaign->name }}</strong></div>
        </div>
        @endif
        <div class="info-row">
            <div class="lbl">Période</div>
            <div class="val">{{ $period }}</div>
        </div>
        <div class="info-row">
            <div class="lbl">Emplacements</div>
            <div class="val">{{ $totalPanels }} panneau{{ $totalPanels > 1 ? 'x' : '' }}</div>
        </div>
        @if($amount > 0)
        <div class="info-row">
            <div class="lbl">Montant</div>
            <div class="val"><strong>{{ number_format($amount, 0, ',', ' ') }} FCFA</strong></div>
        </div>
        @endif
    </div>

    @if($hasAccount)
        {{-- Cas 1 : compte client existant → CTA direct vers le suivi --}}
        <h2 style="font-size:15px;margin-top:24px;">📊 Suivez votre campagne en temps réel</h2>
        <p>
            Connectez-vous à votre espace client PANORA pour suivre l'avancement
            de votre campagne : poses, photos d'affichage (piges), factures.
        </p>
        <div class="cta-wrap">
            <a href="{{ $loginUrl }}" class="cta">Accéder à mon espace client</a>
            <div class="cta-fallback">
                Si le bouton ne fonctionne pas, copiez ce lien :<br>
                <a href="{{ $loginUrl }}">{{ $loginUrl }}</a>
            </div>
        </div>
    @else
        {{-- Cas 2 : pas de compte → invitation à en demander un --}}
        <h2 style="font-size:15px;margin-top:24px;">✨ Suivez votre campagne en temps réel</h2>
        <p>
            PANORA met à votre disposition un <strong>espace client gratuit</strong>
            pour suivre l'avancement de votre campagne, voir les photos
            d'affichage en direct (piges) dès qu'elles sont validées, et
            télécharger vos factures.
        </p>
        <p style="color:#4b5563;font-size:13px;">
            Pour activer votre espace, contactez votre interlocuteur commercial
            @if($contact)
                <strong>{{ $contact->name }}</strong>
                @if($contact->email) à
                    <a href="mailto:{{ $contact->email }}" style="color:#c2570d;">{{ $contact->email }}</a>
                @endif
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
        de pose, photos d'affichage validées, fin de campagne).
    </x-slot:footerNote>

</x-mail.layout>
