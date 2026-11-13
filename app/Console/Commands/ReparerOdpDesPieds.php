<?php

namespace App\Console\Commands;

use App\Models\Panel;
use App\Services\Axes\AxeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Rattrape l'ancienneté ODP des pieds d'axe — correctif du 2026-11-13.
 *
 * ═══ LE DÉFAUT ═══
 * Panel::booted() remplit `odp_start_date` à la date du jour pour tout
 * panneau créé. Les 101 pieds nés du découpage des 11 et 13/11/2026 ont
 * donc une date de départ ODP en novembre 2026 — alors que les mâts sont
 * plantés depuis des années : seule leur ligne dans Panora est récente.
 *
 * Conséquence constatée à l'écran : sur un exercice ANNUEL, l'ODP d'un
 * pied Lumipub se calculait sur 2 mois (novembre + décembre) au lieu de
 * 12. Le docblock de Panel::booted() avertit précisément de ce cas pour
 * les imports de panneaux historiques.
 *
 * ═══ LA RÈGLE ═══
 * Un pied porte la MÊME ancienneté que son axe — y compris NULL, qui
 * signifie « panneau préexistant, ODP due sur toute la période »
 * (TX-10, validé par la patronne).
 *
 * Simulation par défaut, comme axes:decouper. Rien n'est écrit sans
 * --appliquer.
 */
