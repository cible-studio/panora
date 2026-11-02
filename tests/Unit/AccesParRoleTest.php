<?php

namespace Tests\Unit;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use App\Policies\InvoicePolicy;
use App\Policies\QuotePolicy;
use App\Support\Acces;
use Tests\TestCase;

/**
 * Qui voit quoi (validé le 2026-11-02) — config/acces.php est la source
 * unique. Ces tests refusent qu'une page du menu s'ouvre pour d'autres
 * rôles que ceux listés (onglet caché mais ouvrable par l'adresse, ou
 * l'inverse : onglet affiché qui mène à une page interdite).
 */
class AccesParRoleTest extends TestCase
{
    private const STAFF = ['admin', 'commercial', 'mediaplanner', 'comptable'];

    /** Rôles du personnel qui passent tous les middlewares « role: » de la route. */
    private function rolesDeLaRoute(string $nom): array
    {
        $route = app('router')->getRoutes()->getByName($nom);
        $this->assertNotNull($route, "Route {$nom} introuvable.");

        $roles = self::STAFF;
        foreach ($route->gatherMiddleware() as $m) {
            if (is_string($m) && str_starts_with($m, 'role:')) {
                $roles = array_values(array_intersect($roles, explode(',', substr($m, 5))));
            }
        }
        sort($roles);

        return $roles;
    }

    private function utilisateur(UserRole $role): User
    {
        $u = new User();
        $u->id = 99;
        $u->role = $role;

        return $u;
    }

    public function test_chaque_page_du_menu_s_ouvre_pour_les_roles_du_fichier(): void
    {
        foreach (config('acces.modules') as $module => $def) {
            if ($def['route'] === null) {
                continue;
            }
            $attendu = $def['roles'];
            sort($attendu);
            $this->assertSame($attendu, $this->rolesDeLaRoute($def['route']),
                "Module « {$module} » : le menu et la route {$def['route']} ne donnent pas les mêmes droits.");
        }
    }

    public function test_toutes_les_pages_de_lecture_des_factures_et_devis_suivent_le_fichier(): void
    {
        foreach (['admin.invoices.index', 'admin.invoices.show', 'admin.invoices.pdf',
                  'admin.invoices.export.pdf', 'admin.invoices.export.excel', 'admin.invoices.audit'] as $r) {
            $this->assertSame(['admin', 'commercial', 'comptable'], $this->rolesDeLaRoute($r), $r);
        }
        foreach (['admin.quotes.index', 'admin.quotes.show', 'admin.quotes.pdf'] as $r) {
            $this->assertSame(['admin', 'commercial', 'mediaplanner'], $this->rolesDeLaRoute($r), $r);
        }
        // Prix d'un devis : plus le comptable (il n'a plus les devis).
        $this->assertSame(['admin', 'commercial'], $this->rolesDeLaRoute('admin.quotes.price'));
    }

    public function test_decisions_du_2026_11_02(): void
    {
        $mp = $this->utilisateur(UserRole::MEDIAPLANNER);
        $comptable = $this->utilisateur(UserRole::COMPTABLE);
        $commercial = $this->utilisateur(UserRole::COMMERCIAL);
        $tech = $this->utilisateur(UserRole::TECHNIQUE);

        // Le MP ne voit plus les factures ; il garde les devis en lecture.
        $this->assertFalse((new InvoicePolicy())->viewAny($mp));
        $this->assertFalse((new InvoicePolicy())->view($mp, new Invoice()));
        $this->assertTrue((new QuotePolicy())->viewAny($mp));
        $this->assertTrue((new QuotePolicy())->view($mp, new Quote()));

        // Le comptable ne voit plus les devis ; il garde les factures.
        $this->assertFalse((new QuotePolicy())->viewAny($comptable));
        $this->assertFalse((new QuotePolicy())->view($comptable, new Quote()));
        $this->assertTrue((new InvoicePolicy())->viewAny($comptable));
        $this->assertTrue((new InvoicePolicy())->view($comptable, new Invoice()));

        // Lecture seule du MP : pas de modification des prix d'un devis,
        // même modifiable ; le comptable non plus.
        $devis = new Quote();
        $devis->status = QuoteStatus::BROUILLON;
        $this->assertTrue($devis->isEditable(), "Le devis de test doit être modifiable.");
        $this->assertFalse((new QuotePolicy())->updatePrice($mp, $devis));
        $this->assertFalse((new QuotePolicy())->updatePrice($comptable, $devis));

        // Tableau de bord : le comptable garde le CA, le MP le perd.
        $this->assertTrue(Acces::peut('chiffres_financiers', $comptable));
        $this->assertTrue(Acces::peut('chiffres_financiers', $commercial));
        $this->assertFalse(Acces::peut('chiffres_financiers', $mp));

        // Le technicien n'a rien dans l'espace d'administration.
        foreach (array_keys(config('acces.modules')) as $module) {
            $this->assertFalse(Acces::peut($module, $tech), "Technicien : {$module}");
        }
    }

    public function test_le_menu_ne_garde_aucune_liste_de_roles_a_lui(): void
    {
        $menu = file_get_contents(base_path('resources/views/layouts/admin.blade.php'));
        $debut = strpos($menu, '<nav class="sidebar-nav"');
        $nav = substr($menu, $debut, strpos($menu, '</nav>', $debut) - $debut);

        $this->assertDoesNotMatchRegularExpression('/\$is(Admin|MP|Comptable)\b/', $nav,
            'Le menu doit passer par $voit(), pas par une liste de rôles recopiée.');
        foreach (config('acces.modules') as $module => $def) {
            if ($def['route'] !== null) {
                $this->assertStringContainsString("\$voit('{$module}')", $nav, "Entrée de menu « {$module} » non reliée au fichier.");
            }
        }
    }

    public function test_un_module_inconnu_est_une_erreur_franche(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Acces::peut('modules_qui_n_existe_pas', $this->utilisateur(UserRole::ADMIN));
    }
}
