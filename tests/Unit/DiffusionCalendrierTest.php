<?php

namespace Tests\Unit;

use App\Services\Diffusion\DiffusionCalendrier;
use App\Services\Diffusion\JoursFeriesService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Calendrier de diffusion des disponibilités (2026-09-29).
 *
 * Règle validée : le 1er et le 15 à 10h ; samedi, dimanche ou férié →
 * premier jour ouvrable suivant. La période suit le créneau, pas le jour
 * d'envoi. Les cas testés sont ceux du tableau validé avec la patronne.
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

    // ── Le tableau validé ──────────────────────────────────────────

    public static function tableauValide(): array
    {
        return [
            // créneau        envoi réel     pourquoi
            '1er oct. jeudi'            => ['2026-10-01', '2026-10-01'],
            '15 oct. jeudi'             => ['2026-10-15', '2026-10-15'],
            '1er nov. dimanche Toussaint' => ['2026-11-01', '2026-11-02'],
            '15 nov. dimanche Paix'     => ['2026-11-15', '2026-11-16'],
            '1er déc. mardi'            => ['2026-12-01', '2026-12-01'],
            '1er janv. 2027 férié'      => ['2027-01-01', '2027-01-04'],
        ];
    }

    #[DataProvider('tableauValide')]
    public function test_date_d_envoi_du_tableau_valide(string $creneau, string $envoiAttendu): void
    {
        $this->assertSame(
            $envoiAttendu,
            $this->calendrier()->dateEnvoi(Carbon::parse($creneau))->toDateString()
        );
    }

    // ── Le planificateur, jour par jour ────────────────────────────

    public function test_le_planificateur_reconnait_le_jour_d_envoi_decale(): void
    {
        $cal = $this->calendrier();

        // Lundi 2 novembre : c'est le jour d'envoi du créneau du 1er.
        $this->assertSame('2026-11-01', $cal->creneauDuJour(Carbon::parse('2026-11-02'))?->toDateString());

        // Lundi 16 novembre : créneau du 15.
        $this->assertSame('2026-11-15', $cal->creneauDuJour(Carbon::parse('2026-11-16'))?->toDateString());

        // Lundi 4 janvier 2027 : créneau du 1er janvier.
        $this->assertSame('2027-01-01', $cal->creneauDuJour(Carbon::parse('2027-01-04'))?->toDateString());
    }

    public function test_aucun_envoi_les_autres_jours(): void
    {
        $cal = $this->calendrier();

        foreach (['2026-11-01', '2026-11-03', '2026-11-15', '2026-11-17', '2026-10-14', '2027-01-01'] as $jour) {
            $this->assertNull(
                $cal->creneauDuJour(Carbon::parse($jour)),
                "Aucun envoi ne doit partir le {$jour}."
            );
        }
    }

    public function test_un_jour_ferie_saisi_par_l_admin_decale_l_envoi(): void
    {
        // Une fête musulmane qui tomberait un 15 (date fictive) : l'envoi
        // doit passer au lendemain ouvrable.
        $cal = new DiffusionCalendrier(['2026-10-15']);

        $this->assertSame('2026-10-16', $cal->dateEnvoi(Carbon::parse('2026-10-15'))->toDateString());
    }

    // ── Période couverte ───────────────────────────────────────────

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
        // Envoi le lundi 16 novembre, mais la période reste du 15 au 30.
        $cal = $this->calendrier();
        $creneau = $cal->creneauDuJour(Carbon::parse('2026-11-16'));
        [$debut] = $cal->periode($creneau);

        $this->assertSame('2026-11-15', $debut->toDateString());
    }

    public function test_fevrier_s_arrete_au_bon_jour(): void
    {
        [, $fin] = $this->calendrier()->periode(Carbon::parse('2027-02-15'));

        $this->assertSame('2027-02-28', $fin->toDateString());
    }

    // ── Envoi manuel ───────────────────────────────────────────────

    public function test_envoi_manuel_avant_le_15_couvre_tout_le_mois(): void
    {
        $this->assertSame(
            '2026-11-01',
            $this->calendrier()->creneauCourant(Carbon::parse('2026-11-12'))->toDateString()
        );
    }

    public function test_envoi_manuel_a_partir_du_15_couvre_la_seconde_quinzaine(): void
    {
        $cal = $this->calendrier();

        $this->assertSame('2026-11-15', $cal->creneauCourant(Carbon::parse('2026-11-15'))->toDateString());
        $this->assertSame('2026-11-15', $cal->creneauCourant(Carbon::parse('2026-11-28'))->toDateString());
    }

    // ── Prochain envoi (affiché dans l'écran admin) ────────────────

    public function test_prochain_envoi(): void
    {
        $cal = $this->calendrier();

        $p = $cal->prochainEnvoi(Carbon::parse('2026-10-20'));
        $this->assertSame('2026-11-01', $p['creneau']->toDateString());
        $this->assertSame('2026-11-02', $p['date_envoi']->toDateString());

        // Le jour même d'un envoi, il est encore « à venir ».
        $p = $cal->prochainEnvoi(Carbon::parse('2026-10-15'));
        $this->assertSame('2026-10-15', $p['date_envoi']->toDateString());
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
