<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Un seul bouton « Retour » par écran (2026-11-02).
 *
 * Depuis le 2026-10-29, `admin.partials._smart_back` déduit le retour de la
 * page précédente, et le layout le pose de lui-même quand la vue ne déclare
 * pas de topbarLeft. Treize écrans gardaient en plus leur ancien retour écrit
 * en dur : deux boutons côte à côte. La destination d'origine passe
 * désormais par l'option `defaut` du partial.
 */
class UnSeulRetourTest extends TestCase
{
    private const VUES = __DIR__ . '/../../resources/views/admin';
    private const CHEVRON = 'points="15 18 9 12 15 6"';

    /** @return array<string, string> chemin relatif => contenu */
    private function vues(): array
    {
        $vues = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::VUES));
        foreach ($it as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.blade.php') && !str_contains($f->getPathname(), 'partials')) {
                $vues[str_replace(chr(92), '/', substr($f->getPathname(), strlen(self::VUES) + 1))] = file_get_contents($f->getPathname());
            }
        }

        return $vues;
    }

    public function test_le_retour_intelligent_n_est_jamais_double_dans_la_barre(): void
    {
        foreach ($this->vues() as $vue => $source) {
            if (!preg_match('/<x-slot:topbarLeft>(.*?)<\/x-slot:topbarLeft>/s', $source, $m)
                || !str_contains($m[1], '_smart_back')) {
                continue;
            }
            $this->assertStringNotContainsString(self::CHEVRON, $m[1],
                "{$vue} : un second retour écrit en dur à côté de _smart_back — passer par 'defaut'.");
            $this->assertDoesNotMatchRegularExpression('/>\s*(←\s*)?Retour\b/u', $m[1],
                "{$vue} : un second « Retour » à côté de _smart_back.");
        }
    }

    public function test_les_ecrans_corriges_n_ont_plus_qu_un_retour(): void
    {
        $vues = $this->vues();
        foreach ([
            'campaigns/show.blade.php', 'clients/show.blade.php', 'panels/show.blade.php',
            'performance/commerciaux/show.blade.php', 'performance/equipes/show.blade.php',
            'performance/techniciens/show.blade.php',
            'rapports/panneaux-classification.blade.php',
            'piges/archives.blade.php', 'messages/show.blade.php', 'renouvellements/modeles.blade.php',
            'dashboard/tech-live.blade.php', 'dashboard/team-live.blade.php', 'dashboard/map-live.blade.php',
        ] as $vue) {
            $source = $vues[$vue] ?? '';
            $this->assertSame(1, substr_count($source, '_smart_back'), "{$vue} : le retour doit passer par _smart_back, une fois.");
            $this->assertStringContainsString("'defaut'", $source, "{$vue} : la destination d'origine sert de repli.");
            $this->assertDoesNotMatchRegularExpression('/←\s*(Retour|Tous les messages|Renouvellements)/u', $source,
                "{$vue} : un ancien retour écrit dans la page subsiste.");
        }
    }
}
