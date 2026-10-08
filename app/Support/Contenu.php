<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Contenu éditable : défauts versionnés + surcharges persistées.
 *
 * Architecture délibérée :
 *
 *   défaut   → config/contenu.php, dans git, déployé avec le code
 *   surcharge→ storage/app/contenu/<section>.json, sur le volume persistant
 *   affiché  → surcharge si elle existe, sinon défaut
 *
 * Le volume n'est donc jamais la source de vérité, seulement une couche
 * par-dessus. Si Coolify perd le volume, s'il est mal monté ou vidé, le
 * site continue d'afficher le dernier contenu committé — au lieu de la
 * page blanche qu'on obtiendrait avec un stockage unique en base.
 *
 * C'est le point qui rendait l'admin risqué sur ce projet : le conteneur
 * est éphémère, on l'a constaté avec les logs.
 */
class Contenu
{
    /** Cache par requête : évite de relire le disque à chaque appel de vue. */
    private static array $cache = [];

    /** Sections hors schéma (structures particulières). */
    public const SECTIONS_FIXES = ['coordonnees', 'chiffres', 'realisations'];

    /** Toutes les sections administrables : les fixes plus les pages du schéma. */
    public static function sections(): array
    {
        return array_merge(self::SECTIONS_FIXES, array_keys(Schema::pages()));
    }

    /** @deprecated conservé pour les appels existants */
    public const SECTIONS = self::SECTIONS_FIXES;

    /**
     * Valeurs par défaut d'une section.
     *
     * Les pages décrites par le schéma portent leur défaut au plus près du
     * champ, dans config/admin-schema.php : une seule source, impossible à
     * désynchroniser. Les sections à structure particulière (réalisations,
     * coordonnées, chiffres) restent dans config/contenu.php.
     */
    private static function defauts(string $section): array
    {
        if (Schema::page($section) !== null) {
            return array_map(
                fn ($def) => $def['defaut'] ?? '',
                Schema::champs($section)
            );
        }

        return config("contenu.$section", []);
    }

    /**
     * Retourne une section complète, surcharges appliquées.
     */
    public static function section(string $section): array
    {
        if (isset(self::$cache[$section])) {
            return self::$cache[$section];
        }

        $defaut = self::defauts($section);
        $surcharge = self::lireSurcharge($section);

        // Fusion à un niveau : une entrée surchargée remplace la valeur par
        // défaut, les entrées absentes de la surcharge restent au défaut.
        // Ainsi l'ajout d'un champ dans config/contenu.php apparaît
        // immédiatement, sans devoir réenregistrer depuis l'admin.
        $fusion = $defaut;
        foreach ($surcharge as $cle => $valeur) {
            if (is_array($valeur) && isset($defaut[$cle]) && is_array($defaut[$cle])) {
                $fusion[$cle] = array_replace($defaut[$cle], $valeur);
            } else {
                $fusion[$cle] = $valeur;
            }
        }

        return self::$cache[$section] = $fusion;
    }

    /**
     * Valeur unitaire, en notation pointée : Contenu::get('coordonnees.tel_mobile')
     */
    public static function get(string $chemin, mixed $defaut = null): mixed
    {
        [$section, $cle] = array_pad(explode('.', $chemin, 2), 2, null);

        $valeurs = self::section($section);

        return $cle === null ? $valeurs : ($valeurs[$cle] ?? $defaut);
    }

    /**
     * Champ de type « liste » : une entrée par ligne, saisie dans un simple
     * bloc de texte côté admin.
     *
     * Les lignes vides sont ignorées et les espaces de bord retirés — un
     * copier-coller depuis Word ou un retour à la ligne en trop ne doit pas
     * produire une puce vide sur le site.
     */
    public static function lignes(string $chemin): array
    {
        $valeur = (string) self::get($chemin, '');

        $lignes = preg_split('/\R/u', $valeur) ?: [];
        $lignes = array_map('trim', $lignes);

        return array_values(array_filter($lignes, fn ($l) => $l !== ''));
    }

    /**
     * Valeur avec mise en avant : le texte entre **doubles astérisques**
     * ressort dans la couleur d'accent de la section.
     *
     * Plusieurs phrases du site portent une emphase au milieu du texte —
     * l'accroche rouge du bandeau, la signature jaune, les titres de pôle.
     * Laisser saisir du HTML dans l'admin ouvrirait une injection ; retirer
     * l'emphase abîmerait la charte. On escape donc tout, puis on ne
     * réintroduit qu'une seule balise, à partir d'une notation qu'un
     * non-développeur retient en une phrase.
     *
     * S'utilise avec {!! !!} dans les vues — l'échappement est fait ici.
     */
    public static function riche(string $chemin, mixed $defaut = null): string
    {
        $valeur = (string) self::get($chemin, $defaut ?? '');

        $html = e($valeur);

        // Sur du texte déjà échappé, seuls les astérisques subsistent : le
        // motif ne peut pas déborder sur une balise.
        return preg_replace('/\*\*(.+?)\*\*/su', '<strong>$1</strong>', $html);
    }

