<?php

namespace Tests\Unit;

use App\Models\Reservation;
use App\Models\User;
use Tests\TestCase;

/**
 * Mail de proposition (2026-11-25) : depuis le 25/06, ses deux vues
 * utilisaient $totalDays sans le définir. Le mail ne pouvait plus s'afficher
 * (aperçu « L'aperçu n'a pas pu être préparé », bouton Envoyer grisé) ni
 * partir. Le nombre de jours affiché vient désormais d'une source unique,
 * Reservation::nombreJours() (validé par l'utilisateur : « les 5 endroits »).
 */
class PropositionMailRenduTest extends TestCase
{
    private function reservation(string $debut, string $fin): Reservation
    {
        $r = new Reservation(['reference' => 'RES-ESSAI', 'start_date' => $debut, 'end_date' => $fin, 'total_amount' => 360000]);
        $commercial = new User(['name' => 'Awa Konan', 'email' => 'awa@exemple.ci', 'role' => 'commercial']);
        $r->setRelation('commercial', $commercial);
        $r->setRelation('user', $commercial);

        return $r;
    }

    public function test_le_nombre_de_jours_affiche_vaut_au_moins_un(): void
    {
        $this->assertSame(89, $this->reservation('2026-12-15', '2027-03-14')->nombreJours());
        $this->assertSame(1, $this->reservation('2026-12-15', '2026-12-15')->nombreJours(), 'même jour : 1 jour, pas 0');
        $this->assertSame(0, $this->reservation('2026-12-15', '2026-12-15')->durationInDays(), 'le calcul brut ne change pas');
    }

    public function test_le_mail_de_proposition_s_affiche_de_nouveau(): void
    {
        $donnees = [
            'reservation' => $this->reservation('2026-12-15', '2027-03-14'),
            'client'      => null,
            'panels'      => collect([[
                'id' => 1, 'reference' => 'CDY-001', 'name' => 'Riviera', 'commune' => 'COCODY', 'format' => '12 m²',
                'dimensions' => '4×3', 'monthly_rate' => 120000, 'total' => 360000, 'photo' => null, 'source' => 'interne',
                'is_lit' => false, 'zone' => null, 'quartier' => null, 'adresse' => null,
            ]]),
            'lien'        => 'https://panora.exemple/proposition/RES-ESSAI/lien',
            'expiresAt'   => now()->addDays(7),
            'sentAt'      => now(),
        ];

        $html  = view('admin.emails.proposition', $donnees)->render();
        $texte = view('emails.plain.proposition', $donnees)->render();

        $this->assertStringContainsString('89 jours', $html);
        $this->assertMatchesRegularExpression('/Durée\s+:\s+89 jours/', $texte);
    }

    public function test_les_ecrans_lisent_la_meme_source(): void
    {
        foreach ([
            'resources/views/admin/emails/proposition.blade.php',
            'resources/views/emails/plain/proposition.blade.php',
            'resources/views/admin/propositions/show.blade.php',
            'resources/views/admin/reservations/show.blade.php',
            'app/Http/Controllers/Admin/ReservationController.php',
            'app/Http/Controllers/Client/ClientDashboardController.php',
        ] as $fichier) {
            $this->assertStringContainsString('nombreJours()', file_get_contents(base_path($fichier)), $fichier);
        }
    }
}
