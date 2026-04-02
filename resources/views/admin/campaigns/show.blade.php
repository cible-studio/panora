<x-admin-layout title="{{ $campaign->name }}">
    <x-slot:topbarActions>
        <a href="{{ route('admin.campaigns.index') }}" class="btn btn-ghost btn-sm transition-all duration-200 hover:bg-white/10">
            ← Retour
        </a>
        @if($can['update'])
        <a href="{{ route('admin.campaigns.edit', $campaign) }}" class="btn btn-ghost btn-sm transition-all duration-200 hover:bg-white/10">
            ✏️ Modifier
        </a>
        @endif
        @if($can['delete'])
        <button type="button" onclick="openDeleteModal({{ $campaign->id }}, '{{ addslashes($campaign->name) }}')" 
                class="btn btn-ghost btn-sm text-red-400 border-red-500/30 hover:bg-red-500/20 hover:text-red-300 transition-all duration-200">
            🗑 Supprimer
        </button>
        @endif
    </x-slot:topbarActions>

    @php
        $statusCfg = $campaign->status->uiConfig();
        $daysLeft  = $campaign->daysRemaining();
        $humanTime = $campaign->humanTimeRemaining();
        $pct       = $campaign->progressPercent();
        $barColor = $pct >= 90 ? '#ef4444' : ($pct >= 70 ? '#f59e0b' : '#10b981');
        $endingSoon = $campaign->isEndingSoon();
    @endphp

    {{-- En-tête avec dégradé --}}
    <div class="relative mb-8 overflow-hidden rounded-2xl bg-gradient-to-r from-[#1e293b] to-[#0f172a] border border-[#334155] shadow-xl">
        <div class="absolute top-0 right-0 w-64 h-64 bg-accent/5 rounded-full blur-3xl"></div>
        <div class="relative px-6 py-5">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4 flex-wrap">
                    <h1 class="text-3xl font-bold bg-gradient-to-r from-white to-gray-300 bg-clip-text text-transparent">
                        {{ $campaign->name }}
                    </h1>
                    <span class="px-4 py-1.5 rounded-full text-sm font-bold shadow-lg" 
                          style="background:{{ $statusCfg['bg'] }}; color:{{ $statusCfg['color'] }}; border:1px solid {{ $statusCfg['border'] }}; box-shadow: 0 2px 8px rgba(0,0,0,0.2)">
                        {{ $statusCfg['icon'] }} {{ $campaign->status->label() }}
                    </span>
                    @if(isset($campaign->type))
                    <span class="px-3 py-1.5 rounded-full text-xs font-semibold bg-white/10 text-gray-300 border border-white/20 backdrop-blur-sm">
                        {{ $campaign->type === 'ferme' ? '🔒 Ferme' : '⏳ Option' }}
                    </span>
                    @endif
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-400">Durée totale</div>
                    <div class="text-lg font-semibold text-accent">{{ $campaign->durationHuman() }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Alerte fin proche --}}
    @if($endingSoon)
    <div class="mb-6 rounded-xl bg-gradient-to-r from-amber-500/20 to-orange-500/20 border border-amber-500/40 p-4 flex items-center gap-4 shadow-lg">
        <div class="w-10 h-10 rounded-full bg-amber-500/30 flex items-center justify-center text-2xl">⚠️</div>
        <div class="flex-1">
            <div class="font-bold text-amber-400">Campagne se terminant bientôt — {{ $daysLeft }} jour(s) restant(s)</div>
            <div class="text-sm text-gray-300">Pensez à relancer <strong class="text-amber-400">{{ $campaign->client?->name }}</strong> pour prolongation ou nouvelle campagne.</div>
        </div>
        @if($can['update'])
        <button onclick="scrollToProlonger()" class="px-4 py-2 bg-gradient-to-r from-amber-500 to-orange-500 text-white rounded-lg text-sm font-bold hover:from-amber-600 hover:to-orange-600 transition-all duration-200 shadow-md hover:shadow-lg">
            📅 Prolonger
        </button>
        @endif
    </div>
    @endif

    {{-- Grille principale --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        {{-- Informations --}}
        <div class="lg:col-span-2 bg-[#0f172a] rounded-2xl border border-[#334155] overflow-hidden shadow-xl">
            <div class="px-6 py-4 bg-gradient-to-r from-[#1e293b] to-[#0f172a] border-b border-[#334155]">
                <h2 class="font-bold text-white text-lg flex items-center gap-2">
                    <span class="text-2xl">📋</span> Informations
                </h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
                    <div class="bg-[#1e293b]/50 rounded-xl p-4 border border-[#334155] hover:border-accent/50 transition-all duration-200">
                        <div class="text-xs uppercase text-gray-400 font-semibold mb-2 flex items-center gap-1">
                            <span>👤</span> Client
                        </div>
                        @if($campaign->client?->trashed())
                        <div class="text-gray-300">{{ $campaign->client->name }} <span class="text-xs text-red-400 bg-red-500/20 px-2 py-0.5 rounded">Supprimé</span></div>
                        @else
                        <a href="{{ route('admin.clients.show', $campaign->client) }}" class="text-accent hover:text-accent/80 hover:underline font-medium">{{ $campaign->client?->name ?? '—' }}</a>
                        @endif
                    </div>
                    <div class="bg-[#1e293b]/50 rounded-xl p-4 border border-[#334155] hover:border-accent/50 transition-all duration-200">
                        <div class="text-xs uppercase text-gray-400 font-semibold mb-2 flex items-center gap-1">
                            <span>📅</span> Période
                        </div>
                        <div class="text-white font-medium">{{ $campaign->start_date->format('d/m/Y') }} → {{ $campaign->end_date->format('d/m/Y') }}</div>
                        <div class="text-xs text-gray-400 mt-1">{{ $campaign->durationHuman() }}</div>
                    </div>
                    <div class="bg-[#1e293b]/50 rounded-xl p-4 border border-[#334155] hover:border-accent/50 transition-all duration-200">
                        <div class="text-xs uppercase text-gray-400 font-semibold mb-2 flex items-center gap-1">
                            <span>💰</span> Montant total
                        </div>
                        <div class="text-2xl font-bold text-accent">{{ number_format($campaign->total_amount, 0, ',', ' ') }} <span class="text-xs text-gray-400">FCFA</span></div>
                    </div>
                    <div class="bg-[#1e293b]/50 rounded-xl p-4 border border-[#334155] hover:border-accent/50 transition-all duration-200">
                        <div class="text-xs uppercase text-gray-400 font-semibold mb-2 flex items-center gap-1">
                            <span>🔗</span> Réservation liée
                        </div>
                        @if($campaign->reservation)
                        <a href="{{ route('admin.reservations.show', $campaign->reservation) }}" class="text-accent hover:text-accent/80 hover:underline font-mono text-sm">{{ $campaign->reservation->reference }} →</a>
                        @else
                        <span class="text-gray-400">Aucune</span>
                        @endif
                    </div>
                    <div class="bg-[#1e293b]/50 rounded-xl p-4 border border-[#334155] hover:border-accent/50 transition-all duration-200">
                        <div class="text-xs uppercase text-gray-400 font-semibold mb-2 flex items-center gap-1">
                            <span>👨‍💻</span> Créée par
                        </div>
                        <div class="text-white">{{ $campaign->user?->name ?? '—' }}</div>
                        <div class="text-xs text-gray-400 mt-1">{{ $campaign->created_at->format('d/m/Y H:i') }}</div>
                    </div>
                    <div class="bg-[#1e293b]/50 rounded-xl p-4 border border-[#334155] hover:border-accent/50 transition-all duration-200">
                        <div class="text-xs uppercase text-gray-400 font-semibold mb-2 flex items-center gap-1">
                            <span>✏️</span> Dernière modif.
                        </div>
                        <div class="text-white">{{ $campaign->updated_at->format('d/m/Y H:i') }}</div>
                        @if($campaign->updatedBy)<div class="text-xs text-gray-400 mt-1">par {{ $campaign->updatedBy->name }}</div>@endif
                    </div>
                </div>

                @if($campaign->notes)
                <div class="mt-6 pt-6 border-t border-[#334155]">
                    <div class="text-xs uppercase text-gray-400 font-semibold mb-3 flex items-center gap-1">
                        <span>📝</span> Notes
                    </div>
                    <div class="bg-[#1e293b]/30 rounded-xl p-4 border border-[#334155]">
                        <p class="text-gray-300 leading-relaxed">{{ $campaign->notes }}</p>
                    </div>
                </div>
                @endif

                {{-- Barre progression --}}
                @if($campaign->status->value === 'actif')
                <div class="mt-6 pt-6 border-t border-[#334155]">
                    <div class="flex justify-between items-center mb-3">
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">📊 PROGRESSION</span>
                        <span class="text-sm font-medium text-accent">{{ $humanTime }}</span>
                    </div>
                    <div class="relative h-3 bg-[#1e293b] rounded-full overflow-hidden shadow-inner">
                        <div class="absolute h-full rounded-full transition-all duration-500 ease-out" 
                             style="background: linear-gradient(90deg, {{ $barColor }}, {{ $barColor }}dd); width: {{ $pct }}%; box-shadow: 0 0 8px {{ $barColor }}80"></div>
                    </div>
                    <div class="flex justify-between text-xs mt-2 text-gray-400">
                        <span>{{ $pct }}% de la période écoulée</span>
                        @if($daysLeft > 0)<span class="text-accent">📅 {{ $daysLeft }} jour(s) restant(s)</span>@endif
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Actions et Facturation --}}
        <div class="space-y-6">
            {{-- Actions --}}
            <div class="bg-[#0f172a] rounded-2xl border border-[#334155] overflow-hidden shadow-xl">
                <div class="px-6 py-4 bg-gradient-to-r from-[#1e293b] to-[#0f172a] border-b border-[#334155]">
                    <h2 class="font-bold text-white text-lg flex items-center gap-2">
                        <span class="text-2xl">⚡</span> Actions
                    </h2>
                </div>
                <div class="p-5">
                    <div class="text-center p-4 rounded-xl mb-5 backdrop-blur-sm" 
                         style="background:{{ $statusCfg['bg'] }}; border:1px solid {{ $statusCfg['border'] }}; box-shadow: 0 4px 12px rgba(0,0,0,0.2)">
                        <div class="text-3xl mb-2">{{ $statusCfg['icon'] }}</div>
                        <div class="font-bold text-base" style="color:{{ $statusCfg['color'] }}">{{ $campaign->status->label() }}</div>
                        <div class="text-xs text-gray-400 mt-1">{{ $statusCfg['description'] }}</div>
                    </div>

                    @if($can['updateStatus'] && !empty($allowed))
                    <div class="space-y-3">
                        @foreach($allowed as $val => $label)
                        @php
                            $btnClass = match($val) {
                                'termine' => 'bg-gradient-to-r from-gray-600 to-gray-700 text-white border-gray-500/50 hover:from-gray-700 hover:to-gray-800',
                                'annule' => 'bg-gradient-to-r from-red-600 to-red-700 text-white border-red-500/50 hover:from-red-700 hover:to-red-800',
                                'actif' => 'bg-gradient-to-r from-emerald-600 to-green-700 text-white border-green-500/50 hover:from-emerald-700 hover:to-green-800',
                                default => 'bg-gradient-to-r from-slate-600 to-slate-700 text-white border-slate-500/50',
                            };
                            $btnIcon = match($val) { 'termine' => '✅', 'annule' => '🚫', 'actif' => '▶️', default => '→' };
                        @endphp
                        <form method="POST" action="{{ route('admin.campaigns.update-status', $campaign) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="{{ $val }}">
                            <button type="submit" 
                                    class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-bold transition-all duration-200 shadow-md hover:shadow-lg transform hover:-translate-y-0.5 {{ $btnClass }}"
                                    @if($val === 'annule') onclick="return confirm('Confirmer l\'annulation ?\nLes panneaux seront libérés immédiatement.')" @endif>
                                <span class="text-base">{{ $btnIcon }}</span> {{ $label }}
                            </button>
                        </form>
                        @endforeach
                    </div>
                    @else
                    <p class="text-center text-sm text-gray-400 py-3">Aucune transition disponible</p>
                    @endif

                    @if($can['update'] && in_array($campaign->status->value, ['actif','termine']))
                    <div class="mt-5 pt-5 border-t border-[#334155]" id="section-prolonger" x-data="{ show: false }">
                        <button type="button" 
                                class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-bold bg-gradient-to-r from-indigo-600 to-purple-600 text-white border border-indigo-500/50 hover:from-indigo-700 hover:to-purple-700 transition-all duration-200 shadow-md hover:shadow-lg transform hover:-translate-y-0.5"
                                @click="show = !show">
                            📅 Prolonger la campagne
                        </button>
                        <div x-show="show" x-collapse class="mt-4">
                            <form method="POST" action="{{ route('admin.campaigns.prolonger', $campaign) }}">
                                @csrf @method('PATCH')
                                <label class="text-xs text-gray-400 font-semibold block mb-2">NOUVELLE DATE DE FIN</label>
                                <input type="date" name="new_end_date" min="{{ $campaign->end_date->addDay()->format('Y-m-d') }}" 
                                       class="w-full bg-[#1e293b] border border-[#334155] rounded-lg px-4 py-2.5 text-white text-sm focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent transition-all duration-200 mb-4">
                                <button type="submit" class="w-full bg-gradient-to-r from-accent to-yellow-500 text-white font-bold py-2.5 rounded-lg text-sm hover:from-accent/90 hover:to-yellow-500/90 transition-all duration-200 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                                    ✅ Confirmer la prolongation
                                </button>
                            </form>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Facturation --}}
            <div class="bg-[#0f172a] rounded-2xl border border-[#334155] overflow-hidden shadow-xl">
                <div class="px-6 py-4 bg-gradient-to-r from-[#1e293b] to-[#0f172a] border-b border-[#334155]">
                    <h2 class="font-bold text-white text-lg flex items-center gap-2">
                        <span class="text-2xl">💰</span> Facturation
                    </h2>
                </div>
                <div class="p-5">
                    @if($campaign->invoices->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($campaign->invoices as $inv)
                        <div class="flex justify-between items-center py-3 px-4 bg-[#1e293b]/50 rounded-xl border border-[#334155] hover:border-accent/50 transition-all duration-200">
                            <span class="font-mono text-sm text-accent">{{ $inv->reference ?? '#'.$inv->id }}</span>
                            <span class="font-bold text-white">{{ number_format($inv->amount_ttc, 0, ',', ' ') }} FCFA</span>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div class="text-center py-8 bg-[#1e293b]/30 rounded-xl border border-dashed border-[#334155]">
                        <div class="text-4xl mb-3">💰</div>
                        <div class="text-sm font-semibold text-accent">À facturer</div>
                        <div class="text-xs text-gray-400 mt-1">Aucune facture émise</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Panneaux --}}
    <div class="bg-[#0f172a] rounded-2xl border border-[#334155] overflow-hidden shadow-xl" x-data="panneauxManager()">
        <div class="px-6 py-4 bg-gradient-to-r from-[#1e293b] to-[#0f172a] border-b border-[#334155] flex justify-between items-center flex-wrap gap-3">
            <h2 class="font-bold text-white text-lg flex items-center gap-2">
                <span class="text-2xl">🪧</span> Panneaux 
                <span class="text-sm text-gray-400 bg-white/10 px-3 py-1 rounded-full">{{ $campaign->panels->count() }}</span>
            </h2>
            @if($can['managePanel'])
            <button type="button" 
                    class="px-4 py-2 text-sm font-semibold bg-gradient-to-r from-accent to-yellow-500 text-white rounded-lg hover:from-accent/90 hover:to-yellow-500/90 transition-all duration-200 shadow-md hover:shadow-lg transform hover:-translate-y-0.5"
                    @click="showAdd = !showAdd; if(showAdd) loadPanels()" 
                    x-text="showAdd ? '✕ Annuler' : '+ Ajouter un panneau'"></button>
            @endif
        </div>

        {{-- Ajout panneaux --}}
        @if($can['managePanel'])
        <div x-show="showAdd" x-collapse class="border-b border-[#334155]">
            <div class="p-5 bg-[#1e293b]/30">
                <form method="POST" action="{{ route('admin.campaigns.panels.add', $campaign) }}">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
                        <div>
                            <label class="text-xs text-gray-400 font-semibold block mb-2">🔍 Recherche</label>
                            <input type="text" x-model="search" @input.debounce.300ms="filterPanels()" placeholder="Référence, nom..." 
                                   class="w-full bg-[#0f172a] border border-[#334155] rounded-lg px-4 py-2.5 text-white text-sm focus:border-accent focus:outline-none transition-all">
                        </div>
                        <div>
                            <label class="text-xs text-gray-400 font-semibold block mb-2">📍 Commune</label>
                            <select x-model="filterCommune" @change="filterPanels()" class="w-full bg-[#0f172a] border border-[#334155] rounded-lg px-4 py-2.5 text-white text-sm focus:border-accent focus:outline-none">
                                <option value="">Toutes</option>
                                @foreach($communes as $c)<option value="{{ $c->name }}">{{ $c->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-gray-400 font-semibold block mb-2">📏 Format</label>
                            <select x-model="filterFormat" @change="filterPanels()" class="w-full bg-[#0f172a] border border-[#334155] rounded-lg px-4 py-2.5 text-white text-sm">
                                <option value="">Tous</option>
                                @foreach($formats as $f)<option value="{{ $f->name }}">{{ $f->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-gray-400 font-semibold block mb-2">💡 Éclairage</label>
                            <select x-model="filterIsLit" @change="filterPanels()" class="w-full bg-[#0f172a] border border-[#334155] rounded-lg px-4 py-2.5 text-white text-sm">
                                <option value="">Tous</option>
                                <option value="1">💡 Éclairé</option>
                                <option value="0">🌙 Non éclairé</option>
                            </select>
                        </div>
                    </div>

                    <div class="border border-[#334155] rounded-xl overflow-hidden max-h-96 overflow-y-auto bg-[#0f172a]" x-ref="scrollContainer">
                        <div x-show="loadingPanels" class="text-center py-12">
                            <div class="inline-block w-6 h-6 border-2 border-accent border-t-transparent rounded-full animate-spin"></div>
                            <div class="text-sm text-gray-400 mt-2">Chargement...</div>
                        </div>
                        <template x-if="!loadingPanels && filteredPanels.length === 0">
                            <div class="text-center py-12 text-gray-400">Aucun panneau libre trouvé</div>
                        </template>
                        <template x-for="p in paginatedPanels" :key="p.id">
                            <label class="flex items-center gap-4 p-4 border-b border-[#334155] last:border-0 cursor-pointer hover:bg-[#1e293b]/50 transition-all duration-200" 
                                   :class="{ 'bg-accent/10 border-l-4 border-l-accent': selectedPanels.includes(p.id) }">
                                <input type="checkbox" :value="p.id" x-model="selectedPanels" name="panel_ids[]" class="w-4 h-4 accent-accent">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-3 flex-wrap">
                                        <span class="font-mono text-sm font-bold text-accent" x-text="p.reference"></span>
                                        <span class="text-white font-medium truncate" x-text="p.name"></span>
                                    </div>
                                    <div class="flex gap-4 text-xs text-gray-400 mt-1">
                                        <span>📍 <span x-text="p.commune || '—'"></span></span>
                                        <span>📏 <span x-text="p.format || '—'"></span></span>
                                        <span x-show="p.is_lit" class="text-accent">💡 Éclairé</span>
                                        <span x-show="!p.is_lit" class="text-gray-500">🌙 Non éclairé</span>
                                        <span x-show="p.monthly_rate" class="text-accent font-semibold" x-text="formatPrice(p.monthly_rate)"></span>
                                    </div>
                                </div>
                            </label>
                        </template>
                    </div>

                    <div x-show="selectedPanels.length > 0" class="mt-5 flex justify-between items-center p-4 bg-accent/10 rounded-xl border border-accent/30">
                        <span class="text-sm text-gray-300"><strong x-text="selectedPanels.length"></strong> panneau(x) sélectionné(s) — <strong class="text-accent text-base" x-text="formatEstimate()"></strong> FCFA</span>
                        <button type="submit" class="bg-gradient-to-r from-accent to-yellow-500 text-white font-bold px-6 py-2.5 rounded-lg text-sm hover:from-accent/90 hover:to-yellow-500/90 transition-all duration-200 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                            ✅ Ajouter
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        {{-- Tableau des panneaux --}}
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-[#1e293b] border-b border-[#334155]">
                    <tr class="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">
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
                    @forelse($campaign->panels as $panel)
                    @php
                        $ps = $panel->status->value;
                        $psColor = match($ps) {
                            'confirme' => ['#10b981','rgba(16,185,129,0.1)','rgba(16,185,129,0.3)'],
                            'option' => ['#f59e0b','rgba(245,158,11,0.1)','rgba(245,158,11,0.3)'],
                            'libre' => ['#6b7280','rgba(107,114,128,0.1)','rgba(107,114,128,0.3)'],
                            'maintenance' => ['#ef4444','rgba(239,68,68,0.1)','rgba(239,68,68,0.3)'],
                            default => ['#6b7280','rgba(107,114,128,0.1)','rgba(107,114,128,0.3)'],
                        };
                    @endphp
                    <tr class="border-b border-[#334155] hover:bg-[#1e293b]/50 transition-all duration-200 group">
                        <td class="px-5 py-4"><span class="font-mono text-sm font-bold text-accent">{{ $panel->reference }}</span></td>
                        <td class="px-5 py-4 text-white font-medium">{{ $panel->name }}</td>
                        <td class="px-5 py-4 text-gray-400">{{ $panel->commune?->name ?? '—' }}</td>
                        <td class="px-5 py-4 text-gray-400">{{ $panel->format?->name ?? '—' }}</td>
                        <td class="px-5 py-4">@if($panel->is_lit)<span class="text-accent">💡 Oui</span>@else<span class="text-gray-500">Non</span>@endif</td>
                        <td class="px-5 py-4 text-right text-gray-300">{{ $panel->monthly_rate ? number_format($panel->monthly_rate, 0, ',', ' ') . ' FCFA' : '—' }}</td>
                        <td class="px-5 py-4 text-right font-semibold text-accent">{{ $panel->monthly_rate ? number_format($panel->monthly_rate * $campaign->durationInMonths(), 0, ',', ' ') . ' FCFA' : '—' }}</td>
                        <td class="px-5 py-4"><span class="inline-block px-3 py-1 rounded-full text-xs font-semibold border shadow-sm" style="background:{{ $psColor[1] }}; color:{{ $psColor[0] }}; border-color:{{ $psColor[2] }}">{{ $panel->status->label() }}</span></td>
                        @if($can['managePanel'])
                        <td class="px-5 py-4"><button type="button" onclick="openRetirePanel({{ $panel->id }}, '{{ addslashes($panel->reference) }}')" class="text-red-400 hover:text-red-300 hover:bg-red-500/20 p-2 rounded-lg transition-all duration-200 group-hover:opacity-100">✕</button></td>
                        @endif
                    </tr>
                    @empty
                    <tr><td colspan="{{ $can['managePanel'] ? 9 : 8 }}" class="text-center py-16 text-gray-500">Aucun panneau lié à cette campagne</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modals --}}
    <div id="modal-delete" class="fixed inset-0 bg-black/80 backdrop-blur-md flex items-center justify-center z-50 hidden" onclick="if(event.target===this) closeModal()">
        <div class="bg-[#0f172a] rounded-2xl border border-[#334155] max-w-md w-full mx-4 overflow-hidden shadow-2xl transform transition-all" onclick="event.stopPropagation()">
            <div class="px-6 py-5 bg-gradient-to-r from-red-600/20 to-red-700/20 border-b border-red-500/30 flex justify-between items-center">
                <h3 class="font-bold text-red-400 text-xl">🗑 Supprimer la campagne</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-white text-2xl transition">&times;</button>
            </div>
            <div class="p-6 text-center">
                <div class="text-6xl mb-4">🗑</div>
                <div class="font-bold text-lg mb-2 text-white">Supprimer <span id="delete-name" class="text-accent"></span> ?</div>
                <div class="text-sm text-gray-400 mb-5">Tous les panneaux liés seront détachés et libérés.</div>
                <div class="bg-red-500/10 border border-red-500/20 rounded-xl p-4 text-sm text-red-400">⚠️ Cette action est irréversible.</div>
            </div>
            <div class="px-6 py-5 border-t border-[#334155] flex justify-end gap-3">
                <button onclick="closeModal()" class="px-5 py-2 rounded-xl border border-[#334155] text-gray-400 hover:bg-[#1e293b] transition-all">Annuler</button>
                <form id="delete-form" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="px-5 py-2 rounded-xl bg-gradient-to-r from-red-600 to-red-700 text-white font-semibold hover:from-red-700 hover:to-red-800 transition-all shadow-md">🗑 Supprimer</button>
                </form>
            </div>
        </div>
    </div>

    <div id="modal-retire" class="fixed inset-0 bg-black/80 backdrop-blur-md flex items-center justify-center z-50 hidden" onclick="if(event.target===this) closeRetireModal()">
        <div class="bg-[#0f172a] rounded-2xl border border-[#334155] max-w-md w-full mx-4 overflow-hidden shadow-2xl" onclick="event.stopPropagation()">
            <div class="px-6 py-5 bg-gradient-to-r from-orange-600/20 to-red-600/20 border-b border-orange-500/30 flex justify-between items-center">
                <h3 class="font-bold text-orange-400 text-xl">✕ Retirer le panneau</h3>
                <button onclick="closeRetireModal()" class="text-gray-400 hover:text-white text-2xl transition">&times;</button>
            </div>
            <div class="p-6 text-center">
                <div class="text-6xl mb-4">🪧</div>
                <div class="font-bold text-lg mb-2 text-white">Retirer <span id="retire-ref" class="text-accent"></span> ?</div>
                <div class="text-sm text-gray-400">Le panneau sera détaché et son statut recalculé. Le montant total sera mis à jour.</div>
            </div>
            <div class="px-6 py-5 border-t border-[#334155] flex justify-end gap-3">
                <button onclick="closeRetireModal()" class="px-5 py-2 rounded-xl border border-[#334155] text-gray-400 hover:bg-[#1e293b] transition-all">Annuler</button>
                <form id="retire-form" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="px-5 py-2 rounded-xl bg-gradient-to-r from-red-600 to-red-700 text-white font-semibold hover:from-red-700 hover:to-red-800 transition-all shadow-md">✕ Retirer</button>
                </form>
            </div>
        </div>
    </div>

    <script>
    const AVAILABLE_PANELS = {!! json_encode($availablePanels->map(fn($p) => [
        'id' => $p->id, 'reference' => $p->reference, 'name' => $p->name,
        'commune' => $p->commune?->name ?? '', 'format' => $p->format?->name ?? '',
        'monthly_rate' => (float)($p->monthly_rate ?? 0), 'is_lit' => (bool)$p->is_lit,
    ])->values()->toArray()) !!};
    const CAMPAIGN_MONTHS = {{ $campaign->durationInMonths() }};

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
            currentPage: 1,
            perPage: 20,
            get paginatedPanels() {
                return this.filteredPanels.slice(0, this.perPage);
            },
            async loadPanels() {
                if (this.allPanels.length > 0) return;
                this.loadingPanels = true;
                try {
                    this.allPanels = AVAILABLE_PANELS;
                    this.filteredPanels = [...this.allPanels];
                } catch (err) { console.error(err); }
                finally { this.loadingPanels = false; }
            },
            filterPanels() {
                const s = this.search.toLowerCase().trim();
                const fc = this.filterCommune.toLowerCase();
                const ff = this.filterFormat.toLowerCase();
                const fl = this.filterIsLit;
                this.filteredPanels = this.allPanels.filter(p => {
                    const ms = !s || p.reference.toLowerCase().includes(s) || p.name.toLowerCase().includes(s) || p.commune.toLowerCase().includes(s);
                    const mc = !fc || p.commune.toLowerCase() === fc;
                    const mf = !ff || p.format.toLowerCase() === ff;
                    const ml = !fl || (fl === '1' ? p.is_lit : !p.is_lit);
                    return ms && mc && mf && ml;
                });
                this.currentPage = 1;
            },
            formatPrice(p) { return Number(p).toLocaleString('fr-FR') + ' FCFA/mois'; },
            formatEstimate() {
                const total = this.selectedPanels.reduce((s, id) => s + ((this.allPanels.find(x => x.id === id)?.monthly_rate || 0) * CAMPAIGN_MONTHS), 0);
                return Math.round(total).toLocaleString('fr-FR');
            },
            updateScroll() {}
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
    function scrollToProlonger() { document.getElementById('section-prolonger')?.scrollIntoView({ behavior: 'smooth' }); setTimeout(() => document.querySelector('#section-prolonger .btn-prolonger')?.click(), 300); }
    </script>
</x-admin-layout>