<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Offer;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;

class ReservationController extends Controller
{
    public function store(
        StoreReservationRequest $request,
        Offer $offer,
        ReservationService $reservations,
    ): JsonResponse 
    {
        $reservation = $reservations->reserve($offer, $request->validated());

        return (new ReservationResource($reservation))
            ->response()
            ->setStatusCode(201);
    }
}
