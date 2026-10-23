<?php

namespace App\Services\Diffusion;

use App\Models\ModeleMail;

/**
 * Textes des mails clients avant échéance, modifiables dans Panora
 * (2026-10-22) : mail J-7 simple (« fin »), séquence de renouvellement
 * des campagnes annuelles (j60 → j7) et accusé de réception.
 *
 * Source UNIQUE de ces textes et de leur mise en forme. Le texte s'écrit
 * simplement :
 *   · une ligne vide sépare deux paragraphes ;
 *   · **texte** met en gras ;
 *   · des lignes qui commencent par « - » forment une liste ;
 *   · [Champ] est remplacé par l'information du client (cf. CHAMPS) ;
 *   · [Récapitulatif] et [Boutons], seuls sur leur ligne, insèrent
 *     l'encadré de la campagne et les boutons de réponse.
 *
 * Textes d'origine : J-60, J-45 et J-15 validés par la direction le
 * 2026-10-22 ; J-30, J-7 et accusé rédigés dans le même ton ; « fin » =
 * le mail J-7 du 2026-10-21.
 */
class ModelesMail
{
    /** Clé → libellé de l'onglet de l'éditeur, dans l'ordre d'envoi. */
    public const ETAPES = [
        'j60'    => 'J-60 — Première annonce',
        'j45'    => 'J-45 — Relance',
        'j30'    => 'J-30 — Date limite',
        'j15'    => 'J-15 — Disponibilité non garantie',
        'j7'     => 'J-7 — Dernier rappel',
        'accuse' => 'Accusé de réception',
        'fin'    => 'Fin de campagne (moins de 12 mois)',
    ];

    /** Choix de réponse du client → libellé du bouton et de la page. */
    public const CHOIX = [
        'renouveler' => 'Je souhaite renouveler ma campagne',
        'ajuster'    => 'Je souhaite ajuster mon dispositif',
        'refus'      => 'Je ne souhaite pas renouveler',
        'rappel'     => 'Je souhaite échanger avec un conseiller',
    ];

    /** Boutons de chaque étape (textes validés). */
    public const BOUTONS = [
        'j60' => ['renouveler', 'ajuster', 'refus'],
        'j45' => ['renouveler', 'ajuster', 'refus'],
        'j30' => ['renouveler', 'ajuster', 'refus'],
        'j15' => ['renouveler', 'rappel'],
        'j7'  => ['renouveler', 'rappel'],
    ];

    /** Champs utilisables dans l'objet et le texte → aide de l'éditeur. */
    public const CHAMPS = [
        '[Nom du client]'      => 'Interlocuteur du client (à défaut, la société)',
        '[Société]'            => 'Nom du client',
        '[Nom de la campagne]' => 'Nom de la campagne',
        "[Date d'échéance]"    => 'Date de fin, en toutes lettres',
        '[Jours restants]'     => '« dans 7 jours », « demain »…',
        '[Date limite]'        => 'Date limite de réponse : 30 jours avant la fin',
        '[Dispositif]'         => 'Nombre de panneaux',
        '[Nom du commercial]'  => 'Commercial de la campagne',
        '[Régie]'              => 'Nom de la régie',
        '[Votre réponse]'      => 'Accusé : le choix du client',
        '[Votre message]'      => 'Accusé : le message du client (s\'il en a laissé un)',
        '[Récapitulatif]'      => 'Encadré : campagne, panneaux, échéance (seul sur sa ligne)',
        '[Boutons]'            => 'Boutons de réponse de l\'étape (seul sur sa ligne)',
    ];

