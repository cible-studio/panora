@php
    $operator  = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    $title     = "Campagne assignée — {$campaign->name}";
    $preheader = "{$assignedBy->name} vous a assigné la campagne « "
        . $campaign->name . " » pour "
        . ($client?->name ?? 'sans client')
        . " ({$totalPanels} panneau·x).";

    $statusLabel = $campaign->status?->label() ?? '—';
@endphp

<x-mail.layout :title="$title" :preheader="$preheader">

    <span class="pill pill-info">📋 Nouvelle assignation</span>

    <h1>Une campagne vous est assignée</h1>

    <p>
        <strong>{{ $assignedBy->name }}</strong> vous a confié le suivi commercial de la campagne
        <strong>« {{ $campaign->name }} »</strong> pour le client
        <strong>{{ $client?->name ?? '—' }}</strong>.
    </p>

    <div class="info">
        <div class="info-row">
            <div class="lbl">Campagne</div>
            <div class="val">{{ $campaign->name }}</div>
        </div>
        <div class="info-row">
            <div class="lbl">Client</div>
            <div class="val">{{ $client?->name ?? '—' }}</div>
        </div>
        <div class="info-row">
            <div class="lbl">Période</div>
            <div class="val">
                {{ $campaign->start_date->format('d/m/Y') }}
                → {{ $campaign->end_date->format('d/m/Y') }}
            </div>
        </div>
        <div class="info-row">
            <div class="lbl">Panneaux</div>
            <div class="val">{{ $totalPanels }} panneau{{ $totalPanels > 1 ? 'x' : '' }}</div>
        </div>
        @if($campaign->total_amount)
            <div class="info-row">
                <div class="lbl">Montant total</div>
                <div class="val">{{ number_format($campaign->total_amount, 0, ',', ' ') }} FCFA</div>
            </div>
        @endif
        <div class="info-row">
            <div class="lbl">Statut</div>
            <div class="val">{{ $statusLabel }}</div>
        </div>
    </div>

    <p>
        Vous recevrez désormais les notifications opérationnelles liées à cette campagne
        (poses, piges, fin imminente, etc.). Suivez son avancement depuis la fiche campagne.
    </p>

    <div class="cta-wrap">
        <a href="{{ $showLink }}" class="cta">Ouvrir la campagne</a>
        <div class="cta-fallback">
            Lien direct : <a href="{{ $showLink }}">{{ $showLink }}</a>
        </div>
    </div>

    <x-slot:footerNote>
        Notification automatique — vous recevez ce mail car vous êtes le commercial assigné à cette campagne.
    </x-slot:footerNote>

</x-mail.layout>
