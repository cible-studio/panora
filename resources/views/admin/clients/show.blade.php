@php
    // ── Split à la volée des noms importés "PERSONNE / ENTREPRISE" ──
    // Tant que la commande de correction (admin.clients.fix-import-names)
    // n'a pas été appliquée, certains clients ont encore un name au format
    // "ARMÈLE DJO / BEM" avec contact_name vide. On split visuellement
    // SANS toucher la base : entreprise = partie droite du dernier ' / ',
    // contact = partie gauche.
    $displayName    = $client->name;
    $displayContact = $client->contact_name;
    if (!$displayContact && str_contains($client->name, ' / ')) {
        $pos = mb_strrpos($client->name, ' / ');
        if ($pos !== false) {
            $displayContact = trim(mb_substr($client->name, 0, $pos));
            $displayName    = trim(mb_substr($client->name, $pos + 3));
        }
    }
@endphp

<x-admin-layout title="{{ $displayName }}">

<x-slot:topbarLeft>
    <a href="{{ route('admin.clients.index') }}" class="btn btn-ghost btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
        Retour
    </a>
</x-slot:topbarLeft>

<x-slot:topbarActions>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="{{ route('admin.clients.edit', $client) }}"
           class="btn btn-ghost btn-sm">✏️ Modifier</a>
        <button type="button"
                onclick="openDeleteClient({{ $client->id }}, '{{ addslashes($client->name) }}', {{ $client->hasActiveCampaigns() ? 1 : 0 }})"
                class="btn btn-ghost btn-sm"
                style="color:var(--red);border-color:var(--red);">
            🗑 Supprimer
        </button>
    </div>
</x-slot:topbarActions>

{{-- Breadcrumb --}}
<div style="font-size:12px;color:var(--text3);margin-bottom:16px;">
    <a href="{{ route('admin.clients.index') }}"
       style="color:var(--text3);text-decoration:none;">Clients</a>
    <span style="margin:0 6px;">›</span>
    <span style="color:var(--text);">{{ $displayName }}</span>
</div>

