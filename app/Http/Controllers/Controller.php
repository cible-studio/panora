<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests;

    /**
     * Redirige vers /admin/settings sur la page contenant le record
     * $record dans la section $tab, avec un highlight sur la ligne
     * et un ancrage vers la carte de la section.
     *
     * Feedback user 2026-09-14 : après création/édition d'un format
     * (ou commune/zone/catégorie), le user restait sur la page 1
     * alors que le nouveau record se trouvait ailleurs dans le tri
     * alphabétique → impression que rien n'a été enregistré.
     *
     * @param Model  $record       Le record juste créé/modifié
     * @param string $tab          Nom de la section : 'formats',
     *                             'communes', 'zones', 'categories'
     * @param int    $perPage      Pagination (défaut 8, cf. SettingsController)
     * @param string $orderColumn  Colonne de tri (défaut 'name')
     */
    protected function redirectToSettingsRecord(
        Model $record,
        string $tab,
        int $perPage = 8,
        string $orderColumn = 'name'
    ): RedirectResponse {
        $modelClass = get_class($record);
        $recordValue = $record->{$orderColumn};

        // Position du record dans le tri asc (compte des records
        // strictement avant + tie-break par id pour reproduire
        // exactement le tri ->orderBy($orderColumn) SQL).
        $position = $modelClass::query()
            ->where(function ($q) use ($orderColumn, $recordValue, $record) {
                $q->where($orderColumn, '<', $recordValue)
                  ->orWhere(function ($q2) use ($orderColumn, $recordValue, $record) {
                      $q2->where($orderColumn, $recordValue)
                         ->where('id', '<=', $record->id);
                  });
            })
            ->count();

        $page = max(1, (int) ceil($position / $perPage));

        return redirect()->route('admin.settings.index', [
            $tab . '_page'      => $page,
            'highlight_' . $tab => $record->id,
        ])->withFragment($tab);
    }
}
