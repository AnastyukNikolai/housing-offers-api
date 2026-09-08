<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Throwable;

class ProcessImport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3600;

    public function __construct(
        public Import $import,
        public array $offers,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->import->id;
    }

    public function handle(): void
    {
        $import = $this->import->fresh();

        if ($import === null) {
            return;
        }

        if (in_array($import->status, [ImportStatus::Completed, ImportStatus::Processing], true)) {
            return;
        }

        $import->update([
            'status' => ImportStatus::Processing,
            'error' => null,
        ]);

        try {
            $processed = $this->processOffers($import);

            $import->update([
                'status' => ImportStatus::Completed,
                'processed_offers' => $processed,
                'completed_at' => now(),
                'error' => null,
            ]);
        } catch (Throwable $exception) {
            $import->update([
                'status' => ImportStatus::Failed,
                'error' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            throw $exception;
        }
    }

    private function processOffers(Import $import): int
    {
        if ($this->offers === []) {
            return 0;
        }

        $now = now();
        $propertiesByCode = [];

        foreach ($this->offers as $offerData) {
            $code = $offerData['property']['code'];
            $propertiesByCode[$code] = [
                'code' => $code,
                'name' => $offerData['property']['name'],
                'city' => $offerData['property']['city'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Property::query()->upsert(
            array_values($propertiesByCode),
            ['code'],
            ['name', 'city', 'updated_at']
        );

        $propertyIds = Property::query()
            ->whereIn('code', array_keys($propertiesByCode))
            ->pluck('id', 'code');

        $offerRows = [];

        foreach ($this->offers as $offerData) {
            $offerRows[] = [
                'supplier_id' => $import->supplier_id,
                'import_id' => $import->id,
                'property_id' => $propertyIds[$offerData['property']['code']],
                'external_id' => $offerData['external_id'],
                'check_in' => Carbon::parse($offerData['check_in'])->toDateString(),
                'check_out' => Carbon::parse($offerData['check_out'])->toDateString(),
                'max_guests' => $offerData['max_guests'],
                'price' => $offerData['price'],
                'currency' => $offerData['currency'],
                'available_units' => $offerData['available_units'],
                'expires_at' => Carbon::parse($offerData['expires_at'])->toDateTimeString(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($offerRows, 500) as $chunk) {
            Offer::query()->upsert(
                $chunk,
                ['supplier_id', 'external_id'],
                [
                    'import_id',
                    'property_id',
                    'check_in',
                    'check_out',
                    'max_guests',
                    'price',
                    'currency',
                    'available_units',
                    'expires_at',
                    'updated_at',
                ]
            );
        }

        return count($offerRows);
    }
}
