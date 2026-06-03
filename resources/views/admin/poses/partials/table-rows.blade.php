@once
<style>
    /* ── HEADER GROUPE CAMPAGNE — design unifié (aligné cards) ─────── */
    .pose-campaign-header {
        padding: 14px 18px 14px 18px;
        background: var(--surface2);
        color: var(--text);
        border-left: 4px solid var(--accent);
        border-top: 1px solid var(--border);
        border-right: 1px solid var(--border);
        border-top-left-radius: 10px;
        border-top-right-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
    }
    .pose-campaign-header .pch-icon {
        width: 36px; height: 36px;
        border-radius: 9px;
        background: rgba(232,160,32,.12);
        border: 1px solid rgba(232,160,32,.28);
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .pose-campaign-header .pch-label {
        font-size: 9px; font-weight: 700;
        color: var(--accent);
        text-transform: uppercase;
        letter-spacing: 1.2px;
        margin-bottom: 3px;
    }
    .pose-campaign-header .pch-name {
        font-size: 14px; font-weight: 700;
        color: var(--text);
        text-decoration: none;
        display: block;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        max-width: 380px;
        letter-spacing: -.1px;
    }
    .pose-campaign-header .pch-name:hover { color: var(--accent); }
    .pose-campaign-header .pch-sub {
        font-size: 11px;
        color: var(--text3);
        margin-top: 2px;
    }
    .pose-campaign-header .pch-sub strong {
        color: var(--text2);
        font-weight: 600;
    }
    .pose-campaign-header .pch-pill {
        padding: 3px 10px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 999px;
        color: var(--text2);
        font-weight: 700;
        font-size: 11px;
    }
    .pose-campaign-header .pch-pill-success {
        background: rgba(34,197,94,.10);
        border-color: rgba(34,197,94,.35);
        color: #16a34a;
    }
    .pose-campaign-header .pch-pill-warn {
        background: rgba(232,160,32,.10);
        border-color: rgba(232,160,32,.35);
        color: var(--accent);
    }
    .pose-group-toggle {
        background: var(--surface) !important;
        border: 1px solid var(--border) !important;
        color: var(--text2) !important;
    }
    .pose-group-toggle:hover { color: var(--accent) !important; border-color: var(--accent) !important; }

    /* Badges Pige — design unifié pro */
    .pige-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 9px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 600;
        text-decoration: none;
        white-space: nowrap;
        border: 1px solid transparent;
        transition: transform .12s, box-shadow .12s;
    }
    .pige-badge:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(0,0,0,.08);
    }
    .pige-badge svg { flex-shrink: 0; }
    .pige-badge-count { font-weight: 700; }
    .pige-badge-sep { opacity: .35; margin: 0 1px; }
    .pige-badge-verif {
        display: inline-flex;
        align-items: center;
        gap: 2px;
        padding: 1px 5px;
        background: rgba(255,255,255,.5);
        border-radius: 6px;
        font-size: 10px;
        font-weight: 700;
    }
    /* État "à ajouter" — orange action requise */
    .pige-badge-todo {
        background: rgba(249,115,22,.1);
        border-color: rgba(249,115,22,.3);
        color: #f97316;
    }
    /* État "en attente validation" — bleu informatif */
    .pige-badge-pending {
        background: rgba(59,130,246,.08);
        border-color: rgba(59,130,246,.25);
        color: #3b82f6;
    }
    /* État "partiellement validé" — jaune en progression */
    .pige-badge-partial {
        background: rgba(245,158,11,.1);
        border-color: rgba(245,158,11,.3);
        color: #b45309;
    }
    /* État "toutes validées" — vert OK */
    .pige-badge-ok {
        background: rgba(34,197,94,.1);
        border-color: rgba(34,197,94,.3);
        color: #16a34a;
    }
    .pige-badge-ok .pige-badge-verif {
        background: rgba(34,197,94,.18);
    }

    /* ── RESPONSIVE MOBILE ─────────────────────────────────────────
       Sur mobile le tableau à 9 colonnes est inutilisable. On masque
       les colonnes secondaires et on garde l'essentiel : Panneau,
       Statut, Actions. Les infos masquées restent accessibles en
       cliquant sur le panneau (page détail tâche). */
    @media (max-width: 768px) {
        /* Wrapper : on retire le min-width forcé pour ne plus scroller */
        .pose-table-wrap { overflow-x: visible !important; }
        .pose-table-wrap > table { min-width: 0 !important; }

        /* Colonnes à masquer (1=checkbox, 3=campagne, 4=technicien,
           5=planifié, 6=réalisé, 7=pige) */
        .pose-table-wrap thead th:nth-child(1),
        .pose-table-wrap thead th:nth-child(3),
        .pose-table-wrap thead th:nth-child(4),
        .pose-table-wrap thead th:nth-child(5),
        .pose-table-wrap thead th:nth-child(6),
        .pose-table-wrap thead th:nth-child(7),
        .pose-table-wrap tbody tr.trow td:nth-child(1),
        .pose-table-wrap tbody tr.trow td:nth-child(3),
        .pose-table-wrap tbody tr.trow td:nth-child(4),
        .pose-table-wrap tbody tr.trow td:nth-child(5),
        .pose-table-wrap tbody tr.trow td:nth-child(6),
        .pose-table-wrap tbody tr.trow td:nth-child(7) {
            display: none !important;
        }

        /* Boutons d'actions plus compacts */
        .pose-table-wrap .action-btn {
            width: 28px !important;
            height: 28px !important;
            padding: 4px !important;
        }
    }
    @media (max-width: 480px) {
        /* Sur écran très étroit on masque aussi la barre de progression
           (gardée que le badge statut) pour laisser la place à Actions */
        .pose-table-wrap .pose-progress-fill,
        .pose-table-wrap .pose-progress-text { display: none !important; }
    }

    /* ─── Boutons d'action des poses ──────────────────────────────
       Anciennement définis uniquement dans poses/index.blade.php →
       quand le partial était inclus depuis campaigns/poses.blade.php
       les boutons ressortaient pâles/invisibles (CSS absente). On
       déplace ici via @once pour garantir le rendu partout. */
    .action-btn {
        display:inline-flex;align-items:center;justify-content:center;
        width:34px;height:34px;border-radius:9px;
        border:1px solid var(--border);background:var(--surface2);
        color:var(--text2);text-decoration:none;cursor:pointer;
        transition:all .15s;flex-shrink:0;
    }
    .action-btn:hover { background:var(--surface3);border-color:var(--border2);color:var(--text); }
    .action-btn-success { border-color:rgba(34,197,94,.3);background:rgba(34,197,94,.08);color:#22c55e; }
    .action-btn-success:hover { background:rgba(34,197,94,.18);border-color:rgba(34,197,94,.5); }
    .action-btn-accent { border-color:rgba(232,160,32,.3);background:rgba(232,160,32,.08);color:var(--accent); }
    .action-btn-accent:hover { background:rgba(232,160,32,.18);border-color:rgba(232,160,32,.5); }
</style>
@endonce

@if($poseTasks->isEmpty())
<div style="text-align:center;padding:60px 20px;color:var(--text3)">
    <div style="opacity:.15;margin-bottom:14px"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="display:block;margin:0 auto"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg></div>
    <div style="font-size:14px;font-weight:700;margin-bottom:6px">Aucune tâche de pose</div>
    <div style="font-size:12px;margin-bottom:18px;color:var(--text3)">Créez une première tâche de pose pour commencer.</div>
    <a href="{{ route('admin.pose-tasks.create') }}" class="btn btn-primary">+ Créer une tâche</a>
</div>
@else
<div class="pose-table-wrap" style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;min-width:900px">
        <thead>
            <tr style="background:var(--surface2);border-bottom:1px solid var(--border)">
                <th style="padding:9px 6px 9px 14px;width:32px;text-align:left">
                    <input type="checkbox" id="pose-check-all"
                           style="accent-color:var(--accent);width:14px;height:14px;cursor:pointer;"
                           title="Tout sélectionner sur cette page">
                </th>
                <th style="padding:9px 12px;text-align:left;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap">Panneau</th>
                <th style="padding:9px 12px;text-align:left;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap">Campagne</th>
                <th style="padding:9px 12px;text-align:left;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap">Technicien</th>
                <th style="padding:9px 12px;text-align:left;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap">Planifié</th>
                <th style="padding:9px 12px;text-align:left;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap">Réalisé</th>
                <th style="padding:9px 12px;text-align:left;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap">Pige</th>
                <th style="padding:9px 12px;text-align:left;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap">Statut</th>
                <th style="padding:9px 12px;text-align:left;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;white-space:nowrap">Actions</th>
            </tr>
        </thead>
        <tbody id="pose-tbody">
        @php $lastCampaignId = '__INIT__'; @endphp
        @foreach($poseTasks as $task)
        @php
            // ── Header de groupe : insérer une ligne de séparation
            // quand on change de campagne. Permet de visualiser les
            // panneaux regroupés par campagne dans le tableau.
            $currentCampaignId = $task->campaign_id ?? 'none';
            $showCampaignHeader = $currentCampaignId !== $lastCampaignId;
            $lastCampaignId = $currentCampaignId;

            // Compteurs au sein du groupe (calculés à la volée la 1re ligne)
            if ($showCampaignHeader) {
                $groupTasks = collect($poseTasks->items())
                    ->filter(fn($t) => ($t->campaign_id ?? 'none') === $currentCampaignId);
                $groupTotal    = $groupTasks->count();
                $groupRealisee = $groupTasks->where('status', 'realisee')->count();
                $groupPlanif   = $groupTasks->where('status', 'planifiee')->count();
            }
        @endphp

        @if($showCampaignHeader)
        {{-- Espace au-dessus du header pour bien séparer la campagne précédente --}}
        @if(!$loop->first)
        <tr aria-hidden="true"><td colspan="9" style="padding:0;border:none;height:14px;background:transparent"></td></tr>
        @endif
        <tr class="campaign-group-header">
            <td colspan="9" style="padding:0;border:none">
                <div class="pose-campaign-header">
                    <div style="display:flex;align-items:center;gap:12px;min-width:0">
                        {{-- Chevron toggle : replie/déplie les panneaux du groupe --}}
                        <button type="button"
                                class="pose-group-toggle"
                                data-campaign-toggle="{{ $currentCampaignId }}"
                                aria-expanded="true"
                                title="Plier / déplier les panneaux de cette campagne"
                                style="border-radius:8px;width:26px;height:26px;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;padding:0;transition:transform .2s ease, color .15s, border-color .15s">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </button>
                        <input type="checkbox"
                               class="pose-group-check"
                               data-campaign-id="{{ $currentCampaignId }}"
                               title="Sélectionner toutes les poses de cette campagne (hors réalisées / annulées)"
                               style="accent-color:var(--accent);width:16px;height:16px;cursor:pointer;flex-shrink:0;margin-right:2px;">
                        <div class="pch-icon">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                        </div>
                        <div style="min-width:0">
                            <div class="pch-label">Campagne</div>
                            @if($task->campaign)
                                <a href="{{ route('admin.campaigns.show', $task->campaign) }}" class="pch-name"
                                   title="{{ $task->campaign->name }}">
                                    {{ $task->campaign->name }}
                                </a>
                                <div class="pch-sub">
                                    @if($task->campaign->deleted_at)
                                        🗑 Campagne supprimée
                                    @elseif($task->campaign->client?->name)
                                        Client&nbsp;: <strong>{{ $task->campaign->client->name }}</strong>
                                    @else
                                        Créée {{ $task->campaign->created_at?->diffForHumans() ?? '—' }}
                                    @endif
                                </div>
                            @else
                                <div class="pch-name" style="font-style:italic;color:var(--text2)">
                                    Tâches sans campagne
                                </div>
                                <div class="pch-sub">Interventions ponctuelles</div>
                            @endif
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px;font-size:11px;flex-wrap:wrap">
                        <span class="pch-pill">
                            {{ $groupTotal }} pose{{ $groupTotal > 1 ? 's' : '' }}
                        </span>
                        @if($groupRealisee > 0)
                        <span class="pch-pill pch-pill-success">
                            ✓ {{ $groupRealisee }} réalisée{{ $groupRealisee > 1 ? 's' : '' }}
                        </span>
                        @endif
                        @if($groupPlanif > 0)
                        <span class="pch-pill pch-pill-warn">
                            ⏱ {{ $groupPlanif }} planifiée{{ $groupPlanif > 1 ? 's' : '' }}
                        </span>
                        @endif
                    </div>
                </div>
            </td>
        </tr>
        @endif

        @php
            $sCfg = match($task->status) {
                'planifiee' => ['c'=>'#e8a020','bg'=>'rgba(232,160,32,.1)','bd'=>'rgba(232,160,32,.3)','l'=>'Planifiée'],
                'en_cours'  => ['c'=>'#3b82f6','bg'=>'rgba(59,130,246,.1)','bd'=>'rgba(59,130,246,.3)','l'=>'En cours'],
                'realisee'  => ['c'=>'#22c55e','bg'=>'rgba(34,197,94,.1)', 'bd'=>'rgba(34,197,94,.3)', 'l'=>'Réalisée'],
                'annulee'   => ['c'=>'#ef4444','bg'=>'rgba(239,68,68,.1)', 'bd'=>'rgba(239,68,68,.3)', 'l'=>'Annulée'],
                default     => ['c'=>'#6b7280','bg'=>'rgba(107,114,128,.1)','bd'=>'rgba(107,114,128,.3)','l'=>$task->status],
            };
            $sIcon = match($task->status) {
                'planifiee' => '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
                'en_cours'  => '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
                'realisee'  => '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>',
                'annulee'   => '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
                default     => '',
            };
            $isLate = $task->status === 'planifiee' && $task->scheduled_at?->isPast();
            $pigeCount = $task->pige_count ?? 0;
            $pigeVerif = $task->pige_verifie_count ?? 0;
            $needsPige = $task->status === 'realisee' && $task->campaign_id && $pigeCount === 0;
            $rowStyle = $isLate ? 'border-left:3px solid rgba(239,68,68,.5);background:rgba(239,68,68,.02)' : ($needsPige ? 'border-left:3px solid rgba(249,115,22,.4);background:rgba(249,115,22,.015)' : '');
        @endphp
        @php $isFinal = in_array($task->status, ['realisee', 'annulee']); @endphp
        <tr class="trow" data-pose-id="{{ $task->id }}" data-campaign-group="{{ $currentCampaignId }}" style="{{ $rowStyle }}">
            <td style="padding:10px 6px 10px 14px;width:32px">
                <input type="checkbox" class="pose-check"
                       value="{{ $task->id }}"
                       {{ $isFinal ? 'disabled' : '' }}
                       data-tech-id="{{ $task->assigned_user_id ?? '' }}"
                       data-team="{{ $task->team_name ?? '' }}"
                       data-status="{{ $task->status }}"
                       data-campaign-id="{{ $task->campaign_id ?? 'none' }}"
                       title="{{ $isFinal ? 'Tâche terminée — non modifiable en masse' : 'Sélectionner' }}"
                       style="accent-color:var(--accent);width:14px;height:14px;cursor:{{ $isFinal ? 'not-allowed' : 'pointer' }};opacity:{{ $isFinal ? '.35' : '1' }};">
            </td>
            <td style="padding:10px 12px">
                <a href="{{ route('admin.pose-tasks.show', $task) }}" style="font-family:monospace;font-size:12px;font-weight:700;color:var(--accent);text-decoration:none;display:block">{{ $task->panel?->reference ?? '—' }}</a>
                <div style="font-size:11px;color:var(--text2);margin-top:1px;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $task->panel?->name }}">{{ $task->panel?->name ?? '—' }}</div>
                @if($task->panel?->commune)<div style="font-size:10px;color:var(--text3)">{{ $task->panel->commune->name }}</div>@endif
            </td>
            <td style="padding:10px 12px;max-width:150px">
                @if($task->campaign)
                    @php
                        $isTrashed = (bool) $task->campaign->deleted_at;
                        $cstatus   = $task->campaign->status?->value ?? '';
                        $isPaused  = $cstatus === 'pause';
                        $isClosed  = in_array($cstatus, ['annule', 'termine'], true);
                        $orphan    = $isTrashed || $isClosed;
                    @endphp
                    <a href="{{ route('admin.campaigns.show', $task->campaign) }}" style="font-size:12px;font-weight:500;color:var(--text);text-decoration:none;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;{{ $orphan ? 'text-decoration:line-through;color:var(--text3);' : '' }}" title="{{ $task->campaign->name }}">{{ Str::limit($task->campaign->name, 22) }}</a>
                    @if($isTrashed)
                        <div style="font-size:9px;color:#ef4444;margin-top:2px;font-weight:700">🗑️ Supprimée</div>
                    @elseif($isPaused)
                        <div style="font-size:9px;color:#f59e0b;margin-top:2px;font-weight:700">⏸️ En pause</div>
                    @elseif($isClosed)
                        @php $cui = $task->campaign->status->uiConfig(); @endphp
                        <div style="font-size:9px;color:{{ $cui['color'] }};margin-top:2px;font-weight:700">{{ $task->campaign->status->label() }}</div>
                    @else
                        @php $cui = $task->campaign->status->uiConfig(); @endphp
                        <div style="font-size:9px;color:{{ $cui['color'] }};margin-top:2px;font-weight:600">{{ $task->campaign->status->label() }}</div>
                    @endif
                @else
                    <span style="font-size:11px;color:var(--text3);font-style:italic">Intervention</span>
                @endif
            </td>
            <td style="padding:10px 12px">
                @php
                    $tech = $task->technicien;
                    $techInitials = $tech ? mb_strtoupper(mb_substr(collect(explode(' ', $tech->name))->map(fn($w)=>$w[0] ?? '')->take(2)->implode(''), 0, 2)) : '';
                    $hasWa = $tech && !empty($tech->whatsapp_number);
                @endphp
                @if($tech)
                <div style="display:flex;align-items:center;gap:8px;min-width:0">
                    <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#e8a020,#fab80b);color:#fff;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;flex-shrink:0;letter-spacing:-.3px">
                        {{ $techInitials ?: '?' }}
                    </div>
                    <div style="min-width:0">
                        <div style="font-size:12px;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:130px" title="{{ $tech->name }}">{{ $tech->name }}</div>
                        @if($task->team_name)
                            <div style="font-size:10px;color:var(--text3)">👥 {{ $task->team_name }}</div>
                        @elseif(!$hasWa)
                            <div style="font-size:9px;color:#ef4444">⚠ Pas de WhatsApp</div>
                        @endif
                    </div>
                </div>
                @elseif($task->tech_name_self)
                {{-- Tech non assigné formellement mais a saisi son nom via le lien public --}}
                <div style="display:flex;align-items:center;gap:8px;min-width:0"
                     title="Identité déclarée via le lien public le {{ $task->tech_name_self_at?->format('d/m/Y H:i') }} (IP {{ $task->tech_name_self_ip }})">
                    <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#8b5cf6);color:#fff;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;flex-shrink:0;letter-spacing:-.3px">
                        {{ mb_strtoupper(mb_substr($task->tech_name_self, 0, 1)) }}
                    </div>
                    <div style="min-width:0">
                        <div style="font-size:12px;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:130px" title="{{ $task->tech_name_self }}">{{ $task->tech_name_self }}</div>
                        <div style="font-size:9px;color:#3b82f6;font-weight:600">📱 Déclaré via lien</div>
                    </div>
                </div>
                @else
                <span style="font-size:11px;color:var(--text3);font-style:italic">— Non assigné —</span>
                @endif
            </td>
            <td style="padding:10px 12px;white-space:nowrap">
                <div style="font-size:12px;font-weight:500;color:{{ $isLate ? '#ef4444' : 'var(--text)' }}">{{ $task->scheduled_at?->format('d/m/Y') ?? '—' }}</div>
                <div style="font-size:10px;color:{{ $isLate ? '#ef4444' : 'var(--text3)' }}">{{ $task->scheduled_at?->format('H:i') }}@if($isLate)<span style="font-weight:700;margin-left:3px">En retard</span>@endif</div>
            </td>
            <td style="padding:10px 12px;white-space:nowrap">
                @if($task->done_at)<div style="font-size:12px;color:#22c55e;font-weight:500">{{ $task->done_at->format('d/m/Y') }}</div><div style="font-size:10px;color:var(--text3)">{{ $task->done_at->format('H:i') }}</div>@else<span style="color:var(--text3);font-size:12px">—</span>@endif
            </td>
            <td style="padding:10px 12px">
                @if(!$task->campaign_id)
                    <span style="font-size:10px;color:var(--text3)">N/A</span>
                @elseif($needsPige)
                    {{-- Pose réalisée mais pas de pige — action requise --}}
                    <a href="{{ route('admin.piges.index', ['campaign_id'=>$task->campaign_id,'panel_id'=>$task->panel_id]) }}"
                       class="pige-badge pige-badge-todo"
                       title="Pose réalisée — pige photo à ajouter">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                            <circle cx="12" cy="13" r="4"/>
                        </svg>
                        <span>À ajouter</span>
                    </a>
                @elseif($pigeCount > 0)
                    @php
                        $allVerified  = $pigeVerif > 0 && $pigeVerif >= $pigeCount;
                        $partlyVerif  = $pigeVerif > 0 && $pigeVerif < $pigeCount;
                        $stateClass   = $allVerified ? 'pige-badge-ok' : ($partlyVerif ? 'pige-badge-partial' : 'pige-badge-pending');
                        $stateTitle   = $allVerified
                            ? "Toutes les piges sont validées ({$pigeVerif}/{$pigeCount})"
                            : ($partlyVerif
                                ? "{$pigeVerif} pige(s) validée(s) sur {$pigeCount}"
                                : "{$pigeCount} pige(s) en attente de validation");
                    @endphp
                    <a href="{{ route('admin.piges.index', ['campaign_id'=>$task->campaign_id,'panel_id'=>$task->panel_id]) }}"
                       class="pige-badge {{ $stateClass }}"
                       title="{{ $stateTitle }}">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                            <circle cx="12" cy="13" r="4"/>
                        </svg>
                        <span class="pige-badge-count">{{ $pigeCount }}</span>
                        @if($pigeVerif > 0)
                        <span class="pige-badge-sep">·</span>
                        <span class="pige-badge-verif">
                            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                            {{ $pigeVerif }}
                        </span>
                        @endif
                    </a>
                @else
                    <span style="font-size:10px;color:var(--text3)">—</span>
                @endif
            </td>
            <td style="padding:10px 12px;min-width:160px">
                <span class="pose-status-pill" style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:20px;font-size:10px;font-weight:700;white-space:nowrap;background:{{ $sCfg['bg'] }};color:{{ $sCfg['c'] }};border:1px solid {{ $sCfg['bd'] }}">{!! $sIcon !!} <span class="pose-status-label">{{ $sCfg['l'] }}</span></span>
                @php $pct = (int) ($task->progress_percent ?? 0); @endphp
                @if($task->status !== 'annulee')
                    <div style="margin-top:6px;display:flex;align-items:center;gap:6px">
                        <div style="flex:1;height:5px;background:#f1f5f9;border-radius:999px;overflow:hidden">
                            <div class="pose-progress-fill"
                                 data-pose-progress="{{ $task->id }}"
                                 style="width:{{ $pct }}%;height:100%;background:{{ $task->progressColor() }};border-radius:999px;transition:width .35s ease, background .25s ease"></div>
                        </div>
                        <span class="pose-progress-text" style="font-family:ui-monospace,monospace;font-size:10px;color:var(--text2);font-weight:600;min-width:32px;text-align:right">{{ $pct }}%</span>
                    </div>
                @endif
                @if($task->whatsapp_sent_at)
                    <div style="font-size:9px;color:#22c55e;margin-top:2px;display:flex;align-items:center;gap:3px">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M20.5 3.5C18.2 1.2 15.2 0 12 0 5.4 0 0 5.4 0 12c0 2.1.6 4.2 1.6 6L0 24l6.2-1.6c1.7.9 3.7 1.5 5.7 1.5 6.6 0 12-5.4 12-12 0-3.2-1.2-6.2-3.4-8.4z"/></svg>
                        WhatsApp envoyé
                    </div>
                @endif
            </td>
            <td style="padding:10px 12px">
                <div style="display:flex;gap:6px;align-items:center">
                    @if(!in_array($task->status, ['realisee','annulee']))
                    <button class="action-btn action-btn-success" title="Marquer réalisée">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    </button>
                    <form method="POST" action="{{ route('admin.pose.complete', $task) }}" style="display:none">@csrf</form>
                    @endif
                    <a href="{{ route('admin.pose-tasks.show', $task) }}" class="action-btn" title="Voir">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </a>
                    @if(!in_array($task->status, ['realisee','annulee']))
                    {{-- Renvoyer le lien WhatsApp (si tech assigné + numéro) --}}
                    @if($task->assigned_user_id && $task->technicien?->whatsapp_number)
                    <form method="POST" action="{{ route('admin.pose-tasks.notify', $task) }}" style="display:inline">
                        @csrf
                        <button type="submit" class="action-btn" title="{{ $task->whatsapp_sent_at ? 'Renvoyer' : 'Envoyer' }} le lien WhatsApp à {{ $task->technicien->name }}"
                                style="color:#22c55e">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20.5 3.5C18.2 1.2 15.2 0 12 0 5.4 0 0 5.4 0 12c0 2.1.6 4.2 1.6 6L0 24l6.2-1.6c1.7.9 3.7 1.5 5.7 1.5 6.6 0 12-5.4 12-12 0-3.2-1.2-6.2-3.4-8.4z"/></svg>
                        </button>
                    </form>
                    @endif
                    <a href="{{ route('admin.pose-tasks.edit', $task) }}" class="action-btn" title="Modifier">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                    </a>
                    @endif
                    @if($task->campaign_id && $task->status === 'realisee')
                    <a href="{{ route('admin.piges.index', ['campaign_id'=>$task->campaign_id]) }}" class="action-btn action-btn-accent" title="Piges campagne">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    </a>
                    @endif
                </div>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif