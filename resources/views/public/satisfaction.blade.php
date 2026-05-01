<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#c2570d">
    <title>Votre avis — {{ $survey->campaign?->name }} — CIBLE CI</title>
    <link rel="icon" href="{{ asset('images/faviconl.png') }}">
    <style>
        :root {
            --orange: #c2570d;
            --orange-light: #fff7ed;
            --bg: #f4f6f8;
            --card: #ffffff;
            --border: #e5e7eb;
            --text: #1f2937;
            --text2: #6b7280;
            --text3: #9ca3af;
            --green: #22c55e;
            --red: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            line-height: 1.5;
        }
        body { padding: 20px 16px 60px; }
        .wrap { max-width: 540px; margin: 0 auto; }

        /* Brand header */
        .brand {
            text-align: center;
            margin: 4px 0 22px;
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

        /* Cards */
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 14px;
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
        }
        h1 {
            font-size: 22px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
            letter-spacing: -0.3px;
            line-height: 1.3;
        }
        .subtitle {
            font-size: 14px;
            color: var(--text2);
            margin-bottom: 4px;
        }
        .campaign-tag {
            display: inline-block;
            background: var(--orange-light);
            color: var(--orange);
            border: 1px solid #fed7aa;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 14px;
        }

        /* Question block */
        .q-block {
            padding: 16px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .q-block:last-of-type { border-bottom: none; }
        .q-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 4px;
            display: flex;
            justify-content: space-between;
            align-items: baseline;
        }
        .q-hint {
            font-size: 12px;
            color: var(--text3);
            margin-bottom: 10px;
        }
        .q-value {
            font-size: 11px;
            color: var(--orange);
            font-weight: 700;
            min-width: 80px;
            text-align: right;
        }

        /* Stars */
        .stars {
            display: flex;
            gap: 4px;
            justify-content: flex-start;
            margin-top: 8px;
        }
        .star {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 32px;
            color: #d1d5db;
            transition: transform .1s, color .15s;
            padding: 4px;
            line-height: 1;
        }
        .star:hover { transform: scale(1.1); }
        .star.active { color: #fbbf24; }
        .star:focus { outline: none; }
        .star:focus-visible { outline: 2px solid var(--orange); border-radius: 4px; }

        /* Yes/No buttons */
        .yesno {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 8px;
        }
        .yesno button {
            padding: 12px;
            border: 2px solid var(--border);
            background: var(--card);
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text2);
            cursor: pointer;
            transition: all .15s;
        }
        .yesno button:hover { border-color: var(--orange); color: var(--orange); }
        .yesno button.active.yes { border-color: var(--green); background: rgba(34,197,94,0.08); color: var(--green); }
        .yesno button.active.no  { border-color: var(--red); background: rgba(239,68,68,0.08); color: var(--red); }

        /* Textarea */
        textarea {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 14px;
            font-family: inherit;
            font-size: 14px;
            color: var(--text);
            min-height: 90px;
            resize: vertical;
            margin-top: 8px;
        }
        textarea:focus { outline: 2px solid var(--orange); outline-offset: -1px; }
        .char-count {
            font-size: 11px;
            color: var(--text3);
            text-align: right;
            margin-top: 4px;
        }

        /* Submit */
        .submit-wrap { padding: 4px 0; }
        button.cta {
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
            margin-top: 6px;
            transition: background .15s, transform .05s;
        }
        button.cta:hover:not(:disabled) { background: #a04609; }
        button.cta:active:not(:disabled) { transform: scale(.98); }
        button.cta:disabled {
            background: #d1d5db;
            color: #6b7280;
            cursor: not-allowed;
        }
        .footer {
            text-align: center;
            font-size: 11px;
            color: var(--text3);
            margin-top: 16px;
        }
        .err {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 12px;
        }

        @media (max-width: 480px) {
            body { padding: 14px 10px 40px; }
            .card { padding: 18px; }
            .star { font-size: 28px; }
        }
    </style>
</head>
<body>

<div class="wrap">

    <div class="brand">
        <div class="brand-name">CIBLE CI</div>
        <div class="brand-sub">Enquête de satisfaction</div>
    </div>

    <div class="card">
        <span class="campaign-tag">Votre avis compte</span>
        <h1>Bonjour {{ $survey->client?->name ?? 'Client' }} 👋</h1>
        <p class="subtitle">
            Votre campagne <strong>{{ $survey->campaign?->name }}</strong> vient de se terminer.
            Prenez 1 minute pour partager votre expérience.
        </p>
    </div>

    @if(isset($errors) && $errors->any())
        <div class="err">
            ⚠️ Veuillez corriger les erreurs ci-dessous :
            <ul style="margin:6px 0 0 18px;">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('satisfaction.submit', $survey->token) }}" id="surveyForm">
        @csrf

        <div class="card">
            @php
                $questions = [
                    ['key' => 'score_global',                'label' => 'Note globale',                  'hint' => 'Votre satisfaction générale'],
                    ['key' => 'score_qualite',               'label' => 'Qualité de la prestation',      'hint' => 'Pose, état des panneaux, finition'],
                    ['key' => 'score_delais',                'label' => 'Respect des délais',            'hint' => 'Démarrage, durée, retraits'],
                    ['key' => 'score_communication',         'label' => 'Communication',                 'hint' => 'Réactivité, écoute, transparence'],
                    ['key' => 'score_rapport_qualite_prix',  'label' => 'Rapport qualité / prix',        'hint' => 'Justesse du tarif vs ce que vous avez reçu'],
                ];
            @endphp

            @foreach($questions as $q)
                <div class="q-block">
                    <div class="q-label">
                        <span>{{ $q['label'] }}</span>
                        <span class="q-value" data-value-for="{{ $q['key'] }}">—</span>
                    </div>
                    <div class="q-hint">{{ $q['hint'] }}</div>
                    <input type="hidden" name="{{ $q['key'] }}" value="{{ old($q['key'], 0) }}" data-score-input="{{ $q['key'] }}">
                    <div class="stars" data-stars-for="{{ $q['key'] }}">
                        @for($i = 1; $i <= 5; $i++)
                            <button type="button" class="star" data-value="{{ $i }}" aria-label="{{ $i }} étoile{{ $i > 1 ? 's' : '' }}">★</button>
                        @endfor
                    </div>
                </div>
            @endforeach

            {{-- Renouvellement --}}
            <div class="q-block">
                <div class="q-label">
                    <span>Renouvelleriez-vous une campagne avec nous ?</span>
                </div>
                <div class="q-hint">Recommanderiez-vous CIBLE CI ?</div>
                <input type="hidden" name="would_renew" id="wouldRenewInput" value="{{ old('would_renew', '') }}">
                <div class="yesno" id="wouldRenewBlock">
                    <button type="button" class="yes" data-val="1">👍 Oui</button>
                    <button type="button" class="no"  data-val="0">👎 Non</button>
                </div>
            </div>

            {{-- Commentaire --}}
            <div class="q-block">
                <div class="q-label">
                    <span>Commentaire (optionnel)</span>
                </div>
                <div class="q-hint">Un point fort, un point d'amélioration… 100% anonyme côté client.</div>
                <textarea name="commentaire" id="commentInput" maxlength="2000" placeholder="Votre retour libre…">{{ old('commentaire') }}</textarea>
                <div class="char-count"><span id="charCount">0</span> / 2000</div>
            </div>
        </div>

        <div class="submit-wrap">
            <button type="submit" class="cta" id="submitBtn" disabled>Envoyer mon avis</button>
        </div>

        <div class="footer">
            🔒 Votre lien est personnel et sécurisé. Vos réponses sont confidentielles.<br>
            © {{ date('Y') }} CIBLE CI · Régie Publicitaire — Abidjan
        </div>
    </form>
