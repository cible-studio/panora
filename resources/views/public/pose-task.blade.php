<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#c2570d">
    <title>Pose {{ $task->panel?->reference ?? '' }} — CIBLE CI</title>
    <link rel="icon" href="{{ asset('images/faviconl.png') }}" type="image/png">
    <style>
        :root {
            --orange: #c2570d;
            --orange-dim: #fff7ed;
            --bg: #f4f6f8;
            --card: #ffffff;
            --border: #e5e7eb;
            --text: #1f2937;
            --text2: #6b7280;
            --text3: #9ca3af;
            --green: #22c55e;
            --blue: #3b82f6;
            --orange-warn: #f59e0b;
            --red: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            line-height: 1.5;
        }
        body { padding: 16px; padding-bottom: 40px; }

        .wrap { max-width: 480px; margin: 0 auto; }

        /* Header */
        .brand {
            text-align: center;
            margin: 8px 0 18px;
        }
        .brand-name {
            font-weight: 700;
            font-size: 14px;
            color: var(--orange);
            letter-spacing: 1px;
        }
        .brand-sub {
            font-size: 11px;
            color: var(--text3);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-top: 2px;
        }

        /* Card */
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 14px;
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
        }
        .card h1 {
            font-size: 20px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 4px;
            letter-spacing: -0.2px;
        }
        .card h1 .ref {
            font-family: ui-monospace, "SF Mono", monospace;
            font-size: 16px;
            font-weight: 700;
            color: var(--orange);
            display: block;
            margin-bottom: 4px;
        }
        .card h2 {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text3);
            font-weight: 600;
            margin-bottom: 10px;
        }
        .meta-row {
            display: flex;
            justify-content: space-between;
            padding: 9px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
        }
        .meta-row:last-child { border-bottom: none; }
        .meta-row .lbl { color: var(--text2); }
        .meta-row .val { color: var(--text); font-weight: 500; text-align: right; }

        /* Status pill */
        .pill {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        .pill-progress { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
        .pill-done     { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
        .pill-cancel   { background: #fef2f2; color: #991b1b; border-color: #fecaca; }

        /* Progress bar (visualisation) */
        .progress-block { margin: 18px 0 22px; }
        .progress-label-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 12px;
            color: var(--text2);
        }
        .progress-percent-big {
            font-size: 38px;
            font-weight: 700;
            color: var(--text);
            text-align: center;
            margin: 4px 0 12px;
            letter-spacing: -1px;
        }
        .progress-bar {
            height: 10px;
            background: #f1f5f9;
            border-radius: 999px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            border-radius: 999px;
            transition: width .35s ease, background .25s ease;
        }

        /* Slider */
        .slider-block { margin-bottom: 16px; }
        .slider-block label {
            display: block;
            font-size: 13px;
            color: var(--text2);
            margin-bottom: 8px;
            font-weight: 500;
        }
        input[type="range"] {
            -webkit-appearance: none;
            width: 100%;
            height: 6px;
            background: #e5e7eb;
            border-radius: 999px;
            outline: none;
        }
        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 26px;
            height: 26px;
            background: var(--orange);
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(194,87,13,.4);
            border: 3px solid #fff;
        }
        input[type="range"]::-moz-range-thumb {
            width: 26px;
            height: 26px;
            background: var(--orange);
            border-radius: 50%;
            cursor: pointer;
            border: 3px solid #fff;
            box-shadow: 0 2px 6px rgba(194,87,13,.4);
        }

        /* Quick buttons (presets) */
        .presets {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 6px;
            margin-top: 14px;
        }
        .preset-btn {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 0;
            font-size: 13px;
            font-weight: 600;
            color: var(--text2);
            cursor: pointer;
            transition: all .15s;
        }
        .preset-btn:hover, .preset-btn.active {
            background: var(--orange-dim);
            border-color: var(--orange);
            color: var(--orange);
        }

        /* Note input */
        .note-block { margin-top: 16px; }
        .note-block label {
            display: block;
            font-size: 12px;
            color: var(--text2);
            margin-bottom: 6px;
            font-weight: 500;
        }
        textarea {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 12px;
            font-family: inherit;
            font-size: 14px;
            color: var(--text);
            resize: vertical;
            min-height: 70px;
        }
        textarea:focus { outline: 2px solid var(--orange); outline-offset: -1px; }

        /* CTA */
        .cta {
            display: block;
            width: 100%;
            background: var(--orange);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 18px;
            transition: background .15s, transform .05s;
        }
        .cta:hover:not(:disabled) { background: #a04609; }
        .cta:active:not(:disabled) { transform: scale(.98); }
        .cta:disabled {
            background: #d1d5db;
            color: #6b7280;
            cursor: not-allowed;
        }

        .cta-call {
            display: block;
            text-align: center;
            text-decoration: none;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px;
            color: var(--text2);
            font-weight: 500;
            font-size: 13px;
            margin-top: 8px;
            background: var(--card);
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            background: #111827;
            color: #fff;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 8px 24px rgba(0,0,0,.25);
            z-index: 100;
            opacity: 0;
            transition: opacity .25s, transform .25s;
            pointer-events: none;
        }
        .toast.show { opacity: 1; transform: translateX(-50%) translateY(-4px); }
        .toast.success { background: #166534; }
        .toast.error   { background: #991b1b; }

        /* Done state */
        .done-banner {
            text-align: center;
            padding: 32px 22px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 14px;
        }
        .done-banner .check {
            width: 64px;
            height: 64px;
            margin: 0 auto 14px;
            border-radius: 50%;
            background: var(--green);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 32px;
            font-weight: 700;
        }
        .done-banner h3 { font-size: 18px; color: #166534; margin-bottom: 4px; }
        .done-banner p { font-size: 13px; color: #15803d; }

        .footer {
            text-align: center;
            margin-top: 18px;
            font-size: 11px;
            color: var(--text3);
        }

        @media (max-width: 360px) {
            .presets { grid-template-columns: repeat(3, 1fr); }
            .progress-percent-big { font-size: 32px; }
        }
    </style>
</head>
<body>

<div class="wrap">

    {{-- Brand --}}
    <div class="brand">
        <div class="brand-name">CIBLE CI</div>
        <div class="brand-sub">Suivi de pose</div>
    </div>

    @if($isCancelled)
        <div class="card">
            <div class="done-banner" style="background:#fef2f2;border-color:#fecaca;">
                <div class="check" style="background:#ef4444;">×</div>
                <h3 style="color:#991b1b;">Tâche annulée</h3>
                <p style="color:#dc2626;">Cette pose a été annulée. Contactez votre superviseur.</p>
            </div>
        </div>
    @elseif($isDone)
        <div class="card">
            <div class="done-banner">
                <div class="check">✓</div>
                <h3>Pose terminée</h3>
                <p>
                    Marquée terminée le {{ $task->done_at?->format('d/m/Y à H:i') }}@if($task->real_minutes)
                        — durée : {{ $task->real_minutes }} min
                    @endif
                </p>
            </div>
        </div>
    @else
        {{-- Carte d'identité de la tâche --}}
        <div class="card">
            <h1>
                <span class="ref">{{ $task->panel?->reference ?? '—' }}</span>
                {{ $task->panel?->name ?? 'Panneau' }}
            </h1>
            <div style="margin-bottom:14px;">
                @php
                    $statusLabel = \App\Enums\PoseTaskStatus::tryFrom($task->status)?->label() ?? '—';
                    $pillClass   = match ($task->status) {
                        'en_cours' => 'pill pill-progress',
                        'realisee' => 'pill pill-done',
                        'annulee'  => 'pill pill-cancel',
                        default    => 'pill',
                    };
                @endphp
                <span class="{{ $pillClass }}">{{ $statusLabel }}</span>
            </div>

            <div class="meta-row">
                <span class="lbl">Adresse</span>
                <span class="val">
                    @if($task->panel?->adresse){{ $task->panel->adresse }} —@endif
                    {{ $task->panel?->commune?->name ?? '—' }}
                </span>
            </div>
            @if($task->panel?->quartier)
                <div class="meta-row">
                    <span class="lbl">Quartier</span>
                    <span class="val">{{ $task->panel->quartier }}</span>
                </div>
            @endif
            <div class="meta-row">
                <span class="lbl">Format</span>
                <span class="val">{{ $task->panel?->format?->name ?? '—' }}</span>
            </div>
            @if($task->campaign)
                <div class="meta-row">
                    <span class="lbl">Campagne</span>
                    <span class="val">{{ $task->campaign->name }}</span>
                </div>
            @endif
            @if($task->campaign?->client)
                <div class="meta-row">
                    <span class="lbl">Client</span>
                    <span class="val">{{ $task->campaign->client->name }}</span>
                </div>
            @endif
            <div class="meta-row">
                <span class="lbl">Prévue le</span>
                <span class="val">{{ $task->scheduled_at?->format('d/m/Y à H:i') ?? '—' }}</span>
            </div>
            @if($task->panel?->latitude && $task->panel?->longitude)
                <div class="meta-row">
                    <span class="lbl">GPS</span>
                    <span class="val">
                        <a href="https://maps.google.com/?q={{ $task->panel->latitude }},{{ $task->panel->longitude }}"
                           style="color:var(--orange);text-decoration:none;font-weight:600">
                            Ouvrir Maps →
                        </a>
                    </span>
                </div>
            @endif
        </div>

        {{-- Progression --}}
        <div class="card">
            <h2>Avancement</h2>
            <div class="progress-percent-big" id="bigPercent">{{ $task->progress_percent ?? 0 }} %</div>
            <div class="progress-bar">
                <div id="progressFill" class="progress-fill"
                     style="width: {{ $task->progress_percent ?? 0 }}%; background: {{ $task->progressColor() }};"></div>
            </div>

            <form id="progressForm" onsubmit="return submitProgress(event)" style="margin-top:18px;">
                @csrf

                <div class="slider-block">
                    <label for="progress">Glissez pour ajuster votre progression</label>
                    <input type="range" id="progress" name="progress" min="0" max="100" step="5"
                           value="{{ $task->progress_percent ?? 0 }}"
                           oninput="onSlide(this.value)">
                </div>

                <div class="presets">
                    @foreach([0, 25, 50, 75, 100] as $p)
                        <button type="button" class="preset-btn" data-val="{{ $p }}" onclick="setProgress({{ $p }})">
                            {{ $p }}%
                        </button>
                    @endforeach
                </div>

                <div class="note-block">
                    <label for="note">Note (optionnelle)</label>
                    <textarea id="note" name="note" maxlength="500" placeholder="Ex : retard de 30min, panneau abîmé, photos prises..."></textarea>
                </div>

                <button type="submit" class="cta" id="submitBtn">Mettre à jour</button>

                @if($task->technicien?->whatsapp_number)
                    <a href="tel:{{ $task->technicien->whatsapp_number }}" class="cta-call">
                        ☎ Appeler le superviseur
                    </a>
                @endif
            </form>
        </div>
    @endif

    <div class="footer">
        © {{ date('Y') }} CIBLE CI · Lien sécurisé personnel
    </div>
</div>

<div id="toast" class="toast"></div>

<script>
    const TOKEN     = @js($task->public_token);
    const UPDATE_URL = "{{ route('pose.public.update', $task->public_token ?? '') }}";
    const CSRF      = document.querySelector('meta[name="csrf-token"]').content;

    let _busy = false;

    function setProgress(v) {
        const slider = document.getElementById('progress');
        if (slider) {
            slider.value = v;
            onSlide(v);
        }
        document.querySelectorAll('.preset-btn').forEach(b => {
            b.classList.toggle('active', Number(b.dataset.val) === Number(v));
        });
    }

    function onSlide(v) {
        const fill = document.getElementById('progressFill');
        const big  = document.getElementById('bigPercent');
        if (fill) fill.style.width = v + '%';
        if (big)  big.textContent  = v + ' %';
        if (fill) fill.style.background = colorFor(v);
        // Highlight closest preset
        document.querySelectorAll('.preset-btn').forEach(b => {
            b.classList.toggle('active', Number(b.dataset.val) === Number(v));
        });
    }

    function colorFor(p) {
        p = Number(p);
        if (p >= 100) return '#22c55e';
        if (p >=  67) return '#3b82f6';
        if (p >=  34) return '#f59e0b';
        return '#ef4444';
    }

    function showToast(msg, type) {
        const t = document.getElementById('toast');
        t.className = 'toast ' + (type || '');
        t.textContent = msg;
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 3500);
    }

    async function submitProgress(ev) {
        ev.preventDefault();
        if (_busy) return false;

        const btn = document.getElementById('submitBtn');
        const progress = Number(document.getElementById('progress').value);
        const note = document.getElementById('note').value.trim();

        _busy = true;
        btn.disabled = true;
        btn.textContent = 'Envoi…';

        try {
            const res = await fetch(UPDATE_URL, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': CSRF,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ progress, note: note || null }),
            });
            const data = await res.json();

            if (!res.ok || !data.ok) {
                showToast(data.message || 'Erreur — réessayez.', 'error');
                return false;
            }

            showToast(data.message || 'Mise à jour OK', 'success');
            document.getElementById('note').value = '';

            if (data.is_done) {
                // Recharger pour afficher la vue "terminée"
                setTimeout(() => location.reload(), 1500);
            }
        } catch (e) {
            showToast('Erreur réseau — réessayez.', 'error');
        } finally {
            _busy = false;
            btn.disabled = false;
            btn.textContent = 'Mettre à jour';
        }
        return false;
    }

    // Initial state
    setProgress({{ $task->progress_percent ?? 0 }});
</script>

</body>
</html>
