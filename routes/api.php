<?php

use App\Http\Controllers\CloudFarmerController;
use App\Http\Controllers\TelegramController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/** Server */
Route::get('server', function () {
    return [
        'name' => config('app.name'),
        'is_telegram_sessions_enabled' => config('farmer.enable_telegram_sessions')
    ];
});

/** Farmers */
Route::get('farmers', function () {
    return collect(config('farmer.drops'))->keys();
});

/** Sync */
Route::post('sync', [CloudFarmerController::class, 'sync']);


/** Telegram */
Route::middleware('feature:farmer.enable_telegram_sessions')->prefix('telegram')->group(function () {
    Route::post('login', [TelegramController::class, 'login']);
    Route::post('code', [TelegramController::class, 'code']);
    Route::post('password', [TelegramController::class, 'password']);
    Route::post('logout', [TelegramController::class, 'logout']);
});

/** Authenticated Routes */
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/accounts', [CloudFarmerController::class, 'accounts']);
    Route::post('/accounts/{account}/disconnect', [CloudFarmerController::class, 'disconnect']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});


require __DIR__ . '/auth.php';
