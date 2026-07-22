@php
    /**
     * Email d'envoi de devis au client.
     *
     * Utilise le composant partagé <x-mail.layout> qui apporte :
     *   - Header foncé Panora + logo
     *   - Footer avec logo CIBLE (opérateur)
     *   - Palette et styles harmonisés avec tous les autres mails
     *     (invoice-issued, campaign-*, client-account, etc.)
     *   - Responsive + preheader gmail
     *
     * NE PAS ré-inliner du CSS ici : tout est déjà dans le layout.
     */
    $operator  = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    $title     = "Devis {$quote->reference} — {$quote->title}";
    $preheader = 'Devis ' . $quote->reference
               . ' d\'un montant de ' . number_format($quote->total_a_payer, 0, ',', ' ') . ' FCFA'
               . ' — valable jusqu\'au ' . ($quote->expires_at?->format('d/m/Y') ?? '—') . '.';

    $totalFmt = number_format($quote->total_a_payer, 0, ',', ' ');
    $nbPann   = (int) $quote->lines->sum('quantite');
@endphp

<x-mail.layout :title="$title" :preheader="$preheader">

    <span class="pill pill-warning">📄 Nouveau devis</span>

    <h1>Votre devis {{ $quote->reference }} est prêt</h1>

    <p>Bonjour {{ $quote->client?->name ?? '—' }},</p>

    <p>
        Suite à nos échanges, veuillez trouver ci-joint le devis
        <strong>{{ $quote->reference }}</strong> pour votre projet
        <strong>« {{ $quote->title }} »</strong> d'un montant de
        <strong>{{ $totalFmt }} FCFA</strong>.
    </p>

    <div class="info">
        <div class="info-row">
            <div class="lbl">Référence</div>
            <div class="val"><code>{{ $quote->reference }}</code></div>
        </div>
        <div class="info-row">
            <div class="lbl">Nombre de panneaux</div>
            <div class="val">{{ $nbPann }}</div>
        </div>
        @if($quote->period_start && $quote->period_end)
            <div class="info-row">
                <div class="lbl">Période d'affichage</div>
                <div class="val">{{ $quote->period_start->format('d/m/Y') }} → {{ $quote->period_end->format('d/m/Y') }}</div>
            </div>
        @endif
        <div class="info-row">
            <div class="lbl">Validité jusqu'au</div>
            <div class="val" style="color:#c2570d;font-weight:600">{{ $quote->expires_at?->format('d/m/Y') ?? '—' }}</div>
        </div>
        <div class="info-row" style="border-top:1px dashed #e5e7eb;padding-top:10px;margin-top:6px">
            <div class="lbl" style="font-size:14px;color:#111827;font-weight:600">Total à payer</div>
            <div class="val"><span class="code-strong">{{ $totalFmt }} FCFA</span></div>
        </div>
    </div>

    @if($quote->notes_client)
        <div class="alert alert-warning" style="white-space:pre-wrap">{{ $quote->notes_client }}</div>
    @endif

    <div class="cta-wrap">
        <a href="{{ $consultUrl }}" class="cta">Consulter le devis en ligne</a>
        <div class="cta-fallback">
            Le PDF est également joint à ce mail.<br>
            Si le bouton ne fonctionne pas : <a href="{{ $consultUrl }}">{{ $consultUrl }}</a>
        </div>
    </div>

    <p>
        Vous pouvez y consulter le détail complet et me faire part de votre décision :
        <strong>accepter</strong>, <strong>refuser</strong>, ou <strong>demander une modification</strong>.
    </p>

    <p style="margin-top:24px;color:#374151">
        Je reste à votre disposition pour tout complément d'information.<br><br>
        Cordialement,<br>
        <strong>{{ $quote->commercial?->name ?? "L'équipe " . $operator }}</strong>
        @if($quote->commercial?->email)
            <br><a href="mailto:{{ $quote->commercial->email }}">{{ $quote->commercial->email }}</a>
        @endif
    </p>

    <x-slot:footerNote>
        Devis émis par {{ $quote->commercial?->name ?? $operator }} · Réf. {{ $quote->reference }}
        · {{ optional($quote->sent_at ?: $quote->created_at)->format('d/m/Y') }}
    </x-slot:footerNote>

</x-mail.layout>
