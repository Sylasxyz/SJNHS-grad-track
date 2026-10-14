<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->name('login.attempt');

Route::get('/register', [RegisterController::class, 'show'])->name('register');
Route::post('/register', [RegisterController::class, 'store'])
    ->middleware('throttle:register')
    ->name('register.store');

Route::get('/forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'handleForgot'])
    ->middleware('throttle:password-reset')
    ->name('password.email');
Route::get('/reset-password', [PasswordResetController::class, 'showReset'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'updatePassword'])->name('password.update');

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Role-only areas; each converted feature adds its routes to the matching group.
    Route::middleware('role:student')->prefix('student')->name('student.')->group(function () {
        //
    });

    Route::middleware('role:instructor')->prefix('instructor')->name('instructor.')->group(function () {
        //
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        //
    });
});
