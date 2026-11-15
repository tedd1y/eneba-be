<?php

use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductFilterController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function () {
    // Store
    Route::get('products/filters', [ProductFilterController::class, 'index'])->name('products.filters.index');
    Route::apiResource('products', ProductController::class)->only(['index', 'show']);
});
