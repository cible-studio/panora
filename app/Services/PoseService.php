<?php
// app/Services/PoseService.php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\PoseTaskStatus;
use App\Models\Campaign;
use App\Models\Panel;
use App\Models\Pige;
use App\Models\PoseTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PoseService
{
    // ══════════════════════════════════════════════════════════════
    // CREATE BATCH — Créer N tâches de pose en une transaction
    //
    // Remplace create() simple.
    // Gère : multi-panneaux, validation campagne, doublons, warnings.
    // ══════════════════════════════════════════════════════════════
    public function createBatch(array $data, User $creator): array
    {
        $campaign  = null;
        $panelIds  = $data['panel_ids'];

        // ── Vérifier la campagne si fournie ──────────────────────
        if (!empty($data['campaign_id'])) {
            $campaign = Campaign::with('panels:id')->find($data['campaign_id']);
            if (!$campaign) {
                return $this->error('Campagne introuvable.');
            }
            if ($campaign->status->isTerminal()) {
                return $this->error("Campagne « {$campaign->status->label()} » — impossible de créer des poses.");
            }

            // Vérifier que tous les panneaux sélectionnés appartiennent à la campagne
            $campaignPanelIds = $campaign->panels->pluck('id')->toArray();
            $notInCampaign    = array_diff($panelIds, $campaignPanelIds);
            if (!empty($notInCampaign)) {
                $refs = Panel::whereIn('id', $notInCampaign)->pluck('reference')->join(', ');
                return $this->error("Panneau(x) non associé(s) à cette campagne : {$refs}");
            }
        } else {
            // Sans campagne : vérifier que les panneaux existent
            $found = Panel::whereIn('id', $panelIds)->whereNull('deleted_at')->count();
            if ($found !== count($panelIds)) {
                return $this->error("Un ou plusieurs panneaux sont introuvables ou supprimés.");
            }
        }

        return DB::transaction(function () use ($data, $panelIds, $campaign, $creator) {
            $created  = [];
            $warnings = [];

            foreach ($panelIds as $panelId) {
                $panel = Panel::find($panelId);
                if (!$panel) continue;

                // Vérifier si une tâche non-annulée existe déjà pour ce panneau/campagne
                $existingQuery = PoseTask::where('panel_id', $panelId)
                    ->whereNotIn('status', [PoseTaskStatus::CANCELLED->value]);
                if ($campaign) {
                    $existingQuery->where('campaign_id', $campaign->id);
                }
                $existing = $existingQuery->first();

                if ($existing) {
                    $warnings[] = "Panneau {$panel->reference} : une tâche {$existing->status} existe déjà (ignoré).";
                    continue;
                }

                $task = PoseTask::create([
                    'panel_id'         => $panelId,
                    'campaign_id'      => $campaign?->id,
                    'assigned_user_id' => $data['assigned_user_id'] ?? null,
                    'team_name'        => $data['team_name'] ?? null,
                    'scheduled_at'     => $data['scheduled_at'],
                    'status'           => $data['status'] ?? PoseTaskStatus::PLANNED->value,
                    'notes'            => $data['notes'] ?? null,
                ]);

                // Génération du token public dès la création (pour pouvoir envoyer
                // immédiatement le lien au technicien par WhatsApp).
                $task->ensurePublicToken();

                $created[] = $task->id;

                Log::info('pose_task.created', [
                    'task_id'     => $task->id,
                    'panel_id'    => $panelId,
                    'campaign_id' => $campaign?->id,
                    'created_by'  => $creator->id,
                ]);
            }

            // Envoi WhatsApp (best-effort, après commit pour ne pas bloquer la trans)
            // — fait en dehors de la transaction pour ne pas la garder ouverte le
            //   temps des appels HTTP externes.
            //
            // Stratégie digest : si 2+ tâches sont créées en lot pour le même
            // (technicien, campagne), on envoie UN seul message récapitulatif
            // avec un lien unique (page pige terrain de la campagne) plutôt que
            // 100 messages séparés. Pour 1 seule tâche, on garde le message
            // détaillé classique avec le lien public dédié.
            if (!empty($created)) {
                $tasks = PoseTask::with(['panel:id,reference,name,adresse,quartier,commune_id', 'panel.commune:id,name', 'technicien:id,name,whatsapp_number'])
                    ->whereIn('id', $created)
                    ->get();

                $groups = $tasks->groupBy(fn($t) => ($t->assigned_user_id ?? 'none') . ':' . ($t->campaign_id ?? 'none'));

                foreach ($groups as $group) {
                    if ($group->count() === 1) {
                        $this->notifyTechnicianOnWhatsApp($group->first());
                    } else {
                        $this->notifyTechnicianBatch($group);
                    }
                }
            }

            if (empty($created) && !empty($warnings)) {
                return $this->error('Aucune tâche créée. ' . implode(' ', $warnings));
            }

            return [
                'ok'       => true,
                'count'    => count($created),
                'task_ids' => $created,
                'warnings' => $warnings,
            ];
        });
    }

    // ══════════════════════════════════════════════════════════════
    // CREATE RECHANGE — Nouvelle pose sur un panneau déjà posé
    //
    // Contexte métier (2026-08-04) : sur une campagne longue, le
    // client change de créa périodiquement. À chaque changement, une
    // nouvelle pose physique doit être réalisée sur le même panneau.
    // Cette méthode :
    //   1. crée une nouvelle PoseTask liée au même (panel, campaign)
    //      que $sourcePose, avec pose_kind = 'rechange' et
    //      replaces_pose_task_id = sourcePose.id
    //   2. marque sourcePose.replaced_at = now() — décapage
    //      intermédiaire implicite (l'ancienne affiche a bien été
    //      retirée avant que la nouvelle soit posée)
    //   3. laisse sourcePose.status = 'realisee' (audit préservé —
    //      la pose initiale A ÉTÉ réalisée, seule sa durée de vie
    //      est bornée par replaced_at)
    //
    // Contrainte : la pose source doit être 'realisee'. Un rechange
    // sur une pose encore planifiée n'a pas de sens métier (on modifie
    // la pose planifiée à la place).
    //
    // Ne passe PAS par createBatch (qui bloque explicitement les
    // poses en doublon) — c'est le point clé.
    //
    // @param PoseTask $sourcePose  Pose à remplacer (doit être realisee)
    // @param array{scheduled_at:string, assigned_user_id?:int, team_name?:string, notes?:string, pose_kind?:string} $data
    // @param User $creator  Admin qui déclenche le rechange
    // @return array{ok:bool, task?:PoseTask, error?:string}
    // ══════════════════════════════════════════════════════════════
    public function createRechange(PoseTask $sourcePose, array $data, User $creator): array
    {
        if ($sourcePose->status !== PoseTaskStatus::COMPLETED->value) {
            return $this->error(
                "La pose source doit être réalisée avant de créer un rechange. "
                . "Statut actuel : {$sourcePose->status}."
            );
        }

        if ($sourcePose->isReplaced()) {
            return $this->error(
                "Cette pose a déjà été remplacée le "
                . $sourcePose->replaced_at?->format('d/m/Y H:i')
                . ". Créez un rechange à partir de la pose active la plus récente."
            );
        }

        // Vérif campagne active — un rechange sur une campagne terminée
        // ou annulée n'a pas de sens (le panneau attend son décapage).
        $campaign = $sourcePose->campaign_id
            ? Campaign::withTrashed()->find($sourcePose->campaign_id)
            : null;
        $blocker = $this->resolveCampaignBlocker($campaign);
        if ($blocker) {
            return $this->error($blocker);
        }

        $kind = \App\Enums\PoseTaskKind::tryFrom($data['pose_kind'] ?? 'rechange')
             ?? \App\Enums\PoseTaskKind::RECHANGE;

        return DB::transaction(function () use ($sourcePose, $data, $kind, $creator) {
            // 1. Créer la nouvelle pose (chaînée à la source)
            $newTask = PoseTask::create([
                'panel_id'              => $sourcePose->panel_id,
                'campaign_id'           => $sourcePose->campaign_id,
                'assigned_user_id'      => $data['assigned_user_id'] ?? null,
                'team_name'             => $data['team_name'] ?? null,
                'scheduled_at'          => $data['scheduled_at'],
                'status'                => PoseTaskStatus::PLANNED->value,
                'notes'                 => $data['notes'] ?? null,
                'pose_kind'             => $kind->value,
                'replaces_pose_task_id' => $sourcePose->id,
            ]);
            $newTask->ensurePublicToken();

            // 2. Marquer l'ancienne comme remplacée SI le kind
            //    supersède (rechange = oui, retouche = non car l'ancienne
            //    affiche reste en place, on la répare simplement).
            if ($kind->supersedesPrevious()) {
                $sourcePose->forceFill(['replaced_at' => now()])->save();
            }

            Log::info('pose_task.rechange_created', [
                'source_task_id' => $sourcePose->id,
                'new_task_id'    => $newTask->id,
                'panel_id'       => $sourcePose->panel_id,
                'campaign_id'    => $sourcePose->campaign_id,
                'kind'           => $kind->value,
                'created_by'     => $creator->id,
            ]);

            // 3. Notif WhatsApp technicien (mêmes règles que la pose
            //    initiale — le tech doit être prévenu de l'intervention).
            try {
                $newTask->load(['panel:id,reference,name,adresse,quartier,commune_id', 'panel.commune:id,name', 'technicien:id,name,whatsapp_number']);
                $this->notifyTechnicianOnWhatsApp($newTask);
            } catch (\Throwable $e) {
                Log::warning('pose_task.rechange.notify_failed', [
                    'task_id' => $newTask->id, 'err' => $e->getMessage(),
                ]);
            }

            return ['ok' => true, 'task' => $newTask];
        });
    }

    // ══════════════════════════════════════════════════════════════
    // CREATE RECHANGE BULK — 1 rechange × N poses en un seul geste
    //
    // Ajout 2026-08-08 : le workflow métier réel est massif (le client
    // demande "change l'affiche sur toute ma campagne", donc 30-80 poses
    // en une fois, pas une par une). Cette méthode itère createRechange()
    // sur chaque source, partage les mêmes paramètres (date, tech, notes,
    // kind), et agrège les erreurs par pose pour un feedback UX précis.
    //
    // Retour :
    //   ok: true si au moins 1 rechange créé
    //   created: int (rechanges effectivement créés)
    //   skipped: int (poses ignorées — status non-realisee, déjà remplacée,
    //                 campagne bloquée, etc.)
    //   task_ids: [ids des nouvelles PoseTask créées]
    //   warnings: ["Panneau XXX-01 : raison du skip", ...]
    //
    // @param int[]  $sourcePoseIds  IDs des poses source à rechanger
    // @param array{scheduled_at:string, assigned_user_id?:int, team_name?:string, notes?:string, pose_kind?:string} $data
    // @param User   $creator
    // ══════════════════════════════════════════════════════════════
    public function createRechangeBulk(array $sourcePoseIds, array $data, User $creator): array
    {
        $sourcePoseIds = array_values(array_filter(array_map('intval', $sourcePoseIds)));
        if (empty($sourcePoseIds)) {
            return $this->error('Aucune pose sélectionnée.');
        }

        // Load en 1 requête toutes les sources avec leur panel (utile pour
        // messages d'erreur explicites côté UI : "Panneau ABJ-01 : …").
        $sources = PoseTask::with('panel:id,reference')
            ->whereIn('id', $sourcePoseIds)
            ->get()
            ->keyBy('id');

        $created  = [];
        $warnings = [];

        foreach ($sourcePoseIds as $id) {
            $source = $sources->get($id);
            if (!$source) {
                $warnings[] = "Pose #{$id} : introuvable (ignorée).";
                continue;
            }

            $res = $this->createRechange($source, $data, $creator);
            if ($res['ok']) {
                $created[] = $res['task']->id;
            } else {
                $ref = $source->panel?->reference ?? "#{$source->id}";
                $warnings[] = "Panneau {$ref} : " . ($res['error'] ?? 'échec inconnu');
            }
        }

        Log::info('pose_task.rechange_bulk', [
            'requested'  => count($sourcePoseIds),
            'created'    => count($created),
            'skipped'    => count($warnings),
            'created_by' => $creator->id,
        ]);

        return [
            'ok'       => count($created) > 0,
            'created'  => count($created),
            'skipped'  => count($warnings),
            'task_ids' => $created,
            'warnings' => $warnings,
            // Si rien n'a été créé et qu'il y a des warnings, on renvoie
            // aussi 'error' pour affichage inline (cohérence avec les
            // autres méthodes du service qui utilisent 'error').
            'error'    => count($created) === 0 && !empty($warnings)
                ? 'Aucun rechange créé. ' . implode(' | ', array_slice($warnings, 0, 3))
                : null,
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // UPDATE
    // ══════════════════════════════════════════════════════════════
    public function update(PoseTask $task, array $data, User $updater): array
    {
        if ($task->status === PoseTaskStatus::COMPLETED->value) {
            return $this->error('Impossible de modifier une tâche déjà réalisée.');
        }
        if ($task->status === PoseTaskStatus::CANCELLED->value) {
            return $this->error('Impossible de modifier une tâche annulée.');
        }

        // Si campagne change, vérifier que le panneau appartient à la nouvelle campagne
        if (!empty($data['campaign_id']) && $data['campaign_id'] != $task->campaign_id) {
            $campaign = Campaign::with('panels:id')->find($data['campaign_id']);
            if ($campaign && !$campaign->panels->contains('id', $data['panel_id'] ?? $task->panel_id)) {
                return $this->error("Ce panneau n'appartient pas à la campagne sélectionnée.");
            }
        }

        // Garde métier : on n'autorise PLANNED → IN_PROGRESS que si la
        // campagne est en ACTIF ou POSE. Bloque toute pose terrain sur
        // une campagne mise en pause, annulée, terminée ou supprimée.
        $oldStatus = $task->status;
        $newStatus = $data['status'] ?? $oldStatus;
        if ($newStatus === PoseTaskStatus::IN_PROGRESS->value
            && $oldStatus === PoseTaskStatus::PLANNED->value) {
            $campaignId = $data['campaign_id'] ?? $task->campaign_id;
            $campaign = $campaignId ? Campaign::withTrashed()->find($campaignId) : null;
            $blocker = $this->resolveCampaignBlocker($campaign);
            if ($blocker) {
                return $this->error($blocker);
            }
        }

        $old = $oldStatus;
        $task->update([
            'campaign_id'      => $data['campaign_id'] ?? $task->campaign_id,
            'panel_id'         => $data['panel_id'] ?? $task->panel_id,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'team_name'        => $data['team_name'] ?? null,
            'scheduled_at'     => $data['scheduled_at'],
            'status'           => $data['status'],
            'notes'            => $data['notes'] ?? null,
        ]);

        Log::info('pose_task.updated', ['task_id' => $task->id, 'old_status' => $old, 'new_status' => $task->status, 'by' => $updater->id]);
        return ['ok' => true, 'task' => $task];
    }

    // ══════════════════════════════════════════════════════════════
    // COMPLETE
    // ══════════════════════════════════════════════════════════════
    public function complete(PoseTask $task, User $actor): array
    {
        if ($task->status === PoseTaskStatus::COMPLETED->value) {
            return $this->error('Cette tâche est déjà réalisée.');
        }
        if ($task->status === PoseTaskStatus::CANCELLED->value) {
            return $this->error('Impossible de réaliser une tâche annulée.');
        }

        // Garde campagne : si la campagne mère a été mise en pause ou
        // close entre la planification et la pose terrain, on bloque.
        $campaign = $task->campaign_id ? Campaign::withTrashed()->find($task->campaign_id) : null;
        $blocker = $this->resolveCampaignBlocker($campaign);
        if ($blocker) {
            return $this->error($blocker);
        }

        // Lock optimiste anti double-clic
        $updated = PoseTask::where('id', $task->id)
            ->whereNotIn('status', [PoseTaskStatus::COMPLETED->value, PoseTaskStatus::CANCELLED->value])
            ->update(['status' => PoseTaskStatus::COMPLETED->value, 'done_at' => now()]);

        if (!$updated) {
            return $this->error('Cette tâche a déjà été traitée.');
        }

        // Vérifier si une pige existe
        $warning = null;
        if ($task->campaign_id) {
            $hasPige = Pige::where('panel_id', $task->panel_id)
                ->where('campaign_id', $task->campaign_id)
                ->exists();
            if (!$hasPige) {
                $warning = "Aucune pige photo pour ce panneau. Pensez à uploader une preuve d'affichage.";
            }
        }

        Log::info('pose_task.completed', ['task_id' => $task->id, 'by' => $actor->id, 'has_pige' => !$warning]);
        return ['ok' => true, 'warning' => $warning];
    }

    // ══════════════════════════════════════════════════════════════
    // BULK UPDATE — actions groupées depuis la liste poses
    //
    // Action acceptées :
    //   - assign_tech     : value = user_id (technicien) | null
    //   - rename_team     : value = string (nom équipe) | null
    //   - change_status   : value = 'planifiee'|'en_cours'|'realisee'|'annulee'
    //   - reschedule      : value = date (Y-m-d ou Y-m-d H:i)
    //
    // Garde : on ignore silencieusement les tâches déjà terminées
    // (réalisée/annulée) pour rester idempotent et éviter de défaire
    // des résolutions. Le compte renvoyé permet d'afficher à l'admin
    // ce qui a effectivement été appliqué.
    //
    // @return array{ok:bool, updated:int, skipped:int, error?:string}
    // ══════════════════════════════════════════════════════════════
    public function bulkUpdate(array $taskIds, string $action, $value, User $actor): array
    {
        $taskIds = array_values(array_filter(array_map('intval', $taskIds)));
        if (empty($taskIds)) {
            return ['ok' => false, 'updated' => 0, 'skipped' => 0, 'error' => 'Aucune tâche sélectionnée.'];
        }

        // Normalisation / validation du payload selon l'action.
        $payload = [];
        switch ($action) {
            case 'assign_tech':
                $payload['assigned_user_id'] = $value === null || $value === '' ? null : (int) $value;
                if ($payload['assigned_user_id'] !== null
                    && !\App\Models\User::where('id', $payload['assigned_user_id'])
                        ->where('role', 'technique')->exists()) {
                    return $this->bulkError('Le technicien sélectionné est introuvable ou n\'a pas le bon rôle.');
                }
                break;

            case 'rename_team':
                $payload['team_name'] = $value === null || $value === '' ? null : mb_substr((string) $value, 0, 100);
                break;

            case 'change_status':
                $allowed = [
                    PoseTaskStatus::PLANNED->value,
                    PoseTaskStatus::IN_PROGRESS->value,
                    PoseTaskStatus::CANCELLED->value,
                    // COMPLETED interdit en bulk : la pose terrain doit passer
                    // par le flow normal (photo / pige / lien public). Sinon
                    // on contournerait tous les contrôles métier.
                ];
                if (!in_array($value, $allowed, true)) {
                    return $this->bulkError('Statut invalide pour une action groupée (réalisée se fait à l\'unité).');
                }
                $payload['status'] = $value;
                break;

            case 'reschedule':
                try {
                    $d = \Carbon\Carbon::parse($value);
                    $payload['scheduled_at'] = $d->toDateTimeString();
                } catch (\Throwable) {
                    return $this->bulkError('Date invalide.');
                }
                break;

            default:
                return $this->bulkError('Action inconnue : ' . $action);
        }

        // Récupération + filtre des tâches non-terminales. On charge la
        // campagne pour vérifier la garde campagne (PAUSE / supprimée) sur
        // les passages vers en_cours.
        $tasks = PoseTask::whereIn('id', $taskIds)
            ->whereNotIn('status', [
                PoseTaskStatus::COMPLETED->value,
                PoseTaskStatus::CANCELLED->value,
            ])
            ->get();

        $updated = 0;
        $skipped = count($taskIds) - $tasks->count(); // pré-filtre déjà terminées

        $assignedTasks = collect(); // mémorisées pour la notif batch
        foreach ($tasks as $task) {
            // Garde campagne pour passage à IN_PROGRESS.
            if ($action === 'change_status'
                && $value === PoseTaskStatus::IN_PROGRESS->value
                && $task->status === PoseTaskStatus::PLANNED->value) {
                $campaign = $task->campaign_id ? Campaign::withTrashed()->find($task->campaign_id) : null;
                if ($this->resolveCampaignBlocker($campaign)) {
                    $skipped++;
                    continue;
                }
            }

            $task->update($payload);
            $updated++;
            $assignedTasks->push($task);
        }

        // ── Notification batch — UN SEUL lien par technicien × campagne ──
        // Quand on assigne 1 tech à N tâches (cas typique : MP sélectionne
        // 50 panneaux d'une campagne et clique "Appliquer"), on envoie un
        // SEUL message WhatsApp avec un lien campagne unique vers la page
        // pige terrain qui liste tous les panneaux. Sans ce regroupement,
        // le tech recevrait 50 notifs et 50 liens distincts — inutilisable
        // dans la pratique (cf. retour terrain).
        $notified = 0;
        if ($action === 'assign_tech'
            && !empty($payload['assigned_user_id'])
            && $assignedTasks->isNotEmpty()) {
            // On rafraîchit avec les relations utiles à la notif (tech +
            // panel + campaign + commune) pour éviter un N+1.
            $assignedTasks = PoseTask::with([
                'technicien:id,name,whatsapp_number',
                'panel:id,reference,name,adresse,quartier,commune_id',
                'panel.commune:id,name',
                'campaign:id,name,pige_token,pige_token_created_at',
            ])->whereIn('id', $assignedTasks->pluck('id'))->get();

            // Groupage : 1 message par (technicien × campagne). Si une
            // tâche n'a pas de campagne (pose ad-hoc), elle tombe dans
            // un groupe "sans-campagne" avec notif individuelle classique.
            $groups = $assignedTasks->groupBy(function ($t) {
                return $t->assigned_user_id . '|' . ($t->campaign_id ?? 'adhoc');
            });

            foreach ($groups as $group) {
                if ($group->count() > 1 && $group->first()->campaign_id) {
                    // Batch : lien campagne unique
                    $this->notifyTechnicianBatch($group);
                    $notified += $group->count();
                } else {
                    // Cas mono-tâche ou ad-hoc : notif individuelle
                    foreach ($group as $t) {
                        $this->notifyTechnicianOnWhatsApp($t);
                        $notified++;
                    }
                }
            }
        }

        Log::info('pose_task.bulk_updated', [
            'action'   => $action,
            'value'    => $value,
            'updated'  => $updated,
            'skipped'  => $skipped,
            'notified' => $notified,
            'by'       => $actor->id,
        ]);

        return [
            'ok'       => true,
            'updated'  => $updated,
            'skipped'  => $skipped,
            'notified' => $notified,
        ];
    }

    private function bulkError(string $msg): array
    {
        return ['ok' => false, 'updated' => 0, 'skipped' => 0, 'error' => $msg];
    }

    // ══════════════════════════════════════════════════════════════
    // STATS
    // ══════════════════════════════════════════════════════════════
    public function getStats(): array
    {
        $raw = PoseTask::selectRaw("
            SUM(CASE WHEN status = 'planifiee' THEN 1 ELSE 0 END) as planifiee,
            SUM(CASE WHEN status = 'en_cours'  THEN 1 ELSE 0 END) as en_cours,
            SUM(CASE WHEN status = 'realisee'  THEN 1 ELSE 0 END) as realisee,
            SUM(CASE WHEN status = 'annulee'   THEN 1 ELSE 0 END) as annulee
        ")->first();

        return [
            'planifiee' => (int) ($raw->planifiee ?? 0),
            'en_cours'  => (int) ($raw->en_cours  ?? 0),
            'realisee'  => (int) ($raw->realisee  ?? 0),
            'annulee'   => (int) ($raw->annulee   ?? 0),
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // OVERDUE
    // ══════════════════════════════════════════════════════════════
    public function getOverdueTasks()
    {
        return PoseTask::where('status', PoseTaskStatus::PLANNED->value)
            ->where('scheduled_at', '<', PoseTask::lateThreshold())
            ->with(['panel:id,reference,name', 'campaign:id,name'])
            ->orderBy('scheduled_at')
            ->limit(20)
            ->get();
    }

    // ══════════════════════════════════════════════════════════════
    // NOTIFICATION WHATSAPP — best effort, n'échoue jamais le flux
    //
    // $options accepte :
    //   - preamble : texte ajouté en tête du message (ex: motif rejet
    //     pige pour re-pige auto). Optionnel.
    // ══════════════════════════════════════════════════════════════
    public function notifyTechnicianOnWhatsApp(PoseTask $task, array $options = []): bool
    {
        if (!config('services.whatsapp.enabled', true)) {
            return false;
        }

        $tech = $task->technicien;
        if (!$tech || empty($tech->whatsapp_number)) {
            Log::info('pose_task.whatsapp.skipped_no_number', [
                'task_id' => $task->id,
                'tech_id' => $tech?->id,
            ]);
            return false;
        }

        // ── Lien envoyé au tech ──────────────────────────────────────
        // Convention : on envoie TOUJOURS le lien d'espace tech personnel
        // (et non plus le lien direct vers la pose individuelle). Le tech
        // a ainsi UNE URL stable à mémoriser/bookmarker, qui montre
        // toujours toutes ses poses à jour, quelque soit la quantité.
        //
        // L'ancien `$task->publicUrl()` (= /pige/{public_token}) reste
        // utilisé en interne par l'admin pour le suivi technique d'UNE
        // pose précise, mais n'est plus envoyé au tech via WhatsApp.
        $task->ensurePublicToken(); // token PoseTask conservé pour usage interne
        $url       = route('tech.space', $tech->ensureTechPublicToken());
        $panel     = $task->panel;
        $commune   = $panel?->commune?->name ?? '—';
        $address   = trim(($panel?->adresse ?? '') . ($panel?->quartier ? ' · ' . $panel->quartier : ''));
        $scheduled = $task->scheduled_at?->format('d/m/Y à H:i') ?? '—';

        // Préambule optionnel (utilisé pour la re-pige après rejet : motif
        // affiché en tête, suivi du lien pour reprendre la photo).
        $preamble = !empty($options['preamble'])
            ? rtrim($options['preamble']) . "\n\n"
            : '';

        $message = $preamble
                 . "Bonjour {$tech->name},\n\n"
                 . "Une nouvelle pose vous est assignée par CIBLE CI :\n\n"
                 . "• Panneau : " . ($panel->reference ?? '—') . " — " . ($panel->name ?? '') . "\n"
                 . ($address ? "• Adresse : {$address}\n" : '')
                 . "• Commune : {$commune}\n"
                 . "• Prévue : {$scheduled}\n"
                 . ($task->campaign ? "• Campagne : {$task->campaign->name}\n" : '')
                 . "\nRetrouvez TOUTES vos poses en cours sur votre espace personnel :\n{$url}\n\n"
                 . "Merci.\nCIBLE CI";

        $context = [
            'action'  => 'pose.assignment',
            'task_id' => $task->id,
            'tech_id' => $tech->id,
        ];

        // Mode prod (template Meta approuvé) : passer ContentSid + variables
        // au service. Sinon (sandbox / dev) : envoi free-form du message.
        // Le template attendu côté Twilio (5 variables) :
        //   {{1}} = nom technicien
        //   {{2}} = référence panneau
        //   {{3}} = adresse / commune
        //   {{4}} = date/heure prévue
        //   {{5}} = URL publique de mise à jour
        $contentSid = config('services.twilio.content_sid_pose_assignment');
        if (!empty($contentSid)) {
            $context['twilio_content_sid']  = $contentSid;
            $context['twilio_content_vars'] = [
                '1' => $tech->name,
                '2' => $panel->reference ?? '—',
                '3' => $address !== '' ? $address : $commune,
                '4' => $scheduled,
                '5' => (string) $url,
            ];
        }

        $sent = app(WhatsAppService::class)->send(
            $tech->whatsapp_number,
            $message,
            $context,
        );

        if ($sent) {
            $task->forceFill(['whatsapp_sent_at' => now()])->saveQuietly();
            \App\Http\Controllers\TechSpaceController::invalidateCache($tech->id);
        }

        return $sent;
    }

    /**
     * Envoie 1 seul message digest pour un lot de tâches pose assignées au
     * même technicien et concernant la même campagne. Le lien pointe vers
     * la page pige terrain de la campagne (génération automatique du token
     * si pas encore créé) — le technicien y voit tous les panneaux d'un
     * coup, photos, GPS, statuts pose, sans avoir 100 URLs distinctes.
     *
     * Si pas de campagne (poses ad-hoc), on envoie quand même un récap
     * sans lien — l'admin recevra du feedback côté UI sur quels panneaux.
     */
    public function notifyTechnicianBatch(\Illuminate\Support\Collection $tasks): bool
    {
        if (!config('services.whatsapp.enabled', true) || $tasks->isEmpty()) {
            return false;
        }

        $first = $tasks->first();
        $tech  = $first->technicien;

        if (!$tech || empty($tech->whatsapp_number)) {
            Log::info('pose_task.whatsapp.batch.skipped_no_number', [
                'count'   => $tasks->count(),
                'tech_id' => $tech?->id,
            ]);
            return false;
        }

        $campaign = $first->campaign;
        $count    = $tasks->count();
        $earliest = $tasks->min('scheduled_at');
        $latest   = $tasks->max('scheduled_at');
        $period   = $earliest && $latest && $earliest->ne($latest)
            ? $earliest->format('d/m') . ' → ' . $latest->format('d/m/Y')
            : ($earliest?->format('d/m/Y à H:i') ?? '—');

        // Aperçu des 3 premiers panneaux (référence + commune) — assez
        // pour donner du contexte sans surcharger le message WhatsApp.
        $preview = $tasks->take(3)
            ->map(fn($t) => '• ' . ($t->panel?->reference ?? '?')
                . ' — ' . ($t->panel?->commune?->name ?? '—'))
            ->join("\n");
        $rest = $count > 3 ? "\n• … et " . ($count - 3) . " autre" . ($count - 3 > 1 ? 's' : '') : '';

        // Lien unique : ESPACE TECHNICIEN PERSONNEL (toutes campagnes
        // confondues, mais filtré sur les poses de ce tech). Remplace
        // l'ancien lien /pige/{campaign.pige_token} qui exposait les
        // panneaux des autres techs sur la même campagne.
        $url = route('tech.space', $tech->ensureTechPublicToken());

        $message = "Bonjour {$tech->name},\n\n"
                 . "CIBLE CI vous assigne {$count} pose" . ($count > 1 ? 's' : '') . " :\n"
                 . ($campaign ? "• Campagne : {$campaign->name}\n" : '')
                 . "• Période : {$period}\n\n"
                 . "Aperçu :\n{$preview}{$rest}\n\n"
                 . ($url
                    ? "Toutes les tâches + photos sur :\n{$url}\n\n"
                    : "Détails à venir par votre superviseur.\n\n")
                 . "Merci.\nCIBLE CI";

        $context = [
            'action'   => 'pose.assignment.batch',
            'count'    => $count,
            'tech_id'  => $tech->id,
            'campaign' => $campaign?->id,
        ];

        // Template Meta dédié batch — fallback free-form si pas configuré.
        // Variables :
        //   {{1}} = nom technicien
        //   {{2}} = nombre tâches
        //   {{3}} = nom campagne (ou "—")
        //   {{4}} = période
        //   {{5}} = URL unique
        $contentSid = config('services.twilio.content_sid_pose_batch');
        if (!empty($contentSid) && $url !== null) {
            $context['twilio_content_sid']  = $contentSid;
            $context['twilio_content_vars'] = [
                '1' => $tech->name,
                '2' => (string) $count,
                '3' => $campaign?->name ?? '—',
                '4' => $period,
                '5' => $url,
            ];
        }

        $sent = app(WhatsAppService::class)->send($tech->whatsapp_number, $message, $context);

        if ($sent) {
            // Marque toutes les tâches du lot comme notifiées en une UPDATE
            PoseTask::whereIn('id', $tasks->pluck('id'))
                ->update(['whatsapp_sent_at' => now()]);
            \App\Http\Controllers\TechSpaceController::invalidateCache($tech->id);
        }

        return $sent;
    }

    // ── Helpers ───────────────────────────────────────────────────
    private function error(string $msg): array { return ['ok' => false, 'error' => $msg]; }

    /**
     * Retourne un message d'erreur si la campagne empêche l'exécution
     * d'une pose terrain (PAUSE / TERMINE / ANNULE / supprimée), ou null
     * si tout est OK. Source unique de vérité pour update() et complete().
     */
    private function resolveCampaignBlocker(?Campaign $campaign): ?string
    {
        if (!$campaign) {
            return 'Pose orpheline : la campagne associée a été supprimée.';
        }
        if ($campaign->trashed()) {
            return 'La campagne associée a été supprimée — pose impossible.';
        }
        $statusValue = $campaign->status?->value ?? (string) $campaign->status;
        return match ($statusValue) {
            'pause'   => 'Campagne en pause — reprenez la campagne avant de démarrer la pose.',
            'annule'  => 'Campagne annulée — pose impossible.',
            'termine' => 'Campagne terminée — pose impossible.',
            default   => null,
        };
    }
}