<?php

use App\Http\Controllers\CloudFarmerController;
use App\Http\Controllers\TelegramController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/** Server */
Route::get('server', function () {
    return [
        'name' => config('app.name'),
        'farmers' => collect(config('farmer.drops'))
            ->filter(fn($drop) => $drop['enabled'])
            ->keys(),
        'is_telegram_sessions_enabled' => config('farmer.enable_telegram_sessions')
    ];
});

/** Sync */
Route::post('sync', [CloudFarmerController::class, 'sync']);

/** Telegram */
Route::middleware('feature:farmer.enable_telegram_sessions')->prefix('telegram')->group(function () {
    Route::post('login', [TelegramController::class, 'login']);
    Route::post('code', [TelegramController::class, 'code']);
    Route::post('password', [TelegramController::class, 'password']);
    Route::post('logout', [TelegramController::class, 'logout']);
    Route::post('check', [TelegramController::class, 'check']);
});

/** Authenticated Routes */
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/farmers', [CloudFarmerController::class, 'farmers']);
    Route::post('/farmers/{farmer}/disconnect', [CloudFarmerController::class, 'disconnect']);
    Route::post('/members/{id}/kick', [CloudFarmerController::class, 'kick']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});


require __DIR__ . '/auth.php';
