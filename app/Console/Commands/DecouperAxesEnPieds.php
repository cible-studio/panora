<?php

namespace App\Console\Commands;

use App\Models\Panel;
use App\Services\Axes\AxeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Découpe les axes en pieds — phase 1 (2026-11-11).
 *
 * Un axe est aujourd'hui UNE ligne `panels` qui regroupe plusieurs
 * supports. Cette commande crée un panneau par pied, rattaché à la ligne
 * de son axe, et marque celle-ci comme non vendable.
 *
 * ⚠ **Simulation par défaut.** Rien n'est écrit sans `--appliquer`. Le
 * reste du projet utilise plutôt `--simuler` en option, mais ici le geste
 * crée une centaine de lignes dans le parc : le défaut prudent est de ne
 * rien faire et de montrer ce qui serait fait.
 *
 * Rejouable : un axe déjà découpé est ignoré, un pied déjà présent n'est
 * pas recréé.
 *
 * Ce qui est recopié depuis l'axe : commune, zone, format, catégorie,
 * statut, tarif, éclairage, VIP, trafic, maintenance, adresse, quartier,
 * axe routier, type de support, orientation, et **odp_start_date** — sans
 * elle, `Panel::booted()` daterait chaque pied d'aujourd'hui et ferait
 * repartir son ODP à zéro.
 *
 * Ce qui n'est PAS recopié : la **position GPS**. Les pieds d'un axe sont
 * répartis sur des centaines de mètres ; recopier la position de l'axe
 * ferait rendre « trop loin » au contrôle géo des piges
 * (`GeoService::pigePanelCheck`). Laissée nulle, le verdict est
 * `no_panel_gps` — neutre et vrai — et la géolocalisation automatique
 * (`gps_source = pige_provisional`) remplit chaque pied depuis sa
 * première photo de terrain.
 *
 * Le tarif est recopié **tel quel**, jamais « corrigé » : deux anomalies
 * connues (CDYPUB-09 à 100 000, CDYLUP-10 réservé à 0) attendent une
 * décision — cf. docs/TECHNICAL_DEBT.md.
 */
