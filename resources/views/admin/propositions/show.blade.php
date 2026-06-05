{{-- Page publique proposition — design sobre & pro (light theme) --}}
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Proposition {{ $reservation->reference }} — CIBLE CI</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="{{ asset('images/faviconl.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    /* ─────────── Design system pro (Stripe / Notion / Linear style) ─────────── */
    :root {
        --bg:        #f4f6f8;
        --card:      #ffffff;
        --border:    #e5e7eb;
        --border-strong: #d1d5db;
        --text:      #111827;
        --text2:     #4b5563;
        --text3:     #9ca3af;
        --accent:    #c2570d;
        --accent-hover: #a04609;
        --accent-soft: #fff7ed;
        --green:     #16a34a;
        --green-hover: #15803d;
        --red:       #dc2626;
        --red-hover: #b91c1c;
        --warning-bg: #fffbeb;
        --warning-border: #fde68a;
        --warning-text: #92400e;
        --radius:    8px;
        --radius-lg: 12px;
    }

    *, *::before, *::after { box-sizing: border-box; }
    body, html { margin: 0; padding: 0; }

    body {
        font-family: 'DM Sans', system-ui, sans-serif;
        background: var(--bg);
        color: var(--text);
        font-size: 14px;
        line-height: 1.5;
        -webkit-font-smoothing: antialiased;
    }

    a { color: var(--accent); text-decoration: none; }
    a:hover { text-decoration: underline; }

    /* ─────────── Header ─────────── */
    .header {
        background: var(--card);
        border-bottom: 1px solid var(--border);
        position: sticky;
        top: 0;
        z-index: 50;
    }
    .header-inner {
        max-width: 980px;
        margin: 0 auto;
        padding: 16px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }
    .brand {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .brand-logo {
        font-weight: 700;
        font-size: 18px;
        color: var(--text);
        letter-spacing: -0.3px;
    }
    .brand-logo .accent { color: var(--accent); }
    .brand-sub {
        font-size: 11px;
        color: var(--text3);
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-top: -2px;
    }
    .header-meta {
        font-size: 12px;
        color: var(--text3);
        text-align: right;
    }
    .header-meta .ref {
        font-family: ui-monospace, "SF Mono", Menlo, monospace;
        color: var(--text);
        font-weight: 600;
    }

    /* ─────────── Container ─────────── */
    .container {
        max-width: 980px;
        margin: 0 auto;
        padding: 32px 24px 48px;
    }

    /* ─────────── Alerts ─────────── */
    .alert {
        padding: 12px 16px;
        border-radius: var(--radius);
        font-size: 13px;
        margin-bottom: 16px;
        display: flex;
        gap: 10px;
        align-items: flex-start;
    }
    .alert-error   { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
    .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }

    /* ─────────── Hero (intro) ─────────── */
    .intro {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 32px;
        margin-bottom: 20px;
    }
    .intro .pill {
        display: inline-block;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: var(--accent);
        background: var(--accent-soft);
        padding: 4px 10px;
        border-radius: 999px;
        margin-bottom: 16px;
    }
    .intro h1 {
        font-size: 24px;
        font-weight: 600;
        color: var(--text);
        line-height: 1.3;
        margin: 0 0 8px;
        letter-spacing: -0.3px;
    }
    .intro p {
        font-size: 14px;
        color: var(--text2);
        margin: 0;
    }

    /* Période / résumé */
    .summary {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-top: 24px;
        padding-top: 24px;
        border-top: 1px solid var(--border);
    }
    .summary-cell .lbl {
        font-size: 11px;
        color: var(--text3);
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 4px;
        font-weight: 600;
    }
    .summary-cell .val {
        font-size: 15px;
        font-weight: 600;
        color: var(--text);
    }

    /* Bandeau expiration */
    .expire {
        margin-top: 16px;
        padding: 10px 14px;
        background: var(--warning-bg);
        border: 1px solid var(--warning-border);
        border-radius: var(--radius);
        font-size: 13px;
        color: var(--warning-text);
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .expired {
        background: #fef2f2;
        border-color: #fecaca;
        color: #991b1b;
    }

    /* ─────────── Section panneaux ─────────── */
    .section-head {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        margin: 8px 4px 14px;
    }
    .section-head h2 {
        font-size: 16px;
        font-weight: 600;
        color: var(--text);
    }
    .section-head .count {
        font-size: 13px;
        color: var(--text3);
    }

    .panels-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 14px;
    }
    @media (max-width: 800px) {
        .panels-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 540px) {
        .panels-grid { grid-template-columns: 1fr; }
        .summary { grid-template-columns: repeat(2, 1fr); }
    }

    .panel {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transition: border-color .15s, box-shadow .15s;
        position: relative;
    }
    .panel:hover { border-color: var(--border-strong); box-shadow: 0 1px 3px rgba(0,0,0,.04); }
    .panel.selected { border-color: #dc2626; box-shadow: 0 0 0 1px #dc2626; }

    /* ── Checkbox de sélection multiple (coin haut-gauche) ── */
    .panel-select {
        position: absolute;
        top: 10px;
        left: 10px;
        z-index: 5;
        cursor: pointer;
        display: inline-flex;
    }
    .panel-select input { display: none; }
    .panel-select-box {
        width: 26px;
        height: 26px;
        border-radius: 7px;
        background: rgba(255,255,255,.95);
        border: 2px solid #fff;
        box-shadow: 0 1px 4px rgba(0,0,0,.25);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: background .15s, border-color .15s, transform .15s;
    }
    .panel-select-box svg { width: 14px; height: 14px; opacity: 0; transition: opacity .15s; }
    .panel-select input:checked + .panel-select-box {
        background: #dc2626;
        border-color: #dc2626;
    }
    .panel-select input:checked + .panel-select-box svg { opacity: 1; }
    .panel-select:hover .panel-select-box { transform: scale(1.06); }

    /* ── Barre d'action bulk (sticky en bas, visible si > 0 sélection) ── */
    .bulk-bar {
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%) translateY(160%);
        z-index: 1000;
        background: #0f172a;
        color: #fff;
        padding: 12px 16px 12px 20px;
        border-radius: 14px;
        box-shadow: 0 10px 32px rgba(0,0,0,.35);
        display: flex;
        align-items: center;
        gap: 14px;
        transition: transform .25s ease;
        max-width: calc(100vw - 32px);
        flex-wrap: wrap;
    }
    .bulk-bar.open { transform: translateX(-50%) translateY(0); }
    .bulk-bar-count {
        font-size: 13px;
        font-weight: 700;
    }
    .bulk-bar-count strong { color: #fab80b; }
    .bulk-bar button {
        background: #dc2626;
        color: #fff;
        border: 0;
        padding: 9px 16px;
        border-radius: 9px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
    }
    .bulk-bar button.secondary {
        background: rgba(255,255,255,.1);
        color: #fff;
    }
    .bulk-bar button:active { transform: translateY(1px); }

    .panel-photo {
        height: 140px;
        background: #f3f4f6;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border-bottom: 1px solid var(--border);
    }
    .panel-photo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .panel-photo .placeholder {
        font-family: ui-monospace, monospace;
        font-size: 13px;
        color: var(--text3);
        font-weight: 600;
    }

    .panel-body {
        padding: 14px 16px;
        display: flex;
        flex-direction: column;
        gap: 6px;
        flex: 1;
    }
    .panel-ref {
        font-family: ui-monospace, monospace;
        font-size: 12px;
        font-weight: 700;
        color: var(--accent);
        letter-spacing: 0.3px;
    }
    .panel-name {
        font-size: 14px;
        font-weight: 600;
        color: var(--text);
        line-height: 1.35;
    }
    .panel-meta {
        font-size: 12px;
        color: var(--text2);
        line-height: 1.5;
    }
    .panel-tags {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin-top: 4px;
    }
    .panel-tag {
        font-size: 10px;
        font-weight: 500;
        color: var(--text2);
        background: #f3f4f6;
        padding: 2px 8px;
        border-radius: 999px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .panel-price {
        margin-top: auto;
        padding-top: 12px;
        border-top: 1px dashed var(--border);
        display: flex;
        justify-content: space-between;
        align-items: baseline;
    }
    .panel-price .lbl {
        font-size: 11px;
        color: var(--text3);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .panel-price .val {
        font-size: 14px;
        font-weight: 700;
        color: var(--text);
    }

    .panel-remove {
        padding: 8px 14px 12px;
    }
    .panel-remove button {
        width: 100%;
        padding: 7px;
        font-size: 11px;
        font-weight: 500;
        color: var(--text3);
        background: transparent;
        border: 1px solid var(--border);
        border-radius: 6px;
        cursor: pointer;
        transition: all .15s;
    }
    .panel-remove button:hover { color: var(--red); border-color: #fecaca; background: #fef2f2; }

    /* ─────────── Total ─────────── */
    .total {
        margin-top: 24px;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 24px 28px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 24px;
        flex-wrap: wrap;
    }
    .total .lbl {
        font-size: 12px;
        color: var(--text3);
        text-transform: uppercase;
        letter-spacing: 0.8px;
        font-weight: 600;
        margin-bottom: 4px;
    }
    .total .amount {
        font-size: 28px;
        font-weight: 700;
        color: var(--text);
        letter-spacing: -0.5px;
    }
    .total .sub {
        font-size: 12px;
        color: var(--text3);
        margin-top: 4px;
    }
    .total-right {
        text-align: right;
    }
    .total-right .stat {
        font-size: 14px;
        color: var(--text2);
    }
    .total-right .stat strong { color: var(--text); }

    /* ─────────── CTA ─────────── */
    .cta {
        margin-top: 24px;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 28px;
        text-align: center;
    }
    .cta h3 {
        font-size: 16px;
        font-weight: 600;
        color: var(--text);
        margin: 0 0 6px;
    }
    .cta p {
        font-size: 13px;
        color: var(--text2);
        margin: 0 0 18px;
    }
    .cta-buttons {
        display: flex;
        gap: 10px;
        justify-content: center;
        flex-wrap: wrap;
    }
    .btn {
        font-family: inherit;
        font-size: 14px;
        font-weight: 600;
        padding: 11px 24px;
        border-radius: var(--radius);
        border: 1px solid transparent;
        cursor: pointer;
        transition: all .15s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-primary {
        background: var(--green);
        color: #fff;
    }
    .btn-primary:hover { background: var(--green-hover); }

    .btn-secondary {
        background: var(--card);
        color: var(--text);
        border-color: var(--border-strong);
    }
    .btn-secondary:hover { background: var(--bg); border-color: #9ca3af; }

    .btn-danger {
        background: var(--card);
        color: var(--red);
        border-color: #fecaca;
    }
    .btn-danger:hover { background: #fef2f2; border-color: var(--red); }

    .cta-note {
        margin-top: 16px;
        font-size: 11px;
        color: var(--text3);
    }

    /* ─────────── Footer ─────────── */
    .footer {
        max-width: 980px;
        margin: 0 auto;
        padding: 24px;
        text-align: center;
        font-size: 12px;
        color: var(--text3);
        border-top: 1px solid var(--border);
    }

    /* ─────────── Modals ─────────── */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(17, 24, 39, 0.5);
        backdrop-filter: blur(2px);
        z-index: 100;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    .modal-overlay.open { display: flex; }
    .modal {
        background: var(--card);
        border-radius: var(--radius-lg);
        padding: 28px;
        max-width: 440px;
        width: 100%;
        box-shadow: 0 12px 32px rgba(0, 0, 0, .12);
    }
    .modal h3 {
        font-size: 16px;
        font-weight: 600;
        color: var(--text);
        margin: 0 0 8px;
    }
    .modal p {
        font-size: 13px;
        color: var(--text2);
        margin: 0 0 16px;
        line-height: 1.6;
    }
    .modal-warning {
        background: var(--warning-bg);
        border: 1px solid var(--warning-border);
        border-radius: var(--radius);
        padding: 10px 14px;
        font-size: 12px;
        color: var(--warning-text);
        margin-bottom: 18px;
    }
    .modal textarea {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid var(--border-strong);
        border-radius: var(--radius);
        font-size: 13px;
        font-family: inherit;
        color: var(--text);
        background: var(--card);
        margin-bottom: 16px;
        min-height: 90px;
        resize: vertical;
    }
    .modal textarea:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-soft);
    }
    .modal-btns {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
        flex-wrap: wrap;
    }
</style>
</head>
<body>

{{-- ────────── HEADER ────────── --}}
<header class="header">
    <div class="header-inner">
        <div class="brand">
            <img src="{{ asset('images/logol.png') }}" alt="CIBLE CI"
                 style="height:42px;display:block;"
                 onerror="this.outerHTML='<div class=&quot;brand-logo&quot;>CIBLE <span class=&quot;accent&quot;>CI</span></div><div class=&quot;brand-sub&quot;>Régie Publicitaire</div>';">
        </div>
        <div class="header-meta">
            <div>Proposition <span class="ref">{{ $reservation->reference }}</span></div>
            @if($reservation->proposition_sent_at)
                <div style="margin-top:2px">Envoyée le {{ $reservation->proposition_sent_at->format('d/m/Y') }}</div>
            @endif
        </div>
    </div>
</header>

<div class="container">

    {{-- Alerts session --}}
    @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- ────────── INTRO ────────── --}}
    <div class="intro">
        <span class="pill">Proposition commerciale</span>
        <h1>Bonjour {{ $reservation->client?->name ?? 'Client' }},</h1>
        <p>
            Notre équipe a sélectionné <strong>{{ $panels->count() }} emplacement{{ $panels->count() > 1 ? 's' : '' }}</strong>
            adapté{{ $panels->count() > 1 ? 's' : '' }} à vos besoins. Consultez les détails ci-dessous puis
            confirmez ou refusez la proposition.
        </p>

        @php
            $totalDays   = (int) $reservation->start_date->copy()->startOfDay()
                ->diffInDays($reservation->end_date->copy()->startOfDay());
            $totalDays   = max(1, $totalDays);
            $monthsLabel = rtrim(rtrim(number_format($months, 1, ',', ''), '0'), ',');
        @endphp

        <div class="summary">
            <div class="summary-cell">
                <div class="lbl">Début</div>
                <div class="val">{{ $reservation->start_date->format('d/m/Y') }}</div>
            </div>
            <div class="summary-cell">
                <div class="lbl">Fin</div>
                <div class="val">{{ $reservation->end_date->format('d/m/Y') }}</div>
            </div>
            <div class="summary-cell">
                <div class="lbl">Durée</div>
                <div class="val">{{ $totalDays }} jour{{ $totalDays > 1 ? 's' : '' }}</div>
                <div style="font-size:11px;color:var(--text3);margin-top:2px;">
                    {{ $monthsLabel }} mois facturé{{ $months > 1 ? 's' : '' }}
                </div>
            </div>
            <div class="summary-cell">
                <div class="lbl">Emplacements</div>
                <div class="val">{{ $panels->count() }}</div>
            </div>
        </div>

        @if($expiresIn !== null && $expiresIn > 0)
            <div class="expire">
                <span>⏱</span>
                <span>
                    Cette proposition expire dans
                    <strong>{{ $expiresIn > 24 ? round($expiresIn / 24) . ' jour(s)' : $expiresIn . ' heure(s)' }}</strong>
                    — le {{ $reservation->proposition_expires_at->format('d/m/Y à H:i') }}
                </span>
            </div>
        @elseif($expiresIn !== null && $expiresIn <= 0)
            <div class="expire expired">
                <span>⚠</span>
                <span>Cette proposition a expiré.</span>
            </div>
        @endif
    </div>

    {{-- ────────── PANNEAUX ────────── --}}
    <div class="section-head">
        <h2>Emplacements proposés</h2>
        <div class="count">{{ $panels->count() }} panneau{{ $panels->count() > 1 ? 'x' : '' }}</div>
    </div>

    <div class="panels-grid" id="panels-grid">
        @foreach($panels as $panel)
            @php $canRemove = $isActif && ($panel['source'] ?? 'interne') === 'interne'; @endphp
            <div class="panel" data-panel-id="{{ $panel['id'] }}">
                @if($canRemove)
                    <label class="panel-select" title="Sélectionner pour retrait groupé">
                        <input type="checkbox" class="panel-checkbox" value="{{ $panel['id'] }}">
                        <span class="panel-select-box" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </span>
                    </label>
                @endif
                <div class="panel-photo">
                    @if($panel['photo_url'])
                        <img src="{{ $panel['photo_url'] }}" alt="{{ $panel['reference'] }}" loading="lazy"
                             onerror="this.onerror=null;this.parentElement.innerHTML='<span class=\'placeholder\'>{{ $panel['reference'] }}</span>'">
                    @else
                        <span class="placeholder">{{ $panel['reference'] }}</span>
                    @endif
                </div>

                <div class="panel-body">
                    <div class="panel-ref">{{ $panel['reference'] }}</div>
                    <div class="panel-name">{{ \Illuminate\Support\Str::limit($panel['name'], 50) }}</div>

                    <div class="panel-meta">
                        {{ $panel['commune'] }}
                        @if($panel['zone'] !== '—') · {{ $panel['zone'] }} @endif
                    </div>

                    <div class="panel-tags">
                        @if($panel['dimensions'])
                            <span class="panel-tag">{{ $panel['dimensions'] }}</span>
                        @endif
                        @if($panel['surface'] ?? null)
                            <span class="panel-tag">{{ $panel['surface'] }}</span>
                        @endif
                        @if($panel['category'] !== '—')
                            <span class="panel-tag">{{ $panel['category'] }}</span>
                        @endif
                        @if($panel['is_lit'])
                            <span class="panel-tag" style="color:#a04609;background:#fff7ed">Éclairé</span>
                        @endif
                    </div>

                    <div class="panel-price">
                        <span class="lbl">Tarif campagne</span>
                        @if($panel['total'] > 0)
                            <span class="val">{{ number_format($panel['total'], 0, ',', ' ') }} FCFA</span>
                        @else
                            {{-- 0 FCFA est un tarif valide (campagne offerte / package
                                 inclus). On l'affiche tel quel avec un badge "Offert"
                                 plutôt que "Sur devis" qui suggérait à tort une donnée
                                 manquante. --}}
                            <span class="val" style="display:inline-flex;align-items:baseline;gap:8px;">
                                <span>0 FCFA</span>
                                <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;background:rgba(34,197,94,0.12);color:#16a34a;letter-spacing:.4px;">OFFERT</span>
                            </span>
                        @endif
                    </div>
                </div>

                @if($canRemove)
                    <div class="panel-remove">
                        <form method="POST"
                              action="{{ route('proposition.retirer-panneau', [$reference, $slug, $panel['id']]) }}"
                              onsubmit="return confirm('Retirer ce panneau de la proposition ?')">
                            @csrf @method('DELETE')
                            <button type="submit">Retirer cet emplacement</button>
                        </form>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- ────────── BARRE BULK ─────────── --}}
    @if($isActif)
    <div class="bulk-bar" id="bulk-bar">
        <div class="bulk-bar-count"><strong id="bulk-count">0</strong> panneau<span id="bulk-plural"></span> sélectionné<span id="bulk-plural2"></span></div>
        <form method="POST"
              action="{{ route('proposition.bulk-retirer-panneaux', [$reference, $slug]) }}"
              id="bulk-form"
              style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;"
              onsubmit="return bulkConfirm(event)">
            @csrf
            <div id="bulk-hidden-inputs"></div>
            <button type="button" class="secondary" onclick="bulkClear()">Tout désélectionner</button>
            <button type="submit">🗑 Retirer la sélection</button>
        </form>
    </div>
    <script>
    (function() {
        const checkboxes = document.querySelectorAll('.panel-checkbox');
        const bar        = document.getElementById('bulk-bar');
        const countEl    = document.getElementById('bulk-count');
        const plural     = document.getElementById('bulk-plural');
        const plural2    = document.getElementById('bulk-plural2');
        const hidden     = document.getElementById('bulk-hidden-inputs');

        function sync() {
            const selected = Array.from(checkboxes).filter(cb => cb.checked);
            countEl.textContent = selected.length;
            plural.textContent  = selected.length > 1 ? 'x' : '';
            plural2.textContent = selected.length > 1 ? 's' : '';
            bar.classList.toggle('open', selected.length > 0);
            // Mise à jour des inputs hidden pour le POST
            hidden.innerHTML = selected.map(cb =>
                `<input type="hidden" name="panel_ids[]" value="${cb.value}">`
            ).join('');
            // Highlight des cartes sélectionnées
            checkboxes.forEach(cb => {
                cb.closest('.panel')?.classList.toggle('selected', cb.checked);
            });
        }
        checkboxes.forEach(cb => cb.addEventListener('change', sync));
        window.bulkClear = function() {
            checkboxes.forEach(cb => cb.checked = false);
            sync();
        };
        window.bulkConfirm = function(e) {
            const n = Array.from(checkboxes).filter(cb => cb.checked).length;
            if (n === 0) { e.preventDefault(); return false; }
            return confirm(`Retirer ${n} panneau${n > 1 ? 'x' : ''} de la proposition ?`);
        };
    })();
    </script>
    @endif

    {{-- ────────── TOTAL ────────── --}}
    @php
        $totalAmount = $panels->sum('total');
        $panelCount  = $panels->count();
    @endphp

    {{-- Le total est toujours affiché : 0 FCFA reste un montant valide
         (campagne offerte). Pas de "@if > 0" qui ferait disparaître le bloc
         et laisserait le client sans récap visuel. --}}
    <div class="total">
        <div>
            <div class="lbl">Montant total estimé HT</div>
            <div class="amount">
                {{ number_format($totalAmount, 0, ',', ' ') }} FCFA
                @if($totalAmount === 0.0 || $totalAmount === 0)
                    <span style="font-size:12px;font-weight:700;padding:3px 10px;border-radius:12px;background:rgba(34,197,94,0.12);color:#16a34a;letter-spacing:.4px;margin-left:8px;vertical-align:middle;">OFFERT</span>
                @endif
            </div>
            <div class="sub">Hors taxes — devis définitif sur confirmation</div>
        </div>
        <div class="total-right">
            <div class="stat"><strong>{{ $panelCount }}</strong> emplacement{{ $panelCount > 1 ? 's' : '' }}</div>
            <div class="stat" style="margin-top:4px">
                <strong>{{ $totalDays }} jour{{ $totalDays > 1 ? 's' : '' }}</strong>
                ({{ $monthsLabel }} mois facturé{{ $months > 1 ? 's' : '' }})
            </div>
        </div>
    </div>

    {{-- ────────── CTA ou message d'état ────────── --}}
    @php
        $statusValue = $reservation->status->value;
        $stateInfo = match (true) {
            $isExpired                          => [
                'icon'  => '⏱',
                'title' => 'Cette proposition a expiré',
                'text'  => 'Le délai de réponse est dépassé. Contactez votre commercial pour recevoir une nouvelle proposition.',
                'tone'  => 'expired',
            ],
            $statusValue === 'confirme'         => [
                'icon'  => '✅',
                'title' => 'Proposition déjà confirmée',
                'text'  => 'Cette proposition a été acceptée. Elle a donné lieu à une campagne — connectez-vous à votre espace client pour la suivre.',
                'tone'  => 'success',
            ],
            $statusValue === 'refuse'           => [
                'icon'  => '❌',
                'title' => 'Proposition refusée',
                'text'  => 'Cette proposition a été refusée. Si c\'était une erreur, contactez votre commercial.',
                'tone'  => 'danger',
            ],
            $statusValue === 'annule'           => [
                'icon'  => '🚫',
                'title' => 'Proposition annulée',
                'text'  => "Cette proposition n'est plus active — certains emplacements ont été attribués entre temps via une autre proposition. Contactez votre commercial pour en recevoir une nouvelle adaptée.",
                'tone'  => 'danger',
            ],
            !$isActif                           => [
                'icon'  => 'ℹ️',
                'title' => 'Proposition non disponible',
                'text'  => 'Cette proposition n\'est plus accessible. Contactez votre commercial pour plus d\'informations.',
                'tone'  => 'neutral',
            ],
            default                             => null,
        };
    @endphp

    @if($stateInfo)
        <div class="cta">
            <div style="display:flex;flex-direction:column;align-items:center;gap:12px;text-align:center;padding:24px 16px;">
                <div style="font-size:48px;line-height:1">{{ $stateInfo['icon'] }}</div>
                <h3 style="margin:0">{{ $stateInfo['title'] }}</h3>
                <p style="margin:0;max-width:520px;color:#475569;line-height:1.6">{{ $stateInfo['text'] }}</p>
                @if(session('error'))
                    <div class="modal-warning" style="margin-top:4px;max-width:520px;">
                        {{ session('error') }}
                    </div>
                @endif
            </div>
        </div>
    @else
        <div class="cta">
            @if($reservation->hasPendingDateChange())
                {{-- ════════════════════════════════════════════════
                     DEMANDE DE DÉCALAGE EN COURS
                     Le client a proposé de nouvelles dates. Tant que
                     CIBLE n'a pas validé, on BLOQUE Confirmer/Refuser
                     pour éviter le piège : confirmer aux dates CIBLE
                     alors que le client a demandé d'autres dates →
                     campagne lancée sur les MAUVAISES dates.
                     Le bouton "Modifier ma demande" reste possible
                     pour ajuster les dates demandées, et "Annuler ma
                     demande" pour revenir au choix initial.
                ═══════════════════════════════════════════════════ --}}
                @php
                    $estimatedNew = $reservation->estimateAmountForDates(
                        $reservation->requested_start_date,
                        $reservation->requested_end_date
                    );
                    $currentAmt = (float) ($reservation->total_amount ?? 0);
                    $diff = $estimatedNew - $currentAmt;
                    $hasDiff = abs($diff) > 0.01;
                @endphp
                <h3 style="display:flex;align-items:center;gap:8px">
                    <span>🕒</span> En attente de notre réponse
                </h3>
                <p style="margin-bottom:14px">
                    Tu as demandé un décalage de la période —
                    <strong>{{ $reservation->requested_start_date->format('d/m/Y') }} → {{ $reservation->requested_end_date->format('d/m/Y') }}</strong>.
                    Notre équipe va te répondre rapidement.
                </p>
                <div style="background:linear-gradient(180deg,#fff7ed,#fffbeb);border:1px solid #fed7aa;border-radius:10px;padding:14px 16px;font-size:13px;color:#9a3412;line-height:1.6;margin-bottom:14px">
                    @if($hasDiff)
                        💰 Nouveau montant estimé :
                        <strong>{{ number_format($estimatedNew, 0, ',', ' ') }} FCFA HT</strong>
                        <span style="color:{{ $diff > 0 ? '#92400e' : '#16a34a' }};font-weight:700">
                            ({{ $diff > 0 ? '+' : '' }}{{ number_format($diff, 0, ',', ' ') }} FCFA vs proposition initiale)
                        </span>
                        <div style="font-size:11.5px;opacity:.85;margin-top:4px">
                            Le montant définitif sera confirmé dans la réponse de notre équipe.
                        </div>
                        <hr style="border:none;border-top:1px solid #fed7aa;margin:10px 0">
                    @endif
                    <strong>📌 Important</strong> — tu ne peux pas confirmer ou refuser tant que cette demande
                    est en cours. Choisis l'une des actions ci-dessous.
                </div>

                <div class="cta-buttons">
                    <button type="button" class="btn btn-secondary" onclick="openDateChangeModal()" style="background:#fff7ed;color:#9a3412;border:1px solid #fed7aa">
                        🗓 Modifier ma demande
                    </button>
                    <form method="POST" action="{{ route('proposition.annuler-demande-changement-dates', [$reference, $slug]) }}" style="display:inline" onsubmit="return confirm('Annuler ta demande de décalage et revenir aux dates initiales ?');">
                        @csrf
                        <button type="submit" class="btn btn-ghost" style="background:#f3f4f6;color:#4b5563;border:1px solid #e5e7eb">
                            ↩ Annuler ma demande
                        </button>
                    </form>
                </div>
                <div class="cta-note">
                    Une fois ta demande annulée, tu pourras à nouveau confirmer ou refuser la proposition initiale.
                </div>
            @else
                <h3>Votre décision</h3>
                <p>
                    Confirmez pour attribuer les emplacements et créer votre campagne, ou refusez si la
                    proposition ne convient pas. Notre équipe reste à votre disposition.
                </p>

                <div class="cta-buttons">
                    <button type="button" class="btn btn-primary" id="btn-confirm" onclick="openConfirmModal()">
                        Confirmer la proposition
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="openDateChangeModal()" style="background:#fff7ed;color:#9a3412;border:1px solid #fed7aa">
                        🗓 Proposer d'autres dates
                    </button>
                    <button type="button" class="btn btn-danger" onclick="openRefusModal()">
                        Refuser
                    </button>
                </div>

                <div class="cta-note">
                    Votre réponse est sécurisée et prise en compte immédiatement.
                </div>
            @endif
        </div>
    @endif

</div>

<footer class="footer">
    CIBLE CI — Régie Publicitaire — Abidjan, Côte d'Ivoire<br>
    © {{ date('Y') }} · Référence : {{ $reservation->reference }}
</footer>

{{-- ────────── MODAL CONFIRMATION ────────── --}}
<div class="modal-overlay" id="modal-confirm" role="dialog" aria-modal="true">
    <div class="modal">
        <h3>Confirmer la proposition</h3>
        <p>
            En confirmant, les emplacements vous seront attribués et une campagne sera automatiquement
            créée. Cette action est définitive.
        </p>
        <div class="modal-warning">
            Vous recevrez ensuite un email de confirmation avec le récapitulatif détaillé.
        </div>
        <div class="modal-btns">
            <button type="button" class="btn btn-secondary" onclick="closeConfirmModal()">Annuler</button>
            <button type="button" class="btn btn-primary" id="modal-confirm-btn" onclick="submitConfirm()">
                Je confirme
            </button>
        </div>
    </div>
</div>

{{-- ────────── MODAL DEMANDE DÉCALAGE DATES ────────── --}}
<div class="modal-overlay" id="modal-date-change" role="dialog" aria-modal="true">
    <div class="modal">
        <h3>🗓 Proposer d'autres dates</h3>
        <p>
            Indique-nous la période qui te convient mieux — notre équipe va vérifier la disponibilité
            des panneaux à cette nouvelle période et te répondre rapidement.
        </p>
        <form method="POST" action="{{ route('proposition.demander-changement-dates', [$reference, $slug]) }}" id="form-date-change">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:8px">
                <label style="display:block">
                    <span style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;color:#374151">Nouvelle date de début *</span>
                    <input type="date" name="requested_start_date" id="dc-start" required
                           min="{{ now()->toDateString() }}"
                           value="{{ optional($reservation->start_date)->toDateString() }}"
                           style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13.5px">
                </label>
                <label style="display:block">
                    <span style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;color:#374151">Nouvelle date de fin *</span>
                    <input type="date" name="requested_end_date" id="dc-end" required
                           value="{{ optional($reservation->end_date)->toDateString() }}"
                           style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13.5px">
                </label>
            </div>
            <label style="display:block;margin-top:12px">
                <span style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;color:#374151">Précisions (optionnel)</span>
                <textarea name="note" rows="3" maxlength="1000"
                          placeholder="Ex : « Je préfère démarrer après mon salon le 15/07 », ou « Décaler de 2 semaines »…"
                          style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13.5px;resize:vertical;font-family:inherit"></textarea>
            </label>

            {{-- Estimation live du nouveau montant. Calculée côté JS à partir
                 de la durée saisie × le tarif total mensuel actuel. Donne
                 au client une transparence sur l'impact financier avant
                 d'envoyer sa demande. --}}
            <div id="dc-amount-preview"
                 style="display:none;margin-top:12px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:10px 12px;font-size:12.5px;color:#9a3412;line-height:1.5">
                <div>
                    💰 Estimation du nouveau montant :
                    <strong id="dc-amount-new">—</strong>
                    <span id="dc-amount-diff" style="font-weight:700"></span>
                </div>
                <div style="font-size:11px;color:#b45309;margin-top:3px">
                    Montant actuel : <span id="dc-amount-old">{{ number_format((float) $reservation->total_amount, 0, ',', ' ') }} FCFA</span>
                    · le montant définitif reste négociable et confirmé par notre équipe.
                </div>
            </div>

            <div id="dc-error" style="display:none;color:#b91c1c;font-size:12px;margin-top:8px"></div>
            <div class="modal-warning" style="margin-top:12px">
                Pendant que ta demande est en attente, tu peux toujours <strong>confirmer la proposition initiale</strong> ou la refuser.
            </div>
            <div class="modal-btns">
                <button type="button" class="btn btn-secondary" onclick="closeDateChangeModal()">Annuler</button>
                <button type="submit" class="btn btn-primary" style="background:#f97316">
                    🗓 Envoyer ma demande
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ────────── MODAL REFUS ────────── --}}
<div class="modal-overlay" id="modal-refus" role="dialog" aria-modal="true">
    <div class="modal">
        <h3>Refuser la proposition</h3>
        <p>
            Indiquez le motif principal — cela nous aide à mieux ajuster les prochaines propositions.
        </p>
        <form method="POST" action="{{ route('proposition.refuser', [$reference, $slug]) }}" id="form-refuser">
            @csrf
            <div class="refus-options" role="radiogroup" aria-label="Motif du refus">
                @foreach(\App\Models\Reservation::REFUS_REASONS as $code => $label)
                    <label class="refus-option">
                        <input type="radio" name="reason_code" value="{{ $code }}" required>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <textarea name="motif" rows="2" placeholder="Précisions optionnelles (commentaire libre)…"></textarea>
            <div class="modal-btns">
                <button type="button" class="btn btn-secondary" onclick="closeRefusModal()">Annuler</button>
                <button type="submit" class="btn btn-danger">Confirmer le refus</button>
            </div>
        </form>
    </div>
</div>

<style>
    .refus-options { display:flex; flex-direction:column; gap:6px; margin-bottom:14px; }
    .refus-option {
        display:flex; align-items:center; gap:10px;
        padding:10px 12px;
        border:1px solid var(--border, rgba(0,0,0,.12));
        border-radius:9px;
        cursor:pointer;
        font-size:13px;
        background:var(--surface2, #f7f7f7);
        transition:border-color .15s, background .15s;
    }
    .refus-option input { accent-color:#e20613; cursor:pointer; flex-shrink:0; }
    .refus-option:hover { border-color:rgba(226,6,19,.5); }
    .refus-option:has(input:checked) { border-color:#e20613; background:rgba(226,6,19,.05); }
</style>

{{-- Form caché pour la confirmation --}}
<form method="POST" action="{{ route('proposition.confirmer', [$reference, $slug]) }}" id="form-confirmer" style="display:none;">
    @csrf
</form>

<script>
    function openConfirmModal() { document.getElementById('modal-confirm').classList.add('open'); }
    function closeConfirmModal(){ document.getElementById('modal-confirm').classList.remove('open'); }
    function openRefusModal()   { document.getElementById('modal-refus').classList.add('open'); }
    function closeRefusModal()  { document.getElementById('modal-refus').classList.remove('open'); }
    function openDateChangeModal()  { document.getElementById('modal-date-change').classList.add('open'); }
    function closeDateChangeModal() { document.getElementById('modal-date-change').classList.remove('open'); }

    // Validation côté client : end > start + pas dans le passé + estimation live
    (function () {
        const form = document.getElementById('form-date-change');
        if (!form) return;
        const start = document.getElementById('dc-start');
        const end   = document.getElementById('dc-end');
        const err   = document.getElementById('dc-error');
        const previewBox = document.getElementById('dc-amount-preview');
        const newSpan    = document.getElementById('dc-amount-new');
        const diffSpan   = document.getElementById('dc-amount-diff');

        // Tarif mensuel total des panneaux = total_amount / billableMonths actuel.
        // Permet de recalculer le total estimé pour n'importe quelle durée.
        const CURRENT_AMOUNT  = {{ (float) ($reservation->total_amount ?? 0) }};
        const CURRENT_MONTHS  = {{ (float) ($reservation->billableMonths() ?? 0.5) }};
        const MONTHLY_TOTAL   = CURRENT_MONTHS > 0 ? CURRENT_AMOUNT / CURRENT_MONTHS : 0;

        function billableMonthsJS(startStr, endStr) {
            if (!startStr || !endStr) return 0;
            const s = new Date(startStr + 'T00:00:00');
            const e = new Date(endStr + 'T00:00:00');
            if (e <= s) return 0;
            // Même convention que Reservation::durationInDays (PHP) :
            // 05/06 → 05/07 = 30 jours = 1 mois pile (pas 1 mois + 1 jour).
            const days = Math.round((e - s) / 86400000);
            const full = Math.floor(days / 30);
            const rem  = days % 30;
            let frac = 0;
            if (rem >= 1 && rem <= 15) frac = 0.5;
            else if (rem > 15)         frac = 1;
            return Math.max(full + frac, 0.5);
        }
        function fmt(n) { return Math.round(n).toLocaleString('fr-FR') + ' FCFA'; }

        function updatePreview() {
            const months = billableMonthsJS(start.value, end.value);
            if (months <= 0 || MONTHLY_TOTAL <= 0) {
                previewBox.style.display = 'none';
                return;
            }
            const estimated = MONTHLY_TOTAL * months;
            const diff = estimated - CURRENT_AMOUNT;
            newSpan.textContent = fmt(estimated);
            if (Math.abs(diff) < 1) {
                diffSpan.textContent = '';
            } else {
                const sign = diff > 0 ? '+' : '';
                diffSpan.textContent = ` (${sign}${fmt(diff)})`;
                diffSpan.style.color = diff > 0 ? '#92400e' : '#16a34a';
            }
            previewBox.style.display = 'block';
        }

        function show(msg) { err.textContent = msg; err.style.display = 'block'; }
        function clear() { err.style.display = 'none'; }
        // Auto-ajuste end si start passe au-delà
        start?.addEventListener('change', () => {
            if (end.value && end.value <= start.value) end.value = '';
            end.min = start.value;
            clear();
            updatePreview();
        });
        end?.addEventListener('change', () => { clear(); updatePreview(); });
        // Initial : si les inputs sont déjà pré-remplis aux dates actuelles
        updatePreview();

        form.addEventListener('submit', (e) => {
            clear();
            const sv = start.value, ev = end.value;
            if (!sv || !ev) { e.preventDefault(); show('Renseigne les deux dates.'); return; }
            if (ev <= sv) { e.preventDefault(); show('La date de fin doit être après la date de début.'); return; }
            const today = new Date(); today.setHours(0,0,0,0);
            if (new Date(sv) < today) { e.preventDefault(); show('La date de début ne peut pas être dans le passé.'); return; }
        });
    })();

    function submitConfirm() {
        const btn = document.getElementById('modal-confirm-btn');
        const cta = document.getElementById('btn-confirm');
        btn.disabled = true;
        btn.textContent = 'Confirmation en cours...';
        if (cta) cta.disabled = true;
        document.getElementById('form-confirmer').submit();
    }

    // Click hors modal = fermer
    document.getElementById('modal-confirm').addEventListener('click', e => { if (e.target === e.currentTarget) closeConfirmModal(); });
    document.getElementById('modal-refus').addEventListener('click', e => { if (e.target === e.currentTarget) closeRefusModal(); });

    // Échap = fermer toutes les modales
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { closeConfirmModal(); closeRefusModal(); }
    });

    // Empêche double-soumission du formulaire de refus
    document.getElementById('form-refuser')?.addEventListener('submit', function () {
        const btn = this.querySelector('button[type="submit"]');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Refus en cours...';
        }
    });
</script>

</body>
</html>
