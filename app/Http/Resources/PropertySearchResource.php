<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class PropertySearchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->property_code,
            'name' => $this->property_name,
            'city' => $this->property_city,
            'best_offer' => [
                'id' => $this->offer_id,
                'supplier' => $this->supplier_code,
                'external_id' => $this->external_id,
                'check_in' => $this->check_in,
                'check_out' => $this->check_out,
                'max_guests' => (int) $this->max_guests,
                'price' => (int) $this->price,
                'currency' => $this->currency,
                'available_units' => (int) $this->available_units,
                'expires_at' => $this->expires_at !== null
                    ? Carbon::parse($this->expires_at)->toIso8601String()
                    : null,
            ],
        ];
    }
}
