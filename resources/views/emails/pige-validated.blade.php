@php
    $ch = config('charte.couleurs');
    $operator = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    // Axe (2026-11-12) : un seul e-mail pour tous ses pieds (AvisPigeValidee).
    $axe = $axe ?? null;
    $photosAxe = $photosAxe ?? collect();
    $nbPieds = $photosAxe->count();
    $nomAxe = $axe ? app(\App\Services\Axes\AxeService::class)->nomCourt($axe->name) : null;
    $title = $axe
        ? "Photos de l'axe {$axe->reference} disponibles"
        : "Pige photo disponible — {$panel?->reference}";
    $preheader = $axe
        ? "Les {$nbPieds} pieds de l'axe {$axe->reference} sont photographiés et validés pour votre campagne « " . ($campaign?->name ?? '—') . " »."
        : "Photo du panneau {$panel?->reference} validée pour votre campagne « " . ($campaign?->name ?? '—') . " ».";
@endphp

<x-mail.layout :title="$title" :preheader="$preheader">

    <span class="pill pill-success">📸 {{ $axe ? 'Photos validées' : 'Pige photo validée' }}</span>

    <h1>{{ $axe ? 'Les photos de votre axe sont disponibles' : 'Votre pige photo est disponible' }}</h1>

    <p>Bonjour {{ $client?->name ?? '—' }},</p>

    @if($axe)
        <p>
            Les <strong>{{ $nbPieds }} pied{{ $nbPieds > 1 ? 's' : '' }}</strong> de l'axe
            <strong>{{ $axe->reference }}</strong>{{ $nomAxe ? ' (' . $nomAxe . ')' : '' }}
            pour votre campagne
            <strong>« {{ $campaign?->name ?? 'votre campagne' }} »</strong>
            ont été photographiés et validés par notre équipe. Leurs photos sont désormais consultables.
        </p>

        @foreach($photosAxe->take(6) as $p)
            @if($p->photo_path)
                <p style="margin:14px 0 4px;font-size:13px;color:{{ $ch['gris'] ?? '#666' }}"><strong>Pied {{ $p->panel?->pied_numero }}</strong> · {{ $p->panel?->reference }}</p>
                <p style="margin:0 0 10px;text-align:center">
                    <img src="{{ asset('storage/'.$p->photo_path) }}" alt="Pied {{ $p->panel?->pied_numero }}"
                         style="display:block;margin:0 auto;max-width:100%;height:auto;border:1px solid {{ $ch['gris'] }}">
                </p>
            @endif
        @endforeach
        @if($nbPieds > 6)
            <p style="font-size:13px">… et {{ $nbPieds - 6 }} autre{{ $nbPieds - 6 > 1 ? 's' : '' }} photo{{ $nbPieds - 6 > 1 ? 's' : '' }} à consulter en ligne.</p>
        @endif
    @else
        <p>
            La photo du panneau <strong>{{ $panel?->reference ?? '—' }}</strong>
            pour votre campagne
            <strong>« {{ $campaign?->name ?? 'votre campagne' }} »</strong>
            a été validée par notre équipe et est désormais consultable.
        </p>

        @if($pige->photo_path)
            <p style="margin:18px 0;text-align:center">
                <img src="{{ asset('storage/'.$pige->photo_path) }}" alt="Pige photo"
                     style="display:block;margin:0 auto;max-width:100%;height:auto;border:1px solid {{ $ch['gris'] }}">
            </p>
        @endif
    @endif

    <div class="info">
        <div class="info-row">
            <div class="lbl">{{ $axe ? 'Axe' : 'Panneau' }}</div>
            <div class="val">
                @if($axe)
                    <strong>{{ $axe->reference }}</strong> · {{ $nbPieds }} pied{{ $nbPieds > 1 ? 's' : '' }} · {{ $panel?->commune?->name ?? '—' }}
                @else
                    <strong>{{ $panel?->reference }}</strong> · {{ $panel?->commune?->name ?? '—' }}
                @endif
            </div>
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
        <a href="{{ $url }}" class="cta">{{ $axe ? 'Voir les photos de l\'axe' : 'Consulter la pige' }}</a>
        <div class="cta-fallback">
            🔒 Lien sécurisé valable {{ $link->expires_at?->diffForHumans(now(), ['parts'=>1]) ?? '30 jours' }}.<br>
            Vous pouvez aussi vous connecter à votre <a href="{{ url('/client') }}">espace client</a> pour retrouver toutes vos piges.
        </div>
    </div>

    <x-slot:footerNote>
        Pige photo générée automatiquement par Panora — opérée par {{ $operator }}.
    </x-slot:footerNote>

</x-mail.layout>
