<x-admin-layout>
<x-slot name="title">Import GPS panneaux</x-slot>

<x-slot:topbarLeft>
    <a href="{{ route('admin.map') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour à la carte
    </a>
</x-slot:topbarLeft>

<div style="max-width:720px;margin:0 auto">

    {{-- Breadcrumb --}}
    <div style="font-size:12px;color:var(--text3);margin-bottom:16px">
        <a href="{{ route('admin.map') }}" style="color:var(--text3);text-decoration:none">Carte</a>
        <span style="margin:0 6px">›</span>
        <span style="color:var(--text)">Import coordonnées GPS</span>
    </div>

    {{-- Intro --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 22px;margin-bottom:18px;display:flex;align-items:flex-start;gap:14px">
        <div style="width:44px;height:44px;border-radius:12px;background:rgba(232,160,32,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:22px">📍</div>
        <div>
            <div style="font-size:16px;font-weight:800;color:var(--text);margin-bottom:3px">Importer les coordonnées GPS</div>
            <div style="font-size:12.5px;color:var(--text3);line-height:1.55">
                Upload un fichier <strong>Excel (.xlsx, .xls)</strong> ou <strong>CSV</strong> contenant 3 colonnes :
                <strong>référence panneau</strong>, <strong>latitude</strong>, <strong>longitude</strong>.
                Les en-têtes sont détectés automatiquement.
            </div>
        </div>
    </div>

    {{-- Erreurs --}}
    @if($errors->any())
    <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:10px;padding:14px 16px;margin-bottom:16px">
        @foreach($errors->all() as $error)
            <div style="color:#ef4444;font-size:13px;display:flex;gap:6px;align-items:flex-start;margin-bottom:3px">
                <span>⚠️</span><span>{{ $error }}</span>
            </div>
        @endforeach
    </div>
    @endif

    {{-- Formulaire upload --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">📥 Sélectionner le fichier</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.panels.import-gps') }}" enctype="multipart/form-data">
                @csrf

                <div class="mfg">
                    <label>Fichier Excel ou CSV <span style="color:var(--red)">*</span></label>
                    <input type="file" name="gps_file" id="gps_file" required
                           accept=".xlsx,.xls,.csv,.txt"
                           style="display:block;padding:14px;border:2px dashed var(--border2);border-radius:10px;background:var(--surface2);cursor:pointer;width:100%;font-size:13px">
                    <div style="font-size:11px;color:var(--text3);margin-top:6px">
                        Taille max : 5 Mo · Formats : .xlsx, .xls, .csv
                    </div>
                </div>

                <div class="mfg" style="margin-top:14px">
                    <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer">
                        <input type="checkbox" name="force" value="1" style="margin-top:3px">
                        <span>
                            <strong style="color:var(--text);font-size:13px">Écraser les coordonnées existantes</strong>
                            <div style="font-size:11.5px;color:var(--text3);margin-top:2px;line-height:1.5">
                                Décoché (recommandé) : ne touche pas un panneau qui a déjà des coords valides.<br>
                                Coché : remplace toutes les coords par celles du fichier.
                            </div>
                        </span>
                    </label>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:18px;padding-top:18px;border-top:1px solid var(--border)">
                    <a href="{{ route('admin.map') }}" class="btn btn-ghost">Annuler</a>
                    <button type="submit" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px">
                        📥 Importer
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Aide format --}}
    <div style="background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:14px 18px;margin-top:16px">
        <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:8px">💡 Format du fichier attendu</div>
        <div style="font-size:12px;color:var(--text2);line-height:1.6;margin-bottom:10px">
            3 colonnes dans cet ordre : <strong>référence</strong>, <strong>latitude</strong>, <strong>longitude</strong>.
            L'en-tête est optionnel — il sera ignoré automatiquement s'il commence par "ref" ou "pan".
        </div>
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:10px 12px;font-family:monospace;font-size:12px;color:var(--text2)">
            <div style="color:var(--text3);margin-bottom:4px">référence,latitude,longitude</div>
            <div>ABG-001A,6.74425,-3.5026667</div>
            <div>MRY-002,5.2906527,-3.9823743</div>
            <div>CDY-013,5.3480341,-4.0103326</div>
        </div>
        <div style="font-size:11px;color:var(--text3);margin-top:8px;line-height:1.5">
            🛡️ <strong>Validation auto :</strong> coordonnées hors Côte d'Ivoire (lat hors 3-12°N ou lng hors -9/-2°W) sont rejetées avec un message d'erreur.<br>
            🔁 <strong>Tolérance refs :</strong> si une ref n'est pas trouvée à l'identique, le système essaie aussi le format avec "CH" (ex: MRY-002 → MRY-CH002).
        </div>
    </div>

    @if(session('gps_import_report'))
        @php $report = session('gps_import_report'); @endphp
        @if(!empty($report['notfound']) || !empty($report['bad']))
        <div style="background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.3);border-radius:12px;padding:14px 18px;margin-top:16px">
            <div style="font-size:13px;font-weight:700;color:#b45309;margin-bottom:8px">⚠ Détails du dernier import</div>
            @if(!empty($report['notfound']))
                <div style="font-size:12px;color:var(--text2);margin-bottom:8px">
                    <strong>{{ count($report['notfound']) }} référence(s) non trouvée(s) :</strong>
                    <div style="font-family:monospace;font-size:11px;color:var(--text3);margin-top:4px;line-height:1.6;background:var(--surface);padding:8px 10px;border-radius:6px">
                        {{ implode(', ', $report['notfound']) }}
                    </div>
                </div>
            @endif
            @if(!empty($report['bad']))
                <div style="font-size:12px;color:var(--text2)">
                    <strong>{{ count($report['bad']) }} ligne(s) invalide(s) :</strong>
                    <div style="font-family:monospace;font-size:11px;color:var(--text3);margin-top:4px;line-height:1.6;background:var(--surface);padding:8px 10px;border-radius:6px">
                        @foreach($report['bad'] as $line)
                            {{ $line }}<br>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
        @endif
    @endif
</div>

</x-admin-layout>
