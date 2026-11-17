<?php

namespace App\Console\Commands;

use App\Models\Panel;
use App\Models\PanelCategory;
use App\Models\PanelFormat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Classe les panneaux d'une surface donnée dans une catégorie
 * (2026-11-17) — demande : « tous les 6 m² en Petit format ».
 *
 * ═══ POURQUOI UNE COMMANDE ET PAS UN UPDATE ═══
 * Trois pièges, tous vérifiés dans le code avant d'écrire ceci.
 *
 * 1. LA SURFACE EN BASE N'EST PAS FIABLE.
 *    PanelFormat::getSurfaceM2Attribute() le dit noir sur blanc : « vu en
 *    prod : 4x3m avec surface=32 ». On se fie donc à largeur × hauteur,
 *    et la commande signale les formats dont la colonne `surface`
 *    contredit les dimensions.
 *
 * 2. LA CATÉGORIE PILOTE LE CALCUL DES TAXES DES LUMIPUB.
 *    PanelController::update() ne synchronise `faces_par_pied` — qui
 *    alimente le calcul ODP/TM — que si le panneau est un axe, un pied,
 *    ou si le NOM de sa catégorie contient « lumipub ». Sortir un
 *    Lumipub de sa catégorie coupe ce lien : on modifierait son nombre
 *    de faces à l'écran sans que la taxe bouge. C'est exactement le
 *    défaut corrigé le 2026-11-13 sur MRYLUP-001.
 *    → Ces panneaux sont ÉCARTÉS par défaut et listés à part.
 *
 * 3. LA CATÉGORIE SERT À GÉNÉRER LES RÉFÉRENCES.
 *    PanelReferenceGenerator lit `category->code`. Les références déjà
 *    attribuées ne bougent pas, mais une catégorie sans code fera
 *    retomber les FUTURS panneaux sur un repli. La commande le signale.
 *
 * Simulation par défaut. Rien n'est écrit sans --appliquer.
 */
class ClasserPanneauxParSurface extends Command
{
    protected $signature = 'panneaux:classer-surface
                            {--surface=6 : Surface en m² (largeur × hauteur)}
                            {--categorie=Petit format : Nom exact de la catégorie cible}
                            {--tolerance=0.05 : Écart toléré sur la surface, en m²}
                            {--forcer : Reclasser aussi les catégories sensibles — Lumipub, Chevalet, Murale (déconseillé)}
                            {--appliquer : Écrire les changements (sinon simulation)}';

    protected $description = "Classe les panneaux d'une surface donnée dans une catégorie (simulation par défaut)";

