<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedSmallInteger('max_guests');
            $table->unsignedBigInteger('price');
            $table->string('currency', 3);
            $table->unsignedInteger('available_units');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['supplier_id', 'external_id']);
            $table->index(
                ['check_in', 'check_out', 'max_guests', 'available_units', 'expires_at'],
                'offers_availability_idx'
            );
            $table->index(['property_id', 'price'], 'offers_property_price_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
