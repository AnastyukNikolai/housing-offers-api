<?php

use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::post('/imports', [ImportController::class, 'store']);
Route::get('/imports/{import}', [ImportController::class, 'show']);
