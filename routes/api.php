<?php

use App\Http\Controllers\ImportController;
use App\Http\Controllers\PropertyController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::post('/imports', [ImportController::class, 'store']);
Route::get('/imports/{import}', [ImportController::class, 'show']);
Route::get('/properties', [PropertyController::class, 'index']);
