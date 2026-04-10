<?php
namespace App\Services;

use App\Models\Alert;
use App\Models\Reservation;
use App\Models\Maintenance;
use App\Models\Campaign;
use App\Models\Panel;

class AlertService
{
    /**
     * Générer toutes les alertes automatiques
     * Appelé par la commande artisan alerts:generate
     */
    public function generateAll(): array
    {
        $counts = [
            'reservations' => $this->alertesReservationsEnAttente(),
            'maintenances' => $this->alertesMaintenancesUrgentes(),
            'campagnes'    => $this->alertesCampagnesExpirantBientot(),
            'panneaux'     => $this->alertesPanneauxEnMaintenance(),
        ];

        return $counts;
    }

    /**
     * Alertes : réservations en attente depuis plus de 48h
     */
    public function alertesReservationsEnAttente(): int
    {
        $count = 0;

        $reservations = Reservation::with('client')
            ->where('status', 'en_attente')
            ->where('created_at', '<=', now()->subHours(48))
            ->get();

        foreach ($reservations as $reservation) {
            $exists = Alert::where('type', 'reservation')
                ->where('related_type', 'reservation')
                ->where('related_id', $reservation->id)
                ->where('title', 'like', '%en attente%')
                ->where('is_read', false)
                ->exists();

            if (!$exists) {
                Alert::create([
                    'type'         => 'reservation',
                    'niveau'       => 'warning',
                    'title'        => "Réservation en attente — {$reservation->client?->name}",
                    'message'      => "La réservation {$reservation->reference} est en attente de confirmation depuis plus de 48h.",
                    'related_type' => 'reservation',
                    'related_id'   => $reservation->id,
                    'is_read'      => false,
                    'triggered_at' => now(),
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Alertes : maintenances urgentes non résolues depuis plus de 24h
     */
    public function alertesMaintenancesUrgentes(): int
    {
        $count = 0;

        $maintenances = Maintenance::with('panel')
            ->where('priorite', 'urgente')
            ->where('statut', '!=', 'resolu')
            ->where('created_at', '<=', now()->subHours(24))
            ->get();

        foreach ($maintenances as $maintenance) {
            $exists = Alert::where('type', 'maintenance')
                ->where('related_type', 'maintenance')
                ->where('related_id', $maintenance->id)
                ->where('is_read', false)
                ->exists();

            if (!$exists) {
                Alert::create([
                    'type'         => 'maintenance',
                    'niveau'       => 'danger',
                    'title'        => "Maintenance urgente — {$maintenance->panel?->reference}",
                    'message'      => "Panne urgente non résolue : {$maintenance->type_panne}. Panneau {$maintenance->panel?->reference} hors service.",
                    'related_type' => 'maintenance',
                    'related_id'   => $maintenance->id,
                    'is_read'      => false,
                    'triggered_at' => now(),
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Alertes : campagnes actives qui se terminent dans moins de 14 jours
     */
    public function alertesCampagnesExpirantBientot(): int
    {
        $count = 0;

        $campagnes = Campaign::with('client')
            ->where('status', 'actif')
            ->whereBetween('end_date', [now(), now()->addDays(14)])
            ->get();

        foreach ($campagnes as $campagne) {
            $exists = Alert::where('type', 'campagne')
                ->where('related_type', 'campaign')
                ->where('related_id', $campagne->id)
                ->where('is_read', false)
                ->exists();

            if (!$exists) {
                $joursRestants = now()->diffInDays($campagne->end_date);
                Alert::create([
                    'type'         => 'campagne',
                    'niveau'       => $joursRestants <= 7 ? 'danger' : 'warning',
                    'title'        => "Campagne expire bientôt — {$campagne->client?->name}",
                    'message'      => "La campagne \"{$campagne->name}\" se termine dans {$joursRestants} jour(s). Pensez au renouvellement.",
                    'related_type' => 'campaign',
                    'related_id'   => $campagne->id,
                    'is_read'      => false,
                    'triggered_at' => now(),
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Alertes : panneaux en maintenance depuis plus de 7 jours
     */
    public function alertesPanneauxEnMaintenance(): int
    {
        $count = 0;

        $panneaux = Panel::where('status', 'maintenance')
            ->where('updated_at', '<=', now()->subDays(7))
            ->get();

        foreach ($panneaux as $panel) {
            $exists = Alert::where('type', 'panneau')
                ->where('related_type', 'panel')
                ->where('related_id', $panel->id)
                ->where('title', 'like', '%maintenance%')
                ->where('is_read', false)
                ->exists();

            if (!$exists) {
                Alert::create([
                    'type'         => 'panneau',
                    'niveau'       => 'warning',
                    'title'        => "Panneau en maintenance prolongée — {$panel->reference}",
                    'message'      => "Le panneau {$panel->reference} est en maintenance depuis plus de 7 jours.",
                    'related_type' => 'panel',
                    'related_id'   => $panel->id,
                    'is_read'      => false,
                    'triggered_at' => now(),
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Créer une alerte manuelle
     */
    public static function create(string $type, string $niveau, string $title, string $message, $model = null): Alert
    {
        return Alert::create([
            'type'         => $type,
            'niveau'       => $niveau,
            'title'        => $title,
            'message'      => $message,
            'related_type' => $model ? class_basename($model) : null,
            'related_id'   => $model?->id,
            'is_read'      => false,
            'triggered_at' => now(),
        ]);
    }
}
