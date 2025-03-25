<?php

use App\Http\Controllers\CloudFarmerController;
use App\Http\Controllers\PaymentController;
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

/** Cloud Farmer */
Route::post('sync', [CloudFarmerController::class, 'sync']);
Route::post('subscription', [CloudFarmerController::class, 'subscription']);


/** Payment */
Route::middleware('feature:farmer.enable_payments')->prefix('payments')->group(function () {
    Route::post('initialize', [PaymentController::class, 'initialize'])->name('api.payments.initialize');
    Route::post('verify', [PaymentController::class, 'verify'])->name('api.payments.verify');
});


/** Telegram */
Route::middleware('feature:farmer.enable_telegram_sessions')->prefix('telegram')->group(function () {
    Route::post('login', [TelegramController::class, 'login']);
    Route::post('code', [TelegramController::class, 'code']);
    Route::post('password', [TelegramController::class, 'password']);
    Route::post('logout', [TelegramController::class, 'logout']);
    Route::post('session', [TelegramController::class, 'session']);
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