</div>

<script>
(function () {
    const form         = document.getElementById('surveyForm');
    const submitBtn    = document.getElementById('submitBtn');
    const wouldInput   = document.getElementById('wouldRenewInput');
    const wouldBlock   = document.getElementById('wouldRenewBlock');
    const commentInput = document.getElementById('commentInput');
    const charCount    = document.getElementById('charCount');

    const SCORE_LABELS = {
        1: '😞 Très insatisfait',
        2: '😕 Insatisfait',
        3: '😐 Moyen',
        4: '🙂 Satisfait',
        5: '😄 Très satisfait',
    };

    // ─── Étoiles ───
    document.querySelectorAll('.stars').forEach(starsEl => {
        const key  = starsEl.dataset.starsFor;
        const hidden = document.querySelector(`[data-score-input="${key}"]`);
        const valueEl = document.querySelector(`[data-value-for="${key}"]`);
        const stars = starsEl.querySelectorAll('.star');

        function paint(value) {
            stars.forEach((s, i) => s.classList.toggle('active', i < value));
            if (valueEl) valueEl.textContent = value > 0 ? `${value}/5 ${SCORE_LABELS[value]}` : '—';
        }

        // Init depuis old value
        const initial = parseInt(hidden.value || '0', 10);
        if (initial > 0) paint(initial);

        stars.forEach((s, i) => {
            s.addEventListener('click', () => {
                hidden.value = String(i + 1);
                paint(i + 1);
                checkComplete();
            });
            s.addEventListener('mouseenter', () => {
                stars.forEach((ss, j) => ss.classList.toggle('active', j <= i));
            });
        });
        starsEl.addEventListener('mouseleave', () => paint(parseInt(hidden.value || '0', 10)));
    });

    // ─── Yes/No ───
    wouldBlock.querySelectorAll('button').forEach(btn => {
        btn.addEventListener('click', () => {
            const v = btn.dataset.val;
            wouldInput.value = v;
            wouldBlock.querySelectorAll('button').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            checkComplete();
        });
    });
    // Restaurer old('would_renew')
    if (wouldInput.value === '1' || wouldInput.value === '0') {
        wouldBlock.querySelector(`button[data-val="${wouldInput.value}"]`)?.classList.add('active');
    }

    // ─── Char counter ───
    function updateCharCount() {
        charCount.textContent = commentInput.value.length;
    }
    commentInput.addEventListener('input', updateCharCount);
    updateCharCount();

    // ─── Validation ───
    function checkComplete() {
        const requiredScores = ['score_global', 'score_qualite', 'score_delais', 'score_communication', 'score_rapport_qualite_prix'];
        const allScored = requiredScores.every(k => parseInt(document.querySelector(`[data-score-input="${k}"]`).value, 10) >= 1);
        const renewSet  = wouldInput.value === '1' || wouldInput.value === '0';
        submitBtn.disabled = !(allScored && renewSet);
    }
    checkComplete();
})();
</script>

</body>
</html>
