<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes here are prefixed with /api automatically by bootstrap/app.php
|
*/

Route::prefix('auth')->group(function () {

    // Public routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);

    // Social sign-in
    Route::post('/google', [AuthController::class, 'googleSignIn']);
    Route::post('/apple',  [AuthController::class, 'appleSignIn']);

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me',     [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});
