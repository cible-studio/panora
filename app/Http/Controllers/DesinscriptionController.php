<?php

namespace App\Http\Controllers;

use App\Models\DiffusionDesinscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Lien « Ne plus recevoir nos messages commerciaux » des mails envoyés par
 * Panora (mail de fin de campagne) — 2026-10-21.
 *
 * L'adresse rejoint la liste des adresses exclues, commune avec la
 * diffusion des disponibilités : elle ne reçoit plus ni les disponibilités
 * ni le mail de fin de campagne. Les factures et documents liés aux
 * commandes continuent de lui parvenir.
 *
 *   GET  : page de confirmation. Ne désinscrit PAS : les antivirus de
 *          messagerie ouvrent les liens des mails, ce qui désinscrirait
 *          des clients à leur insu.
 *   POST : désinscription — bouton de la page, ou désinscription en un
 *          clic de Gmail / Yahoo (en-tête List-Unsubscribe-Post).
 *          CSRF exempté (bootstrap/app.php) : la signature du lien suffit.
 */
class DesinscriptionController extends Controller
{
    public function show(string $e, string $sig)
    {
        $email = DiffusionDesinscription::emailDuLien($e, $sig) ?? abort(404);

        return view('public.desinscription', [
            'email'     => $email,
            'deja'      => DiffusionDesinscription::where('email', $email)->exists(),
            'confirme'  => false,
            'action'    => route('desinscription.store', ['e' => $e, 'sig' => $sig]),
        ]);
    }

    public function store(Request $request, string $e, string $sig)
    {
        $email = DiffusionDesinscription::emailDuLien($e, $sig) ?? abort(404);

        $ligne = DiffusionDesinscription::firstOrCreate(
            ['email' => $email],
            ['motif' => 'desinscription', 'source' => 'lien', 'survenu_at' => now()]
        );

        if ($ligne->wasRecentlyCreated) {
            Log::info('desinscription.lien', ['email' => $email, 'un_clic' => $request->has('List-Unsubscribe')]);
        }

        return view('public.desinscription', [
            'email'    => $email,
            'deja'     => true,
            'confirme' => true,
            'action'   => null,
        ]);
    }
}
