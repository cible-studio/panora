<x-admin-layout>
<x-slot name="title">Importer une campagne depuis un PDF</x-slot>

<x-slot:topbarLeft>
    <a href="{{ route('admin.campaigns.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour aux campagnes
    </a>
</x-slot:topbarLeft>

<div style="max-width:720px;margin:0 auto">

    {{-- Breadcrumb --}}
    <div style="font-size:12px;color:var(--text3);margin-bottom:16px">
        <a href="{{ route('admin.campaigns.index') }}" style="color:var(--text3);text-decoration:none">Campagnes</a>
        <span style="margin:0 6px">›</span>
        <span style="color:var(--text)">Import depuis PDF</span>
    </div>

    {{-- Intro card --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 22px;margin-bottom:18px;display:flex;align-items:flex-start;gap:14px">
        <div style="width:44px;height:44px;border-radius:12px;background:rgba(232,160,32,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
        </div>
        <div style="flex:1">
            <div style="font-size:16px;font-weight:800;color:var(--text);margin-bottom:4px">Importer depuis un PDF « Liste des panneaux commandes »</div>
            <div style="font-size:12px;color:var(--text3);line-height:1.6">
                Upload le PDF du client → l'app extrait automatiquement <strong>Client</strong>, <strong>Campagne</strong>, <strong>Période</strong> et la <strong>liste des codes panneaux</strong>.
                Tu auras un récap avant validation finale.
            </div>
            <div style="font-size:11px;color:var(--text3);margin-top:8px;padding:8px 10px;background:var(--surface2);border-radius:8px">
                ⚠️ Le PDF doit contenir du <strong>texte sélectionnable</strong> (pas une image scannée).
                Si tu n'arrives pas à copier le texte avec la souris dans ton lecteur PDF, l'extraction échouera.
            </div>
        </div>
    </div>

    @if($errors->any())
    <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:10px;padding:14px 16px;margin-bottom:16px">
        @foreach($errors->all() as $error)
            <div style="color:#ef4444;font-size:13px;display:flex;gap:6px;align-items:flex-start;margin-bottom:3px">
                <span>⚠️</span><span>{{ $error }}</span>
            </div>
        @endforeach
    </div>
    @endif

    <div class="card">
        <div class="card-header">
            <div class="card-title">📥 Upload du PDF</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.campaigns.import-pdf.preview') }}" enctype="multipart/form-data">
                @csrf

                <div class="mfg">
                    <label>Fichier PDF <span style="color:var(--red)">*</span></label>
                    <input type="file" name="pdf" accept="application/pdf" required
                           style="padding:8px 12px;background:var(--surface2);border:1px dashed var(--border2);border-radius:10px;cursor:pointer">
                    <div style="font-size:11px;color:var(--text3);margin-top:6px">
                        Format PDF · 10 MB maximum
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:8px;padding-top:18px;border-top:1px solid var(--border)">
                    <a href="{{ route('admin.campaigns.index') }}" class="btn btn-ghost">Annuler</a>
                    <button type="submit" class="btn btn-primary">
                        🔍 Analyser le PDF
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div style="margin-top:14px;font-size:11px;color:var(--text3);text-align:center;line-height:1.6">
        Pattern reconnu : <code style="background:var(--surface2);padding:1px 6px;border-radius:4px">Periode du JJ/MM/AAAA au JJ/MM/AAAA</code>,
        <code style="background:var(--surface2);padding:1px 6px;border-radius:4px">CLIENT&nbsp;: ...</code>,
        <code style="background:var(--surface2);padding:1px 6px;border-radius:4px">CAMPAGNE&nbsp;: ...</code>,
        codes panneaux au format <code style="background:var(--surface2);padding:1px 6px;border-radius:4px">XXX-NNNN</code>.
    </div>
</div>

</x-admin-layout>
