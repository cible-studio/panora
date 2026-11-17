<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Une édition du baromètre clients (2026-11-17) : sa date limite.
 * Les liens personnels expirent à la fin de ce jour-là.
 */
class BarometreEdition extends Model
{
    protected $table = 'barometre_editions';

    protected $fillable = ['edition', 'date_limite'];

    protected $casts = ['date_limite' => 'date'];

    /** Dernier instant où le questionnaire est ouvert : 23:59:59 le jour limite. */
    public function finDuDelai(): Carbon
    {
        return $this->date_limite->copy()->endOfDay();
    }

    public function estClose(): bool
    {
        return now()->gt($this->finDuDelai());
    }
}
