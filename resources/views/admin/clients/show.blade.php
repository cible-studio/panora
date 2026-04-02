<x-admin-layout title="{{ $client->name }}">
<x-slot:topbarActions>
    <div style="display:flex;gap:8px">
        <a href="{{ route('admin.clients.edit', $client) }}" class="btn btn-ghost btn-sm">✏️ Modifier</a>
        <button onclick="openDeleteClient({{ $client->id }}, '{{ addslashes($client->name) }}', {{ $client->hasActiveCampaigns() ? 1 : 0 }})"
                class="btn btn-ghost btn-sm" style="color:var(--red);border-color:rgba(239,68,68,.3)">
            🗑 Supprimer
        </button>
    </div>
</x-slot:topbarActions>

{{-- ── BREADCRUMB ── --}}
<nav style="font-size:12px;color:var(--text3);margin-bottom:20px;display:flex;align-items:center;gap:6px">
    <a href="{{ route('admin.clients.index') }}" style="color:var(--text3);text-decoration:none;transition:color .15s" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--text3)'">Clients</a>
    <span style="opacity:.4">›</span>
    <span>{{ $client->name }}</span>
</nav>

{{-- ── LAYOUT PRINCIPAL ── --}}
<div class="cs-layout">

    {{-- ── COLONNE GAUCHE : Identité ── --}}
    <aside class="cs-sidebar">

        {{-- Carte identité --}}
        <div class="cs-card">
            <div class="cs-avatar-section">
                @php
                    $colors = ['#e8a020','#22c55e','#3b82f6','#ec4899','#8b5cf6','#06b6d4'];
                    $color  = $colors[crc32($client->name) % count($colors)];
                @endphp
                <div class="cs-avatar" style="background:{{ $color }}18;border:2px solid {{ $color }}40;color:{{ $color }}">
                    {{ strtoupper(mb_substr($client->name, 0, 1)) }}
                </div>
                <div class="cs-name">{{ $client->name }}</div>
                @if($client->ncc)
                    <div class="cs-ncc">{{ $client->ncc }}</div>
                @endif
                @if($client->sector)
                    <span class="cs-sector-badge">{{ $client->sector }}</span>
                @endif
            </div>

            <div class="cs-info-list">
                @foreach([
                    ['👤','Contact',   $client->contact_name],
                    ['📧','Email',     $client->email],
                    ['📞','Téléphone', $client->phone],
                    ['📍','Adresse',   $client->address],
                    ['📅','Client depuis', $client->created_at->format('d/m/Y')],
                ] as [$icon, $label, $value])
                @if($value)
                <div class="cs-info-row">
                    <div class="cs-info-icon">{{ $icon }}</div>
                    <div>
                        <div class="cs-info-label">{{ $label }}</div>
                        <div class="cs-info-value">{{ $value }}</div>
                    </div>
                </div>
                @endif
                @endforeach
            </div>

            <div class="cs-card-footer">
                <a href="{{ route('admin.reservations.disponibilites') }}" class="btn btn-primary" style="width:100%;text-align:center;display:block">
                    + Nouvelle réservation
                </a>
            </div>
        </div>

        {{-- Gestion Compte ── --}}
        <div class="cs-card cs-account-card {{ $client->hasAccount() ? 'has-account' : '' }}">
            <div class="cs-account-header">
                <div>
                    <div class="cs-account-title">🏢 Espace Client</div>
                    <div class="cs-account-sub">Accès au portail client</div>
                </div>
                @if($client->hasAccount())
                    <span class="cs-badge-active">● Actif</span>
                @else
                    <span class="cs-badge-inactive">○ Inactif</span>
                @endif
            </div>

            @if($client->hasAccount())
                <div class="cs-account-info">
                    @if($client->last_login_at)
                        <div class="cs-account-meta">
                            <span>Dernière connexion</span>
                            <strong>{{ $client->last_login_at->diffForHumans() }}</strong>
                        </div>
                    @endif
                    @if($client->must_change_password)
                        <div class="cs-badge-warning">⚠️ Doit changer son MDP</div>
                    @endif
                    @if($client->password_changed_at)
                        <div class="cs-account-meta">
                            <span>MDP changé le</span>
                            <strong>{{ $client->password_changed_at->format('d/m/Y') }}</strong>
                        </div>
                    @endif
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
                    <form method="POST" action="{{ route('admin.clients.account.reset', $client) }}"
                          onsubmit="return confirm('Envoyer un nouveau mot de passe temporaire à {{ addslashes($client->name) }} ?')">
                        @csrf
                        <button type="submit" class="cs-btn-sm cs-btn-blue">🔑 Reset MDP</button>
                    </form>
                    <form method="POST" action="{{ route('admin.clients.account.revoke', $client) }}"
                          onsubmit="return confirm('Révoquer l\'accès de {{ addslashes($client->name) }} ?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="cs-btn-sm cs-btn-red">🚫 Révoquer</button>
                    </form>
                </div>
            @else
                <div class="cs-account-empty">
                    @if(empty($client->email))
                        <p>Ajoutez un email pour créer un compte.</p>
                    @else
                        <p>Aucun accès espace client configuré.</p>
                        <form method="POST" action="{{ route('admin.clients.account.create', $client) }}"
                              onsubmit="return confirm('Créer un compte pour {{ addslashes($client->name) }} ({{ $client->email }}) ?')">
                            @csrf
                            <button type="submit" class="cs-btn-sm cs-btn-gold">📧 Créer le compte</button>
                        </form>
                    @endif
                </div>
            @endif
        </div>

    </aside>

    {{-- ── COLONNE DROITE : Activité ── --}}
    <main class="cs-main">

        {{-- Stats ── --}}
        <div class="cs-stats">
            @php
                $campCount = $client->campaigns->count();
                $resCount  = $client->reservations->count();
                $actifs    = $client->campaigns->filter(fn($c) => in_array($c->status->value, ['actif','pose']))->count();
            @endphp
            <div class="cs-stat-box">
                <div class="cs-stat-num">{{ $resCount }}</div>
                <div class="cs-stat-lbl">Réservations</div>
            </div>
            <div class="cs-stat-box">
                <div class="cs-stat-num" style="color:#3b82f6">{{ $campCount }}</div>
                <div class="cs-stat-lbl">Campagnes</div>
                @if($actifs > 0)
                    <div style="font-size:10px;color:#22c55e;margin-top:2px">{{ $actifs }} active(s)</div>
                @endif
            </div>
            <div class="cs-stat-box" style="border-color:rgba(232,160,32,.2)">
                <div class="cs-stat-num" style="color:var(--accent);font-size:18px">{{ number_format($totalFacture, 0, ',', ' ') }}</div>
                <div class="cs-stat-lbl">FCFA facturés</div>
            </div>
        </div>

        {{-- Campagnes ── --}}
        <div class="cs-card cs-table-card">
            <div class="cs-table-header">
                <span class="cs-table-title">📢 Campagnes récentes</span>
                <a href="{{ route('admin.campaigns.index', ['client_id' => $client->id]) }}" class="cs-link">Voir toutes →</a>
            </div>
            @if($client->campaigns->isEmpty())
                <div class="cs-empty">
                    <div style="font-size:32px;opacity:.3;margin-bottom:8px">📢</div>
                    Aucune campagne pour ce client.
                </div>
            @else
            <div class="cs-responsive-table">
                <table class="cs-table">
                    <thead>
                        <tr>
                            <th>Campagne</th>
                            <th class="ci-hide-sm">Période</th>
                            <th class="ci-hide-sm" style="text-align:center">Panneaux</th>
                            <th>Montant</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($client->campaigns->take(8) as $campaign)
                        @php
                            $cfg = $campaign->status->uiConfig();
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.campaigns.show', $campaign) }}"
                                   style="font-weight:600;font-size:13px;color:var(--text);text-decoration:none;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px">
                                    {{ $campaign->name }}
                                </a>
                            </td>
                            <td class="ci-hide-sm" style="font-size:12px;color:var(--text2);white-space:nowrap">
                                {{ $campaign->start_date->format('d/m/Y') }} → {{ $campaign->end_date->format('d/m/Y') }}
                            </td>
                            <td class="ci-hide-sm" style="text-align:center;font-size:13px;color:var(--text2)">
                                {{ $campaign->total_panels }}
                            </td>
                            <td style="font-weight:700;font-size:13px;white-space:nowrap">
                                <span style="color:var(--accent)">{{ number_format($campaign->total_amount, 0, ',', ' ') }}</span>
                                <span style="font-size:10px;color:var(--text3)"> FCFA</span>
                            </td>
                            <td>
                                <span style="padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600;white-space:nowrap;background:{{ $cfg['bg'] }};color:{{ $cfg['color'] }};border:1px solid {{ $cfg['border'] }}">
                                    {{ $campaign->status->label() }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        {{-- Réservations ── --}}
        @if($client->reservations->count() > 0)
        <div class="cs-card cs-table-card">
            <div class="cs-table-header">
                <span class="cs-table-title">📋 Réservations récentes</span>
                <a href="{{ route('admin.reservations.index', ['client_id' => $client->id]) }}" class="cs-link">Voir toutes →</a>
            </div>
            <div class="cs-responsive-table">
                <table class="cs-table">
                    <thead>
                        <tr>
                            <th>Référence</th>
                            <th class="ci-hide-sm">Période</th>
                            <th class="ci-hide-sm" style="text-align:center">Panneaux</th>
                            <th>Montant</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($client->reservations->take(5) as $res)
                        @php
                            $cfg = $res->status->uiConfig();
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.reservations.show', $res) }}"
                                   style="font-family:monospace;font-size:12px;font-weight:700;color:var(--accent);text-decoration:none">
                                    {{ $res->reference }}
                                </a>
                                {{-- Badge proposition --}}
                                @if($res->proposition_token && $res->status->value === 'en_attente')
                                    <div style="display:inline-flex;align-items:center;gap:3px;margin-left:6px;background:rgba(232,160,32,.1);border:1px solid rgba(232,160,32,.25);color:#e8a020;border-radius:4px;padding:1px 6px;font-size:9px;font-weight:700;vertical-align:middle">
                                        {{ $res->proposition_viewed_at ? '👁 Prop. vue' : '📤 Prop.' }}
                                    </div>
                                @endif
                            </td>
                            <td class="ci-hide-sm" style="font-size:12px;color:var(--text2);white-space:nowrap">
                                {{ $res->start_date->format('d/m/Y') }} → {{ $res->end_date->format('d/m/Y') }}
                            </td>
                            <td class="ci-hide-sm" style="text-align:center;font-size:13px;color:var(--text2)">
                                {{ $res->panels_count ?? '—' }}
                            </td>
                            <td style="font-weight:700;font-size:13px;white-space:nowrap">
                                <span style="color:var(--accent)">{{ number_format($res->total_amount, 0, ',', ' ') }}</span>
                                <span style="font-size:10px;color:var(--text3)"> FCFA</span>
                            </td>
                            <td>
                                <span style="padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600;white-space:nowrap;background:{{ $cfg['bg'] }};color:{{ $cfg['color'] }};border:1px solid {{ $cfg['border'] }}">
                                    {{ $res->status->label() }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

    </main>
</div>

{{-- ══ MODAL SUPPRESSION ══ --}}
<div id="modal-delete" class="ci-modal-overlay" onclick="CI.modal.close('modal-delete')">
    <div class="ci-modal" style="max-width:400px" onclick="event.stopPropagation()">
        <div class="ci-modal-icon ci-modal-icon-danger">🗑</div>
        <h3 class="ci-modal-title">Supprimer le client</h3>
        <p class="ci-modal-desc">
            Supprimer <strong id="del-name" class="ci-text-gold"></strong> ?<br>
            Le client sera archivé. L'historique sera conservé.
        </p>
        <div id="del-warning" class="ci-alert ci-alert-danger" style="display:none">
            ⚠️ Ce client a des campagnes actives.
        </div>
        <div class="ci-alert ci-alert-danger" style="font-size:12px">
            Ses réservations passeront en lecture seule.
        </div>
        <div class="ci-modal-footer">
            <button class="ci-btn ci-btn-ghost" onclick="CI.modal.close('modal-delete')">Annuler</button>
            <form id="del-form" method="POST" style="display:inline">
                @csrf @method('DELETE')
                <button type="submit" class="ci-btn ci-btn-danger">🗑 Confirmer</button>
            </form>
        </div>
    </div>
</div>

{{-- ══ TOAST ══ --}}
<div id="ci-toast-container" style="position:fixed;bottom:24px;right:24px;z-index:1000;display:flex;flex-direction:column;gap:8px;pointer-events:none"></div>

<style>
/* ── LAYOUT ── */
.cs-layout{display:grid;grid-template-columns:300px 1fr;gap:16px;align-items:start}
@media(max-width:900px){.cs-layout{grid-template-columns:1fr}}

/* ── CARD ── */
.cs-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:14px}
.cs-card-footer{padding:16px;border-top:1px solid var(--border)}

/* ── AVATAR ── */
.cs-avatar-section{padding:24px;text-align:center;border-bottom:1px solid var(--border)}
.cs-avatar{width:60px;height:60px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:24px;margin:0 auto 12px}
.cs-name{font-weight:800;font-size:16px;color:var(--text);margin-bottom:6px}
.cs-ncc{font-family:monospace;font-size:11px;background:var(--surface2);border:1px solid var(--border);border-radius:20px;padding:2px 10px;display:inline-block;color:var(--text3);margin-bottom:8px}
.cs-sector-badge{background:var(--surface3,#1f2840);border:1px solid var(--border);border-radius:20px;padding:3px 12px;font-size:11px;font-weight:600;color:var(--text2)}

/* ── INFO LIST ── */
.cs-info-list{padding:12px 16px}
.cs-info-row{display:flex;gap:10px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.04)}
.cs-info-row:last-child{border-bottom:none}
.cs-info-icon{width:28px;height:28px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:14px;background:var(--surface2);border-radius:7px}
.cs-info-label{font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-bottom:1px}
.cs-info-value{font-size:13px;color:var(--text2);word-break:break-word}

/* ── ACCOUNT CARD ── */
.cs-account-card{padding:16px 18px}
.cs-account-card.has-account{border-color:rgba(34,197,94,.2);background:rgba(34,197,94,.02)}
.cs-account-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px}
.cs-account-title{font-size:13px;font-weight:700;color:var(--text)}
.cs-account-sub{font-size:11px;color:var(--text3);margin-top:2px}
.cs-badge-active{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#22c55e;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:700;white-space:nowrap}
.cs-badge-inactive{background:rgba(107,114,128,.1);border:1px solid rgba(107,114,128,.2);color:var(--text3);border-radius:20px;padding:2px 10px;font-size:11px;font-weight:600}
.cs-badge-warning{background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.2);color:#fde68a;border-radius:6px;padding:4px 10px;font-size:11px;margin:8px 0}
.cs-account-meta{display:flex;justify-content:space-between;font-size:12px;color:var(--text3);padding:5px 0;border-bottom:1px solid rgba(255,255,255,.04)}
.cs-account-meta strong{color:var(--text2)}
.cs-account-empty{font-size:13px;color:var(--text2);text-align:center;padding:8px 0}
.cs-account-empty p{margin-bottom:12px;color:var(--text3)}

/* ── SMALL BUTTONS ── */
.cs-btn-sm{border-radius:8px;padding:7px 14px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid transparent;transition:all .15s;font-family:inherit}
.cs-btn-gold{background:var(--accent);color:#0a0d14;border-color:var(--accent)}
.cs-btn-gold:hover{opacity:.9}
.cs-btn-blue{background:rgba(59,130,246,.1);border-color:rgba(59,130,246,.3);color:#93c5fd}
.cs-btn-blue:hover{background:rgba(59,130,246,.2)}
.cs-btn-red{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.25);color:#fca5a5}
.cs-btn-red:hover{background:rgba(239,68,68,.2)}

/* ── STATS ── */
.cs-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:14px}
.cs-stat-box{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;text-align:center}
.cs-stat-num{font-size:24px;font-weight:800;color:var(--text);line-height:1;font-family:monospace}
.cs-stat-lbl{font-size:11px;color:var(--text3);margin-top:4px;font-weight:600;text-transform:uppercase;letter-spacing:.4px}

/* ── TABLE CARD ── */
.cs-table-card{margin-bottom:14px}
.cs-table-header{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.cs-table-title{font-weight:700;font-size:14px}
.cs-link{font-size:12px;color:var(--accent);text-decoration:none}
.cs-link:hover{text-decoration:underline}
.cs-responsive-table{overflow-x:auto;-webkit-overflow-scrolling:touch}
.cs-table{width:100%;border-collapse:collapse;min-width:480px}
.cs-table thead th{padding:10px 16px;text-align:left;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border)}
.cs-table tbody tr{border-bottom:1px solid rgba(255,255,255,.04);transition:background .12s}
.cs-table tbody tr:hover{background:rgba(255,255,255,.02)}
.cs-table tbody tr:last-child{border-bottom:none}
.cs-table td{padding:12px 16px;font-size:13px;color:var(--text2)}
.cs-empty{text-align:center;padding:40px;color:var(--text3);font-size:13px}

/* ── FLASH ── */
.ci-flash{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:16px}
.ci-flash-success{background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.25);color:#86efac}
.ci-flash-error{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);color:#fca5a5}

/* ── RESPONSIVE ── */
@media(max-width:600px){.cs-stats{grid-template-columns:1fr 1fr}}
</style>

@push('scripts')
<script>
// Réutiliser CI depuis index si disponible, sinon définir localement
if (!window.CI) {
    window.CI = {
        toast(msg, type='success') {
            const container = document.getElementById('ci-toast-container');
            if (!container) return;
            const icons = {success:'✅',error:'❌',info:'ℹ️',warning:'⚠️'};
            const el = document.createElement('div');
            el.className = `ci-toast ci-toast-${type}`;
            el.innerHTML = `<span>${icons[type]}</span><span>${msg}</span>`;
            el.onclick = () => { el.classList.add('out'); setTimeout(()=>el.remove(),250); };
            container.appendChild(el);
            setTimeout(() => { if(el.parentNode){el.classList.add('out');setTimeout(()=>el.remove(),250);} }, 4000);
        },
        modal: {
            open(id){document.getElementById(id)?.classList.add('open')},
            close(id){document.getElementById(id)?.classList.remove('open')},
        }
    };
}

function openDeleteClient(id, name, activeCampaigns) {
    document.getElementById('del-name').textContent = name;
    document.getElementById('del-form').action = `/admin/clients/${id}`;
    document.getElementById('del-warning').style.display = activeCampaigns > 0 ? 'block' : 'none';
    CI.modal.open('modal-delete');
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') CI.modal.close('modal-delete');
});


</script>

{{-- CI toast styles si pas déjà chargés --}}
<style>
.ci-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:500;display:none;align-items:center;justify-content:center;padding:16px}
.ci-modal-overlay.open{display:flex}
.ci-modal{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:32px 28px;width:100%;max-width:420px;animation:ci-modal-in .2s ease}
@keyframes ci-modal-in{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.ci-modal-icon{width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:24px;margin:0 auto 16px}
.ci-modal-icon-danger{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2)}
.ci-modal-title{font-size:17px;font-weight:700;color:var(--text);text-align:center;margin-bottom:8px}
.ci-modal-desc{font-size:13px;color:var(--text2);text-align:center;line-height:1.6;margin-bottom:14px}
.ci-modal-footer{display:flex;gap:10px;justify-content:center;margin-top:18px}
.ci-text-gold{color:var(--accent)}
.ci-alert{border-radius:8px;padding:10px 14px;font-size:12px;margin-bottom:10px;line-height:1.5}
.ci-alert-danger{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#fca5a5}
.ci-btn{border-radius:8px;padding:9px 20px;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;border:1px solid transparent;font-family:inherit}
.ci-btn-ghost{background:transparent;border-color:var(--border);color:var(--text2)}
.ci-btn-ghost:hover{border-color:var(--accent);color:var(--text)}
.ci-btn-danger{background:rgba(239,68,68,.15);border-color:rgba(239,68,68,.35);color:#fca5a5}
.ci-btn-danger:hover{background:rgba(239,68,68,.25)}
.ci-toast{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:12px 16px;font-size:13px;color:var(--text);display:flex;align-items:center;gap:10px;min-width:240px;pointer-events:all;box-shadow:0 4px 20px rgba(0,0,0,.4);animation:ci-toast-in .25s ease;cursor:pointer}
.ci-toast.out{animation:ci-toast-out .25s ease forwards}
@keyframes ci-toast-in{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:translateX(0)}}
@keyframes ci-toast-out{from{opacity:1;transform:translateX(0)}to{opacity:0;transform:translateX(20px)}}
.ci-toast-success{border-color:rgba(34,197,94,.3)}
.ci-toast-error{border-color:rgba(239,68,68,.3)}
.ci-toast-info{border-color:rgba(59,130,246,.3)}
.ci-hide-sm{} @media(max-width:600px){.ci-hide-sm{display:none}}
</style>
@endpush
</x-admin-layout>