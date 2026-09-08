<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchPropertiesRequest;
use App\Http\Resources\PropertySearchCollection;
use App\Services\PropertySearchService;

class PropertyController extends Controller
{
    public function index(SearchPropertiesRequest $request, PropertySearchService $search): PropertySearchCollection
    {
        $properties = $search->search($request->validated());

        return new PropertySearchCollection($properties);
    }
}
