@php
    $operator  = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    $title     = "Réservation confirmée — {$reservation->reference}";
    $preheader = "Votre réservation {$reservation->reference} est confirmée — "
        . number_format($reservation->total_amount ?? 0, 0, ',', ' ') . ' FCFA.';
@endphp

<x-mail.layout :title="$title" :preheader="$preheader">

    <span class="pill pill-success">✅ Réservation confirmée</span>

    <h1>Votre réservation est confirmée</h1>

    <p>Bonjour {{ $client?->name ?? '—' }},</p>

    <p>
        Nous avons le plaisir de vous confirmer votre réservation
        <strong>{{ $reservation->reference }}</strong>. Les panneaux
        sélectionnés sont désormais réservés sur la période demandée.
    </p>

    <div class="info">
        <div class="info-row">
            <div class="lbl">Référence</div>
            <div class="val"><code>{{ $reservation->reference }}</code></div>
        </div>
        <div class="info-row">
            <div class="lbl">Période</div>
            <div class="val">
                {{ \Carbon\Carbon::parse($reservation->start_date)->format('d/m/Y') }}
                → {{ \Carbon\Carbon::parse($reservation->end_date)->format('d/m/Y') }}
            </div>
        </div>
        <div class="info-row">
            <div class="lbl">Montant total</div>
            <div class="val"><strong>{{ number_format($reservation->total_amount ?? 0, 0, ',', ' ') }} FCFA</strong></div>
        </div>
    </div>

    <div class="cta-wrap">
        <a href="{{ $url }}" class="cta">Consulter le récapitulatif</a>
        <div class="cta-fallback">
            🔒 Lien sécurisé valable jusqu'au {{ $link->expires_at?->format('d/m/Y') ?? '—' }}.<br>
            Si le bouton ne fonctionne pas : <a href="{{ $url }}">{{ $url }}</a>
        </div>
    </div>

    <p style="margin-top:24px;color:#6b7280;font-size:13px;">
        Pour toute question : <a href="mailto:contact@cible-ci.com">contact@cible-ci.com</a>.
    </p>

    <x-slot:footerNote>
        Confirmation automatique Panora — opérée par {{ $operator }}.
    </x-slot:footerNote>

</x-mail.layout>
