<?php

namespace App\Services;

use App\Exceptions\OfferUnavailableException;
use App\Models\Offer;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    public function reserve(Offer $offer, array $payload): Reservation
    {
        return DB::transaction(function () use ($offer, $payload) {
            $lockedOffer = Offer::query()
                ->whereKey($offer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOffer->available_units <= 0 || $lockedOffer->expires_at->isPast()) {
                throw new OfferUnavailableException('Offer is no longer available.');
            }

            $lockedOffer->decrement('available_units');

            return Reservation::query()->create([
                'offer_id' => $lockedOffer->id,
                'client_reference' => $payload['client_reference'],
                'customer_name' => $payload['customer_name'],
                'customer_email' => $payload['customer_email'],
            ]);
        }, 3);
    }
}
