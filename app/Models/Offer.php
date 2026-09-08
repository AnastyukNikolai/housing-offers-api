<?php

namespace App\Models;

use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'supplier_id',
    'import_id',
    'property_id',
    'external_id',
    'check_in',
    'check_out',
    'max_guests',
    'price',
    'currency',
    'available_units',
    'expires_at',
])]
class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'expires_at' => 'immutable_datetime',
            'max_guests' => 'integer',
            'price' => 'integer',
            'available_units' => 'integer',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function scopeAvailable(Builder $query, ?Carbon $at = null): Builder
    {
        return $query
            ->where('available_units', '>', 0)
            ->where('expires_at', '>', $at ?? now());
    }

    public function scopeForStay(Builder $query, string $checkIn, string $checkOut, int $guests): Builder
    {
        return $query
            ->whereDate('check_in', $checkIn)
            ->whereDate('check_out', $checkOut)
            ->where('max_guests', '>=', $guests);
    }
}
