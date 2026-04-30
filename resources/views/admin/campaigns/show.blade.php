<x-admin-layout title="{{ $campaign->name }}">
    <x-slot:topbarActions>
        <a href="{{ route('admin.campaigns.index') }}" class="btn btn-ghost btn-sm">← Retour</a>
        @if($can['update'])
            <a href="{{ route('admin.campaigns.edit', $campaign) }}" class="btn btn-ghost btn-sm">✏️ Modifier</a>
        @endif
        @if($can['delete'])
            <button type="button"
                    onclick="openDeleteModal({{ $campaign->id }}, @js($campaign->name))"
                    class="btn btn-ghost btn-sm text-red-400 border-red-500/30 hover:bg-red-500/20">
                🗑 Supprimer
            </button>
        @endif
    </x-slot:topbarActions>

    @php
        $statusCfg  = $campaign->status->uiConfig();
        $daysLeft   = $campaign->daysRemaining();
        $humanTime  = $campaign->humanTimeRemaining();
        $pct        = $campaign->progressPercent();
        $endingSoon = $campaign->isEndingSoon();
        $isRunning  = in_array($campaign->status->value, ['actif', 'pose']);
        $minNewEnd  = $campaign->end_date->copy()->addDay()->format('Y-m-d');
    @endphp

    {{-- ── EN-TÊTE ── --}}
    <div class="relative mb-8 overflow-hidden rounded-2xl border shadow-xl"
         style="background:var(--surface2);border-color:var(--border)">
        <div class="relative px-6 py-5">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4 flex-wrap">
                    <h1 class="text-3xl font-bold" style="color:var(--text)">{{ $campaign->name }}</h1>
                    <span id="campaign-status-badge"
                          class="px-4 py-1.5 rounded-full text-sm font-bold shadow-lg"
                          style="background:{{ $statusCfg['bg'] }};color:{{ $statusCfg['color'] }};border:1px solid {{ $statusCfg['border'] }}">
                        {{ $statusCfg['icon'] }} {{ $campaign->status->label() }}
                    </span>
                </div>
                <div class="text-right">
                    <div class="text-sm" style="color:var(--text3)">Durée totale</div>
                    <div class="text-lg font-semibold" style="color:var(--accent)">{{ $campaign->durationHuman() }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── ALERTE FIN PROCHE ── --}}
    <div id="campaign-ending-alert" class="mb-6 rounded-xl border p-4 flex items-center gap-4 {{ $endingSoon ? '' : 'hidden' }}"
         style="background:rgba(245,158,11,0.08);border-color:rgba(245,158,11,0.3)">
        <div class="w-10 h-10 rounded-full flex items-center justify-center text-2xl"
             style="background:rgba(245,158,11,0.2)">⚠️</div>
        <div class="flex-1">
            <div class="font-bold" style="color:#f59e0b">
                Campagne se terminant bientôt — <span id="ending-days-left">{{ $daysLeft }}</span> jour(s) restant(s)
            </div>
            <div class="text-sm" style="color:var(--text2)">
                Pensez à relancer <strong style="color:#f59e0b">{{ $campaign->client?->name }}</strong> pour prolongation.
            </div>
        </div>
        @if($can['update'])
            <button onclick="scrollToProlonger()" class="px-4 py-2 text-white rounded-lg text-sm font-bold" style="background:#f59e0b">
                📅 Prolonger
            </button>
        @endif
    </div>

    {{-- ── GRILLE PRINCIPALE ── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

        {{-- Informations --}}
        <div class="lg:col-span-2 rounded-2xl border overflow-hidden shadow-xl"
             style="background:var(--surface);border-color:var(--border)">
            <div class="px-6 py-4 border-b" style="background:var(--surface2);border-color:var(--border)">
                <h2 class="font-bold text-lg flex items-center gap-2" style="color:var(--text)">
                    <span class="text-2xl">📋</span> Informations
                </h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 md:grid-cols-3 gap-6">

                    {{-- Client --}}
                    <div class="rounded-xl p-4 border" style="background:var(--surface2);border-color:var(--border)">
                        <div class="text-xs uppercase font-semibold mb-2" style="color:var(--text3)">👤 Client</div>
                        @if($campaign->client?->trashed())
                            <div style="color:var(--text2)">
                                {{ $campaign->client->name }}
                                <span class="text-xs px-2 py-0.5 rounded" style="color:#ef4444;background:rgba(239,68,68,0.1)">Supprimé</span>
                            </div>
                        @else
                            <a href="{{ route('admin.clients.show', $campaign->client) }}" class="font-medium hover:underline" style="color:var(--accent)">
                                {{ $campaign->client?->name ?? '—' }}
                            </a>
                        @endif
                    </div>

                    {{-- Période --}}
                    <div class="rounded-xl p-4 border" style="background:var(--surface2);border-color:var(--border)">
                        <div class="text-xs uppercase font-semibold mb-2" style="color:var(--text3)">📅 Période</div>
                        <div class="font-medium" style="color:var(--text)">
                            {{ $campaign->start_date->format('d/m/Y') }} → {{ $campaign->end_date->format('d/m/Y') }}
                        </div>
                        <div class="text-xs mt-1" style="color:var(--text2)">{{ $campaign->durationHuman() }}</div>
                    </div>

                    {{-- Montant --}}
                    <div class="rounded-xl p-4 border" style="background:var(--surface2);border-color:var(--border)">
                        <div class="text-xs uppercase font-semibold mb-2" style="color:var(--text3)">💰 Montant total</div>
                        <div class="text-2xl font-bold" style="color:var(--accent)">
                            {{ number_format($campaign->total_amount, 0, ',', ' ') }}
                            <span class="text-xs font-normal" style="color:var(--text3)">FCFA</span>
                        </div>
                    </div>

                    {{-- Réservation --}}
                    <div class="rounded-xl p-4 border" style="background:var(--surface2);border-color:var(--border)">
                        <div class="text-xs uppercase font-semibold mb-2" style="color:var(--text3)">🔗 Réservation liée</div>
                        @if($campaign->reservation)
                            <a href="{{ route('admin.reservations.show', $campaign->reservation) }}"
                               class="font-mono text-sm hover:underline" style="color:var(--accent)">
                                {{ $campaign->reservation->reference }} →
                            </a>
                        @else
                            <span style="color:var(--text3)">Aucune</span>
                        @endif
                    </div>

                    {{-- Créée par --}}
                    <div class="rounded-xl p-4 border" style="background:var(--surface2);border-color:var(--border)">
                        <div class="text-xs uppercase font-semibold mb-2" style="color:var(--text3)">👨‍💻 Créée par</div>
                        <div style="color:var(--text)">{{ $campaign->user?->name ?? '—' }}</div>
                        <div class="text-xs mt-1" style="color:var(--text3)">{{ $campaign->created_at->format('d/m/Y H:i') }}</div>
                    </div>

                    {{-- Dernière modif --}}
                    <div class="rounded-xl p-4 border" style="background:var(--surface2);border-color:var(--border)">
                        <div class="text-xs uppercase font-semibold mb-2" style="color:var(--text3)">✏️ Dernière modif.</div>
                        <div style="color:var(--text)">{{ $campaign->updated_at->format('d/m/Y H:i') }}</div>
                        @if($campaign->updatedBy)
                            <div class="text-xs mt-1" style="color:var(--text3)">par {{ $campaign->updatedBy->name }}</div>
                        @endif
                    </div>
                </div>

                @if($campaign->notes)
                <div class="mt-6 pt-6 border-t" style="border-color:var(--border)">
                    <div class="text-xs uppercase font-semibold mb-3" style="color:var(--text3)">📝 Notes</div>
                    <div class="rounded-xl p-4 border" style="background:var(--surface2);border-color:var(--border)">
                        <p style="color:var(--text2);white-space:pre-line">{{ $campaign->notes }}</p>
                    </div>
                </div>
                @endif

                {{-- ── BARRE DE PROGRESSION DYNAMIQUE ── --}}
                <div id="campaign-progress-block" class="mt-6 pt-6 border-t {{ $isRunning ? '' : 'hidden' }}"
                     style="border-color:var(--border)"
                     data-campaign-id="{{ $campaign->id }}"
                     data-progress-url="{{ route('admin.campaigns.progress', $campaign) }}"
                     data-start="{{ $campaign->start_date->copy()->startOfDay()->toIso8601String() }}"
                     data-end="{{ $campaign->end_date->copy()->endOfDay()->toIso8601String() }}">
                    <div class="flex justify-between items-center mb-3">
                        <span class="text-xs font-semibold uppercase tracking-wider" style="color:var(--text3)">
                            📊 Progression <span id="progress-live-dot" class="inline-block w-1.5 h-1.5 rounded-full ml-1" style="background:#10b981;animation:pulse-dot 2s infinite"></span>
                        </span>
                        <span id="progress-human" class="text-sm font-medium" style="color:var(--accent)">{{ $humanTime }}</span>
                    </div>
                    <div class="relative h-3 rounded-full overflow-hidden" style="background:var(--surface3)">
                        <div id="progress-bar"
                             class="absolute h-full rounded-full"
                             style="background:#10b981;width:{{ $pct }}%;transition:width .8s ease-out, background .3s"></div>
                    </div>
                    <div class="flex justify-between text-xs mt-2">
                        <span style="color:var(--text3)"><span id="progress-pct">{{ number_format($pct, 1, ',', '') }}</span>% écoulé</span>
                        <span id="progress-days" style="color:var(--accent)">
                            @if($daysLeft > 0)📅 {{ $daysLeft }} jour(s) restant(s)@endif
                        </span>
                    </div>
                </div>

                <style>@keyframes pulse-dot{0%,100%{opacity:1}50%{opacity:.3}}</style>
            </div>
        </div>

        {{-- Actions + Facturation --}}
        <div class="space-y-6">

            {{-- Actions --}}
            <div class="rounded-2xl border overflow-hidden shadow-xl" style="background:var(--surface);border-color:var(--border)">
                <div class="px-6 py-4 border-b" style="background:var(--surface2);border-color:var(--border)">
                    <h2 class="font-bold text-lg flex items-center gap-2" style="color:var(--text)">
                        <span class="text-2xl">⚡</span> Actions
                    </h2>
                </div>
                <div class="p-5">
                    <div class="text-center p-4 rounded-xl mb-5"
                         style="background:{{ $statusCfg['bg'] }};border:1px solid {{ $statusCfg['border'] }}">
                        <div class="text-3xl mb-2">{{ $statusCfg['icon'] }}</div>
                        <div class="font-bold text-base" style="color:{{ $statusCfg['color'] }}">{{ $campaign->status->label() }}</div>
                        <div class="text-xs mt-1" style="color:var(--text3)">{{ $statusCfg['description'] }}</div>
                    </div>

                    @if($can['updateStatus'] && !empty($allowed))
                    <div class="space-y-3">
                        @foreach($allowed as $val => $label)
                            @php
                                $btnStyle = match($val) {
                                    'termine' => 'background:#6b7280;color:#fff',
                                    'annule'  => 'background:#ef4444;color:#fff',
                                    'actif'   => 'background:#10b981;color:#fff',
                                    'pose'    => 'background:#3b82f6;color:#fff',
                                    default   => 'background:var(--surface2);color:var(--text)',
                                };
                                $btnIcon = match($val) {
                                    'termine' => '✅', 'annule' => '🚫', 'actif' => '▶️', 'pose' => '🔧', default => '→'
                                };
                            @endphp
                            <form method="POST" action="{{ route('admin.campaigns.update-status', $campaign) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $val }}">
                                <button type="submit"
                                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-bold transition-all"
                                        style="{{ $btnStyle }}"
                                        @if($val === 'annule') onclick="return confirm('Confirmer l\'annulation ?')" @endif>
                                    {{ $btnIcon }} {{ $label }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                    @else
                        <p class="text-center text-sm py-3" style="color:var(--text3)">Aucune transition disponible</p>
                    @endif

                    {{-- Prolonger --}}
                    @if($can['update'] && in_array($campaign->status->value, ['actif', 'pose', 'termine']))
                    <div class="mt-5 pt-5 border-t" id="section-prolonger" style="border-color:var(--border)" x-data="{ show: false }">
                        <button type="button"
                                class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-bold"
                                style="background:var(--surface2);color:var(--text);border:1px solid var(--border)"
                                @click="show = !show">
                            📅 Prolonger la campagne
                        </button>
                        <div x-show="show" x-collapse class="mt-4">
                            <form method="POST" action="{{ route('admin.campaigns.prolonger', $campaign) }}">
                                @csrf @method('PATCH')
                                <label class="text-xs font-semibold block mb-2" style="color:var(--text3)">NOUVELLE DATE DE FIN</label>
                                <input type="date" name="new_end_date" required min="{{ $minNewEnd }}"
                                       class="w-full rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none"
                                       style="background:var(--surface2);border:1px solid var(--border);color:var(--text)">
                                <button type="submit"
                                        class="w-full font-bold py-2.5 rounded-lg text-sm text-white"
                                        style="background:var(--accent)">
                                    ✅ Confirmer la prolongation
                                </button>
                            </form>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Facturation --}}
            <div class="rounded-2xl border overflow-hidden shadow-xl" style="background:var(--surface);border-color:var(--border)">
                <div class="px-6 py-4 border-b" style="background:var(--surface2);border-color:var(--border)">
                    <h2 class="font-bold text-lg flex items-center gap-2" style="color:var(--text)">
                        <span class="text-2xl">💰</span> Facturation
                    </h2>
                </div>
                <div class="p-5">
                    @if($campaign->invoices->isNotEmpty())
                        <div class="space-y-3">
                            @foreach($campaign->invoices as $inv)
                                <div class="flex justify-between items-center py-3 px-4 rounded-xl border"
                                     style="background:var(--surface2);border-color:var(--border)">
                                    <span class="font-mono text-sm" style="color:var(--accent)">{{ $inv->reference ?? '#'.$inv->id }}</span>
                                    <span class="font-bold" style="color:var(--text)">{{ number_format($inv->amount_ttc, 0, ',', ' ') }} FCFA</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8 rounded-xl border border-dashed" style="border-color:var(--border)">
                            <div class="text-4xl mb-3">💰</div>
                            <div class="text-sm font-semibold" style="color:var(--accent)">À facturer</div>
                            <div class="text-xs mt-1" style="color:var(--text3)">Aucune facture émise</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ── PANNEAUX ── --}}
    <div class="rounded-2xl border overflow-hidden shadow-xl"
         style="background:var(--surface);border-color:var(--border)"
         x-data="panneauxManager()">

        <div class="px-6 py-4 border-b flex justify-between items-center flex-wrap gap-3"
             style="background:var(--surface2);border-color:var(--border)">
            <h2 class="font-bold text-lg flex items-center gap-2" style="color:var(--text)">
                <span class="text-2xl">🪧</span> Panneaux
                <span class="text-sm px-3 py-1 rounded-full" style="background:var(--surface3);color:var(--text3)">
                    {{ $campaign->panels->count() }}
                </span>
            </h2>
            @if($can['managePanel'])
            <button type="button"
                    class="px-4 py-2 text-sm font-semibold rounded-lg text-white"
                    style="background:var(--accent)"
                    @click="toggleAdd()"
                    x-text="showAdd ? '✕ Annuler' : '+ Ajouter un panneau'"></button>
            @endif
        </div>

        @if($can['managePanel'])
        <div x-show="showAdd" x-collapse class="border-b" style="border-color:var(--border)">
            <div class="p-5" style="background:var(--surface2)">
                <form method="POST" action="{{ route('admin.campaigns.panels.add', $campaign) }}">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
                        <div>
                            <label class="text-xs font-semibold block mb-2" style="color:var(--text3)">🔍 Recherche</label>
                            <input type="text" x-model="search" @input.debounce.250ms="filterPanels()"
                                   placeholder="Référence, nom..."
                                   class="w-full rounded-lg px-4 py-2.5 text-sm focus:outline-none"
                                   style="background:var(--surface);border:1px solid var(--border);color:var(--text)">
                        </div>
                        <div>
                            <label class="text-xs font-semibold block mb-2" style="color:var(--text3)">📍 Commune</label>
                            <select x-model="filterCommune" @change="filterPanels()"
                                    class="w-full rounded-lg px-4 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--surface);border:1px solid var(--border);color:var(--text)">
                                <option value="">Toutes</option>
                                <template x-for="c in communeOptions" :key="c">
                                    <option :value="c" x-text="c"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold block mb-2" style="color:var(--text3)">📏 Format</label>
                            <select x-model="filterFormat" @change="filterPanels()"
                                    class="w-full rounded-lg px-4 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--surface);border:1px solid var(--border);color:var(--text)">
                                <option value="">Tous</option>
                                <template x-for="f in formatOptions" :key="f">
                                    <option :value="f" x-text="f"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold block mb-2" style="color:var(--text3)">💡 Éclairage</label>
                            <select x-model="filterIsLit" @change="filterPanels()"
                                    class="w-full rounded-lg px-4 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--surface);border:1px solid var(--border);color:var(--text)">
                                <option value="">Tous</option>
                                <option value="1">💡 Éclairé</option>
                                <option value="0">🌙 Non éclairé</option>
                            </select>
                        </div>
                    </div>

                    <div class="border rounded-xl overflow-hidden max-h-96 overflow-y-auto"
                         style="background:var(--surface);border-color:var(--border)">
                        <div x-show="loadingPanels" class="text-center py-12">
                            <div class="inline-block w-6 h-6 border-2 border-t-transparent rounded-full animate-spin"
                                 style="border-color:var(--accent);border-top-color:transparent"></div>
                            <div class="text-sm mt-2" style="color:var(--text3)">Chargement des panneaux disponibles...</div>
                        </div>
                        <template x-if="!loadingPanels && filteredPanels.length === 0">
                            <div class="text-center py-12" style="color:var(--text3)">Aucun panneau libre trouvé</div>
                        </template>
                        <template x-for="p in paginatedPanels" :key="p.id">
                            <label class="flex items-center gap-4 p-4 border-b last:border-0 cursor-pointer transition-all"
                                   style="border-color:var(--border)"
                                   :style="selectedPanels.includes(p.id) ? 'background:var(--accent-dim);border-left:3px solid var(--accent)' : ''">
                                <input type="checkbox" :value="p.id" x-model="selectedPanels" name="panel_ids[]"
                                       class="w-4 h-4" style="accent-color:var(--accent)">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-3 flex-wrap">
                                        <span class="font-mono text-sm font-bold" style="color:var(--accent)" x-text="p.reference"></span>
                                        <span class="font-medium truncate" style="color:var(--text)" x-text="p.name"></span>
                                    </div>
                                    <div class="flex gap-4 text-xs mt-1" style="color:var(--text3)">
                                        <span>📍 <span x-text="p.commune || '—'"></span></span>
                                        <span>📏 <span x-text="p.format || '—'"></span></span>
                                        <span x-show="p.is_lit" style="color:var(--accent)">💡 Éclairé</span>
                                        <span x-show="p.monthly_rate" class="font-semibold" style="color:var(--accent)" x-text="formatPrice(p.monthly_rate)"></span>
                                    </div>
                                </div>
                            </label>
                        </template>
                    </div>

                    <div x-show="filteredPanels.length > visibleCount" class="text-center py-3">
                        <button type="button" @click="visibleCount += 20" class="text-sm font-semibold" style="color:var(--accent)">
                            + Afficher plus (<span x-text="filteredPanels.length - visibleCount"></span> restant(s))
                        </button>
                    </div>

                    <div x-show="selectedPanels.length > 0"
                         class="mt-5 flex justify-between items-center p-4 rounded-xl border"
                         style="background:var(--accent-dim);border-color:rgba(var(--accent-rgb),.3)">
                        <span class="text-sm" style="color:var(--text2)">
                            <strong x-text="selectedPanels.length" style="color:var(--text)"></strong> panneau(x) —
                            <strong class="text-base" style="color:var(--accent)" x-text="formatEstimate()"></strong> FCFA
                        </span>
                        <button type="submit"
                                class="text-white font-bold px-6 py-2.5 rounded-lg text-sm"
                                style="background:var(--accent)">
                            ✅ Ajouter
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        {{-- Tableau panneaux --}}
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="border-b" style="background:var(--surface2);border-color:var(--border)">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider" style="color:var(--text3)">
                        <th class="px-5 py-4">Référence</th>
                        <th class="px-5 py-4">Désignation</th>
                        <th class="px-5 py-4">Commune</th>
                        <th class="px-5 py-4">Format</th>
                        <th class="px-5 py-4">💡</th>
                        <th class="px-5 py-4 text-right">Prix/mois</th>
                        <th class="px-5 py-4 text-right">Total période</th>
                        <th class="px-5 py-4">Statut</th>
                        @if($can['managePanel'])<th class="px-5 py-4"></th>@endif
                    </tr>
                </thead>
                <tbody>
                @php $billableMonths = $campaign->billableMonths(); @endphp
                @forelse($campaign->panels as $panel)
                    @php
                        $ps = $panel->status->value;
                        $psColor = match($ps) {
                            'confirme'    => ['#10b981','rgba(16,185,129,0.1)','rgba(16,185,129,0.3)'],
                            'option'      => ['#f59e0b','rgba(245,158,11,0.1)','rgba(245,158,11,0.3)'],
                            'libre'       => ['#6b7280','rgba(107,114,128,0.1)','rgba(107,114,128,0.3)'],
                            'maintenance' => ['#ef4444','rgba(239,68,68,0.1)','rgba(239,68,68,0.3)'],
                            default       => ['#6b7280','rgba(107,114,128,0.1)','rgba(107,114,128,0.3)'],
                        };
                        $rate = (float) ($panel->monthly_rate ?? 0);
                    @endphp
                    <tr class="border-b transition-all group" style="border-color:var(--border)"
                        onmouseover="this.style.background='var(--surface2)'"
                        onmouseout="this.style.background='transparent'">
                        <td class="px-5 py-4">
                            <span class="font-mono text-sm font-bold" style="color:var(--accent)">{{ $panel->reference }}</span>
                        </td>
                        <td class="px-5 py-4 font-medium" style="color:var(--text)">{{ $panel->name }}</td>
                        <td class="px-5 py-4" style="color:var(--text2)">{{ $panel->commune?->name ?? '—' }}</td>
                        <td class="px-5 py-4" style="color:var(--text2)">{{ $panel->format?->name ?? '—' }}</td>
                        <td class="px-5 py-4">
                            @if($panel->is_lit)
                                <span style="color:var(--accent)">💡 Oui</span>
                            @else
                                <span style="color:var(--text3)">Non</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right" style="color:var(--text2)">
                            {{ $rate > 0 ? number_format($rate, 0, ',', ' ') . ' FCFA' : '—' }}
                        </td>
                        <td class="px-5 py-4 text-right font-semibold" style="color:var(--accent)">
                            {{ $rate > 0 ? number_format($rate * $billableMonths, 0, ',', ' ') . ' FCFA' : '—' }}
                        </td>
                        <td class="px-5 py-4">
                            <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold border"
                                  style="background:{{ $psColor[1] }};color:{{ $psColor[0] }};border-color:{{ $psColor[2] }}">
                                {{ $panel->status->label() }}
                            </span>
                        </td>
                        @if($can['managePanel'])
                            <td class="px-5 py-4">
                                <button type="button"
                                        onclick="openRetirePanel({{ $panel->id }}, @js($panel->reference))"
                                        class="p-2 rounded-lg transition-all"
                                        style="color:#ef4444"
                                        onmouseover="this.style.background='rgba(239,68,68,0.1)'"
                                        onmouseout="this.style.background='transparent'">✕</button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $can['managePanel'] ? 9 : 8 }}" class="text-center py-16" style="color:var(--text3)">
                            Aucun panneau lié à cette campagne
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── MODAL SUPPRESSION ── --}}
    <div id="modal-delete" class="fixed inset-0 backdrop-blur-md flex items-center justify-center z-50 hidden"
         style="background:rgba(0,0,0,0.7)" onclick="if(event.target===this) closeModal()">
        <div class="rounded-2xl border max-w-md w-full mx-4 overflow-hidden shadow-2xl"
             style="background:var(--surface);border-color:var(--border)" onclick="event.stopPropagation()">
            <div class="px-6 py-5 border-b flex justify-between items-center"
                 style="background:rgba(239,68,68,0.08);border-color:rgba(239,68,68,0.25)">
                <h3 class="font-bold text-xl" style="color:#ef4444">🗑 Supprimer la campagne</h3>
                <button onclick="closeModal()" class="text-2xl transition" style="color:var(--text3)">&times;</button>
            </div>
            <div class="p-6 text-center">
                <div class="text-6xl mb-4">🗑</div>
                <div class="font-bold text-lg mb-2" style="color:var(--text)">
                    Supprimer <span id="delete-name" style="color:var(--accent)"></span> ?
                </div>
                <div class="text-sm mb-5" style="color:var(--text2)">Tous les panneaux liés seront détachés et libérés.</div>
                <div class="rounded-xl p-4 text-sm" style="color:#ef4444;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2)">
                    ⚠️ Cette action est irréversible.
                </div>
            </div>
            <div class="px-6 py-5 border-t flex justify-end gap-3" style="border-color:var(--border)">
                <button onclick="closeModal()" class="px-5 py-2 rounded-xl border transition-all"
                        style="border-color:var(--border);color:var(--text2)">Annuler</button>
                <form id="delete-form" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="px-5 py-2 rounded-xl text-white font-semibold" style="background:#ef4444">🗑 Supprimer</button>
                </form>
            </div>
        </div>
    </div>

    {{-- ── MODAL RETRAIT PANNEAU ── --}}
    <div id="modal-retire" class="fixed inset-0 backdrop-blur-md flex items-center justify-center z-50 hidden"
         style="background:rgba(0,0,0,0.7)" onclick="if(event.target===this) closeRetireModal()">
        <div class="rounded-2xl border max-w-md w-full mx-4 overflow-hidden shadow-2xl"
             style="background:var(--surface);border-color:var(--border)" onclick="event.stopPropagation()">
            <div class="px-6 py-5 border-b flex justify-between items-center"
                 style="background:rgba(249,115,22,0.08);border-color:rgba(249,115,22,0.25)">
                <h3 class="font-bold text-xl" style="color:#f97316">✕ Retirer le panneau</h3>
                <button onclick="closeRetireModal()" class="text-2xl" style="color:var(--text3)">&times;</button>
            </div>
            <div class="p-6 text-center">
                <div class="text-6xl mb-4">🪧</div>
                <div class="font-bold text-lg mb-2" style="color:var(--text)">
                    Retirer <span id="retire-ref" style="color:var(--accent)"></span> ?
                </div>
                <div class="text-sm" style="color:var(--text2)">
                    Le panneau sera détaché et son statut recalculé.
                </div>
            </div>
            <div class="px-6 py-5 border-t flex justify-end gap-3" style="border-color:var(--border)">
                <button onclick="closeRetireModal()" class="px-5 py-2 rounded-xl border"
                        style="border-color:var(--border);color:var(--text2)">Annuler</button>
                <form id="retire-form" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="px-5 py-2 rounded-xl text-white font-semibold" style="background:#ef4444">✕ Retirer</button>
                </form>
            </div>
        </div>
    </div>

    {{-- ── SCRIPTS ── --}}
    <script>
    // ─────────────────────────────────────────────────────────────────
    // PROGRESSION DYNAMIQUE
    // - Polling JSON toutes les 60 s pour synchro serveur (statut, jours)
    // - Interpolation locale toutes les 1 s entre 2 polls (animation fluide)
    // - Si le serveur signale un changement de statut → rechargement page
    // ─────────────────────────────────────────────────────────────────
    (function () {
        const block = document.getElementById('campaign-progress-block');
        if (!block || block.classList.contains('hidden')) return;

        const POLL_INTERVAL = 60_000; // 60 s
        const TICK_INTERVAL = 1_000;  // interpolation locale

        const startTs = new Date(block.dataset.start).getTime();
        const endTs   = new Date(block.dataset.end).getTime();
        const url     = block.dataset.progressUrl;

        const $bar    = document.getElementById('progress-bar');
        const $pct    = document.getElementById('progress-pct');
        const $human  = document.getElementById('progress-human');
        const $days   = document.getElementById('progress-days');
        const $alert  = document.getElementById('campaign-ending-alert');
        const $alertDays = document.getElementById('ending-days-left');
        const $statusBadge = document.getElementById('campaign-status-badge');

        const colorFor = (pct) => pct >= 90 ? '#ef4444' : (pct >= 70 ? '#f59e0b' : '#10b981');

        function computeLocalPct() {
            const now = Date.now();
            if (now <= startTs) return 0;
            if (now >= endTs)   return 100;
            const total   = endTs - startTs;
            const elapsed = now - startTs;
            return Math.max(0, Math.min(100, (elapsed / total) * 100));
        }

        function applyPct(pct) {
            const rounded = pct.toFixed(1).replace('.', ',');
            if ($bar)   { $bar.style.width = pct + '%'; $bar.style.background = colorFor(pct); }
            if ($pct)   $pct.textContent = rounded;
        }

        // Tick local (animation entre 2 polls)
        setInterval(() => applyPct(computeLocalPct()), TICK_INTERVAL);

        // Poll serveur (vérité, jours, statut)
        async function poll() {
            try {
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const d = await res.json();

                if (d.reload) { window.location.reload(); return; }

                applyPct(parseFloat(d.pct));
                if ($human) $human.textContent = d.human_time;
                if ($days)  $days.textContent = d.days_left > 0 ? `📅 ${d.days_left} jour(s) restant(s)` : '';

                // Alerte fin proche
                if (d.ending_soon) {
                    $alert?.classList.remove('hidden');
                    if ($alertDays) $alertDays.textContent = d.days_left;
                } else {
                    $alert?.classList.add('hidden');
                }

                // Si la campagne est terminée → on cache la barre
                if (!d.is_running) {
                    block.classList.add('hidden');
                }
            } catch (e) { /* offline / network — silencieux */ }
        }

        poll();
        setInterval(poll, POLL_INTERVAL);
    })();

    // ─────────────────────────────────────────────────────────────────
    // GESTION PANNEAUX (lazy AJAX au lieu de tout précharger)
    // ─────────────────────────────────────────────────────────────────
    @if($can['managePanel'])
    const PANELS_URL = @json(route('admin.campaigns.available-panels', $campaign));
    @endif

    function panneauxManager() {
        return {
            showAdd: false,
            search: '',
            filterCommune: '',
            filterFormat: '',
            filterIsLit: '',
            selectedPanels: [],
            allPanels: [],
            filteredPanels: [],
            loadingPanels: false,
            loaded: false,
            visibleCount: 20,
            campaignMonths: {{ $billableMonths }},

            get communeOptions() {
                return [...new Set(this.allPanels.map(p => p.commune).filter(Boolean))].sort();
            },
            get formatOptions() {
                return [...new Set(this.allPanels.map(p => p.format).filter(Boolean))].sort();
            },
            get paginatedPanels() {
                return this.filteredPanels.slice(0, this.visibleCount);
            },

            async toggleAdd() {
                this.showAdd = !this.showAdd;
                if (this.showAdd && !this.loaded) {
                    await this.loadPanels();
                }
            },

            async loadPanels() {
                @if($can['managePanel'])
                this.loadingPanels = true;
                try {
                    const res = await fetch(PANELS_URL, { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    this.allPanels      = data.panels || [];
                    this.campaignMonths = data.campaign_months || this.campaignMonths;
                    this.filteredPanels = [...this.allPanels];
                    this.loaded = true;
                } catch (e) {
                    this.allPanels = [];
                    this.filteredPanels = [];
                } finally {
                    this.loadingPanels = false;
                }
                @endif
            },

            filterPanels() {
                const s  = this.search.toLowerCase().trim();
                const fc = this.filterCommune.toLowerCase();
                const ff = this.filterFormat.toLowerCase();
                const fl = this.filterIsLit;
                this.visibleCount = 20;
                this.filteredPanels = this.allPanels.filter(p =>
                    (!s  || p.reference.toLowerCase().includes(s) || p.name.toLowerCase().includes(s) || p.commune.toLowerCase().includes(s)) &&
                    (!fc || p.commune.toLowerCase() === fc) &&
                    (!ff || p.format.toLowerCase() === ff) &&
                    (!fl || (fl === '1' ? p.is_lit : !p.is_lit))
                );
            },

            formatPrice(p) { return Number(p).toLocaleString('fr-FR') + ' FCFA/mois'; },
            formatEstimate() {
                const total = this.selectedPanels.reduce((s, id) =>
                    s + ((this.allPanels.find(x => x.id === id)?.monthly_rate || 0) * this.campaignMonths), 0);
                return Math.round(total).toLocaleString('fr-FR');
            },
        };
    }

    function openDeleteModal(id, name) {
        document.getElementById('delete-name').textContent = name;
        document.getElementById('delete-form').action = `/admin/campaigns/${id}`;
        document.getElementById('modal-delete').classList.remove('hidden');
    }
    function closeModal() { document.getElementById('modal-delete').classList.add('hidden'); }

    function openRetirePanel(id, ref) {
        document.getElementById('retire-ref').textContent = ref;
        document.getElementById('retire-form').action = `/admin/campaigns/{{ $campaign->id }}/panels/${id}`;
        document.getElementById('modal-retire').classList.remove('hidden');
    }
    function closeRetireModal() { document.getElementById('modal-retire').classList.add('hidden'); }

    function scrollToProlonger() {
        document.getElementById('section-prolonger')?.scrollIntoView({ behavior: 'smooth' });
    }
    </script>
</x-admin-layout>
