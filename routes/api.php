<?php

use App\Http\Controllers\Api\Auth\MagicLinkController;
use App\Http\Controllers\Api\Auth\SessionController;
use App\Http\Controllers\Api\V1\CurrentUserController;
use App\Http\Controllers\Api\V1\GenreController;
use App\Http\Controllers\Api\V1\HomeGalleryController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\OrderPaymentController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductFilterController;
use App\Http\Controllers\Api\V1\SideMenuController;
use Illuminate\Support\Facades\Route;

// Authentication: passwordless sign-in with emailed magic links, using the Sanctum session cookie.
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('magic-link', [MagicLinkController::class, 'store'])->middleware('throttle:magic-link.request')->name('magic-link.store');
    Route::post('magic-link/verify', [MagicLinkController::class, 'verify'])->middleware('throttle:magic-link.verify')->name('magic-link.verify');
    Route::post('logout', [SessionController::class, 'destroy'])->middleware('auth:sanctum')->name('logout');
});

Route::prefix('v1')->name('v1.')->group(function () {
    // Account
    Route::get('me', [CurrentUserController::class, 'show'])->middleware('auth:sanctum')->name('me.show');

    // Store
    Route::get('products/filters', [ProductFilterController::class, 'index'])->name('products.filters.index');
    Route::apiResource('products', ProductController::class)->only(['index', 'show']);
    Route::apiResource('genres', GenreController::class)->only(['index']);

    // Navigation
    Route::get('menus/sidemenu', [SideMenuController::class, 'index'])->name('menus.sidemenu.index');
    Route::get('home/galleries', [HomeGalleryController::class, 'index'])->name('home.galleries.index');

    // Checkout
    Route::prefix('orders')->name('orders.')->whereUuid('order')->group(function () {
        Route::post('/', [OrderController::class, 'store'])->middleware('throttle:orders.store')->name('store');
        Route::get('{order}', [OrderController::class, 'show'])->name('show');
        Route::post('{order}/pay', [OrderPaymentController::class, 'store'])->middleware('throttle:orders.pay')->name('pay');
    });
});
