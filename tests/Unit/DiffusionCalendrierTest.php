<?php

namespace Tests\Unit;

use App\Services\Diffusion\DiffusionCalendrier;
use App\Services\Diffusion\JoursFeriesService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Calendrier de diffusion des disponibilités (2026-10-30).
 *
 * Règle validée : les catalogues partent AVANT la période qu'ils
 * annoncent, pour que le client ait ses disponibilités à temps pour
 * lancer sa campagne du mois.
 *
 *   · catalogue du 1er du mois M → envoyé entre le 20 et le 25 de M-1 ;
 *   · catalogue du 15 du mois M → envoyé entre le 1er et le 5 de M ;
 *   · jour exact : le PREMIER JOUR OUVRABLE de la fenêtre.
 *
 * La période couverte ne change pas : le catalogue du 1er couvre tout le
 * mois, celui du 15 va du 15 à la fin du mois.
 */
class DiffusionCalendrierTest extends TestCase
{
    private function calendrier(): DiffusionCalendrier
    {
        // Fériés calculables 2026-2027, sans base de données.
        $service = new JoursFeriesService();
        $feries = array_merge(
            array_keys($service->feriesCalculables(2026)),
            array_keys($service->feriesCalculables(2027)),
        );

        return new DiffusionCalendrier($feries);
    }

    // ── Les dates d'envoi ──────────────────────────────────────────

    public static function datesDEnvoi(): array
    {
        return [
            // catalogue        envoi réel      pourquoi
            'nov. : 20 oct. mardi'        => ['2026-11-01', '2026-10-20'],
            '15 nov. : 1er nov. férié'    => ['2026-11-15', '2026-11-02'],
            'déc. : 20 nov. vendredi'     => ['2026-12-01', '2026-11-20'],
            '15 déc. : 1er déc. mardi'    => ['2026-12-15', '2026-12-01'],
            'janv. 2027 : 20 déc. dim.'   => ['2027-01-01', '2026-12-21'],
            '15 janv. : 1er janv. férié'  => ['2027-01-15', '2027-01-04'],
            'fév. 2027 : 20 janv. merc.'  => ['2027-02-01', '2027-01-20'],
        ];
    }

    #[DataProvider('datesDEnvoi')]
    public function test_le_catalogue_part_avant_sa_periode(string $creneau, string $envoiAttendu): void
    {
        $this->assertSame(
            $envoiAttendu,
            $this->calendrier()->dateEnvoi(Carbon::parse($creneau))->toDateString()
        );
    }

    #[DataProvider('datesDEnvoi')]
    public function test_l_envoi_tombe_toujours_dans_sa_fenetre(string $creneau, string $envoiAttendu): void
    {
        $cal = $this->calendrier();
        [$debut, $fin] = $cal->fenetreEnvoi(Carbon::parse($creneau));
        $envoi = $cal->dateEnvoi(Carbon::parse($creneau));

        $this->assertTrue(
            $envoi->betweenIncluded($debut, $fin),
            "Envoi du {$envoi->toDateString()} hors de la fenêtre "
            . "{$debut->toDateString()} → {$fin->toDateString()}."
        );
    }

    #[DataProvider('datesDEnvoi')]
    public function test_l_envoi_precede_toujours_la_periode(string $creneau, string $envoiAttendu): void
    {
        // C'est la raison d'être du changement : le client doit avoir ses
        // disponibilités avant le début de la période qu'elles couvrent.
        $cal = $this->calendrier();
        [$debutPeriode] = $cal->periode(Carbon::parse($creneau));

        $this->assertTrue(
            $cal->dateEnvoi(Carbon::parse($creneau))->lessThan($debutPeriode),
            "Le catalogue du {$creneau} doit partir avant le {$debutPeriode->toDateString()}."
        );
    }

    public function test_les_fenetres_sont_celles_validees(): void
    {
        $cal = $this->calendrier();

        [$d, $f] = $cal->fenetreEnvoi(Carbon::parse('2026-12-01'));
        $this->assertSame(['2026-11-20', '2026-11-25'], [$d->toDateString(), $f->toDateString()]);

        [$d, $f] = $cal->fenetreEnvoi(Carbon::parse('2026-12-15'));
        $this->assertSame(['2026-12-01', '2026-12-05'], [$d->toDateString(), $f->toDateString()]);
    }

    public function test_un_jour_ferie_saisi_par_l_admin_decale_l_envoi(): void
    {
        // Une fête musulmane qui tomberait le 20 (date fictive) : l'envoi
        // passe au lendemain, toujours dans la fenêtre.
        $cal = new DiffusionCalendrier(['2026-11-20']);

        $this->assertSame('2026-11-23', $cal->dateEnvoi(Carbon::parse('2026-12-01'))->toDateString());
    }

    public function test_une_fenetre_entierement_chomee_envoie_quand_meme(): void
    {
        // Calendrier absurde : mieux vaut partir le dernier jour de la
        // fenêtre que de ne rien envoyer du tout.
        $cal = new DiffusionCalendrier([
            '2026-11-20', '2026-11-21', '2026-11-22',
            '2026-11-23', '2026-11-24', '2026-11-25',
        ]);

        $this->assertSame('2026-11-25', $cal->dateEnvoi(Carbon::parse('2026-12-01'))->toDateString());
    }

    // ── Le planificateur, jour par jour ────────────────────────────

    public function test_le_planificateur_reconnait_le_jour_d_envoi(): void
    {
        $cal = $this->calendrier();

        // 20 octobre : jour d'envoi du catalogue de novembre.
        $this->assertSame('2026-11-01', $cal->creneauDuJour(Carbon::parse('2026-10-20'))?->toDateString());

        // 2 novembre : jour d'envoi du catalogue du 15 novembre.
        $this->assertSame('2026-11-15', $cal->creneauDuJour(Carbon::parse('2026-11-02'))?->toDateString());

        // 21 décembre : catalogue de janvier (20 déc. étant un dimanche).
        $this->assertSame('2027-01-01', $cal->creneauDuJour(Carbon::parse('2026-12-21'))?->toDateString());
    }

