<?php

namespace App\Services\Piges;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\Pige;
use App\Models\Vague;
use App\Services\Vagues\VagueService;
use App\Support\DownloadFilename;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Rapport de piges en PDF, prêt à envoyer au client (2026-11-04).
 *
 * Remplace le document Word monté à la main (« MOOV _ MILLIONS A SE
 * PARTAGER PHASE 2 ») et le reproduit à l'identique (demande de
 * l'utilisateur) : logo, titre, date, communes, « ❖ emplacement » et
 * photo — cf. resources/views/pdf/rapport-piges.blade.php.
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
    public const MAX_PHOTOS = 200;

    /** Photo dans le PDF : réduite (poids du fichier), assez nette pour l'impression. */
    private const PHOTO_MAX_W = 1000;   // côté le plus long ≤ 1000 px, portrait comme paysage
    private const PHOTO_MAX_H = 1000;
    private const PHOTO_QUALITE = 68;

    /** Cadre de la photo dans la fiche, en mm (cf. pdf/rapport-piges, td.cadre). */
    private const CADRE_W = 104;   // cadre de la fiche (2026-11-05 v3) : 104 × 76 mm, photo centrée — trois fiches par page
    private const CADRE_H = 76;

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
        $axes = app(\App\Services\Axes\AxeService::class);

        return array_merge([
            // Axes (2026-11-12) : les photos des pieds d'un axe font UNE fiche.
            'panel:id,reference,name,commune_id,format_id' . $axes->colonnesPanneau(),
            ...$axes->relationsAxe('panel'),
            'panel.commune:id,name',
            'panel.format:id,name,width,height,surface', // dimensions sous chaque photo (2026-11-05)
            'campaign:id,name,client_id',
            'campaign.client:id,name',
        ], VagueService::disponible() ? [
            // Vague de la photo (2026-11-04), par sa pose.
            'poseTask:id,vague_id',
            'poseTask.vague:id,campaign_id,numero,nom',
        ] : []);
    }

    /**
     * Titre proposé : la campagne filtrée (et sa vague : « MOOV 2026 —
     * Phase 2 »), sinon le client, sinon générique.
     */
    public function titreParDefaut(array $filtres): string
    {
        if (!empty($filtres['campaign_id']) && ($c = Campaign::find($filtres['campaign_id']))) {
            $vague = !empty($filtres['vague_id']) && VagueService::disponible()
                ? Vague::where('campaign_id', $c->id)->find($filtres['vague_id'])
                : null;

            // La Vague 1 porte le nom de la campagne : pas de doublon.
            return $vague && trim($vague->nom) !== trim($c->name)
                ? $c->name . ' — ' . $vague->nom
                : $c->name;
        }
        if (!empty($filtres['client_id']) && ($cl = Client::find($filtres['client_id']))) {
            return 'Piges ' . $cl->name;
        }

        return 'Rapport de piges';
    }

    /**
     * Commune → piges, dans l'ordre de la tournée, comme le document Word
     * de l'équipe : les communes dans l'ordre de leur première photo, puis
     * les photos par heure de prise de vue. Même ordre à l'écran de
     * préparation et dans le PDF.
     *
     * @return Collection<string, Collection<int, Pige>>
     */
    public function grouper(Collection $piges): Collection
    {
        $quand = fn (Pige $p) => ($p->taken_at ?? $p->created_at)?->getTimestamp() ?? 0;
        $premiere = $piges->groupBy(fn ($p) => $this->commune($p))
            ->map(fn (Collection $liste) => $liste->min($quand));

        return $piges
            ->sortBy([
                fn ($a, $b) => $premiere[$this->commune($a)] <=> $premiere[$this->commune($b)],
                fn ($a, $b) => strcmp($this->commune($a), $this->commune($b)),
                fn ($a, $b) => $quand($a) <=> $quand($b),
            ])
            ->groupBy(fn ($p) => $this->commune($p));
    }

    private function commune(Pige $p): string
    {
        return $p->panel?->commune?->name ?? 'Commune non renseignée';
    }

    /**
     * Les volumes du résumé de couverture : photos, panneaux (pieds compris),
     * fiches (« emplacements » : un axe compte pour un, avec ses pieds),
     * axes et leurs pieds photographiés.
     *
     * @return array{nbPhotos: int, nbPanneaux: int, nbFiches: int, nbAxes: int, nbPiedsAxes: int}
     */
    public function volumes(Collection $piges): array
    {
        $piedsDAxes = $piges->filter(fn ($p) => $p->panel?->axe_id);

        return [
            'nbPhotos'    => $piges->count(),
            'nbPanneaux'  => $piges->pluck('panel_id')->unique()->count(),
            'nbFiches'    => $piges->map(fn ($p) => $this->cleFiche($p))->unique()->count(),
            'nbAxes'      => $piedsDAxes->map(fn ($p) => $p->panel->axe_id)->unique()->count(),
            'nbPiedsAxes' => $piedsDAxes->pluck('panel_id')->unique()->count(),
        ];
    }

    /**
     * La fiche d'une photo (décision du 12/11) : les pieds d'un axe partagent
     * UNE fiche « CDYLUP-004 · 5 pieds » ; un panneau ordinaire (ou un axe
     * entier d'un ancien contrat) a la sienne.
     */
    public function cleFiche(Pige $p): string
    {
        return $p->panel?->axe_id ? 'axe-' . $p->panel->axe_id : 'p-' . $p->panel_id;
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
        $vagues = VagueService::disponible()
            ? $piges->map(fn ($p) => $p->poseTask?->vague)->filter()->unique('id')->values()
            : collect();

        $donnees = $this->volumes($piges) + [
            'titre'      => $titre,
            'groupes'    => $groupes,
            'debut'      => $dates->min(),
            'fin'        => $dates->max(),
            'clients'    => $clients,
            'campagnes'  => $campagnes,
            'vagues'     => $vagues,
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
     * Photo prête pour le PDF : data-URI JPEG + taille d'affichage en mm,
     * calculée pour tenir dans le cadre sans déformation.
     *
     * 2026-11-05 (« certaines images sont déformées ou coupées ») : chaque
     * photo est réencodée ici, ce qui garantit
     *   · l'orientation : une photo prise téléphone en main porte souvent une
     *     rotation EXIF que DomPDF ignore — elle sortait couchée ;
     *   · le bon rapport largeur / hauteur, lu sur l'image réellement
     *     intégrée (avant : 4:3 supposé quand la taille était illisible,
     *     d'où des photos étirées) ;
     *   · un format que DomPDF sait lire (JPEG), quelle que soit l'origine.
     *
     * @return array{src: string, w: float, h: float}|null
     */
    private function photo(Pige $p): ?array
    {
        $image = $this->ouvrirRedressee($this->cheminPhoto($p->photo_path));
        if (!$image) {
            return null;
        }

        // Réduction (poids du PDF), sans jamais agrandir.
        [$w, $h] = [imagesx($image), imagesy($image)];
        $reduction = min(1, self::PHOTO_MAX_W / $w, self::PHOTO_MAX_H / $h);
        if ($reduction < 1) {
            [$nw, $nh] = [max(1, (int) round($w * $reduction)), max(1, (int) round($h * $reduction))];
            $reduite = imagecreatetruecolor($nw, $nh);
            imagefill($reduite, 0, 0, imagecolorallocate($reduite, 255, 255, 255));
            imagecopyresampled($reduite, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($image);
            [$image, $w, $h] = [$reduite, $nw, $nh];
        }

        ob_start();
        imagejpeg($image, null, self::PHOTO_QUALITE);
        $jpeg = ob_get_clean();
        imagedestroy($image);

        $echelle = min(self::CADRE_W / $w, self::CADRE_H / $h);

        return [
            'src' => 'data:image/jpeg;base64,' . base64_encode($jpeg),
            'w'   => round($w * $echelle, 1),
            'h'   => round($h * $echelle, 1),
        ];
    }

    /** Chemin du fichier sur le disque « public » (où PigeService range les photos). */
    private function cheminPhoto(?string $relatif): ?string
    {
        if (!$relatif) {
            return null;
        }
        $rel = ltrim($relatif, '/');
        foreach ([Storage::disk('public')->path($rel), storage_path('app/public/' . $rel), storage_path('app/' . $rel)] as $chemin) {
            if (is_file($chemin) && is_readable($chemin)) {
                return $chemin;
            }
        }

        return null;
    }

    /** Image GD remise à l'endroit selon son orientation EXIF ; null si illisible. */
    private function ouvrirRedressee(?string $chemin)
    {
        $infos = $chemin ? @getimagesize($chemin) : false;
        if (!$infos || !extension_loaded('gd')) {
            return null;
        }

        $image = match ($infos[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($chemin),
            IMAGETYPE_PNG  => @imagecreatefrompng($chemin),
            IMAGETYPE_GIF  => @imagecreatefromgif($chemin),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($chemin) : false,
            default        => false,
        };
        if (!$image) {
            return null;
        }

        // Fond blanc sous la transparence (PNG), qui sortirait noire en JPEG.
        if ($infos[2] !== IMAGETYPE_JPEG) {
            $fond = imagecreatetruecolor(imagesx($image), imagesy($image));
            imagefill($fond, 0, 0, imagecolorallocate($fond, 255, 255, 255));
            imagecopy($fond, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            imagedestroy($image);
            $image = $fond;
        }

        $orientation = ($infos[2] === IMAGETYPE_JPEG && function_exists('exif_read_data'))
            ? (int) ((@exif_read_data($chemin) ?: [])['Orientation'] ?? 1)
            : 1;

        switch ($orientation) {
            case 2: imageflip($image, IMG_FLIP_HORIZONTAL); break;
            case 3: $image = imagerotate($image, 180, 0); break;
            case 4: imageflip($image, IMG_FLIP_VERTICAL); break;
            case 5: imageflip($image, IMG_FLIP_VERTICAL); $image = imagerotate($image, -90, 0); break;
            case 6: $image = imagerotate($image, -90, 0); break;
            case 7: imageflip($image, IMG_FLIP_VERTICAL); $image = imagerotate($image, 90, 0); break;
            case 8: $image = imagerotate($image, 90, 0); break;
        }

        return $image ?: null;
    }
}