<div style="display:grid;grid-template-columns:300px 1fr;gap:16px;align-items:start;">

    {{-- Carte identité --}}
    <div style="background:var(--surface);border:1px solid var(--border);
                border-radius:14px;overflow:hidden;">

        {{-- Header carte --}}
        <div style="padding:24px;text-align:center;
                    border-bottom:1px solid var(--border);">
            <div style="width:60px;height:60px;border-radius:50%;background:var(--accent);
                        color:#000;display:flex;align-items:center;justify-content:center;
                        font-weight:800;font-size:24px;margin:0 auto 12px;">
                {{ strtoupper(mb_substr($displayName, 0, 1)) }}
            </div>
            <div style="font-weight:800;font-size:16px;color:var(--text);margin-bottom:6px;">
                {{ $displayName }}
            </div>
            @if($client->ncc)
            <div style="font-family:monospace;font-size:12px;background:var(--surface2);
                        padding:3px 10px;border-radius:20px;display:inline-block;
                        color:var(--text2);margin-bottom:8px;">
                {{ $client->ncc }}
            </div>
            @endif
            @if($client->sector)
            <div>
                <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;
                             background:var(--surface3);color:var(--text2);">
                    {{ $client->sector }}
                </span>
            </div>
            @endif
        </div>

        {{-- Infos --}}
        <div style="padding:16px 20px;">
            @foreach([
                ['👤 Contact',   $displayContact],
                ['📧 Email',     $client->email],
                ['📞 Téléphone', $client->phone],
                ['🏷️ Secteur',   $client->sector],
                ['📍 Adresse',   $client->address],
                ['📅 Depuis',    $client->created_at->format('d/m/Y')],
            ] as [$label, $value])
            <div style="display:flex;gap:10px;padding:8px 0;
                        border-bottom:1px solid var(--border);">
                <span style="font-size:11px;color:var(--text3);min-width:90px;
                             flex-shrink:0;padding-top:1px;">{{ $label }}</span>
                <span style="font-size:13px;color:var(--text2);word-break:break-word;">
                    {{ $value ?? '—' }}
                </span>
            </div>
            @endforeach
        </div>

        {{-- ── INTERLOCUTEURS — carnet d'adresses interne régie ────
             À ne pas confondre avec « Équipe espace client » plus bas :
             les interlocuteurs sont des contacts MÉTIER (nom + email +
             téléphone) que NOUS ajoutons côté régie pour savoir qui appeler
             chez le client. PAS de compte d'accès, PAS de connexion possible.
             Reçoivent éventuellement les notifications transactionnelles
             (factures, propositions) si `receives_notifications=true`.
             ────────────────────────────────────────────────── --}}
        <div style="padding:14px 20px;border-top:1px solid var(--border);">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text3);">
                    Interlocuteurs ({{ $client->contacts->count() }})
                </span>
                <button type="button" onclick="ClientContacts.openCreate()" class="btn btn-ghost btn-sm" title="Ajouter un interlocuteur" style="padding:3px 10px;font-size:11px;">
                    + Ajouter
                </button>
            </div>
            <div style="font-size:10px;color:var(--text3);margin-bottom:10px;line-height:1.4;">
                📒 Carnet d'adresses — personnes à <strong>contacter</strong> chez le client (téléphone, email). Pas de compte d'accès portail.
            </div>
            <div id="contacts-list" style="display:flex;flex-direction:column;gap:6px;">
                @forelse($client->contacts as $contact)
                    @include('admin.clients.partials.contact-item', ['contact' => $contact, 'client' => $client])
                @empty
                    <div id="contacts-empty" style="font-size:12px;color:var(--text3);font-style:italic;padding:8px 0;">
                        Aucun interlocuteur enregistré.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ── ÉQUIPE ESPACE CLIENT — comptes d'accès au portail ──
             Distinct du carnet d'interlocuteurs : ICI ce sont des
             comptes d'authentification (email + mot de passe) qui se
             connectent réellement à l'espace client Panora pour
             consulter campagnes, propositions, etc.
             Read-only côté admin : la gestion reste l'apanage de
             l'owner client (RBAC interne owner/member). ───────── --}}
        <div style="padding:14px 20px;border-top:1px solid var(--border);">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text3);">
                    Équipe espace client ({{ $client->users->count() }})
                </span>
                <span style="font-size:10px;color:var(--text3);" title="Géré uniquement côté client (par l'owner)">🔒 Read-only</span>
            </div>
            <div style="font-size:10px;color:var(--text3);margin-bottom:10px;line-height:1.4;">
                🔑 Comptes d'<strong>accès au portail</strong> — se connectent avec email + mot de passe pour consulter campagnes / propositions / piges.
            </div>
            @if($client->users->isEmpty())
                <div style="font-size:12px;color:var(--text3);font-style:italic;padding:8px 0;line-height:1.45;">
                    Aucun utilisateur côté client. Le client n'a pas encore créé de compte d'accès au portail.
                </div>
            @else
                <div style="display:flex;flex-direction:column;gap:6px;">
                    @foreach($client->users as $u)
                        @php
                            $roleCfg = $u->role === 'owner'
                                ? ['c'=>'#e20613', 'label'=>'Owner',  'title'=>'Gère l\'équipe + accepte les propositions']
                                : ['c'=>'#6b7280', 'label'=>'Member', 'title'=>'Lecture seule (campagnes, poses, piges, propositions)'];
                        @endphp
                        <div style="display:flex;align-items:center;gap:10px;padding:8px 10px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;{{ !$u->is_active ? 'opacity:.55;' : '' }}">
                            <div style="width:30px;height:30px;border-radius:50%;background:{{ $roleCfg['c'] }}1a;color:{{ $roleCfg['c'] }};display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0;">{{ mb_substr($u->name, 0, 1) }}</div>
                            <div style="flex:1;min-width:0;">
                                <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                    <span style="font-size:12px;font-weight:600;color:var(--text);">{{ $u->name }}</span>
                                    <span title="{{ $roleCfg['title'] }}"
                                          style="padding:1px 6px;border-radius:10px;font-size:9px;font-weight:700;background:{{ $roleCfg['c'] }}1a;color:{{ $roleCfg['c'] }};text-transform:uppercase;letter-spacing:.3px;">{{ $roleCfg['label'] }}</span>
                                    @if(!$u->is_active)
                                        <span style="padding:1px 6px;border-radius:10px;font-size:9px;font-weight:700;background:rgba(107,114,128,.15);color:#6b7280;">Désactivé</span>
                                    @endif
                                </div>
                                <div style="font-size:10px;color:var(--text3);font-family:monospace;">{{ $u->email }}</div>
                                <div style="font-size:10px;color:var(--text3);margin-top:2px;">
                                    @if($u->last_login_at)
                                        Dernière connexion : {{ $u->last_login_at->diffForHumans() }}
                                    @else
                                        Jamais connecté
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
            <div style="font-size:10px;color:var(--text3);margin-top:8px;line-height:1.45;">
                💡 Le client gère lui-même son équipe depuis son espace (page « Mon équipe »). L'admin ne peut ni ajouter ni modifier ces comptes.
            </div>
        </div>

        {{-- Lien nouvelle réservation --}}
        <div style="padding:16px 20px;border-top:1px solid var(--border);">
            <a href="{{ route('admin.reservations.disponibilites') }}"
               class="btn btn-primary" style="width:100%;text-align:center;display:block;">
                + Nouvelle réservation
            </a>
        </div>
    </div>

    {{-- Activité --}}
    <div style="display:flex;flex-direction:column;gap:16px;">

        {{-- Badges client récurrent / satisfaction --}}
        @php
            $campaignsCount     = $client->campaigns->count();
            $reservationsCount  = $client->reservations->count();
            $isRecurrent        = $campaignsCount > 1;
            // Note moyenne satisfaction (calculée si la table existe — sinon null)
            $satisfactionAvg = null;
            $satisfactionN   = 0;
            if (\Illuminate\Support\Facades\Schema::hasTable('satisfaction_surveys')) {
                $sStats = \DB::table('satisfaction_surveys')
                    ->where('client_id', $client->id)
                    ->whereNotNull('completed_at')
                    ->selectRaw('AVG(score_global) as avg, COUNT(*) as n')
                    ->first();
                if ($sStats && $sStats->n > 0) {
                    $satisfactionAvg = round((float) $sStats->avg, 1);
                    $satisfactionN   = (int) $sStats->n;
                }
            }
        @endphp

        @if($isRecurrent || $satisfactionAvg !== null)
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                @if($isRecurrent)
                    <span style="display:inline-flex;align-items:center;gap:6px;background:rgba(232,160,32,0.1);border:1px solid rgba(232,160,32,0.3);color:var(--accent);padding:6px 12px;border-radius:999px;font-size:12px;font-weight:600">
                        🔄 Client récurrent ({{ $campaignsCount }} campagnes)
                    </span>
                @endif
                @if($satisfactionAvg !== null)
                    @php
                        $satColor = $satisfactionAvg >= 4 ? '#22c55e' : ($satisfactionAvg >= 3 ? '#f59e0b' : '#ef4444');
                    @endphp
                    <span style="display:inline-flex;align-items:center;gap:6px;background:{{ $satColor }}1a;border:1px solid {{ $satColor }}55;color:{{ $satColor }};padding:6px 12px;border-radius:999px;font-size:12px;font-weight:600">
                        ⭐ {{ number_format($satisfactionAvg, 1, ',', '') }}/5 satisfaction ({{ $satisfactionN }} avis)
                    </span>
                @endif
            </div>
        @endif

        {{-- Analyse financière (déplacée depuis la liste) --}}
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
            @foreach([
                ['Réservations',  $reservationsCount,                                              'var(--text)'],
                ['Campagnes',     $campaignsCount,                                                 '#3b82f6'],
                ['CA Total',      number_format($totalFacture, 0, ',', ' ') . ' FCFA',             'var(--accent)'],
            ] as [$label, $value, $color])
            <div style="background:var(--surface);border:1px solid var(--border);
                        border-radius:12px;padding:16px;text-align:center;">
                <div style="font-size:20px;font-weight:800;color:{{ $color }};">
                    {{ $value }}
                </div>
                <div style="font-size:11px;color:var(--text3);margin-top:3px;font-weight:600;">
                    {{ $label }}
                </div>
            </div>
            @endforeach
        </div>

        {{-- Campagnes récentes --}}
        <div style="background:var(--surface);border:1px solid var(--border);
                    border-radius:14px;overflow:hidden;">
            <div style="padding:14px 18px;border-bottom:1px solid var(--border);
                        display:flex;align-items:center;justify-content:space-between;">
                <span style="font-weight:700;font-size:14px;">Campagnes récentes</span>
                <a href="{{ route('admin.campaigns.index', ['client_id' => $client->id]) }}"
                   style="font-size:12px;color:var(--accent);text-decoration:none;">
                    Voir toutes →
                </a>
            </div>
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:1px solid var(--border);">
                        @foreach(['Campagne','Période','Panneaux','Montant','Statut'] as $h)
                        <th style="padding:10px 16px;text-align:left;font-size:10px;
                                   font-weight:700;color:var(--text3);text-transform:uppercase;
                                   letter-spacing:.5px;">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($client->campaigns->take(8) as $campaign)
                    @php
                        $cs = match($campaign->status->value) {
                            'actif'   => ['#22c55e','rgba(34,197,94,0.1)','rgba(34,197,94,0.3)'],
                            'pose'    => ['#3b82f6','rgba(59,130,246,0.1)','rgba(59,130,246,0.3)'],
                            'termine' => ['#6b7280','rgba(107,114,128,0.1)','rgba(107,114,128,0.3)'],
                            'annule'  => ['#ef4444','rgba(239,68,68,0.1)','rgba(239,68,68,0.3)'],
                            default   => ['#6b7280','rgba(107,114,128,0.1)','rgba(107,114,128,0.3)'],
                        };
                    @endphp
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:12px 16px;">
                            <a href="{{ route('admin.campaigns.show', $campaign) }}"
                               style="font-weight:600;color:var(--text);text-decoration:none;
                                      font-size:13px;">
                                {{ $campaign->name }}
                            </a>
                        </td>
                        <td style="padding:12px 16px;font-size:12px;color:var(--text2);">
                            {{ $campaign->start_date->format('d/m/Y') }}
                            → {{ $campaign->end_date->format('d/m/Y') }}
                        </td>
                        <td style="padding:12px 16px;text-align:center;color:var(--text2);">
                            {{ $campaign->total_panels }}
                        </td>
                        <td style="padding:12px 16px;font-weight:700;color:var(--accent);
                                   font-size:13px;">
                            {{ number_format($campaign->total_amount, 0, ',', ' ') }}
                            <span style="font-size:10px;color:var(--text3);">FCFA</span>
                        </td>
                        <td style="padding:12px 16px;">
                            <span style="padding:3px 9px;border-radius:20px;font-size:11px;
                                         font-weight:600;background:{{ $cs[1] }};
                                         color:{{ $cs[0] }};border:1px solid {{ $cs[2] }};">
                                {{ ucfirst($campaign->status->value) }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5"
                            style="padding:40px;text-align:center;color:var(--text3);
                                   font-size:13px;">
                            Aucune campagne pour ce client.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ═══════════ ONGLET FINANCIER (Phase 6 cahier §10) ═══════════
             Synthèse : total facturé, total encaissé, solde dû, nb factures,
             nb impayés, retard moyen, historique des paiements + relances.
        ════════════════════════════════════════════════════════════════ --}}
        @php
            $clientInvoices = \App\Models\Invoice::where('client_id', $client->id)
                ->whereNotIn('status', ['annulee'])
                ->with(['payments', 'schedules'])
                ->orderByDesc('issued_at')
                ->get();

            $totalFactured = (int) $clientInvoices->sum(fn($i) => (int) ($i->total_a_payer ?: $i->amount_ttc));
            $totalPaid     = (int) $clientInvoices->sum(fn($i) => (int) $i->paidAmount());
            $soldeDu       = max(0, $totalFactured - $totalPaid);
            $countImpayes  = $clientInvoices->filter(fn($i) => $i->remainingAmount() > 0)->count();
            $countEnRetard = $clientInvoices->filter(fn($i) => $i->isOverdue())->count();
            $retardJours   = $clientInvoices
                ->filter(fn($i) => $i->isOverdue())
                ->map(function ($i) {
                    $next = $i->nextDueSchedule();
                    return $next ? abs($next->daysUntilDue()) : 0;
                })
                ->avg() ?: 0;
            $pctPaye = $totalFactured > 0 ? round($totalPaid / $totalFactured * 100, 1) : 0;
            $allPayments = $clientInvoices->flatMap->payments->sortByDesc('paid_at')->take(10);
            $allRelances = \App\Models\Relance::where('client_id', $client->id)
                ->orderByDesc('relance_date')->limit(10)->get();
        @endphp

        @if($clientInvoices->isNotEmpty())
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:18px">
            <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:linear-gradient(180deg,rgba(232,160,32,.04),rgba(232,160,32,0))">
                <span style="font-weight:800;font-size:14px">💰 Profil financier</span>
                <a href="{{ route('admin.invoices.index', ['client_id' => $client->id]) }}"
                   style="font-size:12px;color:var(--accent);text-decoration:none;font-weight:700">
                    Voir toutes les factures →
                </a>
            </div>
            <div style="padding:16px 18px">
                {{-- KPIs financiers --}}
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:14px">
                    <div style="padding:12px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:10px">
                        <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;font-weight:700">Total facturé</div>
                        <div style="font-size:16px;font-weight:800;color:var(--text);margin-top:3px">{{ number_format($totalFactured, 0, ',', ' ') }}</div>
                        <div style="font-size:10px;color:var(--text3)">{{ $clientInvoices->count() }} facture(s)</div>
                    </div>
                    <div style="padding:12px 14px;background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.20);border-radius:10px">
                        <div style="font-size:10px;color:#15803d;text-transform:uppercase;letter-spacing:.4px;font-weight:700">Total encaissé</div>
                        <div style="font-size:16px;font-weight:800;color:#16a34a;margin-top:3px">{{ number_format($totalPaid, 0, ',', ' ') }}</div>
                        <div style="font-size:10px;color:#15803d">{{ $pctPaye }} % du facturé</div>
                    </div>
                    <div style="padding:12px 14px;background:{{ $soldeDu > 0 ? 'rgba(239,68,68,.06)' : 'rgba(34,197,94,.06)' }};border:1px solid {{ $soldeDu > 0 ? 'rgba(239,68,68,.25)' : 'rgba(34,197,94,.20)' }};border-radius:10px">
                        <div style="font-size:10px;color:{{ $soldeDu > 0 ? '#b91c1c' : '#15803d' }};text-transform:uppercase;letter-spacing:.4px;font-weight:700">Solde dû</div>
                        <div style="font-size:16px;font-weight:800;color:{{ $soldeDu > 0 ? '#b91c1c' : '#16a34a' }};margin-top:3px">{{ number_format($soldeDu, 0, ',', ' ') }}</div>
                        <div style="font-size:10px;color:{{ $soldeDu > 0 ? '#b91c1c' : '#15803d' }}">{{ $countImpayes }} impayé(s)</div>
                    </div>
                    <div style="padding:12px 14px;background:{{ $countEnRetard > 0 ? 'rgba(239,68,68,.06)' : 'var(--surface2)' }};border:1px solid {{ $countEnRetard > 0 ? 'rgba(239,68,68,.25)' : 'var(--border)' }};border-radius:10px">
                        <div style="font-size:10px;color:{{ $countEnRetard > 0 ? '#b91c1c' : 'var(--text3)' }};text-transform:uppercase;letter-spacing:.4px;font-weight:700">En retard</div>
                        <div style="font-size:16px;font-weight:800;color:{{ $countEnRetard > 0 ? '#b91c1c' : 'var(--text)' }};margin-top:3px">{{ $countEnRetard }}</div>
                        <div style="font-size:10px;color:var(--text3)">Retard moyen {{ round($retardJours) }}j</div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                    {{-- Derniers versements --}}
                    <div>
                        <div style="font-size:11px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px">📥 Derniers versements ({{ $allPayments->count() }})</div>
                        @if($allPayments->isEmpty())
                            <div style="font-size:12px;color:var(--text3);padding:10px;background:var(--surface2);border-radius:8px;text-align:center">Aucun versement enregistré.</div>
                        @else
                            <div style="display:flex;flex-direction:column;gap:5px;max-height:240px;overflow-y:auto">
                                @foreach($allPayments as $p)
                                    <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 10px;background:var(--surface2);border-radius:7px;font-size:12px">
                                        <div>
                                            <div style="font-weight:700">{{ $p->mode_icon }} {{ number_format((float) $p->montant, 0, ',', ' ') }} F</div>
                                            <div style="font-size:10.5px;color:var(--text3)">{{ $p->paid_at->format('d/m/Y') }} · {{ $p->mode_label }}@if($p->is_acompte) · 🅰@endif</div>
                                        </div>
                                        <a href="{{ route('admin.invoices.show', $p->invoice_id) }}" style="font-size:10.5px;color:var(--accent);text-decoration:none">→</a>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Dernières relances --}}
                    <div>
                        <div style="font-size:11px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px">📞 Dernières relances ({{ $allRelances->count() }})</div>
                        @if($allRelances->isEmpty())
                            <div style="font-size:12px;color:var(--text3);padding:10px;background:var(--surface2);border-radius:8px;text-align:center">Aucune relance.</div>
                        @else
                            <div style="display:flex;flex-direction:column;gap:5px;max-height:240px;overflow-y:auto">
                                @foreach($allRelances as $r)
                                    <div style="padding:7px 10px;background:var(--surface2);border-radius:7px;font-size:12px">
                                        <div style="display:flex;justify-content:space-between;align-items:center">
                                            <span style="font-weight:700">{{ \App\Services\ReminderService::canalLabel($r->canal) }}</span>
                                            <span style="font-size:10.5px;color:var(--text3)">{{ $r->relance_date->format('d/m/Y') }}</span>
                                        </div>
                                        <div style="font-size:11px;color:var(--text2);margin-top:2px">{{ \Illuminate\Support\Str::limit($r->note, 80) }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Réservations récentes --}}
        @if($client->reservations->count() > 0)
        <div style="background:var(--surface);border:1px solid var(--border);
                    border-radius:14px;overflow:hidden;">
            <div style="padding:14px 18px;border-bottom:1px solid var(--border);
                        display:flex;align-items:center;justify-content:space-between;">
                <span style="font-weight:700;font-size:14px;">Réservations récentes</span>
                <a href="{{ route('admin.reservations.index', ['client_id' => $client->id]) }}"
                   style="font-size:12px;color:var(--accent);text-decoration:none;">
                    Voir toutes →
                </a>
            </div>
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:1px solid var(--border);">
                        @foreach(['Référence','Période','Panneaux','Montant','Statut'] as $h)
                        <th style="padding:10px 16px;text-align:left;font-size:10px;
                                   font-weight:700;color:var(--text3);text-transform:uppercase;
                                   letter-spacing:.5px;">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($client->reservations->take(5) as $reservation)
                    @php
                        $rs = match($reservation->status->value) {
                            'en_attente' => ['#e8a020','rgba(232,160,32,0.1)','rgba(232,160,32,0.3)'],
                            'confirme'   => ['#22c55e','rgba(34,197,94,0.1)','rgba(34,197,94,0.3)'],
                            'refuse'     => ['#ef4444','rgba(239,68,68,0.1)','rgba(239,68,68,0.3)'],
                            'annule'     => ['#6b7280','rgba(107,114,128,0.1)','rgba(107,114,128,0.3)'],
                            default      => ['#6b7280','rgba(107,114,128,0.1)','rgba(107,114,128,0.3)'],
                        };
                    @endphp
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:12px 16px;">
                            <a href="{{ route('admin.reservations.show', $reservation) }}"
                               style="font-family:monospace;font-size:12px;font-weight:700;
                                      color:var(--accent);text-decoration:none;">
                                {{ $reservation->reference }}
                            </a>
                        </td>
                        <td style="padding:12px 16px;font-size:12px;color:var(--text2);">
                            {{ $reservation->start_date->format('d/m/Y') }}
                            → {{ $reservation->end_date->format('d/m/Y') }}
                        </td>
                        <td style="padding:12px 16px;text-align:center;color:var(--text2);">
                            {{ $reservation->panels_count ?? '—' }}
                        </td>
                        <td style="padding:12px 16px;font-weight:700;color:var(--accent);
                                   font-size:13px;">
                            {{ number_format($reservation->total_amount, 0, ',', ' ') }}
                            <span style="font-size:10px;color:var(--text3);">FCFA</span>
                        </td>
                        <td style="padding:12px 16px;">
                            <span style="padding:3px 9px;border-radius:20px;font-size:11px;
                                         font-weight:600;background:{{ $rs[1] }};
                                         color:{{ $rs[0] }};border:1px solid {{ $rs[2] }};">
                                {{ $reservation->status->label() }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

    </div>
</div>

{{-- ══ INVENTAIRE PANNEAUX DU CLIENT ══ --}}
@if($panneauxClient->isNotEmpty())
<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <div class="card-title">🪧 Panneaux associés ({{ $panneauxClient->count() }})</div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Désignation</th>
                    <th>Commune</th>
                    <th>Format</th>
                    <th>Source</th>
                    <th>Période</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                @foreach($panneauxClient as $item)
                @php
                    $statusConfig = [
                        'actif'      => ['label' => 'Actif',       'bg' => 'rgba(34,197,94,0.1)',   'color' => '#22c55e', 'border' => 'rgba(34,197,94,0.3)'],
                        'confirme'   => ['label' => 'Confirmé',    'bg' => 'rgba(34,197,94,0.1)',   'color' => '#22c55e', 'border' => 'rgba(34,197,94,0.3)'],
                        'en_attente' => ['label' => 'Option',      'bg' => 'rgba(232,160,32,0.1)',  'color' => '#e8a020', 'border' => 'rgba(232,160,32,0.3)'],
                        'option'     => ['label' => 'Option',      'bg' => 'rgba(232,160,32,0.1)',  'color' => '#e8a020', 'border' => 'rgba(232,160,32,0.3)'],
                        'pose'       => ['label' => 'Pose en cours','bg'=> 'rgba(59,130,246,0.1)',  'color' => '#3b82f6', 'border' => 'rgba(59,130,246,0.3)'],
                        'termine'    => ['label' => 'Terminé',     'bg' => 'rgba(107,114,128,0.1)', 'color' => '#6b7280', 'border' => 'rgba(107,114,128,0.3)'],
                        'annule'     => ['label' => 'Annulé',      'bg' => 'rgba(239,68,68,0.1)',   'color' => '#ef4444', 'border' => 'rgba(239,68,68,0.3)'],
                    ];
                    $sc = $statusConfig[$item['status']] ?? ['label' => ucfirst($item['status']), 'bg' => 'rgba(107,114,128,0.1)', 'color' => '#6b7280', 'border' => 'rgba(107,114,128,0.3)'];
                @endphp
                <tr onmouseover="this.style.background='var(--surface2)'"
                    onmouseout="this.style.background=''">
                    <td>
                        <span style="font-family:monospace;font-weight:700;color:var(--accent);font-size:12px;">
                            {{ $item['panel']->reference }}
                        </span>
                    </td>
                    <td style="font-weight:500;font-size:13px;">
                        {{ $item['panel']->name }}
                    </td>
                    <td style="font-size:12px;color:var(--text2);">
                        {{ $item['panel']->commune?->name ?? '—' }}
                    </td>
                    <td style="font-size:12px;color:var(--text2);">
                        {{ $item['panel']->format?->name ?? '—' }}
                    </td>
                    <td>
                        <!-- @if($item['source'] === 'campaign') -->
                            <a href="{{ route('admin.campaigns.show', $item['source_id']) }}"
                               style="font-size:11px;color:#3b82f6;text-decoration:none;font-weight:600;">
                                📢 {{ $item['reference_source'] }}
                            </a>
                        <!-- @else
                            <a href="{{ route('admin.reservations.show', $item['source_id']) }}"
                               style="font-size:11px;color:var(--text2);text-decoration:none;font-weight:600;">
                                📋 {{ $item['reference_source'] }}
                            </a>
                        @endif -->
                    </td>
                    <td style="font-size:11px;color:var(--text3);white-space:nowrap;">
                        {{ \Carbon\Carbon::parse($item['start_date'])->format('d/m/Y') }}
                        → {{ \Carbon\Carbon::parse($item['end_date'])->format('d/m/Y') }}
                    </td>
                    <td>
                        <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;
                                     background:{{ $sc['bg'] }};color:{{ $sc['color'] }};
                                     border:1px solid {{ $sc['border'] }};
                                     text-transform:uppercase;letter-spacing:.5px;">
                            {{ $sc['label'] }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Modal suppression --}}
