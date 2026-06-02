<x-admin-layout title="Signalements terrain">

<x-slot:topbarLeft>
    <h1 style="font-size:20px;font-weight:800;margin:0;display:flex;align-items:center;gap:8px">
        ⚠️ Signalements terrain
    </h1>
</x-slot:topbarLeft>

{{-- ── KPI cards ────────────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:18px">
    <div class="kpi-card" style="--kpi-color:#ef4444">
        <div class="kpi-card__top-bar" style="background:#ef4444"></div>
        <div class="kpi-card__icon" style="color:#ef4444">⚠️</div>
        <div class="kpi-card__value" style="color:#ef4444">{{ $kpi['pending'] }}</div>
        <div class="kpi-card__label">À traiter</div>
        <div class="kpi-card__sub">Signalements en attente</div>
    </div>
    <div class="kpi-card" style="--kpi-color:#f97316">
        <div class="kpi-card__top-bar" style="background:#f97316"></div>
        <div class="kpi-card__icon" style="color:#f97316">🔧</div>
        <div class="kpi-card__value" style="color:#f97316">{{ $kpi['maintenance'] }}</div>
        <div class="kpi-card__label">Mis en maintenance</div>
        <div class="kpi-card__sub">Panneau sorti de dispo</div>
    </div>
    <div class="kpi-card" style="--kpi-color:#6b7280">
        <div class="kpi-card__top-bar" style="background:#6b7280"></div>
        <div class="kpi-card__icon" style="color:#6b7280">✓</div>
        <div class="kpi-card__value" style="color:#6b7280">{{ $kpi['dismissed'] }}</div>
        <div class="kpi-card__label">Dismissed</div>
        <div class="kpi-card__sub">Clôturés sans action</div>
    </div>
    <div class="kpi-card" style="--kpi-color:var(--accent)">
        <div class="kpi-card__top-bar" style="background:var(--accent)"></div>
        <div class="kpi-card__icon" style="color:var(--accent)">📊</div>
        <div class="kpi-card__value" style="color:var(--accent)">{{ $kpi['total'] }}</div>
        <div class="kpi-card__label">Total</div>
        <div class="kpi-card__sub">Tous statuts</div>
    </div>
</div>

{{-- ── Filtres ─────────────────────────────────────────── --}}
<div class="card" style="margin-bottom:18px">
    <form method="GET" action="{{ route('admin.signalements.index') }}"
          style="display:flex;flex-wrap:wrap;gap:10px;align-items:center">
        <div style="display:flex;gap:4px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:4px">
            @foreach(['pending' => '⚠️ À traiter', 'all' => 'Tous', 'resolved' => '✓ Traités'] as $key => $label)
            <a href="{{ route('admin.signalements.index', ['status' => $key]) }}"
               style="padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
                      {{ $status === $key ? 'background:var(--surface);color:var(--text);box-shadow:0 1px 2px rgba(0,0,0,.04)' : 'color:var(--text2)' }}">
                {{ $label }}
            </a>
            @endforeach
        </div>

        <select name="type" onchange="this.form.submit()"
                style="padding:8px 12px;border:1px solid var(--border);border-radius:10px;background:var(--surface);font-size:13px">
            <option value="">Tous les types</option>
            @foreach($problemLabels as $key => $label)
                <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
            @endforeach
        </select>

        @if(request('type'))
            <a href="{{ route('admin.signalements.index', ['status' => $status]) }}"
               style="font-size:12px;color:var(--text3);text-decoration:none">✕ Réinitialiser</a>
        @endif
    </form>
</div>

{{-- ── Liste ───────────────────────────────────────────── --}}
@forelse($signalements as $sig)
    @php
        $type      = $sig->payload['type'] ?? 'autre';
        $note      = $sig->payload['note'] ?? null;
        $label     = $problemLabels[$type] ?? 'Problème';
        $panel     = $sig->task?->panel;
        $firstPhoto= $panel?->photos?->sortBy('ordre')->first();
        $thumbUrl  = $firstPhoto ? asset('storage/' . $firstPhoto->path) : null;
        $typeColor = [
            'panneau_casse'    => '#ef4444',
            'acces_bloque'     => '#f97316',
            'mauvaise_adresse' => '#3b82f6',
            'autre'            => '#6b7280',
        ][$type] ?? '#6b7280';
    @endphp
    <div class="card" style="margin-bottom:12px;border-left:4px solid {{ $sig->resolved_at ? 'var(--border)' : $typeColor }}">
        <div style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap">
            {{-- Vignette du panneau --}}
            <div style="flex:0 0 72px">
                @if($thumbUrl)
                    <div style="width:72px;height:72px;border-radius:10px;background:url('{{ $thumbUrl }}') center/cover no-repeat;border:1px solid var(--border)"></div>
                @else
                    <div style="width:72px;height:72px;border-radius:10px;background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:28px;color:var(--text3)">🪧</div>
                @endif
            </div>

            {{-- Infos --}}
            <div style="flex:1;min-width:240px">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">
                    <span style="font-family:monospace;font-size:14px;font-weight:800;color:var(--accent-dark)">
                        {{ $panel?->reference ?? '—' }}
                    </span>
                    <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;background:rgba(0,0,0,.06);color:{{ $typeColor }};border:1px solid {{ $typeColor }}40">
                        {{ $label }}
                    </span>
                    @if($sig->resolved_at)
                        @if($sig->resolution_action === 'maintenance')
                            <span style="font-size:11px;font-weight:700;color:#f97316;background:rgba(249,115,22,.10);padding:2px 8px;border-radius:999px">🔧 En maintenance</span>
                        @else
                            <span style="font-size:11px;font-weight:700;color:var(--text3);background:var(--surface2);padding:2px 8px;border-radius:999px">✓ Traité</span>
                        @endif
                    @endif
                </div>
                <div style="font-size:13px;color:var(--text);margin-bottom:4px">
                    {{ $panel?->name ?? '—' }} · 📍 {{ $panel?->commune?->name ?? '—' }}
                    @if($panel?->adresse) · {{ $panel->adresse }} @endif
                </div>
                @if($note)
                    <div style="font-size:13px;color:var(--text2);background:var(--surface2);border-left:3px solid var(--border);padding:8px 12px;border-radius:6px;margin:6px 0;white-space:pre-wrap">{{ $note }}</div>
                @endif
                @php $photoPath = $sig->payload['photo_path'] ?? null; @endphp
                @if($photoPath)
                    <a href="{{ \Illuminate\Support\Facades\Storage::url($photoPath) }}" target="_blank" rel="noopener"
                       style="display:inline-block;margin:6px 0;border:1px solid var(--border);border-radius:8px;overflow:hidden">
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($photoPath) }}"
                             alt="Photo signalement"
                             style="display:block;width:140px;height:100px;object-fit:cover;cursor:zoom-in">
                    </a>
                    <div style="font-size:10.5px;color:var(--text3);margin-top:-4px">📷 Photo jointe par le tech — cliquer pour zoomer</div>
                @endif
                <div style="font-size:12px;color:var(--text3);display:flex;gap:12px;flex-wrap:wrap;margin-top:4px">
                    <span>👷 {{ $sig->actor ?? '—' }}</span>
                    <span>🕐 {{ $sig->created_at?->diffForHumans() }}</span>
                    @if($sig->task?->campaign)
                        <span>📢 {{ $sig->task->campaign->name }}</span>
                    @endif
                    @if($sig->task)
                        <a href="{{ route('admin.pose-tasks.show', $sig->task->id) }}" style="color:var(--accent);text-decoration:none">Ouvrir la pose →</a>
                    @endif
                </div>
                @if($sig->resolved_at)
                    <div style="font-size:11px;color:var(--text3);margin-top:6px;font-style:italic">
                        Traité par {{ $sig->resolvedBy?->name ?? 'admin' }} {{ $sig->resolved_at->diffForHumans() }}
                        @if($sig->maintenance_id)
                            · <a href="{{ route('admin.maintenances.show', $sig->maintenance_id) }}" style="color:var(--accent)">Voir la maintenance →</a>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Actions --}}
            @if(!$sig->resolved_at)
            <div style="display:flex;gap:8px;flex-direction:column;min-width:200px">
                <form method="POST" action="{{ route('admin.signalements.maintenance', $sig->id) }}"
                      onsubmit="return confirm('Mettre le panneau {{ $panel?->reference }} en maintenance ? Il sortira des disponibilités et une fiche maintenance sera créée.')">
                    @csrf
                    <button type="submit"
                            style="width:100%;padding:10px 14px;background:#f97316;color:#fff;border:none;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer">
                        🔧 Mettre en maintenance
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.signalements.dismiss', $sig->id) }}"
                      onsubmit="return confirm('Marquer le signalement comme traité, sans toucher au panneau ?')">
                    @csrf
                    <button type="submit"
                            style="width:100%;padding:10px 14px;background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:10px;font-weight:700;font-size:13px;cursor:pointer">
                        ✓ Marquer traité
                    </button>
                </form>
            </div>
            @endif
        </div>
    </div>
@empty
    <div class="card" style="text-align:center;padding:60px 20px;color:var(--text3)">
        <div style="font-size:48px;margin-bottom:12px">🎉</div>
        <div style="font-size:16px;font-weight:700;color:var(--text);margin-bottom:6px">
            @if($status === 'pending')
                Aucun signalement en attente
            @else
                Aucun signalement
            @endif
        </div>
        <div style="font-size:13px">Le terrain n'a rien remonté pour ce filtre.</div>
    </div>
@endforelse

{{ $signalements->links() }}

</x-admin-layout>
