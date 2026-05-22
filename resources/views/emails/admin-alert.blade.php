@php
    $operator = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));

    // Mapping couleur héritée → pill class. Le layout x-mail.layout n'expose
    // que 4 variantes : info (par défaut), success, warning, danger.
    $pillClass = match(true) {
        str_contains($color ?? '', '22c55e') || str_contains($color ?? '', '16a34a') => 'pill pill-success',
        str_contains($color ?? '', 'f59e0b') || str_contains($color ?? '', 'e8a020') => 'pill pill-warning',
        str_contains($color ?? '', 'ef4444') || str_contains($color ?? '', 'dc2626') => 'pill pill-danger',
        default => 'pill',
    };

    $preheader = $summary ?: "Alerte interne Panora — {$title}";
@endphp

<x-mail.layout :title="$title" :preheader="$preheader">

    <span class="{{ $pillClass }}">{{ $emoji ?? 'ℹ️' }} Alerte interne</span>

    <h1>{{ $title }}</h1>

    @if($summary)
        <p>{{ $summary }}</p>
    @endif

    @if(!empty($lines))
        <div class="info">
            @foreach($lines as $line)
                <div class="info-row">
                    <div class="val" style="width:100%;text-align:left;font-size:14px;color:#374151">{{ $line }}</div>
                </div>
            @endforeach
        </div>
    @endif

    @if(!empty($ctaLabel) && !empty($ctaUrl))
        <div class="cta-wrap">
            <a href="{{ $ctaUrl }}" class="cta">{{ $ctaLabel }}</a>
            <div class="cta-fallback">
                Lien direct : <a href="{{ $ctaUrl }}">{{ $ctaUrl }}</a>
            </div>
        </div>
    @endif

    @if(!empty($footer))
        <p style="margin-top:24px;font-size:12px;color:#9ca3af;text-align:center">{{ $footer }}</p>
    @endif

    <x-slot:footerNote>
        Notification interne automatique — vous recevez ce mail car vous faites partie de l'équipe {{ $operator }}.
    </x-slot:footerNote>

</x-mail.layout>
