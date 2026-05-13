@php
    $operator   = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    $ref        = $reservation->reference;
    $period     = $reservation->start_date->format('d/m/Y') . ' → ' . $reservation->end_date->format('d/m/Y');
    $amount     = (float) ($reservation->total_amount ?? 0);
    $preheader  = "Le MP {$submittedBy->name} vous a confié la proposition {$ref} pour envoi au client.";
@endphp

<x-mail.layout title="Proposition à envoyer" :preheader="$preheader">

    <span class="pill pill-info">📋 Action requise</span>

    <h1>Bonjour {{ $commercial->name }},</h1>

    <p>
        <strong>{{ $submittedBy->name }}</strong> ({{ $submittedBy->role?->value ?? 'Media Planner' }})
        a finalisé une proposition commerciale et vous l'a confiée pour
        envoi au client.
    </p>

    <div class="info">
        <div class="info-row">
            <div class="lbl">Référence</div>
            <div class="val"><code>{{ $ref }}</code></div>
        </div>
        <div class="info-row">
            <div class="lbl">Client</div>
            <div class="val"><strong>{{ $client?->name ?? '—' }}</strong></div>
        </div>
        <div class="info-row">
            <div class="lbl">Période</div>
            <div class="val">{{ $period }}</div>
        </div>
        <div class="info-row">
            <div class="lbl">Panneaux</div>
            <div class="val">{{ $panelCount }} emplacement{{ $panelCount > 1 ? 's' : '' }}</div>
        </div>
        @if($amount > 0)
        <div class="info-row">
            <div class="lbl">Montant</div>
            <div class="val"><strong>{{ number_format($amount, 0, ',', ' ') }} FCFA</strong></div>
        </div>
        @endif
    </div>

    <p>
        Connectez-vous à PANORA pour vérifier la proposition puis
        cliquer <strong>« Envoyer au client »</strong>.
        Le mail sera signé avec vos coordonnées commerciales.
    </p>

    <div class="cta-wrap">
        <a href="{{ $showUrl }}" class="cta">Ouvrir la proposition</a>
        <div class="cta-fallback">
            Si le bouton ne fonctionne pas, copiez ce lien :<br>
            <a href="{{ $showUrl }}">{{ $showUrl }}</a>
        </div>
    </div>

    <p style="margin-top:24px;color:#6b7280;font-size:13px;">
        Une question sur ce dossier ? Répondez à cet email, votre Media
        Planner ({{ $submittedBy->email ?? $submittedBy->name }}) recevra
        votre réponse directement.
    </p>

    <x-slot:footerNote>
        Notification interne PANORA · {{ $operator }} — workflow proposition.
    </x-slot:footerNote>

</x-mail.layout>
