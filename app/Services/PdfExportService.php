<?php
namespace App\Services;

use App\Models\Panel;
use App\Models\Commune;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfExportService
{
    // ── FICHE PANNEAU ──
    public function exportPanelSheet(Panel $panel): mixed
    {
        $panel->load(
            'commune', 'zone', 'format',
            'category', 'photos', 'maintenances'
        );

        $pdf = Pdf::loadView('pdf.panel-sheet', compact('panel'));
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download("panneau-{$panel->reference}.pdf");
    }

    // ── LISTE PANNEAUX ──
    public function exportPanelList(array $filters = []): mixed
    {
        $query = Panel::with('commune', 'zone', 'format', 'category');

        if (!empty($filters['commune_id'])) {
            $query->where('commune_id', $filters['commune_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $panels   = $query->orderBy('reference')->get();
        $commune  = !empty($filters['commune_id'])
            ? Commune::find($filters['commune_id'])
            : null;

        $pdf = Pdf::loadView('pdf.panel-list', compact('panels', 'commune', 'filters'));
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download('liste-panneaux.pdf');
    }

    // ── RÉSEAU PANNEAUX (pour client) ──
    public function exportNetworkReport(): mixed
    {
        $communes = Commune::with(['panels' => function($q) {
            $q->with('format', 'category')
              ->orderBy('reference');
        }])->get();

        $totalPanneaux  = Panel::count();
        $panneauxLibres = Panel::where('status', 'libre')->count();
        $tauxOccupation = $totalPanneaux > 0
            ? round((($totalPanneaux - $panneauxLibres) / $totalPanneaux) * 100, 1)
            : 0;

        $pdf = Pdf::loadView('pdf.network-report', compact(
            'communes', 'totalPanneaux',
            'panneauxLibres', 'tauxOccupation'
        ));
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('reseau-panneaux-cible-ci.pdf');
    }
}
