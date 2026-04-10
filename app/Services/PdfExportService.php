<?php
// ══════════════════════════════════════════════════════════════════
// app/Services/PdfExportService.php — Version optimisée
// ══════════════════════════════════════════════════════════════════

namespace App\Services;

use App\Models\Panel;
use App\Models\Commune;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PdfExportService
{
    // ══════════════════════════════════════════════════════════════
    // FICHE PANNEAU INDIVIDUEL
    // ══════════════════════════════════════════════════════════════

    public function exportPanelSheet(Panel $panel): mixed
    {
        $panel->load('commune', 'zone', 'format', 'category', 'photos');

        $enriched = $this->enrichPanel($panel);

        $pdf = Pdf::loadView('admin.reservations.pdf.disponibilites-images', [
            'panels'    => collect([$enriched]),
            'startDate' => null,
            'endDate'   => null,
            'generated' => now()->format('d/m/Y'),
        ])
        ->setPaper('a4', 'portrait')
        ->setOptions($this->dompdfOptions());

        return $pdf->stream("panneau-{$panel->reference}.pdf");
    }

    // ══════════════════════════════════════════════════════════════
    // LISTE PANNEAUX (format avec images — comme disponibilités)
    // ══════════════════════════════════════════════════════════════

    public function exportPanelList(array $filters = []): mixed
    {
        $query = Panel::with('commune', 'zone', 'format', 'category', 'photos');

        if (!empty($filters['commune_id'])) {
            $query->where('commune_id', (int)$filters['commune_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['zone_id'])) {
            $query->where('zone_id', (int)$filters['zone_id']);
        }

        $panelModels = $query->orderBy('reference')->get();
        $panels      = $panelModels->map(fn($p) => $this->enrichPanel($p));

        $commune = !empty($filters['commune_id'])
            ? Commune::find($filters['commune_id'])
            : null;

        $pdf = Pdf::loadView('admin.reservations.pdf.disponibilites-images', [
            'panels'    => $panels,
            'startDate' => $filters['start_date'] ?? null,
            'endDate'   => $filters['end_date']   ?? null,
            'generated' => now()->format('d/m/Y'),
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
        $communes = Commune::with(['panels' => function ($q) {
            $q->with('format', 'category')->orderBy('reference');
        }])->get();

        $totalPanneaux  = Panel::count();
        $panneauxLibres = Panel::where('status', 'libre')->count();
        $tauxOccupation = $totalPanneaux > 0
            ? round((($totalPanneaux - $panneauxLibres) / $totalPanneaux) * 100, 1)
            : 0;

        $pdf = Pdf::loadView('pdf.network-report', compact(
            'communes', 'totalPanneaux', 'panneauxLibres', 'tauxOccupation'
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
        $photo     = $panel->photos->sortBy('ordre')->first();
        $photoPath = null;
        $photoUrl  = null;

        if ($photo) {
            $rel = ltrim($photo->path, '/');
            foreach ([
                storage_path('app/public/' . $rel),
                public_path('storage/' . $rel),
            ] as $candidate) {
                if (file_exists($candidate)) {
                    $photoPath = $candidate;
                    break;
                }
            }
            if (!$photoPath) {
                $photoUrl = asset('storage/' . $rel);
            }
        }

        $dims = null;
        if ($panel->format?->width && $panel->format?->height) {
            $w = rtrim(rtrim(number_format($panel->format->width,  2, '.', ''), '0'), '.');
            $h = rtrim(rtrim(number_format($panel->format->height, 2, '.', ''), '0'), '.');
            $dims = "{$w}x{$h}m";
        }

        return [
            'id'               => $panel->id,
            'reference'        => $panel->reference,
            'name'             => $panel->name,
            'commune'          => $panel->commune?->name  ?? '—',
            'zone'             => $panel->zone?->name     ?? '—',
            'format'           => $panel->format?->name   ?? '—',
            'format_width'     => $panel->format?->width  ?? null,
            'format_height'    => $panel->format?->height ?? null,
            'dimensions'       => $dims,
            'category'         => $panel->category?->name ?? '—',
            'is_lit'           => (bool)$panel->is_lit,
            'monthly_rate'     => (float)($panel->monthly_rate  ?? 0),
            'daily_traffic'    => (int)($panel->daily_traffic   ?? 0),
            'zone_description' => $panel->zone_description ?? '',
            'display_status'   => $panel->status->value,
            'source'           => 'internal',
            'photo_path'       => $photoPath,
            'photo_url'        => $photoUrl,
            'release_info'     => null,
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // OPTIONS DOMPDF COMMUNES (DRY)
    // ══════════════════════════════════════════════════════════════

    private function dompdfOptions(): array
    {
        return [
            'isRemoteEnabled'      => false,
            'isHtml5ParserEnabled' => true,
            'defaultFont'          => 'DejaVu Sans',
            'dpi'                  => 96,
            'defaultPaperSize'     => 'a4',
        ];
    }
}