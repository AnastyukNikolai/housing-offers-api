<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImportRequest;
use App\Http\Resources\ImportAcceptedResource;
use App\Http\Resources\ImportResource;
use App\Models\Import;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;

class ImportController extends Controller
{
    public function store(StoreImportRequest $request, ImportService $imports): JsonResponse
    {
        $import = $imports->accept($request->validated());

        return (new ImportAcceptedResource($import))
            ->response()
            ->setStatusCode(202);
    }

    public function show(Import $import): ImportResource
    {
        $import->load('supplier');

        return new ImportResource($import);
    }
}
