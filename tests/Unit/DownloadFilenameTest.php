<?php

namespace Tests\Unit;

use App\Support\DownloadFilename;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 2026-09-23 — Nettoyage des noms de fichiers saisis par l'utilisateur.
 *
 * Ce helper est la seule barrière entre une saisie libre du MP et
 * l'en-tête HTTP Content-Disposition. Un saut de ligne ou un guillemet
 * non filtré permettrait d'injecter des en-têtes ; un « / » ou un « .. »
 * produirait un nom de fichier invalide ou trompeur.
 *
 * Il est partagé par les exports Disponibilités (PDF) et Taxes
 * (PDF + Excel) — d'où les tests sur les deux extensions.
 */
class DownloadFilenameTest extends TestCase
{
    // ── Cas nominaux ───────────────────────────────────────────────

    public function test_nom_simple_recoit_son_extension(): void
    {
        $this->assertSame('rapport-mairie.pdf', DownloadFilename::sanitize('rapport-mairie', 'defaut'));
    }

    public function test_extension_deja_saisie_n_est_pas_doublee(): void
    {
        $this->assertSame('rapport.pdf', DownloadFilename::sanitize('rapport.pdf', 'defaut'));
        $this->assertSame('rapport.pdf', DownloadFilename::sanitize('rapport.PDF', 'defaut'));
    }

    public function test_extension_xlsx_pour_les_exports_excel(): void
    {
        $this->assertSame('taxes-septembre.xlsx', DownloadFilename::sanitize('taxes-septembre', 'defaut', 'xlsx'));
        $this->assertSame('taxes.xlsx', DownloadFilename::sanitize('taxes.xlsx', 'defaut', 'xlsx'));
    }

    public function test_extension_d_un_autre_type_n_est_pas_retiree(): void
    {
        // « bilan.pdf » exporté en Excel reste « bilan.pdf.xlsx » : on ne
        // retire que l'extension attendue, jamais une autre partie du nom.
        $this->assertSame('bilan.pdf.xlsx', DownloadFilename::sanitize('bilan.pdf', 'defaut', 'xlsx'));
    }

    // ── Repli sur le nom par défaut ────────────────────────────────

    public static function videProvider(): array
    {
        return [
            'null'                 => [null],
            'chaine vide'          => [''],
            'espaces'              => ['   '],
            'points seuls'         => ['...'],
            'que des interdits'    => ['/\\:*?"<>|'],
        ];
    }

    #[DataProvider('videProvider')]
    public function test_saisie_vide_ou_invalide_retombe_sur_le_defaut(?string $saisie): void
    {
        $this->assertSame('mon-defaut.pdf', DownloadFilename::sanitize($saisie, 'mon-defaut'));
    }

    public function test_defaut_lui_meme_vide_donne_un_nom_utilisable(): void
    {
        // Jamais de fichier nommé « .pdf ».
        $this->assertSame('export.pdf', DownloadFilename::sanitize('', ''));
    }

    // ── Sécurité ───────────────────────────────────────────────────

    public function test_les_caracteres_interdits_sont_retires(): void
    {
        $this->assertSame(
            'rapport2026.pdf',
            DownloadFilename::sanitize('rap/port\\:2026*?"<>|', 'defaut')
        );
    }

    public function test_les_sauts_de_ligne_sont_retires(): void
    {
        // Sans ça : injection d'en-tête HTTP via Content-Disposition.
        // Le « : » fait aussi partie des caractères interdits : il saute en
        // même temps que le CRLF, l'en-tête injecté est doublement cassé.
        $this->assertSame(
            'rapportSet-Cookie a=b.pdf',
            DownloadFilename::sanitize("rapport\r\nSet-Cookie: a=b", 'defaut')
        );
        $this->assertStringNotContainsString("\n", DownloadFilename::sanitize("a\nb", 'defaut'));
        $this->assertStringNotContainsString("\r", DownloadFilename::sanitize("a\rb", 'defaut'));
    }

    public function test_traversee_de_repertoire_neutralisee(): void
    {
        $resultat = DownloadFilename::sanitize('../../etc/passwd', 'defaut');

        $this->assertStringNotContainsString('/', $resultat);
        $this->assertStringNotContainsString('\\', $resultat);
        // Les séparateurs sautent, puis les points de tête sont rognés :
        // il ne reste rien qui ressemble à un chemin.
        $this->assertSame('etcpasswd.pdf', $resultat);
    }

    public function test_points_et_espaces_en_bordure_sont_retires(): void
    {
        // Windows refuse les noms qui commencent ou finissent par un point.
        $this->assertSame('rapport.pdf', DownloadFilename::sanitize('  . rapport .  ', 'defaut'));
    }

    public function test_espaces_multiples_reduits(): void
    {
        $this->assertSame('rapport de septembre.pdf', DownloadFilename::sanitize("rapport   de \t septembre", 'defaut'));
    }

    // ── Longueur ───────────────────────────────────────────────────

    public function test_nom_tronque_a_96_caracteres(): void
    {
        $resultat = DownloadFilename::sanitize(str_repeat('a', 300), 'defaut');

        $this->assertSame(str_repeat('a', 96) . '.pdf', $resultat);
        $this->assertSame(100, mb_strlen($resultat));
    }

    public function test_troncature_ne_laisse_pas_de_point_final(): void
    {
        // 95 « a » puis un point : la coupe à 96 finirait sur « . ».
        $resultat = DownloadFilename::sanitize(str_repeat('a', 95) . '.' . str_repeat('b', 20), 'defaut');

        $this->assertStringNotContainsString('..pdf', $resultat);
        $this->assertSame(str_repeat('a', 95) . '.pdf', $resultat);
    }

    // ── Extension mal fournie côté appelant ────────────────────────

    public function test_extension_avec_point_ou_majuscules(): void
    {
        $this->assertSame('doc.xlsx', DownloadFilename::sanitize('doc', 'defaut', '.XLSX'));
    }

    public function test_extension_vide_retombe_sur_pdf(): void
    {
        $this->assertSame('doc.pdf', DownloadFilename::sanitize('doc', 'defaut', ''));
    }
}
