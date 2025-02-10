<?php

use App\Http\Controllers\CloudFarmerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('sync', [CloudFarmerController::class, 'sync']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/accounts', [CloudFarmerController::class, 'accounts']);
    Route::post('/accounts/{account}/disconnect', [CloudFarmerController::class, 'disconnect']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

require __DIR__ . '/auth.php';
