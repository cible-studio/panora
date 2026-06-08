@php
    $statusCfg     = $campaign->status->uiConfig();
    $isRunning     = $campaign->status->value === 'actif';
    $daysLeft      = $campaign->daysRemaining();
    $pct           = $isRunning ? $campaign->progressPercent() : 0;
    $endingSoon    = $campaign->isEndingSoon();
    // Bug #1 fix : la fiche détail affichait "À facturer" même sur planifie,
    // mais la liste ne le proposait qu'aux actif/termine — incohérent. On
    // étend à planifie/pause. annule/termine sans facture restent muettes
    // (rien à facturer si campagne annulée; termine sans facture = oubli
    // intentionnel ou déjà clos).
    $isNonFacturee = in_array($campaign->status->value, ['planifie','actif','pause','termine'])
        && ($campaign->invoices_count ?? 0) === 0
        && (float) ($campaign->total_amount ?? 0) > 0;
    $isCancelledRow = $campaign->status->value === 'annule';

    $latestInvoice = $campaign->invoices->first();
    $invoiceCfg = $latestInvoice ? match($latestInvoice->status) {
        'brouillon' => ['icon'=>'📝','label'=>'Brouillon','color'=>'#6b7280','bg'=>'rgba(107,114,128,0.1)'],
        'envoyee'   => ['icon'=>'📤','label'=>'Envoyée','color'=>'#3b82f6','bg'=>'rgba(59,130,246,0.1)'],
        'payee'     => ['icon'=>'✅','label'=>'Payée','color'=>'#22c55e','bg'=>'rgba(34,197,94,0.1)'],
        'annulee'   => ['icon'=>'🚫','label'=>'Annulée','color'=>'#ef4444','bg'=>'rgba(239,68,68,0.1)'],
        default     => ['icon'=>'','label'=>$latestInvoice->status,'color'=>'var(--text2)','bg'=>'var(--surface2)'],
    } : null;

    $barColor = $pct >= 90 ? '#ef4444' : ($pct >= 70 ? '#e8a020' : '#22c55e');

    $isCancelled = $campaign->status->value === 'annule';
    $cancelLabels = [
        'budget' => 'Budget insuffisant', 'zone' => 'Zone non pertinente',
        'strategie' => 'Changement de stratégie', 'report' => 'Report de campagne',
        'concurrent' => 'Choix concurrent', 'autre' => 'Autre',
    ];
    $cancelTip = $isCancelled
        ? trim(($cancelLabels[$campaign->cancellation_reason] ?? '') . ($campaign->cancellation_notes ? ' — ' . $campaign->cancellation_notes : ''))
        : null;
