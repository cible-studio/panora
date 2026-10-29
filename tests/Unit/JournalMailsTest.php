<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\JournalMail;
use App\Services\Diffusion\RenouvellementService;
use App\Services\JournalMails;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Journal des mails (2026-10-29) : chaque mail parti (serveur de mail ou
 * Brevo) et chaque échec, avec ses destinataires exacts et son dossier.
 */
class JournalMailsTest extends TestCase
{
    use Concerns\BaseRenouvellement;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerBaseRenouvellement();
        (require base_path('database/migrations/2026_10_29_120000_create_journal_mails_table.php'))->up();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Un mail simple portant une campagne, comme les vrais mails de suivi. */
    private function mail(?Campaign $campaign = null): Mailable
    {
        return new class($campaign) extends Mailable {
            public function __construct(public ?Campaign $campaign) {}
            public function envelope(): Envelope { return new Envelope(subject: 'Votre campagne démarre'); }
            public function content(): Content { return new Content(htmlString: '<p>Bonjour</p>'); }
        };
    }

    public function test_un_mail_parti_par_le_serveur_est_note_avec_ses_destinataires_et_son_dossier(): void
    {
        Mail::swap((new \Illuminate\Mail\MailManager(app()))->mailer('array')); // vrai envoi (transport « array »), pas le faux de la base
        $c = $this->campagne();

        Mail::to(['awa@orange.ci', 'jean@orange.ci'])->cc('commercial@cible-ci.com')->send($this->mail($c));

        $ligne = JournalMail::sole();
        $this->assertSame('envoye', $ligne->statut);
        $this->assertSame('serveur', $ligne->canal);
        $this->assertSame('Votre campagne démarre', $ligne->sujet);
        $this->assertSame('awa@orange.ci, jean@orange.ci', $ligne->destinataires);
        $this->assertSame('commercial@cible-ci.com', $ligne->copie);
        $this->assertSame(['campagne', $c->id], [$ligne->dossier_type, $ligne->dossier_id]);
    }

    public function test_un_echec_est_note_avec_son_erreur(): void
    {
        JournalMails::echec(['awa@orange.ci'], $this->mail(), 'Connexion SMTP refusée');

        $ligne = JournalMail::sole();
        $this->assertSame('echec', $ligne->statut);
        $this->assertSame('awa@orange.ci', $ligne->destinataires);
        $this->assertSame('Connexion SMTP refusée', $ligne->erreur);
    }

    public function test_un_mail_brevo_est_note_adresse_par_adresse(): void
    {
        $c = $this->campagne();

        $this->assertSame('envoye', app(RenouvellementService::class)->traiter($c)->statut);

        $ligne = JournalMail::where('canal', 'brevo')->sole();
        $this->assertSame('envoye', $ligne->statut);
        $this->assertSame('awa@orange.ci', $ligne->destinataires);
        $this->assertSame('commercial@cible-ci.com', $ligne->copie);
        $this->assertSame(['campagne', $c->id], [$ligne->dossier_type, $ligne->dossier_id]);
        $this->assertStringStartsWith('Brevo · ', $ligne->type);
    }

    public function test_un_journal_en_panne_ne_bloque_pas_l_envoi(): void
    {
        \Illuminate\Support\Facades\Schema::drop('journal_mails');
        Mail::swap((new \Illuminate\Mail\MailManager(app()))->mailer('array'));

        Mail::to('awa@orange.ci')->send($this->mail());

        $this->assertTrue(true); // aucune exception
    }
}
