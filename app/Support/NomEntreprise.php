<?php

namespace App\Support;

use App\Models\Client;
use App\Models\ClientRegroupement;
use Illuminate\Support\Str;

/**
 * Nom d'entreprise unique (règle de la direction, 2026-10-29). Source unique
 * pour tous les endroits qui créent, renomment ou retrouvent une fiche client
 * par son nom : formulaires, fenêtres « Nouveau client », imports, Sheet.
 *
 *   · REFUS — même nom une fois ignorés majuscules, accents, espaces, tirets,
 *     points : « Coris-Bank » = « CORIS BANK ».
 *   · ALERTE (non bloquante) — nom qui ressemble à une fiche existante :
 *       - même nom sans forme juridique ni pays (« MOOV AFRICA » ≈ « MOOV ») ;
 *       - un nom contenu dans l'autre (« ORO » ≈ « ASSEKE ORO »,
 *         « ATHOME » ≈ « ATHOME360 ») ;
 *       - orthographe très proche (« VISUELCONCEPTS » ≈ « VISUEL CONCEPT »).
 *
 * Les fiches supprimées ne comptent pas, sauf une fiche regroupée avec une
 * autre (2026-10-30) : son nom renvoie vers la fiche gardée. Instance
 * « scoped » (une par requête ou par job) : la liste des noms est lue une
 * fois, puis tenue à jour par les événements du modèle Client.
 */
final class NomEntreprise
{
    /** Mots qui ne distinguent pas une entreprise : forme juridique, pays, « groupe »… */
    private const MOTS_NEUTRES = [
        'SA', 'SAS', 'SASU', 'SARL', 'SARLU', 'SCS', 'SNC', 'GIE', 'LTD', 'LLC', 'INC', 'CORP', 'PLC', 'GMBH',
        'ETS', 'ETABLISSEMENT', 'ETABLISSEMENTS', 'STE', 'STES', 'SOCIETE', 'CIE', 'COMPAGNIE',
        'GROUP', 'GROUPE', 'GRP', 'HOLDING', 'INTERNATIONAL', 'INTL',
        'CI', 'RCI', 'COTE', 'DIVOIRE', 'IVOIRE', 'ABIDJAN', 'AFRICA', 'AFRIQUE', 'AFRIC',
        'LE', 'LA', 'LES', 'L', 'D', 'DE', 'DU', 'DES', 'ET', 'THE', 'OF', 'AND', 'CO',
    ];

    /** Raisons d'alerte, de la plus forte à la plus faible. */
    public const MEME_RACINE   = 'même nom sans forme juridique ni pays';
    public const CONTENU       = 'un nom est contenu dans l\'autre';
    public const ORTHOGRAPHE   = 'orthographe très proche';

    /** @var array<int, array{nom: string, cle: string, racine: string, mots: list<string>}>|null id → empreinte */
    private ?array $index = null;

    /** @var array<string, int>|null clé du nom d'une fiche regroupée → id de la fiche gardée */
    private ?array $anciensNoms = null;

    // ── Comparaison des noms (sans base) ─────────────────────────

    /** Clé d'identité : « Coris-Bank » et « CORIS BANK » donnent « CORISBANK ». */
    public static function cle(?string $nom): string
    {
        return (string) preg_replace('/[^A-Z0-9]+/', '', Str::upper(Str::ascii((string) $nom)));
    }

    /** @return array{nom: string, cle: string, racine: string, mots: list<string>} */
    public static function empreinte(?string $nom): array
    {
        $texte = Str::upper(Str::ascii(str_replace('&', ' ET ', (string) $nom)));
        $mots = array_values(array_filter(
            preg_split('/[^A-Z0-9]+/', $texte) ?: [],
            fn ($m) => $m !== '' && !in_array($m, self::MOTS_NEUTRES, true),
        ));

        return ['nom' => trim((string) $nom), 'cle' => self::cle($nom), 'racine' => implode('', $mots), 'mots' => $mots];
    }

    /** Raison pour laquelle $a ressemble à $b (deux empreintes), ou null. Ne traite pas le nom identique. */
    public static function ressemblance(array $a, array $b): ?string
    {
        [$ra, $rb] = [$a['racine'], $b['racine']];
        if ($ra === '' || $rb === '') {
            return null;
        }
        if ($ra === $rb) {
            return self::MEME_RACINE;
        }

        [$court, $long] = strlen($ra) <= strlen($rb) ? [$a, $b] : [$b, $a];
        if (strlen($court['racine']) >= 3 && str_starts_with($long['racine'], $court['racine'])) {
            return self::CONTENU;
        }
        if (array_diff($court['mots'], $long['mots']) === []) {
            return self::CONTENU;
        }

        if (strlen($ra) >= 4 && strlen($rb) >= 4) {
            similar_text($ra, $rb, $pourcent);
            if ($pourcent >= 85) {
                return self::ORTHOGRAPHE;
            }
        }

        return null;
    }

