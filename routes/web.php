<?php

use App\Http\Controllers\ProfileDeletionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/privacy-policy', function () {
    return view('privacy');
});

Route::get('/remove-profile', [ProfileDeletionController::class, 'showForm'])->name('profile.deletion.form');
Route::post('/remove-profile', [ProfileDeletionController::class, 'submitRequest'])->name('profile.deletion.submit');
