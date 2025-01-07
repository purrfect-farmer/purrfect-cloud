<?php

use App\Http\Controllers\CloudFarmerController;
use Illuminate\Support\Facades\Route;

Route::post('sync', [CloudFarmerController::class, 'sync']);
