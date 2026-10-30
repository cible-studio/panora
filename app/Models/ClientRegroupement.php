<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des regroupements de fiches client (2026-10-30), cf.
 * App\Services\Clients\RegroupementClients. Une fiche retirée pointe vers
 * la fiche gardée ; si celle-ci est regroupée à son tour, ses lignes sont
 * repointées vers la nouvelle fiche gardée (pas de chaîne à suivre).
 */
class ClientRegroupement extends Model
{
    protected $table = 'client_regroupements';

    protected $fillable = [
        'ancien_client_id',
        'ancien_nom',
        'client_id',
        'user_id',
        'detail',
    ];

    protected $casts = [
        'detail' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function auteur()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** La fiche active qui a repris une fiche retirée, ou null. */
    public static function ficheGardee(?int $ancienClientId): ?Client
    {
        if (!$ancienClientId || !Schema::hasTable('client_regroupements')) {
            return null;
        }
        $id = static::where('ancien_client_id', $ancienClientId)->latest('id')->value('client_id');

        return $id ? Client::find($id) : null;
    }

    /**
     * Noms des fiches retirées → id de la fiche gardée, pour la règle « nom
     * unique ». Vide si la table n'existe pas encore.
     *
     * @return list<array{nom: string, client_id: int}>
     */
    public static function anciensNoms(): array
    {
        if (!Schema::hasTable('client_regroupements')) {
            return [];
        }

        return static::query()->toBase()->orderBy('id')->get(['ancien_nom', 'client_id'])
            ->map(fn ($r) => ['nom' => (string) $r->ancien_nom, 'client_id' => (int) $r->client_id])
            ->all();
    }
}
