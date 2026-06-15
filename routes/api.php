<?php

use App\Http\Controllers\Api\WaitlistController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Вайтлист — pre-account, ВНЕ couple-context. Rate-limited, без рефералов.
Route::prefix('waitlist')->group(function () {
    Route::post('join', [WaitlistController::class, 'join'])->middleware('throttle:10,1');
    Route::get('confirm/{token}', [WaitlistController::class, 'confirm'])->middleware('throttle:30,1');
    Route::get('status/{token}', [WaitlistController::class, 'status'])->middleware('throttle:60,1');
});
