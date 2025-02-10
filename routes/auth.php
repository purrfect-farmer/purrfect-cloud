<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordUpdateController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest')
    ->name('login');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->name('logout');


/** Authenticated Routes */
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/update-password', [PasswordUpdateController::class, 'store'])
        ->name('password.update');
});
