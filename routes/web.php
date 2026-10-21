<?php

use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Settings\PasswordController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\TranslationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ProfileDeletionController;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/privacy-policy', function () {
    return view('privacy');
})->name('privacy.policy');

Route::get('/terms-of-use', function () {
    return view('terms');
})->name('terms.of.use');

Route::get('/remove-profile', [ProfileDeletionController::class, 'showForm'])->name('profile.deletion.form');
Route::post('/remove-profile', [ProfileDeletionController::class, 'submitRequest'])->name('profile.deletion.submit');

/*
| The admin dashboard. Its path segment comes from config('braille.admin_path')
| (random in production .env); the admin guard and the login throttle in
| App\Http\Requests\Admin\LoginRequest are what actually protect it.
*/
Route::prefix(config('braille.admin_path'))
    ->name('admin.')
    ->middleware(HandleInertiaRequests::class)
    ->group(function () {
        Route::middleware('guest:admin')->group(function () {
            Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
            Route::post('login', [AuthenticatedSessionController::class, 'store']);
        });

        Route::middleware('auth:admin')->group(function () {
            Route::redirect('/', 'dashboard');
            Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

            Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
            Route::get('users', [UserController::class, 'index'])->name('users');
            Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
            Route::get('translations', [TranslationController::class, 'index'])->name('translations');
            Route::get('translations/{translation}', [TranslationController::class, 'show'])->name('translations.show');
            Route::get('translations/{translation}/read', [TranslationController::class, 'read'])->name('translations.read');
            Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions');

            Route::get('settings/password', [PasswordController::class, 'edit'])->name('settings.password');
            Route::put('settings/password', [PasswordController::class, 'update'])->name('settings.password.update');
        });
    });
