{{-- resources/views/client/piges.blade.php --}}
@extends('client.layout')

@section('title', 'Mes piges photos')

@section('content')

<div style="max-width:960px;margin:0 auto">

    {{-- ── EN-TÊTE ── --}}
    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:20px;flex-wrap:wrap;gap:12px">
        <div>
            <h1 style="font-size:20px;font-weight:800;color:var(--text);margin-bottom:4px">📸 Mes piges photos</h1>
            <p style="font-size:13px;color:var(--text3)">Preuves d'affichage de vos campagnes publicitaires · {{ number_format($totalPiges) }} photos vérifiées</p>
        </div>
        {{-- Export PDF si campagne sélectionnée --}}
        @if(request('campaign_id'))
        <a href="{{ route('admin.piges.export-pdf') }}?campaign_id={{ request('campaign_id') }}"
           target="_blank"
           style="display:inline-flex;align-items:center;gap:6px;padding:9px 16px;background:var(--surface);border:1px solid rgba(239,68,68,.35);color:#ef4444;border-radius:12px;font-size:13px;font-weight:600;text-decoration:none">
            📄 Télécharger le rapport PDF
        </a>
        @endif
    </div>

    {{-- ── FILTRE CAMPAGNES ── --}}
    @if($campaigns->isNotEmpty())
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 18px;margin-bottom:16px">
        <form method="GET" action="{{ route('client.piges') }}">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <span style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text3)">📢 Campagne :</span>
                <select name="campaign_id" onchange="this.form.submit()"
                        style="height:36px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:9px;font-size:13px;color:var(--text)">
                    <option value="">Toutes mes campagnes</option>
                    @foreach($campaigns as $c)
                        <option value="{{ $c->id }}" {{ request('campaign_id') == $c->id ? 'selected' : '' }}>
                            {{ $c->name }}
                        </option>
                    @endforeach
                </select>
                @if(request('campaign_id'))
                <a href="{{ route('client.piges') }}"
                   style="font-size:12px;color:var(--text3);text-decoration:none">↺ Voir tout</a>
                @endif
            </div>
        </form>
    </div>
    @endif

    {{-- ── GRILLE PIGES ── --}}
    @if($piges->isEmpty())
        <div style="text-align:center;padding:80px 20px;background:var(--surface);border:1px solid var(--border);border-radius:16px">
            <div style="font-size:52px;margin-bottom:12px">📷</div>
            <div style="font-size:15px;font-weight:700;color:var(--text2);margin-bottom:6px">
                Aucune pige disponible
            </div>
            <div style="font-size:13px;color:var(--text3)">
                Les photos de vos panneaux apparaîtront ici après vérification.
            </div>
        </div>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:24px">
            @foreach($piges as $pige)
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;overflow:hidden;cursor:pointer;transition:transform .15s,box-shadow .15s"
                 onclick="openPigeModal(this)"
                 data-photo="{{ asset('storage/' . $pige->photo_path) }}"
                 data-panel="{{ $pige->panel?->reference }} · {{ $pige->panel?->name }}"
                 data-campaign="{{ $pige->campaign?->name ?? 'Sans campagne' }}"
                 data-date="{{ $pige->taken_at?->format('d/m/Y') }}"
                 data-notes="{{ $pige->notes ?? '' }}"
                 onmouseenter="this.style.transform='translateY(-3px)';this.style.boxShadow='0 8px 24px rgba(0,0,0,.2)'"
                 onmouseleave="this.style.transform='';this.style.boxShadow=''">

                {{-- Photo --}}
                <div style="position:relative;height:140px;background:var(--surface2);overflow:hidden">
                    <img src="{{ asset('storage/' . $pige->photo_path) }}"
                         alt="Pige {{ $pige->panel?->reference }}"
                         loading="lazy"
                         style="width:100%;height:140px;object-fit:cover">
                    {{-- Badge vérifié --}}
                    <div style="position:absolute;top:8px;right:8px;background:#22c55e;color:#fff;font-size:9px;font-weight:700;padding:2px 8px;border-radius:20px">
                        ✅ Vérifié
                    </div>
                </div>

                {{-- Infos --}}
                <div style="padding:10px 12px">
                    <div style="font-family:monospace;font-size:11px;color:var(--accent);font-weight:700;margin-bottom:2px">
                        {{ $pige->panel?->reference ?? '—' }}
                    </div>
                    <div style="font-size:12px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-bottom:4px">
                        {{ Str::limit($pige->panel?->name ?? '—', 26) }}
                    </div>
                    <div style="font-size:11px;color:var(--text3)">
                        📅 {{ $pige->taken_at?->format('d/m/Y') }}
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($piges->hasPages())
        <div style="display:flex;justify-content:center;align-items:center;gap:8px;padding:12px 0">
            @if(!$piges->onFirstPage())
                <a href="{{ $piges->previousPageUrl() }}"
                   style="padding:7px 16px;background:var(--surface);border:1px solid var(--border);border-radius:9px;text-decoration:none;color:var(--text2);font-size:13px">← Précédent</a>
            @endif
            <span style="font-size:12px;color:var(--text3)">{{ $piges->currentPage() }} / {{ $piges->lastPage() }}</span>
            @if($piges->hasMorePages())
                <a href="{{ $piges->nextPageUrl() }}"
                   style="padding:7px 16px;background:var(--surface);border:1px solid var(--border);border-radius:9px;text-decoration:none;color:var(--text2);font-size:13px">Suivant →</a>
            @endif
        </div>
        @endif
    @endif