    public function test_aucun_envoi_les_autres_jours(): void
    {
        $cal = $this->calendrier();

        foreach ([
            '2026-11-01',  // début de période — ce n'est plus un jour d'envoi
            '2026-11-10',  // entre les deux fenêtres
            '2026-11-15',  // début de période
            '2026-11-26',  // fenêtre refermée
            '2026-12-22',  // le catalogue de janvier est parti la veille
        ] as $jour) {
            $this->assertNull(
                $cal->creneauDuJour(Carbon::parse($jour)),
                "Aucun envoi ne doit partir le {$jour}."
            );
        }
    }

    // ── Période couverte (inchangée) ───────────────────────────────

    public function test_le_creneau_du_1er_couvre_tout_le_mois(): void
    {
        [$debut, $fin] = $this->calendrier()->periode(Carbon::parse('2026-11-01'));

        $this->assertSame('2026-11-01', $debut->toDateString());
        $this->assertSame('2026-11-30', $fin->toDateString());
    }

    public function test_le_creneau_du_15_couvre_du_15_a_la_fin_du_mois(): void
    {
        [$debut, $fin] = $this->calendrier()->periode(Carbon::parse('2026-11-15'));

        $this->assertSame('2026-11-15', $debut->toDateString());
        $this->assertSame('2026-11-30', $fin->toDateString());
    }

    public function test_la_periode_suit_le_creneau_pas_le_jour_d_envoi(): void
    {
        // Envoi le 20 octobre, mais la période reste celle de novembre.
        $cal = $this->calendrier();
        $creneau = $cal->creneauDuJour(Carbon::parse('2026-10-20'));
        [$debut, $fin] = $cal->periode($creneau);

        $this->assertSame(['2026-11-01', '2026-11-30'], [$debut->toDateString(), $fin->toDateString()]);
    }

    public function test_fevrier_s_arrete_au_bon_jour(): void
    {
        [, $fin] = $this->calendrier()->periode(Carbon::parse('2027-02-15'));

        $this->assertSame('2027-02-28', $fin->toDateString());
    }

    // ── Envoi manuel ───────────────────────────────────────────────

    public function test_envoi_manuel_avant_le_20_prepare_la_seconde_quinzaine(): void
    {
        $cal = $this->calendrier();

        $this->assertSame('2026-11-15', $cal->creneauCourant(Carbon::parse('2026-11-03'))->toDateString());
        $this->assertSame('2026-11-15', $cal->creneauCourant(Carbon::parse('2026-11-19'))->toDateString());
    }

    public function test_envoi_manuel_a_partir_du_20_prepare_le_mois_suivant(): void
    {
        // La bascule suit l'ouverture de la fenêtre, pas le milieu du mois.
        $cal = $this->calendrier();

        $this->assertSame('2026-12-01', $cal->creneauCourant(Carbon::parse('2026-11-20'))->toDateString());
        $this->assertSame('2026-12-01', $cal->creneauCourant(Carbon::parse('2026-11-30'))->toDateString());
    }

    // ── Prochain envoi (affiché dans l'écran admin) ────────────────

    public function test_prochain_envoi(): void
    {
        $cal = $this->calendrier();

        // Le 10 octobre, le prochain départ est celui du catalogue de
        // novembre, le 20 octobre.
        $p = $cal->prochainEnvoi(Carbon::parse('2026-10-10'));
        $this->assertSame('2026-11-01', $p['creneau']->toDateString());
        $this->assertSame('2026-10-20', $p['date_envoi']->toDateString());

        // Le jour même d'un envoi, il est encore « à venir ».
        $p = $cal->prochainEnvoi(Carbon::parse('2026-10-20'));
        $this->assertSame('2026-10-20', $p['date_envoi']->toDateString());

        // Juste après, c'est le catalogue du 15 novembre qui vient.
        $p = $cal->prochainEnvoi(Carbon::parse('2026-10-21'));
        $this->assertSame('2026-11-15', $p['creneau']->toDateString());
        $this->assertSame('2026-11-02', $p['date_envoi']->toDateString());
    }

    // ── Jours fériés calculés ──────────────────────────────────────

    public function test_paques_est_calcule_juste(): void
    {
        $this->assertSame('2026-04-05', JoursFeriesService::dimancheDePaques(2026)->toDateString());
        $this->assertSame('2027-03-28', JoursFeriesService::dimancheDePaques(2027)->toDateString());
        $this->assertSame('2024-03-31', JoursFeriesService::dimancheDePaques(2024)->toDateString());
    }

    public function test_feries_chretiens_mobiles_2026(): void
    {
        $feries = (new JoursFeriesService())->feriesCalculables(2026);

        $this->assertSame('Lundi de Pâques', $feries['2026-04-06'][0]);
        $this->assertSame('Ascension', $feries['2026-05-14'][0]);
        $this->assertSame('Lundi de Pentecôte', $feries['2026-05-25'][0]);
    }

    // ── Libellés ───────────────────────────────────────────────────

    public function test_libelles(): void
    {
        $this->assertSame(
            'du 1er au 30 novembre 2026',
            DiffusionCalendrier::libellePeriode(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'))
        );
        $this->assertSame(
            'du 15 au 30 novembre 2026',
            DiffusionCalendrier::libellePeriode(Carbon::parse('2026-11-15'), Carbon::parse('2026-11-30'))
        );
    }
}
