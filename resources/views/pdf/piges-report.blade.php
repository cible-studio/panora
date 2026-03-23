<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'DejaVu Sans', sans-serif; font-size:11px; color:#1a1a2e; }

        .header {
            background:#0a0c10; color:white;
            padding:20px 25px; margin-bottom:20px;
        }
        .logo { font-size:20px; font-weight:800; color:#e8a020; }
        .logo-sub { font-size:9px; color:#8a90a2; }

        .pige-card {
            border:1px solid #e2e8f0; border-radius:8px;
            margin-bottom:20px; overflow:hidden;
            page-break-inside:avoid;
        }

        .pige-header {
            background:#f8fafc; padding:10px 15px;
            border-bottom:1px solid #e2e8f0;
            display:flex; justify-content:space-between;
            align-items:center;
        }

        .pige-ref {
            font-family:monospace; color:#e8a020;
            font-weight:700; font-size:13px;
        }

        .pige-body {
            display:grid; grid-template-columns:200px 1fr;
        }

        .pige-photo {
            border-right:1px solid #e2e8f0;
        }

        .pige-photo img {
            width:200px; height:150px;
            object-fit:cover; display:block;
        }

        .pige-infos {
            padding:12px;
        }

        .field { margin-bottom:8px; }
        .field-label {
            font-size:9px; color:#64748b;
            text-transform:uppercase; margin-bottom:2px;
        }
        .field-value { font-size:11px; font-weight:600; }

        .badge {
            display:inline-block; padding:2px 8px;
            border-radius:10px; font-size:9px; font-weight:700;
        }
        .badge-green  { background:#dcfce7; color:#16a34a; }
        .badge-orange { background:#fef3c7; color:#d97706; }

        .footer {
            position:fixed; bottom:0; left:0; right:0;
            padding:8px 25px; background:#f8fafc;
            border-top:1px solid #e2e8f0;
            font-size:8px; color:#94a3b8;
            display:flex; justify-content:space-between;
        }
    </style>
</head>
<body>

    {{-- HEADER --}}
    <div class="header">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <div class="logo">CIBLE CI</div>
                <div class="logo-sub">RAPPORT PIGES PHOTOS</div>
                @if($campaign)
                <div style="color:white; margin-top:6px; font-size:11px;">
                    Campagne : {{ $campaign->name }}
                </div>
                @endif
            </div>
            <div style="text-align:right; color:#8a90a2; font-size:9px;">
                <div>{{ $piges->count() }} piges</div>
                <div>{{ now()->format('d/m/Y') }}</div>
            </div>
        </div>
    </div>

    {{-- PIGES --}}
    <div style="padding:0 25px 40px;">
        @forelse($piges as $pige)
        <div class="pige-card">

            {{-- EN-TÊTE --}}
            <div class="pige-header">
                <div>
                    <span class="pige-ref">{{ $pige->panel->reference }}</span>
                    <span style="color:#64748b; font-size:11px; margin-left:8px;">
                        {{ $pige->panel->commune->name }}
                    </span>
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                    @if($pige->is_verified)
                        <span class="badge badge-green">✓ Vérifiée</span>
                    @else
                        <span class="badge badge-orange">En attente</span>
                    @endif
                    <span style="font-size:10px; color:#64748b;">
                        {{ $pige->taken_at->format('d/m/Y') }}
                    </span>
                </div>
            </div>

            {{-- CORPS --}}
            <div class="pige-body">

                {{-- PHOTO en base64 --}}
                <div class="pige-photo">
                    @php
                        $imagePath = storage_path('app/public/' . $pige->photo_path);
                        $imageData = '';
                        $mimeType  = 'image/jpeg';
                        if (file_exists($imagePath)) {
                            $imageData = base64_encode(file_get_contents($imagePath));
                            $mimeType  = mime_content_type($imagePath);
                        }
                    @endphp
                    @if($imageData)
                        <img src="data:{{ $mimeType }};base64,{{ $imageData }}"
                             style="width:200px; height:150px; object-fit:cover; display:block;">
                    @else
                        <div style="width:200px; height:150px; background:#f1f5f9;
                                    display:flex; align-items:center; justify-content:center;
                                    color:#94a3b8; font-size:11px;">
                            Photo non disponible
                        </div>
                    @endif
                </div>

                {{-- INFOS --}}
                <div class="pige-infos">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                        <div class="field">
                            <div class="field-label">Campagne</div>
                            <div class="field-value">{{ $pige->campaign?->name ?? '—' }}</div>
                        </div>
                        <div class="field">
                            <div class="field-label">Prise par</div>
                            <div class="field-value">{{ $pige->takenBy->name }}</div>
                        </div>
                        <div class="field">
                            <div class="field-label">Date de prise</div>
                            <div class="field-value">{{ $pige->taken_at->format('d/m/Y') }}</div>
                        </div>
                        @if($pige->gps_lat && $pige->gps_lng)
                        <div class="field">
                            <div class="field-label">GPS</div>
                            <div class="field-value" style="font-family:monospace; font-size:10px;">
                                {{ $pige->gps_lat }}, {{ $pige->gps_lng }}
                            </div>
                        </div>
                        @endif
                        @if($pige->is_verified)
                        <div class="field">
                            <div class="field-label">Vérifiée par</div>
                            <div class="field-value">{{ $pige->verifiedBy?->name ?? '—' }}</div>
                        </div>
                        <div class="field">
                            <div class="field-label">Date vérification</div>
                            <div class="field-value">
                                {{ $pige->verified_at?->format('d/m/Y') ?? '—' }}
                            </div>
                        </div>
                        @endif
                    </div>
                    @if($pige->notes)
                    <div class="field" style="margin-top:6px;">
                        <div class="field-label">Notes</div>
                        <div style="color:#475569;">{{ $pige->notes }}</div>
                    </div>
                    @endif
                </div>

            </div>
        </div>
        @empty
        <div style="text-align:center; padding:40px; color:#94a3b8;">
            Aucune pige trouvée
        </div>
        @endforelse
    </div>

    {{-- FOOTER --}}
    <div class="footer">
        <div>CIBLE CI — Document confidentiel</div>
        <div>Généré le {{ now()->format('d/m/Y à H:i') }}</div>
        <div>{{ $piges->count() }} piges au total</div>
    </div>

</body>
</html>

