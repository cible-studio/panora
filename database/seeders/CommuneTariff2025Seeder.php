<?php

namespace Database\Seeders;

use App\Models\Commune;
use App\Models\CommuneTaxRateHistory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Tarifs ODP/m²/mois 2025 (source : tableau « NOUVEAUX TARIFS DE L'ODP/m²
 * EN 2025 » validé par CIBLE le 2026-06-08).
 *
 * TM fixe à 1000 F/m²/mois pour TOUTES les communes.
 *
 * On s'appuie sur la table existante `commune_tax_rate_history`
 * (effective_from / effective_to) déjà utilisée par
 * Commune::ratesAt($date) — pas de table dédiée additionnelle.
 *
 * Comportement idempotent :
 *   - Si une ligne avec effective_from='2025-01-01' existe → UPDATE
 *   - Sinon → INSERT
 *
 * Si une commune mentionnée ici n'existe pas dans `communes`, elle est
 * SKIP avec un warning (à l'admin de créer la commune d'abord).
 */
class CommuneTariff2025Seeder extends Seeder
{
    public function run(): void
    {
        $effectiveFrom = '2025-01-01';
        $effectiveTo   = '2025-12-31';

        // Tarifs ODP/m²/mois — source : image validée 2026-06-08
        $odpRates = [
            'Plateau'     => 15000,
            'Cocody'      => 5000,
            'Adjamé'      => 3000,
            'Koumassi'    => 3000,
            'San Pedro'   => 3000,
            'Assinie'     => 2000,
            'Bingerville' => 2000,
            'Abengourou'  => 1000,
            'Abobo'       => 1000,
            'Aboisso'     => 1000,
            'Adzopé'      => 1000,
            'Anyama'      => 1000,
            'Attécoubé'   => 1000,
            'Bassam'      => 1000,
            'Bondoukou'   => 1000,
            'Bonoua'      => 1000,
            'Bouaké'      => 1000,
            'Daloa'       => 1000,
            'Divo'        => 1000,
            'Ferké'       => 1000,
            'Gagnoa'      => 1000,
            'Korhogo'     => 1000,
            'Man'         => 1000,
            'Marcory'     => 1000,
            'Odienné'     => 1000,
            'Port-Bouët'  => 1000,
            'Samo'        => 1000,
            'Songon'      => 1000,
            'Soubré'      => 1000,
            'Toumodi'     => 1000,
            'Treichville' => 1000,
            'Yakro'       => 1000,
            'Yopougon'    => 1000,
        ];

        $inserted = 0;
        $updated  = 0;
        $missing  = [];

        foreach ($odpRates as $name => $odp) {
            $commune = $this->findCommuneByName($name);
            if (!$commune) {
                $missing[] = $name;
                continue;
            }

            $existing = CommuneTaxRateHistory::where('commune_id', $commune->id)
                ->whereDate('effective_from', $effectiveFrom)
                ->first();

            if ($existing) {
                $existing->update([
                    'odp_rate'       => $odp,
                    'tm_rate'        => 1000,
                    'effective_to'   => $effectiveTo,
                    'notes'          => 'Tarifs 2025 CIBLE — image validée 2026-06-08 (seeder)',
                ]);
                $updated++;
            } else {
                CommuneTaxRateHistory::create([
                    'commune_id'     => $commune->id,
                    'odp_rate'       => $odp,
                    'tm_rate'        => 1000,
                    'effective_from' => $effectiveFrom,
                    'effective_to'   => $effectiveTo,
                    'notes'          => 'Tarifs 2025 CIBLE — image validée 2026-06-08 (seeder)',
                ]);
                $inserted++;
            }
        }

        $this->command->info("✓ Tarifs 2025 seedés : {$inserted} créés, {$updated} mis à jour.");

        if (!empty($missing)) {
            $this->command->warn(
                'Communes absentes (skip) : ' . implode(', ', $missing) .
                '. Créez-les via /admin/communes puis rejouez le seeder.'
            );
        }
    }

    /**
     * Recherche tolérante : accepte les variations d'orthographe sans accent.
     */
    private function findCommuneByName(string $name): ?Commune
    {
        $normalized = strtr(strtolower($name), [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'à' => 'a', 'â' => 'a', 'ô' => 'o', 'ï' => 'i',
            'ç' => 'c',
        ]);

        return Commune::query()
            ->where('name', $name)
            ->orWhereRaw(
                'LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(name, "é","e"),"è","e"),"ê","e"),"ë","e"),"à","a"),"â","a"),"ô","o"),"ï","i"),"ç","c")) = ?',
                [$normalized]
            )
            ->first();
    }
}
