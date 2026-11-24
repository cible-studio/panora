<?php

namespace App\Services\Campagnes;

use App\Models\Campaign;
use App\Models\CampaignPanelMouvement;
use App\Models\ExternalPanel;
use App\Models\Panel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * HISTORIQUE DES PANNEAUX D'UNE CAMPAGNE — SOURCE UNIQUE (2026-11-16).
 *
 * Besoin validé par l'utilisateur le 2026-11-16 :
 *   « Une campagne commence avec 10 panneaux et se termine avec 6. Plus
 *     tard on veut savoir quel panneau a été retiré, à quelle période,
 *     par qui et pour quelle raison. »
 *
 * TOUT mouvement passe par ici — y compris les retraits automatiques
 * (résolution de conflit, annulation de campagne). Un seul chemin qui
 * contourne cette classe, et un panneau disparaîtra sans explication :
 * c'est exactement ce qu'on cherche à éviter.
 *
 * ─── Règles tenues ici ───
 *  · La référence du panneau est FIGÉE à l'écriture : un panneau
 *    démantelé ou renommé plus tard ne rend pas l'historique illisible.
 *  · Une écriture d'historique ne doit JAMAIS faire échouer l'opération
 *    métier. Si la trace échoue, on la journalise et on continue : mieux
 *    vaut un retrait non tracé qu'un retrait impossible.
 *  · Les mouvements ne sont ni modifiés ni supprimés : c'est un journal.
 */
class MouvementsPanneaux
{
    /**
     * Un panneau interne entre dans la campagne.
     *
     * @param int[] $panelIds
     */
    public function ajoutInternes(
        Campaign $campaign,
        array $panelIds,
        string $motif = CampaignPanelMouvement::MOTIF_AJOUT_MANUEL,
        string $source = CampaignPanelMouvement::SOURCE_MANUEL,
        ?string $note = null,
        ?\Carbon\CarbonInterface $effetLe = null, // date réelle d'entrée (2026-11-24)
    ): void {
        $this->ecrireInternes(
            $campaign, $panelIds, CampaignPanelMouvement::AJOUT, $motif, $source, $note, $effetLe
        );
    }

    /**
     * Un panneau interne quitte la campagne.
     *
     * @param int[] $panelIds
     */
    public function retraitInternes(
        Campaign $campaign,
        array $panelIds,
        string $motif,
        string $source = CampaignPanelMouvement::SOURCE_MANUEL,
        ?string $note = null,
        ?\Carbon\CarbonInterface $effetLe = null, // date réelle de sortie (2026-11-24)
    ): void {
        $this->ecrireInternes(
            $campaign, $panelIds, CampaignPanelMouvement::RETRAIT, $motif, $source, $note, $effetLe
        );
    }

    /** @param int[] $externalPanelIds */
    public function ajoutExternes(
        Campaign $campaign,
        array $externalPanelIds,
        string $motif = CampaignPanelMouvement::MOTIF_AJOUT_MANUEL,
        string $source = CampaignPanelMouvement::SOURCE_MANUEL,
        ?string $note = null,
    ): void {
        $this->ecrireExternes(
            $campaign, $externalPanelIds, CampaignPanelMouvement::AJOUT, $motif, $source, $note
        );
    }

    /** @param int[] $externalPanelIds */
    public function retraitExternes(
        Campaign $campaign,
        array $externalPanelIds,
        string $motif,
        string $source = CampaignPanelMouvement::SOURCE_MANUEL,
        ?string $note = null,
    ): void {
        $this->ecrireExternes(
            $campaign, $externalPanelIds, CampaignPanelMouvement::RETRAIT, $motif, $source, $note
        );
    }

    /**
     * Tous les panneaux encore attachés quittent la campagne d'un coup.
     * Utilisé par l'annulation : sans cela, une campagne annulée laisse
     * ses panneaux « disparus » sans la moindre ligne d'historique.
     */
    public function retraitDeTous(
        Campaign $campaign,
        string $motif,
        string $source,
        ?string $note = null,
    ): void {
        try {
            $internes = $campaign->panels()->pluck('panels.id')->all();
            $externes = $campaign->externalPanels()->pluck('external_panels.id')->all();
        } catch (\Throwable $e) {
            Log::error('campagne.mouvements.lecture_echouee', [
                'campagne' => $campaign->id,
                'erreur'   => $e->getMessage(),
            ]);

            return;
        }

        if ($internes) {
            $this->retraitInternes($campaign, $internes, $motif, $source, $note);
        }
        if ($externes) {
            $this->retraitExternes($campaign, $externes, $motif, $source, $note);
        }
    }