<div id="modal-delete-client" class="modal-overlay" style="display:none"
     onclick="if(event.target===this) closeDeleteClient()">
    <div class="modal" style="max-width:420px" onclick="event.stopPropagation()">
        <div class="modal-header">
            <div class="modal-title" style="color:var(--red)">🗑 Supprimer le client</div>
            <button class="modal-close" onclick="closeDeleteClient()">✕</button>
        </div>
        <div class="modal-body" style="text-align:center;padding:28px 22px;">
            <div style="font-size:44px;margin-bottom:12px;">👥</div>
            <div style="font-weight:700;font-size:15px;margin-bottom:8px;">
                Supprimer <span id="del-client-name"
                                style="color:var(--accent);"></span> ?
            </div>
            <div id="del-client-warning"
                 style="display:none;background:rgba(239,68,68,.08);
                        border:1px solid rgba(239,68,68,.2);border-radius:8px;
                        padding:10px;font-size:12px;color:var(--red);margin-bottom:12px;">
                ⚠️ Ce client a des campagnes actives. La suppression sera bloquée par le serveur.
            </div>
            <div style="font-size:13px;color:var(--text2);margin-bottom:14px;">
                Le client sera archivé. Ses données historiques seront conservées.
            </div>
            <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);
                        border-radius:8px;padding:10px;font-size:12px;color:var(--red);">
                ⚠️ Ses réservations passeront en lecture seule.
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeDeleteClient()">Annuler</button>
            <form id="del-client-form" method="POST" style="display:inline">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger">🗑 Supprimer</button>
            </form>
        </div>
    </div>
