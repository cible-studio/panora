<x-admin-layout title="Devis">
    <x-slot:topbarActions>
        @can('create', \App\Models\Quote::class)
            <a href="{{ route('admin.quotes.create') }}" class="btn btn-primary btn-sm">
                ➕ <span class="btn-label">Nouveau devis</span>
            </a>
        @endcan
    </x-slot:topbarActions>

    {{-- ═══════════════════ KPI CARDS ═══════════════════ --}}
    <div class="stats-grid" style="display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:18px">
        @foreach([
            ['label'=>'Total', 'value'=>$kpi['total'], 'c'=>'#475569', 'icon'=>'📋'],
            ['label'=>'Brouillon', 'value'=>$kpi['brouillon'], 'c'=>'#94a3b8', 'icon'=>'📝'],
            ['label'=>'Envoyés', 'value'=>$kpi['envoye'], 'c'=>'#1d4ed8', 'icon'=>'📤'],
            ['label'=>'Acceptés', 'value'=>$kpi['accepte'], 'c'=>'#15803d', 'icon'=>'✅'],
            ['label'=>'Refusés', 'value'=>$kpi['refuse'], 'c'=>'#991b1b', 'icon'=>'❌'],
            ['label'=>'Expire dans 7j', 'value'=>$kpi['expire_bientot'], 'c'=>'#b45309', 'icon'=>'⌛'],
        ] as $k)
            <div style="background:var(--surface);border:1px solid var(--border);border-top:3px solid {{ $k['c'] }};border-radius:10px;padding:14px 16px">
                <div style="font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">{{ $k['label'] }}</div>
                <div style="display:flex;align-items:baseline;gap:6px">
                    <span style="font-size:26px;font-weight:800;color:{{ $k['c'] }};line-height:1">{{ $k['value'] }}</span>
                    <span style="font-size:16px">{{ $k['icon'] }}</span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ═══════════════════ FILTRES ═══════════════════ --}}
    <form method="GET" class="filter-bar" style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px;padding:12px;background:var(--surface);border:1px solid var(--border);border-radius:10px">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Réf ou titre..." class="filter-input" style="min-width:180px;flex:1">
        <select name="status" class="filter-select" onchange="this.form.submit()" style="min-width:160px">
            <option value="">— Tous statuts —</option>
            @foreach(\App\Enums\QuoteStatus::cases() as $s)
                <option value="{{ $s->value }}" @selected(request('status')===$s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
        <select name="client_id" class="filter-select" onchange="this.form.submit()" style="min-width:180px">
            <option value="">— Tous clients —</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" @selected(request('client_id')==$c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="filter-input" style="min-width:150px">
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="filter-input" style="min-width:150px">
        <button type="submit" class="btn btn-primary btn-sm">Filtrer</button>
        @if(request()->hasAny(['q','status','client_id','date_from','date_to']))
            <a href="{{ route('admin.quotes.index') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
        @endif
    </form>

    {{-- ═══════════════════ TABLE ═══════════════════ --}}
    <div class="card" style="background:var(--surface);border:1px solid var(--border);border-radius:10px;overflow:hidden">
        <div class="card-header" style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
            <div class="card-title" style="font-weight:700">📋 Liste des devis ({{ $quotes->total() }})</div>
        </div>
        <div class="table-responsive" style="overflow-x:auto">
            <table class="data-table" style="width:100%;border-collapse:collapse">
                <thead>
                    <tr>
                        <th style="text-align:left;padding:10px;background:var(--surface2);font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase">Réf</th>
                        <th style="text-align:left;padding:10px;background:var(--surface2);font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase">Titre / Client</th>
                        <th style="text-align:left;padding:10px;background:var(--surface2);font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase">Période</th>
                        <th style="text-align:right;padding:10px;background:var(--surface2);font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase">Montant</th>
                        <th style="text-align:left;padding:10px;background:var(--surface2);font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase">Statut</th>
                        <th style="text-align:left;padding:10px;background:var(--surface2);font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase">Commercial</th>
                        <th style="text-align:center;padding:10px;background:var(--surface2);font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase">Expire</th>
                        <th style="width:80px;padding:10px;background:var(--surface2)"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($quotes as $quote)
                        @php
                            $st  = $quote->status->uiConfig();
                            $exp = $quote->daysUntilExpiry();
                        @endphp
                        <tr>
                            <td style="padding:10px;font-family:monospace;font-weight:700;color:var(--accent)">{{ $quote->reference }}</td>
                            <td style="padding:10px">
                                <a href="{{ route('admin.quotes.show', $quote) }}" style="color:var(--text);font-weight:600">{{ $quote->title }}</a>
                                <div style="font-size:11.5px;color:var(--text3);margin-top:2px">🏢 {{ \Illuminate\Support\Str::limit($quote->client?->name ?? '—', 30) }}</div>
                            </td>
                            <td style="padding:10px;font-size:12px;color:var(--text2)">
                                @if($quote->period_start && $quote->period_end)
                                    {{ $quote->period_start->format('d/m/y') }} → {{ $quote->period_end->format('d/m/y') }}
                                @else
                                    <span style="color:var(--text3)">—</span>
                                @endif
                            </td>
                            <td style="padding:10px;text-align:right;font-weight:700">
                                {{ number_format($quote->total_a_payer, 0, ',', ' ') }}
                                <span style="font-size:10px;color:var(--text3)">FCFA</span>
                            </td>
                            <td style="padding:10px">
                                <span style="background:{{ $st['bg'] }};color:{{ $st['color'] }};border:1px solid {{ $st['border'] }};padding:3px 9px;border-radius:12px;font-size:11px;font-weight:700">
                                    {{ $st['icon'] }} {{ $quote->status->label() }}
                                </span>
                            </td>
                            <td style="padding:10px;font-size:12px;color:var(--text2)">{{ $quote->commercial?->name ?? '—' }}</td>
                            <td style="padding:10px;text-align:center;font-size:12px">
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
                            <td style="padding:10px;text-align:right">
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
</x-admin-layout>
