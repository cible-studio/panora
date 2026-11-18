<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BarometreReponse;
use App\Models\Client;
use App\Services\Barometre\BarometreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Écran « Baromètre clients » (2026-11-17). Accès : module « barometre »
 * (admins + BAROMETRE_ACCES). Tout passe par BarometreService.
 *
 * Refonte du 17/11 après le premier essai : comme les autres listes de
 * Panora, les cartes du haut filtrent la liste (un clic l'active, un
 * second clic l'enlève), la recherche et la pagination se rechargent sans
 * quitter la page (?ajax=1 → JSON), et les actions s'ouvrent dans des
 * fenêtres. Les réglages (questionnaire, date, signature, réponses) se
 * font à l'écran.
 */
class BarometreController extends Controller
{
    public const PAR_PAGE = 20;

    /** Filtre → statuts de la liste. Les cartes comptent avec la même règle. */
    public const FILTRES = [
        'tous'              => null,
        'a_envoyer'         => BarometreService::A_ENVOYER,
        'invites'           => ['invite', 'ouvert', 'en_cours'],
        'repondus'          => ['repondu'],
        'sans_destinataire' => BarometreService::SANS_DESTINATAIRE,
        'exclus'            => ['exclu'],
    ];

    public const MOTIFS = ['Régie concurrente', 'Nouveau client', 'Autre'];

    public function __construct(private BarometreService $service) {}

    public function index(Request $request)
    {
        $filtre = array_key_exists((string) $request->query('filtre'), self::FILTRES) ? (string) $request->query('filtre') : 'tous';
        $q      = trim((string) $request->query('q', ''));
        $liste  = $this->service->liste();
        $lignes = self::filtrer($liste, $filtre, $q);

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginee = new LengthAwarePaginator(
            $lignes->forPage($page, self::PAR_PAGE)->values(), $lignes->count(), self::PAR_PAGE, $page,
            ['path' => route('admin.barometre.index'), 'query' => array_filter(['filtre' => $filtre !== 'tous' ? $filtre : null, 'q' => $q ?: null])],
        );

        if ($request->boolean('ajax')) {
            return response()->json([
                'lignes'     => view('admin.barometre.partials.lignes', ['lignes' => $paginee])->render(),
                'pagination' => $paginee->hasPages() ? $paginee->links()->toHtml() : '',
                'total'      => $paginee->total(),
            ]);
        }

        return view('admin.barometre.index', [
            'edition'      => $this->service->edition(),
            'production'   => $this->service->enProduction(),
            'reglage'      => $this->service->reglageManquant(),
            'signature'    => $this->service->signature(),
            'expediteur'   => $this->service->expediteur(),
            'repondreA'    => $this->service->repondreA(),
            'formulaire'   => $this->service->formulaire(),
            'jetonGoogle'  => (string) config('barometre.jeton_google') !== '',
            'compteurs'    => $this->service->compteurs(),
            'nombres'      => collect(self::FILTRES)->map(fn ($s, $f) => self::filtrer($liste, $f, '')->count())->all(),
            'reponses'     => $this->service->nombreReponses(),
            'derniere'     => BarometreReponse::where('edition', $this->service->code())->max('created_at'),
            'nonReconnues' => $this->service->reponsesNonReconnues(),
            'lignes'       => $paginee,
            'liste'        => $liste,
            'filtre'       => $filtre,
            'q'            => $q,
        ]);
    }

    /** Filtre d'une carte + recherche (client, interlocuteur, adresse). */
    public static function filtrer(Collection $liste, string $filtre, string $q): Collection
    {
        $statuts = self::FILTRES[$filtre] ?? null;
        $lignes  = $statuts === null ? $liste : $liste->whereIn('statut', $statuts);

        if ($q !== '') {
            $cle = BarometreService::normaliser($q);
            $lignes = $lignes->filter(fn ($l) => str_contains(BarometreService::normaliser($l['client']->name), $cle)
                || str_contains(BarometreService::normaliser((string) $l['destinataire']['contact']), $cle)
                || str_contains((string) $l['destinataire']['email'], mb_strtolower($q)));
        }

        return $lignes->values();
    }

    /** Excel des clients sans destinataire, à faire compléter par les commerciaux (18/11). */
    public function aCompleter()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BarometreACompleterExport($this->service->aCompleter()),
            'barometre-contacts-a-completer-' . now()->format('Y-m-d') . '.xlsx',
        );
    }

    /** Le mail exact d'un client, à l'adresse de la personne connectée. */
    public function tester(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer'],
            'relance'   => ['nullable', 'boolean'],
        ]);
        $client  = Client::clients()->findOrFail($data['client_id']);
        $relance = (bool) ($data['relance'] ?? false);
        $adresse = (string) $request->user()->email;

        try {
            $this->service->envoyerTest($client, $adresse, $relance);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::warning('barometre.test_refuse', ['erreur' => $e->getMessage()]);

            return back()->with('error', 'Brevo a refusé le mail de test : ' . $e->getMessage());
        }

        return back()->with('success', 'Mail de test (' . ($relance ? 'relance' : 'invitation') . ", {$client->name}) envoyé à {$adresse}.");
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
        $data = $request->validate([
            'motif'     => ['required', 'string', 'in:' . implode(',', self::MOTIFS)],
            'precision' => ['nullable', 'string', 'max:200'],
        ]);
        $precision = trim((string) ($data['precision'] ?? ''));
        if ($data['motif'] === 'Autre' && $precision === '') {
            return back()->with('error', 'Précisez pourquoi ' . $client->name . ' est retiré.');
        }
        $motif = $data['motif'] === 'Autre' ? $precision : $data['motif'] . ($precision !== '' ? ' — ' . $precision : '');
        $this->service->exclure($client, $motif, $request->user());

        return back()->with('success', "{$client->name} est retiré du baromètre ({$motif}).");
    }

    public function reintegrer(Client $client): RedirectResponse
    {
        $this->service->reintegrer($client);

        return back()->with('success', "{$client->name} est remis dans le baromètre.");
    }

    /** ⚙ Réglages : questionnaire, date limite, signature, adresse de réponse. */
    public function reglages(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'lien'               => ['nullable', 'string', 'max:1000'],
            'date_limite'        => ['required', 'date'],
            'signature_nom'      => ['nullable', 'string', 'max:120'],
            'signature_fonction' => ['nullable', 'string', 'max:160'],
            'signature_societe'  => ['nullable', 'string', 'max:120'],
            'repondre_a'         => ['nullable', 'string', 'max:191'],
        ]);

        $date = Carbon::parse($data['date_limite']);
        $dateChangee = !$date->isSameDay($this->service->edition()->date_limite);
        if ($dateChangee && $date->lt(today())) {
            return back()->with('error', 'La date limite ne peut pas être dans le passé.');
        }

        try {
            // Lien vide : on garde celui déjà enregistré (le champ montre l'actuel).
            $this->service->enregistrerReglages(array_filter(
                $data, fn ($v, $k) => $k !== 'date_limite' && ($k !== 'lien' || trim((string) $v) !== ''), ARRAY_FILTER_USE_BOTH,
            ) + array_fill_keys(['signature_nom', 'signature_fonction', 'signature_societe', 'repondre_a'], null));
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
        if ($dateChangee) {
            $this->service->changerDateLimite($date);
        }

        return back()->with('success', 'Réglages enregistrés.' . ($dateChangee
            ? ' Date limite : ' . $date->locale('fr')->translatedFormat('j F Y') . ' — les liens déjà envoyés expirent désormais ce jour-là à minuit.'
            : ''));
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
