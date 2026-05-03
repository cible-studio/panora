<x-admin-layout title="Tâche {{ $poseTask->panel?->reference }}">

<x-slot:topbarLeft>
    <a href="{{ route('admin.pose-tasks.index') }}" class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:5px">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Retour
    </a>
</x-slot:topbarLeft>

<x-slot:topbarActions>
    @if(!in_array($poseTask->status, ['realisee','annulee']))
    <a href="{{ route('admin.pose-tasks.edit', $poseTask) }}" class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:5px">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
        Modifier
    </a>
    <button type="button"
            onclick="Confirm.show('Marquer la pose du panneau <strong>{{ $poseTask->panel?->reference }}</strong> comme réalisée ?', 'warning', function(){ document.getElementById(\'form-complete\').submit(); })"
            class="btn btn-primary btn-sm" style="display:flex;align-items:center;gap:5px">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Marquer réalisée
    </button>
    <form id="form-complete" method="POST" action="{{ route('admin.pose.complete', $poseTask) }}" style="display:none">@csrf</form>
    @endif
</x-slot:topbarActions>

@php
$sCfg = match($poseTask->status) {
    'planifiee' => ['c'=>'#e8a020','bg'=>'rgba(232,160,32,.08)','bd'=>'rgba(232,160,32,.3)','l'=>'Planifiée'],
    'en_cours'  => ['c'=>'#3b82f6','bg'=>'rgba(59,130,246,.08)', 'bd'=>'rgba(59,130,246,.3)', 'l'=>'En cours'],
    'realisee'  => ['c'=>'#22c55e','bg'=>'rgba(34,197,94,.08)',  'bd'=>'rgba(34,197,94,.3)',  'l'=>'Réalisée'],
    'annulee'   => ['c'=>'#ef4444','bg'=>'rgba(239,68,68,.08)',  'bd'=>'rgba(239,68,68,.3)',  'l'=>'Annulée'],
    default     => ['c'=>'#6b7280','bg'=>'rgba(107,114,128,.08)','bd'=>'rgba(107,114,128,.3)','l'=>$poseTask->status],
};
$sIconLg = match($poseTask->status) {
    'planifiee' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
    'en_cours'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
    'realisee'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>',
    'annulee'   => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
    default     => '',
};
@endphp

{{-- ════ ALERTE RETARD ════ --}}
@if($isLate)
<div style="background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.25);border-radius:12px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:12px">
    <div style="width:36px;height:36px;background:rgba(239,68,68,.15);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    </div>
    <div>
        <div style="font-size:13px;font-weight:700;color:#ef4444">Tâche en retard</div>
        <div style="font-size:11px;color:rgba(239,68,68,.75);margin-top:2px">
            Planifiée le {{ $poseTask->scheduled_at?->format('d/m/Y à H:i') }}
            ({{ $poseTask->scheduled_at?->diffForHumans() }}) — non réalisée
        </div>
    </div>
    @if(!in_array($poseTask->status, ['realisee','annulee']))
    <button type="button" onclick="Confirm.show('Marquer la pose comme réalisée malgré le retard ?', 'warning', function(){ document.getElementById(\'form-complete\').submit(); })"
            style="margin-left:auto;flex-shrink:0;padding:7px 14px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#ef4444;border-radius:9px;font-size:11px;font-weight:700;cursor:pointer">
        Marquer réalisée quand même
    </button>
    @endif
</div>
@endif