</div>

{{-- ────────── MODAL INTERLOCUTEUR ────────── --}}
<div id="contact-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9000;align-items:center;justify-content:center;padding:16px;"
     onclick="if(event.target===this)ClientContacts.close()">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;width:100%;max-width:520px;max-height:92vh;display:flex;flex-direction:column;overflow:hidden;"
         onclick="event.stopPropagation()">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
            <h3 id="contact-modal-title" style="font-size:15px;font-weight:700;">Nouvel interlocuteur</h3>
            <button type="button" onclick="ClientContacts.close()" style="background:none;border:none;cursor:pointer;font-size:18px;color:var(--text3);">✕</button>
        </div>
        <form id="contact-form" onsubmit="return ClientContacts.submit(event)" style="padding:18px 20px;overflow-y:auto;flex:1;">
            <input type="hidden" name="contact_id" id="contact_id" value="">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div style="grid-column:1/3;">
                    <label class="filter-label">Nom *</label>
                    <input type="text" name="name" id="contact-name-input" required maxlength="120"
                        class="filter-input" style="width:100%;" placeholder="Ex: Jean Kouassi">
                </div>
                <div>
                    <label class="filter-label">Email</label>
                    <input type="email" name="email" id="contact-email" maxlength="150"
                        class="filter-input" style="width:100%;" placeholder="email@exemple.com">
                </div>
                <div>
                    <label class="filter-label">Téléphone</label>
                    <input type="text" name="phone" id="contact-phone" maxlength="30"
                        class="filter-input" style="width:100%;" placeholder="07 07 07 07 07">
                </div>
                <div>
                    <label class="filter-label">Rôle</label>
                    <select name="role" id="contact-role" class="filter-select" style="width:100%;">
                        @foreach(\App\Models\ClientContact::ROLES as $code => $label)
                            <option value="{{ $code }}" @if($code === 'autre') selected @endif>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="filter-label">Fonction (libre)</label>
                    <input type="text" name="position" id="contact-position" maxlength="100"
                        class="filter-input" style="width:100%;" placeholder="Ex: Directeur marketing">
                </div>
                <div style="grid-column:1/3;">
                    <label class="filter-label">Notes</label>
                    <textarea name="notes" id="contact-notes" maxlength="1000" rows="2"
                        class="filter-input" style="width:100%;resize:vertical;font-family:inherit;" placeholder="Préférences de contact, dispo, langue…"></textarea>
                </div>
                <div style="grid-column:1/3;display:flex;gap:18px;">
                    <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text2);cursor:pointer;">
                        <input type="checkbox" name="is_primary" id="contact-primary" value="1">
                        Contact principal
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text2);cursor:pointer;">
                        <input type="checkbox" name="receives_notifications" id="contact-notif" value="1" checked>
                        Reçoit les notifications
                    </label>
                </div>
            </div>
        </form>
        <div style="padding:14px 20px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end;background:var(--surface2);">
            <button type="button" onclick="ClientContacts.close()" class="btn btn-ghost btn-sm">Annuler</button>
            <button type="button" id="contact-submit-btn" onclick="document.getElementById('contact-form').requestSubmit()" class="btn btn-primary btn-sm">Enregistrer</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openDeleteClient(id, name, activeCampaigns) {
    document.getElementById('del-client-name').textContent = name;
    document.getElementById('del-client-form').action = `/admin/clients/${id}`;
    document.getElementById('del-client-warning').style.display =
        activeCampaigns > 0 ? 'block' : 'none';
    document.getElementById('modal-delete-client').style.display = 'flex';
}
function closeDeleteClient() {
    document.getElementById('modal-delete-client').style.display = 'none';
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeDeleteClient();
        if (document.getElementById('contact-modal')?.style.display === 'flex') {
            ClientContacts.close();
        }
    }
});

