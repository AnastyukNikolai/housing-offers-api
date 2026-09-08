<?php

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Jobs\ProcessImport;
use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private array $payload;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = Supplier::factory()->create(['code' => 'supplier-a']);
        Supplier::factory()->create(['code' => 'supplier-b']);

        $this->payload = [
            'supplier' => 'supplier-a',
            'external_import_id' => 'import-2026-09-01-001',
            'sent_at' => '2026-09-01T10:00:00Z',
            'offers' => [
                [
                    'external_id' => 'offer-a-10001',
                    'property' => [
                        'code' => 'BCN-0001',
                        'name' => 'Apartment near Sagrada Familia',
                        'city' => 'Barcelona',
                    ],
                    'check_in' => '2026-10-10',
                    'check_out' => '2026-10-15',
                    'max_guests' => 4,
                    'price' => 72500,
                    'currency' => 'EUR',
                    'available_units' => 2,
                    'expires_at' => '2026-09-10T23:59:59Z',
                ],
            ],
        ];
    }

    public function test_import_is_accepted_with_http_202(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/imports', $this->payload);

        $response
            ->assertAccepted()
            ->assertJsonPath('data.status', ImportStatus::Pending->value)
            ->assertJsonStructure(['data' => ['id', 'status']]);

        $this->assertDatabaseHas('imports', [
            'supplier_id' => $this->supplier->id,
            'external_import_id' => 'import-2026-09-01-001',
            'status' => ImportStatus::Pending->value,
            'total_offers' => 1,
        ]);

        Queue::assertPushed(ProcessImport::class, 1);
    }

    public function test_duplicate_import_does_not_create_second_record_or_dispatch_job(): void
    {
        Queue::fake();

        $first = $this->postJson('/api/imports', $this->payload);
        $second = $this->postJson('/api/imports', $this->payload);

        $first->assertAccepted();
        $second
            ->assertAccepted()
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertSame(1, Import::query()->count());
        Queue::assertPushed(ProcessImport::class, 1);
    }
    public function test_process_import_creates_property_and_offer(): void
    {
        Queue::fake();

        $this->postJson('/api/imports', $this->payload)->assertAccepted();

        Queue::assertPushed(ProcessImport::class, function (ProcessImport $job): bool {
            $job->handle();

            return true;
        });

        $this->assertDatabaseHas('properties', [
            'code' => 'BCN-0001',
            'name' => 'Apartment near Sagrada Familia',
            'city' => 'Barcelona',
        ]);

        $this->assertDatabaseHas('offers', [
            'supplier_id' => $this->supplier->id,
            'external_id' => 'offer-a-10001',
            'price' => 72500,
            'available_units' => 2,
            'currency' => 'EUR',
        ]);

        $import = Import::query()->firstOrFail();

        $this->assertSame(ImportStatus::Completed, $import->status);
        $this->assertSame(1, $import->processed_offers);
        $this->assertNotNull($import->completed_at);
    }

    public function test_existing_offer_is_updated_on_new_import(): void
    {
        Queue::fake();

        $this->postJson('/api/imports', $this->payload)->assertAccepted();
        Queue::assertPushed(ProcessImport::class, function (ProcessImport $job): bool {
            $job->handle();

            return true;
        });

        $updatedPayload = $this->payload;
        $updatedPayload['external_import_id'] = 'import-2026-09-01-002';
        $updatedPayload['offers'][0]['price'] = 50000;
        $updatedPayload['offers'][0]['available_units'] = 1;
        $updatedPayload['offers'][0]['property']['name'] = 'Updated apartment';

        Queue::fake();

        $this->postJson('/api/imports', $updatedPayload)->assertAccepted();
        Queue::assertPushed(ProcessImport::class, function (ProcessImport $job): bool {
            $job->handle();

            return true;
        });

        $this->assertSame(1, Offer::query()->count());
        $this->assertSame(1, Property::query()->count());

        $offer = Offer::query()->firstOrFail();
        $secondImport = Import::query()
            ->where('external_import_id', 'import-2026-09-01-002')
            ->firstOrFail();

        $this->assertSame(50000, $offer->price);
        $this->assertSame(1, $offer->available_units);
        $this->assertSame($secondImport->id, $offer->import_id);
        $this->assertSame('Updated apartment', $offer->property->name);
    }

    public function test_failed_job_marks_import_as_failed(): void
    {
        $import = Import::factory()->for($this->supplier)->create([
            'status' => ImportStatus::Pending,
            'total_offers' => 1,
        ]);

        $job = new ProcessImport($import, [
            [
                'external_id' => 'broken-offer',
                'property' => [
                    'name' => 'Broken',
                    'city' => 'Barcelona',
                ],
                'check_in' => '2026-10-10',
                'check_out' => '2026-10-15',
                'max_guests' => 2,
                'price' => 1000,
                'currency' => 'EUR',
                'available_units' => 1,
                'expires_at' => '2026-09-10T23:59:59Z',
            ],
        ]);

        try {
            $job->handle();
            $this->fail('Expected ProcessImport to throw.');
        } catch (\Throwable) {
            //
        }

        $import->refresh();

        $this->assertSame(ImportStatus::Failed, $import->status);
        $this->assertNotNull($import->error);
        $this->assertNotNull($import->completed_at);
    }

    public function test_import_status_endpoint_returns_current_state(): void
    {
        $import = Import::factory()->for($this->supplier)->completed()->create([
            'external_import_id' => 'import-status-1',
            'total_offers' => 3,
            'processed_offers' => 3,
        ]);

        $this->getJson('/api/imports/'.$import->id)
            ->assertOk()
            ->assertJsonPath('data.id', $import->id)
            ->assertJsonPath('data.supplier', 'supplier-a')
            ->assertJsonPath('data.external_import_id', 'import-status-1')
            ->assertJsonPath('data.status', ImportStatus::Completed->value)
            ->assertJsonPath('data.total_offers', 3)
            ->assertJsonPath('data.processed_offers', 3)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'supplier',
                    'external_import_id',
                    'sent_at',
                    'status',
                    'total_offers',
                    'processed_offers',
                    'error',
                    'created_at',
                    'completed_at',
                ],
            ]);
    }
}
