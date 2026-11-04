<?php

namespace App\Services\Piges;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\Pige;
use App\Support\DownloadFilename;
use App\Support\PdfAssets;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Rapport de piges en PDF, prêt à envoyer au client (2026-11-04).
 *
 * Remplace le document Word monté à la main (« MOOV _ MILLIONS A SE
 * PARTAGER PHASE 2 ») : mêmes photos groupées par commune, deux panneaux
 * par page, avec en plus la référence, le format, la date de la photo, un
 * récapitulatif en première page et la charte.
 *
 * Règles validées le 2026-11-04 :
 *   · seules les piges VÉRIFIÉES sont exportables — jamais une rejetée ni
 *     une en attente, quel que soit le filtre de statut de la page ;
 *   · toutes les piges du filtre sont proposées, le MP décoche celles
 *     qu'il ne veut pas (écran de préparation) ;
 *   · au-delà de MAX_PHOTOS, Panora demande d'affiner les filtres.
 */
final class RapportPigesService
{
    use PdfAssets;

    public const MAX_PHOTOS = 200;

    /** Photo dans le PDF : réduite (poids du fichier), assez nette pour l'impression. */
    private const PHOTO_MAX_W = 1000;
    private const PHOTO_MAX_H = 750;
    private const PHOTO_QUALITE = 65;

    /** Cadre de la photo dans la page, en mm (2 panneaux par page A4). */
    private const CADRE_W = 186;
    private const CADRE_H = 100;

    /** Les piges exportables pour ces filtres (cf. PigeFiltres). */
    public function selection(array $filtres): Builder
    {
        return PigeFiltres::appliquer(Pige::active(), $filtres)
            ->where('status', 'verifie');
    }

    /** Piges choisies par le MP, revérifiées : vérifiées et non archivées. */
    public function choisies(array $ids): Collection
    {
        return Pige::active()
            ->where('status', 'verifie')
            ->whereIn('id', array_map('intval', $ids))
            ->with($this->relations())
            ->get();
    }

    public function relations(): array
    {
        return [
            'panel:id,reference,name,commune_id,format_id',
            'panel.commune:id,name',
            'panel.format:id,name',
            'campaign:id,name,client_id',
            'campaign.client:id,name',
        ];
    }

    /** Titre proposé : la campagne filtrée, sinon le client, sinon générique. */
    public function titreParDefaut(array $filtres): string
    {
        if (!empty($filtres['campaign_id']) && ($c = Campaign::find($filtres['campaign_id']))) {
            return $c->name;
        }
        if (!empty($filtres['client_id']) && ($cl = Client::find($filtres['client_id']))) {
            return 'Piges ' . $cl->name;
        }

        return 'Rapport de piges';
    }

    /**
     * Commune → piges, communes par ordre alphabétique, puis panneau et
     * date de la photo. Même ordre à l'écran de préparation et dans le PDF.
     *
     * @return Collection<string, Collection<int, Pige>>
     */
    public function grouper(Collection $piges): Collection
    {
        return $piges
            ->sortBy([
                fn ($a, $b) => strcmp($this->commune($a), $this->commune($b)),
                fn ($a, $b) => strcmp((string) $a->panel?->reference, (string) $b->panel?->reference),
                fn ($a, $b) => ($a->taken_at ?? $a->created_at) <=> ($b->taken_at ?? $b->created_at),
            ])
            ->groupBy(fn ($p) => $this->commune($p));
    }

    private function commune(Pige $p): string
    {
        return $p->panel?->commune?->name ?? 'Commune non renseignée';
    }

    /** Le PDF à télécharger. */
    public function pdf(Collection $piges, string $titre)
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '768M');

        $groupes = $this->grouper($piges)->map(
            fn (Collection $liste) => $liste->map(fn (Pige $p) => ['pige' => $p, 'photo' => $this->photo($p)])->values()
        );

        $dates = $piges->map(fn ($p) => $p->taken_at ?? $p->created_at)->filter();
        $clients = $piges->map(fn ($p) => $p->campaign?->client?->name)->filter()->unique()->values();
        $campagnes = $piges->map(fn ($p) => $p->campaign?->name)->filter()->unique()->values();

        $donnees = [
            'titre'      => $titre,
            'groupes'    => $groupes,
            'nbPhotos'   => $piges->count(),
            'nbPanneaux' => $piges->pluck('panel_id')->unique()->count(),
            'debut'      => $dates->min(),
            'fin'        => $dates->max(),
            'clients'    => $clients,
            'campagnes'  => $campagnes,
        ];

        $fichier = DownloadFilename::sanitize(
            $titre . ' - piges ' . now()->format('d-m-Y'),
            'piges-' . now()->format('Y-m-d'),
            'pdf'
        );

        return Pdf::loadView('pdf.rapport-piges', $donnees)
            ->setPaper('a4', 'portrait')
            ->download($fichier);
    }

    /**
     * Photo réduite en data-URI + sa taille d'affichage en mm, calculée
     * pour tenir dans le cadre sans déformation (paysage ou portrait).
     *
     * @return array{src: string, w: float, h: float}|null
     */
    private function photo(Pige $p): ?array
    {
        $src = $this->photoToDataUriCompact($p->photo_path, self::PHOTO_MAX_W, self::PHOTO_MAX_H, self::PHOTO_QUALITE);
        if (!$src) {
            return null;
        }

        $taille = @getimagesizefromstring(base64_decode(substr($src, strpos($src, ',') + 1)));
        [$w, $h] = $taille ? [$taille[0], $taille[1]] : [4, 3];
        $echelle = min(self::CADRE_W / $w, self::CADRE_H / $h);

        return ['src' => $src, 'w' => round($w * $echelle, 1), 'h' => round($h * $echelle, 1)];
    }
}