class ReparerOdpDesPieds extends Command
{
    protected $signature = 'axes:reparer-odp
                            {--appliquer : Écrire les corrections (sinon simulation)}
                            {--axe= : Limiter à une référence d\'axe}';

    protected $description = "Aligne la date de départ ODP des pieds sur celle de leur axe (simulation par défaut)";

    public function handle(AxeService $axes): int
    {
        if (! $axes->colonnesPresentes()) {
            $this->error('Colonnes axe_id / est_axe absentes : joue d\'abord php artisan migrate.');
            return self::FAILURE;
        }

        $applique = (bool) $this->option('appliquer');

        $requete = Panel::query()->whereNotNull('axe_id')->whereNull('deleted_at');
        if ($ref = $this->option('axe')) {
            $axe = Panel::where('reference', $ref)->where('est_axe', true)->first();
            if (! $axe) {
                $this->error("Axe {$ref} introuvable.");
                return self::FAILURE;
            }
            $requete->where('axe_id', $axe->id);
        }

        $pieds = $requete->orderBy('reference')->get(
            ['id', 'reference', 'axe_id', 'odp_start_date', 'faces_par_pied', 'nombre_faces']
        );

        if ($pieds->isEmpty()) {
            $this->info('Aucun pied à examiner.');
            return self::SUCCESS;
        }

        // `nombre_faces` DOIT figurer dans le select : sans elle, la
        // comparaison plus bas lit null → 0, et les 13 lignes d'axe
        // ressortaient « divergentes » à chaque passage, même une fois
        // corrigées. Faux positif constaté le 2026-11-13.
        $parAxe = Panel::whereIn('id', $pieds->pluck('axe_id')->unique()->filter())
            ->get(['id', 'reference', 'name', 'odp_start_date', 'faces_par_pied', 'nombre_faces'])
            ->keyBy('id');

        $lignes       = [];
        $aCorriger    = [];
        $sansFaces    = 0;
        $facesADealigner = [];

        foreach ($pieds as $pied) {
            $axe = $parAxe->get($pied->axe_id);
            if (! $axe) continue;

            if (empty($pied->faces_par_pied)) $sansFaces++;

            // Les deux colonnes de faces doivent dire la même chose :
            // `nombre_faces` est celle qu'on voit et qu'on modifie depuis
            // l'inventaire, `faces_par_pied` celle que lit le calcul des
            // taxes. Tant qu'elles divergent, l'écran ment — et enregistrer
            // la fiche écraserait la bonne valeur par la mauvaise.
            $facesUtiles = (int) ($pied->faces_par_pied ?: 0);
            if ($facesUtiles > 0 && (int) $pied->nombre_faces !== $facesUtiles) {
                $facesADealigner[] = $pied;
            }

            $actuelle = $pied->odp_start_date?->toDateString();
            $attendue = $axe->odp_start_date?->toDateString();

            if ($actuelle === $attendue) continue;

            $aCorriger[] = $pied;
            $lignes[] = [
                $axe->reference,
                $pied->reference,
                $actuelle ?? 'NULL (préexistant)',
                $attendue ?? 'NULL (préexistant)',
            ];
        }

        // Les lignes d'axe aussi : elles s'éditent depuis l'inventaire.
        foreach ($parAxe as $axe) {
            $facesUtiles = (int) ($axe->faces_par_pied ?: 0);
            if ($facesUtiles > 0 && (int) $axe->nombre_faces !== $facesUtiles) {
                $facesADealigner[] = $axe;
            }
        }

        // ═══ Lumipub autonomes (2026-11-13) ═══
        // MRYLUP-001 « Marcory Résidentiel (1 Lumipub) » : un seul support
        // à 3 faces de 3 m². Jamais découpé, donc pas de faces_par_pied —
        // et le calcul le comptait à une face. On reprend sa saisie
        // `nombre_faces`, qui est la bonne.
        // Les chevalets restent dehors : leur règle n'est pas tranchée.
        $lumipubs = Panel::query()
            ->whereNull('deleted_at')
            ->whereNull('axe_id')
            ->where('est_axe', false)
            ->whereNull('faces_par_pied')
            ->where('nombre_faces', '>', 1)
            ->whereHas('category', fn ($q) => $q->where('name', 'like', '%umipub%'))
            ->get(['id', 'reference', 'nombre_faces', 'faces_par_pied']);

        if ($lumipubs->isNotEmpty()) {
            $this->newLine();
            $this->warn($lumipubs->count() . ' Lumipub autonome(s) dont les faces ne comptent pas encore :');
            $this->table(
                ['RÉFÉRENCE', 'FACES SAISIES', 'UTILISÉ AUJOURD\'HUI'],
                $lumipubs->map(fn ($p) => [$p->reference, (int) $p->nombre_faces, 1])->all()
            );
        }

        if ($facesADealigner) {
            $this->newLine();
            $this->warn(count($facesADealigner) . ' panneau(x) dont « nombre de faces » affiché '
                . "diverge de la valeur utilisée pour la taxe :");
            $this->table(
                ['RÉFÉRENCE', 'AFFICHÉ (nombre_faces)', 'UTILISÉ POUR LA TAXE'],
                collect($facesADealigner)->take(20)->map(fn ($p) => [
                    $p->reference,
                    (int) ($p->nombre_faces ?: 1),
                    (int) $p->faces_par_pied,
                ])->all()
            );
            if (count($facesADealigner) > 20) {
                $this->line('   … et ' . (count($facesADealigner) - 20) . ' autre(s).');
            }
        }

        if (empty($aCorriger) && empty($facesADealigner) && $lumipubs->isEmpty()) {
            $this->info("✅ Les {$pieds->count()} pied(s) portent déjà l'ancienneté de leur axe,");
            $this->info('   et toutes les faces concordent.');
            if ($sansFaces) {
                $this->warn("⚠ {$sansFaces} pied(s) sans faces_par_pied : le calcul retombe sur le nom de l'axe.");
            }
            return self::SUCCESS;
        }

        if (empty($aCorriger)) {
            $this->newLine();
            $this->info('Les dates ODP sont déjà bonnes — seules les faces sont à traiter.');
            if (! $applique) {
                $this->line('Relance avec --appliquer pour écrire les corrections.');
                return self::SUCCESS;
            }
            if ($facesADealigner) $this->aligner($facesADealigner);
            $this->reprendreLumipubs($lumipubs);
            return self::SUCCESS;
        }

        $this->table(['AXE', 'PIED', 'ACTUELLE', 'ATTENDUE'], $lignes);
        $this->newLine();
        $this->warn(count($aCorriger) . ' pied(s) à corriger sur ' . $pieds->count() . '.');

        if ($sansFaces) {
            $this->warn("⚠ {$sansFaces} pied(s) sans faces_par_pied : le calcul des faces "
                . "retombe sur le nom de l'axe. Rejoue axes:decouper si besoin.");
        }

        if (! $applique) {
            $this->newLine();
            $this->info('SIMULATION — rien n\'a été modifié.');
            $this->line('Relance avec --appliquer pour écrire les corrections.');
            return self::SUCCESS;
        }

        foreach ($aCorriger as $pied) {
            $axe = $parAxe->get($pied->axe_id);
            $pied->forceFill(['odp_start_date' => $axe->odp_start_date])->save();
        }

        Log::info('axes.reparer_odp', [
            'pieds_corriges' => count($aCorriger),
            'par'            => auth()->id(),
        ]);

        $this->newLine();
        $this->info('✅ ' . count($aCorriger) . ' pied(s) corrigé(s) sur l\'ancienneté ODP.');

        if ($facesADealigner) {
            $this->aligner($facesADealigner);
        }
        $this->reprendreLumipubs($lumipubs);

        $this->line("Recalcule l'ODP annuelle : les pieds doivent maintenant compter 12 mois.");

        return self::SUCCESS;
    }

    /**
     * Reprend la saisie `nombre_faces` d'un Lumipub autonome dans
     * `faces_par_pied`, le champ que lit le calcul des taxes.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Panel> $lumipubs
     */
    private function reprendreLumipubs($lumipubs): void
    {
        if ($lumipubs->isEmpty()) return;

        foreach ($lumipubs as $p) {
            $p->forceFill(['faces_par_pied' => max(1, (int) $p->nombre_faces)])->save();
        }

        Log::info('axes.reprendre_lumipubs', [
            'panneaux' => $lumipubs->pluck('reference')->all(),
            'par'      => auth()->id(),
        ]);

        $this->info('✅ ' . $lumipubs->count() . ' Lumipub autonome(s) : leurs faces comptent désormais pour la taxe.');
    }

    /**
     * Recopie faces_par_pied (la valeur qui sert au calcul) dans
     * nombre_faces (celle qu'on voit et qu'on modifie à l'inventaire),
     * pour que les deux cessent de diverger.
     *
     * @param  \App\Models\Panel[] $panneaux
     */
    private function aligner(array $panneaux): void
    {
        foreach ($panneaux as $p) {
            $p->forceFill(['nombre_faces' => (int) $p->faces_par_pied])->save();
        }

        Log::info('axes.reparer_faces', [
            'panneaux_alignes' => count($panneaux),
            'par'              => auth()->id(),
        ]);

        $this->info('✅ ' . count($panneaux) . ' panneau(x) : « nombre de faces » aligné sur la valeur de calcul.');
    }
}
