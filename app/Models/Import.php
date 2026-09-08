<?php

namespace App\Models;

use App\Enums\ImportStatus;
use Database\Factories\ImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'supplier_id',
    'external_import_id',
    'sent_at',
    'status',
    'total_offers',
    'processed_offers',
    'error',
    'completed_at',
])]
class Import extends Model
{
    /** @use HasFactory<ImportFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'sent_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'total_offers' => 'integer',
            'processed_offers' => 'integer',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }
}
