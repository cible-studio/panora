@php
    $operator = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    $title    = "Réservation assignée — {$reservation->reference}";
    $preheader = "{$assignedBy->name} vous a assigné la réservation "
        . ($client->name ?? 'sans client') . " ({$totalPanels} panneau·x).";
@endphp

<x-mail.layout :title="$title" :preheader="$preheader">

    <span class="pill pill-info">📋 Nouvelle assignation</span>

    <h1>Une réservation vous est assignée</h1>

    <p>
        <strong>{{ $assignedBy->name }}</strong> vous a confié le suivi commercial de
        la réservation <strong>{{ $reservation->reference }}</strong> pour
        <strong>{{ $client->name ?? '—' }}</strong>.
    </p>

    <div class="info">
        <div class="info-row">
            <div class="lbl">Référence</div>
            <div class="val"><code>{{ $reservation->reference }}</code></div>
        </div>
        <div class="info-row">
            <div class="lbl">Client</div>
            <div class="val">{{ $client->name ?? '—' }}</div>
        </div>
        <div class="info-row">
            <div class="lbl">Période</div>
            <div class="val">
                {{ $reservation->start_date->format('d/m/Y') }}
                → {{ $reservation->end_date->format('d/m/Y') }}
            </div>
        </div>
        <div class="info-row">
            <div class="lbl">Panneaux</div>
            <div class="val">{{ $totalPanels }} panneau{{ $totalPanels > 1 ? 'x' : '' }}</div>
        </div>
        @if($reservation->total_amount)
            <div class="info-row">
                <div class="lbl">Montant</div>
                <div class="val">{{ number_format($reservation->total_amount, 0, ',', ' ') }} FCFA</div>
            </div>
        @endif
    </div>

    <p>
        Préparez la proposition commerciale et envoyez-la au client depuis la fiche réservation.
    </p>

    <div class="cta-wrap">
        <a href="{{ $showLink }}" class="cta">Ouvrir la réservation</a>
        <div class="cta-fallback">
            Lien direct : <a href="{{ $showLink }}">{{ $showLink }}</a>
        </div>
    </div>

    <x-slot:footerNote>
        Notification automatique — vous recevez ce mail car vous êtes le commercial assigné à cette réservation.
    </x-slot:footerNote>

</x-mail.layout>
