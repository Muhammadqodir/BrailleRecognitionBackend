<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AppleNotificationController;
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

/*
|--------------------------------------------------------------------------
| Apple Server-to-Server Notifications
|--------------------------------------------------------------------------
|
| Apple posts signed JWTs here when users change email relay preferences,
| revoke app consent, or permanently delete their Apple ID.
|
| Register this URL in App Store Connect → your app → Sign in with Apple
| → Server to Server Notification Endpoint:
|
|   https://your-domain.com/api/apple/notifications
|
| Requirements: TLS 1.2+, publicly reachable, no auth header required.
|
*/
Route::post('/apple/notifications', [AppleNotificationController::class, 'handle'])
    ->name('apple.notifications');
