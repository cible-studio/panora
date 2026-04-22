<x-admin-layout title="Alertes & Notifications">

    <x-slot:topbarActions>
        @if ($totalNonLues > 0)
            <form method="POST" action="{{ route('admin.alerts.read-all') }}">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:6px">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2.5">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    Tout marquer lu
                </button>
            </form>
        @endif
    </x-slot:topbarActions>

    {{-- ════ KPI ════ --}}
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px">
        @php
            $kpis = [
                [
                    'label' => 'Non lues',
                    'val' => $totalNonLues,
                    'color' => '#e8a020',
                    'filter' => 'non_lues=1',
                    'icon' =>
                        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
                ],
                [
                    'label' => 'Danger',
                    'val' => $totalDanger,
                    'color' => '#ef4444',
                    'filter' => 'niveau=danger',
                    'icon' =>
                        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
                ],
                [
                    'label' => 'Avertissements',
                    'val' => $totalWarning,
                    'color' => '#f97316',
                    'filter' => 'niveau=warning',
                    'icon' =>
                        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
                ],
                [
                    'label' => 'Informations',
                    'val' => $totalInfo,
                    'color' => '#3b82f6',
                    'filter' => 'niveau=info',
                    'icon' =>
                        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
                ],
            ];
        @endphp
        @foreach ($kpis as $k)
            @php
                $isActive =
                    ($k['filter'] === 'non_lues=1' && request()->boolean('non_lues')) ||
                    (str_contains($k['filter'], 'niveau=') && request('niveau') === explode('=', $k['filter'])[1]);
            @endphp
            <a href="{{ route('admin.alerts.index') }}?{{ $k['filter'] }}"
                style="background:var(--surface);border:2px solid {{ $isActive ? $k['color'] : 'var(--border)' }};border-radius:14px;padding:16px 20px;border-left:4px solid {{ $k['color'] }};text-decoration:none;display:block;transition:all .15s;{{ $isActive ? 'background:' . $k['color'] . '15;' : '' }}"
                onmouseover="this.style.borderColor='{{ $k['color'] }}';this.style.transform='translateY(-2px)'"
                onmouseout="this.style.borderColor='{{ $isActive ? $k['color'] : 'var(--border)' }}';this.style.transform=''">
                <div style="color:{{ $k['color'] }};margin-bottom:8px">{!! $k['icon'] !!}</div>
                <div style="font-size:28px;font-weight:800;color:{{ $k['color'] }};line-height:1">
                    {{ number_format($k['val']) }}</div>
                <div
                    style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text3);margin-top:4px;display:flex;align-items:center;justify-content:space-between;">
                    {{ $k['label'] }}
                    @if ($isActive)
                        <span
                            style="font-size:9px;background:{{ $k['color'] }};color:#fff;padding:1px 6px;border-radius:20px;">Actif</span>
                    @endif
                </div>
            </a>
        @endforeach
    </div>

    {{-- ════ FILTRES ════ --}}
    <div
        style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 18px;margin-bottom:16px">
        <form method="GET" action="{{ route('admin.alerts.index') }}"
            style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
            <div>
                <label class="albl">Niveau</label>
                <select name="niveau" class="asel" onchange="this.form.submit()">
                    <option value="">Tous</option>
                    <option value="danger" {{ request('niveau') === 'danger' ? 'selected' : '' }}>🔴 Danger</option>
                    <option value="warning" {{ request('niveau') === 'warning' ? 'selected' : '' }}>🟠 Avertissement
                    </option>
                    <option value="info" {{ request('niveau') === 'info' ? 'selected' : '' }}>🔵 Information</option>
                </select>
            </div>
            <div>
                <label class="albl">Module</label>
                <select name="type" class="asel" onchange="this.form.submit()">
                    <option value="">Tous</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" {{ request('type') === $type ? 'selected' : '' }}>
                            {{ ucfirst($type) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex;align-items:center;gap:7px;align-self:flex-end;height:40px">
                <input type="checkbox" id="chk-non-lues" name="non_lues" value="1"
                    {{ request()->boolean('non_lues') ? 'checked' : '' }}
                    style="accent-color:var(--accent);width:15px;height:15px;cursor:pointer"
                    onchange="this.form.submit()">
                <label for="chk-non-lues" style="font-size:12px;color:var(--text2);cursor:pointer;font-weight:500">Non
                    lues seulement</label>
            </div>
            @if (request()->hasAny(['niveau', 'type', 'non_lues']))
                <a href="{{ route('admin.alerts.index') }}"
                    style="display:flex;align-items:center;justify-content:center;width:40px;height:40px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;color:var(--text3);text-decoration:none;align-self:flex-end"
                    title="Réinitialiser">↺</a>
            @endif
            <div style="margin-left:auto;align-self:flex-end;font-size:12px;color:var(--text3)">
                <strong style="color:var(--text)">{{ $alertes->total() }}</strong> alerte(s)
            </div>
        </form>
    </div>

    {{-- ════ LISTE ALERTES ════ --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden">
        <div
            style="padding:12px 18px;background:var(--surface2);border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
            <span style="font-weight:600;font-size:14px;display:flex;align-items:center;gap:8px">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                    <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                </svg>
                Alertes
                @if ($totalNonLues > 0)
                    <span
                        style="background:#ef4444;color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px">{{ $totalNonLues }}</span>
                @endif
            </span>
            <span style="font-size:12px;color:var(--text3)">page
                {{ $alertes->currentPage() }}/{{ $alertes->lastPage() }}</span>
        </div>

        @if ($alertes->isEmpty())
            <div style="text-align:center;padding:60px;color:var(--text3)">
                <div style="font-size:48px;margin-bottom:14px">🎉</div>
                <div style="font-size:16px;font-weight:700;color:var(--text2);margin-bottom:6px">Aucune alerte !</div>
                <div style="font-size:13px">Tout est en ordre. Revenez plus tard.</div>
            </div>
        @else
            <div style="display:flex;flex-direction:column">
                @foreach ($alertes as $alerte)
                    @php
                        $niveauCfg = match ($alerte->niveau) {
                            'danger' => [
                                'c' => '#ef4444',
                                'bg' => 'rgba(239,68,68,.08)',
                                'bd' => 'rgba(239,68,68,.2)',
                                'icon' => '🔴',
                            ],
                            'warning' => [
                                'c' => '#f97316',
                                'bg' => 'rgba(249,115,22,.08)',
                                'bd' => 'rgba(249,115,22,.2)',
                                'icon' => '🟠',
                            ],
                            default => [
                                'c' => '#3b82f6',
                                'bg' => 'rgba(59,130,246,.08)',
                                'bd' => 'rgba(59,130,246,.2)',
                                'icon' => '🔵',
                            ],
                        };

                        $typeCfg = match ($alerte->type) {
                            'pose' => [
                                'label' => 'Pose OOH',
                                'c' => '#e8a020',
                                'bg' => 'rgba(232,160,32,.1)',
                                'route' => 'admin.pose-tasks.index',
                            ],
                            'pige' => [
                                'label' => 'Pige photo',
                                'c' => '#a855f7',
                                'bg' => 'rgba(168,85,247,.1)',
                                'route' => 'admin.piges.index',
                            ],
                            'campagne' => [
                                'label' => 'Campagne',
                                'c' => '#22c55e',
                                'bg' => 'rgba(34,197,94,.1)',
                                'route' => 'admin.campaigns.index',
                            ],
                            'reservation' => [
                                'label' => 'Réservation',
                                'c' => '#3b82f6',
                                'bg' => 'rgba(59,130,246,.1)',
                                'route' => 'admin.reservations.index',
                            ],
                            'panneau' => [
                                'label' => 'Panneau',
                                'c' => '#6b7280',
                                'bg' => 'rgba(107,114,128,.1)',
                                'route' => 'admin.panels.index',
                            ],
                            'facture' => [
                                'label' => 'Facture',
                                'c' => '#f59e0b',
                                'bg' => 'rgba(245,158,11,.1)',
                                'route' => 'admin.invoices.index',
                            ],
                            default => [
                                'label' => ucfirst($alerte->type ?? 'Système'),
                                'c' => 'var(--text3)',
                                'bg' => 'var(--surface2)',
                                'route' => null,
                            ],
                        };
                    @endphp
                    <div id="alert-{{ $alerte->id }}"
                        style="display:flex;align-items:flex-start;gap:14px;padding:14px 18px;
                    border-bottom:1px solid var(--border);transition:background .15s;
                    {{ !$alerte->is_read ? 'background:' . $niveauCfg['bg'] . '40;border-left:3px solid ' . $niveauCfg['c'] . ';' : '' }}"
                        onmouseover="this.style.background='var(--surface2)'"
                        onmouseout="this.style.background='{{ !$alerte->is_read ? $niveauCfg['bg'] . '40' : '' }}'">

                        {{-- Icône niveau --}}
                        <div style="font-size:18px;flex-shrink:0;margin-top:1px">{{ $niveauCfg['icon'] }}</div>

                        {{-- Contenu --}}
                        <div style="flex:1;min-width:0">
                            <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:5px">
                                {{-- Titre --}}
                                <span
                                    style="font-size:13px;font-weight:700;color:var(--text)">{{ $alerte->title }}</span>

                                {{-- Badge nouveau --}}
                                @if (!$alerte->is_read)
                                    <span
                                        style="padding:1px 7px;border-radius:20px;font-size:9px;font-weight:800;background:{{ $niveauCfg['c'] }};color:#fff;text-transform:uppercase;letter-spacing:.4px">Nouveau</span>
                                @endif

                                {{-- Badge module --}}
                                <span
                                    style="padding:1px 8px;border-radius:20px;font-size:9px;font-weight:700;background:{{ $typeCfg['bg'] }};color:{{ $typeCfg['c'] }}">
                                    {{ $typeCfg['label'] }}
                                </span>
                            </div>

                            <div style="font-size:12px;color:var(--text2);line-height:1.5;margin-bottom:6px">
                                {{ $alerte->message }}
                            </div>

                            {{-- Liens vers les modèles concernés --}}
                            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                                <span
                                    style="font-size:11px;color:var(--text3)">{{ $alerte->created_at->diffForHumans() }}</span>

                                @if ($typeCfg['route'])
                                    <a href="{{ route($typeCfg['route']) }}"
                                        style="font-size:11px;color:{{ $typeCfg['c'] }};text-decoration:none;font-weight:600">
                                        Voir le module →
                                    </a>
                                @endif

                                {{-- Lien direct vers le modèle si disponible --}}
                                @if ($alerte->model_id)
                                    @php
                                        $directUrl = match ($alerte->type) {
                                            'pose' => route('admin.pose-tasks.show', $alerte->model_id),
                                            'pige' => route('admin.piges.show', $alerte->model_id),
                                            'campagne' => route('admin.campaigns.show', $alerte->model_id),
                                            'reservation' => route('admin.reservations.show', $alerte->model_id),
                                            'panneau' => route('admin.panels.show', $alerte->model_id),
                                            'facture' => route('admin.invoices.show', $alerte->model_id),
                                            default => null,
                                        };
                                    @endphp
                                    @if ($directUrl)
                                        <a href="{{ $directUrl }}"
                                            style="font-size:11px;color:var(--accent);text-decoration:none;font-weight:600;padding:2px 8px;background:rgba(232,160,32,.08);border:1px solid rgba(232,160,32,.2);border-radius:6px">
                                            Accéder directement ↗
                                        </a>
                                    @endif
                                @endif
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div style="display:flex;gap:6px;flex-shrink:0;align-items:flex-start">
                            @if (!$alerte->is_read)
                                <button onclick="ALERTS.markRead({{ $alerte->id }}, this)"
                                    style="padding:5px 10px;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.3);color:#22c55e;border-radius:8px;font-size:11px;font-weight:600;cursor:pointer;white-space:nowrap"
                                    title="Marquer comme lu">
                                    ✓ Lu
                                </button>
                            @endif
                            <button onclick="ALERTS.destroy({{ $alerte->id }}, this)"
                                style="padding:5px 9px;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);color:#ef4444;border-radius:8px;font-size:11px;cursor:pointer"
                                title="Supprimer">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6" />
                                    <path
                                        d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
                                </svg>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($alertes->hasPages())
                <div
                    style="padding:14px 18px;border-top:1px solid var(--border);display:flex;justify-content:flex-end">
                    {{ $alertes->links() }}
                </div>
            @endif
        @endif
    </div>

    <style>
        .albl {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--text3);
            display: block;
            margin-bottom: 4px
        }

        .asel {
            height: 40px;
            padding: 0 12px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 13px;
            color: var(--text);
            cursor: pointer;
            outline: none
        }
    </style>

    @push('scripts')
        <script>
            window.ALERTS = {
                csrf: '{{ csrf_token() }}',

                async markRead(id, btn) {
                    btn.disabled = true;
                    btn.textContent = '⟳';
                    try {
                        const res = await fetch(`/admin/alerts/${id}/read`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': this.csrf,
                                Accept: 'application/json'
                            }
                        });
                        if ((await res.json()).success) {
                            const row = document.getElementById('alert-' + id);
                            if (row) {
                                row.style.borderLeft = '';
                                row.style.background = '';
                                // Supprimer badge "Nouveau"
                                row.querySelector('[style*="Nouveau"]')?.remove();
                                // Remplacer le bouton Lu par rien
                                btn.remove();
                            }
                            window.Toast?.success('Alerte marquée comme lue.');
                        }
                    } catch {
                        btn.disabled = false;
                        btn.textContent = '✓ Lu';
                    }
                },

                async destroy(id, btn) {
                    if (!confirm('Supprimer cette alerte ?')) return;
                    btn.disabled = true;
                    try {
                        const res = await fetch(`/admin/alerts/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': this.csrf,
                                Accept: 'application/json'
                            }
                        });
                        if ((await res.json()).success) {
                            const row = document.getElementById('alert-' + id);
                            if (row) {
                                row.style.animation = 'alertFade .3s ease forwards';
                                setTimeout(() => row.remove(), 280);
                            }
                            window.Toast?.success('Alerte supprimée.');
                        }
                    } catch {
                        btn.disabled = false;
                    }
                },
            };
        </script>

        <style>
            @keyframes alertFade {
                to {
                    opacity: 0;
                    transform: translateX(10px);
                    max-height: 0;
                    padding: 0
                }
            }
        </style>
    @endpush

</x-admin-layout>
