<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
    });
});
