@php
    $title = $succes
        ? 'Disponibilités envoyées aux clients'
        : "L'envoi des disponibilités a échoué";
    $preheader = $succes
        ? "{$envoi->nb_destinataires} client(s) ont reçu les disponibilités {$periode}."
        : "Les disponibilités {$periode} ne sont pas parties. Utilisez l'envoi manuel.";
@endphp

<x-mail.layout :title="$title" :preheader="$preheader">

    @if($test)
        <span class="pill pill-warning">🧪 Envoi de test — aucun client n'a été contacté</span>
    @elseif($succes)
        <span class="pill pill-success">✅ Envoi réussi</span>
    @else
        <span class="pill pill-danger">⚠️ Échec de l'envoi</span>
    @endif

    <h1>{{ $title }}</h1>

    @if($succes)
        <p>
            Les disponibilités <strong>{{ $periode }}</strong> ont été envoyées
            @if($test)
                à la liste <strong>Tests internes</strong> de Brevo.
            @else
                à <strong>{{ $envoi->nb_destinataires }} client{{ $envoi->nb_destinataires > 1 ? 's' : '' }}</strong>.
            @endif
        </p>
    @else
        <p>
            Les disponibilités <strong>{{ $periode }}</strong> ne sont <strong>pas parties</strong>.
            Aucun client ne les a reçues.
        </p>
        <p>
            Une fois la cause corrigée, utilisez le bouton
            <strong>« Envoyer maintenant »</strong> dans l'écran de diffusion.
        </p>
    @endif

    <div class="info">
        <div class="info-row">
            <div class="lbl">Période</div>
            <div class="val">{{ $periode }}</div>
        </div>
        <div class="info-row">
            <div class="lbl">Panneaux présentés</div>
            <div class="val">{{ $envoi->nb_panneaux }}</div>
        </div>
        <div class="info-row">
            <div class="lbl">Type d'envoi</div>
            <div class="val">{{ ucfirst($envoi->libelleMode()) }}@if($envoi->auteur) — {{ $envoi->auteur->name }}@endif</div>
        </div>
        @if($succes)
            <div class="info-row">
                <div class="lbl">Envoyé le</div>
                <div class="val">{{ $envoi->envoye_at?->format('d/m/Y à H\hi') }}</div>
            </div>
        @else
            <div class="info-row">
                <div class="lbl">Cause</div>
                <div class="val">{{ $envoi->erreur ?: 'Inconnue — voir le journal.' }}</div>
            </div>
        @endif
    </div>

    @if($lienPdf)
        <div class="cta-wrap">
            <a href="{{ $lienPdf }}" class="cta">📄 {{ $succes ? 'Voir les disponibilités envoyées' : 'Voir le catalogue préparé' }}</a>
        </div>
    @endif

    @if($lienApercu)
        <p style="text-align:center;">
            <a href="{{ $lienApercu }}">📊 Suivi détaillé dans Brevo</a>
            <br><span style="font-size:12px;color:#6b7280;">Délivrés, ouvertures, clics, désinscriptions — nécessite un accès au compte Brevo.</span>
        </p>
    @endif

    <p style="text-align:center;">
        <a href="{{ $lienEcran }}">Ouvrir l'écran de diffusion dans Panora</a>
    </p>

</x-mail.layout>
