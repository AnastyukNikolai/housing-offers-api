<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PropertySearchService
{
    public function search(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = isset($filters['page']) ? (int) $filters['page'] : null;

        $ranked = DB::table('offers')
            ->join('properties', 'properties.id', '=', 'offers.property_id')
            ->join('suppliers', 'suppliers.id', '=', 'offers.supplier_id')
            ->select([
                'properties.code as property_code',
                'properties.name as property_name',
                'properties.city as property_city',
                'offers.id as offer_id',
                'suppliers.code as supplier_code',
                'offers.external_id',
                'offers.check_in',
                'offers.check_out',
                'offers.max_guests',
                'offers.price',
                'offers.currency',
                'offers.available_units',
                'offers.expires_at',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY offers.property_id ORDER BY offers.price ASC, offers.id ASC) as offer_rank'),
            ])
            ->whereDate('offers.check_in', $filters['check_in'])
            ->whereDate('offers.check_out', $filters['check_out'])
            ->where('offers.max_guests', '>=', (int) $filters['guests'])
            ->where('offers.available_units', '>', 0)
            ->where('offers.expires_at', '>', now())
            ->when(
                filled($filters['city'] ?? null),
                fn ($query) => $query->where('properties.city', $filters['city'])
            );

        return DB::query()
            ->fromSub($ranked, 'ranked_offers')
            ->where('offer_rank', 1)
            ->orderBy('price')
            ->orderBy('offer_id')
            ->paginate($perPage, ['*'], 'page', $page)
            ->appends(collect($filters)->only(['city', 'check_in', 'check_out', 'guests', 'per_page'])->all());
    }
}
