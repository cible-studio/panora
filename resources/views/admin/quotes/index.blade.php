<x-admin-layout title="Devis">
    <x-slot:topbarActions>
        @can('create', \App\Models\Quote::class)
            <a href="{{ route('admin.quotes.create') }}" class="btn btn-primary btn-sm">
                ➕ <span class="btn-label">Nouveau devis</span>
            </a>
        @endcan
    </x-slot:topbarActions>

    @php
        $currentStatus  = request('status');
        $currentClient  = request('client_id');
        $currentQ       = request('q');
        $currentFrom    = request('date_from');
        $currentTo      = request('date_to');
        $hasFilters     = filled($currentStatus) || filled($currentClient) || filled($currentQ) || filled($currentFrom) || filled($currentTo);
    @endphp

    {{-- ═══════════════════ KPI CARDS (cliquables → filtre statut) ═══════════════════ --}}
    <div style="display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;margin-bottom:18px">
        @foreach([
            ['label'=>'Total',         'value'=>$kpi['total'],           'c'=>'#475569', 'icon'=>'📋', 'status'=>null],
            ['label'=>'Brouillon',     'value'=>$kpi['brouillon'],       'c'=>'#94a3b8', 'icon'=>'📝', 'status'=>'brouillon'],
            ['label'=>'Envoyés',       'value'=>$kpi['envoye'],          'c'=>'#1d4ed8', 'icon'=>'📤', 'status'=>'envoye'],
            ['label'=>'Acceptés',      'value'=>$kpi['accepte'],         'c'=>'#15803d', 'icon'=>'✅', 'status'=>'accepte'],
            ['label'=>'Refusés',       'value'=>$kpi['refuse'],          'c'=>'#991b1b', 'icon'=>'❌', 'status'=>'refuse'],
            ['label'=>'Expire dans 7j','value'=>$kpi['expire_bientot'],  'c'=>'#b45309', 'icon'=>'⌛', 'status'=>'__expires_soon__'],
        ] as $k)
            @php
                // Card active = statut correspondant à celui filtré (ou "Total" si aucun filtre statut)
                $isActive = ($k['status'] === null && !filled($currentStatus))
                         || ($k['status'] !== null && $currentStatus === $k['status']);
                // URL clic : Total → sans status. "Expire 7j" → non implémenté comme statut natif,
                // on retire simplement le filtre (badge informationnel). Autres → filtre le statut.
                $href = $k['status'] === null || $k['status'] === '__expires_soon__'
                    ? route('admin.quotes.index')
                    : route('admin.quotes.index', ['status' => $k['status']]);
            @endphp
            <a href="{{ $href }}"
               class="kpi-card"
               style="text-decoration:none;background:var(--surface);border:1px solid var(--border);
                      border-top:3px solid {{ $k['c'] }};border-radius:12px;padding:14px 16px;
                      display:flex;flex-direction:column;gap:6px;height:100%;min-height:96px;
                      box-shadow:{{ $isActive ? '0 0 0 2px ' . $k['c'] . ', 0 4px 12px rgba(0,0,0,.08)' : '0 1px 2px rgba(0,0,0,.03)' }};
                      transform:{{ $isActive ? 'translateY(-1px)' : 'none' }};
                      transition:all .15s ease">
                <div style="font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.5px">
                    {{ $k['label'] }}
                </div>
                <div style="display:flex;align-items:baseline;justify-content:space-between;gap:6px">
                    <span style="font-size:28px;font-weight:800;color:{{ $k['c'] }};line-height:1">{{ $k['value'] }}</span>
                    <span style="font-size:20px;opacity:.9">{{ $k['icon'] }}</span>
                </div>
            </a>
        @endforeach
    </div>

    {{-- ═══════════════════ FILTRES (instantanés, sans bouton "Filtrer") ═══════════════════ --}}
    <form method="GET" id="quotes-filter-form"
          style="display:grid;grid-template-columns:2fr 1.2fr 1.6fr 1fr 1fr auto;gap:10px;
                 margin-bottom:14px;padding:12px;background:var(--surface);
                 border:1px solid var(--border);border-radius:12px;align-items:center">

        <input type="text" name="q" id="filter-q" value="{{ $currentQ }}"
               placeholder="🔍 Réf ou titre…" autocomplete="off"
               class="qf-input">

        <select name="status" id="filter-status" class="qf-select">
            <option value="">— Tous statuts —</option>
            @foreach(\App\Enums\QuoteStatus::cases() as $s)
                <option value="{{ $s->value }}" @selected($currentStatus === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>

        <select name="client_id" id="filter-client" class="qf-select">
            <option value="">— Tous clients —</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" @selected((int) $currentClient === $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>

        <input type="date" name="date_from" value="{{ $currentFrom }}" class="qf-input" title="Créé à partir du">
        <input type="date" name="date_to"   value="{{ $currentTo }}"   class="qf-input" title="Créé jusqu'au">

        <div id="reset-wrap" style="display:{{ $hasFilters ? 'flex' : 'none' }};align-items:center">
            <a href="{{ route('admin.quotes.index') }}" class="qf-btn-reset">↻ Réinitialiser</a>
        </div>
    </form>

    {{-- ═══════════════════ TABLE ═══════════════════ --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;overflow:hidden">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
            <div style="font-weight:700">📋 Liste des devis <span style="color:var(--text3);font-weight:500">({{ $quotes->total() }})</span></div>
        </div>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr>
                        @foreach(['Réf', 'Titre / Client', 'Période', ['Montant', 'right'], 'Statut', 'Commercial', ['Expire', 'center'], ''] as $th)
                            @php
                                $label = is_array($th) ? $th[0] : $th;
                                $align = is_array($th) ? $th[1] : 'left';
                            @endphp
                            <th style="text-align:{{ $align }};padding:10px;background:var(--surface2);
                                       font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($quotes as $quote)
                        @php
                            $st  = $quote->status->uiConfig();
                            $exp = $quote->daysUntilExpiry();
                        @endphp
                        <tr style="border-top:1px solid var(--border)">
                            <td style="padding:10px;font-family:monospace;font-weight:700;color:var(--accent)">{{ $quote->reference }}</td>
                            <td style="padding:10px">
                                <a href="{{ route('admin.quotes.show', $quote) }}" style="color:var(--text);font-weight:600;text-decoration:none">{{ $quote->title }}</a>
                                <div style="font-size:11.5px;color:var(--text3);margin-top:2px">🏢 {{ \Illuminate\Support\Str::limit($quote->client?->name ?? '—', 30) }}</div>
                            </td>
                            <td style="padding:10px;font-size:12px;color:var(--text2)">
                                @if($quote->period_start && $quote->period_end)
                                    {{ $quote->period_start->format('d/m/y') }} → {{ $quote->period_end->format('d/m/y') }}
                                @else
                                    <span style="color:var(--text3)">—</span>
                                @endif
                            </td>
                            <td style="padding:10px;text-align:right;font-weight:700;white-space:nowrap">
                                {{ number_format($quote->total_a_payer, 0, ',', ' ') }}
                                <span style="font-size:10px;color:var(--text3)">FCFA</span>
                            </td>
                            <td style="padding:10px">
                                <span style="background:{{ $st['bg'] }};color:{{ $st['color'] }};border:1px solid {{ $st['border'] }};padding:3px 9px;border-radius:12px;font-size:11px;font-weight:700;white-space:nowrap">
                                    {{ $st['icon'] }} {{ $quote->status->label() }}
                                </span>
                            </td>
                            <td style="padding:10px;font-size:12px;color:var(--text2)">{{ $quote->commercial?->name ?? '—' }}</td>
                            <td style="padding:10px;text-align:center;font-size:12px;white-space:nowrap">
                                @if($quote->expires_at)
                                    @if($exp !== null && $exp < 0)
                                        <span style="color:#991b1b;font-weight:700">⌛ {{ abs($exp) }}j dépassé</span>
                                    @elseif($exp !== null && $exp <= 3)
                                        <span style="color:#b45309;font-weight:700">⚠️ J-{{ $exp }}</span>
                                    @elseif($exp !== null)
                                        <span style="color:var(--text3)">J-{{ $exp }}</span>
                                    @else
                                        <span style="color:var(--text3)">—</span>
                                    @endif
                                @else
                                    <span style="color:var(--text3)">—</span>
                                @endif
                            </td>
                            <td style="padding:10px;text-align:right;white-space:nowrap">
                                <a href="{{ route('admin.quotes.show', $quote) }}" style="text-decoration:none;font-size:16px" title="Voir">👁</a>
                                <a href="{{ route('admin.quotes.pdf', $quote) }}" style="text-decoration:none;font-size:16px;margin-left:8px" title="PDF">📄</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="padding:60px;text-align:center;color:var(--text3)">
                                <div style="font-size:48px;margin-bottom:12px">📋</div>
                                <div>Aucun devis trouvé.</div>
                                @can('create', \App\Models\Quote::class)
                                    <div style="margin-top:12px">
                                        <a href="{{ route('admin.quotes.create') }}" style="color:var(--accent)">+ Créer votre premier devis</a>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="padding:12px 16px">
            {{ $quotes->links() }}
        </div>
    </div>

    @push('scripts')
    {{-- Select2 partagé (mêmes CDN que admin/invoices/index.blade.php) --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

    <style>
        /* Harmonisation hauteur = 40px pour TOUS les champs et boutons filtres.
           Objectif : ligne de filtre parfaitement alignée (input / select / date / bouton reset). */
        .qf-input, .qf-select {
            height: 40px;
            padding: 0 12px;
            background: var(--surface2);
            border: 1px solid var(--border2, var(--border));
            border-radius: 10px;
            color: var(--text);
            font-size: 13px;
            width: 100%;
            font-family: inherit;
        }
        .qf-input:focus, .qf-select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(232, 160, 32, .15);
        }
        .qf-btn-reset {
            height: 40px;
            padding: 0 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            background: var(--surface2);
            border: 1px solid var(--border2, var(--border));
            border-radius: 10px;
            color: var(--text);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            cursor: pointer;
            transition: all .15s ease;
        }
        .qf-btn-reset:hover {
            background: #fef3c7;
            border-color: #f59e0b;
            color: #78350f;
        }

        /* Harmonise Select2 avec les autres champs (même hauteur 40px, même style) */
        .select2-container--default .select2-selection--single {
            height: 40px !important;
            padding: 0 4px !important;
            background: var(--surface2) !important;
            border: 1px solid var(--border2, var(--border)) !important;
            border-radius: 10px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 40px !important;
            color: var(--text) !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            padding-left: 8px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 38px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__clear {
            margin-right: 22px !important;
            color: #94a3b8 !important;
        }
        .select2-dropdown {
            border-radius: 10px !important;
            border-color: var(--border2, var(--border)) !important;
            box-shadow: 0 8px 24px rgba(0,0,0,.08) !important;
        }
        .select2-search--dropdown .select2-search__field {
            border-radius: 8px !important;
            border-color: var(--border2, var(--border)) !important;
            padding: 8px 10px !important;
            font-size: 13px !important;
        }
        .select2-results__option--highlighted {
            background: var(--accent) !important;
            color: #fff !important;
        }

        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0,0,0,.10) !important;
        }
    </style>

    <script>
    (function() {
        const form = document.getElementById('quotes-filter-form');
        if (!form) return;

        const inputQ      = document.getElementById('filter-q');
        const selStatus   = document.getElementById('filter-status');
        const selClient   = document.getElementById('filter-client');
        const resetWrap   = document.getElementById('reset-wrap');
        let debounceTimer = null;

        function hasAnyFilter() {
            const data = new FormData(form);
            for (const [, v] of data.entries()) if (String(v).trim() !== '') return true;
            return false;
        }
        function refreshResetVisibility() {
            resetWrap.style.display = hasAnyFilter() ? 'flex' : 'none';
        }
        function submitNow() { form.submit(); }

        // Recherche texte : debounce 300ms
        inputQ.addEventListener('input', () => {
            refreshResetVisibility();
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(submitNow, 300);
        });

        // Dates : submit direct au changement
        form.querySelectorAll('input[type="date"]').forEach(el => {
            el.addEventListener('change', () => { refreshResetVisibility(); submitNow(); });
        });

        // Select natifs — fallback si Select2 indisponible
        const hasSelect2 = window.jQuery && window.jQuery.fn && window.jQuery.fn.select2;
        if (hasSelect2) {
            window.jQuery(selStatus).select2({
                placeholder: '— Tous statuts —',
                allowClear: true,
                width: '100%',
                minimumResultsForSearch: 6,
                language: { noResults: () => 'Aucun statut', searching: () => 'Recherche…' },
            }).on('change', () => { refreshResetVisibility(); submitNow(); });

            window.jQuery(selClient).select2({
                placeholder: '🔍 Rechercher un client…',
                allowClear: true,
                width: '100%',
                language: { noResults: () => 'Aucun client trouvé', searching: () => 'Recherche…' },
            }).on('change', () => { refreshResetVisibility(); submitNow(); });
        } else {
            selStatus.addEventListener('change', () => { refreshResetVisibility(); submitNow(); });
            selClient.addEventListener('change', () => { refreshResetVisibility(); submitNow(); });
        }
    })();
    </script>
    @endpush
</x-admin-layout>