    public function handle(): int
    {
        $surface   = (float) $this->option('surface');
        $nomCat    = trim((string) $this->option('categorie'));
        $tolerance = max(0.0, (float) $this->option('tolerance'));
        $avecRisque = (bool) $this->option('forcer');
        $appliquer  = (bool) $this->option('appliquer');

        if ($surface <= 0) {
            $this->error('La surface doit être supérieure à zéro.');

            return self::FAILURE;
        }

        foreach (['panel_categories', 'panel_formats', 'panels'] as $table) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                $this->error("Table {$table} absente : joue d'abord php artisan migrate.");

                return self::FAILURE;
            }
        }

        // ── 1. La catégorie cible ────────────────────────────────────
        $categorie = PanelCategory::whereRaw('LOWER(name) = ?', [Str::lower($nomCat)])->first();

        if (!$categorie) {
            $this->error("Catégorie « {$nomCat} » introuvable.");
            $this->line('');
            $this->line('Catégories existantes :');
            foreach (PanelCategory::orderBy('name')->get(['id', 'name', 'code']) as $c) {
                $this->line(sprintf('  #%-3d %-22s code: %s', $c->id, $c->name, $c->code ?: '— (aucun)'));
            }
            $this->line('');
            $this->line('Créez-la dans /admin/settings, puis relancez.');

            return self::FAILURE;
        }

        if (!$categorie->code) {
            $this->warn("⚠ La catégorie « {$categorie->name} » n'a pas de code.");
            $this->warn('  Les références des FUTURS panneaux de cette catégorie retomberont');
            $this->warn('  sur un repli (cf. PanelReferenceGenerator). Les références déjà');
            $this->warn('  attribuées ne changent pas.');
            $this->line('');
        }

        // ── 2. Les formats de cette surface ──────────────────────────
        // largeur × hauteur fait foi ; la colonne `surface` a divergé.
        $formats = PanelFormat::whereNotNull('width')->whereNotNull('height')->get();

        $formatsCibles = $formats->filter(
            fn ($f) => abs(((float) $f->width * (float) $f->height) - $surface) <= $tolerance
        );

        if ($formatsCibles->isEmpty()) {
            $this->error("Aucun format ne fait {$surface} m² (largeur × hauteur).");
            $this->line('');
            $this->line('Formats existants :');
            foreach ($formats->sortBy(fn ($f) => (float) $f->width * (float) $f->height) as $f) {
                $this->line(sprintf(
                    '  %-18s %sx%s = %.2f m²   (colonne surface : %s)',
                    $f->name,
                    rtrim(rtrim(number_format((float) $f->width, 2, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format((float) $f->height, 2, '.', ''), '0'), '.'),
                    (float) $f->width * (float) $f->height,
                    $f->surface ?? '—'
                ));
            }

            return self::FAILURE;
        }

        $this->line('');
        $this->info("Formats retenus pour {$surface} m² :");
        foreach ($formatsCibles as $f) {
            $calc  = (float) $f->width * (float) $f->height;
            $ecart = $f->surface !== null && abs((float) $f->surface - $calc) > 0.01
                ? sprintf('  ⚠ colonne surface = %s, incohérente', $f->surface)
                : '';
            $this->line(sprintf('  %-18s %.2f m²%s', $f->name, $calc, $ecart));
        }

        // ── 3. Les panneaux concernés ────────────────────────────────
        $panneaux = Panel::with(['category:id,name', 'commune:id,name'])
            ->whereIn('format_id', $formatsCibles->pluck('id'))
            ->orderBy('reference')
            ->get(['id', 'reference', 'name', 'category_id', 'commune_id', 'format_id', 'est_axe', 'axe_id']);

        if ($panneaux->isEmpty()) {
            $this->line('');
            $this->info('Aucun panneau à ce format. Rien à faire.');

            return self::SUCCESS;
        }

        // ── 4. Tri : à changer / déjà classés / écartés ──────────────
        $aChanger = [];
        $dejaOk   = [];
        $ecartes  = [];

        foreach ($panneaux as $p) {
            if ((int) $p->category_id === (int) $categorie->id) {
                $dejaOk[] = $p;
                continue;
            }

            $motifRisque = $this->motifEcart($p);
            if ($motifRisque !== null && !$avecRisque) {
                $ecartes[] = [$p, $motifRisque];
                continue;
            }

            $aChanger[] = $p;
        }

        $this->line('');
        $this->line($appliquer ? '→ APPLICATION des changements.' : '→ SIMULATION (ajoute --appliquer pour écrire).');
        $this->line('');

        // ── 5. Écartés ───────────────────────────────────────────────
        if ($ecartes) {
            $this->warn('ÉCARTÉS — leur catégorie pilote un calcul :');
            foreach ($ecartes as [$p, $motif]) {
                $this->line(sprintf(
                    '  %-14s %-18s %s',
                    $p->reference,
                    $p->category?->name ?? '—',
                    $motif
                ));
            }
            $this->line('  → --forcer pour les traiter quand même.');
            $this->line('');
        }

        // ── 6. À changer ─────────────────────────────────────────────
        if (!$aChanger) {
            $this->info(count($dejaOk) . ' panneau(x) déjà dans « ' . $categorie->name . ' ».');
            if ($ecartes) {
                $this->warn(count($ecartes) . ' écarté(s), voir ci-dessus.');
            }

            return self::SUCCESS;
        }

        $this->info(count($aChanger) . ' panneau(x) à reclasser vers « ' . $categorie->name . ' » :');
        foreach ($aChanger as $p) {
            $this->line(sprintf(
                '  %-14s %-16s %-20s → %s',
                $p->reference,
                $p->commune?->name ?? '—',
                $p->category?->name ?? '(sans catégorie)',
                $categorie->name
            ));
        }

        // ── 7. Écriture ──────────────────────────────────────────────
        if ($appliquer) {
            $ids = array_map(fn ($p) => $p->id, $aChanger);

            // update() de masse : pas d'observer à déclencher ici, on ne
            // touche qu'au classement. Les références déjà attribuées ne
            // bougent pas.
            Panel::whereIn('id', $ids)->update(['category_id' => $categorie->id]);

            Log::warning('panneaux.reclassement_par_surface', [
                'surface'      => $surface,
                'categorie'    => $categorie->name,
                'categorie_id' => $categorie->id,
                'panneaux'     => count($ids),
                'references'   => array_map(fn ($p) => $p->reference, $aChanger),
                'ecartes'      => count($ecartes),
                'par'          => 'console',
            ]);
        }

        $this->line('');
        $this->info(count($aChanger) . ' reclassé(s)' . ($appliquer ? ' — écrit.' : ' (simulation).'));
        if ($dejaOk)  $this->line(count($dejaOk) . ' déjà dans la catégorie.');
        if ($ecartes) $this->warn(count($ecartes) . ' écarté(s).');
        if (!$appliquer) {
            $this->line('');
            $this->line('Relance avec --appliquer pour écrire.');
        }
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * Catégories dont le NOM pilote une règle ailleurs dans Panora.
     * Les sortir de leur catégorie change silencieusement un calcul ou
     * un rapport — c'est pour ça qu'on les écarte par défaut.
     *
     * nom (en minuscules) => ce qui casserait
     */
    private const CATEGORIES_SENSIBLES = [
        'lumipub' => "le nom déclenche la synchro des faces → calcul ODP/TM (PanelController)",
        'chevalet'=> "exclu de la classification par occupation (règle validée 09/2026)",
        'murale'  => "exclue de la classification par occupation (règle validée 09/2026)",
    ];

    /**
     * Pourquoi ce panneau ne doit pas changer de catégorie à la légère.
     * null = aucun risque connu.
     *
     * ⚠ CORRECTION DU 2026-11-17 — les axes et leurs pieds ne sont PLUS
     * écartés, et l'ancien motif était faux.
     *
     * Il annonçait « sa catégorie et ses faces pilotent le calcul
     * ODP/TM ». Vérification faite : la synchro de `faces_par_pied` se
     * déclenche sur `est_axe` / `axe_id` — jamais sur la catégorie
     * (PanelController). Et le calcul des taxes lit `est_axe`, `axe_id`
     * et `faces_par_pied`, pas la catégorie. Reclasser un axe ou un pied
     * ne touche donc aucun montant.
     *
     * La garde écartait 13 panneaux sans raison réelle — dont BK-002 et
     * ses 12 pieds, des bornes kilométriques de 6 m² qui ont toute leur
     * place en « Petit format ».
     *
     * Seul le NOM de la catégorie reste un vrai danger : c'est lui, et
     * lui seul, qui pilote une règle ailleurs.
     */
    private function motifEcart(Panel $panneau): ?string
    {
        $nom = Str::lower((string) $panneau->category?->name);
        foreach (self::CATEGORIES_SENSIBLES as $cle => $consequence) {
            if (Str::contains($nom, $cle)) {
                return ucfirst($cle) . ' — ' . $consequence;
            }
        }

        return null;
    }
}
