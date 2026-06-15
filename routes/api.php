<?php

use App\Http\Controllers\Api\Couple\BoardController;
use App\Http\Controllers\Api\Couple\CardController;
use App\Http\Controllers\Api\Couple\ColumnController;
use App\Http\Controllers\Api\TelegramWebhookController;
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

// Telegram webhook — секьюрится secret-токеном внутри контроллера (вне couple-context).
Route::post('telegram/webhook', TelegramWebhookController::class)->middleware('throttle:120,1');

// Couple-scoped (за auth:sanctum + ResolveCoupleContext). Данные изолированы CoupleScoped.
Route::prefix('couple')->middleware(['auth:sanctum', 'couple.context'])->group(function () {
    Route::get('board', [BoardController::class, 'show']);

    Route::post('columns', [ColumnController::class, 'store']);
    Route::patch('columns/reorder', [ColumnController::class, 'reorder']);
    Route::match(['put', 'patch'], 'columns/{columnId}', [ColumnController::class, 'update']);
    Route::delete('columns/{columnId}', [ColumnController::class, 'destroy']);

    Route::post('cards', [CardController::class, 'store']);
    Route::patch('cards/{cardId}/move', [CardController::class, 'move']);
    Route::patch('cards/{cardId}/toggle-done', [CardController::class, 'toggleDone']);
    Route::match(['put', 'patch'], 'cards/{cardId}', [CardController::class, 'update']);
    Route::delete('cards/{cardId}', [CardController::class, 'destroy']);
});
