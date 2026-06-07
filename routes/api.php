<?php

use App\Http\Controllers\Api\Admin\BusinessController as AdminBusinessController;
use App\Http\Controllers\Api\Admin\BusinessImageController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Api\Admin\SubcategoryController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ReviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    /*
     | Public endpoints
     */
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('businesses', [BusinessController::class, 'index']);
    Route::get('businesses/{slug}', [BusinessController::class, 'show']);
    Route::get('businesses/{slug}/reviews', [ReviewController::class, 'index']);
    Route::post('businesses/{slug}/reviews', [ReviewController::class, 'store'])
        ->middleware('throttle:reviews');

    Route::post('login', [AuthController::class, 'login']);

    /*
     | Admin endpoints (Sanctum token + Bouncer abilities), prefixed with /admin
     */
    Route::middleware('auth:sanctum')->prefix('admin')->name('admin.')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        Route::middleware('can:manage-businesses')->group(function () {
            Route::apiResource('businesses', AdminBusinessController::class);
            Route::post('businesses/{business}/images', [BusinessImageController::class, 'store']);
            Route::put('businesses/{business}/images/reorder', [BusinessImageController::class, 'reorder']);
            Route::delete('images/{image}', [BusinessImageController::class, 'destroy']);
        });

        Route::middleware('can:manage-categories')->group(function () {
            Route::apiResource('categories', AdminCategoryController::class)->except(['show']);
            Route::apiResource('subcategories', SubcategoryController::class)->except(['show']);
        });

        Route::middleware('can:moderate-reviews')->group(function () {
            Route::get('reviews', [AdminReviewController::class, 'index']);
            Route::delete('reviews/{review}', [AdminReviewController::class, 'destroy']);
        });

        Route::middleware('can:manage-users')->group(function () {
            Route::apiResource('users', UserController::class)->except(['show']);
            Route::put('users/{user}/roles', [UserController::class, 'syncRoles']);
        });
    });
});
