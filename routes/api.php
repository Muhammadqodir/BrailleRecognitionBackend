<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceAuthController;
use App\Http\Controllers\Api\AppleNotificationController;
use App\Http\Controllers\Api\AvailableLanguageController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\RevenueCatWebhookController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TranslationController;
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

    // Anonymous-first. The app calls /anonymous on a fresh install and /legacy
    // when it still holds a token from the old PHP backend, so an existing
    // install is recognised without ever showing a login screen.
    Route::post('/anonymous', [DeviceAuthController::class, 'anonymous']);
    Route::post('/legacy',    [DeviceAuthController::class, 'legacy']);

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me',     [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // Upgrades an anonymous account to a social one, carrying the
        // entitlement with it.
        Route::post('/link',    [DeviceAuthController::class, 'link']);
        Route::post('/profile', [DeviceAuthController::class, 'profile']);
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

/*
|--------------------------------------------------------------------------
| Available Languages
|--------------------------------------------------------------------------
*/
Route::get('/languages', [AvailableLanguageController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Analytics
|--------------------------------------------------------------------------
|
| Unauthenticated on purpose: the funnel starts before an account exists.
| A bearer token, when the client has one, enriches the row rather than
| gating it.
|
*/
Route::post('/events', [AnalyticsController::class, 'store']);

/*
|--------------------------------------------------------------------------
| RevenueCat webhook
|--------------------------------------------------------------------------
|
| Authenticated by the shared secret in the Authorization header, checked
| inside the controller — RevenueCat sends the bare value with no "Bearer "
| prefix, which Sanctum would reject.
|
*/
Route::post('/webhooks/revenuecat', [RevenueCatWebhookController::class, 'handle']);

/*
|--------------------------------------------------------------------------
| Signed-in surface
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/subscription', [SubscriptionController::class, 'show']);
    Route::post('/subscription/sync', [SubscriptionController::class, 'sync']);

    Route::get('/translations', [TranslationController::class, 'index']);
    Route::post('/translations', [TranslationController::class, 'store']);
    Route::patch('/translations/{translation}', [TranslationController::class, 'update']);
    Route::delete('/translations/{translation}', [TranslationController::class, 'destroy']);
});