{{-- ════ ALERTES SYSTÈME LIÉES ════ --}}
@if($taskAlerts->isNotEmpty())
<div style="display:flex;flex-direction:column;gap:6px;margin-bottom:16px">
    @foreach($taskAlerts as $alert)
    @php
    $ac = match($alert->niveau ?? 'info') {
        'danger'  => ['c'=>'#ef4444','bg'=>'rgba(239,68,68,.07)','bd'=>'rgba(239,68,68,.25)'],
        'warning' => ['c'=>'#f97316','bg'=>'rgba(249,115,22,.07)','bd'=>'rgba(249,115,22,.25)'],
        default   => ['c'=>'#3b82f6','bg'=>'rgba(59,130,246,.07)','bd'=>'rgba(59,130,246,.25)'],
    };
    $alertIcon = match($alert->niveau ?? 'info') {
        'danger'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
        'warning' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>',
        default   => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
    };
    @endphp
    <div style="background:{{ $ac['bg'] }};border:1px solid {{ $ac['bd'] }};border-radius:10px;padding:10px 14px;display:flex;align-items:flex-start;gap:10px">
        <span style="color:{{ $ac['c'] }};flex-shrink:0;margin-top:1px">{!! $alertIcon !!}</span>
        <div style="flex:1;min-width:0">
            <div style="font-size:12px;font-weight:700;color:{{ $ac['c'] }}">{{ $alert->title }}</div>
            <div style="font-size:11px;color:{{ $ac['c'] }};opacity:.8;margin-top:2px">{{ $alert->message }}</div>
            @if($alert->created_at)
            <div style="font-size:10px;color:var(--text3);margin-top:3px">{{ $alert->created_at->diffForHumans() }}</div>
            @endif
        </div>
        <form method="POST" action="{{ route('admin.alerts.read', $alert) }}" style="flex-shrink:0">
            @csrf
            <button type="submit" style="background:none;border:none;color:{{ $ac['c'] }};opacity:.55;cursor:pointer;font-size:11px;padding:0;display:flex;align-items:center;gap:3px" title="Marquer comme lu">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Lu
            </button>
        </form>
    </div>
    @endforeach
</div>
@endif

