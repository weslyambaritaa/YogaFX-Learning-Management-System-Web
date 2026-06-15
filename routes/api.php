<?php

use App\Http\Controllers\Mobile\V1\AuthController;
use App\Http\Controllers\Mobile\V1\MeController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile/v1')
    ->as('mobile.api.v1.')
    ->group(function (): void {
        Route::post('/auth/login', [AuthController::class, 'store'])->name('auth.login');

        Route::middleware(['auth:sanctum', 'mobile.student'])->group(function (): void {
            Route::get('/me', MeController::class)->name('me.show');
        });

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/auth/logout', [AuthController::class, 'destroy'])->name('auth.logout');
        });
    });
