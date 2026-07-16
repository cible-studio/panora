{{--
    Composant screenshot style "browser window" éditorial.
    Usage : @include('public.landing._screenshot', ['src' => 'dashboard.png', 'alt' => '…', 'caption' => '…'])
    Fallback élégant si l'image n'existe pas encore (placeholder gradient).
--}}
@php
    $srcPath  = 'images/landing/' . ($src ?? 'placeholder.png');
    $exists   = file_exists(public_path($srcPath));
    $alt      = $alt ?? 'Aperçu Panora';
    $caption  = $caption ?? null;
    $device   = $device ?? 'desktop';  // desktop | mobile
    $accent   = $accent ?? false;      // ombre orange
@endphp
<figure class="screenshot {{ $device === 'mobile' ? 'is-mobile' : '' }} {{ $accent ? 'is-accent' : '' }}">
    <div class="screenshot-window">
        <div class="screenshot-chrome">
            <span class="chrome-dot"></span>
            <span class="chrome-dot"></span>
            <span class="chrome-dot"></span>
            <span class="chrome-url">panora-cible.com</span>
        </div>
        <div class="screenshot-body">
            @if($exists)
                <img src="{{ asset($srcPath) }}" alt="{{ $alt }}" loading="lazy">
            @else
                <div class="screenshot-placeholder">
                    <div>
                        <strong>{{ $alt }}</strong>
                        <small>À déposer : <code>public/{{ $srcPath }}</code></small>
                    </div>
                </div>
            @endif
        </div>
    </div>
    @if($caption)
        <figcaption>{{ $caption }}</figcaption>
    @endif
</figure>

@once
    @push('head')
    <style>
        .screenshot { margin: 0; }
        .screenshot.is-accent .screenshot-window {
            box-shadow: 0 60px 100px -40px rgba(217, 78, 31, 0.35),
                        0 30px 60px -30px rgba(11, 15, 25, 0.25);
        }
        .screenshot-window {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 40px 80px -30px rgba(11, 15, 25, 0.22),
                        0 12px 24px -12px rgba(11, 15, 25, 0.1);
        }
        .screenshot-chrome {
            display: flex; align-items: center; gap: 6px;
            padding: 12px 16px;
            background: linear-gradient(#fafaf7, #f3f4f6);
            border-bottom: 1px solid var(--line);
        }
        .chrome-dot {
            width: 11px; height: 11px; border-radius: 50%;
            background: #e5e7eb;
        }
        .chrome-dot:nth-child(1) { background: #ff5f57; }
        .chrome-dot:nth-child(2) { background: #febc2e; }
        .chrome-dot:nth-child(3) { background: #28c840; }
        .chrome-url {
            margin-left: 14px;
            font-family: 'Inter', monospace;
            font-size: 11px;
            color: var(--ink-4);
            letter-spacing: 0.02em;
        }
        .screenshot-body {
            background: #fff;
            aspect-ratio: 16 / 9;
            overflow: hidden;
        }
        .screenshot-body img {
            width: 100%; height: 100%;
            object-fit: cover; object-position: top left;
            display: block;
        }
        .screenshot-placeholder {
            width: 100%; height: 100%;
            background: linear-gradient(135deg, var(--bg-cream), var(--bg-soft));
            display: flex; align-items: center; justify-content: center;
            padding: 40px; text-align: center;
        }
        .screenshot-placeholder strong {
            display: block;
            font-family: 'Fraunces', serif;
            font-weight: 500;
            font-size: 18px;
            color: var(--ink-3);
            margin-bottom: 10px;
        }
        .screenshot-placeholder small {
            display: block;
            font-size: 11px;
            color: var(--ink-5);
            font-family: monospace;
        }
        .screenshot-placeholder code {
            background: rgba(0,0,0,0.05);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10.5px;
        }
        .screenshot figcaption {
            margin-top: 16px;
            font-size: 13.5px;
            color: var(--ink-4);
            text-align: center;
            font-style: italic;
        }

        /* Variante mobile */
        .screenshot.is-mobile {
            max-width: 300px; margin: 0 auto;
        }
        .screenshot.is-mobile .screenshot-window {
            border-radius: 32px;
            border: 8px solid #0b0f19;
            padding: 4px 0;
            background: #0b0f19;
            box-shadow: 0 30px 60px -20px rgba(11, 15, 25, 0.4);
        }
        .screenshot.is-mobile .screenshot-chrome {
            display: none;
        }
        .screenshot.is-mobile .screenshot-body {
            aspect-ratio: 9 / 19.5;
            border-radius: 24px;
            overflow: hidden;
        }
    </style>
    @endpush
@endonce
