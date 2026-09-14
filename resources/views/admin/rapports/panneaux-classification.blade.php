<x-admin-layout>
    @php
        $meta = [
            'a_lannee' => [
                'label'   => "Panneaux occupés à l'année",
                'sub'     => 'Occupation ≥ 365 jours cumulés',
                'color'   => '#22c55e',
                'icon'    => '🟢',
                'count'   => $classification['a_lannee']->count(),
                'empty'   => "Aucun panneau n'a atteint 365 jours d'occupation sur cette période.",
            ],
            'intermediaire' => [
                'label'   => 'Panneaux occupation intermédiaire',
                'sub'     => 'Occupation entre 1 et 364 jours',
                'color'   => '#f97316',
                'icon'    => '🟡',
                'count'   => $classification['intermediaire']->count(),
                'empty'   => 'Aucun panneau dans cette tranche sur la période.',
            ],
            'jamais' => [
                'label'   => 'Panneaux jamais occupés',
                'sub'     => "0 jour d'occupation sur la période",
                'color'   => '#ef4444',
                'icon'    => '🔴',
                'count'   => $classification['jamais']->count(),
                'empty'   => 'Excellent : aucun panneau non occupé sur cette période.',
            ],
        ];
    @endphp

    <div style="max-width:1400px;margin:0 auto;padding:24px 16px">
        {{-- ── En-tête + navigation retour ─────────────────────── --}}
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
            <div>
                <a href="{{ route('admin.rapports.index') }}" style="font-size:12px;color:var(--text3);text-decoration:none">
                    ← Retour aux rapports
                </a>
                <h1 style="margin:6px 0 4px;font-size:22px;font-weight:800">
                    🎯 Classification des panneaux par occupation
                </h1>
                <div style="font-size:12px;color:var(--text2)">
                    Période analysée :
                    <strong>{{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}</strong>
                    ({{ $periodDays }} jours)
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <form method="GET" action="{{ route('admin.rapports.panneaux-classification') }}"
                      style="display:flex;gap:6px;align-items:center;font-size:12px">
                    <label style="color:var(--text3)">Du</label>
                    <input type="date" name="from" value="{{ $from->format('Y-m-d') }}"
                           style="height:34px;padding:0 10px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;color:var(--text)">
                    <label style="color:var(--text3)">au</label>
                    <input type="date" name="to" value="{{ $to->format('Y-m-d') }}"
                           style="height:34px;padding:0 10px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;color:var(--text)">
                    <button type="submit" class="btn btn-sm btn-primary">Appliquer</button>
                </form>
                <a href="{{ route('admin.rapports.panneaux-classification.pdf', request()->query()) }}"
                   class="btn btn-sm" style="background:#dc2626;color:#fff">
                    📄 Export PDF
                </a>
            </div>
        </div>

        {{-- ── Bandeau explicatif ─────────────────────────────── --}}
        <div style="background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.2);border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:var(--text2);line-height:1.5">
            💡 <strong>Chevalets et façades murales exclus</strong> de cette analyse
            ({{ $classification['excluded_count'] }} panneaux). Un panneau
            <em>à l'année</em> doit être occupé <strong>≥ 365 jours cumulés</strong>
            sur la période — au minimum <strong>12 mois glissants</strong> requis
            pour qu'il y accède.
        </div>

        {{-- ── 3 cartes KPI cliquables ────────────────────────── --}}
        <style>
            .panclass-card { cursor: pointer; transition: transform .12s ease, box-shadow .12s ease; }
            .panclass-card:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,.08); }
            .panclass-card:focus-visible { outline: 3px solid var(--accent); outline-offset: 2px; }
            .panclass-card.is-active {
                box-shadow: 0 0 0 3px var(--panclass-color), 0 6px 18px rgba(0,0,0,.10);
                transform: translateY(-1px);
            }
            .panclass-panel[hidden] { display: none !important; }
        </style>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin-bottom:24px">
            @foreach($meta as $key => $m)
                <div class="panclass-card {{ $key === 'a_lannee' ? 'is-active' : '' }}"
                     data-panel-key="{{ $key }}" role="button" tabindex="0"
                     style="--panclass-color:{{ $m['color'] }};background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px 18px;position:relative;overflow:hidden">
                    <div style="position:absolute;top:0;left:0;right:0;height:4px;background:{{ $m['color'] }}"></div>
                    <div style="display:flex;align-items:center;gap:10px;margin-top:4px">
                        <span style="font-size:22px">{{ $m['icon'] }}</span>
                        <div style="font-size:36px;font-weight:800;color:{{ $m['color'] }};line-height:1">
                            {{ $m['count'] }}
                        </div>
                    </div>
                    <div style="font-size:13px;font-weight:700;color:var(--text);margin-top:8px">
                        {{ $m['label'] }}
                    </div>
                    <div style="font-size:11px;color:var(--text3);margin-top:2px">{{ $m['sub'] }}</div>
                </div>
            @endforeach

            {{-- 4e carte : Total (agrégat, cliquable = "afficher la première") --}}
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px 18px;position:relative;overflow:hidden">
                <div style="position:absolute;top:0;left:0;right:0;height:4px;background:var(--accent)"></div>
                <div style="display:flex;align-items:center;gap:10px;margin-top:4px">
                    <span style="font-size:22px">📊</span>
                    <div style="font-size:36px;font-weight:800;color:var(--accent);line-height:1">
                        {{ $classification['total'] }}
                    </div>
                </div>
                <div style="font-size:13px;font-weight:700;color:var(--text);margin-top:8px">
                    Total panneaux analysés
                </div>
                <div style="font-size:11px;color:var(--text3);margin-top:2px">
                    Hors chevalets ({{ $classification['excluded_count'] }} exclus)
                </div>
            </div>
        </div>

        {{-- ── 3 tables filtrées (JS toggle) ──────────────────── --}}
        @foreach($meta as $key => $m)
            <div class="panclass-panel card" data-panel="{{ $key }}"
                 @if($key !== 'a_lannee') hidden @endif
                 style="margin-bottom:18px">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:14px">
                    <h2 style="margin:0;font-size:16px;font-weight:700;display:flex;align-items:center;gap:8px">
                        <span>{{ $m['icon'] }}</span>
                        <span>{{ $m['label'] }}</span>
                        <span style="background:{{ $m['color'] }}18;color:{{ $m['color'] }};font-size:12px;font-weight:600;padding:2px 8px;border-radius:10px">
                            {{ $m['count'] }}
                        </span>
                    </h2>
                    <span style="font-size:11px;color:var(--text3)">{{ $m['sub'] }}</span>
                </div>

                @php $list = $classification[$key]; @endphp
                @if($list->isEmpty())
                    <div style="padding:24px;text-align:center;color:var(--text3);font-size:13px;background:rgba(107,114,128,.04);border-radius:8px">
                        {{ $m['empty'] }}
                    </div>
                @else
                    <div style="overflow-x:auto">
                        <table style="width:100%;border-collapse:collapse;font-size:13px">
                            <thead>
                                <tr style="border-bottom:2px solid var(--border);color:var(--text2);font-size:11px;text-transform:uppercase;letter-spacing:.4px">
                                    <th style="text-align:right;padding:8px 10px;font-weight:600;width:36px">N°</th>
                                    <th style="text-align:left;padding:8px 10px;font-weight:600">Référence</th>
                                    <th style="text-align:left;padding:8px 10px;font-weight:600">Emplacement</th>
                                    <th style="text-align:left;padding:8px 10px;font-weight:600">Commune</th>
                                    <th style="text-align:left;padding:8px 10px;font-weight:600">Zone</th>
                                    <th style="text-align:center;padding:8px 10px;font-weight:600">Format</th>
                                    <th style="text-align:right;padding:8px 10px;font-weight:600">Jours occupés</th>
                                    <th style="text-align:right;padding:8px 10px;font-weight:600">Taux</th>
                                    <th style="text-align:right;padding:8px 10px;font-weight:600">Campagnes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($list as $index => $p)
                                    @php $isMaint = $p->status === 'maintenance'; @endphp
                                    <tr style="border-bottom:1px solid var(--border);{{ $isMaint ? 'background:rgba(251,191,36,.05)' : '' }}">
                                        <td style="padding:10px;text-align:right;color:var(--text3);font-family:ui-monospace,monospace;font-size:11px">
                                            {{ $index + 1 }}
                                        </td>
                                        <td style="padding:10px;font-weight:700">
                                            <a href="{{ route('admin.panels.show', $p->id) }}"
                                               style="font-family:ui-monospace,monospace;color:var(--accent);text-decoration:none">
                                                {{ $p->reference }}
                                            </a>
                                            @if($isMaint)
                                                <span style="background:#fef3c7;color:#92400e;font-size:10px;font-weight:700;padding:1px 6px;border-radius:4px;margin-left:6px;letter-spacing:.3px" title="Panneau en maintenance">🔧 MAINT.</span>
                                            @endif
                                        </td>
                                        <td style="padding:10px;color:var(--text2);max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                            {{ $p->name ?? '—' }}
                                        </td>
                                        <td style="padding:10px;color:var(--text2)">{{ $p->commune_name ?? '—' }}</td>
                                        <td style="padding:10px">
                                            @if($p->zone === 'Abidjan')
                                                <span style="background:rgba(59,130,246,.15);color:#1d4ed8;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600">Abidjan</span>
                                            @else
                                                <span style="background:rgba(16,185,129,.15);color:#047857;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600">Intérieur</span>
                                            @endif
                                        </td>
                                        <td style="padding:10px;text-align:center;color:var(--text2);font-size:12px">
                                            {{ $p->format_name ?? '—' }}
                                        </td>
                                        <td style="padding:10px;text-align:right;font-weight:700;color:{{ $m['color'] }}">
                                            {{ (int) $p->days_occupied }} j
                                        </td>
                                        <td style="padding:10px;text-align:right;color:var(--text2)">
                                            {{ number_format((float) $p->occupation_rate, 1, ',', ' ') }} %
                                        </td>
                                        <td style="padding:10px;text-align:right;color:var(--text2)">
                                            {{ (int) $p->campaigns_count }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <script>
    (function () {
        'use strict';
        const cards  = document.querySelectorAll('.panclass-card[data-panel-key]');
        const panels = document.querySelectorAll('.panclass-panel[data-panel]');

        function activate(key) {
            cards.forEach(c => c.classList.toggle('is-active', c.dataset.panelKey === key));
            panels.forEach(p => { p.hidden = (p.dataset.panel !== key); });
        }

        cards.forEach(card => {
            card.addEventListener('click', () => activate(card.dataset.panelKey));
            card.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    activate(card.dataset.panelKey);
                }
            });
        });
    })();
    </script>
</x-admin-layout>
