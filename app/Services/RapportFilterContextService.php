<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Commune;
use App\Models\PanelCategory;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Construit un récapitulatif des filtres ACTIFS sur le dashboard Rapports
 * pour l'afficher dans les en-têtes des exports (PDF + Excel).
 *
 * Source unique de vérité partagée par les 4 endpoints d'export du
 * RapportController (exportPdf / exportExcel / exportPanelsOccupationPdf
 * / exportPanelsOccupationExcel) — cf. CLAUDE.md règle 1 "harmonisation
 * globale".
 *
 * IMPORTANT : la résolution des labels (commune_id → nom de commune, etc.)
 * se fait UNE seule fois ici, pour garantir un libellé identique au pixel
 * près entre le PDF et l'Excel.
 */
class RapportFilterContextService
{
    /**
     * Construit la structure de récap depuis la Request des exports.
     *
     * @return array{
     *     periode_label: string,
     *     period_from:   ?Carbon,
     *     period_to:     ?Carbon,
     *     filters:       array<int, array{icon: string, label: string, value: string}>,
     *     hasAnyFilter:  bool,
     * }
     */
    public function build(Request $request, Carbon $from, Carbon $to): array
    {
        return [
            'periode_label' => $this->buildPeriodLabel($request, $from, $to),
            'period_from'   => $from,
            'period_to'     => $to,
            'filters'       => $this->buildActiveFilters($request),
            'hasAnyFilter'  => $this->hasAnyFilter($request),
        ];
    }

    /**
     * Une seule ligne compacte pour les bandeaux Excel (où l'espace est
     * limité à 1 cellule). Utilisée comme première metaLine des sheets.
     */
    public function buildOneLine(array $recap): string
    {
        $parts = ['Période : ' . $recap['periode_label']];
        foreach ($recap['filters'] as $f) {
            $parts[] = $f['label'] . ' : ' . $f['value'];
        }
        return implode('   ·   ', $parts);
    }

    private function buildPeriodLabel(Request $request, Carbon $from, Carbon $to): string
    {
        $dates = $from->format('d/m/Y') . ' → ' . $to->format('d/m/Y');

        $preset = $request->input('preset');
        $presetLabels = [
            'today'   => 'Aujourd\'hui',
            'week'    => 'Cette semaine',
            'month'   => 'Ce mois',
            'quarter' => 'Ce trimestre',
            'year'    => 'Cette année',
            'all'     => 'Depuis le début',
        ];
        if (isset($presetLabels[$preset])) {
            return $dates . ' (' . $presetLabels[$preset] . ')';
        }

        if ($request->filled('from') && $request->filled('to')) {
            return $dates . ' (intervalle personnalisé)';
        }

        if ($request->filled('annee') || $request->filled('mois_du') || $request->filled('mois_au')) {
            $annee  = (int) ($request->input('annee') ?? date('Y'));
            $moisDu = (int) ($request->input('mois_du') ?? 1);
            $moisAu = (int) ($request->input('mois_au') ?? 12);
            $mois = [1=>'Jan',2=>'Fév',3=>'Mar',4=>'Avr',5=>'Mai',6=>'Jui',7=>'Jul',8=>'Aoû',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Déc'];
            return $dates . ' (' . ($mois[$moisDu] ?? '?') . ' → ' . ($mois[$moisAu] ?? '?') . ' ' . $annee . ')';
        }

        return $dates;
    }

    /**
     * Renvoie uniquement les filtres effectivement actifs, avec leur label
     * résolu (IDs → noms). L'ordre est volontairement stable :
     *   Zone → Commune → Ville → Client → Catégorie panneau.
     */
    private function buildActiveFilters(Request $request): array
    {
        $rows = [];

        $zone = $request->input('filter_zone');
        if (in_array($zone, ['abidjan', 'interieur'], true)) {
            $rows[] = [
                'icon'  => '🌍',
                'label' => 'Zone',
                'value' => $zone === 'abidjan' ? 'Abidjan' : 'Intérieur (hors Abidjan)',
            ];
        }

        if ($communeId = $request->input('filter_commune_id')) {
            $name = Commune::whereKey($communeId)->value('name');
            if ($name) {
                $rows[] = ['icon' => '🏙️', 'label' => 'Commune', 'value' => $name];
            }
        }

        if ($city = trim((string) $request->input('filter_city'))) {
            $rows[] = ['icon' => '🏘️', 'label' => 'Ville', 'value' => $city];
        }

        if ($clientId = $request->input('filter_client_id')) {
            $name = Client::whereKey($clientId)->value('name');
            if ($name) {
                $rows[] = ['icon' => '👤', 'label' => 'Client', 'value' => $name];
            }
        }

        if ($categoryId = $request->input('filter_category_id')) {
            $name = PanelCategory::whereKey($categoryId)->value('name');
            if ($name) {
                $rows[] = ['icon' => '🏷️', 'label' => 'Catégorie panneau', 'value' => $name];
            }
        }

        return $rows;
    }

    private function hasAnyFilter(Request $request): bool
    {
        return $request->filled('filter_zone')
            || $request->filled('filter_commune_id')
            || $request->filled('filter_city')
            || $request->filled('filter_client_id')
            || $request->filled('filter_category_id');
    }
}
