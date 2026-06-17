<x-admin-layout>
<x-slot name="title">Attribution commercial</x-slot>

<x-slot:topbarLeft>
    <a href="{{ route('admin.performance.commercial.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Performance commerciale
    </a>
</x-slot:topbarLeft>

<div style="max-width:1200px;margin:0 auto">
    {{-- Hero --}}
    <div style="background:linear-gradient(135deg,rgba(245,158,11,.10),rgba(217,119,6,.06));border:1px solid var(--border);border-radius:16px;padding:22px 26px;margin-bottom:18px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div style="width:54px;height:54px;border-radius:14px;background:rgba(245,158,11,.20);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:26px">⚙</div>
        <div style="flex:1;min-width:240px">
            <div style="font-size:18px;font-weight:800;color:var(--text)">Migration — Attribution commercial</div>
            <div style="font-size:12.5px;color:var(--text3);margin-top:4px;line-height:1.5">
                Backfill manuel de <strong style="color:var(--text)">campaigns.commercial_user_id</strong> sur les campagnes
                historiques. Chaque campagne est attribuée individuellement — pas de migration auto en masse.
            </div>
        </div>
        <div style="font-size:24px;font-weight:800;color:#b45309">{{ $unattributedCount }} <span style="font-size:12px;color:var(--text3);font-weight:600">/ {{ $totalCount }}</span></div>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div style="padding:10px 14px;background:rgba(34,197,94,.10);border-left:4px solid #16a34a;border-radius:8px;margin-bottom:12px;font-size:13px;color:#15803d">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div style="padding:10px 14px;background:rgba(239,68,68,.10);border-left:4px solid #dc2626;border-radius:8px;margin-bottom:12px;font-size:13px;color:#b91c1c">{{ session('error') }}</div>
    @endif

    {{-- Liste --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden">
        @forelse($campaigns as $c)
            @php $creator = $c->user; @endphp
            <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:16px;flex-wrap:wrap">
                <div style="flex:1;min-width:280px">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                        <a href="{{ route('admin.campaigns.show', $c) }}" style="font-family:monospace;color:var(--accent);text-decoration:none;font-weight:800">{{ $c->name }}</a>
                        <span style="font-size:11px;color:var(--text3)">·</span>
                        <span style="font-size:13px;color:var(--text2)">{{ $c->client?->name ?? '—' }}</span>
                    </div>
                    <div style="font-size:11.5px;color:var(--text3);margin-top:4px;display:flex;gap:14px;flex-wrap:wrap">
                        <span>📅 {{ $c->start_date?->format('d/m/Y') }} → {{ $c->end_date?->format('d/m/Y') }}</span>
                        <span>💰 {{ number_format($c->total_amount, 0, ',', ' ') }} FCFA TTC</span>
                        <span>🖊 Créé par {{ $creator?->name ?? '—' }}@if($creator && $creator->role?->value !== 'commercial') <span style="color:#b45309;font-weight:700">(non commercial)</span>@endif</span>
                        <span>🕒 {{ $c->created_at?->diffForHumans() }}</span>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.migration.commercial-attribution.assign', $c) }}"
                      style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    @csrf
                    <select name="commercial_user_id" required style="height:34px;padding:0 10px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:12.5px;min-width:180px">
                        <option value="">— Choisir —</option>
                        @foreach($commerciaux as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->agent_code }})</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm" style="font-size:12px">Assigner</button>
                    @if($creator && $creator->role?->value === 'commercial')
                        <button type="submit" name="use_creator" value="1"
                                class="btn btn-ghost btn-sm"
                                style="font-size:11.5px;color:#0891b2;border:1px solid rgba(8,145,178,.4)"
                                title="Assigne directement au créateur ({{ $creator->name }})">
                            ⚡ Créateur
                        </button>
                    @endif
                </form>
            </div>
        @empty
            <div style="text-align:center;padding:60px;color:var(--text3)">
                <div style="font-size:48px;margin-bottom:12px">🎉</div>
                <div style="font-size:16px;font-weight:700;color:var(--text);margin-bottom:4px">Toutes les campagnes ont un commercial</div>
                <div style="font-size:12px">Aucun backfill restant à faire.</div>
            </div>
        @endforelse

        @if($campaigns->hasPages())
            <div style="padding:14px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end">
                {{ $campaigns->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>

</x-admin-layout>
