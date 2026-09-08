<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    private Offer $offer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-08 12:00:00');

        $supplier = Supplier::factory()->create(['code' => 'supplier-a']);
        $import = Import::factory()->for($supplier)->completed()->create();
        $property = Property::factory()->create();

        $this->offer = Offer::factory()
            ->for($supplier)
            ->for($import)
            ->for($property)
            ->create([
                'available_units' => 2,
                'expires_at' => now()->addDay(),
            ]);
    }

    public function test_successful_reservation_returns_201_and_decrements_units(): void
    {
        $response = $this->postJson('/api/offers/'.$this->offer->id.'/reservations', [
            'client_reference' => 'web-order-9f782b1c',
            'customer_name' => 'John Smith',
            'customer_email' => 'john@example.com',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.client_reference', 'web-order-9f782b1c')
            ->assertJsonPath('data.customer_name', 'John Smith')
            ->assertJsonPath('data.customer_email', 'john@example.com')
            ->assertJsonPath('data.offer_id', $this->offer->id);

        $this->assertDatabaseHas('reservations', [
            'offer_id' => $this->offer->id,
            'client_reference' => 'web-order-9f782b1c',
        ]);

        $this->assertSame(1, $this->offer->fresh()->available_units);
        $this->assertSame(1, Reservation::query()->count());
    }

    public function test_unavailable_offer_returns_409(): void
    {
        $this->offer->update(['available_units' => 0]);

        $this->postJson('/api/offers/'.$this->offer->id.'/reservations', [
            'client_reference' => 'web-order-unavailable',
            'customer_name' => 'John Smith',
            'customer_email' => 'john@example.com',
        ])
            ->assertStatus(409);

        $this->assertSame(0, Reservation::query()->count());
        $this->assertSame(0, $this->offer->fresh()->available_units);
    }

    public function test_expired_offer_returns_409(): void
    {
        $this->offer->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/offers/'.$this->offer->id.'/reservations', [
            'client_reference' => 'web-order-expired',
            'customer_name' => 'John Smith',
            'customer_email' => 'john@example.com',
        ])
            ->assertStatus(409);

        $this->assertSame(0, Reservation::query()->count());
        $this->assertSame(2, $this->offer->fresh()->available_units);
    }

    public function test_duplicate_client_reference_is_rejected(): void
    {
        Reservation::factory()->for($this->offer)->create([
            'client_reference' => 'web-order-duplicate',
        ]);

        $this->postJson('/api/offers/'.$this->offer->id.'/reservations', [
            'client_reference' => 'web-order-duplicate',
            'customer_name' => 'John Smith',
            'customer_email' => 'john@example.com',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['client_reference']);

        $this->assertSame(1, Reservation::query()->count());
        $this->assertSame(2, $this->offer->fresh()->available_units);
    }

    public function test_transaction_persists_reservation_and_decrement_together(): void
    {
        DB::transaction(function (): void {
            $this->postJson('/api/offers/'.$this->offer->id.'/reservations', [
                'client_reference' => 'web-order-tx',
                'customer_name' => 'John Smith',
                'customer_email' => 'john@example.com',
            ])->assertCreated();
        });

        $this->assertDatabaseHas('reservations', [
            'client_reference' => 'web-order-tx',
            'offer_id' => $this->offer->id,
        ]);
        $this->assertSame(1, $this->offer->fresh()->available_units);
    }

    public function test_last_unit_can_be_reserved_only_once(): void
    {
        $this->offer->update(['available_units' => 1]);

        $this->postJson('/api/offers/'.$this->offer->id.'/reservations', [
            'client_reference' => 'web-order-last-1',
            'customer_name' => 'John Smith',
            'customer_email' => 'john@example.com',
        ])->assertCreated();

        $this->postJson('/api/offers/'.$this->offer->id.'/reservations', [
            'client_reference' => 'web-order-last-2',
            'customer_name' => 'Jane Smith',
            'customer_email' => 'jane@example.com',
        ])->assertStatus(409);

        $this->assertSame(1, Reservation::query()->count());
        $this->assertSame(0, $this->offer->fresh()->available_units);
    }
}
