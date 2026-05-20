<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * KpiForecastService — prévisions statistiques simples (COMMIT D).
 *
 * Implémente une régression linéaire des moindres carrés sur les
 * 12 derniers mois pour projeter :
 *   - le CA prévisionnel sur N prochains mois
 *   - le taux d'occupation prévisionnel sur N prochains mois
 *
 * Pas d'IA / pas de dépendances externes — juste les maths du lycée.
 * Avantages :
 *   - 100 % offline, pas de coût d'API
 *   - Explicable au client (pente + intercept → prévision)
 *   - R² (coefficient de détermination) donne la "confiance" du modèle
 *
 * Limites :
 *   - Ne capture pas la saisonnalité (fêtes, vacances scolaires, etc.)
 *   - Hypothèse linéaire — si la tendance est exponentielle ou cyclique,
 *     prévision faussée. Pour le cas OOH CI, la tendance est relativement
 *     stable mois après mois, donc l'approximation linéaire est OK.
 */
class KpiForecastService
{
    public function __construct(protected DashboardKpiService $kpi) {}

    /**
     * Prévision de CA pour les N prochains mois (régression linéaire).
     */
    public function revenueForecast(int $months = 3): array
    {
        $history = $this->kpi->revenueByMonth(12);
        $values  = $history->pluck('total')->map(fn($v) => (float) $v)->all();
        return $this->forecast($values, $months, 'CA');
    }

    /**
     * Prévision du taux d'occupation pour les N prochains mois.
     */
    public function occupationForecast(int $months = 3): array
    {
        $history = $this->kpi->occupationTrend(12);
        $values  = $history->pluck('rate')->map(fn($v) => (float) $v)->all();
        return $this->forecast($values, $months, 'Occupation', 100);
    }

    /**
     * Régression linéaire générique sur série temporelle équidistante.
     *
     * @param array  $values Série historique (du plus ancien au plus récent)
     * @param int    $futureMonths Nombre de mois à projeter
     * @param string $label   Étiquette de l'indicateur
     * @param float|null $clampMax Borne max (utile pour taux d'occupation)
     */
    protected function forecast(array $values, int $futureMonths, string $label, ?float $clampMax = null): array
    {
        $n = count($values);
        if ($n < 4) {
            // Trop peu de données pour faire une régression fiable
            return [
                'label'      => $label,
                'history'    => $values,
                'forecast'   => [],
                'slope'      => 0,
                'intercept'  => $n > 0 ? array_sum($values) / $n : 0,
                'r_squared'  => 0,
                'confidence' => 0,
                'message'    => 'Pas assez d\'historique pour projeter (' . $n . ' point(s)).',
            ];
        }

        // x = indices 0..n-1, y = valeurs
        $sumX = 0; $sumY = 0; $sumXY = 0; $sumXX = 0; $sumYY = 0;
        for ($i = 0; $i < $n; $i++) {
            $x = $i; $y = $values[$i];
            $sumX  += $x;
            $sumY  += $y;
            $sumXY += $x * $y;
            $sumXX += $x * $x;
            $sumYY += $y * $y;
        }
        $meanX = $sumX / $n;
        $meanY = $sumY / $n;

        // Pente et ordonnée à l'origine
        $denom = ($sumXX - $n * $meanX * $meanX);
        $slope = $denom != 0 ? ($sumXY - $n * $meanX * $meanY) / $denom : 0;
        $intercept = $meanY - $slope * $meanX;

        // Coefficient de détermination R² (qualité de l'ajustement)
        $ssTot = 0; $ssRes = 0;
        for ($i = 0; $i < $n; $i++) {
            $predicted = $intercept + $slope * $i;
            $ssTot += pow($values[$i] - $meanY, 2);
            $ssRes += pow($values[$i] - $predicted, 2);
        }
        $rSquared = $ssTot > 0 ? max(0, 1 - ($ssRes / $ssTot)) : 0;
        // Confiance affichée = R² × 100, mais on borne à [0, 95] car un R²
        // de 1.0 sur un historique aussi court n'est pas réaliste.
        $confidence = round(min(95, $rSquared * 100));

        // Projection sur les N prochains mois
        $forecast = [];
        for ($k = 1; $k <= $futureMonths; $k++) {
            $idx = $n - 1 + $k;
            $val = $intercept + $slope * $idx;
            if ($val < 0) $val = 0;
            if ($clampMax !== null && $val > $clampMax) $val = $clampMax;
            $date = now()->addMonths($k);
            $forecast[] = [
                'label' => $date->translatedFormat('M Y'),
                'value' => round($val, 2),
                'trend' => $slope > 0 ? '↗ hausse' : ($slope < 0 ? '↘ baisse' : '→ stable'),
                'month_offset' => $k,
            ];
        }

        return [
            'label'      => $label,
            'history'    => $values,
            'forecast'   => $forecast,
            'slope'      => round($slope, 4),
            'intercept'  => round($intercept, 2),
            'r_squared'  => round($rSquared, 4),
            'confidence' => $confidence,
            'trend_direction' => $slope > 0 ? 'up' : ($slope < 0 ? 'down' : 'flat'),
            'trend_pct_per_month' => $meanY > 0 ? round(($slope / $meanY) * 100, 1) : 0,
        ];
    }
}
