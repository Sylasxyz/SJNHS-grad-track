<?php

use App\Http\Controllers\Admin\SecurityAlertController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Instructor\StudentAccountController;
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

    // Manage Student Accounts: like the legacy instructor/users.php, Admins may use it too.
    Route::middleware('role:instructor,admin')->prefix('instructor')->name('instructor.')->group(function () {
        Route::get('/users', [StudentAccountController::class, 'index'])->name('users');
        Route::patch('/users/{student}/approve', [StudentAccountController::class, 'approve'])
            ->whereNumber('student')->can('manageStudent', 'student')->name('users.approve');
        Route::patch('/users/{student}/status', [StudentAccountController::class, 'updateStatus'])
            ->whereNumber('student')->can('manageStudent', 'student')->name('users.status');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');

        Route::middleware('can:manage,user')->whereNumber('user')->group(function () {
            Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::patch('/users/{user}/access', [UserController::class, 'updateAccess'])->name('users.access');
            Route::patch('/users/{user}/approve', [UserController::class, 'approve'])->name('users.approve');
            Route::patch('/users/{user}/decline', [UserController::class, 'decline'])->name('users.decline');
            Route::patch('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        });

        Route::delete('/security-alerts/account', [SecurityAlertController::class, 'clearAccount'])->name('security-alerts.clear-account');
        Route::delete('/security-alerts/ip', [SecurityAlertController::class, 'dismissIp'])->name('security-alerts.dismiss-ip');
    });
});
