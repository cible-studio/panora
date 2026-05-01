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
            //   temps des appels HTTP externes
            if (!empty($created)) {
                $tasks = PoseTask::with(['panel:id,reference,name,adresse,quartier,commune_id', 'panel.commune:id,name', 'technicien:id,name,whatsapp_number'])
                    ->whereIn('id', $created)
                    ->get();
                foreach ($tasks as $t) {
                    $this->notifyTechnicianOnWhatsApp($t);
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

        $old = $task->status;
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
            ->where('scheduled_at', '<', now())
            ->with(['panel:id,reference,name', 'campaign:id,name'])
            ->orderBy('scheduled_at')
            ->limit(20)
            ->get();
    }

    // ══════════════════════════════════════════════════════════════
    // NOTIFICATION WHATSAPP — best effort, n'échoue jamais le flux
    // ══════════════════════════════════════════════════════════════
    public function notifyTechnicianOnWhatsApp(PoseTask $task): bool
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

        $task->ensurePublicToken();
        $url       = $task->publicUrl();
        $panel     = $task->panel;
        $commune   = $panel?->commune?->name ?? '—';
        $address   = trim(($panel?->adresse ?? '') . ($panel?->quartier ? ' · ' . $panel->quartier : ''));
        $scheduled = $task->scheduled_at?->format('d/m/Y à H:i') ?? '—';

        $message = "Bonjour {$tech->name},\n\n"
                 . "Une tâche de pose vous est assignée par CIBLE CI :\n\n"
                 . "• Panneau : " . ($panel->reference ?? '—') . " — " . ($panel->name ?? '') . "\n"
                 . ($address ? "• Adresse : {$address}\n" : '')
                 . "• Commune : {$commune}\n"
                 . "• Prévue : {$scheduled}\n"
                 . ($task->campaign ? "• Campagne : {$task->campaign->name}\n" : '')
                 . "\nMettez à jour votre avancement en temps réel ici :\n{$url}\n\n"
                 . "Merci.\nCIBLE CI";

        $sent = app(WhatsAppService::class)->send(
            $tech->whatsapp_number,
            $message,
            ['action' => 'pose.assignment', 'task_id' => $task->id, 'tech_id' => $tech->id],
        );

        if ($sent) {
            $task->forceFill(['whatsapp_sent_at' => now()])->saveQuietly();
        }

        return $sent;
    }

    // ── Helpers ───────────────────────────────────────────────────
    private function error(string $msg): array { return ['ok' => false, 'error' => $msg]; }
}