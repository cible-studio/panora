<?php

namespace Tests\Unit;

use App\Models\DiffusionEnvoi;
use App\Services\Brevo\BrevoClient;
use App\Services\Diffusion\DiffusionDisponibilitesService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Catalogue de test reçu le 2026-11-03 : il manquait le 2e numéro
 * commercial, le logo, et Stéphane n'était pas en copie.
 *   • numéros et logo : Panora les fournit au modèle Brevo (charte, donc
 *     .env) ; le modèle ne les écrit plus en dur ;
 *   • copie : MAIL_COPIE_COMMERCIALE reçoit chaque vrai catalogue comme un
 *     client (une campagne Brevo n'a pas de copie), sans compter parmi eux.
 */
class DiffusionSignatureCatalogueTest extends TestCase
{
    use Concerns\BaseRenouvellement;

    private const CLIENTS = 3;
    private const TESTS = 4;
    private const STEPHANE = 'stephane.oponou@cible-ci.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerBaseRenouvellement();
        Cache::flush();

        config([
            'diffusion.mode'                => 'production',
            'diffusion.controle_quota'      => true,
            'brevo.lists.clients'           => self::CLIENTS,
            'brevo.lists.tests'             => self::TESTS,
            'brevo.template_id'             => 14,
            'mails.copie_commerciale'       => self::STEPHANE,
            'charte.nom'                    => 'CIBLE',
            'charte.coordonnees.telephones' => '+225 07 98 49 66 74 · +225 07 00 78 06 27 · +225 27 22 20 80 08',
            'charte.logos.clair'            => 'images/logol.png',
        ]);
        DB::table('users')->insert(['id' => 4, 'name' => 'Stéphane Oponou', 'email' => self::STEPHANE, 'role' => 'admin']);

        Schema::create('diffusion_envois', function (Blueprint $t) {
            $t->id(); $t->date('creneau'); $t->date('periode_debut'); $t->date('periode_fin'); $t->string('mode', 10);
            $t->unsignedBigInteger('declenche_par')->nullable(); $t->string('statut', 12)->default('prepare');
            $t->unsignedInteger('nb_destinataires')->default(0); $t->unsignedInteger('nb_panneaux')->default(0);
            $t->string('pdf_path')->nullable(); $t->string('pdf_token')->nullable();
            $t->unsignedInteger('nb_panneaux_abidjan')->default(0); $t->unsignedInteger('nb_panneaux_interieur')->default(0);
            $t->string('pdf_path_abidjan')->nullable(); $t->string('pdf_token_abidjan')->nullable();
            $t->string('pdf_path_interieur')->nullable(); $t->string('pdf_token_interieur')->nullable();
            $t->string('brevo_campaign_id')->nullable();
            $t->text('erreur')->nullable(); $t->timestamp('envoye_at')->nullable(); $t->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function envoi(string $mode = DiffusionEnvoi::MODE_MANUEL): DiffusionEnvoi
    {
        return DiffusionEnvoi::create([
            'creneau' => '2026-11-15', 'periode_debut' => '2026-11-15', 'periode_fin' => '2026-11-30',
            'mode' => $mode, 'statut' => DiffusionEnvoi::STATUT_PREPARE, 'nb_panneaux' => 260,
            'pdf_path' => 'diffusions/complet.pdf', 'pdf_token' => str_repeat('a', 48),
        ]);
    }

    private function prive(DiffusionDisponibilitesService $s, string $methode, ...$args)
    {
        return (new \ReflectionMethod($s, $methode))->invoke($s, ...$args);
    }

    /** contenuCampagne() est privée : c'est le contrat avec Brevo qu'on vérifie. */
    private function params(DiffusionEnvoi $envoi): array
    {
        return $this->prive(app(DiffusionDisponibilitesService::class), 'contenuCampagne', $envoi, self::CLIENTS)['params'] ?? [];
    }

    // ── Numéros et logo ─────────────────────────────────────────────

    public function test_les_trois_numeros_et_le_logo_partent_a_brevo(): void
    {
        $params = $this->params($this->envoi());

        // Insécables DANS chaque numéro, jamais autour des « · ».
        $n = "\u{00A0}";
        $this->assertSame(
            "+225{$n}07{$n}98{$n}49{$n}66{$n}74 · +225{$n}07{$n}00{$n}78{$n}06{$n}27 · +225{$n}27{$n}22{$n}20{$n}80{$n}08",
            $params['TELEPHONES']
        );
        $this->assertStringStartsWith('http', $params['LOGO']);
        $this->assertStringEndsWith('/images/logol.png', $params['LOGO']);
        // 2026-11-04 : la ligne d'activité vient de la charte, comme les autres mails.
        $this->assertSame("Régie & Studio Créatif · Côte d'Ivoire · depuis 1994", $params['ACTIVITE']);
    }

    public function test_sans_numero_ni_logo_dans_la_charte_rien_n_est_envoye(): void
    {
        config(['charte.coordonnees.telephones' => '', 'charte.logos.clair' => null, 'charte.coordonnees.activite' => '']);
        $params = $this->params($this->envoi());

        // Le modèle teste leur présence : pas de ligne vide, pas d'image cassée.
        $this->assertArrayNotHasKey('ACTIVITE', $params);
        $this->assertArrayNotHasKey('TELEPHONES', $params);
        $this->assertArrayNotHasKey('LOGO', $params);
    }

    public function test_le_modele_brevo_lit_les_numeros_et_le_logo_de_panora(): void
    {
        $modele = file_get_contents(base_path('docs/diffusion/modele-brevo-disponibilites-leger.html'));

        $this->assertStringContainsString('{{ params.TELEPHONES }}', $modele);
        $this->assertStringContainsString('{{ params.ACTIVITE }}', $modele);
        $this->assertSame(1, substr_count($modele, '<img src="{{ params.LOGO }}"'), 'Logo en haut seulement (retiré de la signature le 2026-11-04).');
        // Plus aucun numéro en dur : un changement de numéro ne passe que par le .env.
        $this->assertDoesNotMatchRegularExpression('/\d{2} \d{2} \d{2} \d{2} \d{2}/', $modele);
    }

    public function test_le_mail_nomme_l_entreprise_du_destinataire(): void
    {
        // Validé le 2026-11-03, phrase revue le 2026-11-06 :
        // « …notre sélection de panneaux pour ORANGE CI : 232 emplacements… ».
        // Sans entreprise (copie, contact de test) :
        // « …notre sélection de panneaux : 232 emplacements… ».
        $phrase = 'en découvrant notre sélection de panneaux{% if contact.SOCIETE %} pour <strong>{{ contact.SOCIETE }}</strong>{% endif %}&nbsp;:';

        $this->assertStringContainsString(
            $phrase . ' <strong>{{ params.NB_PANNEAUX }} emplacements disponibles',
            file_get_contents(base_path('docs/diffusion/modele-brevo-disponibilites-leger.html'))
        );

        // Le gabarit de secours (sans modèle Brevo) dit la même chose.
        config(['brevo.template_id' => null]);
        $html = $this->prive(app(DiffusionDisponibilitesService::class), 'contenuCampagne', $this->envoi(), self::CLIENTS)['htmlContent'];
        $this->assertStringContainsString($phrase, $html);
    }

    // ── Texte et objet de la direction (2026-11-04) ─────────────────

    public function test_le_logo_est_large_sans_etre_deforme(): void
    {
        // Demande du 2026-11-06 : seule la largeur est fixée, la hauteur suit
        // les proportions de l'image. Une hauteur imposée l'étirerait.
        $modele = file_get_contents(base_path('docs/diffusion/modele-brevo-disponibilites-leger.html'));

        $this->assertSame(1, preg_match('/<img src="\{\{ params\.LOGO \}\}"[^>]*>/', $modele, $logo));
        $this->assertStringContainsString('width="220"', $logo[0]);
        $this->assertStringContainsString('width:220px; height:auto;', $logo[0]);
        $this->assertStringNotContainsString(' height="', $logo[0]);
    }

    public function test_seuls_le_titre_et_l_accroche_sont_a_gauche(): void
    {
        // Demande du 2026-11-06, capture à l'appui : ces deux blocs à gauche,
        // tout le reste du mail centré.
        $modele = file_get_contents(base_path('docs/diffusion/modele-brevo-disponibilites-leger.html'));
        $this->assertSame(2, substr_count($modele, '<td align="left"'), 'Le titre et l\'accroche, rien d\'autre.');
        $this->assertMatchesRegularExpression('/<td align="left"[^>]*text-align:left;">\s*<strong>De nouveaux emplacements/u', $modele);
        $this->assertMatchesRegularExpression('/<td align="left"[^>]*text-align:left;">\s*Anticipez votre visibilité/u', $modele);
        $this->assertStringContainsString('max-width:560px; margin:0 auto;', $modele);
        $this->assertStringContainsString('color:#111111; text-align:center;">', $modele);

        // Le gabarit de secours suit.
        $secours = file_get_contents(resource_path('views/diffusion/campagne-defaut.blade.php'));
        $this->assertSame(1, substr_count($secours, '<td align="left"'));
        $this->assertMatchesRegularExpression('/<td align="left"[^>]*>\s*<h1[^>]*>\s*De nouveaux emplacements/u', $secours);
        $this->assertMatchesRegularExpression('/<p style="margin:0;text-align:left;">\s*\{\{--.*?--\}\}\s*Anticipez votre visibilité/us', $secours);
    }

    public function test_le_mail_porte_le_texte_de_la_direction(): void
    {
        $modele = file_get_contents(base_path('docs/diffusion/modele-brevo-disponibilites-leger.html'));
        config(['brevo.template_id' => null]);
        $envoi = $this->envoi();
        $envoi->update([
            'nb_panneaux_abidjan'   => 220, 'pdf_path_abidjan'   => 'diffusions/abj.pdf', 'pdf_token_abidjan'   => str_repeat('c', 48),
            'nb_panneaux_interieur' => 40,  'pdf_path_interieur' => 'diffusions/int.pdf', 'pdf_token_interieur' => str_repeat('b', 48),
        ]);
        $secours = $this->prive(app(DiffusionDisponibilitesService::class), 'contenuCampagne', $envoi->fresh(), self::CLIENTS)['htmlContent'];

        foreach ([
            'De nouveaux emplacements publicitaires sont disponibles pour vos prochaines campagnes',
            'Anticipez votre visibilité',
            "Découvrez les disponibilités selon vos zones d'intérêt",
            // Texte du 2026-11-06 : ce que montre vraiment le PDF, pas de « recommandations ».
            'Découvrez pour chaque support une fiche détaillée',
            'sa localisation, une estimation du trafic et une description de ses caractéristiques',
            // Le passage à la ligne est voulu (2026-11-06).
            'Répondez simplement à cet email en indiquant les références souhaitées.<br>',
            'Notre équipe vous confirmera leur disponibilité',
            'sous 1&nbsp;heure ouvrée',
            'Anticipez vos réservations et sécurisez les meilleurs emplacements',
            "L'équipe commerciale CIBLE",
            'Votre visibilité, notre métier.',
        ] as $texte) {
            $this->assertStringContainsString($texte, $modele, "Modèle n° 14 : {$texte}");
            $this->assertStringContainsString($texte, $secours, "Gabarit de secours : {$texte}");
        }

        $this->assertStringNotContainsString('nos recommandations stratégiques', $modele);
        $this->assertStringNotContainsString('24&nbsp;heures', $modele);
    }

    public function test_une_zone_vide_n_est_pas_annoncee(): void
    {
        // Le catalogue de test n'a que l'intérieur du pays : ni « Abidjan »
        // dans la couverture, ni bouton vers un PDF qui n'existe pas.
        config(['brevo.template_id' => null]);
        $envoi = $this->envoi();
        $envoi->update(['nb_panneaux_interieur' => 40, 'pdf_path_interieur' => 'diffusions/int.pdf', 'pdf_token_interieur' => str_repeat('b', 48)]);

        $html = $this->prive(app(DiffusionDisponibilitesService::class), 'contenuCampagne', $envoi->fresh(), self::CLIENTS)['htmlContent'];

        $this->assertStringContainsString(e("dans les principales villes de Côte d'Ivoire") . '.', $html);
        $this->assertStringContainsString('Intérieur du pays', $html);
        $this->assertStringNotContainsString('à Abidjan', $html);
    }

    public function test_les_trois_objets_tournent_d_un_catalogue_au_suivant(): void
    {
        $service = app(DiffusionDisponibilitesService::class);
        $objet = fn (string $debut, string $fin, string $mode = DiffusionEnvoi::MODE_MANUEL) => $service->objet(
            DiffusionEnvoi::create([
                'creneau' => $debut, 'periode_debut' => $debut, 'periode_fin' => $fin,
                'mode' => $mode, 'statut' => DiffusionEnvoi::STATUT_PREPARE,
            ])
        );

        $anticipez = "Anticipez vos campagnes\u{00A0}: découvrez nos emplacements disponibles";
        $nouveaux  = 'De nouveaux emplacements à réserver pour vos prochaines campagnes';
        $ou        = "Où afficher votre prochaine campagne\u{00A0}? Découvrez nos disponibilités";

        $this->assertSame($anticipez, $objet('2026-11-15', '2026-11-30'));
        $this->assertSame($nouveaux,  $objet('2026-12-01', '2026-12-31'));
        $this->assertSame($ou,        $objet('2026-12-15', '2026-12-31'));
        $this->assertSame($anticipez, $objet('2027-01-01', '2027-01-31'));
        // Passage d'année : le tour continue sans répétition.
        $this->assertSame($ou,        $objet('2027-12-15', '2027-12-31'));
        $this->assertSame($anticipez, $objet('2028-01-01', '2028-01-31'));

        // Un test annonce l'objet que recevront les clients pour cette période.
        $this->assertSame('[TEST] ' . $anticipez, $objet('2026-11-15', '2026-11-30', DiffusionEnvoi::MODE_TEST));

        // Le contrat avec Brevo porte cet objet.
        $this->assertSame($anticipez, $this->prive($service, 'contenuCampagne', $this->envoi(), self::CLIENTS)['subject']);
    }

    public function test_diffusion_objet_remplace_la_rotation(): void
    {
        config(['diffusion.objet' => 'Nos disponibilités {periode}']);

        $this->assertSame('Nos disponibilités du 15 au 30 novembre 2026',
            app(DiffusionDisponibilitesService::class)->objet($this->envoi()));
    }

    // ── Copie à Stéphane ────────────────────────────────────────────

    public function test_stephane_recoit_le_catalogue_sans_compter_parmi_les_clients(): void
    {
        $clients = app(DiffusionDisponibilitesService::class)->destinataires()->pluck('email')->all();
        $this->assertNotEmpty($clients);
        $this->assertNotContains(self::STEPHANE, $clients);

        $inscrits = new \ArrayObject();
        $retires = new \ArrayObject();
        $this->mock(BrevoClient::class, function ($m) use ($clients, $inscrits, $retires) {
            $m->shouldReceive('enregistrerContact')->andReturnUsing(
                function ($email, $attributs, $listes) use ($inscrits) { $inscrits[$email] = [$attributs, $listes]; }
            );
            $m->shouldReceive('emailsDeLaListe')->with(self::CLIENTS)->andReturn([...$clients, self::STEPHANE, 'ancien@client.ci']);
            $m->shouldReceive('retirerDeLaListe')->andReturnUsing(
                function ($liste, $emails) use ($retires) { $retires->exchangeArray($emails); }
            );
        });
        $s = app(DiffusionDisponibilitesService::class);

        $this->assertSame([self::STEPHANE], $s->copiesCatalogue());
        $this->assertSame(count($clients), $this->prive($s, 'synchroniserDestinataires', self::CLIENTS, false));
        // Pas d'entreprise pour la copie : elle lit la phrase sans « pour … ».
        $this->assertSame([['CONTACT' => 'Stéphane Oponou'], [self::CLIENTS]], $inscrits[self::STEPHANE]);
        // Un client, lui, porte son entreprise.
        $this->assertSame('ORANGE CI', $inscrits[$clients[0]][0]['SOCIETE']);
        // Stéphane reste dans la liste ; seul l'ancien client en sort.
        $this->assertSame(['ancien@client.ci'], $retires->getArrayCopy());
    }

    public function test_une_copie_desinscrite_n_est_pas_remise_dans_la_liste(): void
    {
        DB::table('diffusion_desinscriptions')->insert(['email' => self::STEPHANE, 'motif' => 'desinscription', 'source' => 'brevo']);

        $inscrits = new \ArrayObject();
        $this->mock(BrevoClient::class, function ($m) use ($inscrits) {
            $m->shouldReceive('enregistrerContact')->andReturnUsing(
                function ($email) use ($inscrits) { $inscrits[$email] = true; }
            );
            $m->shouldReceive('emailsDeLaListe')->andReturn([]);
        });
        $s = app(DiffusionDisponibilitesService::class);

        $this->assertSame([], $s->copiesCatalogue());
        $this->prive($s, 'synchroniserDestinataires', self::CLIENTS, false);
        $this->assertArrayNotHasKey(self::STEPHANE, $inscrits->getArrayCopy());
    }

    public function test_un_test_n_ajoute_pas_la_copie(): void
    {
        // La liste « Tests internes » se gère à la main dans Brevo.
        $this->mock(BrevoClient::class, function ($m) {
            $m->shouldReceive('emailsDeLaListe')->with(self::TESTS)->andReturn(['commercial@cible-ci.com', 'romulad.cible@gmail.com']);
            $m->shouldNotReceive('enregistrerContact');
        });

        $this->assertSame(2, $this->prive(app(DiffusionDisponibilitesService::class), 'synchroniserDestinataires', self::TESTS, true));
    }

    public function test_stephane_recoit_aussi_la_confirmation_d_un_vrai_envoi(): void
    {
        // 2026-11-04. Par la copie commerciale, pas par DIFFUSION_CONFIRMATION_EMAIL,
        // qui sert aussi d'adresses de test aux mails clients.
        $s = app(DiffusionDisponibilitesService::class);

        $this->assertSame(['studio@cible-ci.com', self::STEPHANE, 'mp@cible-ci.com'], $s->destinatairesConfirmation());
        $this->assertSame($s->destinatairesConfirmation(), $s->destinatairesInternes($this->envoi()));

        // Un test : seulement son auteur.
        $test = $this->envoi(DiffusionEnvoi::MODE_TEST);
        $test->update(['declenche_par' => 2]);
        $this->assertSame(['k.yao@cible-ci.com'], $s->destinatairesInternes($test->fresh()));
    }

    public function test_le_quota_compte_la_copie(): void
    {
        $nbClients = app(DiffusionDisponibilitesService::class)->destinataires()->count();
        $this->mock(BrevoClient::class, function ($m) use ($nbClients) {
            $m->shouldReceive('enregistrerContact');
            $m->shouldReceive('emailsDeLaListe')->andReturn([]);
            $m->shouldReceive('envoisRestants')->andReturn($nbClients);   // juste assez pour les clients seuls
            $m->shouldNotReceive('creerCampagne');
        });

        $envoi = app(DiffusionDisponibilitesService::class)->envoyer($this->envoi());

        $this->assertSame(DiffusionEnvoi::STATUT_ECHEC, $envoi->statut);
        $this->assertStringContainsString('il en faut ' . ($nbClients + 1), $envoi->erreur);
    }
}
