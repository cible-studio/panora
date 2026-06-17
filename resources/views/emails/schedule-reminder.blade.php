@php
    $operator = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    $remaining = $invoice->remainingAmount();
    $title = $daysUntilDue > 0
        ? "Rappel — Échéance dans {$daysUntilDue} jour" . ($daysUntilDue > 1 ? 's' : '')
        : 'Rappel — Échéance aujourd\'hui';
    $preheader = sprintf(
        'Échéance « %s » de %s FCFA — facture %s.',
        $schedule->label ?? 'Solde',
        number_format((float) $schedule->amount, 0, ',', ' '),
        $invoice->reference
    );
@endphp

<x-mail.layout :title="$title" :preheader="$preheader">

    <span class="pill pill-warning">📅 Rappel d'échéance</span>

    <h1>
        @if($daysUntilDue > 0)
            Une échéance arrive dans {{ $daysUntilDue }} jour{{ $daysUntilDue > 1 ? 's' : '' }}
        @else
            Une échéance arrive à terme aujourd'hui
        @endif
    </h1>

    <p>Bonjour {{ $client?->name ?? '—' }},</p>

    <p>
        Nous vous rappelons que l'échéance
        <strong>« {{ $schedule->label ?? 'Solde' }} »</strong>
        de la facture <strong>{{ $invoice->reference }}</strong>
        d'un montant de <strong>{{ number_format((float) $schedule->amount, 0, ',', ' ') }} FCFA</strong>
        @if($daysUntilDue > 0)
            arrive à échéance le <strong>{{ $schedule->due_date->format('d/m/Y') }}</strong>
            (dans {{ $daysUntilDue }} jour{{ $daysUntilDue > 1 ? 's' : '' }}).
        @else
            est due aujourd'hui, le <strong>{{ $schedule->due_date->format('d/m/Y') }}</strong>.
        @endif
    </p>

    <div class="info">
        <div class="info-row">
            <div class="lbl">Facture</div>
            <div class="val"><code>{{ $invoice->reference }}</code></div>
        </div>
        <div class="info-row">
            <div class="lbl">Échéance</div>
            <div class="val">{{ $schedule->label ?? 'Solde' }}</div>
        </div>
        <div class="info-row">
            <div class="lbl">Date d'échéance</div>
            <div class="val"><strong>{{ $schedule->due_date->format('d/m/Y') }}</strong></div>
        </div>
        <div class="info-row">
            <div class="lbl">Montant de l'échéance</div>
            <div class="val"><strong>{{ number_format((float) $schedule->amount, 0, ',', ' ') }} FCFA</strong></div>
        </div>
        <div class="info-row">
            <div class="lbl">Reste à payer sur la facture</div>
            <div class="val">{{ number_format($remaining, 0, ',', ' ') }} FCFA</div>
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
        Pour toute question ou difficulté de règlement, contactez-nous à
        <a href="mailto:contact@cible-ci.com">contact@cible-ci.com</a> —
        nous restons à votre disposition.
    </p>

    <x-slot:footerNote>
        Rappel envoyé automatiquement par Panora — opéré par {{ $operator }}.
        Vous pouvez ignorer ce message si vous avez déjà procédé au règlement.
    </x-slot:footerNote>

</x-mail.layout>
