<?php

namespace App\Http\Controllers;

use App\Models\Renouvellement;
use App\Services\Diffusion\ModelesMail;
use App\Services\Diffusion\RenouvellementService;
use Illuminate\Http\Request;

/**
 * Page des boutons de réponse des mails de renouvellement (2026-10-22).
 *
 * Lien personnel (jeton du dossier), sans connexion. Ouvrir le lien
 * n'enregistre rien : les antivirus de messagerie ouvrent les liens des
 * mails. C'est le bouton « Confirmer » (POST) qui enregistre la réponse.
 *
 * Liens « essai » (mails de test, aperçus) : même page, signée, mais
 * aucune réponse n'est enregistrée ; l'accusé et le mail à l'équipe
 * partent en test.
 */
class RenouvellementPublicController extends Controller
{
    public function __construct(private RenouvellementService $service) {}

    public function show(string $token, ?string $choix = null)
    {
        $dossier = Renouvellement::where('token', $token)->firstOrFail();

        return $this->page($dossier, $choix, essai: false);
    }

    public function store(Request $request, string $token)
    {
        $dossier = Renouvellement::where('token', $token)->firstOrFail();
        $data = $this->valider($request);

        if (!RenouvellementService::lienValable($dossier) || $dossier->statut === Renouvellement::CONFIRME) {
            return $this->page($dossier, $data['choix'], essai: false);
        }

        $this->service->enregistrerReponse($dossier, $data['choix'], $data['message'] ?? null);

        return $this->merci($dossier, $data['choix'], essai: false);
    }

    public function essai(int $dossier, string $sig, ?string $choix = null)
    {
        return $this->page($this->dossierEssai($dossier, $sig), $choix, essai: true);
    }

    public function essaiStore(Request $request, int $dossier, string $sig)
    {
        $d = $this->dossierEssai($dossier, $sig);
        $data = $this->valider($request);

        $this->service->simulerReponse($d, $data['choix'], $data['message'] ?? null);

        return $this->merci($d, $data['choix'], essai: true);
    }

    // ══════════════════════════════════════════════════════════════

    private function page(Renouvellement $dossier, ?string $choix, bool $essai)
    {
        $dossier->loadMissing(['campaign.client']);

        $etat = match (true) {
            !$essai && !RenouvellementService::lienValable($dossier) => 'expire',
            !$essai && $dossier->statut === Renouvellement::CONFIRME => 'confirme',
            default                                                  => 'formulaire',
        };

        return view('public.renouvellement', [
            'dossier'  => $dossier,
            'campagne' => $dossier->campaign,
            'etat'     => $etat,
            'essai'    => $essai,
            'choix'    => array_key_exists((string) $choix, ModelesMail::CHOIX) ? $choix : ($dossier->reponse_choix ?? 'renouveler'),
            'choixListe' => ModelesMail::CHOIX,
            'action'   => $essai
                ? route('renouvellement.essai.store', ['dossier' => $dossier->id, 'sig' => RenouvellementService::signatureEssai($dossier)])
                : route('renouvellement.store', ['token' => $dossier->token]),
        ]);
    }

    private function merci(Renouvellement $dossier, string $choix, bool $essai)
    {
        $dossier->loadMissing(['campaign.client']);

        return view('public.renouvellement', [
            'dossier'    => $dossier,
            'campagne'   => $dossier->campaign,
            'etat'       => 'merci',
            'essai'      => $essai,
            'choix'      => $choix,
            'choixListe' => ModelesMail::CHOIX,
            'action'     => null,
        ]);
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'choix'   => 'required|in:' . implode(',', array_keys(ModelesMail::CHOIX)),
            'message' => 'nullable|string|max:2000',
        ]);
    }

    private function dossierEssai(int $id, string $sig): Renouvellement
    {
        $dossier = Renouvellement::findOrFail($id);
        abort_unless(hash_equals(RenouvellementService::signatureEssai($dossier), $sig), 404);

        return $dossier;
    }
}