<div style="display:grid;grid-template-columns:1fr 300px;gap:16px;align-items:start">

    {{-- ══ INFOS PRINCIPALES ══ --}}
    <div style="display:flex;flex-direction:column;gap:14px">

        {{-- Card principale --}}
        <div class="d-card">
            <div class="d-card-header">
                <div>
                    <div style="font-size:15px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                        Tâche #{{ $poseTask->id }}
                    </div>
                    <div style="font-size:11px;color:var(--text3);margin-top:3px">
                        Panneau <span style="font-family:monospace;color:var(--accent);font-weight:700">{{ $poseTask->panel?->reference }}</span>
                    </div>
                </div>
                <span style="display:inline-flex;align-items:center;gap:6px;padding:5px 14px;border-radius:20px;font-size:12px;font-weight:700;background:{{ $sCfg['bg'] }};color:{{ $sCfg['c'] }};border:1px solid {{ $sCfg['bd'] }}">
                    {!! $sIconLg !!} {{ $sCfg['l'] }}
                </span>
            </div>

            <div style="padding:18px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
                @php
                $fields = [
                    ['PANNEAU',      $poseTask->panel?->reference.' — '.($poseTask->panel?->name ?? '—'), null, route('admin.panels.show', $poseTask->panel)],
                    ['COMMUNE',      $poseTask->panel?->commune?->name ?? '—', null, null],
                    ['CAMPAGNE',     $poseTask->campaign?->name ?? 'Sans campagne', null, $poseTask->campaign ? route('admin.campaigns.show', $poseTask->campaign) : null],
                    ['STATUT CAMP.', $poseTask->campaign?->status?->label() ?? '—', $poseTask->campaign?->status?->uiConfig()['color'] ?? null, null],
                    ['TECHNICIEN',   $poseTask->technicien?->name ?? 'Non assigné', null, null],
                    ['ÉQUIPE',       $poseTask->team_name ?? '—', null, null],
                    ['PLANIFIÉ LE',  $poseTask->scheduled_at?->format('d/m/Y à H:i') ?? '—', $isLate ? '#ef4444' : null, null],
                    ['RÉALISÉ LE',   $poseTask->done_at?->format('d/m/Y à H:i') ?? '—', $poseTask->done_at ? '#22c55e' : null, null],
                ];
                @endphp
                @foreach($fields as [$label, $value, $color, $link])
                <div>
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text3);margin-bottom:4px">{{ $label }}</div>
                    @if($link)
                    <a href="{{ $link }}" style="font-size:13px;font-weight:500;color:{{ $color ?? 'var(--accent)' }};text-decoration:none;line-height:1.4;display:block">{{ $value }}</a>
                    @else
                    <div style="font-size:13px;font-weight:500;color:{{ $color ?? 'var(--text)' }};line-height:1.4">{{ $value }}</div>
                    @endif
                </div>
                @endforeach
            </div>

            @if($poseTask->notes)
            <div style="padding:0 18px 18px">
                <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text3);margin-bottom:6px">NOTES</div>
                <div style="font-size:12px;color:var(--text2);background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 12px;line-height:1.6;white-space:pre-wrap">{{ $poseTask->notes }}</div>
            </div>
            @endif

            {{-- ─── Section WhatsApp + Suivi temps réel ─────────────── --}}
            @php
                $hasToken    = !empty($poseTask->public_token);
                $publicUrl   = $hasToken ? route('pose.public.show', $poseTask->public_token) : null;
                $tech        = $poseTask->technicien;
                $waSent      = $poseTask->whatsapp_sent_at;
                $progress    = (int) ($poseTask->progress_percent ?? 0);
                // Note : progressColor est une méthode, pas un attribut → toujours appelée avec ()
                $progColor   = method_exists($poseTask, 'progressColor')
                    ? $poseTask->progressColor()
                    : '#ef4444';
            @endphp

            <div style="margin:0 18px 18px;border-top:1px solid var(--border);padding-top:14px">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="#22c55e" aria-hidden="true">
                        <path d="M20.5 3.5C18.2 1.2 15.2 0 12 0 5.4 0 0 5.4 0 12c0 2.1.6 4.2 1.6 6L0 24l6.2-1.6c1.7.9 3.7 1.5 5.7 1.5 6.6 0 12-5.4 12-12 0-3.2-1.2-6.2-3.4-8.4z"/>
                    </svg>
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text2)">
                        Suivi technicien (WhatsApp)
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:12px">
                    {{-- Numéro technicien --}}
                    <div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 12px">
                        <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text3);margin-bottom:4px">📱 Num. WhatsApp</div>
                        @if($tech?->whatsapp_number)
                            @php $waFormatted = app(\App\Services\WhatsAppService::class)->format($tech->whatsapp_number); @endphp
                            <div style="font-family:ui-monospace,monospace;font-size:13px;color:#22c55e;font-weight:600">{{ $waFormatted }}</div>
                        @elseif($tech)
                            <div style="font-size:12px;color:#ef4444">⚠️ Non renseigné — <a href="{{ route('admin.users.edit', $tech) }}" style="color:var(--accent);text-decoration:underline">configurer</a></div>
                        @else
                            <div style="font-size:12px;color:var(--text3);font-style:italic">Aucun technicien assigné</div>
                        @endif
                    </div>

                    {{-- Statut envoi --}}
                    <div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 12px">
                        <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text3);margin-bottom:4px">📤 Notification</div>
                        @if($waSent)
                            <div style="font-size:12px;color:#22c55e;font-weight:600">
                                ✓ Envoyée
                                <span style="color:var(--text3);font-weight:normal;font-size:11px">({{ $waSent->diffForHumans() }})</span>
                            </div>
                        @else
                            <div style="font-size:12px;color:#f59e0b">⏸ Non envoyée</div>
                        @endif
                    </div>
                </div>

                {{-- Progression actuelle --}}
                <div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:12px">
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                        <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text3)">📊 Progression rapportée</div>
                        <div style="font-family:ui-monospace,monospace;font-size:13px;color:var(--text);font-weight:700">{{ $progress }} %</div>
                    </div>
                    <div style="height:8px;background:#f1f5f9;border-radius:999px;overflow:hidden">
                        <div style="width:{{ $progress }}%;height:100%;background:{{ $progColor ?? '#ef4444' }};transition:width .3s"></div>
                    </div>
                    <div style="display:flex;gap:14px;margin-top:8px;font-size:11px;color:var(--text3)">
                        <div>Démarré : <strong style="color:var(--text)">{{ $poseTask->started_at?->format('d/m/Y H:i') ?? '—' }}</strong></div>
                        <div>Terminé : <strong style="color:var(--text)">{{ $poseTask->done_at?->format('d/m/Y H:i') ?? '—' }}</strong></div>
                        @if($poseTask->real_minutes)
                            <div>Durée : <strong style="color:var(--accent)">{{ $poseTask->real_minutes }} min</strong></div>
                        @endif
                    </div>
                </div>

                {{-- Lien public technicien + actions --}}
                @if($hasToken)
                    <div style="margin-top:12px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:10px 12px">
                        <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#9a3412;margin-bottom:4px">🔗 Lien personnel technicien</div>
                        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                            <input type="text" id="pose-public-url" readonly value="{{ $publicUrl }}"
                                   style="flex:1;min-width:200px;font-family:ui-monospace,monospace;font-size:11px;background:#fff;border:1px solid #fed7aa;border-radius:5px;padding:5px 8px;color:#9a3412">
                            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('pose-public-url').value).then(()=>this.textContent='✓ Copié')"
                                    class="btn btn-ghost btn-sm" style="font-size:11px">📋 Copier</button>
                            @if($tech?->whatsapp_number)
                                @php
                                    $waText = "Pose CIBLE CI - {$poseTask->panel?->reference}\nMettez à jour votre avancement : {$publicUrl}";
                                    $waLink = 'https://wa.me/' . $tech->whatsapp_number . '?text=' . urlencode($waText);
                                @endphp
                                <a href="{{ $waLink }}" target="_blank" rel="noopener"
                                   class="btn btn-ghost btn-sm" style="font-size:11px;color:#22c55e;border-color:rgba(34,197,94,.4)">
                                    💬 Ouvrir WhatsApp
                                </a>
                            @endif
                        </div>
                        <div style="font-size:10px;color:#9a3412;margin-top:6px;line-height:1.45">
                            Ce lien permet au technicien de mettre à jour sa progression depuis son téléphone, sans login.
                            La barre de progression ci-dessus se met à jour automatiquement (rafraîchissement 30s).
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Card piges --}}
        @if($pigeStats)
        <div class="d-card">
            <div class="d-card-header">
                <div style="font-size:13px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    Piges photos
                </div>
                <a href="{{ route('admin.piges.index', ['campaign_id'=>$poseTask->campaign_id,'panel_id'=>$poseTask->panel_id]) }}"
                   style="font-size:11px;color:var(--accent);font-weight:600;text-decoration:none;padding:4px 10px;background:rgba(232,160,32,.08);border:1px solid rgba(232,160,32,.25);border-radius:8px">
                    Gérer →
                </a>
            </div>
            <div style="padding:16px 18px">
                @if($pigeStats['total'] === 0)
                    @if($poseTask->status === 'realisee')
                    <div style="text-align:center;padding:16px">
                        <div style="font-size:36px;opacity:.25;margin-bottom:10px">📷</div>
                        <div style="font-size:13px;font-weight:700;color:#f97316;margin-bottom:6px">Aucune pige enregistrée</div>
                        <div style="font-size:12px;color:var(--text3);margin-bottom:14px">La pose est réalisée mais sans preuve d'affichage. Le client ne peut pas être facturé.</div>
                        <a href="{{ route('admin.piges.index', ['campaign_id'=>$poseTask->campaign_id]) }}"
                           style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:#e8a020;color:#000;border-radius:10px;font-size:12px;font-weight:700;text-decoration:none">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                            Ajouter une pige maintenant
                        </a>
                    </div>
                    @else
                    <div style="font-size:12px;color:var(--text3);text-align:center;padding:12px">La pige sera ajoutée après la réalisation de la pose.</div>
                    @endif
                @else
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:12px">
                    @foreach([['Total','total','#e8a020'],['Vérifiées','verifie','#22c55e'],['En attente','en_attente','#f97316'],['Rejetées','rejete','#ef4444']] as [$l,$k,$c])
                    <div style="text-align:center;padding:12px 8px;background:var(--surface2);border-radius:10px;border:1px solid var(--border)">
                        <div style="font-size:22px;font-weight:800;color:{{ $c }};line-height:1">{{ $pigeStats[$k] }}</div>
                        <div style="font-size:9px;color:var(--text3);margin-top:4px;text-transform:uppercase;letter-spacing:.4px;font-weight:700">{{ $l }}</div>
                    </div>
                    @endforeach
                </div>
                @if($pigeStats['verifie'] > 0)
                <div style="padding:9px 12px;background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.2);border-radius:9px;font-size:12px;color:#22c55e;display:flex;align-items:center;gap:8px">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    {{ $pigeStats['verifie'] }} pige(s) vérifiée(s) — preuve d'affichage conforme.
                </div>
                @endif
                @if($pigeStats['rejete'] > 0)
                <div style="padding:9px 12px;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);border-radius:9px;font-size:12px;color:#ef4444;display:flex;align-items:center;gap:8px;margin-top:6px">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg>
                    {{ $pigeStats['rejete'] }} pige(s) rejetée(s) — le technicien doit soumettre de nouvelles photos.
                </div>
                @endif
                @if($pigeStats['en_attente'] > 0)
                <div style="padding:9px 12px;background:rgba(249,115,22,.06);border:1px solid rgba(249,115,22,.2);border-radius:9px;font-size:12px;color:#f97316;display:flex;align-items:center;gap:8px;margin-top:6px">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    {{ $pigeStats['en_attente'] }} pige(s) en attente de vérification.
                </div>
                @endif
                @endif
            </div>
        </div>
        @endif

    </div>

    {{-- ══ SIDEBAR ══ --}}
    <div style="display:flex;flex-direction:column;gap:12px">

        {{-- Actions --}}
        <div class="d-card">
            <div class="d-card-header"><div style="font-size:13px;font-weight:600">Actions rapides</div></div>
            <div style="padding:14px 16px;display:flex;flex-direction:column;gap:6px">
                @if($poseTask->panel)
                <a href="{{ route('admin.panels.show', $poseTask->panel) }}" class="sb-action">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                    Voir le panneau
                </a>
                @endif
                @if($poseTask->campaign)
                <a href="{{ route('admin.campaigns.show', $poseTask->campaign) }}" class="sb-action">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l19-9-9 19-2-8-8-2z"/></svg>
                    Voir la campagne
                </a>
                @if(!$poseTask->campaign->status->isTerminal())
                <a href="{{ route('admin.piges.index', ['campaign_id'=>$poseTask->campaign_id,'panel_id'=>$poseTask->panel_id]) }}" class="sb-action sb-action-accent">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    Piges de ce panneau
                </a>
                @endif
                @endif

                @if(!in_array($poseTask->status, ['realisee','annulee']))
                <div style="border-top:1px solid var(--border);padding-top:6px;margin-top:2px;display:flex;flex-direction:column;gap:6px">
                    <a href="{{ route('admin.pose-tasks.edit', $poseTask) }}" class="sb-action">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                        Modifier la tâche
                    </a>
                    <button type="button"
                            onclick="Confirm.show('Supprimer définitivement la tâche du panneau <strong>{{ $poseTask->panel?->reference }}</strong> ? Cette action est irréversible.', 'danger', function(){ document.getElementById(\'form-destroy\').submit(); })"
                            class="sb-action sb-action-danger" style="width:100%;text-align:left;cursor:pointer">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                        Supprimer la tâche
                    </button>
                    <form id="form-destroy" method="POST" action="{{ route('admin.pose-tasks.destroy', $poseTask) }}" style="display:none">@csrf @method('DELETE')</form>
                </div>
                @endif
            </div>
        </div>

        {{-- Métadonnées --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 16px">
            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:10px">Informations</div>
            <div style="display:flex;flex-direction:column;gap:7px">
                @foreach([['ID','#'.$poseTask->id],['Créée le',$poseTask->created_at->format('d/m/Y H:i')],['Modifiée le',$poseTask->updated_at->format('d/m/Y H:i')]] as [$l,$v])
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span style="font-size:11px;color:var(--text3)">{{ $l }}</span>
                    <span style="font-size:11px;color:var(--text2);font-family:monospace">{{ $v }}</span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Progression campagne --}}
        @if($poseTask->campaign && $poseTask->campaign->start_date && $poseTask->campaign->end_date)
        @php
        $prog = min(100, $poseTask->campaign->progressPercent());
        $remaining = $poseTask->campaign->daysRemaining();
        $endSoon = $poseTask->campaign->isEndingSoon();
        @endphp
        <div style="background:var(--surface);border:1px solid {{ $endSoon ? 'rgba(249,115,22,.3)' : 'var(--border)' }};border-radius:14px;padding:14px 16px">
            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:10px">Campagne</div>
            <div style="font-size:12px;font-weight:600;color:var(--text2);margin-bottom:8px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $poseTask->campaign->name }}</div>
            <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text3);margin-bottom:5px">
                <span>{{ $poseTask->campaign->start_date->format('d/m/Y') }}</span>
                <span style="color:{{ $endSoon ? '#f97316' : 'var(--text3)' }};font-weight:{{ $endSoon ? '700' : '400' }}">
                    {{ $remaining >= 0 ? $remaining.' j. restants' : abs($remaining).' j. dépassés' }}
                </span>
                <span>{{ $poseTask->campaign->end_date->format('d/m/Y') }}</span>
            </div>
            <div style="background:var(--surface2);border-radius:20px;height:5px;overflow:hidden">
                <div style="background:{{ $endSoon ? '#f97316' : 'var(--accent)' }};height:100%;width:{{ $prog }}%;border-radius:20px;transition:width .3s"></div>
            </div>
        </div>
        @endif

    </div>
