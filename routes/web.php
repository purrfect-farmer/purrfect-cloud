<?php

use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

Route::middleware('feature:farmer.enable_payments')
    ->get('payments/verify', [PaymentController::class, 'verifyWeb'])
    ->name('payments.verify');