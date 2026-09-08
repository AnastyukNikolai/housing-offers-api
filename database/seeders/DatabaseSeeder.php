<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['supplier-a', 'supplier-b'] as $code) {
            Supplier::query()->firstOrCreate(['code' => $code]);
        }
    }
}
