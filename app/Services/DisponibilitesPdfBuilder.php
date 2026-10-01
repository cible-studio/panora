<?php

namespace App\Services;

use App\Models\ExternalPanel;
use App\Models\Panel;
use App\Support\PdfAssets;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * PDF « images » des disponibilités — une page par panneau avec photo.
 *
 * Extrait le 2026-09-29 de ReservationController::pdfImages() : la
 * diffusion automatique des disponibilités aux clients produit le MÊME
 * document que l'export manuel du media planner. Une seule construction
 * pour les deux — c'est une copie parallèle de ce calcul qui avait fait
 * afficher « Actuellement occupé » sur une recherche de novembre.
 */
class DisponibilitesPdfBuilder
{
    use PdfAssets;

    public function __construct(
        private AvailabilityService $availability,
        private PdfExportService $pdfExport,
    ) {}

    /**
     * Lignes prêtes pour la vue PDF, avec le statut recalculé SUR LA
     * PÉRIODE et la date de libération des panneaux occupés.
     *
     * @param  int[]   $internalIds
     * @param  int[]   $externalIds
     * @param  bool    $photosCompactes  photos réduites (800×600, JPEG q60) :
     *                                   indispensable sur tout le parc, sinon
     *                                   364 photos pleine résolution = PDF de
     *                                   100+ Mo et 5+ minutes de génération.
     * @return Collection<int, array>
     */
    public function lignes(
        array $internalIds,
        array $externalIds,
        ?string $startDate,
        ?string $endDate,
        bool $photosCompactes = false
    ): Collection {
        $enriched = collect();

        if ($internalIds) {
            $internals = Panel::with([
                'commune:id,name',
                'zone:id,name',
                'format:id,name,width,height',
                'category:id,name',
                'photos' => fn($q) => $q->orderBy('ordre'),
            ])
                ->whereIn('id', $internalIds)
                ->orderByRaw('FIELD(id,' . implode(',', array_map('intval', $internalIds)) . ')')
                ->get();
            $enriched = $enriched->merge(
                $internals->map(fn($p) => $this->pdfExport->enrichPanel($p, $photosCompactes))
            );
        }

        if ($externalIds) {
            $externals = ExternalPanel::with([
                'commune:id,name',
                'zone:id,name',
                'format:id,name,width,height',
                'category:id,name',
                'agency:id,name',
            ])
                ->whereIn('id', $externalIds)
                ->orderByRaw('FIELD(id,' . implode(',', array_map('intval', $externalIds)) . ')')
                ->get();
            $enriched = $enriched->merge(
                $externals->map(fn($p) => $this->pdfExport->enrichExternalPanel($p))
            );
        }

        $panels = $enriched->values();

        // Recalcul display_status + release_date :
        // - Si période fournie → blocking sur cette période.
        // - Sinon → fallback today → +1 an pour toujours récupérer la
        //   date de libération du panneau s'il est actuellement occupé.
        $lookupStart = $startDate ?: now()->toDateString();
        $lookupEnd   = $endDate   ?: now()->addYear()->toDateString();

        $internalBlocking = $internalIds
            ? $this->availability->getInternalPanelBookingMap($internalIds, $lookupStart, $lookupEnd)->keyBy('panel_id')
            : collect();
        $externalBlocking = $externalIds
            ? $this->availability->getExternalPanelBookingMap($externalIds, $lookupStart, $lookupEnd)->keyBy('id')
            : collect();

        // Campagnes actives : un panneau peut être occupé via une campagne
        // sans réservation associée.
        if ($internalIds) {
            $campaignRelease = DB::table('campaign_panels')
                ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
                ->whereIn('campaign_panels.panel_id', $internalIds)
                ->where('campaign_panels.type', 'interne')
                ->whereIn('campaigns.status', ['actif', 'pause'])
                ->where('campaigns.end_date', '>=', $lookupStart)
                ->select(
                    'campaign_panels.panel_id',
                    DB::raw('MAX(campaigns.end_date) as release_date')
                )
                ->groupBy('campaign_panels.panel_id')
                ->get()
                ->keyBy('panel_id');

            $internalBlocking = $internalBlocking->map(function ($b) use ($campaignRelease) {
                $camp = $campaignRelease->get($b->panel_id);
                if ($camp && (!$b->release_date || $camp->release_date > $b->release_date)) {
                    $b->release_date  = $camp->release_date;
                    $b->has_confirmed = 1;
                }
                return $b;
            });

            foreach ($campaignRelease as $panelId => $camp) {
                if (!$internalBlocking->has($panelId)) {
                    $internalBlocking->put($panelId, (object) [
                        'panel_id'      => $panelId,
                        'has_confirmed' => 1,
                        'has_option'    => 0,
                        'release_date'  => $camp->release_date,
                    ]);
                }
            }
        }

        return $panels->map(function ($row) use ($internalBlocking, $externalBlocking, $startDate, $endDate) {
            $isExt = ($row['source'] ?? null) === 'external';
            $booking = $isExt
                ? $externalBlocking->get($row['id'] ?? null)
                : $internalBlocking->get($row['id'] ?? null);

            // Le statut décrit LA PÉRIODE demandée, jamais l'instant présent.
            $row['display_status'] = AvailabilityService::displayStatusForPeriod(
                $row['display_status'] ?? null,
                (bool) ($startDate && $endDate),
                (bool) ($booking->has_confirmed ?? false),
                (bool) ($booking->has_option ?? false)
            );
            $row['release_date'] = $booking->release_date ?? null;
            return $row;
        })->values();
    }

    /**
     * Rendu du PDF.
     *
     * @param  array{reservation_ref?: ?string, client_name?: ?string,
     *               hide_status?: bool, show_pricing?: bool, titre?: ?string} $options
     */
    public function rendre(Collection $lignes, ?string $startDate, ?string $endDate, array $options = []): \Barryvdh\DomPDF\PDF
    {
        return \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'admin.reservations.pdf.disponibilites-images',
            [
                'panels'          => $lignes,
                'startDate'       => $startDate,
                'endDate'         => $endDate,
                'generated'       => now()->format('d/m/Y à H:i'),
                'reservation_ref' => $options['reservation_ref'] ?? null,
                'client_name'     => $options['client_name'] ?? null,
                'logoSrc'         => $this->getLogoPdf(),
                'hideStatus'      => (bool) ($options['hide_status'] ?? true),
                'showPricing'     => (bool) ($options['show_pricing'] ?? false),
            ]
        )
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => false,
                'defaultFont'          => 'DejaVu Sans',
                'dpi'                  => 96,
            ], true);
    }
}
