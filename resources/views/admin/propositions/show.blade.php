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
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
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
        font-family: 'Inter', sans-serif;
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
    }
    .panel:hover { border-color: var(--border-strong); box-shadow: 0 1px 3px rgba(0,0,0,.04); }

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
            <div>
                <div class="brand-logo">CIBLE <span class="accent">CI</span></div>
                <div class="brand-sub">Régie Publicitaire</div>
            </div>
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
                <div class="val">{{ round($months) }} mois</div>
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

    <div class="panels-grid">
        @foreach($panels as $panel)
            <div class="panel">
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
                            <span class="val" style="font-size:12px;color:var(--text3);font-weight:500">Sur devis</span>
                        @endif
                    </div>
                </div>

                @if($isActif)
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

    {{-- ────────── TOTAL ────────── --}}
    @php
        $totalAmount = $panels->sum('total');
        $panelCount  = $panels->count();
    @endphp

    @if($totalAmount > 0)
        <div class="total">
            <div>
                <div class="lbl">Montant total estimé HT</div>
                <div class="amount">{{ number_format($totalAmount, 0, ',', ' ') }} FCFA</div>
                <div class="sub">Hors taxes — devis définitif sur confirmation</div>
            </div>
            <div class="total-right">
                <div class="stat"><strong>{{ $panelCount }}</strong> emplacement{{ $panelCount > 1 ? 's' : '' }}</div>
                <div class="stat" style="margin-top:4px"><strong>{{ round($months) }} mois</strong> de campagne</div>
            </div>
        </div>
    @endif

    {{-- ────────── CTA ────────── --}}
    @if($expiresIn === null || $expiresIn > 0)
        <div class="cta">
            <h3>Votre décision</h3>
            <p>
                Confirmez pour attribuer les emplacements et créer votre campagne, ou refusez si la
                proposition ne convient pas. Notre équipe reste à votre disposition.
            </p>

            <div class="cta-buttons">
                <button type="button" class="btn btn-primary" id="btn-confirm" onclick="openConfirmModal()">
                    Confirmer la proposition
                </button>
                <button type="button" class="btn btn-danger" onclick="openRefusModal()">
                    Refuser
                </button>
            </div>

            <div class="cta-note">
                Votre réponse est sécurisée et prise en compte immédiatement.
            </div>
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

{{-- ────────── MODAL REFUS ────────── --}}
<div class="modal-overlay" id="modal-refus" role="dialog" aria-modal="true">
    <div class="modal">
        <h3>Refuser la proposition</h3>
        <p>
            Indiquez optionnellement un motif. Cela aide notre équipe à mieux adapter les futures
            propositions à vos besoins.
        </p>
        <form method="POST" action="{{ route('proposition.refuser', [$reference, $slug]) }}" id="form-refuser">
            @csrf
            <textarea name="motif" placeholder="Motif (optionnel) — budget, zones, période, autre..."></textarea>
            <div class="modal-btns">
                <button type="button" class="btn btn-secondary" onclick="closeRefusModal()">Annuler</button>
                <button type="submit" class="btn btn-danger">Confirmer le refus</button>
            </div>
        </form>
    </div>
</div>

{{-- Form caché pour la confirmation --}}
<form method="POST" action="{{ route('proposition.confirmer', [$reference, $slug]) }}" id="form-confirmer" style="display:none;">
    @csrf
</form>

<script>
    function openConfirmModal() { document.getElementById('modal-confirm').classList.add('open'); }
    function closeConfirmModal(){ document.getElementById('modal-confirm').classList.remove('open'); }
    function openRefusModal()   { document.getElementById('modal-refus').classList.add('open'); }
    function closeRefusModal()  { document.getElementById('modal-refus').classList.remove('open'); }

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