class DecouperAxesEnPieds extends Command
{
    protected $signature = 'axes:decouper
        {--appliquer : écrit réellement en base (sans cette option, simple simulation)}
        {--axe= : limiter à une référence d\'axe (ex. CDYLUP-004)}';

    protected $description = 'Crée un panneau par pied pour chaque axe (simulation par défaut)';

    /** Colonnes recopiées de l'axe vers chacun de ses pieds. */
    private const HERITE = [
        'commune_id', 'zone_id', 'format_id', 'category_id',
        'status', 'is_lit', 'is_vip', 'monthly_rate', 'daily_traffic',
        'maintenance_status', 'zone_description',
        'type_support', 'orientation', 'adresse', 'quartier', 'axe_routier',
        'odp_start_date', 'created_by',
    ];

    public function handle(AxeService $axes): int
    {
        if (! $axes->colonnesPresentes()) {
            $this->error('Les colonnes axe_id / est_axe sont absentes : joue d\'abord php artisan migrate.');

            return self::FAILURE;
        }

        $applique = (bool) $this->option('appliquer');
        $filtre   = $this->option('axe');

        $candidats = $axes->candidats();
        if ($filtre) {
            $candidats = $candidats->where('reference', $filtre)->values();
        }

        if ($candidats->isEmpty()) {
            $this->info($filtre
                ? "Aucun axe à découper pour « {$filtre} » (déjà fait, ou nom sans « (NP x NF) »)."
                : 'Aucun axe à découper : tout est déjà fait.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->line($applique
            ? '  ⚠ MODE RÉEL — les pieds vont être créés.'
            : '  MODE SIMULATION — rien ne sera écrit. Ajoute --appliquer pour exécuter.');
        $this->line('');

        $lignes = [];
        $totalPieds = 0;
        $piedsCrees = [];

        foreach ($candidats as $axe) {
            $structure = $axes->lireStructure($axe->name);
            if ($structure === null) {
                continue;   // candidats() l'a déjà filtré, ceinture et bretelles
            }

            [$pieds, $faces] = [$structure['pieds'], $structure['faces']];
            $totalPieds += $pieds;

            $lignes[] = [
                $axe->reference,
                mb_substr((string) $axe->name, 0, 46),
                $pieds,
                $faces,
                number_format((float) $axe->monthly_rate, 0, ',', ' '),
                $axes->referencePied($axe->reference, 1) . ' … ' . $axes->referencePied($axe->reference, $pieds),
            ];

            if (! $applique) {
                continue;
            }

            $piedsCrees = array_merge($piedsCrees, $this->decouper($axe, $pieds, $faces, $axes));
        }

        $this->table(
            ['axe', 'nom', 'pieds', 'faces/pied', 'tarif', 'références créées'],
            $lignes
        );

        $this->line('');
        $this->line('  Axes traités : ' . count($lignes) . '   ·   pieds : ' . $totalPieds);

        if (! $applique) {
            $this->line('');
            $this->warn('  Simulation terminée — aucune écriture. Relance avec --appliquer.');

            return self::SUCCESS;
        }

        // Le statut des pieds se recalcule : la ligne d'axe peut porter un
        // contrat en cours, et le service sait désormais le reporter.
        if (! empty($piedsCrees)) {
            app(\App\Services\AvailabilityService::class)->syncPanelStatuses($piedsCrees);
        }

        $this->line('');
        $this->info('  ✅ ' . count($piedsCrees) . ' pied(s) créé(s).');
        $this->line('');
        $this->warn('  À faire ensuite :');
        $this->line('   · les pieds n\'ont PAS de position GPS (volontaire) : elle se remplira');
        $this->line('     depuis les piges, ou à saisir à la main sur les pieds importants ;');
        $this->line('   · vérifier que les axes n\'apparaissent plus au catalogue ni en');
        $this->line('     inventaire, et que leurs pieds s\'y affichent à leur place ;');
        $this->line('   · les contrats signés avant le découpage restent sur la ligne de');
        $this->line('     l\'axe : ses pieds sont donc occupés tant qu\'ils courent.');
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * Crée les pieds d'un axe et marque la ligne parente.
     *
     * @return int[] ids des pieds créés
     */
    private function decouper(Panel $axe, int $pieds, int $faces, AxeService $axes): array
    {
        $crees = [];

        DB::transaction(function () use ($axe, $pieds, $faces, $axes, &$crees) {
            // Verrou pessimiste sur l'axe : deux exécutions simultanées ne
            // doivent pas créer deux jeux de pieds.
            $axe = Panel::whereKey($axe->id)->lockForUpdate()->firstOrFail();

            if ($axe->est_axe || $axe->pieds()->exists()) {
                $this->line("  — {$axe->reference} : déjà découpé, ignoré.");

                return;
            }

            $herite = [];
            foreach (self::HERITE as $colonne) {
                $herite[$colonne] = $axe->{$colonne};
            }

            for ($numero = 1; $numero <= $pieds; $numero++) {
                $reference = $axes->referencePied($axe->reference, $numero);

                // Rejouable : un pied déjà présent n'est pas recréé.
                if (Panel::withTrashed()->where('reference', $reference)->exists()) {
                    continue;
                }

                $pied = Panel::create($herite + [
                    'reference'      => $reference,
                    'name'           => $axes->nomPied($axe->name, $numero, $pieds),
                    'axe_id'         => $axe->id,
                    'pied_numero'    => $numero,
                    'faces_par_pied' => $faces,
                    // Position laissée vide : cf. docblock de la commande.
                    'latitude'       => null,
                    'longitude'      => null,
                ]);

                $crees[] = $pied->id;
            }

            // La ligne d'axe garde tout son historique mais cesse d'être
            // proposée à la vente.
            $axe->forceFill([
                'est_axe'        => true,
                'faces_par_pied' => $faces,
            ])->save();

            Log::info('axes.decoupe', [
                'axe_id'    => $axe->id,
                'reference' => $axe->reference,
                'pieds'     => count($crees),
                'par'       => auth()->id(),
            ]);

            $this->line("  ✅ {$axe->reference} → " . count($crees) . ' pied(s).');
        });

        return $crees;
    }
}