    /**
     * Enregistre une section. Écrit la surcharge complète, pas un delta :
     * une relecture reste ainsi lisible et diffable à la main en cas de
     * besoin d'intervention directe sur le volume.
     */
    public static function enregistrer(string $section, array $valeurs): bool
    {
        if (!in_array($section, self::sections(), true)) {
            return false;
        }

        // JSON_INVALID_UTF8_SUBSTITUTE : un texte collé depuis Word ou un PDF
        // contient parfois un octet invalide. Sans cette option, json_encode
        // renvoie false, on écrirait un fichier VIDE en annonçant un succès,
        // et la section repartirait silencieusement sur ses valeurs par
        // défaut à l'affichage suivant. Le cas s'est produit en test.
        $json = json_encode(
            $valeurs,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($json === false || $json === '') {
            Journal::erreur('cible.contenu.encodage_impossible', [
                'section' => $section, 'erreur' => json_last_error_msg(),
            ]);

            return false;
        }

        try {
            Storage::disk('contenu')->put("$section.json", $json);

            unset(self::$cache[$section]);

            return true;
        } catch (\Throwable $e) {
            // Volume absent ou non inscriptible : on trace et on refuse, mais
            // le site public continue de fonctionner sur les défauts.
            Journal::erreur('cible.contenu.ecriture_impossible', [
                'section' => $section, 'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** Efface la surcharge d'une section : retour au contenu de git. */
    public static function reinitialiser(string $section): bool
    {
        try {
            Storage::disk('contenu')->delete("$section.json");
            unset(self::$cache[$section]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** La section est-elle surchargée (donc différente de git) ? */
    public static function estSurchargee(string $section): bool
    {
        try {
            return Storage::disk('contenu')->exists("$section.json");
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Date de la dernière modification, toutes sections confondues.
     *
     * Répond à la question que se pose la personne qui revient sur l'admin
     * après quelques semaines : « est-ce que quelqu'un a touché au site ? »
     */
    public static function derniereModificationGlobale(): ?\DateTimeImmutable
    {
        $dates = [];

        foreach (self::sections() as $section) {
            if ($date = self::derniereModification($section)) {
                $dates[] = $date;
            }
        }

        if ($dates === []) {
            return null;
        }

        usort($dates, fn ($a, $b) => $b <=> $a);

        return $dates[0];
    }

    /**
     * Le dossier de contenu est-il un volume monté, ou une simple couche du
     * conteneur ?
     *
     * « Inscriptible » ne veut pas dire « conservé » : un dossier créé dans
     * l'image est parfaitement inscriptible et disparaît pourtant à chaque
     * déploiement. C'est exactement ce qui a effacé le mot de passe
     * administrateur, et ce qui effacerait toutes les modifications de
     * contenu — sans qu'aucun message ne l'annonce.
     *
     * Un volume monté est un système de fichiers distinct : son numéro de
     * périphérique diffère de celui du code. Numéros identiques = pas de
     * volume.
     *
     * @return bool|null null si l'information n'est pas disponible
     */
    public static function stockagePersistant(): ?bool
    {
        // Hors production, la question ne se pose pas : le dossier vit sur le
        // disque du développeur, il n'y a pas de conteneur à recréer. Sans ce
        // garde-fou, l'admin local afficherait en permanence une alerte
        // fausse — et une alerte qu'on apprend à ignorer ne protège plus rien
        // le jour où elle est vraie.
        if (!app()->isProduction()) {
            return null;
        }

        try {
            $dossier = storage_path('app/contenu');

            if (!is_dir($dossier)) {
                return false;
            }

            $contenu = @stat($dossier);
            $code    = @stat(base_path());

            if ($contenu === false || $code === false) {
                return null;
            }

            return $contenu['dev'] !== $code['dev'];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Date de la dernière modification d'une section, ou null si elle n'a
     * jamais été retouchée depuis l'admin.
     */
    public static function derniereModification(string $section): ?\DateTimeImmutable
    {
        try {
            if (!Storage::disk('contenu')->exists("$section.json")) {
                return null;
            }

            return (new \DateTimeImmutable())
                ->setTimestamp(Storage::disk('contenu')->lastModified("$section.json"));
        } catch (\Throwable) {
            return null;
        }
    }

    /** Le stockage est-il réellement inscriptible ? Affiché dans l'admin. */
    public static function stockageDisponible(): bool
    {
        try {
            Storage::disk('contenu')->put('.verification', (string) time());
            Storage::disk('contenu')->delete('.verification');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * URL d'affichage d'un visuel de réalisation.
     *
     * Deux origines coexistent :
     *  - « images/cible/campagne-1.jpg » : livré avec le code, servi par
     *    public/ comme n'importe quel asset ;
     *  - « visuel:nom.jpg » : téléversé depuis l'admin, stocké sur le volume
     *    persistant et servi par la route dédiée.
     *
     * public/ est reconstruit depuis git à chaque déploiement : y déposer un
     * fichier téléversé le ferait disparaître au déploiement suivant.
     */
    public static function urlImage(string $reference): string
    {
        if (str_starts_with($reference, 'visuel:')) {
            return route('visuel', ['nom' => substr($reference, 7)]);
        }

        return asset($reference);
    }

    /** Le visuel est-il réellement disponible ? (évite une vignette cassée) */
    public static function imageExiste(string $reference): bool
    {
        if (str_starts_with($reference, 'visuel:')) {
            try {
                return Storage::disk('contenu')->exists('visuels/' . substr($reference, 7));
            } catch (\Throwable) {
                return false;
            }
        }

        return file_exists(public_path($reference));
    }

    private static function lireSurcharge(string $section): array
    {
        try {
            if (!Storage::disk('contenu')->exists("$section.json")) {
                return [];
            }

            $json = json_decode(Storage::disk('contenu')->get("$section.json"), true);

            return is_array($json) ? $json : [];
        } catch (\Throwable $e) {
            // Un JSON corrompu ne doit pas casser le site : on ignore la
            // surcharge et on sert les défauts.
            Journal::avertir('cible.contenu.surcharge_illisible', [
                'section' => $section, 'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