</div>

{{-- ════ MODAL CONFIRMATION ════ --}}
<div id="modal-confirm" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:16px">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:18px;width:100%;max-width:400px;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.4)">
        <div style="padding:20px 22px 16px">
            <div id="modal-confirm-icon" style="width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;margin-bottom:14px"></div>
            <div id="modal-confirm-title" style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:8px"></div>
            <div id="modal-confirm-body" style="font-size:13px;color:var(--text2);line-height:1.5"></div>
        </div>
        <div style="padding:14px 22px 20px;display:flex;gap:8px;justify-content:flex-end">
            <button onclick="Confirm.cancel()" style="padding:8px 18px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;color:var(--text2);cursor:pointer;font-weight:500">Annuler</button>
            <button id="modal-confirm-btn" style="padding:8px 20px;border:none;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer"></button>
        </div>
    </div>
</div>

<style>
.d-card { background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden }
.d-card-header { padding:12px 16px;background:var(--surface2);border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center }
.sb-action { display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:10px;font-size:12px;color:var(--text2);text-decoration:none;background:var(--surface2);border:1px solid var(--border);transition:border-color .15s }
.sb-action:hover { border-color:var(--accent);color:var(--accent) }
.sb-action-accent { background:rgba(232,160,32,.06);border-color:rgba(232,160,32,.2);color:var(--accent) }
.sb-action-danger { background:rgba(239,68,68,.04);border-color:rgba(239,68,68,.15);color:#ef4444 }
.sb-action-danger:hover { border-color:rgba(239,68,68,.4) }
</style>

@push('scripts')
<script>
window.Confirm = {
    _cb: null,
    show(body, type = 'confirm', callback) {
        this._cb = callback;
        const cfg = {
            confirm: { icon:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>', ibg:'rgba(59,130,246,.12)', btnBg:'#3b82f6', btnTxt:'Confirmer', title:'Confirmer l\'action' },
            danger:  { icon:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>', ibg:'rgba(239,68,68,.12)', btnBg:'#ef4444', btnTxt:'Supprimer', title:'Confirmer la suppression' },
            warning: { icon:'<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>', ibg:'rgba(249,115,22,.12)', btnBg:'#f97316', btnTxt:'Confirmer', title:'Confirmer l\'action' },
        };
        const c = cfg[type] || cfg.confirm;
        const el = id => document.getElementById('modal-confirm-' + id);
        el('icon').innerHTML = c.icon; el('icon').style.background = c.ibg;
        el('title').textContent = c.title; el('body').innerHTML = body;
        el('btn').textContent = c.btnTxt; el('btn').style.background = c.btnBg; el('btn').style.color = '#fff';
        el('btn').onclick = () => { this.cancel(); callback?.(); };
        const m = document.getElementById('modal-confirm');
        m.style.display = 'flex';
        setTimeout(() => el('btn').focus(), 50);
    },
    cancel() { document.getElementById('modal-confirm').style.display='none'; this._cb=null; },
};
document.getElementById('modal-confirm').addEventListener('click', function(e) { if(e.target===this) Confirm.cancel(); });
document.addEventListener('keydown', e => { if(e.key==='Escape') Confirm.cancel(); });
</script>
@endpush
</x-admin-layout>