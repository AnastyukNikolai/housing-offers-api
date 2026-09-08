<?php

namespace App\Services;

use App\Enums\ImportStatus;
use App\Jobs\ProcessImport;
use App\Models\Import;
use App\Models\Supplier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ImportService
{
    public function accept(array $payload): Import
    {
        $supplier = Supplier::query()
            ->where('code', $payload['supplier'])
            ->firstOrFail();

        try {
            return DB::transaction(function () use ($supplier, $payload) {
                $import = Import::query()->create([
                    'supplier_id' => $supplier->id,
                    'external_import_id' => $payload['external_import_id'],
                    'sent_at' => $payload['sent_at'],
                    'status' => ImportStatus::Pending,
                    'total_offers' => count($payload['offers']),
                    'processed_offers' => 0,
                ]);

                ProcessImport::dispatch($import, $payload['offers'])->afterCommit();

                return $import;
            });
        } catch (UniqueConstraintViolationException) {
            return Import::query()
                ->where('supplier_id', $supplier->id)
                ->where('external_import_id', $payload['external_import_id'])
                ->firstOrFail();
        }
    }
}
