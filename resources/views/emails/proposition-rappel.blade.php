@php
    $clientName   = $client?->name ?? 'Client';
    $expiresLabel = $expiresAt?->format('d/m/Y à H:i') ?? '—';
    $ref          = $reservation->reference;
    $preheader    = "Votre proposition {$ref} expire le {$expiresLabel} — confirmez ou refusez avant la date limite.";
@endphp

<x-mail.layout title="Rappel — Proposition en attente" :preheader="$preheader">

    <span class="pill pill-warning">Rappel</span>

    <h1>Bonjour {{ $clientName }},</h1>
    <p>
        Vous n'avez pas encore répondu à votre proposition commerciale
        <strong>{{ $ref }}</strong>.
    </p>

    <div class="info">
        <div class="info-row">
            <div class="lbl">Référence</div>
            <div class="val"><code>{{ $ref }}</code></div>
        </div>
        <div class="info-row">
            <div class="lbl">Période</div>
            <div class="val">
                {{ $reservation->start_date->format('d/m/Y') }} →
                {{ $reservation->end_date->format('d/m/Y') }}
            </div>
        </div>
        <div class="info-row">
            <div class="lbl">Expire le</div>
            <div class="val"><strong style="color:#92400e;">{{ $expiresLabel }}</strong></div>
        </div>
    </div>

    <p>
        Cliquez sur le bouton ci-dessous pour consulter le détail des emplacements
        et confirmer ou refuser la proposition.
    </p>

    <div class="cta-wrap">
        <a href="{{ $lien }}" class="cta">Voir ma proposition</a>
        <div class="cta-fallback">
            Si le bouton ne fonctionne pas, copiez ce lien :<br>
            <a href="{{ $lien }}">{{ $lien }}</a>
        </div>
    </div>

    <x-slot:footerNote>
        Notification automatique — proposition {{ $ref }}. Si vous avez déjà répondu, ignorez ce message.
    </x-slot:footerNote>

</x-mail.layout>
