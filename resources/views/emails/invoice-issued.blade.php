@php
    $operator = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    $title = $isReminder
        ? "Rappel — Facture impayée {$invoice->reference}"
        : "Nouvelle facture — {$invoice->reference}";
    $preheader = $isReminder
        ? "Facture {$invoice->reference} de " . number_format($invoice->amount_ttc, 0, ',', ' ') . " FCFA TTC en attente de règlement."
        : "Facture {$invoice->reference} d'un montant de " . number_format($invoice->amount_ttc, 0, ',', ' ') . " FCFA TTC.";
@endphp

<x-mail.layout :title="$title" :preheader="$preheader">

    <span class="{{ $isReminder ? 'pill pill-danger' : 'pill pill-warning' }}">
        {{ $isReminder ? '🔔 Rappel impayé' : '📄 Nouvelle facture' }}
    </span>

    <h1>
        @if($isReminder)
            Rappel — facture en attente de règlement
        @else
            Votre facture est disponible
        @endif
    </h1>

    <p>Bonjour {{ $client?->name ?? '—' }},</p>

    @if($isReminder)
        <p>
            Nous vous rappelons que la facture
            <strong>{{ $invoice->reference }}</strong> d'un montant de
            <strong>{{ number_format($invoice->amount_ttc, 0, ',', ' ') }} FCFA TTC</strong>
            n'a pas encore été réglée. Merci de procéder au paiement dès que possible.
        </p>
    @else
        <p>
            Veuillez trouver votre facture
            <strong>{{ $invoice->reference }}</strong> d'un montant de
            <strong>{{ number_format($invoice->amount_ttc, 0, ',', ' ') }} FCFA TTC</strong>.
        </p>
    @endif

    <div class="info">
        <div class="info-row">
            <div class="lbl">Référence</div>
            <div class="val"><code>{{ $invoice->reference }}</code></div>
        </div>
        <div class="info-row">
            <div class="lbl">Montant HT</div>
            <div class="val">{{ number_format($invoice->amount, 0, ',', ' ') }} FCFA</div>
        </div>
        <div class="info-row">
            <div class="lbl">TVA</div>
            <div class="val">{{ number_format($invoice->tva, 0, ',', ' ') }} FCFA</div>
        </div>
        <div class="info-row">
            <div class="lbl">Total TTC</div>
            <div class="val"><strong>{{ number_format($invoice->amount_ttc, 0, ',', ' ') }} FCFA</strong></div>
        </div>
        <div class="info-row">
            <div class="lbl">Émise le</div>
            <div class="val">{{ $invoice->issued_at?->format('d/m/Y') ?? $invoice->created_at->format('d/m/Y') }}</div>
        </div>
    </div>

    <div class="cta-wrap">
        <a href="{{ $url }}" class="cta">Consulter la facture</a>
        <div class="cta-fallback">
            🔒 Lien sécurisé valable jusqu'au {{ $link->expires_at?->format('d/m/Y') ?? '—' }}.<br>
            Si le bouton ne fonctionne pas : <a href="{{ $url }}">{{ $url }}</a>
        </div>
    </div>

    <p style="margin-top:24px;color:#6b7280;font-size:13px;">
        Pour toute question, écrivez-nous à <a href="mailto:contact@cible-ci.com">contact@cible-ci.com</a>.
    </p>

    <x-slot:footerNote>
        Facture émise automatiquement par Panora — opérée par {{ $operator }}.
    </x-slot:footerNote>

</x-mail.layout>
