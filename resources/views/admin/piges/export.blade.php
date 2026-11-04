{{-- Préparation du rapport PDF de piges (2026-11-04).
     Toutes les piges VÉRIFIÉES des filtres de la page Piges sont cochées ;
     le MP décoche celles qu'il ne veut pas et ajuste le titre. Règles :
     App\Services\Piges\RapportPigesService. --}}
<x-admin-layout title="Rapport PDF de piges">

<x-slot:topbarActions>
    <a href="{{ route('admin.piges.index', $filtres) }}" class="btn btn-ghost btn-sm">← Retour aux piges</a>
</x-slot:topbarActions>

@if($errors->any())
<div style="padding:10px 14px;margin-bottom:12px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:10px;font-size:12px;color:#ef4444">
    {{ $errors->first() }}
</div>
@endif

@if($total === 0)
<div style="text-align:center;padding:50px 20px;background:var(--surface);border:1px solid var(--border);border-radius:14px;color:var(--text3)">
    <div style="font-size:40px;opacity:.25;margin-bottom:12px">📄</div>
    <div style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:6px">Aucune pige vérifiée avec ces filtres</div>
    <div style="font-size:12px">Le rapport ne contient que des piges vérifiées.</div>
</div>

@elseif($trop)
<div style="padding:18px 20px;background:rgba(249,115,22,.07);border:1px solid rgba(249,115,22,.3);border-radius:14px">
    <div style="font-size:14px;font-weight:700;color:#f97316;margin-bottom:4px">
        {{ number_format($total, 0, ',', ' ') }} piges vérifiées correspondent à ces filtres
    </div>
    <div style="font-size:12px;color:var(--text2)">
        Un PDF en contient au plus {{ $max }}. Affinez par campagne, commune ou dates, puis relancez l'export.
    </div>
</div>

@else
<form method="POST" action="{{ route('admin.piges.export.pdf') }}" id="form-export">
    @csrf

    <div style="position:sticky;top:0;z-index:5;background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:12px 16px;margin-bottom:14px;display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <div style="flex:1;min-width:240px">
            <label class="flbl" for="titre">Titre du document</label>
            <input type="text" name="titre" id="titre" value="{{ old('titre', $titre) }}" maxlength="120" required
                   style="height:38px;padding:0 12px;width:100%;box-sizing:border-box;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;color:var(--text)">
        </div>
        <div style="font-size:12px;color:var(--text3);align-self:center">
            <strong id="nb-coches" style="color:var(--text)">{{ $total }}</strong> / {{ $total }} photo(s)
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="Export.tout(true)">Tout cocher</button>
        <button type="button" class="btn btn-ghost btn-sm" onclick="Export.tout(false)">Tout décocher</button>
        <button type="submit" class="btn btn-primary" id="btn-generer">Générer le PDF</button>
    </div>

    @foreach($groupes as $commune => $liste)
    <section style="margin-bottom:18px" data-groupe>
        <label style="display:flex;align-items:center;gap:8px;margin:0 0 8px;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--text);cursor:pointer">
            <input type="checkbox" checked onchange="Export.groupe(this)" style="accent-color:var(--accent);width:15px;height:15px">
            {{ $commune }}
            <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--text3)">· {{ $liste->count() }} photo(s)</span>
        </label>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:10px">
            @foreach($liste as $pige)
            @php $prise = $pige->taken_at ?? $pige->created_at; @endphp
            <label class="export-carte" style="display:block;background:var(--surface);border:1px solid var(--border);border-radius:10px;overflow:hidden;cursor:pointer">
                <div style="position:relative;aspect-ratio:4/3;background:var(--surface2)">
                    <img src="{{ $pige->getThumbUrl() }}" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover;display:block">
                    <input type="checkbox" name="pige_ids[]" value="{{ $pige->id }}" checked
                           onchange="Export.compter()"
                           style="position:absolute;top:8px;left:8px;width:18px;height:18px;accent-color:var(--accent)">
                </div>
                <div style="padding:7px 9px">
                    <div style="font-size:11px;font-weight:700;color:var(--accent);font-family:monospace">{{ $pige->panel?->reference ?? '—' }}</div>
                    <div style="font-size:11px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="{{ $pige->panel?->name }}">{{ $pige->panel?->name ?? '' }}</div>
                    <div style="font-size:10px;color:var(--text3)">{{ $prise?->format('d/m/Y H:i') }}@if($pige->campaign) · {{ Str::limit($pige->campaign->name, 22) }}@endif</div>
                    @if($pige->relationLoaded('poseTask') && $pige->poseTask?->vague)
                    <div style="font-size:10px;font-weight:700;color:var(--accent)">🌊 {{ Str::limit($pige->poseTask->vague->badge(), 28) }}</div>
                    @endif
                </div>
            </label>
            @endforeach
        </div>
    </section>
    @endforeach
</form>

@push('scripts')
<script>
const Export = {
    cases() { return document.querySelectorAll('input[name="pige_ids[]"]'); },
    compter() {
        const n = Array.from(this.cases()).filter(c => c.checked).length;
        document.getElementById('nb-coches').textContent = n;
        document.getElementById('btn-generer').disabled = n === 0;
        document.querySelectorAll('.export-carte').forEach(carte => {
            carte.style.opacity = carte.querySelector('input').checked ? '1' : '.4';
        });
    },
    tout(etat) {
        document.querySelectorAll('#form-export input[type=checkbox]').forEach(c => c.checked = etat);
        this.compter();
    },
    groupe(caseGroupe) {
        caseGroupe.closest('[data-groupe]').querySelectorAll('input[name="pige_ids[]"]')
            .forEach(c => c.checked = caseGroupe.checked);
        this.compter();
    },
};

// Le PDF se télécharge sans quitter la page : bouton bloqué le temps de
// la génération (photos réduites une à une), puis rendu.
document.getElementById('form-export').addEventListener('submit', function () {
    const btn = document.getElementById('btn-generer');
    btn.disabled = true;
    btn.textContent = 'Génération…';
    setTimeout(() => { btn.textContent = 'Générer le PDF'; Export.compter(); }, 8000);
});
</script>
@endpush
@endif

</x-admin-layout>
