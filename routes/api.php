<?php

use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\OrderPaymentController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductFilterController;
use App\Http\Controllers\Api\V1\SideMenuController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function () {
    // Store
    Route::get('products/filters', [ProductFilterController::class, 'index'])->name('products.filters.index');
    Route::apiResource('products', ProductController::class)->only(['index', 'show']);

    // Navigation
    Route::get('menus/sidemenu', [SideMenuController::class, 'index'])->name('menus.sidemenu.index');

    // Checkout
    Route::prefix('orders')->name('orders.')->whereUuid('order')->group(function () {
        Route::post('/', [OrderController::class, 'store'])->middleware('throttle:orders.store')->name('store');
        Route::get('{order}', [OrderController::class, 'show'])->name('show');
        Route::post('{order}/pay', [OrderPaymentController::class, 'store'])->middleware('throttle:orders.pay')->name('pay');
    });
});
