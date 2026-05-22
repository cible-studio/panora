@php
    $operator = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    $title = "Pige photo disponible — {$panel?->reference}";
    $preheader = "Photo du panneau {$panel?->reference} validée pour votre campagne « " . ($campaign?->name ?? '—') . " ».";
@endphp

<x-mail.layout :title="$title" :preheader="$preheader">

    <span class="pill pill-success">📸 Pige photo validée</span>

    <h1>Votre pige photo est disponible</h1>

    <p>Bonjour {{ $client?->name ?? '—' }},</p>

    <p>
        La photo du panneau <strong>{{ $panel?->reference ?? '—' }}</strong>
        pour votre campagne
        <strong>« {{ $campaign?->name ?? 'votre campagne' }} »</strong>
        a été validée par notre équipe et est désormais consultable.
    </p>

    @if($pige->photo_path)
        <p style="margin:18px 0;text-align:center">
            <img src="{{ asset('storage/'.$pige->photo_path) }}" alt="Pige photo"
                 style="max-width:100%;border-radius:10px;border:1px solid #e5e7eb">
        </p>
    @endif

    <div class="info">
        <div class="info-row">
            <div class="lbl">Panneau</div>
            <div class="val"><strong>{{ $panel?->reference }}</strong> · {{ $panel?->commune?->name ?? '—' }}</div>
        </div>
        <div class="info-row">
            <div class="lbl">Campagne</div>
            <div class="val">{{ $campaign?->name ?? '—' }}</div>
        </div>
        <div class="info-row">
            <div class="lbl">Date de pose</div>
            <div class="val">{{ $pige->taken_at?->format('d/m/Y') ?? '—' }}</div>
        </div>
    </div>

    <div class="cta-wrap">
        <a href="{{ $url }}" class="cta">Consulter la pige</a>
        <div class="cta-fallback">
            🔒 Lien sécurisé valable {{ $link->expires_at?->diffForHumans(now(), ['parts'=>1]) ?? '30 jours' }}.<br>
            Vous pouvez aussi vous connecter à votre <a href="{{ url('/client') }}">espace client</a> pour retrouver toutes vos piges.
        </div>
    </div>

    <x-slot:footerNote>
        Pige photo générée automatiquement par Panora — opérée par {{ $operator }}.
    </x-slot:footerNote>

</x-mail.layout>
