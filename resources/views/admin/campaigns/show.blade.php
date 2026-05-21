<x-admin-layout title="{{ $campaign->name }}">
    <x-slot:topbarLeft>
        <a href="{{ route('admin.campaigns.index') }}" class="btn btn-ghost btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
            Retour
        </a>
    </x-slot:topbarLeft>

    <x-slot:topbarActions>
        @if($can['updateStatus'] && in_array($campaign->status->value, ['planifie', 'pause']))
            @php
                $panelsCount = $campaign->panels->count() + $campaign->externalPanels->count();
                $isFirstStart = $campaign->status->value === 'planifie';
                $label = $isFirstStart ? '▶ Démarrer la campagne' : '▶ Reprendre';
                $confirmMsg = $isFirstStart
                    ? ($panelsCount > 0
                        ? 'Activer la campagne et envoyer le mail au client ?'
                        : 'Cette campagne n\'a aucun panneau — ajoutez d\'abord des panneaux avant l\'activation.')
                    : 'Reprendre la campagne ?';
            @endphp
            <form method="POST" action="{{ route('admin.campaigns.activate', $campaign) }}"
                  style="display:inline;"
                  onsubmit="return confirm({{ json_encode($confirmMsg) }});">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">{{ $label }}</button>
            </form>
        @endif
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
        $isRunning  = $campaign->status->value === 'actif';
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

    {{-- ── BANDEAU MOTIF D'ANNULATION ── (si campagne annulée) --}}
    @if($campaign->status->value === 'annule' && ($campaign->cancellation_reason || $campaign->cancellation_notes))
        @php
            $reasonLabels = [
                'budget'     => '💰 Budget insuffisant',
                'zone'       => '📍 Zone non pertinente',
                'strategie'  => '🎯 Changement de stratégie',
                'report'     => '📅 Report de campagne',
                'concurrent' => '⚔️ Choix concurrent',
                'autre'      => '📝 Autre motif',
            ];
            $reasonLabel = $reasonLabels[$campaign->cancellation_reason] ?? '📝 ' . ucfirst($campaign->cancellation_reason ?? 'Non précisé');
        @endphp
        <div class="mb-6 rounded-xl border p-4 flex items-start gap-4"
             style="background:rgba(239,68,68,0.06);border-color:rgba(239,68,68,0.25)">
            <div class="w-10 h-10 rounded-full flex items-center justify-center text-2xl flex-shrink-0"
                 style="background:rgba(239,68,68,0.15)">🚫</div>
            <div class="flex-1 min-w-0">
                <div class="font-bold text-sm" style="color:#ef4444">
                    Campagne annulée — {{ $reasonLabel }}
                </div>
                @if($campaign->cancellation_notes)
                    <div class="text-sm mt-1" style="color:var(--text2);white-space:pre-wrap">
                        {{ $campaign->cancellation_notes }}
                    </div>
                @endif
                @if($campaign->updated_at)
                    <div class="text-xs mt-2" style="color:var(--text3)">
                        Annulée le {{ $campaign->updated_at->format('d/m/Y à H:i') }}
                        @if($campaign->updatedBy) · par {{ $campaign->updatedBy->name }}@endif
                    </div>
                @endif
            </div>
        </div>
    @endif

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

                    {{-- Commercial assigné — priorité campaign.commercial_user_id,
                         fallback réservation source puis créateur. --}}
                    @php
                        $com = $campaign->resolveCommercialContact();
                    @endphp
                    @if($com)
                    <div class="rounded-xl p-4 border" style="background:var(--surface2);border-color:var(--border)">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-xs uppercase font-semibold" style="color:var(--text3)">🤝 Commercial</div>
                            @if($can['update'])
                                <a href="{{ route('admin.campaigns.edit', $campaign) }}#commercial"
                                   class="text-xs" style="color:var(--accent);text-decoration:none"
                                   title="Réassigner">✏️</a>
                            @endif
                        </div>
                        <div style="color:var(--text)">{{ $com->name }}</div>
                        @if($com->email)
                            <div class="text-xs mt-1" style="color:var(--text3)">{{ $com->email }}</div>
                        @endif
                        @if($campaign->commercial_user_id === null)
                            <div class="text-[10px] mt-1" style="color:var(--text3);font-style:italic">Hérité du créateur</div>
                        @endif
                    </div>
                    @endif

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

                {{-- ── Opérationnel · barre compacte multi-progression ── --}}
                @php
                    $nbPoses     = \App\Models\PoseTask::where('campaign_id', $campaign->id)->whereNotIn('status', ['annulee'])->count();
                    $nbPosesDone = \App\Models\PoseTask::where('campaign_id', $campaign->id)->where('status', 'realisee')->count();
                    $nbPigesOk   = \App\Models\Pige::where('campaign_id', $campaign->id)->where('status', 'verifie')->count();
                    $nbPigesPend = \App\Models\Pige::where('campaign_id', $campaign->id)->where('status', 'en_attente')->count();
                    $nbInt       = $campaign->panels->count();
                    $nbExt       = $campaign->externalPanels->count();
                    $nbPanels    = $nbInt + $nbExt;
                    $pctPoses    = $nbPoses > 0 ? round(($nbPosesDone / $nbPoses) * 100) : 0;
                    $pctPiges    = $nbPanels > 0 ? round(($nbPigesOk / max($nbPanels, 1)) * 100) : 0;
                @endphp
                <div class="mt-6 pt-6 border-t" style="border-color:var(--border)">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">

                        {{-- Panneaux --}}
                        <a href="{{ route('admin.pose-tasks.index', ['campaign_id' => $campaign->id]) }}"
                           class="group flex items-center gap-3 px-4 py-3 rounded-xl border transition-all"
                           style="background:var(--surface2);border-color:var(--border);text-decoration:none"
                           onmouseover="this.style.borderColor='var(--accent)';this.style.background='var(--surface3)'"
                           onmouseout="this.style.borderColor='var(--border)';this.style.background='var(--surface2)'">
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                                 style="background:rgba(59,130,246,.10);color:#3b82f6;font-size:18px">🪧</div>
                            <div class="flex-1 min-w-0">
                                <div class="text-[10px] font-bold uppercase tracking-wider" style="color:var(--text3)">Panneaux</div>
                                <div class="flex items-baseline gap-1.5">
                                    <span class="text-lg font-bold leading-none" style="color:var(--text)">{{ $nbPanels }}</span>
                                    @if($nbExt > 0)
                                        <span class="text-[10px] font-medium" style="color:var(--text3)">{{ $nbInt }} interne · {{ $nbExt }} externe</span>
                                    @else
                                        <span class="text-[10px] font-medium" style="color:var(--text3)">tous internes</span>
                                    @endif
                                </div>
                            </div>
                        </a>

                        {{-- Poses --}}
                        <a href="{{ route('admin.pose-tasks.index', ['campaign_id' => $campaign->id]) }}"
                           class="group block px-4 py-3 rounded-xl border transition-all"
                           style="background:var(--surface2);border-color:var(--border);text-decoration:none"
                           onmouseover="this.style.borderColor='#f59e0b';this.style.background='var(--surface3)'"
                           onmouseout="this.style.borderColor='var(--border)';this.style.background='var(--surface2)'">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                                     style="background:rgba(245,158,11,.10);color:#f59e0b;font-size:16px">🔧</div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <div class="text-[10px] font-bold uppercase tracking-wider" style="color:var(--text3)">Poses</div>
                                        <div class="text-[11px] font-bold" style="color:{{ $pctPoses === 100 ? '#16a34a' : '#f59e0b' }}">{{ $pctPoses }}%</div>
                                    </div>
                                    <div class="flex items-baseline gap-1.5">
                                        <span class="text-lg font-bold leading-none" style="color:var(--text)">{{ $nbPosesDone }}<span class="text-xs font-medium" style="color:var(--text3)">/{{ $nbPoses }}</span></span>
                                        <span class="text-[10px] font-medium" style="color:var(--text3)">réalisées</span>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2 h-1 rounded-full overflow-hidden" style="background:var(--surface3)">
                                <div class="h-full rounded-full transition-all"
                                     style="background:{{ $pctPoses === 100 ? '#16a34a' : '#f59e0b' }};width:{{ $pctPoses }}%"></div>
                            </div>
                        </a>

                        {{-- Piges --}}
                        <a href="{{ route('admin.piges.index', ['campaign_id' => $campaign->id]) }}"
                           class="group block px-4 py-3 rounded-xl border transition-all"
                           style="background:var(--surface2);border-color:var(--border);text-decoration:none"
                           onmouseover="this.style.borderColor='#a855f7';this.style.background='var(--surface3)'"
                           onmouseout="this.style.borderColor='var(--border)';this.style.background='var(--surface2)'">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                                     style="background:rgba(168,85,247,.10);color:#a855f7;font-size:16px">📸</div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <div class="text-[10px] font-bold uppercase tracking-wider" style="color:var(--text3)">Piges photo</div>
                                        @if($nbPigesPend > 0)
                                            <div class="text-[10px] font-bold px-1.5 py-0.5 rounded-full" style="background:rgba(245,158,11,.15);color:#f59e0b">⏳ {{ $nbPigesPend }}</div>
                                        @endif
                                    </div>
                                    <div class="flex items-baseline gap-1.5">
                                        <span class="text-lg font-bold leading-none" style="color:var(--text)">{{ $nbPigesOk }}</span>
                                        <span class="text-[10px] font-medium" style="color:var(--text3)">validées</span>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2 h-1 rounded-full overflow-hidden" style="background:var(--surface3)">
                                <div class="h-full rounded-full transition-all"
                                     style="background:#a855f7;width:{{ min(100, $pctPiges) }}%"></div>
                            </div>
                        </a>
                    </div>

                    {{-- Contact client — bandeau discret en dessous --}}
                    @if($campaign->client?->email)
                    <a href="mailto:{{ $campaign->client->email }}?subject={{ urlencode('Campagne « ' . $campaign->name . ' »') }}"
                       class="mt-3 flex items-center gap-2 px-3 py-2 rounded-lg border transition-all"
                       style="background:var(--surface);border-color:var(--border);text-decoration:none"
                       onmouseover="this.style.borderColor='var(--accent)'"
                       onmouseout="this.style.borderColor='var(--border)'">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text3);flex-shrink:0"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <span class="text-[11px] font-semibold uppercase tracking-wider" style="color:var(--text3)">Contact</span>
                        <span class="text-xs font-medium flex-1 min-w-0 truncate" style="color:var(--text)">{{ $campaign->client->email }}</span>
                        <span class="text-[10px]" style="color:var(--accent)">Écrire →</span>
                    </a>
                    @endif
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
                                    'pause'   => 'background:#f59e0b;color:#fff',
                                    default   => 'background:var(--surface2);color:var(--text)',
                                };
                                $btnIcon = match($val) {
                                    'termine' => '✅', 'annule' => '🚫', 'actif' => '▶️', 'pose' => '🔧', 'pause' => '⏸', default => '→'
                                };
                            @endphp
                            <form method="POST" action="{{ route('admin.campaigns.update-status', $campaign) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $val }}">
                                <button type="submit"
                                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-bold transition-all"
                                        style="{{ $btnStyle }}"
                                        @if($val === 'annule') onclick="openCancelModal(event, this.closest('form'))" @endif>
                                    {{ $btnIcon }} {{ $label }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                    @else
                        <p class="text-center text-sm py-3" style="color:var(--text3)">Aucune transition disponible</p>
                    @endif

                    {{-- Bouton "Lien pige campagne" retiré — unification workflow.
                         Chaque PoseTask a maintenant son propre lien unique
                         envoyé automatiquement au technicien assigné par
                         WhatsApp. Gestion centralisée dans /admin/pose-tasks.
                         L'ancien token campagne reste en BD pour les liens
                         déjà partagés mais n'est plus généré ni affiché. --}}
                    @if($can['update'] && $campaign->status->value !== 'annule')
                    <div class="mt-5 pt-5 border-t" style="border-color:var(--border)">
                        <a href="{{ route('admin.pose-tasks.index', ['campaign_id' => $campaign->id]) }}"
                           class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-bold"
                           style="background:var(--surface2);color:var(--text);border:1px solid var(--border);text-decoration:none">
                            🔧 Gérer les poses & piges terrain
                        </a>
                        <div class="text-xs mt-2 text-center" style="color:var(--text3)">
                            Une tâche de pose par panneau · lien WhatsApp envoyé
                            au technicien lors de l'assignation.
                        </div>
                    </div>
                    @endif

                    {{-- Prolonger --}}
                    @if($can['update'] && in_array($campaign->status->value, ['actif', 'termine']))
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
                                <a href="{{ route('admin.invoices.show', $inv) }}"
                                   class="flex justify-between items-center py-3 px-4 rounded-xl border transition hover:border-[#e8a020]/60 hover:bg-[#e8a020]/5 group"
                                   style="background:var(--surface2);border-color:var(--border);text-decoration:none;"
                                   title="Ouvrir la facture {{ $inv->reference ?? '#'.$inv->id }}">
                                    <span class="font-mono text-sm group-hover:underline" style="color:var(--accent)">
                                        {{ $inv->reference ?? '#'.$inv->id }}
                                    </span>
                                    <div class="flex items-center gap-3">
                                        <span class="font-bold" style="color:var(--text)">
                                            {{ number_format($inv->amount_ttc, 0, ',', ' ') }} FCFA
                                        </span>
                                        <span style="color:var(--text3);font-size:14px;" class="group-hover:translate-x-0.5 transition-transform">→</span>
                                    </div>
                                </a>
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
                    {{ $campaign->panels->count() + $campaign->externalPanels->count() }}
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

        {{-- Tableau panneaux (avec pagination JS si > 25 panneaux) --}}
        @php $totalPanelsRows = $campaign->panels->count() + $campaign->externalPanels->count(); @endphp
        <div class="overflow-x-auto" id="camp-panels-wrap">
            <table class="w-full" id="camp-panels-table">
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
                @php
                    $billableMonths = $campaign->billableMonths();

                    // ── Statut UNIFORME = statut de la CAMPAGNE ─────────
                    // Décision UX : dans une vue campagne, tous les panneaux
                    // partagent le même statut (celui de la campagne). On
                    // n'affiche PLUS le statut global du panneau (qui peut
                    // diverger s'il est engagé sur plusieurs campagnes).
                    $campStatusValue = is_object($campaign->status)
                        ? $campaign->status->value
                        : $campaign->status;
                    $campBadge = match($campStatusValue) {
                        'planifie' => ['label' => 'Réservé',     'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,.10)',  'border' => 'rgba(59,130,246,.30)'],
                        'actif'    => ['label' => 'En affichage','color' => '#22c55e', 'bg' => 'rgba(34,197,94,.10)',   'border' => 'rgba(34,197,94,.30)'],
                        'pause'    => ['label' => 'En pause',    'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,.10)',  'border' => 'rgba(245,158,11,.30)'],
                        'termine'  => ['label' => 'Terminée',    'color' => '#6b7280', 'bg' => 'rgba(107,114,128,.10)', 'border' => 'rgba(107,114,128,.30)'],
                        'annule'   => ['label' => 'Annulée',     'color' => '#ef4444', 'bg' => 'rgba(239,68,68,.10)',   'border' => 'rgba(239,68,68,.30)'],
                        default    => ['label' => ucfirst((string) $campStatusValue), 'color' => '#6b7280', 'bg' => 'rgba(107,114,128,.10)', 'border' => 'rgba(107,114,128,.30)'],
                    };

                    // ── Préchargement des panel_start_date différés ─────
                    // Lit reservation_panels.panel_start_date pour la résa
                    // parente de cette campagne. Permet d'afficher un badge
                    // "Rejoint le DD/MM" sur les panneaux à démarrage différé.
                    $deferredStartByPanel = [];
                    if ($campaign->reservation_id) {
                        $rows = \Illuminate\Support\Facades\DB::table('reservation_panels')
                            ->where('reservation_id', $campaign->reservation_id)
                            ->whereNotNull('panel_start_date')
                            ->where('panel_start_date', '>', $campaign->start_date)
                            ->get(['panel_id', 'external_panel_id', 'panel_start_date', 'source']);
                        foreach ($rows as $r) {
                            $key = $r->source === 'externe'
                                ? 'ext_' . $r->external_panel_id
                                : 'int_' . $r->panel_id;
                            $deferredStartByPanel[$key] = $r->panel_start_date;
                        }
                    }
                @endphp
                @forelse($campaign->panels as $panel)
                    @php
                        $rate = (float) ($panel->monthly_rate ?? 0);
                        $deferKey = 'int_' . $panel->id;
                        $deferredStart = $deferredStartByPanel[$deferKey] ?? null;
                    @endphp
                    <tr class="border-b transition-all group" data-panel-row style="border-color:var(--border)"
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
                            <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-start;">
                                <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold border"
                                      style="background:{{ $campBadge['bg'] }};color:{{ $campBadge['color'] }};border-color:{{ $campBadge['border'] }}">
                                    {{ $campBadge['label'] }}
                                </span>
                                @if($deferredStart)
                                    <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:12px;background:rgba(245,158,11,.10);color:#d97706;border:1px solid rgba(245,158,11,.25);font-size:10px;font-weight:700;letter-spacing:.2px"
                                          title="Ce panneau rejoint la campagne plus tard car il était occupé.">
                                        📅 Rejoint le {{ \Carbon\Carbon::parse($deferredStart)->format('d/m/Y') }}
                                    </span>
                                @endif
                            </div>
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
                @endforelse

                {{-- Panneaux externes (régies partenaires) — gérés en lecture
                     seule dans cet écran : ajout/retrait passe par la fiche
                     réservation pour préserver le verrou anti-double-booking. --}}
                @foreach($campaign->externalPanels as $panel)
                    @php
                        $rate = (float) ($panel->monthly_rate ?? 0);
                        $deferKey = 'ext_' . $panel->id;
                        $deferredStart = $deferredStartByPanel[$deferKey] ?? null;
                    @endphp
                    <tr class="border-b transition-all group" data-panel-row style="border-color:var(--border);background:rgba(124,58,237,0.025)"
                        onmouseover="this.style.background='rgba(124,58,237,0.06)'"
                        onmouseout="this.style.background='rgba(124,58,237,0.025)'">
                        <td class="px-5 py-4">
                            <span class="font-mono text-sm font-bold" style="color:#7c3aed">{{ $panel->code_panneau }}</span>
                            <span class="ml-2 text-[10px] font-semibold px-2 py-0.5 rounded-full" style="background:rgba(124,58,237,.12);color:#7c3aed">
                                Régie {{ $panel->agency?->name ?? 'partenaire' }}
                            </span>
                        </td>
                        <td class="px-5 py-4 font-medium" style="color:var(--text)">{{ $panel->designation }}</td>
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
                            <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-start;">
                                <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold border"
                                      style="background:{{ $campBadge['bg'] }};color:{{ $campBadge['color'] }};border-color:{{ $campBadge['border'] }}">
                                    {{ $campBadge['label'] }}
                                </span>
                                @if($deferredStart)
                                    <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:12px;background:rgba(245,158,11,.10);color:#d97706;border:1px solid rgba(245,158,11,.25);font-size:10px;font-weight:700;letter-spacing:.2px"
                                          title="Ce panneau rejoint la campagne plus tard car il était occupé.">
                                        📅 Rejoint le {{ \Carbon\Carbon::parse($deferredStart)->format('d/m/Y') }}
                                    </span>
                                @endif
                            </div>
                        </td>
                        @if($can['managePanel'])<td class="px-5 py-4"></td>@endif
                    </tr>
                @endforeach

                @if($campaign->panels->isEmpty() && $campaign->externalPanels->isEmpty())
                    <tr>
                        <td colspan="{{ $can['managePanel'] ? 9 : 8 }}" class="text-center py-16" style="color:var(--text3)">
                            Aucun panneau lié à cette campagne
                        </td>
                    </tr>
                @endif
                </tbody>
            </table>

            {{-- Pagination JS — au-delà de 25 panneaux pour éviter
                 un scroll interminable sur les grosses campagnes.
                 Identique au pattern de la fiche réservation. --}}
            @if($totalPanelsRows > 25)
            <div id="camp-panels-pager"
                 style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;padding:14px 18px;font-size:12px;color:var(--text2);border-top:1px solid var(--border)">
                <div id="camp-pager-info">— sur {{ $totalPanelsRows }} panneaux</div>
                <div id="camp-pager-controls" style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;"></div>
            </div>
            <script>
            (function() {
                const PAGE_SIZE = 25;
                const total = {{ $totalPanelsRows }};
                const pages = Math.ceil(total / PAGE_SIZE);
                const rows  = Array.from(document.querySelectorAll('#camp-panels-table tbody tr[data-panel-row]'));
                const info  = document.getElementById('camp-pager-info');
                const ctrl  = document.getElementById('camp-pager-controls');
                let current = 1;

                function render() {
                    rows.forEach((tr, i) => {
                        const page = Math.floor(i / PAGE_SIZE) + 1;
                        tr.style.display = (page === current) ? '' : 'none';
                    });
                    const from = (current - 1) * PAGE_SIZE + 1;
                    const to   = Math.min(current * PAGE_SIZE, total);
                    info.textContent = `${from}–${to} sur ${total} panneaux`;
                    renderControls();
                }
                function btn(label, target, disabled, active) {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.textContent = label;
                    b.disabled = !!disabled;
                    Object.assign(b.style, {
                        padding: '5px 11px', borderRadius: '6px', fontSize: '12px', fontWeight: '600',
                        border: '1px solid var(--border)',
                        background: active ? 'var(--accent)' : 'var(--surface)',
                        color: active ? '#fff' : 'var(--text2)',
                        cursor: disabled ? 'not-allowed' : 'pointer',
                        opacity: disabled ? '.4' : '1', minWidth: '32px',
                    });
                    if (!disabled && !active) b.addEventListener('click', () => { current = target; render(); window.scrollTo({ top: document.getElementById('camp-panels-wrap').offsetTop - 80, behavior: 'smooth' }); });
                    return b;
                }
                function renderControls() {
                    ctrl.innerHTML = '';
                    ctrl.appendChild(btn('‹ Précédent', current - 1, current === 1, false));
                    const visible = new Set([1, pages, current, current - 1, current + 1]);
                    let last = 0;
                    for (let p = 1; p <= pages; p++) {
                        if (!visible.has(p)) continue;
                        if (p - last > 1) {
                            const dots = document.createElement('span');
                            dots.textContent = '…'; dots.style.padding = '0 4px'; dots.style.color = 'var(--text3)';
                            ctrl.appendChild(dots);
                        }
                        ctrl.appendChild(btn(String(p), p, false, p === current));
                        last = p;
                    }
                    ctrl.appendChild(btn('Suivant ›', current + 1, current === pages, false));
                }
                render();
            })();
            </script>
            @endif
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
            campaignMonths: {{ $campaign->billableMonths() }},

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

    // ══ MODAL MOTIF D'ANNULATION ══
    let _cancelForm = null;

    function openCancelModal(e, form) {
        e.preventDefault();
        _cancelForm = form;
        // Réinitialiser le modal
        document.querySelectorAll('input[name="cancel_reason"]').forEach(r => r.checked = false);
        document.getElementById('cancel-notes').value = '';
        document.getElementById('cancel-notes-group').style.display = 'none';
        document.getElementById('modal-cancel').style.display = 'flex';
    }

    function closeCancelModal() {
        document.getElementById('modal-cancel').style.display = 'none';
        _cancelForm = null;
    }

    function onCancelReasonChange(val) {
        document.getElementById('cancel-notes-group').style.display = val === 'autre' ? 'block' : 'none';
    }

    function confirmCancel() {
        const reason = document.querySelector('input[name="cancel_reason"]:checked')?.value || '';
        const notes  = document.getElementById('cancel-notes').value.trim();
        if (!reason) { alert('Veuillez sélectionner un motif.'); return; }

        const form = _cancelForm;
        const addHidden = (name, value) => {
            let inp = form.querySelector(`input[name="${name}"]`);
            if (!inp) { inp = document.createElement('input'); inp.type = 'hidden'; inp.name = name; form.appendChild(inp); }
            inp.value = value;
        };
        addHidden('cancellation_reason', reason);
        addHidden('cancellation_notes', notes);
        closeCancelModal();
        form.submit();
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { closeCancelModal(); }
    });
    </script>

    {{-- ══ MODAL MOTIF D'ANNULATION ══ --}}
    <div id="modal-cancel"
         style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:var(--surface);border-radius:20px;border:1px solid var(--border);max-width:480px;width:90%;overflow:hidden;">
            <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
                <div style="font-size:18px;font-weight:600;color:var(--text)">🚫 Motif d'annulation</div>
                <button onclick="closeCancelModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text3)">✕</button>
            </div>
            <div style="padding:20px;display:grid;gap:12px;">
                <p style="font-size:13px;color:var(--text2);margin:0;">Sélectionnez le motif d'annulation de cette campagne :</p>
                @foreach([
                    'budget'     => 'Budget insuffisant',
                    'zone'       => 'Zone non pertinente',
                    'strategie'  => 'Changement de stratégie',
                    'report'     => 'Report de campagne',
                    'concurrent' => 'Choix concurrent',
                    'autre'      => 'Autre',
                ] as $key => $libelle)
                <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;border:1px solid var(--border);cursor:pointer;background:var(--surface2);">
                    <input type="radio" name="cancel_reason" value="{{ $key }}" onchange="onCancelReasonChange('{{ $key }}')"
                           style="width:16px;height:16px;accent-color:var(--accent);">
                    <span style="font-size:13px;font-weight:500;color:var(--text)">{{ $libelle }}</span>
                </label>
                @endforeach
                <div id="cancel-notes-group" style="display:none;margin-top:4px;">
                    <label style="font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text3);display:block;margin-bottom:6px;">Précision (optionnel)</label>
                    <textarea id="cancel-notes" rows="2"
                              style="width:100%;padding:10px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;color:var(--text);resize:vertical;box-sizing:border-box;"
                              placeholder="Détails supplémentaires…"></textarea>
                </div>
            </div>
            <div style="padding:12px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:12px;">
                <button onclick="closeCancelModal()"
                        style="padding:8px 16px;border-radius:10px;font-size:13px;font-weight:500;cursor:pointer;background:transparent;border:1px solid var(--border);color:var(--text2)">
                    Retour
                </button>
                <button onclick="confirmCancel()"
                        style="padding:8px 16px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;background:#ef4444;border:none;color:#fff">
                    🚫 Confirmer l'annulation
                </button>
            </div>
        </div>
    </div>
</x-admin-layout>
