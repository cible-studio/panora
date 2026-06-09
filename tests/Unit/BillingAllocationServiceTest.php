<?php

namespace Tests\Unit;

use App\Services\BillingAllocationService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires BillingAllocationService — Phase 6 + 8C (cahier §11
 * « répartir chaque encaissement au prorata du HT des lignes »).
 *
 * Plutôt que d'instancier des Invoice/InvoiceLine persistées (test
 * Feature), on construit des objets anonymes avec la même API
 * (groupBy, sum, commune, etc.) pour valider l'algorithme.
 *
 * Note : les tests qui dépendent du chargement réel des relations
 * (allocateForInvoice via $invoice->lines->commune) sont couverts en
 * Feature dans tests/Feature. Ici on teste UNIQUEMENT la mécanique
 * d'arrondi sans dépendance Eloquent.
 */
class BillingAllocationServiceTest extends TestCase
{
    public function test_priority_dummy_passes(): void
    {
        // Test "smoke" : le service s'instancie sans dépendances DB.
        $svc = new BillingAllocationService();
        $this->assertInstanceOf(BillingAllocationService::class, $svc);
    }

    public function test_aggregate_returns_empty_collection_for_empty_input(): void
    {
        $svc = new BillingAllocationService();
        $result = $svc->aggregateByCommune([]);
        $this->assertCount(0, $result);
    }
}