    // ── Recherche dans les fiches ────────────────────────────────

    /**
     * Id de la fiche active qui porte déjà ce nom (la plus ancienne), hors
     * $sauf ; à défaut, la fiche qui a repris une fiche regroupée de ce nom.
     */
    public function identique(?string $nom, ?int $sauf = null): ?int
    {
        return $this->trouver($nom, $sauf)['id'];
    }

    /** @return array{id: ?int, ancien_nom: bool} */
    private function trouver(?string $nom, ?int $sauf): array
    {
        $cle = self::cle($nom);
        if ($cle === '') {
            return ['id' => null, 'ancien_nom' => false];
        }
        foreach ($this->index() as $id => $e) {
            if ($e['cle'] === $cle && $id !== $sauf) {
                return ['id' => $id, 'ancien_nom' => false];
            }
        }
        $gardee = $this->anciensNoms[$cle] ?? null;

        return $gardee !== null && $gardee !== $sauf
            ? ['id' => $gardee, 'ancien_nom' => true]
            : ['id' => null, 'ancien_nom' => false];
    }

    /**
     * Fiche identique et fiches qui ressemblent, prêtes à afficher.
     *
     * @return array{identique: ?array, proches: list<array{id: int, name: string, statut: string, campagnes: int, url: string, raison: string}>}
     */
    public function verifier(?string $nom, ?int $sauf = null, int $max = 5): array
    {
        ['id' => $identique, 'ancien_nom' => $ancienNom] = $this->trouver($nom, $sauf);
        $saisie = self::empreinte($nom);
        $rang = [self::MEME_RACINE => 0, self::CONTENU => 1, self::ORTHOGRAPHE => 2];

        $proches = [];
        if ($identique === null && $saisie['cle'] !== '') {
            foreach ($this->index() as $id => $e) {
                if ($id !== $sauf && ($raison = self::ressemblance($saisie, $e)) !== null) {
                    $proches[$id] = $raison;
                }
            }
            uksort($proches, fn ($x, $y) => [$rang[$proches[$x]], $x] <=> [$rang[$proches[$y]], $y]);
            $proches = array_slice($proches, 0, $max, true);
        }

        $ids = array_merge($identique ? [$identique] : [], array_keys($proches));
        $fiches = $ids ? Client::withCount('campaigns')->whereKey($ids)->get()->keyBy('id') : collect();
        $carte = fn (int $id, string $raison) => ($f = $fiches->get($id)) ? [
            'id'        => $id,
            'name'      => (string) $f->name,
            'statut'    => Client::STATUTS[$f->statut_commercial] ?? 'Client',
            'campagnes' => (int) $f->campaigns_count,
            'url'       => route('admin.clients.show', $id),
            'raison'    => $raison,
        ] : null;

        return [
            'identique' => $identique ? $carte($identique, $ancienNom ? 'ancien nom de cette fiche (regroupée)' : 'même nom') : null,
            'proches'   => array_values(array_filter(array_map($carte, array_keys($proches), $proches))),
        ];
    }

    /** Message d'alerte commun : « Ça ressemble à MOOV (1 campagne), MOOV AFRICA. » */
    public static function texteProches(array $proches): string
    {
        $noms = array_map(fn ($p) => $p['name'] . ($p['campagnes'] ? ' (' . $p['campagnes'] . ' campagne' . ($p['campagnes'] > 1 ? 's' : '') . ')' : ''), $proches);

        return 'Ça ressemble à ' . implode(', ', $noms) . '.';
    }

    // ── Tenue de la liste (événements du modèle Client) ──────────

    public function ajouter(int $id, ?string $nom): void
    {
        if ($this->index !== null) {
            $this->index[$id] = self::empreinte($nom);
        }
    }

    public function oublier(): void
    {
        $this->index = null;
        $this->anciensNoms = null;
    }

    /** @return array<int, array{nom: string, cle: string, racine: string, mots: list<string>}> */
    private function index(): array
    {
        if ($this->index === null) {
            $this->index = [];
            foreach (Client::query()->toBase()->orderBy('id')->get(['id', 'name']) as $c) {
                $this->index[(int) $c->id] = self::empreinte($c->name);
            }
            // Noms des fiches regroupées, seulement vers une fiche gardée encore active.
            $this->anciensNoms = [];
            foreach (ClientRegroupement::anciensNoms() as $r) {
                $cle = self::cle($r['nom']);
                if ($cle !== '' && isset($this->index[$r['client_id']])) {
                    $this->anciensNoms[$cle] ??= $r['client_id'];
                }
            }
        }

        return $this->index;
    }
}
