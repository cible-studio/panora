<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PanelFormat extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'width', 'height', 'surface', 'print_type'
    ];

    public function panels()
    {
        return $this->hasMany(Panel::class, 'format_id');
    }

    /**
     * "4×3m" — dimensions formatées (× typographique, "m" suffixé une seule
     * fois). Source unique pour les vues admin / client / PDF afin que tout
     * le monde affiche la même chaîne.
     *
     * Renvoie null si l'une des deux dimensions manque (préférable à un
     * "×0m" trompeur côté UI).
     */
    public function getDimensionsLabelAttribute(): ?string
    {
        if (!$this->width || !$this->height) return null;
        return $this->trimNumber($this->width) . '×' . $this->trimNumber($this->height) . 'm';
    }

    /**
     * Surface en m² calculée à partir de width × height — fiable même si la
     * colonne `surface` n'a pas été mise à jour ou a été saisie manuellement
     * de manière incohérente (vu en prod : '4x3m' avec surface=32).
     *
     * Fallback sur la colonne `surface` si width/height manquent.
     */
    public function getSurfaceM2Attribute(): ?float
    {
        if ($this->width && $this->height) {
            return round((float) $this->width * (float) $this->height, 2);
        }
        return $this->surface !== null ? (float) $this->surface : null;
    }

    public function getSurfaceLabelAttribute(): ?string
    {
        $m2 = $this->surface_m2;
        return $m2 !== null ? $this->trimNumber($m2) . ' m²' : null;
    }

    private function trimNumber(float|string|null $n): string
    {
        if ($n === null) return '';
        return rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
    }
}
