{{-- ═══════════════════════════════════════════════════════════════════
     RAPPORT DE PIGES — PDF prêt à envoyer au client

     Généré par App\Services\Piges\RapportPigesService. Uniquement des
     piges vérifiées (règle du 2026-11-04).

     Historique : 2026-11-04 copie du document Word de l'équipe (« MOOV _
     MILLIONS A SE PARTAGER PHASE 2 ») ; 2026-11-05 v3, retour « le doc est
     trop simple » — même famille que la liste des campagnes :
       · en-tête logo + coordonnées, filet, type / titre / ligne de synthèse ;
       · communes en intertitres, dans l'ordre de la tournée ;
       · un panneau = une fiche : photo dans un cadre fixe (centrée, jamais
         déformée — cf. RapportPigesService::photo), à côté son numéro,
         l'emplacement, la référence, les dimensions et la date de la photo ;
       · pied : coordonnées, date d'édition, numéro de page.
     Couleurs et polices : la charte ($charte), jamais un hex en dur.

     Variables : $titre, $groupes (commune → [['pige', 'photo' => {src,w,h}|null]]),
     $nbPhotos, $nbPanneaux, $debut, $fin, $clients, $campagnes, $vagues.
     ═══════════════════════════════════════════════════════════════════ --}}
@php
    $ch     = $charte ?? \App\Support\PdfCharte::data();
    $coord  = $ch['coordonnees'] ?? [];
    $vagues = $vagues ?? collect();

    $periode = match (true) {
        !$debut                 => null,
        $debut->isSameDay($fin) => 'le ' . $debut->format('d/m/Y'),
        default                 => 'du ' . $debut->format('d/m/Y') . ' au ' . $fin->format('d/m/Y'),
    };
    $nbCommunes = $groupes->count();
    $numero = 0;   // numéro de fiche, continu sur tout le document

    // Axes (décision du 12/11) : UNE fiche par axe, ses pieds dedans. Sans
    // axe dans le document, rien ne change (« N panneaux »).
    $nbAxes = (int) ($nbAxes ?? 0);
    $nbFiches = (int) ($nbFiches ?? $nbPanneaux);
    $rapportPiges = app(\App\Services\Piges\RapportPigesService::class);
    $nomCourt = fn ($nom) => app(\App\Services\Axes\AxeService::class)->nomCourt($nom);
    $unite = $nbAxes > 0 ? ['emplacement', 'emplacements'] : ['panneau', 'panneaux'];
    $volume = $nbAxes > 0
        ? '<strong>' . $nbFiches . '</strong> ' . ($nbFiches > 1 ? $unite[1] : $unite[0])
            . ', dont <strong>' . $nbAxes . '</strong> axe' . ($nbAxes > 1 ? 's' : '')
            . ' (' . (int) ($nbPiedsAxes ?? 0) . ' pieds),'
        : '<strong>' . $nbPanneaux . '</strong> panneau' . ($nbPanneaux > 1 ? 'x' : '');

    // Synthèse sous le titre : valeurs échappées, mise en gras.
    $synthese = implode(' · ', array_filter([
        $clients->count() === 1 ? 'Client <strong>' . e($clients->first()) . '</strong>' : null,
        $vagues->count() === 1 ? e($vagues->first()->libelle()) : null,
        $volume . ' dans <strong>' . $nbCommunes . '</strong> commune' . ($nbCommunes > 1 ? 's' : ''),
        $periode ? 'photos prises ' . e($periode) : null,
    ]));
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>{{ $titre }}</title>
@include('pdf.partials.charte-styles')
@include('pdf.partials.doc-styles')
<style>
    @page { margin: 14mm 15mm 19mm 15mm !important; }
    body { font-family: {!! $ch['ff_texte'] !!}; font-size: 9pt; line-height: 1.4; color: {{ $ch['noir'] }}; }

    /* ── Commune : intertitre ─────────────────────────────────────── */
    .commune {
        font-family: {!! $ch['ff_titres'] !!}; font-size: 9pt; font-weight: 700; letter-spacing: 1.5pt; text-transform: uppercase;
        border-bottom: 0.8pt solid {{ $ch['noir'] }}; padding: 0 0 1.5mm; margin: 3mm 0 4mm;
    }
    .commune .nb { font-family: {!! $ch['ff_texte'] !!}; font-weight: 400; letter-spacing: 0; text-transform: none; color: {{ $ch['texte_doux'] }}; }

    /* ── Fiche d'un panneau : photo | informations ────────────────── */
    .bloc { page-break-inside: avoid; margin: 0 0 5mm; }
    .fiche { width: 100%; border-collapse: collapse; }
    .fiche td { padding: 0; vertical-align: top; }
    .fiche td.cadre { width: 104mm; height: 76mm; background: {{ $ch['gris_clair'] }}; text-align: center; vertical-align: middle; }
    .fiche td.cadre img { display: inline-block; }
    .fiche td.infos { padding-left: 6mm; }
    .num { font-family: {!! $ch['ff_titres'] !!}; font-size: 20pt; font-weight: 700; line-height: 1; color: {{ $ch['rouge'] }}; margin: 0 0 3mm; }
    .lieu { font-family: {!! $ch['ff_titres'] !!}; font-size: 9.5pt; font-weight: 700; line-height: 1.25; margin: 0 0 3mm; word-wrap: break-word; }
    .detail { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
    .detail td { padding: 1.6mm 0; border-top: 0.4pt solid {{ $ch['gris'] }}; vertical-align: top; }
    .detail td.etiquette { width: 22mm; color: {{ $ch['texte_doux'] }}; }
    .sans-photo { color: {{ $ch['texte_pale'] }}; font-size: 8.5pt; }

</style>
</head>
<body>

@include('pdf.partials.charte-footer', ['footerHint' => 'Rapport de piges'])

@include('pdf.partials.doc-entete', [
    'docKicker'       => 'Rapport de piges',
    'docTitle'        => $titre,
    'docSubtitleHtml' => $synthese,
])

@foreach($groupes as $commune => $items)
    @php $parPanneau = $items->groupBy(fn ($item) => $rapportPiges->cleFiche($item['pige'])); @endphp
    @foreach($parPanneau as $photos)
        @php
            $numero++;
            $axe = $photos->first()['pige']->panel?->axe;
            if ($axe) {
                // Les pieds dans l'ordre du terrain, puis par heure.
                $photos = $photos->sortBy([
                    fn ($a, $b) => ($a['pige']->panel?->pied_numero ?? 0) <=> ($b['pige']->panel?->pied_numero ?? 0),
                    fn ($a, $b) => ($a['pige']->taken_at ?? $a['pige']->created_at) <=> ($b['pige']->taken_at ?? $b['pige']->created_at),
                ])->values();
            }
            $pige    = $photos->first()['pige'];
            $panneau = $pige->panel;
            $nbPiedsFiche = $axe ? $photos->pluck('pige.panel_id')->unique()->count() : 0;
            // Dimensions : source unique PanelFormat::dimensions_label / surface_label.
            $dimensions = implode(' · ', array_filter([
                $panneau?->format?->dimensions_label,
                $panneau?->format?->surface_label,
            ]));
        @endphp
        {{-- Le nom de la commune est dans le bloc du premier panneau :
             jamais seul en bas de page. --}}
        <div class="bloc">
            @if($loop->first)
                <div class="commune">{{ $commune }}
                    <span class="nb">· {{ $parPanneau->count() }} {{ $parPanneau->count() > 1 ? $unite[1] : $unite[0] }}</span>
                </div>
            @endif
            @foreach($photos as $item)
                @php $prise = $item['pige']->taken_at ?? $item['pige']->created_at; @endphp
                <table class="fiche" @if(!$loop->first) style="margin-top:4mm" @endif>
                    <tr>
                        <td class="cadre">
                            @if($item['photo'])
                                <img src="{{ $item['photo']['src'] }}" style="width:{{ $item['photo']['w'] }}mm;height:{{ $item['photo']['h'] }}mm">
                            @else
                                <span class="sans-photo">Photo indisponible</span>
                            @endif
                        </td>
                        <td class="infos">
                            @php $piedPhoto = $axe ? ('Pied ' . ($item['pige']->panel?->pied_numero ?? '?')) : null; @endphp
                            @if($loop->first)
                                <div class="num">{{ str_pad((string) $numero, 2, '0', STR_PAD_LEFT) }}</div>
                                @if($axe)
                                    <div class="lieu">{{ $nomCourt($axe->name) ?: $axe->reference }}</div>
                                    <table class="detail">
                                        <tr><td class="etiquette">Axe</td><td>{{ $axe->reference }} · {{ $nbPiedsFiche }} pied{{ $nbPiedsFiche > 1 ? 's' : '' }}</td></tr>
                                        @if($dimensions)<tr><td class="etiquette">Dimensions</td><td>{{ $dimensions }} (par pied)</td></tr>@endif
                                        <tr><td class="etiquette">{{ $piedPhoto }}</td><td>{{ $panneau?->reference }}{{ $prise ? ' · ' . $prise->format('d/m/Y à H:i') : '' }}</td></tr>
                                        @if($campagnes->count() > 1 && $item['pige']->campaign)<tr><td class="etiquette">Campagne</td><td>{{ $item['pige']->campaign->name }}</td></tr>@endif
                                    </table>
                                @else
                                    <div class="lieu">{{ $panneau?->name ?: $panneau?->reference }}</div>
                                    <table class="detail">
                                        <tr><td class="etiquette">Référence</td><td>{{ $panneau?->reference ?? '—' }}</td></tr>
                                        @if($dimensions)<tr><td class="etiquette">Dimensions</td><td>{{ $dimensions }}</td></tr>@endif
                                        @if($prise)<tr><td class="etiquette">Photo</td><td>{{ $prise->format('d/m/Y à H:i') }}</td></tr>@endif
                                        @if($campagnes->count() > 1 && $item['pige']->campaign)<tr><td class="etiquette">Campagne</td><td>{{ $item['pige']->campaign->name }}</td></tr>@endif
                                    </table>
                                @endif
                            @elseif($axe)
                                <table class="detail">
                                    <tr><td class="etiquette">{{ $piedPhoto }}</td><td>{{ $item['pige']->panel?->reference }}{{ $prise ? ' · ' . $prise->format('d/m/Y à H:i') : '' }}</td></tr>
                                </table>
                            @else
                                <table class="detail">
                                    <tr><td class="etiquette">Photo {{ $loop->iteration }}/{{ $loop->count }}</td><td>{{ $prise?->format('d/m/Y à H:i') }}</td></tr>
                                </table>
                            @endif
                        </td>
                    </tr>
                </table>
            @endforeach
        </div>
    @endforeach
@endforeach

</body>
</html>
