@forelse($clients as $client)
@php
    $hasActive  = ($client->active_campaigns_count ?? 0) > 0;
    $hasAccount = $client->hasAccount();
    $initials   = strtoupper(mb_substr($client->name, 0, 1));
    $colors     = ['#e8a020','#22c55e','#3b82f6','#ec4899','#8b5cf6','#06b6d4','#f97316'];
    $color      = $colors[crc32($client->name) % count($colors)];
@endphp
<tr>
    {{-- Client --}}
    <td>
        <div style="display:flex;align-items:center;gap:10px">
            <div style="width:36px;height:36px;border-radius:50%;background:{{ $color }}22;border:1.5px solid {{ $color }}55;color:{{ $color }};display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;flex-shrink:0">
                {{ $initials }}
            </div>
            <div style="min-width:0">
                <a href="{{ route('admin.clients.show', $client) }}"
                   style="font-weight:600;font-size:13px;color:var(--text);text-decoration:none;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px"
                   title="{{ $client->name }}">
                    {{ $client->name }}
                </a>
                <div style="display:flex;align-items:center;gap:6px;margin-top:2px;flex-wrap:wrap">
                    @if($client->ncc)
                        <span style="font-family:monospace;font-size:10px;background:var(--surface2);border:1px solid var(--border);border-radius:4px;padding:1px 6px;color:var(--text3)">
                            {{ $client->ncc }}
                        </span>
                    @endif
                    <span style="font-size:10px;color:var(--text3)">
                        {{ $client->created_at->format('d/m/Y') }}
                    </span>
                </div>
            </div>
        </div>
    </td>

    {{-- Secteur --}}
    <td class="ci-hide-sm">
        @if($client->sector)
            <span style="background:var(--surface2);border:1px solid var(--border);color:var(--text2);border-radius:6px;padding:3px 10px;font-size:11px;font-weight:600;white-space:nowrap">
                {{ $client->sector }}
            </span>
        @else
            <span style="color:var(--text3)">—</span>
        @endif
    </td>

    {{-- Campagnes --}}
    <td>
        <div style="font-size:15px;font-weight:800;color:var(--text);line-height:1">
            {{ $client->campaigns_count ?? 0 }}
        </div>
        @if($hasActive)
            <div style="display:inline-flex;align-items:center;gap:3px;margin-top:3px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);color:#22c55e;border-radius:20px;padding:1px 8px;font-size:10px;font-weight:700">
                <span style="width:5px;height:5px;border-radius:50%;background:#22c55e;display:inline-block;animation:ci-pulse 2s infinite"></span>
                {{ $client->active_campaigns_count }} active
            </div>
        @endif
    </td>

    {{-- Réservations --}}
    <td class="ci-hide-md" style="font-size:14px;font-weight:600;color:var(--text2)">
        {{ $client->reservations_count ?? 0 }}
    </td>

    {{-- Contact --}}
    <td class="ci-hide-md">
        @if($client->contact_name)
            <div style="font-size:12px;font-weight:600;color:var(--text);margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:150px">
                {{ $client->contact_name }}
            </div>
        @endif
        @if($client->email)
            <div style="font-size:11px;color:var(--text3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:150px">
                {{ $client->email }}
            </div>
        @endif
        @if($client->phone)
            <div style="font-size:11px;color:var(--text3)">{{ $client->phone }}</div>
        @endif
        @if(!$client->contact_name && !$client->email && !$client->phone)
            <span style="color:var(--text3);font-size:12px">—</span>
        @endif
    </td>

    {{-- Compte --}}
    <td>
        @if($hasAccount)
            <button onclick="openAccountModal({{ $client->id }}, '{{ addslashes($client->name) }}', true)"
                    style="display:inline-flex;align-items:center;gap:5px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#22c55e;border-radius:20px;padding:4px 10px;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s"
                    onmouseover="this.style.background='rgba(34,197,94,.2)'" 
                    onmouseout="this.style.background='rgba(34,197,94,.1)'">
                🔐 Actif
            </button>
        @else
            <button onclick="openAccountModal({{ $client->id }}, '{{ addslashes($client->name) }}', false)"
                    style="display:inline-flex;align-items:center;gap:5px;background:rgba(107,114,128,.08);border:1px solid rgba(107,114,128,.2);color:var(--text3);border-radius:20px;padding:4px 10px;font-size:11px;font-weight:600;cursor:pointer;transition:all .15s"
                    onmouseover="this.style.borderColor='var(--accent)';this.style.color='var(--accent)'" 
                    onmouseout="this.style.borderColor='rgba(107,114,128,.2)';this.style.color='var(--text3)'">
                🔓 Créer
            </button>
        @endif
    </td>

    {{-- Actions dropdown --}}
    <td>
        <div class="ci-dd" style="position:relative;display:inline-block">
            <button onclick="event.stopPropagation(); this.nextElementSibling.classList.toggle('open')"
                    style="background:transparent;border:1px solid transparent;color:var(--text3);cursor:pointer;padding:6px 8px;border-radius:8px;transition:all .15s"
                    onmouseover="this.style.background='var(--surface2)';this.style.borderColor='var(--border)'"
                    onmouseout="this.style.background='transparent';this.style.borderColor='transparent'">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/>
                </svg>
            </button>
            <div class="ci-dd-menu" style="position:absolute;right:0;top:calc(100% + 4px);background:var(--surface);border:1px solid var(--border);border-radius:12px;min-width:180px;z-index:50;box-shadow:0 8px 24px rgba(0,0,0,.35);display:none;overflow:hidden">
                <a href="{{ route('admin.clients.show', $client) }}" class="ci-dd-item">
                    <span>👁</span> Voir les détails
                </a>
                <a href="{{ route('admin.clients.edit', $client) }}" class="ci-dd-item">
                    <span>✏️</span> Modifier
                </a>
                @if($hasAccount)
                <button onclick="openAccountModal({{ $client->id }}, '{{ addslashes($client->name) }}', true)" class="ci-dd-item">
                    <span>🔐</span> Gérer le compte
                </button>
                @else
                <button onclick="openAccountModal({{ $client->id }}, '{{ addslashes($client->name) }}', false)" class="ci-dd-item">
                    <span>📧</span> Créer un compte
                </button>
                @endif
                <div style="height:1px;background:var(--border);margin:4px 0"></div>
                <button onclick="openDeleteClient({{ $client->id }}, '{{ addslashes($client->name) }}', {{ $client->active_campaigns_count ?? 0 }})" class="ci-dd-item ci-dd-item-danger">
                    <span>🗑</span> Supprimer
                </button>
            </div>
        </div>
    </td>
</tr>
@empty
<tr>
    <td colspan="7" style="text-align:center;padding:60px 20px">
        <div style="font-size:40px;margin-bottom:12px;opacity:.4">👥</div>
        <div style="font-size:14px;font-weight:600;color:var(--text2);margin-bottom:6px">Aucun client trouvé</div>
        <div style="font-size:13px;color:var(--text3)">
            <a href="{{ route('admin.clients.create') }}" style="color:var(--accent);text-decoration:none">
                + Créer le premier client
            </a>
        </div>
    </td>
</tr>
@endforelse

<style>
.ci-dd-menu.open{display:block!important;animation:ci-dd-in .15s ease}
@keyframes ci-dd-in{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.ci-dd-item{display:flex;align-items:center;gap:10px;padding:10px 14px;font-size:13px;color:var(--text2);text-decoration:none;transition:all .12s;cursor:pointer;border:none;background:none;width:100%;text-align:left;font-family:inherit}
.ci-dd-item:hover{background:var(--surface2);color:var(--text)}
.ci-dd-item-danger{color:#fca5a5}
.ci-dd-item-danger:hover{background:rgba(239,68,68,.08);color:#fca5a5}
@keyframes ci-pulse{0%,100%{opacity:1}50%{opacity:.5}}
</style>