</div>

{{-- ── LIGHTBOX CLIENT ── --}}
<div id="client-lightbox"
     style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.85);backdrop-filter:blur(6px);align-items:center;justify-content:center;padding:16px"
     onclick="if(event.target===this)closePigeModal()">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:20px;width:100%;max-width:700px;max-height:90vh;overflow:hidden;display:flex;flex-direction:column"
         onclick="event.stopPropagation()">

        {{-- Header --}}
        <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid var(--border)">
            <div style="font-weight:700;font-size:14px;color:var(--text)" id="lb-c-title">Photo</div>
            <button onclick="closePigeModal()"
                    style="width:30px;height:30px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;color:var(--text3);cursor:pointer">✕</button>
        </div>

        {{-- Photo --}}
        <div style="flex:1;background:#000;display:flex;align-items:center;justify-content:center;max-height:440px">
            <img id="lb-c-photo" src="" alt="Pige" style="max-width:100%;max-height:440px;object-fit:contain">
        </div>

        {{-- Infos --}}
        <div style="padding:16px 20px;display:grid;grid-template-columns:1fr 1fr;gap:10px">
            @foreach([['lb-c-panel','Panneau'],['lb-c-campaign','Campagne'],['lb-c-date','Prise le'],['lb-c-notes','Notes']] as [$id,$label])
            <div>
                <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text3);margin-bottom:2px">{{ $label }}</div>
                <div id="{{ $id }}" style="font-size:13px;color:var(--text)">—</div>
            </div>
            @endforeach
        </div>
    </div>
</div>

<script>
function openPigeModal(card) {
    const lb = document.getElementById('client-lightbox');
    document.getElementById('lb-c-photo').src       = card.dataset.photo;
    document.getElementById('lb-c-title').textContent = card.dataset.panel || 'Photo';
    document.getElementById('lb-c-panel').textContent    = card.dataset.panel;
    document.getElementById('lb-c-campaign').textContent = card.dataset.campaign;
    document.getElementById('lb-c-date').textContent     = card.dataset.date;
    document.getElementById('lb-c-notes').textContent    = card.dataset.notes || 'Aucune note';
    lb.style.display = 'flex';
}
function closePigeModal() {
    document.getElementById('client-lightbox').style.display = 'none';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closePigeModal(); });
</script>

@endsection