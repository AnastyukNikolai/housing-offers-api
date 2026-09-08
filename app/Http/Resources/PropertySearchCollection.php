<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PropertySearchCollection extends ResourceCollection
{
    public $collects = PropertySearchResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'next' => $this->resource->nextPageUrl(),
            'prev' => $this->resource->previousPageUrl(),
            'per_page' => $this->resource->perPage(),
        ];
    }

    public function paginationInformation($request, $paginated, $default): array
    {
        return [];
    }
}
