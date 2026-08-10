<x-admin-layout title="{{ $campaign->name }}">
    <x-slot:topbarLeft>
        @include('admin.partials._smart_back')
        @if(!request()->query('back'))
            <a href="{{ route('admin.campaigns.index') }}" class="btn btn-ghost btn-sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
                Retour
            </a>
        @endif
    </x-slot:topbarLeft>

    <x-slot:topbarActions>
        @if($can['updateStatus'] && in_array($campaign->status->value, ['planifie', 'pause']))
            @php
                $panelsCount = $campaign->panels->count() + $campaign->externalPanels->count();
                $isFirstStart = $campaign->status->value === 'planifie';
                // ⚠ Garde anti-démarrage prématuré : si la campagne est
                // planifiée pour une date future, le service refusera.
                // Côté UI on désactive le bouton + on explique pourquoi
                // dans le tooltip (cohérent avec le message backend).
                $tooEarly = $isFirstStart
                    && $campaign->start_date
                    && $campaign->start_date->copy()->startOfDay()->isFuture();
                $label = $isFirstStart ? '▶ Démarrer la campagne' : '▶ Reprendre';
                $confirmMsg = $isFirstStart
                    ? ($panelsCount > 0
                        ? 'Activer la campagne et envoyer le mail au client ?'
                        : 'Cette campagne n\'a aucun panneau — ajoutez d\'abord des panneaux avant l\'activation.')
                    : 'Reprendre la campagne ?';
            @endphp
            @if($tooEarly)
                @php
                    $daysUntil = (int) now()->startOfDay()->diffInDays($campaign->start_date->copy()->startOfDay());
                @endphp
                <button type="button" class="btn btn-ghost btn-sm"
                        disabled
                        style="opacity:.55;cursor:not-allowed"
                        title="Démarrage automatique le {{ $campaign->start_date->format('d/m/Y') }} (dans {{ $daysUntil }} jour{{ $daysUntil > 1 ? 's' : '' }}). Pour démarrer plus tôt, modifie la date de début.">
                    📅 Démarrage le {{ $campaign->start_date->format('d/m') }} · J-{{ $daysUntil }}
                </button>
            @else
                <form method="POST" action="{{ route('admin.campaigns.activate', $campaign) }}"
                      style="display:inline;"
                      onsubmit="return confirm({{ json_encode($confirmMsg) }});">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm keep-label" title="{{ $label }}">{{ $label }}</button>
                </form>
            @endif
        @endif
        {{-- 2026-06-22 / 2026-06-25 — Fiche de pose PDF : 2 modes au choix
             - mode=cards : portrait, 1 panneau par carte avec photo
             - mode=list  : paysage, tableau compact sans photos --}}
        @if(\Illuminate\Support\Facades\Route::has('admin.campaigns.fiche-pose.pdf'))
            <a href="{{ route('admin.campaigns.fiche-pose.pdf', ['campaign' => $campaign, 'mode' => 'cards']) }}"
               class="btn btn-ghost btn-sm"
               style="background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.25);color:#b91c1c;font-weight:700"
               title="PDF détaillé avec photos des panneaux — 1 panneau par carte (A4 portrait)">
                📄 <span class="btn-label">PDF avec photos</span>
            </a>
            <a href="{{ route('admin.campaigns.fiche-pose.pdf', ['campaign' => $campaign, 'mode' => 'list']) }}"
               class="btn btn-ghost btn-sm action-icon-only-mobile"
               style="background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.25);color:#1d4ed8;font-weight:700"
               title="PDF compact en liste — tableau A4 paysage (référence, emplacement, commune, format, technicien, équipe)">
                📋 <span class="btn-label">PDF en liste</span>
            </a>
        @endif
        @php $isTermineeTop = $campaign->status->value === 'termine'; @endphp
        @if($can['update'] && !$isTermineeTop)
            <a href="{{ route('admin.campaigns.edit', $campaign) }}" class="btn btn-ghost btn-sm" title="Modifier la campagne">✏️ <span class="btn-label">Modifier</span></a>
        @elseif($can['managePanel'])
            {{-- Campagne terminée : édition complète bloquée (submit refusé par
                 update()), mais on permet de corriger le nom via un modal ciblé
                 (saisie d'historique). Affiché par STATUT, pas par permission,
                 car pour un admin $can['update'] reste vrai (bypass before()). --}}
            <button type="button" onclick="openRenameModal()" class="btn btn-ghost btn-sm" title="Renommer la campagne">✏️ <span class="btn-label">Renommer</span></button>
        @endif
        @if($can['delete'])
            <button type="button"
                    onclick="openDeleteModal({{ $campaign->id }}, @js($campaign->name))"
                    title="Supprimer la campagne"
                    class="btn btn-ghost btn-sm text-red-400 border-red-500/30 hover:bg-red-500/20 action-icon-only-mobile">
                🗑 <span class="btn-label">Supprimer</span>
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

        // ── M3 SLA enrichi — bandeau "Retard détecté" (Précision A) ──
        // (a) Signalements problem_reported ouverts (isResolved étendu — Précision B)
        // (b) Poses planifiées avec scheduled_at < now() ET sans signalement actif
        // (c) Les deux à la fois → 2 bandeaux empilés, retard pose au-dessus
        $slaOpenSignals  = $campaign->openProblemSignals();
        $slaOverduePoses = $campaign->overduePoseTasks();
        $slaDominant     = $campaign->dominantMotif();
    @endphp

    {{-- ── M3 SLA enrichi : Bandeaux retard détecté (cas a / b / c) ── --}}
    @if($slaOverduePoses->isNotEmpty())
        {{-- Cas b : pose non démarrée — plus grave, donc en haut --}}
        <div style="background:linear-gradient(90deg,rgba(220,38,38,.12),rgba(220,38,38,.04));border:1px solid rgba(220,38,38,.35);border-left:5px solid #dc2626;border-radius:12px;padding:14px 18px;margin-bottom:12px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
            <div style="font-size:28px;line-height:1">⚠</div>
            <div style="flex:1;min-width:240px">
                <div style="font-size:14px;font-weight:800;color:#b91c1c">Pose en retard — {{ $slaOverduePoses->count() }} pose{{ $slaOverduePoses->count() > 1 ? 's' : '' }} non démarrée{{ $slaOverduePoses->count() > 1 ? 's' : '' }}</div>
                <div style="font-size:12px;color:#7f1d1d;margin-top:3px">
                    @foreach($slaOverduePoses->take(3) as $pt)
                        <span style="font-family:monospace;font-weight:700">{{ $pt->panel?->reference }}</span> · planifié le {{ $pt->scheduled_at?->format('d/m/Y') }} (J+{{ (int)$pt->scheduled_at?->diffInDays(now()) }}){{ !$loop->last ? ' · ' : '' }}
                    @endforeach
                    @if($slaOverduePoses->count() > 3)
                        … et {{ $slaOverduePoses->count() - 3 }} autre{{ $slaOverduePoses->count() - 3 > 1 ? 's' : '' }}
                    @endif
                </div>
            </div>
            @if(in_array(auth()->user()?->role?->value, ['admin','mediaplanner'], true))
                <a href="{{ route('admin.campaigns.poses', $campaign) }}"
                   class="btn btn-sm" style="background:#dc2626;color:#fff;border:none;font-weight:700">Voir les poses →</a>
            @endif
        </div>
    @endif

    @if($slaOpenSignals->isNotEmpty())
        {{-- Cas a : signalement actif — bandeau orange --}}
        <div style="background:linear-gradient(90deg,rgba(245,158,11,.12),rgba(245,158,11,.04));border:1px solid rgba(245,158,11,.35);border-left:5px solid #f59e0b;border-radius:12px;padding:14px 18px;margin-bottom:14px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
            <div style="font-size:28px;line-height:1">{{ $slaDominant?->icon() ?? '⚠' }}</div>
            <div style="flex:1;min-width:240px">
                <div style="font-size:14px;font-weight:800;color:#b45309">
                    Signalement actif —
                    @if($slaDominant)
                        Motif&nbsp;: {{ $slaDominant->label() }}
                    @else
                        {{ $slaOpenSignals->count() }} ouvert{{ $slaOpenSignals->count() > 1 ? 's' : '' }}
                    @endif
                    <span style="font-weight:600;color:#92400e">({{ $slaOpenSignals->count() }} ouvert{{ $slaOpenSignals->count() > 1 ? 's' : '' }})</span>
                </div>
                <div style="font-size:12px;color:#78350f;margin-top:3px">
                    @foreach($slaOpenSignals->take(3) as $sig)
                        <span style="font-family:monospace;font-weight:700">{{ $sig->task?->panel?->reference }}</span>{{ !$loop->last ? ' · ' : '' }}
                    @endforeach
                    @if($slaOpenSignals->count() > 3)
                        … et {{ $slaOpenSignals->count() - 3 }} autre{{ $slaOpenSignals->count() - 3 > 1 ? 's' : '' }}
                    @endif
                </div>
            </div>
            @if(in_array(auth()->user()?->role?->value, ['admin','mediaplanner'], true))
                <a href="{{ route('admin.sla.retards.index', ['client_id' => $campaign->client_id]) }}"
                   class="btn btn-sm" style="background:#f59e0b;color:#fff;border:none;font-weight:700">Voir tous les signalements →</a>
            @endif
        </div>
    @endif

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

    {{-- ── BANDEAU "Campagne en préparation" — avant 1er lancement ── --}}
    @php
        $totalPanelsCount = $campaign->panels->count() + $campaign->externalPanels->count();
        $isPreLaunch      = $campaign->status->value === 'planifie' && $totalPanelsCount === 0;
    @endphp
    @if($isPreLaunch)
        <div class="mb-6 rounded-xl border p-4 flex items-start gap-4"
             style="background:rgba(245,158,11,0.06);border-color:rgba(245,158,11,0.3)">
            <div class="w-10 h-10 rounded-full flex items-center justify-center text-xl flex-shrink-0"
                 style="background:rgba(245,158,11,0.18)">📋</div>
            <div class="flex-1 min-w-0">
                <div class="font-bold text-sm" style="color:#d97706">
                    Cette campagne est en préparation
                </div>
                <div class="text-sm mt-1" style="color:var(--text2)">
                    Ajoutez au moins <strong>1 panneau</strong> + ajustez les prix négociés si besoin, puis cliquez sur
                    <strong>« ▶ Démarrer la campagne »</strong> pour la lancer. Le mail au client (avec les panneaux
                    réels et le montant exact) ne partira qu'à ce moment.
                </div>
            </div>
        </div>
    @endif

    {{-- ── BANDEAU COHÉRENCE FACTURATION ──
         Reste visible tant que le montant facturé ne correspond pas au
         montant calculé pour la période réelle de la campagne. Pas de
         recalcul automatique — l'admin décide (ajuster les dates de la
         résa, modifier les prix négociés, ou conserver l'écart en
         connaissance de cause). --}}
    @if(!empty($amountConsistency) && !$amountConsistency['matches'])
        @php
            $ac      = $amountConsistency;
            $diffAbs = number_format(abs($ac['diff']), 0, ',', ' ');
            $expFmt  = number_format($ac['expected'], 0, ',', ' ');
            $stoFmt  = number_format($ac['stored'],   0, ',', ' ');
            $sign    = $ac['diff'] > 0 ? '+' : '−';
            $startH  = \Carbon\Carbon::parse($ac['period']['start'])->format('d/m/Y');
            $endH    = \Carbon\Carbon::parse($ac['period']['end'])->format('d/m/Y');
        @endphp
        <div class="mb-6 rounded-xl border p-4 flex items-start gap-4"
             style="background:rgba(245,158,11,0.08);border-color:rgba(245,158,11,0.35)">
            <div class="w-10 h-10 rounded-full flex items-center justify-center text-xl flex-shrink-0"
                 style="background:rgba(245,158,11,0.18)">💰</div>
            <div class="flex-1 min-w-0">
                <div class="font-bold text-sm" style="color:#d97706">
                    Écart de facturation détecté sur la période {{ $startH }} → {{ $endH }}
                </div>
                <div class="text-sm mt-1.5" style="color:var(--text2);line-height:1.55">
                    Montant actuellement facturé : <strong>{{ $stoFmt }} FCFA</strong>.
                    Montant attendu pour cette période : <strong style="color:#d97706">{{ $expFmt }} FCFA</strong>
                    <span style="opacity:.85">({{ $sign }}{{ $diffAbs }} FCFA)</span>.
                </div>
                <div class="text-xs mt-2" style="color:var(--text3);line-height:1.5">
                    @if($ac['source'] === 'reservation')
                        Source : prix négociés figés sur la réservation liée
                        @if($campaign->reservation)
                            <a href="{{ route('admin.reservations.show', $campaign->reservation) }}"
                               style="color:var(--accent);text-decoration:underline">{{ $campaign->reservation->reference }}</a>.
                        @endif
                        Pour réaligner : ajustez la période de la réservation (puis le total sera recalculé sur le pivot)
                        ou conservez l'écart si la campagne couvre intentionnellement plus que la facturation.
                    @else
                        Source : tarif catalogue × durée campagne. Ajustez les prix négociés des panneaux
                        ou la durée de la campagne pour réaligner.
                    @endif
                    @if($ac['overridden'])
                        <br><strong style="color:#d97706">⚙ Montant manuel en vigueur</strong> — il ne sera pas écrasé automatiquement.
                    @endif
                </div>
            </div>
        </div>
    @endif

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

                    {{-- Montant total — éditable inline si l'utilisateur peut update --}}
                    <div class="rounded-xl p-4 border" style="background:var(--surface2);border-color:var(--border)"
                         data-total-cell
                         data-total-amount="{{ (float) $campaign->total_amount }}"
                         data-update-url="{{ route('admin.campaigns.total', $campaign) }}"
                         data-can-edit="{{ $can['update'] && !in_array($campaign->status->value, ['termine', 'annule']) ? '1' : '0' }}">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-xs uppercase font-semibold" style="color:var(--text3)">💰 Montant total <span style="font-size:9px;color:var(--text3);font-weight:500">(HT)</span></div>
                            @if($can['update'] && !in_array($campaign->status->value, ['termine', 'annule']))
                                <span data-total-edit-btn
                                      title="Modifier le montant total HT (override)"
                                      style="cursor:pointer;font-size:11px;color:var(--accent);font-weight:600">✏️ Ajuster</span>
                            @endif
                        </div>
                        <div class="text-2xl font-bold" style="color:var(--accent)">
                            <span data-campaign-total>{{ number_format($campaign->total_amount, 0, ',', ' ') }}</span>
                            <span class="text-xs font-normal" style="color:var(--text3)">FCFA HT</span>
                        </div>
                        @php
                            $isOverridden = $campaign->isTotalAmountOverridden();
                            $overrideBy = $isOverridden ? $campaign->totalAmountOverriddenBy : null;
                        @endphp
                        <div data-override-badge
                             style="{{ $isOverridden ? '' : 'display:none;' }}margin-top:6px;display:{{ $isOverridden ? 'inline-flex' : 'none' }};align-items:center;gap:5px;font-size:10px;font-weight:600;padding:3px 8px;border-radius:999px;background:rgba(232,160,32,0.12);color:var(--accent);border:1px solid rgba(232,160,32,0.3);">
                            <span>💡 Négocié</span>
                            @if($isOverridden)
                                <span data-override-by style="color:var(--text2);font-weight:500">par {{ $overrideBy?->name ?? '—' }} · {{ $campaign->total_amount_overridden_at?->format('d/m/Y H:i') }}</span>
                            @else
                                <span data-override-by style="color:var(--text2);font-weight:500"></span>
                            @endif
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
                        <div style="color:var(--text)">
                            @if($campaign->user)
                                <a href="{{ route('admin.campaigns.index', ['commercial_user_id' => $campaign->user->id]) }}"
                                   style="color:var(--text);text-decoration:none;border-bottom:1px dashed var(--border)"
                                   title="Voir les campagnes de {{ $campaign->user->name }}">{{ $campaign->user->name }}</a>
                            @else
                                —
                            @endif
                        </div>
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
                        <div style="color:var(--text)">
                            <a href="{{ route('admin.campaigns.index', ['commercial_user_id' => $com->id]) }}"
                               style="color:var(--text);text-decoration:none;border-bottom:1px dashed var(--border)"
                               title="Voir toutes les campagnes de {{ $com->name }}">{{ $com->name }}</a>
                        </div>
                        @if($com->email)
                            <div class="text-xs mt-1">
                                <a href="mailto:{{ $com->email }}"
                                   style="color:var(--text3);text-decoration:none;border-bottom:1px dotted var(--border)"
                                   title="Envoyer un email">{{ $com->email }}</a>
                            </div>
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

                {{-- ── Facturation — intégrée dans la fiche Informations
                     (l'ancien bloc 4 mini-stats Panneaux/Poses/Piges/Contact
                     est désormais en Quick Actions, sous Informations + Actions). ── --}}
                <div class="mt-6 pt-6 border-t" style="border-color:var(--border)">
                    <div class="text-xs uppercase font-semibold mb-3 flex items-center justify-between gap-2" style="color:var(--text3)">
                        <div class="flex items-center gap-2">
                            💰 Facturation
                            @if($campaign->invoices->isNotEmpty())
                                <span class="text-[10px] px-2 py-0.5 rounded-full" style="background:var(--surface3);color:var(--text3)">{{ $campaign->invoices->count() }} facture{{ $campaign->invoices->count() > 1 ? 's' : '' }}</span>
                            @endif
                        </div>
                        @if($campaign->invoices->isNotEmpty())
                            {{-- Lien vers la liste de suivi paiements filtrée sur cette
                                 campagne — utile pour voir Total/Payé/Reste/échéances
                                 de toutes les factures d'un coup. --}}
                            <a href="{{ route('admin.invoices.index', ['campaign_id' => $campaign->id]) }}"
                               class="text-[10px] font-semibold"
                               style="color:var(--accent);text-decoration:none"
                               title="Voir le suivi paiements filtré sur cette campagne">
                                Suivi paiements →
                            </a>
                        @endif
                    </div>
                    @php
                        // Montant attendu vs déjà facturé — pour suggérer le
                        // bon montant lors de l'émission d'une facture.
                        $expectedHt = $campaign->computedAmountHt();
                        $billedHt   = $campaign->alreadyBilledHt();
                        $remainHt   = max(0.0, $expectedHt - $billedHt);
                        $isAdmin    = auth()->user()?->role?->value === 'admin';
                        // 2026-07-21 : le comptable a le même droit que l'admin
                        // sur la facturation depuis la campagne (générer FNE,
                        // facturer manuellement, facture complémentaire).
                        $canBill    = $isAdmin || auth()->user()?->role?->value === 'comptable';
                    @endphp

                    @if($campaign->invoices->isNotEmpty())
                        {{-- Récap HT/TTC en tête : les chiffres affichés ici sont
                             en HT pour être comparables au "Montant total HT" de
                             gauche. Le TTC est rappelé en sous-info pour la
                             cohérence avec la liste des factures. --}}
                        {{-- data-billing-{expected,billed,remain} : ces nœuds
                             sont rafraîchis en live par le JS après modif d'un
                             prix panneau (sans reload). data-billing-billed
                             reste constant (les factures émises ne bougent
                             pas), il sert juste à recalculer le Reste. --}}
                        <div class="mb-3 p-3 rounded-xl" style="background:var(--surface2);border:1px solid var(--border)">
                            <div class="grid grid-cols-3 gap-2 text-center">
                                <div>
                                    <div class="text-[10px] uppercase font-semibold" style="color:var(--text3)">Total campagne</div>
                                    <div class="text-sm font-bold" style="color:var(--text)"><span data-billing-expected>{{ number_format($expectedHt, 0, ',', ' ') }}</span> <span class="text-[10px]" style="color:var(--text3)">HT</span></div>
                                </div>
                                <div>
                                    <div class="text-[10px] uppercase font-semibold" style="color:var(--text3)">Déjà facturé</div>
                                    <div class="text-sm font-bold" style="color:#3aa835"><span data-billing-billed="{{ (float) $billedHt }}">{{ number_format($billedHt, 0, ',', ' ') }}</span> <span class="text-[10px]" style="color:var(--text3)">HT</span></div>
                                </div>
                                <div>
                                    <div class="text-[10px] uppercase font-semibold" style="color:var(--text3)">Reste</div>
                                    <div class="text-sm font-bold" data-billing-remain-color style="color:{{ $remainHt > 0 ? '#f97316' : 'var(--text3)' }}"><span data-billing-remain>{{ number_format($remainHt, 0, ',', ' ') }}</span> <span class="text-[10px]" style="color:var(--text3)">HT</span></div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach($campaign->invoices as $inv)
                                @php
                                    $stCfg = match($inv->status) {
                                        'payee'     => ['c'=>'#16a34a','bg'=>'rgba(34,197,94,.10)','l'=>'Payée'],
                                        'envoyee'   => ['c'=>'#3b82f6','bg'=>'rgba(59,130,246,.10)','l'=>'Envoyée'],
                                        'brouillon' => ['c'=>'#6b7280','bg'=>'rgba(107,114,128,.10)','l'=>'Brouillon'],
                                        'annulee'   => ['c'=>'#ef4444','bg'=>'rgba(239,68,68,.10)','l'=>'Annulée'],
                                        default     => ['c'=>'var(--text3)','bg'=>'var(--surface3)','l'=>$inv->status],
                                    };
                                @endphp
                                <a href="{{ route('admin.invoices.show', $inv) }}"
                                   class="flex justify-between items-center py-3 px-4 rounded-xl border transition group"
                                   style="background:var(--surface2);border-color:var(--border);text-decoration:none"
                                   onmouseover="this.style.borderColor='var(--accent)'"
                                   onmouseout="this.style.borderColor='var(--border)'"
                                   title="Ouvrir la facture {{ $inv->reference ?? '#'.$inv->id }} — HT {{ number_format($inv->amount, 0, ',', ' ') }} / TTC {{ number_format($inv->amount_ttc, 0, ',', ' ') }} FCFA">
                                    <div class="flex flex-col gap-1">
                                        <span class="font-mono text-sm group-hover:underline" style="color:var(--accent)">
                                            {{ $inv->reference ?? '#'.$inv->id }}
                                        </span>
                                        <span style="font-size:10px;font-weight:700;padding:1px 6px;border-radius:999px;background:{{ $stCfg['bg'] }};color:{{ $stCfg['c'] }};align-self:flex-start">
                                            {{ $stCfg['l'] }}
                                        </span>
                                    </div>
                                    <div class="flex flex-col items-end">
                                        <span class="font-bold text-sm" style="color:var(--text)">
                                            {{ number_format($inv->amount, 0, ',', ' ') }} <span style="font-size:10px;color:var(--text3)">HT</span>
                                        </span>
                                        <span style="font-size:11px;color:var(--text3)">
                                            {{ number_format($inv->amount_ttc, 0, ',', ' ') }} FCFA TTC
                                        </span>
                                    </div>
                                </a>
                            @endforeach
                        </div>

                        @if($canBill && $remainHt > 0)
                            {{-- Reste à facturer connu → bouton complémentaire. --}}
                            <div class="mt-3 flex items-center justify-between gap-3 p-3 rounded-xl border border-dashed"
                                 data-billing-remain-row
                                 style="border-color:var(--border);background:rgba(58,168,53,.04)">
                                <div class="text-xs" style="color:var(--text2)">
                                    Reste à facturer :
                                    <strong style="color:var(--accent)"><span data-billing-remain>{{ number_format($remainHt, 0, ',', ' ') }}</span> FCFA HT</strong>
                                </div>
                                <a href="{{ route('admin.invoices.create', ['campaign_id' => $campaign->id]) }}"
                                   class="btn btn-primary btn-sm"
                                   style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:#3aa835;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:12px;text-decoration:none">
                                    ➕ Facture complémentaire
                                </a>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-6 rounded-xl border border-dashed" style="border-color:var(--border)">
                            <div class="text-3xl mb-2">💰</div>
                            <div class="text-sm font-semibold" style="color:var(--accent)">À facturer</div>
                            <div class="text-xs mt-1" style="color:var(--text3)" data-billing-expected-row>
                                @if($expectedHt > 0)
                                    Montant attendu : <strong style="color:var(--text2)"><span data-billing-expected>{{ number_format($expectedHt, 0, ',', ' ') }}</span> FCFA HT</strong>
                                @else
                                    Aucune facture émise pour le moment
                                @endif
                            </div>
                            @if($canBill)
                                <div style="display:flex;flex-direction:column;gap:6px;align-items:center;margin-top:12px">
                                    {{-- Génération automatique FNE : crée invoice + lignes
                                         + résout tarifs ODP/TM historisés en un clic. --}}
                                    <form method="POST" action="{{ route('admin.invoices.from-campaign', $campaign) }}"
                                          onsubmit="return confirm('Générer la facture FNE pour cette campagne ?\n\nElle sera créée en brouillon — vous pourrez ajuster avant l\'envoi.');"
                                          style="display:inline">
                                        @csrf
                                        <button type="submit"
                                                style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#3aa835;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer">
                                            ⚡ Générer la facture FNE
                                        </button>
                                    </form>
                                    <a href="{{ route('admin.invoices.create', ['campaign_id' => $campaign->id]) }}"
                                       style="color:var(--text3);font-size:11px;text-decoration:underline">
                                        ou facture manuelle classique
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <style>@keyframes pulse-dot{0%,100%{opacity:1}50%{opacity:.3}}</style>
            </div>
        </div>

        {{-- Actions (la Facturation a été intégrée dans le card Informations à gauche) --}}
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
                                // Surcharge du libellé pour utiliser des VERBES D'ACTION clairs
                                // au lieu du label du statut destination ("En cours", "En pause"…)
                                // qui n'indique pas ce qui va se passer au clic. Cohérent avec
                                // le bandeau "Cette campagne est en préparation" qui dit
                                // « cliquez sur ▶ Démarrer la campagne ».
                                $currentStatus = $campaign->status->value;
                                $actionLabel = match($val) {
                                    'actif'   => $currentStatus === 'planifie' ? 'Démarrer la campagne' : 'Reprendre',
                                    'pause'   => 'Mettre en pause',
                                    'termine' => 'Terminer',
                                    'annule'  => 'Annuler',
                                    default   => $label,
                                };
                            @endphp
                            <form method="POST" action="{{ route('admin.campaigns.update-status', $campaign) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $val }}">
                                <button type="submit"
                                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-bold transition-all"
                                        style="{{ $btnStyle }}"
                                        @if($val === 'annule') onclick="openCancelModal(event, this.closest('form'))" @endif>
                                    {{ $btnIcon }} {{ $actionLabel }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                    @else
                        {{-- État terminal (annule / termine) : message clair pour
                             que l'admin ne se demande pas pourquoi rien n'est cliquable. --}}
                        @if($campaign->status->value === 'annule')
                            <div style="background:rgba(239,68,68,.05);border:1px solid rgba(239,68,68,.20);border-radius:10px;padding:12px 14px;text-align:left">
                                <div style="font-size:13px;font-weight:800;color:#b91c1c;margin-bottom:4px">🚫 Campagne annulée</div>
                                <div style="font-size:12px;color:#7f1d1d;line-height:1.5">
                                    Statut terminal — aucune transition possible.
                                    @if($campaign->cancellation_reason)
                                        Motif : <strong>{{ ucfirst($campaign->cancellation_reason) }}</strong>.
                                    @endif
                                    @if($campaign->cancellation_notes)
                                        <br><em>{{ $campaign->cancellation_notes }}</em>
                                    @endif
                                    <br><span style="opacity:.85">Panneaux libérés, factures non payées annulées, poses annulées.</span>
                                </div>
                            </div>
                        @elseif($campaign->status->value === 'termine')
                            <div style="background:rgba(107,114,128,.06);border:1px solid rgba(107,114,128,.20);border-radius:10px;padding:12px 14px;text-align:left">
                                <div style="font-size:13px;font-weight:800;color:var(--text);margin-bottom:4px">✅ Campagne terminée</div>
                                <div style="font-size:12px;color:var(--text2);line-height:1.5">
                                    Statut terminal — aucune transition possible.
                                    Tu peux toujours <strong>renommer</strong> la campagne ou <strong>émettre une facture complémentaire</strong>
                                    si nécessaire (saisie historique).
                                </div>
                            </div>
                        @else
                            <p class="text-center text-sm py-3" style="color:var(--text3)">Aucune transition disponible</p>
                        @endif
                    @endif

                    {{-- Bouton "Lien pige campagne" retiré — unification workflow.
                         Chaque PoseTask a maintenant son propre lien unique
                         envoyé automatiquement au technicien assigné par
                         WhatsApp. Gestion centralisée dans /admin/pose-tasks.
                         L'ancien token campagne reste en BD pour les liens
                         déjà partagés mais n'est plus généré ni affiché. --}}
                    @if($can['update'] && $campaign->status->value !== 'annule')
                    <div class="mt-5 pt-5 border-t" style="border-color:var(--border)">
                        <a href="{{ route('admin.campaigns.poses', $campaign) }}"
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

                    {{-- Notifier le client des changements (panneaux, prix négociés…)
                         Bouton TOUJOURS visible quand la campagne est lancée et qu'il
                         y a au moins 1 panneau. Désactivé avec message si pas d'email
                         client (lien rapide pour aller l'ajouter). --}}
                    @if($can['update']
                        && in_array($campaign->status->value, ['planifie', 'actif', 'pause'])
                        && ($campaign->panels->count() + $campaign->externalPanels->count()) > 0)
                    @php
                        $clientHasEmail = !empty($campaign->client?->email);
                    @endphp
                    <div class="mt-5 pt-5 border-t" style="border-color:var(--border)">
                        @if($clientHasEmail)
                            <form method="POST" action="{{ route('admin.campaigns.notify-client', $campaign) }}"
                                  onsubmit="return confirm('Envoyer un mail récap au client avec les panneaux et le montant actuels ?');">
                                @csrf
                                <button type="submit"
                                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-bold"
                                        style="background:var(--surface2);color:var(--text);border:1px solid var(--border)">
                                    📧 Notifier le client des changements
                                </button>
                            </form>
                            <div class="text-xs mt-2 text-center" style="color:var(--text3)">
                                Renvoie un mail récap au client (panneaux ajoutés/retirés, prix négociés).
                            </div>
                        @else
                            <button type="button" disabled
                                    title="Aucune adresse email renseignée pour ce client"
                                    class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-bold opacity-60 cursor-not-allowed"
                                    style="background:var(--surface2);color:var(--text3);border:1px solid var(--border)">
                                📧 Notifier le client des changements
                            </button>
                            <div class="text-xs mt-2 text-center" style="color:#d97706;line-height:1.5">
                                ⚠️ Pas d'email renseigné pour ce client.
                                @if($campaign->client)
                                    <a href="{{ route('admin.clients.edit', $campaign->client) }}"
                                       style="color:var(--accent);text-decoration:underline;font-weight:600">
                                        Ajouter un email →
                                    </a>
                                @endif
                            </div>
                        @endif
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
                        <div x-show="show" x-cloak class="mt-4">
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

        </div>
    </div>

    {{-- ── QUICK ACTIONS — duplication / poses / piges / contact (déplacé sous Informations + Actions) ── --}}
    @php
        $qaPoseDone = \App\Models\PoseTask::where('campaign_id', $campaign->id)->where('status', 'realisee')->count();
        $qaPoseTotal = \App\Models\PoseTask::where('campaign_id', $campaign->id)->whereNotIn('status', ['annulee'])->count();
        $qaPigeOk = \App\Models\Pige::where('campaign_id', $campaign->id)->where('status', 'verifie')->count();
        $qaPigePend = \App\Models\Pige::where('campaign_id', $campaign->id)->where('status', 'en_attente')->count();
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-8">
        @if($can['update'] && $campaign->status->value !== 'annule')
        <button type="button" onclick="openDuplicateModal()"
                class="w-full rounded-xl border p-4 flex items-center gap-3 transition hover:shadow-md"
                style="background:var(--surface);border-color:var(--border);cursor:pointer;text-align:left">
            <span class="text-2xl">🔁</span>
            <div class="min-w-0">
                <div class="font-bold text-sm" style="color:var(--text)">Renouveler</div>
                <div class="text-xs" style="color:var(--text3)">Dupliquer cette campagne</div>
            </div>
        </button>
        @endif
        <a href="{{ route('admin.campaigns.poses', $campaign) }}"
           class="rounded-xl border p-4 flex items-center gap-3 transition hover:shadow-md"
           style="background:var(--surface);border-color:var(--border);text-decoration:none">
            <span class="text-2xl">🔧</span>
            <div class="min-w-0">
                <div class="font-bold text-sm" style="color:var(--text)">Poses & tâches</div>
                <div class="text-xs" style="color:var(--text3)">
                    @if($qaPoseTotal > 0)
                        {{ $qaPoseDone }}/{{ $qaPoseTotal }} réalisées
                    @else
                        Aucune pose
                    @endif
                </div>
            </div>
        </a>
        <a href="{{ route('admin.piges.index', ['campaign_id' => $campaign->id]) }}"
           class="rounded-xl border p-4 flex items-center gap-3 transition hover:shadow-md"
           style="background:var(--surface);border-color:var(--border);text-decoration:none">
            <span class="text-2xl">📸</span>
            <div class="min-w-0">
                <div class="font-bold text-sm" style="color:var(--text)">Piges photo</div>
                <div class="text-xs" style="color:var(--text3)">
                    {{ $qaPigeOk }} validées{{ $qaPigePend > 0 ? ' · '.$qaPigePend.' en attente' : '' }}
                </div>
            </div>
        </a>
        @if($campaign->client?->email)
        <a href="mailto:{{ $campaign->client->email }}?subject={{ urlencode('Campagne « ' . $campaign->name . ' »') }}"
           class="rounded-xl border p-4 flex items-center gap-3 transition hover:shadow-md"
           style="background:var(--surface);border-color:var(--border);text-decoration:none">
            <span class="text-2xl">✉️</span>
            <div class="min-w-0">
                <div class="font-bold text-sm" style="color:var(--text)">Contacter</div>
                <div class="text-xs truncate" style="color:var(--text3)">{{ $campaign->client->email }}</div>
            </div>
        </a>
        @endif

        {{-- Tile "Programmer un rechange" 2026-08-08 (multi-poses bulk).
             Visible seulement s'il y a au moins 1 pose réalisée réaffichable
             (sinon rien à rechanger) ET si l'admin peut update la campagne. --}}
        @if($can['update'] && $eligibleRechangeSources->isNotEmpty())
        <button type="button" onclick="openRechangeModal()"
                class="w-full rounded-xl border p-4 flex items-center gap-3 transition hover:shadow-md"
                style="background:linear-gradient(135deg,rgba(245,158,11,.10),rgba(180,83,9,.05));border-color:rgba(245,158,11,.35);cursor:pointer;text-align:left">
            <span class="text-2xl">🔄</span>
            <div class="min-w-0">
                <div class="font-bold text-sm" style="color:#b45309">Programmer un rechange</div>
                <div class="text-xs" style="color:var(--text3)">
                    {{ $eligibleRechangeSources->count() }} panneau(x) réaffichable(s)
                </div>
            </div>
        </button>
        @endif
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

        @php $isTerminee = $campaign->status->value === 'termine'; @endphp
        @if($can['managePanel'] && $isTerminee)
        {{-- Bandeau correction historique : la gestion des panneaux est
             exceptionnellement ouverte sur cette campagne terminée. --}}
        <div class="px-6 py-3 border-b flex items-center gap-3"
             style="background:rgba(245,158,11,.08);border-color:rgba(245,158,11,.3)">
            <span style="font-size:16px">⚠️</span>
            <div class="text-xs" style="color:#b45309">
                <strong>Campagne terminée</strong> — l'ajout/retrait de panneaux est autorisé uniquement pour corriger l'historique (ex: saisie d'une ancienne campagne). Les modifications seront enregistrées telles quelles.
            </div>
        </div>
        @endif

        @if($can['managePanel'])
        <div x-show="showAdd" x-cloak class="border-b" style="border-color:var(--border)">
            <div class="p-5" style="background:var(--surface2)">
                <form method="POST" action="{{ route('admin.campaigns.panels.add', $campaign) }}"
                      @if($isTerminee) onsubmit="return confirm('Cette campagne est TERMINÉE. Confirmer l\'ajout de panneaux pour correction de l\'historique ?');" @endif>
                    @csrf
                    {{-- Tabs source : Tous / Internes / Externes (régies partenaires)
                         + toggle « Libres uniquement » (ON par défaut) qui filtre
                         les indisponibles. Décocher = voir tout le parc avec badge
                         « Pris jusqu'au DD/MM », pour comprendre la dispo réelle. --}}
                    <div class="flex justify-between items-start gap-4 mb-4 flex-wrap">
                        <div class="flex gap-2 flex-wrap">
                            <button type="button" @click="setSource('all')"
                                    :style="filterSource === 'all' ? 'background:var(--accent);color:#fff;border-color:var(--accent)' : 'background:var(--surface);color:var(--text2);border-color:var(--border)'"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-all">
                                Tous (<span x-text="totals.internal + totals.external"></span>)
                            </button>
                            <button type="button" @click="setSource('internal')"
                                    :style="filterSource === 'internal' ? 'background:#3b82f6;color:#fff;border-color:#3b82f6' : 'background:var(--surface);color:var(--text2);border-color:var(--border)'"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-all">
                                🏢 Internes (<span x-text="counts.internal_available"></span>/<span x-text="totals.internal"></span>)
                            </button>
                            <button type="button" @click="setSource('external')"
                                    :style="filterSource === 'external' ? 'background:#7c3aed;color:#fff;border-color:#7c3aed' : 'background:var(--surface);color:var(--text2);border-color:var(--border)'"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-all">
                                🤝 Régies partenaires (<span x-text="counts.external_available"></span>/<span x-text="totals.external"></span>)
                            </button>
                        </div>
                        <label class="flex items-center gap-2 text-xs font-semibold cursor-pointer select-none px-3 py-1.5 rounded-lg border transition-all"
                               :style="showOnlyAvailable ? 'background:var(--accent-dim);border-color:var(--accent);color:var(--accent)' : 'background:var(--surface);border-color:var(--border);color:var(--text2)'">
                            <input type="checkbox" x-model="showOnlyAvailable" @change="filterPanels()"
                                   class="w-4 h-4" style="accent-color:var(--accent)">
                            Libres uniquement
                        </label>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
                        <div>
                            <label class="text-xs font-semibold block mb-2" style="color:var(--text3)">🔍 Recherche</label>
                            <input type="text" x-model="search" @input.debounce.250ms="filterPanels()"
                                   placeholder="Référence, nom, régie..."
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
                        {{-- Bandeau d'erreur explicite si le chargement a échoué
                             (HTTP 4xx/5xx, réseau, JSON invalide). Permet de distinguer
                             « 0 panneau libre » (cas normal) d'un « bug serveur ». --}}
                        <template x-if="!loadingPanels && loadError">
                            <div class="text-center py-12 px-4" style="color:#ef4444">
                                <div class="font-bold mb-1">⚠️ Impossible de charger la liste</div>
                                <div class="text-xs" x-text="loadError" style="color:var(--text2);font-family:ui-monospace,monospace"></div>
                                <div class="text-xs mt-3" style="color:var(--text3)">
                                    Réessayez en fermant puis rouvrant ce bloc. Si l'erreur persiste, contactez le support.
                                </div>
                            </div>
                        </template>
                        {{-- Cas 1 : campagne non modifiable (terminée/annulée) — message dédié --}}
                        <template x-if="!loadingPanels && !loadError && reason === 'campaign_status_not_modifiable'">
                            <div class="text-center py-12 px-6" style="color:var(--text3)">
                                <div class="text-3xl mb-2">🔒</div>
                                <div class="font-bold mb-1" style="color:var(--text)">Campagne non modifiable</div>
                                <div class="text-xs" x-text="'Statut actuel : ' + (campaignStatus || '—') + '. Repassez la campagne en planifié, actif ou pause pour ajouter des panneaux.'"></div>
                            </div>
                        </template>
                        {{-- Cas 2 : aucun panneau dans le parc — souvent un parc vide / pas de régie active --}}
                        <template x-if="!loadingPanels && !loadError && !reason && totals.internal === 0 && totals.external === 0">
                            <div class="text-center py-12 px-6" style="color:var(--text3)">
                                <div class="text-3xl mb-2">📭</div>
                                <div class="font-bold mb-1" style="color:var(--text)">Aucun panneau dans le parc</div>
                                <div class="text-xs">Aucun panneau interne actif ni régie partenaire active n'est configuré. Contactez l'administrateur.</div>
                            </div>
                        </template>
                        {{-- Cas 3 : parc rempli mais aucun panneau ne passe le filtre actif —
                             on explique pourquoi (filtres, libres uniquement, période) plutôt
                             qu'un sec « 0 trouvé » qui laisse l'utilisateur perplexe. --}}
                        <template x-if="!loadingPanels && !loadError && !reason && (totals.internal + totals.external) > 0 && filteredPanels.length === 0">
                            <div class="text-center py-12 px-6" style="color:var(--text3)">
                                <div class="text-3xl mb-2">🔍</div>
                                <div class="font-bold mb-1" style="color:var(--text)">Aucun panneau ne correspond</div>
                                <div class="text-xs leading-relaxed">
                                    Parc : <strong x-text="totals.internal"></strong> interne(s) + <strong x-text="totals.external"></strong> partenaire(s)
                                    — <strong x-text="counts.internal_available + counts.external_available"></strong> libre(s) sur la période
                                    <strong x-text="periodLabel"></strong>.
                                </div>
                                <template x-if="showOnlyAvailable && (counts.internal_available + counts.external_available) === 0">
                                    <button type="button" @click="showOnlyAvailable = false; filterPanels()"
                                            class="mt-3 text-xs font-semibold underline" style="color:var(--accent)">
                                        Voir tout le parc (libres + pris)
                                    </button>
                                </template>
                                <template x-if="!showOnlyAvailable || (counts.internal_available + counts.external_available) > 0">
                                    <div class="text-xs mt-2">Élargissez la recherche ou retirez un filtre.</div>
                                </template>
                            </div>
                        </template>
                        <template x-for="p in paginatedPanels" :key="p.id">
                            <label class="flex items-center gap-4 p-4 border-b last:border-0 transition-all"
                                   style="border-color:var(--border)"
                                   :class="p.available ? 'cursor-pointer' : 'cursor-not-allowed'"
                                   :style="(p.available && selectedPanels.includes(p.id) ? 'background:var(--accent-dim);border-left:3px solid var(--accent);' : '')
                                         + (p.source === 'external' && p.available ? 'background-color:rgba(124,58,237,0.04);' : '')
                                         + (!p.available ? 'opacity:0.55;' : '')">
                                <input type="checkbox" :value="p.id" x-model="selectedPanels" name="panel_ids[]"
                                       :disabled="!p.available"
                                       class="w-4 h-4" style="accent-color:var(--accent)">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-3 flex-wrap">
                                        <span class="font-mono text-sm font-bold"
                                              :style="p.source === 'external' ? 'color:#7c3aed' : 'color:var(--accent)'"
                                              x-text="p.reference"></span>
                                        <span class="font-medium truncate" style="color:var(--text)" x-text="p.name"></span>
                                        <template x-if="p.source === 'external'">
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full"
                                                  style="background:rgba(124,58,237,.12);color:#7c3aed">
                                                Régie <span x-text="p.agency_name || 'partenaire'"></span>
                                            </span>
                                        </template>
                                        {{-- Badge statut : Libre (vert) si dispo, sinon « Pris »
                                             avec libellé date de libération si connue. --}}
                                        <template x-if="p.available">
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full"
                                                  style="background:rgba(16,185,129,.12);color:#10b981">
                                                ✓ Libre
                                            </span>
                                        </template>
                                        <template x-if="!p.available">
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full"
                                                  style="background:rgba(239,68,68,.12);color:#ef4444"
                                                  x-text="p.release_date || ('Pris (' + (p.blocking_status || 'engagé') + ')')"></span>
                                        </template>
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

        {{-- Bandeau maintenance globale : si au moins un panneau interne de la
             campagne est en maintenance ouverte, on l'affiche en tête de tableau
             pour que l'admin voie tout de suite l'impact sur la diffusion. --}}
        @php
            $panelsInMaint = $campaign->panels->filter(fn($p) => $p->activeMaintenance);
            $maintCount    = $panelsInMaint->count();
            $maxReturn     = $panelsInMaint
                ->map(fn($p) => $p->activeMaintenance?->date_fin_prevue)
                ->filter()
                ->max();
        @endphp
        @if($maintCount > 0)
            <div style="margin-bottom:14px;padding:12px 16px;border-radius:10px;background:linear-gradient(180deg,#fff7ed,#fffbeb);border:1px solid #fed7aa;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                <div style="width:38px;height:38px;border-radius:10px;background:#f97316;color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px">🔧</div>
                <div style="flex:1;min-width:200px;font-size:13px;color:#9a3412">
                    <div style="font-weight:800;margin-bottom:2px">
                        {{ $maintCount }} panneau{{ $maintCount > 1 ? 'x' : '' }} en maintenance
                    </div>
                    <div style="color:#b45309">
                        @if($maxReturn)
                            Retour complet estimé au plus tard le
                            <strong>{{ \Carbon\Carbon::parse($maxReturn)->format('d/m/Y') }}</strong>.
                        @else
                            Durée non renseignée — vérifie les fiches maintenance.
                        @endif
                        Le client a été informé par email automatiquement.
                    </div>
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
                    //
                    // En parallèle, on précharge aussi reservation_panels.unit_price
                    // pour afficher le prix NÉGOCIÉ (différent du tarif catalogue
                    // panel.monthly_rate quand l'utilisateur a customisé).
                    $deferredStartByPanel = [];
                    $negotiatedPriceByPanel = []; // panel_id => unit_price (interne)
                    $negotiatedPriceByExt   = []; // external_panel_id => unit_price
                    if ($campaign->reservation_id) {
                        $rows = \Illuminate\Support\Facades\DB::table('reservation_panels')
                            ->where('reservation_id', $campaign->reservation_id)
                            ->get(['panel_id', 'external_panel_id', 'panel_start_date', 'unit_price', 'source']);
                        foreach ($rows as $r) {
                            $key = $r->source === 'externe'
                                ? 'ext_' . $r->external_panel_id
                                : 'int_' . $r->panel_id;
                            if ($r->panel_start_date && $r->panel_start_date > $campaign->start_date->format('Y-m-d')) {
                                $deferredStartByPanel[$key] = $r->panel_start_date;
                            }
                            if ($r->source === 'externe') {
                                if ($r->external_panel_id !== null) {
                                    $negotiatedPriceByExt[(int) $r->external_panel_id] = (float) $r->unit_price;
                                }
                            } else {
                                if ($r->panel_id !== null) {
                                    $negotiatedPriceByPanel[(int) $r->panel_id] = (float) $r->unit_price;
                                }
                            }
                        }
                    }
                @endphp
                @forelse($campaign->panels as $panel)
                    @php
                        $catalogRate    = (float) ($panel->monthly_rate ?? 0);
                        $negotiatedRate = $negotiatedPriceByPanel[$panel->id] ?? null;
                        $effectiveRate  = $negotiatedRate !== null ? $negotiatedRate : $catalogRate;
                        $isNegotiated   = $negotiatedRate !== null && abs($negotiatedRate - $catalogRate) > 0.01;
                        $deferKey       = 'int_' . $panel->id;
                        $deferredStart  = $deferredStartByPanel[$deferKey] ?? null;
                        $canEditPrice   = $can['updatePrice'];
                    @endphp
                    <tr class="border-b transition-all group" data-panel-row style="border-color:var(--border)"
                        onmouseover="this.style.background='var(--surface2)'"
                        onmouseout="this.style.background='transparent'">
                        <td class="px-5 py-4">
                            <a href="{{ route('admin.panels.show', $panel) }}"
                               class="font-mono text-sm font-bold"
                               style="color:var(--accent);text-decoration:none;border-bottom:1px dashed var(--border)"
                               title="Voir la fiche panneau">{{ $panel->reference }}</a>
                        </td>
                        <td class="px-5 py-4 font-medium" style="color:var(--text)">
                            <a href="{{ route('admin.panels.show', $panel) }}"
                               style="color:var(--text);text-decoration:none;border-bottom:1px dashed transparent"
                               onmouseover="this.style.borderColor='var(--border)'"
                               onmouseout="this.style.borderColor='transparent'">{{ $panel->name }}</a>
                        </td>
                        <td class="px-5 py-4" style="color:var(--text2)">{{ $panel->commune?->name ?? '—' }}</td>
                        <td class="px-5 py-4" style="color:var(--text2)">{{ $panel->format?->name ?? '—' }}</td>
                        <td class="px-5 py-4">
                            @if($panel->is_lit)
                                <span style="color:var(--accent)">💡 Oui</span>
                            @else
                                <span style="color:var(--text3)">Non</span>
                            @endif
                        </td>
                        {{-- Cellule prix : édition inline si on a les droits + bon statut --}}
                        <td class="px-5 py-4 text-right"
                            data-price-cell
                            data-panel-id="{{ $panel->id }}"
                            data-effective-rate="{{ $effectiveRate }}"
                            data-catalog-rate="{{ $catalogRate }}"
                            data-months="{{ $billableMonths }}"
                            data-update-url="{{ route('admin.campaigns.panels.price', ['campaign' => $campaign->id, 'panel' => $panel->id]) }}"
                            data-can-edit="{{ $canEditPrice ? '1' : '0' }}">
                            <span data-price-display
                                  title="{{ $canEditPrice ? 'Cliquez pour modifier le prix négocié' : '' }}"
                                  style="cursor:{{ $canEditPrice ? 'pointer' : 'default' }};
                                         color:{{ $effectiveRate > 0 ? 'var(--text2)' : 'var(--text3)' }};
                                         {{ $canEditPrice ? 'border-bottom:1px dashed var(--border2);padding-bottom:1px;' : '' }}">
                                {{ $effectiveRate > 0 ? number_format($effectiveRate, 0, ',', ' ') . ' FCFA' : '—' }}
                            </span>
                            @if($isNegotiated)
                                <div style="font-size:10px;color:var(--text3);margin-top:2px"
                                     title="Tarif catalogue : {{ number_format($catalogRate, 0, ',', ' ') }} FCFA">
                                    négocié
                                    <span style="text-decoration:line-through;opacity:.7">{{ number_format($catalogRate, 0, ',', ' ') }}</span>
                                </div>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right font-semibold" data-total-period style="color:var(--accent)">
                            {{ $effectiveRate > 0 ? number_format($effectiveRate * $billableMonths, 0, ',', ' ') . ' FCFA' : '—' }}
                        </td>
                        <td class="px-5 py-4">
                            <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-start;">
                                @php $activeMaint = $panel->activeMaintenance; @endphp
                                @if($activeMaint)
                                    {{-- Panneau en maintenance — badge dédié + date retour estimée.
                                         Le client voit la même info dans son espace (cohérence). --}}
                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold border"
                                          style="background:rgba(249,115,22,.10);color:#ea580c;border-color:rgba(249,115,22,.30)"
                                          title="Maintenance #{{ $activeMaint->id }} — statut {{ $activeMaint->statut }}">
                                        🔧 Maintenance
                                    </span>
                                    @if($activeMaint->date_fin_prevue)
                                        @php $remaining = $activeMaint->daysRemaining(); @endphp
                                        <span style="font-size:10.5px;color:var(--text3);font-weight:600"
                                              title="Date de retour estimée">
                                            Retour ~{{ $activeMaint->date_fin_prevue->format('d/m') }}
                                            @if($remaining !== null)
                                                @if($remaining < 0)
                                                    @php $lateDays = (int) abs($remaining); @endphp
                                                    · <span style="color:#ef4444">en retard {{ $lateDays }} {{ $lateDays > 1 ? 'jours' : 'jour' }}</span>
                                                @elseif($remaining === 0)
                                                    · <span style="color:#16a34a">aujourd'hui</span>
                                                @else
                                                    · J-{{ $remaining }}
                                                @endif
                                            @endif
                                        </span>
                                    @endif
                                @else
                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold border"
                                          style="background:{{ $campBadge['bg'] }};color:{{ $campBadge['color'] }};border-color:{{ $campBadge['border'] }}">
                                        {{ $campBadge['label'] }}
                                    </span>
                                @endif
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
                        $catalogRateExt    = (float) ($panel->monthly_rate ?? 0);
                        $negotiatedRateExt = $negotiatedPriceByExt[$panel->id] ?? null;
                        $effectiveRateExt  = $negotiatedRateExt !== null ? $negotiatedRateExt : $catalogRateExt;
                        $isNegotiatedExt   = $negotiatedRateExt !== null && abs($negotiatedRateExt - $catalogRateExt) > 0.01;
                        $deferKey          = 'ext_' . $panel->id;
                        $deferredStart     = $deferredStartByPanel[$deferKey] ?? null;
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
                            {{ $effectiveRateExt > 0 ? number_format($effectiveRateExt, 0, ',', ' ') . ' FCFA' : '—' }}
                            @if($isNegotiatedExt)
                                <div style="font-size:10px;color:var(--text3);margin-top:2px"
                                     title="Tarif catalogue : {{ number_format($catalogRateExt, 0, ',', ' ') }} FCFA">
                                    négocié
                                    <span style="text-decoration:line-through;opacity:.7">{{ number_format($catalogRateExt, 0, ',', ' ') }}</span>
                                </div>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right font-semibold" style="color:var(--accent)">
                            {{ $effectiveRateExt > 0 ? number_format($effectiveRateExt * $billableMonths, 0, ',', ' ') . ' FCFA' : '—' }}
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
                                        onclick="openRetireExtPanel({{ $panel->id }}, @js($panel->code_panneau))"
                                        class="p-2 rounded-lg transition-all"
                                        style="color:#ef4444"
                                        onmouseover="this.style.background='rgba(239,68,68,0.1)'"
                                        onmouseout="this.style.background='transparent'"
                                        title="Retirer ce panneau externe">✕</button>
                            </td>
                        @endif
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

    {{-- ── MODAL RENOMMER (campagne terminée — correction historique) ── --}}
    @if($can['managePanel'])
    <div id="modal-rename" class="fixed inset-0 backdrop-blur-md flex items-center justify-center z-50 hidden"
         style="background:rgba(0,0,0,0.7)" onclick="if(event.target===this) closeRenameModal()">
        <div class="rounded-2xl border max-w-md w-full mx-4 overflow-hidden shadow-2xl"
             style="background:var(--surface);border-color:var(--border)" onclick="event.stopPropagation()">
            <div class="px-6 py-5 border-b flex justify-between items-center"
                 style="background:var(--surface2);border-color:var(--border)">
                <h3 class="font-bold text-xl" style="color:var(--text)">✏️ Corriger la campagne</h3>
                <button onclick="closeRenameModal()" class="text-2xl transition" style="color:var(--text3)">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.campaigns.rename', $campaign) }}">
                @csrf @method('PATCH')
                <div class="p-6 flex flex-col gap-4">
                    <div>
                        <label class="text-xs font-semibold block mb-2" style="color:var(--text3);text-transform:uppercase;letter-spacing:.5px">
                            Nom de la campagne
                        </label>
                        <input type="text" name="name" value="{{ old('name', $campaign->name) }}" required maxlength="150"
                               class="w-full rounded-lg px-4 py-2.5 text-sm focus:outline-none"
                               style="background:var(--surface2);border:1px solid var(--border);color:var(--text)">
                        @error('name')
                            <p class="text-xs mt-2" style="color:#ef4444">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="text-xs font-semibold block mb-2" style="color:var(--text3);text-transform:uppercase;letter-spacing:.5px">
                            Commercial assigné
                        </label>
                        <select name="commercial_user_id"
                                class="w-full rounded-lg px-4 py-2.5 text-sm focus:outline-none"
                                style="background:var(--surface2);border:1px solid var(--border);color:var(--text)">
                            <option value="">— Aucun —</option>
                            @foreach($commerciaux as $c)
                                <option value="{{ $c->id }}" {{ (string) old('commercial_user_id', $campaign->commercial_user_id) === (string) $c->id ? 'selected' : '' }}>
                                    {{ $c->name }} ({{ strtoupper((string) ($c->role?->value ?? $c->role ?? '')) }})
                                </option>
                            @endforeach
                        </select>
                        @error('commercial_user_id')
                            <p class="text-xs mt-2" style="color:#ef4444">{{ $message }}</p>
                        @enderror
                        <p class="text-xs mt-2" style="color:var(--text3)">
                            Le commercial conditionne le classement Top Commercial.
                        </p>
                    </div>
                </div>
                <div class="px-6 py-5 border-t flex justify-end gap-3" style="border-color:var(--border)">
                    <button type="button" onclick="closeRenameModal()" class="px-5 py-2 rounded-xl border transition-all"
                            style="border-color:var(--border);color:var(--text2)">Annuler</button>
                    <button type="submit" class="px-5 py-2 rounded-xl text-white font-semibold" style="background:var(--accent)">
                        💾 Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script>
        function openRenameModal() {
            const m = document.getElementById('modal-rename');
            if (m) { m.classList.remove('hidden'); m.querySelector('input[name="name"]')?.focus(); }
        }
        function closeRenameModal() {
            document.getElementById('modal-rename')?.classList.add('hidden');
        }
        @if($errors->has('name'))
            // Réouvre le modal si erreur de validation au submit
            document.addEventListener('DOMContentLoaded', openRenameModal);
        @endif
    </script>
    @endif

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

    {{-- ── MODAL DUPLICATION ── --}}
    @if($can['update'] && $campaign->status->value !== 'annule')
    @php
        $dupDuration = (int) max(1, $campaign->start_date->diffInDays($campaign->end_date));
        $dupDefaultStart = $campaign->end_date->copy()->addDay()->format('Y-m-d');
        $dupDefaultEnd   = $campaign->end_date->copy()->addDay()->addDays($dupDuration)->format('Y-m-d');
        $dupIntCount = $campaign->panels->count();
        $dupExtCount = $campaign->externalPanels->count();
    @endphp
    <div id="modal-duplicate" class="fixed inset-0 backdrop-blur-md items-center justify-center hidden"
         style="background:rgba(0,0,0,0.7);z-index:9999;display:none"
         onclick="if(event.target===this) closeDuplicateModal()">
        <div class="rounded-2xl border max-w-lg w-full mx-4 overflow-hidden shadow-2xl"
             style="background:var(--surface);border-color:var(--border)" onclick="event.stopPropagation()">
            <div class="px-6 py-5 border-b flex justify-between items-center"
                 style="background:rgba(232,160,32,0.08);border-color:rgba(232,160,32,0.25)">
                <h3 class="font-bold text-xl" style="color:var(--accent)">🔁 Renouveler la campagne</h3>
                <button onclick="closeDuplicateModal()" type="button" class="text-2xl" style="color:var(--text3);background:none;border:none;cursor:pointer">&times;</button>
            </div>
            <form id="form-duplicate" method="POST" action="{{ route('admin.campaigns.duplicate', $campaign) }}">
                @csrf
                <div class="p-6">
                    <div class="rounded-xl p-4 mb-5" style="background:var(--surface2);border:1px solid var(--border)">
                        <div class="text-xs font-bold uppercase tracking-wider mb-2" style="color:var(--text3)">Ce qui sera dupliqué</div>
                        <ul style="font-size:13px;color:var(--text2);line-height:1.8;margin:0;padding:0;list-style:none">
                            <li>✓ {{ $dupIntCount + $dupExtCount }} panneau{{ ($dupIntCount + $dupExtCount) > 1 ? 'x' : '' }} ({{ $dupIntCount }} interne{{ $dupIntCount > 1 ? 's' : '' }}{{ $dupExtCount > 0 ? ' + '.$dupExtCount.' externe'.($dupExtCount > 1 ? 's' : '') : '' }})</li>
                            <li>✓ Prix négociés (tarifs unitaires)</li>
                            <li>✓ Client : <strong style="color:var(--text)">{{ $campaign->client?->name ?? '—' }}</strong></li>
                            <li>✓ Commercial assigné</li>
                            <li>✓ Notes internes</li>
                        </ul>
                        <div class="mt-3 text-xs" style="color:var(--text3);font-style:italic">
                            La nouvelle campagne sera créée en <strong>PLANIFIE</strong>. Aucun mail au client jusqu'à ce que tu cliques sur ▶ Démarrer.
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-bold uppercase tracking-wider block mb-2" style="color:var(--text3)">Date début</label>
                            <input type="date" name="start_date" id="dup-start" required
                                   value="{{ $dupDefaultStart }}"
                                   min="{{ now()->format('Y-m-d') }}"
                                   onchange="syncDupEnd()"
                                   class="w-full rounded-lg px-3 py-2 text-sm focus:outline-none"
                                   style="background:var(--surface2);border:1px solid var(--border);color:var(--text)">
                        </div>
                        <div>
                            <label class="text-xs font-bold uppercase tracking-wider block mb-2" style="color:var(--text3)">Date fin</label>
                            <input type="date" name="end_date" id="dup-end" required
                                   value="{{ $dupDefaultEnd }}"
                                   class="w-full rounded-lg px-3 py-2 text-sm focus:outline-none"
                                   style="background:var(--surface2);border:1px solid var(--border);color:var(--text)">
                        </div>
                    </div>
                    <div class="text-xs mt-2" style="color:var(--text3)">
                        💡 Par défaut : juste après l'ancienne campagne pour la même durée ({{ $dupDuration }} jour{{ $dupDuration > 1 ? 's' : '' }}). Modifiable.
                    </div>
                </div>
                <div class="px-6 py-4 border-t flex justify-end gap-3" style="border-color:var(--border)">
                    <button type="button" onclick="closeDuplicateModal()"
                            class="px-5 py-2 rounded-xl border" style="border-color:var(--border);color:var(--text2);background:transparent;cursor:pointer">
                        Annuler
                    </button>
                    <button type="submit"
                            class="px-5 py-2 rounded-xl text-white font-semibold" style="background:var(--accent);border:none;cursor:pointer">
                        🔁 Dupliquer
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script>
    function openDuplicateModal() {
        const m = document.getElementById('modal-duplicate');
        m.classList.remove('hidden');
        m.style.display = 'flex';
        setTimeout(() => document.getElementById('dup-start')?.focus(), 50);
    }
    function closeDuplicateModal() {
        const m = document.getElementById('modal-duplicate');
        m.classList.add('hidden');
        m.style.display = 'none';
    }
    function syncDupEnd() {
        // Quand l'utilisateur change la date début, recalcule la fin par défaut
        // pour conserver la même durée que l'ancienne campagne.
        const start = document.getElementById('dup-start').value;
        if (!start) return;
        const d = new Date(start);
        d.setDate(d.getDate() + {{ $dupDuration }});
        document.getElementById('dup-end').value = d.toISOString().split('T')[0];
    }
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeDuplicateModal();
    });
    </script>
    @endif

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
            filterSource: 'all',     // 'all' | 'internal' | 'external'
            showOnlyAvailable: true, // par défaut : on ne montre que les libres
                                     // pour ne pas noyer l'utilisateur. Toggle off
                                     // = on voit tout le parc avec badge « Pris ».
            selectedPanels: [],
            allPanels: [],
            filteredPanels: [],
            loadingPanels: false,
            loadError: null,
            loaded: false,
            visibleCount: 20,
            campaignMonths: {{ $campaign->billableMonths() }},
            // Compteurs renvoyés par l'API : permettent un message vide
            // informatif (« Parc : X internes + Y partenaires — Z libres »).
            totals: { internal: 0, external: 0 },
            counts: { internal_available: 0, external_available: 0 },
            period: { start: null, end: null },
            reason: null,            // ex: 'campaign_status_not_modifiable'
            campaignStatus: null,

            get communeOptions() {
                return [...new Set(this.allPanels.map(p => p.commune).filter(Boolean))].sort();
            },
            get formatOptions() {
                return [...new Set(this.allPanels.map(p => p.format).filter(Boolean))].sort();
            },
            get paginatedPanels() {
                return this.filteredPanels.slice(0, this.visibleCount);
            },
            get periodLabel() {
                if (!this.period.start || !this.period.end) return '';
                const fmt = (iso) => {
                    const [y, m, d] = iso.split('-');
                    return `${d}/${m}/${y}`;
                };
                return `${fmt(this.period.start)} → ${fmt(this.period.end)}`;
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
                this.loadError = null;
                try {
                    const res = await fetch(PANELS_URL, { headers: { 'Accept': 'application/json' } });
                    // Distingue erreur réseau / 403 / 500 d'un retour 200 avec liste vide
                    // (permet à l'utilisateur de savoir si c'est un vrai 0 panneau libre
                    // ou un échec à corriger côté serveur).
                    if (!res.ok) {
                        const txt = await res.text().catch(() => '');
                        this.loadError = `Erreur ${res.status} : ${txt.slice(0, 200) || res.statusText}`;
                        this.allPanels = [];
                        this.filteredPanels = [];
                        console.error('[loadPanels] HTTP', res.status, txt);
                        return;
                    }
                    const data = await res.json();
                    this.allPanels      = data.panels || [];
                    this.campaignMonths = data.campaign_months || this.campaignMonths;
                    this.totals         = data.totals || { internal: 0, external: 0 };
                    this.counts         = data.counts || { internal_available: 0, external_available: 0 };
                    this.period         = data.period || { start: null, end: null };
                    this.reason         = data.reason || null;
                    this.campaignStatus = data.campaign_status || null;
                    this.filterPanels();
                    this.loaded = true;
                } catch (e) {
                    this.allPanels = [];
                    this.filteredPanels = [];
                    this.loadError = 'Erreur réseau : ' + (e.message || 'connexion impossible');
                    console.error('[loadPanels] fetch failed', e);
                } finally {
                    this.loadingPanels = false;
                }
                @endif
            },

            setSource(source) {
                this.filterSource = source;
                this.filterPanels();
            },

            filterPanels() {
                const s  = this.search.toLowerCase().trim();
                const fc = this.filterCommune.toLowerCase();
                const ff = this.filterFormat.toLowerCase();
                const fl = this.filterIsLit;
                const src = this.filterSource;
                const onlyFree = this.showOnlyAvailable;
                this.visibleCount = 20;
                this.filteredPanels = this.allPanels.filter(p => {
                    // Toggle « libres uniquement » : exclut les indisponibles.
                    if (onlyFree && !p.available) return false;
                    // Source : 'all' / 'internal' / 'external'
                    if (src === 'internal' && p.source === 'external') return false;
                    if (src === 'external' && p.source !== 'external') return false;
                    // Recherche : référence, nom, commune, ou nom de régie
                    if (s) {
                        const hay = [
                            p.reference?.toLowerCase() || '',
                            p.name?.toLowerCase() || '',
                            p.commune?.toLowerCase() || '',
                            p.agency_name?.toLowerCase() || '',
                        ];
                        if (!hay.some(h => h.includes(s))) return false;
                    }
                    if (fc && p.commune?.toLowerCase() !== fc) return false;
                    if (ff && p.format?.toLowerCase() !== ff) return false;
                    if (fl && (fl === '1' ? !p.is_lit : p.is_lit)) return false;
                    return true;
                });
            },

            formatPrice(p) { return Number(p).toLocaleString('fr-FR') + ' FCFA/mois'; },
            formatEstimate() {
                // Les IDs peuvent être numériques (internes) ou "ext_<n>" (externes)
                // donc on compare en string via String(). Les indisponibles ne
                // peuvent pas se trouver dans selectedPanels (checkbox disabled)
                // mais on filtre par sécurité au cas où la liste change.
                const total = this.selectedPanels.reduce((s, id) => {
                    const panel = this.allPanels.find(x => String(x.id) === String(id));
                    if (!panel || !panel.available) return s;
                    return s + ((panel.monthly_rate || 0) * this.campaignMonths);
                }, 0);
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
    function openRetireExtPanel(id, ref) {
        document.getElementById('retire-ref').textContent = ref + ' (régie partenaire)';
        document.getElementById('retire-form').action = `/admin/campaigns/{{ $campaign->id }}/external-panels/${id}`;
        document.getElementById('modal-retire').classList.remove('hidden');
    }
    function closeRetireModal() { document.getElementById('modal-retire').classList.add('hidden'); }

    // ─────────────────────────────────────────────────────────────────
    // ÉDITION PRIX INLINE — sur chaque cellule [data-price-cell] cliquable.
    //
    // Implémentation par DÉLÉGATION d'événement : on écoute les clics sur
    // l'ensemble du document et on déclenche l'édition dès que la cible
    // est un [data-price-display] dont la cellule parente est éditable.
    // Ainsi quand le span est restauré après edit ou abandon (innerHTML
    // remplacé), le clic suivant continue de fonctionner — le listener
    // n'est PAS attaché aux spans individuels qui sont détruits.
    //
    //   ↵ Entrée : sauvegarde via AJAX PATCH (recalcul du total période
    //              + bandeau global "Montant total" mis à jour).
    //   Echap   : restaure la valeur d'origine sans appel réseau.
    // ─────────────────────────────────────────────────────────────────
    (function() {
        const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const formatFCFA = (n) => Math.round(n).toLocaleString('fr-FR') + ' FCFA';

        // Délégation : un seul listener global qui survit aux remplacements
        // de DOM (innerHTML) à la sortie/save d'une édition.
        document.addEventListener('click', (event) => {
            const display = event.target.closest?.('[data-price-display]');
            if (!display) return;
            const cell = display.closest('[data-price-cell][data-can-edit="1"]');
            if (!cell) return;
            if (cell.dataset.editing === '1') return;
            startEdit(cell, display);
        });

        function startEdit(cell, display) {
            if (cell.dataset.editing === '1') return;
            cell.dataset.editing = '1';

            const current = parseFloat(cell.dataset.effectiveRate || 0);
            const months  = parseFloat(cell.dataset.months || 1);
            const oldHTML = cell.innerHTML;

            const input = document.createElement('input');
            input.type = 'number';
            input.step = '1';
            input.min  = '0';
            input.value = current > 0 ? Math.round(current) : '';
            input.style.cssText = 'width:120px;text-align:right;background:var(--surface);border:1px solid var(--accent);border-radius:6px;padding:4px 8px;font-size:13px;color:var(--text);outline:none;font-family:inherit';

            const wrap = document.createElement('div');
            wrap.style.cssText = 'display:inline-flex;flex-direction:column;align-items:flex-end;gap:4px';
            wrap.appendChild(input);

            const hint = document.createElement('span');
            hint.textContent = '↵ Enregistrer · Échap Annuler';
            hint.style.cssText = 'font-size:9px;color:var(--text3);letter-spacing:.3px';
            wrap.appendChild(hint);

            cell.innerHTML = '';
            cell.appendChild(wrap);
            input.focus();
            input.select();

            function restore() {
                cell.innerHTML = oldHTML;
                cell.dataset.editing = '0';
            }

            async function save() {
                const newVal = parseFloat(input.value);
                if (isNaN(newVal) || newVal < 0) { restore(); return; }
                if (Math.abs(newVal - current) < 0.01) { restore(); return; }

                input.disabled = true;
                hint.textContent = 'Enregistrement…';

                try {
                    const res = await fetch(cell.dataset.updateUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': CSRF,
                            'X-HTTP-Method-Override': 'PATCH',
                        },
                        body: JSON.stringify({ _method: 'PATCH', unit_price: newVal }),
                    });
                    const data = await res.json();
                    if (!res.ok || !data.ok) {
                        hint.textContent = '⚠️ ' + (data.error || 'Erreur — réessayez');
                        hint.style.color = '#ef4444';
                        input.disabled = false;
                        return;
                    }

                    cell.dataset.effectiveRate = data.unit_price;

                    // Reconstruit la cellule d'affichage
                    cell.innerHTML = '';
                    const span = document.createElement('span');
                    span.dataset.priceDisplay = '';
                    span.title = 'Cliquez pour modifier le prix négocié';
                    span.style.cssText = 'cursor:pointer;color:var(--text2);border-bottom:1px dashed var(--border2);padding-bottom:1px';
                    span.textContent = formatFCFA(data.unit_price);
                    cell.appendChild(span);

                    // Badge "négocié" si différent du catalogue
                    const catalog = parseFloat(cell.dataset.catalogRate || 0);
                    if (Math.abs(data.unit_price - catalog) > 0.01) {
                        const sub = document.createElement('div');
                        sub.style.cssText = 'font-size:10px;color:var(--text3);margin-top:2px';
                        sub.innerHTML = 'négocié <span style="text-decoration:line-through;opacity:.7">'
                            + Math.round(catalog).toLocaleString('fr-FR') + '</span>';
                        cell.appendChild(sub);
                    }

                    cell.dataset.editing = '0';
                    // Pas besoin d'attacher de listener sur le span : la
                    // délégation globale au document s'en occupe.

                    // Total période (colonne suivante)
                    const row = cell.closest('tr');
                    const totalCell = row?.querySelector('[data-total-period]');
                    if (totalCell) totalCell.textContent = formatFCFA(data.total_period);

                    // Bandeau "Montant total" en haut de la page +
                    // synchronise data-total-amount (sinon le bouton
                    // Ajuster lirait l'ancienne valeur).
                    const headerTotal = document.querySelector('[data-campaign-total]');
                    if (headerTotal && typeof data.campaign_total === 'number') {
                        headerTotal.textContent = Math.round(data.campaign_total).toLocaleString('fr-FR');
                    }
                    const totalCellRoot = document.querySelector('[data-total-cell]');
                    if (totalCellRoot && typeof data.campaign_total === 'number') {
                        totalCellRoot.dataset.totalAmount = data.campaign_total;
                        // Modif d'un prix unitaire → l'override forfaitaire
                        // précédent (s'il existait) est devenu invalide.
                        const badge = totalCellRoot.querySelector('[data-override-badge]');
                        if (badge) badge.style.display = 'none';
                    }

                    // ── Bloc FACTURATION (live, sans reload) ───────────
                    // Met à jour "Total campagne" / "Montant attendu" /
                    // "Reste à facturer" — sinon ces chiffres restaient à
                    // l'ancien montant tant qu'on ne rechargeait pas la page.
                    if (typeof data.campaign_total === 'number') {
                        const newExpected = data.campaign_total;
                        document.querySelectorAll('[data-billing-expected]').forEach(el => {
                            el.textContent = Math.round(newExpected).toLocaleString('fr-FR');
                        });

                        // Reste = Expected - Déjà facturé (Déjà facturé inchangé
                        // par une modif de prix : on lit la valeur figée du DOM).
                        const billedEl = document.querySelector('[data-billing-billed]');
                        if (billedEl) {
                            const billed = parseFloat(billedEl.dataset.billingBilled || 0);
                            const newRemain = Math.max(0, newExpected - billed);
                            document.querySelectorAll('[data-billing-remain]').forEach(el => {
                                el.textContent = Math.round(newRemain).toLocaleString('fr-FR');
                            });
                            // Reste passe à 0 → on grise la couleur + masque le
                            // bandeau "Facture complémentaire".
                            const remainColor = document.querySelector('[data-billing-remain-color]');
                            if (remainColor) {
                                remainColor.style.color = newRemain > 0 ? '#f97316' : 'var(--text3)';
                            }
                            const remainRow = document.querySelector('[data-billing-remain-row]');
                            if (remainRow) {
                                remainRow.style.display = newRemain > 0 ? '' : 'none';
                            }
                        }
                    }

                    if (window.Toast) window.Toast.success('💰 Prix mis à jour. Montant total recalculé.');
                } catch (e) {
                    hint.textContent = '⚠️ Réseau : ' + e.message;
                    hint.style.color = '#ef4444';
                    input.disabled = false;
                }
            }

            input.addEventListener('keydown', e => {
                if (e.key === 'Enter') { e.preventDefault(); save(); }
                else if (e.key === 'Escape') { e.preventDefault(); restore(); }
            });
            input.addEventListener('blur', () => {
                // Petite tempo pour laisser passer le click de hint éventuel
                setTimeout(() => { if (cell.dataset.editing === '1') restore(); }, 150);
            });
        }
    })();

    // ─────────────────────────────────────────────────────────────────
    // ÉDITION MONTANT TOTAL — override forfaitaire / remise globale
    //
    // Délégation d'événement (et re-query du span à chaque clic) pour
    // qu'on puisse rééditer après une 1ère édition : la 1ère version
    // capturait le span en const à l'init du listener, ce qui le
    // rendait obsolète après remplacement par l'input → 2e clic muet.
    // ─────────────────────────────────────────────────────────────────
    (function() {
        const cell = document.querySelector('[data-total-cell][data-can-edit="1"]');
        if (!cell) return;
        const btn  = cell.querySelector('[data-total-edit-btn]');
        const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        if (!btn) return;

        btn.addEventListener('click', () => {
            // Re-query à chaque clic — le span a été remplacé après une
            // précédente édition, donc une référence capturée à l'init
            // serait stale et invisible dans le DOM.
            const totalSpan = cell.querySelector('[data-campaign-total]');
            if (!totalSpan) return;
            if (cell.dataset.editing === '1') return; // déjà en édition
            cell.dataset.editing = '1';

            const current = parseFloat(cell.dataset.totalAmount || 0);

            const input = document.createElement('input');
            input.type = 'number';
            input.step = '1';
            input.min  = '0';
            input.value = Math.round(current);
            input.style.cssText = 'width:160px;background:var(--surface);border:1px solid var(--accent);border-radius:6px;padding:4px 8px;font-size:16px;color:var(--text);outline:none;font-weight:700;font-family:inherit';

            totalSpan.replaceWith(input);
            input.focus();
            input.select();

            // Capture le parent du input à la création (pour pouvoir y
            // ré-insérer le span même si une autre opération a déjà retiré
            // input du DOM entre temps).
            const inputParent = input.parentElement;

            function restoreSpan(newVal, formatted) {
                cell.dataset.editing = '0';
                const text = formatted || Math.round(newVal).toLocaleString('fr-FR');

                // Si le input est toujours dans le DOM, on le remplace
                // directement (cas nominal). Sinon, on cherche s'il existe
                // déjà un span dans le parent et on le met à jour.
                if (input.parentElement) {
                    const span = document.createElement('span');
                    span.dataset.campaignTotal = '';
                    span.textContent = text;
                    input.replaceWith(span);
                } else if (inputParent) {
                    let span = inputParent.querySelector('[data-campaign-total]');
                    if (!span) {
                        span = document.createElement('span');
                        span.dataset.campaignTotal = '';
                        // Insère en première position du parent
                        inputParent.insertBefore(span, inputParent.firstChild);
                    }
                    span.textContent = text;
                }
            }

            async function save() {
                const newVal = parseFloat(input.value);
                if (isNaN(newVal) || newVal < 0) { restoreSpan(current); return; }
                if (Math.abs(newVal - current) < 0.01) { restoreSpan(current); return; }

                input.disabled = true;

                try {
                    const res = await fetch(cell.dataset.updateUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': CSRF,
                            'X-HTTP-Method-Override': 'PATCH',
                        },
                        body: JSON.stringify({ _method: 'PATCH', total_amount: newVal }),
                    });
                    const data = await res.json();
                    if (!res.ok || !data.ok) {
                        alert('⚠️ ' + (data.error || 'Erreur — réessayez'));
                        input.disabled = false;
                        return;
                    }
                    cell.dataset.totalAmount = data.total_amount;
                    // Utilise le formatage côté serveur (espace insécable
                    // français, pas de décimales) pour rester identique à
                    // ce que PHP affiche au reload.
                    restoreSpan(data.total_amount, data.total_amount_formatted);

                    // Met à jour le badge "Négocié par X · DD/MM HH:mm"
                    updateOverrideBadge(data.overridden_by, data.overridden_at_formatted);

                    // ── Bloc FACTURATION (live, sans reload) ───────────
                    // Même logique que dans le save() de l'édition prix
                    // panneau : Total campagne / Montant attendu / Reste.
                    // (Le Déjà facturé reste figé — une modif de prix ne
                    // crée/supprime aucune facture émise.)
                    const newExpected = parseFloat(data.total_amount);
                    if (!isNaN(newExpected)) {
                        document.querySelectorAll('[data-billing-expected]').forEach(el => {
                            el.textContent = Math.round(newExpected).toLocaleString('fr-FR');
                        });
                        const billedEl = document.querySelector('[data-billing-billed]');
                        if (billedEl) {
                            const billed = parseFloat(billedEl.dataset.billingBilled || 0);
                            const newRemain = Math.max(0, newExpected - billed);
                            document.querySelectorAll('[data-billing-remain]').forEach(el => {
                                el.textContent = Math.round(newRemain).toLocaleString('fr-FR');
                            });
                            const remainColor = document.querySelector('[data-billing-remain-color]');
                            if (remainColor) {
                                remainColor.style.color = newRemain > 0 ? '#f97316' : 'var(--text3)';
                            }
                            const remainRow = document.querySelector('[data-billing-remain-row]');
                            if (remainRow) {
                                remainRow.style.display = newRemain > 0 ? '' : 'none';
                            }
                        }
                    }

                    // Toast de confirmation (system d'app — window.Toast)
                    if (window.Toast) window.Toast.success(data.message || '✅ Montant total mis à jour.');
                } catch (e) {
                    alert('⚠️ Erreur réseau : ' + e.message);
                    input.disabled = false;
                }
            }

            function updateOverrideBadge(by, when) {
                const badge   = cell.querySelector('[data-override-badge]');
                const byLabel = cell.querySelector('[data-override-by]');
                if (!badge) return;
                badge.style.display = 'inline-flex';
                if (byLabel) byLabel.textContent = 'par ' + (by || '—') + ' · ' + (when || '');
            }

            input.addEventListener('keydown', e => {
                if (e.key === 'Enter')       { e.preventDefault(); save(); }
                else if (e.key === 'Escape') { e.preventDefault(); restoreSpan(current); }
            });
            // Pas de blur auto-cancel : la race condition avec save() async
            // refaisait reverter l'affichage à l'ancienne valeur après
            // sauvegarde réussie. L'utilisateur peut toujours annuler via Échap.
        });
    })();

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

    {{-- ═══════════════════════════════════════════════════════════════
         MODAL "Programmer un rechange" — Multi-poses bulk 2026-08-08.
         Ouvert par le tile de la grille "Actions rapides".
         Liste toutes les poses éligibles (realisée non replaced) avec
         checkbox pré-cochée. L'admin décoche les panneaux à ne pas
         refaire, remplit date + tech + notes + type, submit → génère
         N rechanges d'un coup via CampaignController::rechange.
    ══════════════════════════════════════════════════════════════ --}}
    @if($can['update'] && $eligibleRechangeSources->isNotEmpty())
    <div id="rechange-modal"
         style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:9999;align-items:center;justify-content:center;padding:16px"
         onclick="if(event.target===this)closeRechangeModal()">
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;max-width:720px;width:100%;max-height:90vh;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;display:flex;flex-direction:column">
            <div style="padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px">
                <div style="width:40px;height:40px;border-radius:10px;background:rgba(245,158,11,.12);display:flex;align-items:center;justify-content:center;font-size:20px">🔄</div>
                <div style="flex:1">
                    <h3 style="margin:0;font-size:16px;font-weight:800;color:var(--text)">Programmer un rechange</h3>
                    <p style="margin:2px 0 0;font-size:12.5px;color:var(--text3)">
                        Campagne <strong>{{ $campaign->name }}</strong>
                        · <strong>{{ $eligibleRechangeSources->count() }}</strong> panneau(x) réaffichable(s)
                    </p>
                </div>
                <button type="button" onclick="closeRechangeModal()"
                        style="background:transparent;border:0;color:var(--text3);cursor:pointer;padding:4px;line-height:0">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.campaigns.rechange', $campaign) }}"
                  style="display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden">
                @csrf

                <div style="padding:16px 22px;overflow-y:auto;flex:1;min-height:0">
                    {{-- Bandeau info workflow --}}
                    <div style="background:rgba(59,130,246,.06);border:1px solid rgba(59,130,246,.15);border-radius:8px;padding:10px 12px;margin-bottom:16px;font-size:12.5px;color:var(--text2);line-height:1.55">
                        💡 Les <strong>panneaux cochés ci-dessous</strong> vont recevoir un rechange à la date et avec le technicien indiqués.
                        Les poses actuelles resteront dans l'historique (marquées <em>remplacées</em>).
                        Chaque tech assigné recevra une notification WhatsApp.
                    </div>

                    {{-- Type de pose --}}
                    <div style="margin-bottom:14px">
                        <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text2);margin-bottom:6px">Type d'intervention</label>
                        <select name="pose_kind" required
                                style="width:100%;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:13px;color:var(--text)">
                            <option value="rechange" selected>🔄 Rechange affiche (nouveau visuel)</option>
                            <option value="retouche">🔧 Retouche (réparation, nettoyage)</option>
                        </select>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
                        {{-- Date planifiée --}}
                        <div>
                            <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text2);margin-bottom:6px">Date &amp; heure prévues <span style="color:#ef4444">*</span></label>
                            <input type="datetime-local" name="scheduled_at" required
                                   value="{{ now()->addDay()->format('Y-m-d\TH:i') }}"
                                   style="width:100%;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:13px;color:var(--text)">
                        </div>
                        {{-- Tech assigné --}}
                        <div>
                            <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text2);margin-bottom:6px">Technicien assigné (tous)</label>
                            <select name="assigned_user_id" id="rechange-campaign-tech"
                                    style="width:100%;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:13px;color:var(--text)">
                                <option value="">— Non assigné —</option>
                                @foreach($technicians as $t)
                                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                                @endforeach
                            </select>
                            <div id="rechange-campaign-tech-hint" style="display:none;margin-top:4px;font-size:11px;font-style:italic"></div>
                        </div>
                    </div>

                    <div style="margin-bottom:14px">
                        <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text2);margin-bottom:6px">Équipe créditée (facultatif)</label>
                        {{-- 2026-08-10 : basculé sur pose_team_id (FK) pour
                             attribution mérite collectif. Cf. refonte KPI.
                             v2 : au change → filtre le select tech ci-dessus
                             pour n'afficher que les membres de l'équipe. --}}
                        <select name="pose_team_id" id="rechange-campaign-team"
                                style="width:100%;height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:13px;color:var(--text)">
                            <option value="">— Aucune (crédit individuel du tech) —</option>
                            @foreach(($poseTeams ?? collect()) as $t)
                                <option value="{{ $t->id }}">👥 {{ $t->name }} — crédit équipe</option>
                            @endforeach
                        </select>
                        <div style="font-size:10.5px;color:var(--text3);margin-top:4px;font-style:italic;line-height:1.4">
                            Si tech + équipe : la pose est attribuée à l'équipe (mérite collectif), le tech reste le porteur physique.
                        </div>
                    </div>
                    @push('scripts')
                    @include('admin.partials._filter_tech_by_team', [
                        'selTeamId'     => 'rechange-campaign-team',
                        'selTechId'     => 'rechange-campaign-tech',
                        'membersByTeam' => $membersByTeam ?? [],
                        'hintId'        => 'rechange-campaign-tech-hint',
                    ])
                    @endpush

                    <div style="margin-bottom:16px">
                        <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text2);margin-bottom:6px">Notes (facultatif)</label>
                        <textarea name="notes" rows="2" maxlength="1000"
                                  placeholder="Ex: nouveau visuel campagne printemps — bâches disponibles au dépôt"
                                  style="width:100%;padding:8px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:13px;color:var(--text);resize:vertical"></textarea>
                    </div>

                    {{-- Liste des panneaux à rechanger — pré-cochés --}}
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                        <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text2)">
                            Panneaux à réafficher ({{ $eligibleRechangeSources->count() }})
                        </label>
                        <div style="display:flex;gap:8px">
                            <button type="button" onclick="rechangeSelectAll(true)"
                                    style="font-size:11px;padding:4px 10px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#15803d;border-radius:6px;cursor:pointer;font-weight:600">
                                ✓ Tout cocher
                            </button>
                            <button type="button" onclick="rechangeSelectAll(false)"
                                    style="font-size:11px;padding:4px 10px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);color:#dc2626;border-radius:6px;cursor:pointer;font-weight:600">
                                Tout décocher
                            </button>
                        </div>
                    </div>

                    <div id="rechange-panels-list" style="display:flex;flex-direction:column;gap:4px;border:1px solid var(--border);border-radius:10px;padding:6px;background:var(--surface2);max-height:280px;overflow-y:auto">
                        @foreach($eligibleRechangeSources as $src)
                            <label style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:6px;cursor:pointer;transition:background .12s"
                                   onmouseover="this.style.background='var(--surface)'"
                                   onmouseout="this.style.background='transparent'">
                                <input type="checkbox" name="source_ids[]" value="{{ $src->id }}" checked
                                       class="rechange-source-cb"
                                       style="width:16px;height:16px;accent-color:var(--accent);cursor:pointer;flex-shrink:0">
                                <div style="flex:1;min-width:0">
                                    <div style="font-size:13px;font-weight:700;color:var(--text)">
                                        {{ $src->panel?->reference ?? '#'.$src->panel_id }}
                                        <span style="color:var(--text3);font-weight:500">·</span>
                                        {{ \Illuminate\Support\Str::limit($src->panel?->name ?? '—', 40) }}
                                    </div>
                                    <div style="font-size:11px;color:var(--text3);margin-top:1px">
                                        📍 {{ $src->panel?->commune?->name ?? '—' }}
                                        @if($src->done_at)
                                            · dernière pose {{ $src->done_at->format('d/m/Y') }}
                                        @endif
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    <div style="margin-top:8px;font-size:11.5px;color:var(--text3)">
                        <span id="rechange-count-selected">{{ $eligibleRechangeSources->count() }}</span>
                        panneau(x) sélectionné(s) — décoche ceux qui ne doivent pas être refaits.
                    </div>
                </div>

                <div style="padding:14px 22px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;background:var(--surface)">
                    <button type="button" onclick="closeRechangeModal()"
                            style="padding:9px 16px;background:var(--surface2);border:1px solid var(--border2);color:var(--text2);border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">
                        Annuler
                    </button>
                    <button type="submit" id="rechange-submit-btn"
                            style="padding:9px 20px;background:linear-gradient(135deg,#f59e0b,#b45309);color:#fff;border:0;border-radius:8px;font-size:13px;font-weight:800;cursor:pointer;box-shadow:0 4px 12px rgba(245,158,11,.3)">
                        🔄 Créer les rechanges
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openRechangeModal()  { document.getElementById('rechange-modal').style.display = 'flex'; updateRechangeCount(); }
        function closeRechangeModal() { document.getElementById('rechange-modal').style.display = 'none'; }
        function rechangeSelectAll(v) {
            document.querySelectorAll('.rechange-source-cb').forEach(cb => cb.checked = v);
            updateRechangeCount();
        }
        function updateRechangeCount() {
            const n = document.querySelectorAll('.rechange-source-cb:checked').length;
            const el = document.getElementById('rechange-count-selected');
            if (el) el.textContent = n;
            const btn = document.getElementById('rechange-submit-btn');
            if (btn) {
                btn.disabled = n === 0;
                btn.style.opacity = n === 0 ? '.5' : '1';
                btn.style.cursor  = n === 0 ? 'not-allowed' : 'pointer';
            }
        }
        // Écoute les changements pour maintenir le compteur à jour
        document.addEventListener('change', function(e) {
            if (e.target && e.target.classList.contains('rechange-source-cb')) updateRechangeCount();
        });
    </script>
    @endif
</x-admin-layout>
