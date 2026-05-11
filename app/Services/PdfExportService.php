<?php
// ══════════════════════════════════════════════════════════════════
// app/Services/PdfExportService.php — Version optimisée
// ══════════════════════════════════════════════════════════════════

namespace App\Services;

use App\Models\Panel;
use App\Models\ExternalPanel;
use App\Models\Commune;
use App\Support\PdfAssets;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PdfExportService
{
    use PdfAssets;

    // ══════════════════════════════════════════════════════════════
    // FICHE PANNEAU INDIVIDUEL
    // ══════════════════════════════════════════════════════════════

    public function exportPanelSheet(Panel $panel): mixed
    {
        $panel->load('commune', 'zone', 'format', 'category', 'photos');

        $enriched = $this->enrichPanel($panel);
        $logoSrc  = $this->getLogoPdf();
        $generated = now()->format('d/m/Y à H:i');

        $pdf = Pdf::loadView('admin.panels.pdf.fiche', [
            'panel'     => $enriched,
            'logoSrc'   => $logoSrc,
            'generated' => $generated,
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions($this->dompdfOptions());

        return $pdf->stream("fiche-panneau-{$panel->reference}.pdf");
    }

    // ══════════════════════════════════════════════════════════════
    // LISTE PANNEAUX (format avec images — comme disponibilités)
    // ══════════════════════════════════════════════════════════════

    public function exportPanelList(array $filters = []): mixed
    {
        $query = Panel::with([
            'commune:id,name',
            'zone:id,name',
            'format:id,name,width,height',
            'category:id,name',
            // On ne tire que la photo principale (ordre asc, limit 1) — drastiquement
            // moins lourd qu'un .photos full sur 200+ panneaux
            'photos' => fn($q) => $q->orderBy('ordre')->limit(1),
        ]);

        if (!empty($filters['commune_id'])) {
            $query->where('commune_id', (int) $filters['commune_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['zone_id'])) {
            $query->where('zone_id', (int) $filters['zone_id']);
        }

        $panelModels = $query->orderBy('reference')->get();
        $panels      = $panelModels->map(fn($p) => $this->enrichPanel($p));
        $hideStatus  = !empty($filters['hide_status']);
        $logoSrc     = $this->getLogoPdf();

        $commune = !empty($filters['commune_id'])
            ? Commune::find($filters['commune_id'])
            : null;

        $pdf = Pdf::loadView('admin.reservations.pdf.disponibilites-images', [
            'panels'     => $panels,
            'startDate'  => $filters['start_date'] ?? null,
            'endDate'    => $filters['end_date']   ?? null,
            'generated'  => now()->format('d/m/Y à H:i'),
            'hideStatus' => $hideStatus,
            'logoSrc'    => $logoSrc,
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions($this->dompdfOptions());

        $filename = 'liste-panneaux' . ($commune ? '-' . \Str::slug($commune->name) : '');
        return $pdf->stream("{$filename}.pdf");
    }

    // ══════════════════════════════════════════════════════════════
    // RAPPORT RÉSEAU (vue dédiée sans images — format texte)
    // ══════════════════════════════════════════════════════════════

    public function exportNetworkReport(): mixed
    {
        $communes = Commune::with([
            'panels' => function ($q) {
                $q->with('format', 'category')->orderBy('reference');
            }
        ])->get();

        $totalPanneaux = Panel::count();
        $panneauxLibres = Panel::where('status', 'libre')->count();
        $tauxOccupation = $totalPanneaux > 0
            ? round((($totalPanneaux - $panneauxLibres) / $totalPanneaux) * 100, 1)
            : 0;

        $pdf = Pdf::loadView('pdf.network-report', compact(
            'communes',
            'totalPanneaux',
            'panneauxLibres',
            'tauxOccupation'
        ))
            ->setPaper('a4', 'portrait')
            ->setOptions($this->dompdfOptions());

        return $pdf->stream('reseau-panneaux-cible-ci.pdf');
    }

    // ══════════════════════════════════════════════════════════════
    // HELPER : enrichir un modèle Panel pour la vue PDF
    // ══════════════════════════════════════════════════════════════

    public function enrichPanel(Panel $panel): array
    {
        // Photo principale : on charge en base64 pour DomPDF (qui ne sait pas
        // suivre les URLs locales) + on garde un fallback URL pour le HTML web.
        $photo = $panel->photos->sortBy('ordre')->first();
        $photoBase64 = null;
        $photoUrl    = null;
        if ($photo) {
            $photoBase64 = $this->photoToDataUri($photo->path);
            if (!$photoBase64) {
                $photoUrl = asset('storage/' . ltrim($photo->path, '/'));
            }
        }

        // Dimensions impression : "WxH m" + surface en m²
        $dims    = null;
        $surface = null;
        if ($panel->format?->width && $panel->format?->height) {
            $w = rtrim(rtrim(number_format($panel->format->width,  2, '.', ''), '0'), '.');
            $h = rtrim(rtrim(number_format($panel->format->height, 2, '.', ''), '0'), '.');
            $dims    = "{$w} × {$h} m";
            $surface = round($panel->format->width * $panel->format->height, 2);
        }

        // Coordonnées GPS — lien Google Maps cliquable (PDF supporte les liens)
        $gpsLink = null;
        if ($panel->latitude !== null && $panel->longitude !== null) {
            $gpsLink = "https://maps.google.com/?q={$panel->latitude},{$panel->longitude}";
        }

        return [
            'id'                => $panel->id,
            'reference'         => $panel->reference,
            'name'              => $panel->name,
            'adresse'           => $panel->adresse ?? null,
            'quartier'          => $panel->quartier ?? null,
            'commune'           => $panel->commune?->name ?? '—',
            'zone'              => $panel->zone?->name ?? '—',
            'format'            => $panel->format?->name ?? '—',
            'format_width'      => $panel->format?->width ?? null,
            'format_height'     => $panel->format?->height ?? null,
            'dimensions'        => $dims,
            'surface_m2'        => $surface,
            'category'          => $panel->category?->name ?? '—',
            'is_lit'            => (bool) $panel->is_lit,
            'monthly_rate'      => (float) ($panel->monthly_rate ?? 0),
            'daily_traffic'     => (int)   ($panel->daily_traffic ?? 0),
            'zone_description'  => $panel->zone_description ?? '',
            'latitude'          => $panel->latitude ?? null,
            'longitude'         => $panel->longitude ?? null,
            'gps_link'          => $gpsLink,
            'display_status'    => $panel->status->value,
            'source'            => 'internal',
            // Pour DomPDF on privilégie le base64 (clé "photo_src"), photo_url reste un fallback
            'photo_src'         => $photoBase64,
            'photo_path'        => $photoBase64 ? null : ($photoUrl ? null : null), // legacy
            'photo_url'         => $photoUrl,
            'release_info'      => null,
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // HELPER : enrichir un ExternalPanel pour les vues PDF (mêmes
    // clés que enrichPanel() afin de réutiliser disponibilites-images
    // et disponibilites-list sans dupliquer les blades).
    // ══════════════════════════════════════════════════════════════

    public function enrichExternalPanel(ExternalPanel $panel): array
    {
        // 1) Tentative directe : data-URI base64 prêt à servir (DomPDF idéal)
        $photoBase64 = $this->photoToDataUri($panel->photo_path);

        // 2) Fallback chemin local : si le base64 a échoué (taille, droits…),
        //    on tente de localiser le fichier sur disque pour que la vue
        //    puisse l'embarquer elle-même (file_exists + base64 inline).
        $localPath = null;
        if (!$photoBase64 && !empty($panel->photo_path)) {
            $rel = ltrim($panel->photo_path, '/');
            foreach ([
                storage_path('app/public/' . $rel),
                public_path('storage/' . $rel),
                public_path($rel),
            ] as $candidate) {
                if (is_file($candidate) && is_readable($candidate)) {
                    $localPath = $candidate;
                    break;
                }
            }
        }

        // 3) Dernier recours : URL via asset() — utile pour le HTML (web),
        //    ignoré par DomPDF car isRemoteEnabled=false.
        $photoUrl = (!$photoBase64 && !$localPath && $panel->photo_path)
            ? asset('storage/' . ltrim($panel->photo_path, '/'))
            : null;

        $dims    = null;
        $surface = null;
        if ($panel->format?->width && $panel->format?->height) {
            $w = rtrim(rtrim(number_format($panel->format->width,  2, '.', ''), '0'), '.');
            $h = rtrim(rtrim(number_format($panel->format->height, 2, '.', ''), '0'), '.');
            $dims    = "{$w} × {$h} m";
            $surface = round($panel->format->width * $panel->format->height, 2);
        }

        $gpsLink = null;
        if ($panel->latitude !== null && $panel->longitude !== null) {
            $gpsLink = "https://maps.google.com/?q={$panel->latitude},{$panel->longitude}";
        }

        // Mapping availability_status (string) → display_status pour le statusMap
        // commun aux vues PDF (qui attendent libre/occupe/option/confirme/maintenance)
        $rawStatus = $panel->availability_status ?? 'disponible';
        $displayStatus = match ($rawStatus) {
            'disponible'                => 'libre',
            'occupe'                    => 'occupe',
            'option', 'option_periode'  => 'option',
            'confirme'                  => 'confirme',
            'maintenance'               => 'maintenance',
            default                     => 'occupe',
        };

        return [
            'id'                => $panel->id,
            // L'ExternalPanel n'a pas de "reference" / "name" — on mappe les libellés
            // exposés par l'agence (code_panneau / designation) sur ces clés pour que
            // les vues PDF marchent sans modification.
            'reference'         => $panel->code_panneau,
            'name'              => $panel->designation,
            'adresse'           => $panel->adresse ?? null,
            'quartier'          => $panel->quartier ?? null,
            'commune'           => $panel->commune?->name ?? '—',
            'zone'              => $panel->zone?->name ?? '—',
            'format'            => $panel->format?->name ?? '—',
            'format_width'      => $panel->format?->width ?? null,
            'format_height'     => $panel->format?->height ?? null,
            'dimensions'        => $dims,
            'surface_m2'        => $surface,
            'category'          => $panel->category?->name ?? ($panel->type ?? '—'),
            'is_lit'            => (bool) $panel->is_lit,
            'monthly_rate'      => (float) ($panel->monthly_rate ?? 0),
            'daily_traffic'     => (int)   ($panel->daily_traffic ?? 0),
            'zone_description'  => $panel->zone_description ?? '',
            'latitude'          => $panel->latitude ?? null,
            'longitude'         => $panel->longitude ?? null,
            'gps_link'          => $gpsLink,
            'display_status'    => $displayStatus,
            'source'            => 'external',
            'agency_name'       => $panel->agency?->name ?? null,
            'photo_src'         => $photoBase64,
            'photo_path'        => $localPath, // chemin LOCAL absolu (fallback view)
            'photo_url'         => $photoUrl,
            'release_info'      => null,
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // OPTIONS DOMPDF COMMUNES (DRY)
    // ══════════════════════════════════════════════════════════════

    private function dompdfOptions(): array
    {
        return [
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
            'dpi' => 96,
            'defaultPaperSize' => 'a4',
        ];
    }
}
