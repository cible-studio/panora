<?php

namespace App\Services\Vagues;

use App\Enums\PoseTaskKind;
use App\Enums\PoseTaskStatus;
use App\Models\Campaign;
use App\Models\Pige;
use App\Models\PoseTask;
use App\Models\User;
use App\Models\Vague;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Vagues d'une campagne — source unique des règles (2026-11-04).
 *
 * Besoin : un client prend des panneaux pour l'année et change plusieurs
 * fois de message. Le MP renommait la campagne à chaque fois : l'historique
 * se perdait et les piges d'une opération étaient introuvables. Désormais
 * la campagne reste le chapeau (contrat, facture, nom) ; chaque changement
 * de message est une vague nommée.
 *
 * Règles validées par l'utilisateur le 2026-11-04 :
 *   · une vague concerne tous les panneaux ou une partie seulement ;
 *   · facturation unique pour l'année (rien ne change côté factures) ;
 *   · le nom est obligatoire à la création (proposé : « Vague N ») ;
 *   · Vague 1 naît au premier changement de message, avec le nom de la
 *     campagne ; toutes les poses déjà faites y sont rattachées ;
 *   · une retouche reste dans la vague de la pose réparée ;
 *   · une pose créée hors rechange (panneau ajouté en cours d'année)
 *     rejoint la vague en cours : elle reçoit le message du moment ;
 *   · le nom de la vague est montré aux techniciens (bon visuel à poser).
 *
 * Une campagne qui n'a jamais changé de message n'a aucune vague : rien
 * ne change pour elle (pas de bloc, pas de badge). Dès qu'elle en a, elle
 * en a au moins deux.
 */
final class VagueService
{
    /** Choix envoyés par les formulaires de rechange (champ « vague »). */
    public const NOUVELLE = 'nouvelle';
    public const EN_COURS = 'en_cours';

    /**
     * La base connaît-elle les vagues ? Code déployé avant la migration :
     * aucune requête sur une table absente, les poses se créent comme avant.
     */
    public static function disponible(): bool
    {
        return once(fn () => Schema::hasTable('vagues') && Schema::hasColumn('pose_tasks', 'vague_id'));
    }

    // ── Lecture ─────────────────────────────────────────────────────

    /** @return Collection<int, Vague> par numéro croissant */
    public function deLaCampagne(?int $campaignId): Collection
    {
        if (!$campaignId || !self::disponible()) {
            return collect();
        }

        return Vague::where('campaign_id', $campaignId)->orderBy('numero')->get();
    }

    /** La vague en cours : la plus récente. */
    public function enCours(?int $campaignId): ?Vague
    {
        if (!$campaignId || !self::disponible()) {
            return null;
        }

        return Vague::where('campaign_id', $campaignId)->orderByDesc('numero')->first();
    }

    /** Nom proposé pour la prochaine vague : « Vague 2 », « Vague 3 »… */
    public function nomPropose(?int $campaignId): string
    {
        $dernier = $campaignId && self::disponible()
            ? (int) Vague::where('campaign_id', $campaignId)->max('numero')
            : 0;

        return 'Vague ' . max(2, $dernier + 1);
    }

    // ── Création ────────────────────────────────────────────────────

    /**
     * Vague 1 de la campagne, créée au premier changement de message avec
     * le nom de la campagne. Toutes les poses déjà faites y sont rattachées
     * (sans événement de modèle : aucune notification, aucun recalcul).
     */
    public function initialiser(int $campaignId, ?User $auteur = null): Vague
    {
        $existante = Vague::where('campaign_id', $campaignId)->orderBy('numero')->first();
        if ($existante) {
            return $existante;
        }

        $campagne = Campaign::withTrashed()->find($campaignId);
        $debut = PoseTask::where('campaign_id', $campaignId)->min('scheduled_at');

        $vague1 = Vague::create([
            'campaign_id' => $campaignId,
            'numero'      => 1,
            'nom'         => trim((string) $campagne?->name) ?: 'Vague 1',
            'debut_le'    => $debut ? substr((string) $debut, 0, 10) : null,
            'created_by'  => $auteur?->id,
        ]);

        PoseTask::where('campaign_id', $campaignId)
            ->whereNull('vague_id')
            ->toBase()
            ->update(['vague_id' => $vague1->id]);

        return $vague1;
    }

    /** Nouvelle vague (Vague 1 créée d'abord si besoin). */
    public function creer(int $campaignId, string $nom, ?User $auteur = null, $debut = null): Vague
    {
        return DB::transaction(function () use ($campaignId, $nom, $auteur, $debut) {
            $this->initialiser($campaignId, $auteur);

            $numero = (int) Vague::where('campaign_id', $campaignId)->max('numero') + 1;

            return Vague::create([
                'campaign_id' => $campaignId,
                'numero'      => $numero,
                'nom'         => trim($nom) ?: 'Vague ' . $numero,
                'debut_le'    => $debut ? substr((string) $debut, 0, 10) : null,
                'created_by'  => $auteur?->id,
            ]);
        });
    }

    public function renommer(Vague $vague, string $nom): Vague
    {
        $vague->update(['nom' => trim($nom)]);

        return $vague;
    }

    // ── Rattachement des poses ──────────────────────────────────────

    /**
     * Vague d'un rechange ou d'une retouche, selon le choix du formulaire :
     *   « nouvelle » + vague_nom → nouvelle vague ;
     *   un identifiant           → cette vague (si elle est de la campagne) ;
     *   « en_cours » ou rien     → la vague en cours (ou aucune).
     * Une retouche garde la vague de la pose réparée.
     */
    public function pourRechange(PoseTask $source, PoseTaskKind $kind, array $data, ?User $auteur = null): ?int
    {
        if (!self::disponible() || !$source->campaign_id || !$kind->supersedesPrevious()) {
            return $source->vague_id;
        }

        return $this->resoudre($source->campaign_id, $data, $auteur);
    }

    // Rechange groupé : une seule vague par campagne pour tout le lot — cf.
    // PoseService::createRechangeBulk, qui reprend la vague du premier
    // rechange réussi de chaque campagne. La liste des poses peut mêler
    // plusieurs campagnes : une « nouvelle vague » naît alors dans chacune,
    // avec le même nom.

    private function resoudre(int $campaignId, array $data, ?User $auteur): ?int
    {
        $choix = (string) ($data['vague'] ?? '');

        if ($choix === self::NOUVELLE) {
            return $this->creer($campaignId, (string) ($data['vague_nom'] ?? ''), $auteur, $data['scheduled_at'] ?? null)->id;
        }
        if (ctype_digit($choix)) {
            $vague = Vague::where('campaign_id', $campaignId)->find((int) $choix);
            if ($vague) {
                return $vague->id;
            }
        }

        return $this->enCours($campaignId)?->id;
    }

    /**
     * Vague d'une pose créée sans vague précisée (PoseTask::creating) :
     * retouche → celle de la pose réparée ; sinon la vague en cours
     * (panneau ajouté en cours d'année : il reçoit le message du moment).
     */
    public function pourNouvellePose(PoseTask $pose): ?int
    {
        if (!self::disponible() || !$pose->campaign_id) {
            return null;
        }

        $kind = PoseTaskKind::tryFrom((string) ($pose->pose_kind ?? 'initial')) ?? PoseTaskKind::INITIAL;
        if ($pose->replaces_pose_task_id && !$kind->supersedesPrevious()) {
            return PoseTask::whereKey($pose->replaces_pose_task_id)->value('vague_id');
        }

        return $this->enCours($pose->campaign_id)?->id;
    }

    // ── Piges ───────────────────────────────────────────────────────

    /**
     * Restreint des piges à une vague : celles dont la pose est dans la
     * vague. Pour la Vague 1, aussi les anciennes piges sans pose de la
     * campagne (antérieures au rattachement automatique pige → pose).
     */
    public function restreindrePiges(Builder $query, Vague $vague): Builder
    {
        return $query->where(function ($q) use ($vague) {
            $q->whereHas('poseTask', fn ($p) => $p->where('vague_id', $vague->id));
            if ($vague->numero === 1) {
                $q->orWhere(fn ($legacy) => $legacy->whereNull('pose_task_id')->where('campaign_id', $vague->campaign_id));
            }
        });
    }

    /**
     * Bloc « Vagues » de la fiche campagne : par vague, panneaux concernés,
     * poses (hors annulées), poses réalisées, piges vérifiées.
     *
     * @return Collection<int, array{vague: Vague, panneaux: int, poses: int, realisees: int, piges: int}>
     */
    public function resume(Campaign $campaign): Collection
    {
        $vagues = $this->deLaCampagne($campaign->id);
        if ($vagues->isEmpty()) {
            return collect();
        }

        $poses = PoseTask::where('campaign_id', $campaign->id)
            ->whereNotNull('vague_id')
            ->where('status', '!=', PoseTaskStatus::CANCELLED->value)
            ->toBase()
            ->selectRaw('vague_id, COUNT(*) AS poses, COUNT(DISTINCT panel_id) AS panneaux, '
                . 'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS realisees', [PoseTaskStatus::COMPLETED->value])
            ->groupBy('vague_id')
            ->get()
            ->keyBy('vague_id');

        return $vagues->map(fn (Vague $v) => [
            'vague'     => $v,
            'panneaux'  => (int) ($poses[$v->id]->panneaux ?? 0),
            'poses'     => (int) ($poses[$v->id]->poses ?? 0),
            'realisees' => (int) ($poses[$v->id]->realisees ?? 0),
            'piges'     => $this->restreindrePiges(Pige::active()->where('status', 'verifie'), $v)->count(),
        ]);
    }

    // ── Formulaires ─────────────────────────────────────────────────

    /** Règles de validation des champs « vague » des trois rechanges. */
    public static function regles(Request $request): array
    {
        return [
            'vague'     => ['nullable', 'string', 'max:20'],
            'vague_nom' => [
                'nullable', 'string', 'max:150',
                Rule::requiredIf(fn () => $request->input('vague') === self::NOUVELLE
                    && $request->input('pose_kind', 'rechange') !== 'retouche'),
            ],
        ];
    }

    public const MESSAGES = [
        'vague_nom.required' => 'Donnez un nom à la nouvelle vague (ex. « Phase 2 »).',
        'vague_nom.max'      => 'Le nom de la vague ne doit pas dépasser 150 caractères.',
    ];
}