@endphp
<tr data-campaign-row="{{ $campaign->id }}" style="{{ $endingSoon ? 'background:rgba(232,160,32,0.03);' : '' }}">
    @if(in_array(auth()->user()?->role?->value, ['admin', 'mediaplanner'], true))
    <td style="text-align:center;">
        <input type="checkbox" class="bulk-checkbox"
               value="{{ $campaign->id }}"
               data-status="{{ $campaign->status->value }}"
               aria-label="Sélectionner {{ $campaign->name }}">
    </td>
    @endif
    <td class="col-campaign">
        <a href="{{ route('admin.campaigns.show', $campaign) }}" class="campaign-name" title="{{ $campaign->name }}">
            {{ $campaign->name }}
        </a>
        @if($endingSoon)
        <div class="days-left" style="color:var(--accent);">⚠️ Dans {{ $daysLeft }} jour(s)</div>
        @endif
        @if($isCancelled && $cancelTip)
        <div title="{{ $cancelTip }}" style="display:inline-flex;align-items:center;gap:4px;margin-top:4px;font-size:11px;color:#ef4444;cursor:help;">
            🚫 {{ $cancelLabels[$campaign->cancellation_reason] ?? 'Annulée' }}
        </div>
        @endif
    </td>
    <td class="col-client">
        @if($campaign->client?->trashed())
        <span class="client-deleted" title="{{ $campaign->client->name }}">{{ $campaign->client->name ?? '—' }}</span>
        <span class="deleted-badge">Supprimé</span>
        @else
        <a href="{{ route('admin.clients.show', $campaign->client) }}" class="client-link" title="{{ $campaign->client?->name }}">
            {{ $campaign->client?->name ?? '—' }}
        </a>
        @endif
    </td>
    {{-- Période sur 1 seule ligne avec année 2 chiffres (gagne ~30% de
         largeur sur l'ancien format dd/mm/YYYY → dd/mm/YYYY). --}}
    <td class="date-range">
        {{ $campaign->start_date->format('d/m/y') }}
        <span>→</span>
        {{ $campaign->end_date->format('d/m/y') }}
    </td>
    <td class="duration">{{ $campaign->durationHuman() }}</td>
    <td class="text-center">
        <span class="badge-panels">{{ ($campaign->panels_count ?? 0) + ($campaign->external_panels_count ?? 0) }} 🪧</span>
    </td>
    <td class="amount">
        @if($campaign->total_amount !== null && (float) $campaign->total_amount > 0)
            {{ number_format($campaign->total_amount, 0, ',', ' ') }} <span>FCFA HT</span>
        @else
            <span class="badge-muted">—</span>
        @endif
    </td>
    <td>
        <span class="status-badge" style="background:{{ $statusCfg['bg'] }};color:{{ $statusCfg['color'] }};border-color:{{ $statusCfg['border'] }}">
            {{ $statusCfg['icon'] }} {{ $campaign->status->label() }}
        </span>
        @if($isRunning)
        <div class="progress-bar">
            <div class="progress-fill" style="background:{{ $barColor }}; width:{{ $pct }}%;"></div>
        </div>
        <div class="days-left">{{ number_format($pct, 1, ',', '') }}% écoulé · {{ $daysLeft }} {{ $daysLeft > 1 ? 'jours' : 'jour' }} restant{{ $daysLeft > 1 ? 's' : '' }}</div>
        @endif
    </td>
    @php
        // Facturation : visible pour admin + commercial. Cachée pour MP.
        $rowAuthRole = auth()->user()?->role?->value;
        $rowCanSeeBilling = in_array($rowAuthRole, ['admin', 'commercial'], true);

        // Commercial suivant le dossier — priorité campaign.commercial_user_id,
        // fallback résa source. On n'affiche QUE si le user est commercial
        // ou admin (un MP/technicien ne doit jamais apparaître dans cette
        // colonne, c'est réservé au suivi commercial).
        $rowCommercial = null;
        $candidate = $campaign->resolveCommercialContact()
                  ?? $campaign->reservation?->resolveCommercialContact();
        if ($candidate) {
            $candidateRole = $candidate->role?->value ?? $candidate->role;
            if (in_array($candidateRole, ['admin', 'commercial'], true)) {
                $rowCommercial = $candidate;
            }
        }
    @endphp
    @if($rowCanSeeBilling)
    <td>
        @if($latestInvoice)
            <button type="button"
                    class="billing-btn"
                    style="background:{{ $invoiceCfg['bg'] }};color:{{ $invoiceCfg['color'] }};border:1px solid {{ $invoiceCfg['color'] }}22;"
                    onclick="openBillingModal({{ $campaign->id }}, '{{ addslashes($campaign->name) }}', {{ $campaign->total_amount }}, '{{ $latestInvoice->status }}', '{{ number_format((float)$latestInvoice->amount_ttc,0,',','') }}', '{{ $latestInvoice->paid_at?->format('Y-m-d') ?? '' }}')"
                    title="Gérer la facturation">
                {{ $invoiceCfg['icon'] }} {{ $invoiceCfg['label'] }}
                @if($latestInvoice->paid_at) <span style="font-size:9px;opacity:0.7;display:block;">{{ $latestInvoice->paid_at->format('d/m/Y') }}</span> @endif
            </button>
        @elseif($isNonFacturee)
            <button type="button"
                    class="billing-btn billing-btn--new"
                    onclick="openBillingModal({{ $campaign->id }}, '{{ addslashes($campaign->name) }}', {{ $campaign->total_amount }}, '', '', '')"
                    title="Créer une facture">
                💰 À facturer
            </button>
        @else
            <span class="badge-muted">—</span>
        @endif
    </td>
    @endif
    <td class="col-commercial">
        @if($rowCommercial)
            {{-- Email retiré de l'affichage : trop large, déjà accessible
                 depuis la fiche détail. On le met juste en tooltip. --}}
            <div style="font-weight:500" title="{{ $rowCommercial->email ?? $rowCommercial->name }}">{{ $rowCommercial->name }}</div>
        @else
            <span class="badge-muted">—</span>
        @endif
    </td>
    <td>
        <div class="actions">
            <a href="{{ route('admin.campaigns.show', $campaign) }}" class="btn-icon" title="Voir">👁</a>
            @can('update', $campaign)
                @if($campaign->status->value === 'termine')
                    {{-- TERMINÉE : edit form refusé par le controller. On désactive
                         le bouton et on renvoie sur la fiche détail où un bouton
                         dédié "Renommer" (modal) est dispo (saisie d'historique). --}}
                    <a href="{{ route('admin.campaigns.show', $campaign) }}#section-actions"
                       class="btn-icon"
                       style="opacity:.6"
                       title="Campagne terminée — utilise « Renommer » sur la fiche">✏️</a>
                @else
                    <a href="{{ route('admin.campaigns.edit', $campaign) }}" class="btn-icon" title="Modifier">✏️</a>
                @endif
            @endcan
            @can('delete', $campaign)
                @if($isCancelledRow)
                    <button class="btn-icon btn-delete"
                            onclick="openDeleteCampaign({{ $campaign->id }}, '{{ addslashes($campaign->name) }}')"
                            title="Supprimer définitivement (campagne annulée)">🗑</button>
                @else
                    {{-- delete() service refuse si pas annulée. Bouton désactivé
                         pour éviter le clic dans le vide + tooltip explicatif. --}}
                    <button type="button"
                            class="btn-icon"
                            disabled
                            style="opacity:.4;cursor:not-allowed"
                            title="Suppression réservée aux campagnes annulées. Annule d'abord la campagne.">🗑</button>
                @endif
            @endcan
        </div>
    </td>
</tr>
