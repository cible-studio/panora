<?php

namespace App\Services\Campagnes;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\DiffusionDesinscription;
use Illuminate\Support\Collection;

/**
 * Campagnes dont l'interlocuteur reste à renseigner (2026-11-09). Sans
 * interlocuteur, les mails de suivi partent à tous les contacts cochés du
 * client : chaque commercial choisit le sien, sur l'écran
 * admin.campaigns.interlocuteurs ou dans le fichier de la commande
 * campagnes:sans-interlocuteur. Source unique de la liste pour les deux.
 *
 * « Sans interlocuteur » suit Client::interlocuteurRetenu(), la règle des
 * envois : aucun, supprimé, d'un autre client ou sans adresse valide.
 *
 * Par défaut, seules les campagnes planifiées, en cours ou en pause : une
 * campagne terminée ou annulée ne reçoit plus de mails de suivi.
 */
class CampagnesSansInterlocuteur
{
    public const SANS_COMMERCIAL = 'Sans commercial';

    private const STATUTS_EN_COURS = [CampaignStatus::PLANIFIE, CampaignStatus::ACTIF, CampaignStatus::PAUSE];

    /** Contacts cités par campagne dans le fichier : une grande entreprise peut en compter des dizaines. */
    private const MAX_CONTACTS = 8;

    /**
     * @param ?int $commercialId  limite aux campagnes de ce commercial
     * @return Collection<int, Campaign>  les échéances les plus proches d'abord
     */
    public function campagnes(?int $commercialId = null, bool $toutes = false): Collection
    {
        return $this->avecClient($commercialId, $toutes)
            ->reject(fn (Campaign $c) => $this->interlocuteurActuel($c))
            ->values();
    }

    /**
     * Campagnes de l'écran, avec ou sans interlocuteur (2026-11-09) : celui
     * qui existe doit rester visible et modifiable.
     *
     * @return Collection<int, Campaign>
     */
    public function enCours(?int $commercialId = null): Collection
    {
        return $this->avecClient($commercialId, false);
    }

    /** L'interlocuteur utilisé pour les envois, ou null. */
    public function interlocuteurActuel(Campaign $campagne): ?ClientContact
    {
        return $campagne->client->interlocuteurRetenu($campagne->interlocuteur);
    }

    public function libelleContact(ClientContact $contact): string
    {
        return $contact->name . ($contact->position ? ' — ' . $contact->position : '') . ' (' . mb_strtolower(trim($contact->email)) . ')';
    }

    /** @return Collection<int, Campaign> */
    private function avecClient(?int $commercialId, bool $toutes): Collection
    {
        return Campaign::query()
            ->with(['client.contacts', 'interlocuteur', 'commercial', 'user'])
            ->when($commercialId !== null, fn ($q) => $q->forCommercialUser($commercialId))
            ->when(!$toutes, fn ($q) => $q->whereIn('status', array_map(fn ($s) => $s->value, self::STATUTS_EN_COURS)))
            ->orderByRaw('end_date IS NULL')
            ->orderBy('end_date')
            ->get()
            ->filter(fn (Campaign $c) => $c->client && !$c->client->trashed())
            ->values();
    }

    /**
     * @return Collection<string, Collection<int, array{commercial: string, echeance: string, client: string, campagne: string, statut: string, reconduction: string, contacts: string}>>
     *         lignes par nom de commercial, les échéances les plus proches d'abord
     */
    public function parCommercial(bool $toutes = false): Collection
    {
        $exclues = $this->adressesExclues();

        return $this->campagnes(null, $toutes)
            ->map(fn (Campaign $c) => [
                'commercial'   => $this->nomCommercial($c),
                'echeance'     => $c->end_date?->format('d/m/Y') ?? '',
                'client'       => $c->client->name,
                'campagne'     => $c->name,
                'statut'       => $c->status?->label() ?? '',
                'reconduction' => $c->estAnnuelle() ? 'Oui' : 'Non',
                'contacts'     => $this->contactsConnus($c->client, $exclues),
            ])
            ->groupBy('commercial')
            ->sortKeys();
    }

    public function nomCommercial(Campaign $campagne): string
    {
        return $campagne->resolveCommercialContact()?->name ?: self::SANS_COMMERCIAL;
    }

    /**
     * Contacts qu'on peut choisir : adresse valide, ni morte ni désinscrite.
     * Le contact principal d'abord, puis les échanges les plus récents.
     *
     * @return Collection<int, ClientContact>
     */
    public function contactsProposes(Client $client, ?Collection $exclues = null): Collection
    {
        $exclues ??= $this->adressesExclues();

        return $client->contacts
            ->filter(fn ($k) => filter_var(trim((string) $k->email), FILTER_VALIDATE_EMAIL)
                && !$exclues->has(mb_strtolower(trim($k->email))))
            ->sortByDesc(fn ($k) => ($k->is_primary ? '1' : '0') . ($k->dernier_echange?->format('Y-m-d') ?? ''))
            ->values();
    }

    /** @return Collection<string, int>  adresses exclues, en minuscules, en clés */
    public function adressesExclues(): Collection
    {
        return DiffusionDesinscription::pluck('email')
            ->map(fn ($e) => mb_strtolower(trim((string) $e)))
            ->flip();
    }

    private function contactsConnus(Client $client, Collection $exclues): string
    {
        $contacts = $this->contactsProposes($client, $exclues);

        $cites = $contacts->take(self::MAX_CONTACTS)
            ->map(fn ($k) => trim($k->name . ' <' . mb_strtolower(trim($k->email)) . '>'))
            ->implode("\n");

        $reste = $contacts->count() - self::MAX_CONTACTS;

        return $reste > 0 ? $cites . "\n+ {$reste} autre(s)" : $cites;
    }
}
