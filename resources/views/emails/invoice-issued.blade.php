@php
    $ch = config('charte.couleurs');
    $operator = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    $title = $isReminder
        ? "Rappel — Facture impayée {$invoice->reference}"
        : "Nouvelle facture — {$invoice->reference}";
    // 2026-11-26 — Le mail annonçait le TTC des panneaux (sans taxes ni
    // services) et la TVA en « 18 FCFA » (le taux pris pour un montant).
    // Source unique : Invoice::totauxAffiches(), comme la page et le PDF.
    $tx    = $invoice->totauxAffiches();
    $reste = (int) round($invoice->remainingAmount());
    $f     = fn ($n) => number_format((int) $n, 0, ',', ' ');
    $preheader = $isReminder
        ? "Facture {$invoice->reference} : " . $f($reste) . " FCFA restent à régler."
        : "Facture {$invoice->reference} d'un montant total de " . $f($tx['total_a_payer']) . " FCFA.";
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
            <strong>{{ $invoice->reference }}</strong>, d'un montant total de
            <strong>{{ $f($tx['total_a_payer']) }} FCFA</strong>, n'est pas encore entièrement réglée :
            il reste <strong>{{ $f($reste) }} FCFA</strong> à payer. Merci de procéder au paiement dès que possible.
        </p>
    @else
        <p>
            Veuillez trouver votre facture
            <strong>{{ $invoice->reference }}</strong>, d'un montant total de
            <strong>{{ $f($tx['total_a_payer']) }} FCFA</strong> (taxes et services compris).
        </p>
    @endif

    <div class="info">
        <div class="info-row">
            <div class="lbl">Référence</div>
            <div class="val"><code>{{ $invoice->reference }}</code></div>
        </div>
        <div class="info-row">
            <div class="lbl">Total HT{{ $tx['remise'] > 0 ? ' après remise' : '' }}</div>
            <div class="val">{{ $f($tx['net_ht'] + $tx['services_ht']) }} FCFA</div>
        </div>
        <div class="info-row">
            <div class="lbl">TVA ({{ rtrim(rtrim(number_format($tx['taux_tva'], 2, ',', ''), '0'), ',') }} %)</div>
            <div class="val">{{ $f($tx['tva']) }} FCFA</div>
        </div>
        <div class="info-row">
            <div class="lbl">Total TTC</div>
            <div class="val">{{ $f($tx['ttc']) }} FCFA</div>
        </div>
        @if($tx['autres_taxes'] > 0)
        <div class="info-row">
            <div class="lbl">Autres taxes (ODP, TM, TSP)</div>
            <div class="val">{{ $f($tx['autres_taxes']) }} FCFA</div>
        </div>
        @endif
        <div class="info-row">
            <div class="lbl">Total à payer</div>
            <div class="val"><strong>{{ $f($tx['total_a_payer']) }} FCFA</strong></div>
        </div>
        @if($isReminder && $reste < $tx['total_a_payer'])
        <div class="info-row">
            <div class="lbl">Reste à payer</div>
            <div class="val"><strong>{{ $f($reste) }} FCFA</strong></div>
        </div>
        @endif
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

    <p style="margin-top:24px;color:{{ $ch['noir'] }};font-size:13px;">
        Pour toute question, écrivez-nous à <a href="mailto:contact@cible-ci.com">contact@cible-ci.com</a>.
    </p>

    <x-slot:footerNote>
        Facture émise automatiquement par Panora — opérée par {{ $operator }}.
    </x-slot:footerNote>

</x-mail.layout>
