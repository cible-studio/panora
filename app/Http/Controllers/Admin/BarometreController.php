<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BarometreReponse;
use App\Models\Client;
use App\Services\Barometre\BarometreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Écran « Baromètre clients » (2026-11-17) : la liste, l'envoi de test,
 * l'envoi à tous, la relance, les exclusions, la date limite, les réponses
 * non reconnues. Accès : module « barometre » (admins + BAROMETRE_ACCES).
 * Tout passe par BarometreService.
 */
class BarometreController extends Controller
{
    public const FILTRES = [
        'tous'       => 'Tous',
        'a_envoyer'  => 'À envoyer',
        'invites'    => 'Invités',
        'repondus'   => 'Ont répondu',
        'a_traiter'  => 'Sans destinataire',
        'exclus'     => 'Exclus',
    ];

    public function __construct(private BarometreService $service) {}

    public function index(Request $request)
    {
        $filtre = array_key_exists($request->query('filtre'), self::FILTRES) ? $request->query('filtre') : 'tous';
        $liste  = $this->service->liste();

        $lignes = match ($filtre) {
            'a_envoyer' => $liste->whereIn('statut', BarometreService::A_ENVOYER),
            'invites'   => $liste->whereIn('statut', ['invite', 'ouvert', 'en_cours']),
            'repondus'  => $liste->where('statut', 'repondu'),
            'a_traiter' => $liste->whereIn('statut', ['sans_email', 'a_choisir', 'doublon']),
            'exclus'    => $liste->where('statut', 'exclu'),
            default     => $liste,
        };

        return view('admin.barometre.index', [
            'edition'      => $this->service->edition(),
            'production'   => $this->service->enProduction(),
            'reglage'      => $this->service->reglageManquant(),
            'compteurs'    => $this->service->compteurs(),
            'reponses'     => $this->service->nombreReponses(),
            'nonReconnues' => $this->service->reponsesNonReconnues(),
            'lignes'       => $lignes->values(),
            'liste'        => $liste,
            'filtre'       => $filtre,
        ]);
    }

    /** Le mail exact d'un client, à l'adresse de la personne connectée. */
    public function tester(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer'],
            'relance'   => ['nullable', 'boolean'],
        ]);
        $client  = Client::clients()->findOrFail($data['client_id']);
        $adresse = (string) $request->user()->email;

        try {
            $this->service->envoyerTest($client, $adresse, (bool) ($data['relance'] ?? false));
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::warning('barometre.test_refuse', ['erreur' => $e->getMessage()]);

            return back()->with('error', 'Brevo a refusé le mail de test : ' . $e->getMessage());
        }

        return back()->with('success', 'Mail de test (' . ($data['relance'] ?? false ? 'relance' : 'invitation') . ", {$client->name}) envoyé à {$adresse}.");
    }

    public function envoyer(Request $request): RedirectResponse
    {
        $nombre = (int) $request->validate(['nombre' => ['required', 'integer', 'min:1']])['nombre'];

        try {
            $n = $this->service->demanderInvitations($nombre, $request->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$n} invitation(s) en cours d'envoi. Les statuts se mettent à jour d'ici quelques minutes.");
    }

    public function relancer(Request $request): RedirectResponse
    {
        $nombre = (int) $request->validate(['nombre' => ['required', 'integer', 'min:1']])['nombre'];

        try {
            $n = $this->service->demanderRelances($nombre, $request->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$n} relance(s) en cours d'envoi.");
    }

    public function exclure(Request $request, Client $client): RedirectResponse
    {
        $motif = $request->validate(['motif' => ['required', 'string', 'max:255']])['motif'];
        $this->service->exclure($client, $motif, $request->user());

        return back()->with('success', "{$client->name} est retiré du baromètre ({$motif}).");
    }

    public function reintegrer(Client $client): RedirectResponse
    {
        $this->service->reintegrer($client);

        return back()->with('success', "{$client->name} est remis dans le baromètre.");
    }

    public function dateLimite(Request $request): RedirectResponse
    {
        $date = $request->validate(['date_limite' => ['required', 'date', 'after_or_equal:today']])['date_limite'];
        $this->service->changerDateLimite(Carbon::parse($date));

        return back()->with('success', 'Date limite : ' . Carbon::parse($date)->locale('fr')->translatedFormat('j F Y')
            . '. Les liens déjà envoyés expirent désormais ce jour-là à minuit.');
    }

    /** Une réponse dont le nom n'a pas été reconnu : on choisit le client. */
    public function rattacher(Request $request, BarometreReponse $reponse): RedirectResponse
    {
        abort_unless($reponse->edition === $this->service->code(), 404);
        $id = $request->validate(['client_id' => ['nullable', 'integer']])['client_id'] ?? null;
        $client = $id ? Client::findOrFail($id) : null;
        $this->service->rattacher($reponse, $client, $request->user());

        return back()->with('success', $client ? "Réponse « {$reponse->structure} » rattachée à {$client->name}." : 'Rattachement retiré.');
    }
}