// ══════════════════════════════
// CLIENT CONTACTS (multi-interlocuteurs)
// ══════════════════════════════
window.ClientContacts = (function () {
    const clientId = {{ $client->id }};
    const csrf     = '{{ csrf_token() }}';
    const modal    = document.getElementById('contact-modal');
    const list     = document.getElementById('contacts-list');

    function showToast(message, type = 'success') {
        let host = document.getElementById('client-toast-host');
        if (!host) {
            host = document.createElement('div');
            host.id = 'client-toast-host';
            host.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;';
            document.body.appendChild(host);
        }
        const colors = type === 'error'
            ? { bg: '#fee2e2', fg: '#991b1b', bd: '#fca5a5' }
            : { bg: '#dcfce7', fg: '#166534', bd: '#86efac' };
        const t = document.createElement('div');
        t.textContent = message;
        t.style.cssText = `padding:10px 14px;background:${colors.bg};color:${colors.fg};border:1px solid ${colors.bd};border-radius:8px;font-size:13px;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,.08);min-width:240px;max-width:380px;`;
        host.appendChild(t);
        setTimeout(() => { t.style.transition = 'opacity .3s'; t.style.opacity = '0'; }, 2700);
        setTimeout(() => t.remove(), 3100);
    }

    function resetForm() {
        document.getElementById('contact_id').value = '';
        document.getElementById('contact-name-input').value = '';
        document.getElementById('contact-email').value = '';
        document.getElementById('contact-phone').value = '';
        document.getElementById('contact-role').value = 'autre';
        document.getElementById('contact-position').value = '';
        document.getElementById('contact-notes').value = '';
        document.getElementById('contact-primary').checked = false;
        document.getElementById('contact-notif').checked = true;
    }

    function fillForm(row) {
        document.getElementById('contact_id').value = row.dataset.contactId;
        const card = row;
        // On lit depuis le DOM rendu pour ne pas avoir à conserver un cache JS
        document.getElementById('contact-name-input').value = card.querySelector('span[style*="font-weight:700"]')?.textContent.trim() || '';
        // Pour l'édition complète on charge les champs depuis l'API ? Non, on
        // recharge plus tard. Pour cette première version, on demande à l'admin
        // de re-saisir les champs via l'edit (suffisant pour MVP).
    }

    return {
        openCreate() {
            resetForm();
            document.getElementById('contact-modal-title').textContent = 'Nouvel interlocuteur';
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            setTimeout(() => document.getElementById('contact-name-input').focus(), 50);
        },
        openEdit(contactId) {
            const row = list.querySelector(`[data-contact-id="${contactId}"]`);
            if (!row) return;
            resetForm();
            document.getElementById('contact_id').value = contactId;
            // Pré-remplir depuis le DOM
            const name = row.querySelector('span[style*="font-weight:700"]')?.textContent.trim() || '';
            document.getElementById('contact-name-input').value = name;
            const emailLink = row.querySelector('a[href^="mailto:"]');
            if (emailLink) document.getElementById('contact-email').value = emailLink.getAttribute('href').replace('mailto:', '');
            // Phone : extrait du span après "📞"
            const phoneSpan = Array.from(row.querySelectorAll('span')).find(s => s.textContent.includes('📞'));
            if (phoneSpan) document.getElementById('contact-phone').value = phoneSpan.textContent.replace('📞', '').trim();
            // Primary actuel
            document.getElementById('contact-primary').checked = !!row.querySelector('span[style*="rgba(232,160,32"]');

            document.getElementById('contact-modal-title').textContent = 'Modifier l\'interlocuteur';
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            setTimeout(() => document.getElementById('contact-name-input').focus(), 50);
        },
        close() {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        },
        async submit(event) {
            event.preventDefault();
            const btn = document.getElementById('contact-submit-btn');
            btn.disabled = true;
            btn.textContent = '...';

            const id = document.getElementById('contact_id').value;
            const isUpdate = !!id;
            const url = isUpdate
                ? `/admin/clients/${clientId}/contacts/${id}`
                : `/admin/clients/${clientId}/contacts`;

            const fd = new FormData();
            fd.append('_token', csrf);
            if (isUpdate) fd.append('_method', 'PUT');
            fd.append('name',     document.getElementById('contact-name-input').value);
            fd.append('email',    document.getElementById('contact-email').value);
            fd.append('phone',    document.getElementById('contact-phone').value);
            fd.append('role',     document.getElementById('contact-role').value);
            fd.append('position', document.getElementById('contact-position').value);
            fd.append('notes',    document.getElementById('contact-notes').value);
            fd.append('is_primary', document.getElementById('contact-primary').checked ? '1' : '0');
            fd.append('receives_notifications', document.getElementById('contact-notif').checked ? '1' : '0');

            try {
                const r = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: fd,
                });

                if (r.status === 422) {
                    const data = await r.json().catch(() => ({}));
                    const first = data.errors ? Object.values(data.errors).flat()[0] : (data.message || 'Données invalides.');
                    showToast(first, 'error');
                    return false;
                }

                const data = await r.json();
                if (!data.ok) {
                    showToast(data.message || 'Erreur.', 'error');
                    return false;
                }

                this.close();
                showToast(data.message);
                // Recharger la fiche pour avoir le HTML serveur à jour avec
                // toutes les variantes (badge primary, ordre alphabétique).
                window.location.reload();
            } catch (e) {
                console.error(e);
                showToast('Erreur réseau.', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Enregistrer';
            }
            return false;
        },
        async setPrimary(contactId) {
            try {
                const r = await fetch(`/admin/clients/${clientId}/contacts/${contactId}/primary`, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: new URLSearchParams({ _method: 'PATCH', _token: csrf }),
                });
                const data = await r.json();
                if (!r.ok || !data.ok) {
                    showToast(data.message || 'Erreur.', 'error');
                    return;
                }
                showToast(data.message);
                window.location.reload();
            } catch (e) {
                showToast('Erreur réseau.', 'error');
            }
        },
        async remove(contactId, name) {
            if (!confirm(`Supprimer l'interlocuteur « ${name} » ?`)) return;
            try {
                const r = await fetch(`/admin/clients/${clientId}/contacts/${contactId}`, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: new URLSearchParams({ _method: 'DELETE', _token: csrf }),
                });
                const data = await r.json();
                if (!r.ok || !data.ok) {
                    showToast(data.message || 'Erreur.', 'error');
                    return;
                }
                document.querySelector(`.contact-item[data-contact-id="${contactId}"]`)?.remove();
                showToast(data.message);
            } catch (e) {
                showToast('Erreur réseau.', 'error');
            }
        },
    };
})();
</script>
@endpush

</x-admin-layout>