    public const DEFAUTS = [

        'j60' => [
            'objet' => 'Votre campagne [Régie] arrive prochainement à échéance – Anticipons son renouvellement',
            'corps' => <<<'TXT'
Bonjour [Nom du client],

Nous espérons que vous allez bien.

Nous souhaitons vous informer que votre campagne d'affichage actuellement en cours sur notre réseau [Régie] arrivera à échéance le **[Date d'échéance]**.

Afin d'assurer la continuité de votre visibilité et de vous permettre de conserver vos emplacements actuels, nous vous invitons à nous communiquer dès à présent vos intentions de renouvellement pour la prochaine période de diffusion.

[Récapitulatif]

**Anticipons ensemble votre prochaine campagne**
Nous vous invitons à sélectionner l'option correspondant à vos intentions :

[Boutons]

Nous vous remercions de bien vouloir nous communiquer votre décision avant le **[Date limite]**.

Au-delà de cette date, et en l'absence de confirmation, nous ne pourrons garantir la disponibilité de vos emplacements pour la prochaine période de diffusion, ceux-ci étant susceptibles d'être proposés à d'autres annonceurs.

Notre équipe commerciale reste naturellement à votre disposition pour vous accompagner dans le renouvellement ou l'optimisation de votre dispositif.

Nous vous remercions pour votre confiance et espérons poursuivre notre collaboration.

Bien cordialement,
TXT,
        ],

        'j45' => [
            'objet' => 'Avez-vous pu vous positionner sur le renouvellement de votre campagne ?',
            'corps' => <<<'TXT'
Bonjour [Nom du client],

Nous revenons vers vous concernant notre précédent message relatif à l'échéance de votre campagne d'affichage, prévue le **[Date d'échéance]**.

Afin d'anticiper la prochaine période de diffusion et de préserver la disponibilité de vos emplacements actuels, nous vous invitons à nous communiquer vos intentions de renouvellement.

Vous pouvez nous répondre directement en sélectionnant l'une des options ci-dessous :

[Boutons]

Nous vous remercions de votre retour avant le **[Date limite]**.

Bien cordialement,
TXT,
        ],

        'j30' => [
            'objet' => 'Votre campagne [Régie] – Date limite de confirmation',
            'corps' => <<<'TXT'
Bonjour [Nom du client],

Votre campagne d'affichage arrive à échéance le **[Date d'échéance]**. Sauf erreur de notre part, nous n'avons pas encore reçu vos intentions concernant son renouvellement.

Nous arrivons à la date limite de confirmation, fixée au **[Date limite]**. Pour conserver la priorité sur vos emplacements actuels, il vous suffit de sélectionner l'option correspondant à votre choix :

[Boutons]

[Récapitulatif]

[Nom du commercial] reste également à votre disposition pour en parler de vive voix.

Bien cordialement,
TXT,
        ],

        'j15' => [
            'objet' => 'Dernier rappel – Disponibilité de vos emplacements [Régie]',
            'corps' => <<<'TXT'
Bonjour [Nom du client],

Sauf erreur de notre part, nous n'avons pas encore reçu votre confirmation concernant le renouvellement de votre campagne d'affichage, dont l'échéance est fixée au **[Date d'échéance]**.

La date limite de confirmation étant désormais dépassée, nous attirons votre attention sur le fait que nous ne sommes plus en mesure de garantir la disponibilité de vos emplacements actuels pour la prochaine période de diffusion.

Ceux-ci sont susceptibles d'être proposés à d'autres annonceurs, conformément aux dispositions contractuelles applicables.

Si vous souhaitez poursuivre votre campagne, nous vous invitons à nous le confirmer dès maintenant afin que nous puissions vérifier les disponibilités et vous accompagner dans les meilleurs délais.

[Boutons]

Nous restons à votre disposition.

Bien cordialement,
TXT,
        ],

        'j7' => [
            'objet' => "Dernier rappel avant la fin de votre campagne du [Date d'échéance]",
            'corps' => <<<'TXT'
Bonjour [Nom du client],

Votre campagne d'affichage « [Nom de la campagne] » se termine [Jours restants], le **[Date d'échéance]**.

Sans retour de votre part, nous préparons la remise à disposition de vos emplacements pour la période suivante, conformément aux conditions de votre contrat.

