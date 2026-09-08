<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertySearchTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private Import $import;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-08 12:00:00');

        $this->supplier = Supplier::factory()->create(['code' => 'supplier-a']);
        $this->import = Import::factory()->for($this->supplier)->completed()->create();
    }

    public function test_returns_cheapest_offer_per_property(): void
    {
        $property = Property::factory()->create([
            'code' => 'BCN-0001',
            'name' => 'Barcelona Flat',
            'city' => 'Barcelona',
        ]);

        $this->createOffer($property, [
            'external_id' => 'expensive',
            'price' => 90000,
        ]);

        $cheap = $this->createOffer($property, [
            'external_id' => 'cheap',
            'price' => 50000,
        ]);

        $response = $this->getJson('/api/properties?'.http_build_query([
            'city' => 'Barcelona',
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'guests' => 2,
        ]));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'BCN-0001')
            ->assertJsonPath('data.0.best_offer.id', $cheap->id)
            ->assertJsonPath('data.0.best_offer.supplier', 'supplier-a')
            ->assertJsonPath('data.0.best_offer.price', 50000)
            ->assertJsonPath('data.0.best_offer.expires_at', $cheap->expires_at->toIso8601String());
    }

    public function test_expired_and_unavailable_offers_are_excluded(): void
    {
        $property = Property::factory()->create(['city' => 'Barcelona']);

        $this->createOffer($property, [
            'external_id' => 'expired',
            'price' => 10000,
            'expires_at' => now()->subHour(),
        ]);

        $this->createOffer($property, [
            'external_id' => 'unavailable',
            'price' => 20000,
            'available_units' => 0,
        ]);

        $this->getJson('/api/properties?'.http_build_query([
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'guests' => 2,
        ]))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_max_guests_and_dates_are_filtered(): void
    {
        $property = Property::factory()->create(['city' => 'Barcelona']);

        $this->createOffer($property, [
            'external_id' => 'small',
            'max_guests' => 2,
            'price' => 30000,
        ]);

        $this->createOffer($property, [
            'external_id' => 'other-dates',
            'check_in' => '2026-11-01',
            'check_out' => '2026-11-05',
            'max_guests' => 6,
            'price' => 40000,
        ]);

        $this->getJson('/api/properties?'.http_build_query([
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'guests' => 4,
        ]))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
    public function test_city_filter_works(): void
    {
        $barcelona = Property::factory()->create([
            'code' => 'BCN-1',
            'city' => 'Barcelona',
        ]);
        $madrid = Property::factory()->create([
            'code' => 'MAD-1',
            'city' => 'Madrid',
        ]);

        $this->createOffer($barcelona, ['external_id' => 'bcn', 'price' => 50000]);
        $this->createOffer($madrid, ['external_id' => 'mad', 'price' => 40000]);

        $this->getJson('/api/properties?'.http_build_query([
            'city' => 'Barcelona',
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'guests' => 2,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'BCN-1')
            ->assertJsonPath('data.0.city', 'Barcelona');
    }

    public function test_pagination_works_after_best_offer_selection(): void
    {
        $first = Property::factory()->create(['code' => 'P-1', 'city' => 'Barcelona']);
        $second = Property::factory()->create(['code' => 'P-2', 'city' => 'Barcelona']);

        $this->createOffer($first, ['external_id' => 'o-1', 'price' => 30000]);
        $this->createOffer($second, ['external_id' => 'o-2', 'price' => 50000]);

        $page1 = $this->getJson('/api/properties?'.http_build_query([
            'city' => 'Barcelona',
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'guests' => 2,
            'page' => 1,
            'per_page' => 1,
        ]));

        $page1
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'P-1')
            ->assertJsonPath('per_page', 1)
            ->assertJsonPath('prev', null);

        $this->assertNotNull($page1->json('next'));

        $page2 = $this->getJson('/api/properties?'.http_build_query([
            'city' => 'Barcelona',
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'guests' => 2,
            'page' => 2,
            'per_page' => 1,
        ]));

        $page2
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'P-2')
            ->assertJsonPath('next', null);

        $this->assertNotNull($page2->json('prev'));
    }

    public function test_one_property_returns_only_one_best_offer(): void
    {
        $property = Property::factory()->create(['city' => 'Barcelona']);

        $this->createOffer($property, ['external_id' => 'a', 'price' => 70000]);
        $this->createOffer($property, ['external_id' => 'b', 'price' => 60000]);
        $this->createOffer($property, ['external_id' => 'c', 'price' => 80000]);

        $response = $this->getJson('/api/properties?'.http_build_query([
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'guests' => 2,
        ]));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.best_offer.price', 60000)
            ->assertJsonPath('data.0.best_offer.supplier', 'supplier-a');
    }

    private function createOffer(Property $property, array $overrides = []): Offer
    {
        return Offer::factory()
            ->for($this->supplier)
            ->for($this->import)
            ->for($property)
            ->create(array_merge([
                'check_in' => '2026-10-10',
                'check_out' => '2026-10-15',
                'max_guests' => 4,
                'available_units' => 2,
                'expires_at' => now()->addDay(),
                'currency' => 'EUR',
            ], $overrides));
    }
}
