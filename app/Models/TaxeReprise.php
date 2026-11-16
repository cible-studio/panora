<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Ce qu'une mairie a réclamé pour l'exercice de reprise (2026), saisi par
 * les comptables — décision écrite du 2026-11-16, option B. Mêmes colonnes
 * que la fiche de la direction. Cf. migration create_taxe_reprises_table.
 */
class TaxeReprise extends Model implements Auditable
{
    use AuditableTrait;

    protected $fillable = [
        'commune_id', 'exercice', 'arrieres', 'odp',
        'tm_t1', 'tm_t2', 'tm_t3', 'tm_t4', 'commentaire', 'saisi_par',
    ];

    protected $casts = [
        'exercice' => 'integer',
        'arrieres' => 'integer',
        'odp'      => 'integer',
        'tm_t1'    => 'integer',
        'tm_t2'    => 'integer',
        'tm_t3'    => 'integer',
        'tm_t4'    => 'integer',
    ];

    protected $auditExclude = ['updated_at'];

    public function commune()
    {
        return $this->belongsTo(Commune::class);
    }

    public function saisiPar()
    {
        return $this->belongsTo(User::class, 'saisi_par');
    }

    /** @return array<int, int> [1 => T1, …, 4 => T4] */
    public function tmParTrimestre(): array
    {
        return [1 => $this->tm_t1, 2 => $this->tm_t2, 3 => $this->tm_t3, 4 => $this->tm_t4];
    }
}
