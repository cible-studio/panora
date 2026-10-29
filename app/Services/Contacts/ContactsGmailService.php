<?php

namespace App\Services\Contacts;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\EnvoiContactsGmail;
use App\Support\NomEntreprise;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Contacts envoyés depuis le Google Sheets, bouton « Envoyer à Panora »
 * (2026-10-28). Source unique de la règle de rattachement, validée par la
 * direction :
 *   · adresse déjà connue (interlocuteur ou fiche) : laissée où elle est,
 *     seuls les champs vides sont complétés, jamais dupliquée ;
 *   · adresse ou entreprise supprimée dans Panora : refusée, elle ne
 *     revient pas ;
 *   · nouvelle adresse : entreprise trouvée par le domaine, sinon par le nom
 *     (même écrit autrement : « Coris-Bank » = « CORIS BANK », règle
 *     NomEntreprise, 2026-10-29), sinon créée (statut et secteur du Sheets, sans NCC ni adresse
 *     de fiche) ; une messagerie personnelle se rattache par le nom
 *     seulement, sans nom elle est refusée ;
 *   · domaine présent dans plusieurs entreprises : refusé, sauf si le nom
 *     désigne l'une d'elles ;
 *   · statut du contact = celui du Sheets ; « reçoit les mails » décoché
 *     (2026-10-29 : il les reçoit comme interlocuteur d'une campagne) ;
 *   · un contact Client fait passer cliente une entreprise prospect (elle
 *     seule, pas ses autres interlocuteurs), jamais l'inverse ;
 *   · secteur complété seulement s'il est vide.
 */
class ContactsGmailService
{
    public const SOURCE = 'gmail';

    /**
     * Traite un lot de lignes du Sheets. En simulation, tout est joué puis
     * annulé : le Sheets voit exactement ce qui se passerait. Chaque appel
     * est noté au journal.
     *
     * @param list<array<string, mixed>> $lignes
     * @return array{envoi_id: int, simulation: bool, resume: array<string, int>, lignes: list<array<string, mixed>>}
     */
    public function traiter(array $lignes, string $envoyePar, bool $simulation = false): array
    {
        $resultats = [];

        DB::beginTransaction();
        try {
            foreach (array_values($lignes) as $i => $ligne) {
                $ligne = is_array($ligne) ? $ligne : [];
                try {
                    // Point de reprise par ligne : une erreur n'annule pas les autres.
                    $r = DB::transaction(fn () => $this->traiterLigne($ligne, $simulation));
                } catch (\Throwable $e) {
                    // Une fiche créée par cette ligne vient d'être annulée : liste des noms relue.
                    app(NomEntreprise::class)->oublier();
                    Log::warning('contacts_gmail.ligne_echec', ['email' => $ligne['email'] ?? null, 'erreur' => $e->getMessage()]);
                    $r = self::refus('Erreur Panora : ' . mb_substr($e->getMessage(), 0, 200));
                }
                $resultats[] = [
                    'ligne' => $ligne['ligne'] ?? $i + 1,
                    'email' => mb_strtolower(trim((string) ($ligne['email'] ?? ''))),
                ] + $r;
            }
            $simulation ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $compte = fn (string $res) => count(array_filter($resultats, fn ($r) => $r['resultat'] === $res));
        $resume = [
            'lignes'             => count($resultats),
            'crees'              => $compte('cree'),
            'completes'          => $compte('complete'),
            'a_jour'             => $compte('a_jour'),
            'refuses'            => $compte('refuse'),
            'entreprises_creees' => count(array_filter($resultats, fn ($r) => $r['entreprise_creee'])),
        ];

        $envoi = EnvoiContactsGmail::create($resume + [
            'envoye_par' => $envoyePar,
            'simulation' => $simulation,
            'detail'     => $resultats,
        ]);

        Log::info('contacts_gmail.envoi', $resume + ['envoi_id' => $envoi->id, 'par' => $envoyePar, 'simulation' => $simulation]);

        return ['envoi_id' => $envoi->id, 'simulation' => $simulation, 'resume' => $resume, 'lignes' => $resultats];
    }

    /** @return array{resultat: string, message: string, entreprise: ?string, client_id: ?int, entreprise_creee: bool} */
    private function traiterLigne(array $l, bool $simulation): array
    {
        $email = mb_strtolower(trim((string) ($l['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return self::refus('Adresse invalide.');
        }
        $domaine = substr($email, strrpos($email, '@') + 1);
        if (in_array($domaine, config('contacts.domaines_internes', []), true)) {
            return self::refus('Adresse interne CIBLE.');
        }

        $statut = self::statut($l['relation'] ?? $l['statut'] ?? '');
        if (!$statut) {
            return self::refus('Statut à choisir : Client ou Prospect.');
        }

        $nom        = self::texte($l['nom'] ?? '', 120);
        $poste      = self::texte($l['poste'] ?? '', 100);
        $telephone  = self::premierTelephone((string) ($l['telephones'] ?? $l['telephone'] ?? ''));
        $entreprise = mb_strtoupper(self::texte($l['entreprise'] ?? '', 150));
        $secteur    = trim((string) ($l['secteur'] ?? ''));
        $secteurValide = in_array($secteur, Client::SECTORS, true) ? $secteur : null;
        $notes = [];  // ce qui a changé
        $avert = [];  // à savoir, rien de modifié
        if ($secteur !== '' && !$secteurValide) {
            $avert[] = "secteur inconnu de Panora : « {$secteur} »";
        }

        // ── 1. Adresse déjà connue comme interlocuteur ───────────────
        $connus = ClientContact::withTrashed()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->with('client')
            ->orderBy('id')
            ->get();
        if ($connus->isNotEmpty()) {
            $contact = $connus->first(fn ($c) => !$c->trashed() && $c->client && !$c->client->trashed());
            if (!$contact) {
                return self::refus('Supprimé dans Panora : ne revient pas.');
            }

            $maj = [];
            if (trim((string) $contact->phone) === '' && $telephone !== '') {
                $maj['phone'] = $telephone;
                $notes[] = 'téléphone ajouté';
            }
            if (trim((string) $contact->position) === '' && $poste !== '') {
                $maj['position'] = $poste;
                $notes[] = 'poste ajouté';
            }
            if ($statut === Client::STATUT_CLIENT && ($contact->statut ?? Client::STATUT_CLIENT) === Client::STATUT_PROSPECT) {
                $maj['statut'] = Client::STATUT_CLIENT; // la case « reçoit les mails » ne bouge pas (2026-10-29)
                $notes[] = 'passé client';
            }
            if ($maj) {
                $contact->update($maj);
            }
            $notes = array_merge($notes, $this->completerEntreprise($contact->client, $statut, $secteurValide));

            return self::resultat($notes ? 'complete' : 'a_jour', 'Déjà chez ' . $contact->client->name, $notes, $avert, $contact->client);
        }

        // ── 2. Adresse de la fiche d'une entreprise ──────────────────
        $fiches = Client::withTrashed()->whereRaw('LOWER(TRIM(email)) = ?', [$email])->orderBy('id')->get();
        if ($fiches->isNotEmpty()) {
            $client = $fiches->first(fn ($c) => !$c->trashed());
            if (!$client) {
                return self::refus('Entreprise supprimée dans Panora : ' . $fiches->first()->name . '.');
            }

            $maj = [];
            if (trim((string) $client->contact_name) === '' && $nom !== '') {
                $maj['contact_name'] = mb_substr($nom, 0, 100);
                $notes[] = 'nom du contact ajouté';
            }
            if (trim((string) $client->phone) === '' && $telephone !== '' && mb_strlen($telephone) <= 20) {
                $maj['phone'] = $telephone;
                $notes[] = 'téléphone ajouté';
            }
            if ($maj) {
                $client->update($maj);
            }
            $notes = array_merge($notes, $this->completerEntreprise($client, $statut, $secteurValide));

            return self::resultat($notes ? 'complete' : 'a_jour', 'Adresse de la fiche ' . $client->name, $notes, $avert, $client);
        }

        // ── 3. Nouvelle adresse : quelle entreprise ? ────────────────
        $personnelle = in_array($domaine, config('contacts.messageries_personnelles', []), true);
        $client = null;

        if (!$personnelle) {
            $parDomaine = $this->entreprisesDuDomaine($domaine);
            if ($parDomaine->count() === 1) {
                $client = $parDomaine->first();
            } elseif ($parDomaine->count() > 1) {
                $client = $entreprise !== '' ? $parDomaine->first(fn ($c) => NomEntreprise::cle($c->name) === NomEntreprise::cle($entreprise)) : null;
                if (!$client) {
                    return self::refus('Domaine partagé par plusieurs entreprises : ' . $parDomaine->pluck('name')->implode(', ') . '. À rattacher à la main.');
                }
            }
        }

        if (!$client && $entreprise !== '') {
            // Même nom, même écrit autrement ; la plus ancienne fiche s'il y en a deux.
            $id = app(NomEntreprise::class)->identique($entreprise);
            $client = $id !== null ? Client::find($id) : null;
        }

        if (!$client) {
            // Une entreprise supprimée dans Panora ne revient pas (nom comparé
            // comme pour une fiche active).
            $supprimee = $entreprise !== ''
                ? Client::onlyTrashed()->orderBy('id')->get(['id', 'name'])->first(fn ($c) => NomEntreprise::cle($c->name) === NomEntreprise::cle($entreprise))
                : null;
            if (!$supprimee && !$personnelle) {
                $supprimee = Client::onlyTrashed()
                    ->where(fn ($q) => $q->whereRaw('LOWER(TRIM(email)) LIKE ?', ['%@' . $domaine])
                        ->orWhereHas('contacts', fn ($c) => $c->whereRaw('LOWER(TRIM(email)) LIKE ?', ['%@' . $domaine])))
                    ->first();
            }
            if ($supprimee) {
                return self::refus('Entreprise supprimée dans Panora : ' . $supprimee->name . '.');
            }
            if ($entreprise === '') {
                return self::refus("Renseigner l'entreprise.");
            }

            $client = Client::create([
                'name'              => $entreprise,
                'sector'            => $secteurValide,
                'statut_commercial' => $statut,
            ]);
            $notes[] = 'entreprise créée (' . mb_strtolower(Client::STATUTS[$statut]) . ')';
        } else {
            $notes = array_merge($notes, $this->completerEntreprise($client, $statut, $secteurValide));
            if ($entreprise !== '' && mb_strtoupper(trim($client->name)) !== $entreprise) {
                $avert[] = "nom dans le Sheets : {$entreprise}";
            }
        }

        ClientContact::create([
            'client_id'              => $client->id,
            'name'                   => $nom !== '' ? $nom : mb_substr(strstr($email, '@', true), 0, 120),
            'email'                  => $email,
            'phone'                  => $telephone !== '' ? $telephone : null,
            'role'                   => 'autre',
            'position'               => $poste !== '' ? $poste : null,
            'is_primary'             => false,
            // Décoché (direction, 2026-10-29) : il reçoit les mails de campagne
            // quand il devient interlocuteur d'une campagne, ou si on le coche.
            'receives_notifications' => false,
            'statut'                 => $statut,
            'source'                 => self::SOURCE,
        ]);

        $resultat = self::resultat('cree', 'Ajouté chez ' . $client->name, $notes, $avert, $client);
        $resultat['entreprise_creee'] = $client->wasRecentlyCreated;
        if ($simulation && $client->wasRecentlyCreated) {
            $resultat['client_id'] = null; // fiche annulée avec la simulation
        }

        return $resultat;
    }

    /**
     * Complète l'entreprise : secteur vide, et passage client si le contact
     * envoyé est Client. Renvoie ce qui a changé.
     *
     * @return list<string>
     */
    private function completerEntreprise(Client $client, string $statut, ?string $secteur): array
    {
        $maj = [];
        $notes = [];
        if ($statut === Client::STATUT_CLIENT && $client->estProspect()) {
            $maj['statut_commercial'] = Client::STATUT_CLIENT;
            $notes[] = 'entreprise passée cliente';
        }
        if (trim((string) $client->sector) === '' && $secteur) {
            $maj['sector'] = $secteur;
            $notes[] = 'secteur ajouté';
        }
        if ($maj) {
            $client->update($maj);
        }

        return $notes;
    }

    /**
     * Entreprises actives dont la fiche ou un interlocuteur a une adresse
     * de ce domaine.
     *
     * @return \Illuminate\Support\Collection<int, Client>
     */
    private function entreprisesDuDomaine(string $domaine): \Illuminate\Support\Collection
    {
        $motif = '%@' . $domaine;

        return Client::query()
            ->where(fn ($q) => $q->whereRaw('LOWER(TRIM(email)) LIKE ?', [$motif])
                ->orWhereHas('contacts', fn ($c) => $c->whereRaw('LOWER(TRIM(email)) LIKE ?', [$motif])))
            ->orderBy('name')
            ->get();
    }

    private static function statut(mixed $v): ?string
    {
        $v = mb_strtolower(trim((string) $v));

        return match (true) {
            str_starts_with($v, 'client')   => Client::STATUT_CLIENT,
            str_starts_with($v, 'prospect') => Client::STATUT_PROSPECT,
            default                         => null,
        };
    }

    private static function texte(mixed $v, int $max): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) $v)), 0, $max);
    }

    /** Le Sheets peut lister plusieurs numéros (« 07… / 05… ») : on garde le premier. */
    private static function premierTelephone(string $v): string
    {
        foreach (preg_split('/[\/;,|\n]+/', $v) as $numero) {
            $numero = self::texte($numero, 30);
            if ($numero !== '') {
                return $numero;
            }
        }

        return '';
    }

    /** @return array{resultat: string, message: string, entreprise: ?string, client_id: ?int, entreprise_creee: bool} */
    private static function resultat(string $resultat, string $message, array $notes, array $avert, Client $client): array
    {
        return [
            'resultat'   => $resultat,
            'message'    => $message . ($notes ? ' : ' . implode(', ', $notes) : '') . '.'
                . ($avert ? ' À savoir : ' . implode(' ; ', $avert) . '.' : ''),
            'entreprise' => $client->name,
            'client_id'  => $client->id,
            'entreprise_creee' => false,
        ];
    }

    /** @return array{resultat: string, message: string, entreprise: null, client_id: null, entreprise_creee: false} */
    private static function refus(string $message): array
    {
        return ['resultat' => 'refuse', 'message' => $message, 'entreprise' => null, 'client_id' => null, 'entreprise_creee' => false];
    }
}
