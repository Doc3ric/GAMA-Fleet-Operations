<?php

use App\Http\Controllers\Api\V1\AssignmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TripController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public authentication
    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');

    // Protected Sanctum endpoints
    Route::middleware('auth:sanctum')->group(function () {
        // Auth & Profile
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::get('/profile', [AuthController::class, 'profile'])->name('api.v1.profile');

        // Active Vehicle Assignment
        Route::get('/assignment', [AssignmentController::class, 'current'])->name('api.v1.assignment');

        // Driver Trips
        Route::get('/trips', [TripController::class, 'index'])->name('api.v1.trips.index');
        Route::post('/trips', [TripController::class, 'store'])->name('api.v1.trips.store');
        Route::get('/trips/{trip}', [TripController::class, 'show'])->name('api.v1.trips.show');
        Route::patch('/trips/{trip}/end', [TripController::class, 'end'])->name('api.v1.trips.end');
        Route::post('/trips/{trip}/cancel', [TripController::class, 'cancel'])->name('api.v1.trips.cancel');

        // Offline Batch Sync
        Route::post('/sync', [SyncController::class, 'sync'])->name('api.v1.sync');
    });
});
