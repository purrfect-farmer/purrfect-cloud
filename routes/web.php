<?php

use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

Route::get('payments/verify', [PaymentController::class, 'verifyWeb'])->name('payments.verify');