    /**
     * L'historique d'une campagne, du plus récent au plus ancien.
     *
     * @return Collection<int, CampaignPanelMouvement>
     */
    public function pourCampagne(Campaign $campaign): Collection
    {
        return CampaignPanelMouvement::query()
            ->with(['auteur:id,name', 'panel:id,reference,name', 'externalPanel:id,code_panneau'])
            ->where('campaign_id', $campaign->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Combien de panneaux ont quitté la campagne sans y être revenus.
     * C'est le chiffre qui intéresse : « partie avec 10, finit avec 6 ».
     */
    public function nombreRetraits(Campaign $campaign): int
    {
        return (int) CampaignPanelMouvement::query()
            ->where('campaign_id', $campaign->id)
            ->where('sens', CampaignPanelMouvement::RETRAIT)
            ->count();
    }

    // ══════════════════════════════════════════════════════════════
    // ÉCRITURE
    // ══════════════════════════════════════════════════════════════

    /** @param int[] $panelIds */
    private function ecrireInternes(
        Campaign $campaign,
        array $panelIds,
        string $sens,
        string $motif,
        string $source,
        ?string $note,
        ?\Carbon\CarbonInterface $effetLe = null,
    ): void {
        $panelIds = array_values(array_unique(array_filter(array_map('intval', $panelIds))));
        if (!$panelIds) {
            return;
        }

        try {
            // withTrashed : on trace aussi le retrait d'un panneau qui
            // vient d'être démantelé — c'est même le cas le plus utile.
            $references = Panel::withTrashed()
                ->whereIn('id', $panelIds)
                ->pluck('reference', 'id');

            $lignes = [];
            foreach ($panelIds as $id) {
                // ⚠ array_merge, JAMAIS l'opérateur « + » (2026-11-16).
                // `ligne()` pose déjà panel_id = null comme valeur par
                // défaut, et « + » NE REMPLACE PAS une clé déjà présente à
                // gauche : le panel_id était donc perdu, tous les
                // mouvements sortaient sans identifiant, et l'écran les
                // prenait tous pour des panneaux de régie partenaire.
                $lignes[] = array_merge(
                    $this->ligne($campaign, $sens, $motif, $source, $note),
                    [
                        'panel_id'  => $id,
                        'reference' => $references[$id] ?? null,
                    ],
                    // Date réelle (2026-11-24) : seulement quand elle est
                    // donnée, pour ne rien changer aux autres écritures.
                    $effetLe ? ['effet_le' => $effetLe->toDateString()] : []
                );
            }

            CampaignPanelMouvement::insert($lignes);
            $this->journaliser($campaign, $sens, $motif, $source, count($lignes), 'interne');
        } catch (\Throwable $e) {
            $this->echec($campaign, $sens, $e);
        }
    }

    /** @param int[] $externalPanelIds */
    private function ecrireExternes(
        Campaign $campaign,
        array $externalPanelIds,
        string $sens,
        string $motif,
        string $source,
        ?string $note,
    ): void {
        $ids = array_values(array_unique(array_filter(array_map('intval', $externalPanelIds))));
        if (!$ids) {
            return;
        }

        try {
            $references = ExternalPanel::whereIn('id', $ids)->pluck('code_panneau', 'id');

            $lignes = [];
            foreach ($ids as $id) {
                // array_merge et non « + » : cf. la note dans
                // ecrireInternes(). Le défaut était le même ici.
                $lignes[] = array_merge(
                    $this->ligne($campaign, $sens, $motif, $source, $note),
                    [
                        'external_panel_id' => $id,
                        'reference'         => $references[$id] ?? null,
                    ]
                );
            }

            CampaignPanelMouvement::insert($lignes);
            $this->journaliser($campaign, $sens, $motif, $source, count($lignes), 'externe');
        } catch (\Throwable $e) {
            $this->echec($campaign, $sens, $e);
        }
    }

    /** Partie commune d'une ligne. `insert()` n'alimente pas les timestamps. */
    private function ligne(
        Campaign $campaign,
        string $sens,
        string $motif,
        string $source,
        ?string $note,
    ): array {
        $note = trim((string) $note);

        return [
            'campaign_id'       => $campaign->id,
            'panel_id'          => null,
            'external_panel_id' => null,
            'sens'              => $sens,
            'motif'             => $motif,
            'note'              => $note !== '' ? mb_substr($note, 0, 1000) : null,
            'source'            => $source,
            // null = mouvement déclenché hors session (commande planifiée).
            'user_id'           => auth()->id(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ];
    }

    private function journaliser(
        Campaign $campaign,
        string $sens,
        string $motif,
        string $source,
        int $nombre,
        string $type,
    ): void {
        Log::info('campagne.mouvement_panneaux', [
            'campagne' => $campaign->id,
            'sens'     => $sens,
            'motif'    => $motif,
            'source'   => $source,
            'type'     => $type,
            'nombre'   => $nombre,
            'auteur'   => auth()->id(),
        ]);
    }

    /**
     * Une trace manquante est un incident, pas un blocage : on ne refuse
     * jamais un retrait parce que son historique n'a pas pu s'écrire.
     */
    private function echec(Campaign $campaign, string $sens, \Throwable $e): void
    {
        Log::error('campagne.mouvement_panneaux.echec', [
            'campagne' => $campaign->id,
            'sens'     => $sens,
            'erreur'   => $e->getMessage(),
        ]);
    }
}
