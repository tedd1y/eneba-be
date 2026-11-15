<?php

use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function () {
    // Store
    Route::apiResource('products', ProductController::class)->only(['index', 'show']);
});