Si vous souhaitez poursuivre votre communication sur vos emplacements actuels, il est encore temps de nous le faire savoir :

[Boutons]

[Nom du commercial] tentera également de vous joindre dans les prochains jours.

Nous vous remercions pour votre confiance.

Bien cordialement,
TXT,
        ],

        'accuse' => [
            'objet' => 'Votre réponse sur le renouvellement de votre campagne « [Nom de la campagne] »',
            'corps' => <<<'TXT'
Bonjour [Nom du client],

Nous avons bien reçu votre réponse concernant votre campagne « [Nom de la campagne] », qui arrive à échéance le **[Date d'échéance]** :

**[Votre réponse]**

[Votre message]

[Nom du commercial] revient vers vous très prochainement pour la suite.

Nous vous remercions pour votre confiance.

Bien cordialement,
TXT,
        ],

        'fin' => [
            'objet' => "Votre campagne « [Nom de la campagne] » se termine le [Date d'échéance]",
            'corps' => <<<'TXT'
Bonjour [Nom du client],

Votre campagne **« [Nom de la campagne] »** se termine le **[Date d'échéance]**, [Jours restants].

Pour prolonger sa visibilité, ou préparer la suite, nous pouvons vous proposer :

- **Une reconduction** sur vos emplacements actuels, selon leur disponibilité ;
- **Les emplacements libres** en ce moment, à Abidjan et dans le reste du pays ;
- **Une recommandation** adaptée à votre prochain objectif.

Il vous suffit de **répondre à ce mail** en nous indiquant ce qui vous intéresse : nous revenons vers vous sous 24 heures ouvrées avec une proposition.

Bien cordialement,
TXT,
        ],
    ];

    /** @var array<string, array{objet: string, corps: string}> */
    private array $cache = [];

    /**
     * Texte en vigueur : celui modifié dans Panora, sinon l'original.
     *
     * @return array{objet: string, corps: string, modifie: bool}
     */
    public function modele(string $cle): array
    {
        if (isset($this->cache[$cle])) {
            return $this->cache[$cle];
        }

        $defaut = self::DEFAUTS[$cle] ?? ['objet' => '', 'corps' => ''];

        try {
            $ligne = ModeleMail::where('cle', $cle)->first();
        } catch (\Throwable) {
            $ligne = null; // table pas encore créée : textes d'origine
        }

        return $this->cache[$cle] = $ligne
            ? ['objet' => $ligne->objet, 'corps' => $ligne->corps, 'modifie' => true]
            : $defaut + ['modifie' => false];
    }

    public function oublier(): void
    {
        $this->cache = [];
    }

    // ══════════════════════════════════════════════════════════════
    // MISE EN FORME — fonctions pures, testées dans ModelesMailTest
    // ══════════════════════════════════════════════════════════════

    /** Objet : champs remplacés, gras retiré, espaces resserrés. */
    public static function remplir(string $texte, array $valeurs): string
    {
        $texte = self::utf8($texte);
        $s = self::substituer($texte, $valeurs, fn($v) => $v);

        return trim(preg_replace('/\s+/u', ' ', str_replace('**', '', $s)));
    }

    /**
     * Texte → blocs HTML prêts pour la vue du mail.
     *
     * @param array<string, string> $valeurs  [Champ] → valeur (texte brut)
     * @param array<string, string> $blocs    [Récapitulatif] / [Boutons] → HTML
     * @return list<array{type: string, html: string}>  type : texte | liste | bloc
     */
    public static function html(string $corps, array $valeurs, array $blocs = []): array
    {
        $sortie = [];
        $blocs  = self::cles($blocs);

        foreach (self::paragraphes($corps) as $p) {
            $seul = self::normaliser(trim($p));
            if (array_key_exists($seul, $blocs)) {
                if ($blocs[$seul] !== '') {
                    $sortie[] = ['type' => 'bloc', 'html' => $blocs[$seul]];
                }
                continue;
            }

            [$liste, $lignes] = self::lignes($p);
            $rendu = array_map(
                fn($l) => preg_replace('/\*\*(.+?)\*\*/su', '<strong>$1</strong>', self::substituer($l, $valeurs, fn($v) => e($v), fn($t) => e($t))),
                $lignes
            );

            if (trim(html_entity_decode(strip_tags(implode('', $rendu)))) === '') {
                continue; // paragraphe vide après remplacement (ex. [Votre message] absent)
            }

            $sortie[] = ['type' => $liste ? 'liste' : 'texte', 'html' => implode('<br>', $rendu)];
        }

        return $sortie;
    }

    /** Texte → version texte brut du mail (sans HTML). */
    public static function texte(string $corps, array $valeurs, array $blocs = []): string
    {
        $sortie = [];
        $blocs  = self::cles($blocs);

        foreach (self::paragraphes($corps) as $p) {
            $seul = self::normaliser(trim($p));
            if (array_key_exists($seul, $blocs)) {
                if ($blocs[$seul] !== '') {
                    $sortie[] = $blocs[$seul];
                }
                continue;
            }

            [$liste, $lignes] = self::lignes($p);
            $rendu = array_map(
                fn($l) => ($liste ? '- ' : '') . str_replace('**', '', self::substituer($l, $valeurs, fn($v) => $v)),
                $lignes
            );

            if (trim(implode('', $rendu), " -\n") === '') {
                continue;
            }

            $sortie[] = implode("\n", $rendu);
        }

        return implode("\n\n", $sortie);
    }

    /** Texte mal codé (copié d'un autre logiciel) : converti plutôt que de faire échouer le rendu. */
    private static function utf8(string $texte): string
    {
        return mb_check_encoding($texte, 'UTF-8') ? $texte : mb_convert_encoding($texte, 'UTF-8', 'Windows-1252');
    }

    /** @return string[] */
    private static function paragraphes(string $corps): array
    {
        $corps = self::utf8($corps);
        $corps = str_replace(["\r\n", "\r"], "\n", $corps);

        return array_values(array_filter(preg_split('/\n\s*\n/u', trim($corps)), fn($p) => trim($p) !== ''));
    }

    /** @return array{0: bool, 1: string[]}  est-ce une liste, lignes (sans « - ») */
    private static function lignes(string $paragraphe): array
    {
        $lignes = array_map('rtrim', explode("\n", trim($paragraphe)));
        $liste  = count(array_filter($lignes, fn($l) => preg_match('/^\s*-\s+/u', $l))) === count($lignes);

        return [$liste, $liste ? array_map(fn($l) => preg_replace('/^\s*-\s+/u', '', $l), $lignes) : $lignes];
    }

    /**
     * Remplace les [Champ] connus. $echapperValeur s'applique aux valeurs,
     * $echapperTexte au reste. Un champ inconnu reste tel quel : une faute
     * de frappe se voit dans l'aperçu.
     */
    private static function substituer(string $texte, array $valeurs, callable $echapperValeur, ?callable $echapperTexte = null): string
    {
        $echapperTexte ??= fn($t) => $t;
        $valeurs = self::cles($valeurs);

        $texte    = self::utf8($texte);
        $morceaux = preg_split('/(\[[^\[\]\n]{2,40}\])/u', $texte, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$texte];
        $s = '';
        foreach ($morceaux as $m) {
            $cle = self::normaliser($m);
            $s .= array_key_exists($cle, $valeurs) ? $echapperValeur((string) $valeurs[$cle]) : $echapperTexte($m);
        }

        return $s;
    }

    private static function cles(array $tableau): array
    {
        return $tableau ? array_combine(array_map([self::class, 'normaliser'], array_keys($tableau)), $tableau) : [];
    }

    /** « [Date d’échéance] » = « [date d'échéance] » : apostrophe typographique et casse tolérées. */
    private static function normaliser(string $champ): string
    {
        return mb_strtolower(str_replace(['’', '‘'], "'", $champ));
    }
}
