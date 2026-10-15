<?php

use App\Http\Controllers\Admin\MailTestController;
use App\Http\Controllers\Admin\SecurityAlertController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GraduateController;
use App\Http\Controllers\Instructor\StudentAccountController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfilePictureController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Student\ProfileController;
use App\Models\Announcement;
use App\Models\GraduateProfile;
use App\Models\Report;
use Illuminate\Support\Facades\Route;

Route::view('/', 'public.home')->name('home');
Route::view('/about', 'public.about')->name('about');
Route::view('/privacy', 'public.privacy')->name('privacy');
Route::view('/terms', 'public.terms')->name('terms');

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

// Old instructor_preview.php only redirected (without a login check) to instructor/dashboard.
Route::get('/instructor_preview', fn () => redirect()->route('instructor.dashboard'));

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements');
    Route::get('/announcements/create', [AnnouncementController::class, 'create'])
        ->can('create', Announcement::class)->name('announcements.create');
    Route::post('/announcements', [AnnouncementController::class, 'store'])
        ->can('create', Announcement::class)->name('announcements.store');
    Route::whereNumber('announcement')->group(function () {
        Route::get('/announcements/{announcement}/image', [AnnouncementController::class, 'image'])
            ->can('view', 'announcement')->name('announcements.image');
        Route::patch('/announcements/{announcement}/archive', [AnnouncementController::class, 'archive'])
            ->can('archive', 'announcement')->name('announcements.archive');
        Route::patch('/announcements/{announcement}/restore', [AnnouncementController::class, 'restore'])
            ->can('restore', 'announcement')->name('announcements.restore');
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])
            ->can('delete', 'announcement')->name('announcements.destroy');
    });

    Route::get('/messages', [MessageController::class, 'index'])->name('messages');
    Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::whereNumber('partner')->group(function () {
        Route::get('/messages/{partner}', [MessageController::class, 'show'])
            ->can('openConversation', 'partner')->name('messages.show');
        Route::post('/messages/{partner}', [MessageController::class, 'reply'])
            ->can('message', 'partner')->name('messages.reply');
    });

    // Role-only areas; each converted feature adds its routes to the matching group.
    Route::middleware('can:viewAny,'.GraduateProfile::class)->group(function () {
        Route::get('/graduates', [GraduateController::class, 'index'])->name('graduates');
        Route::get('/graduates/export', [GraduateController::class, 'export'])->name('graduates.export');
        Route::patch('/graduates/verify', [GraduateController::class, 'verifyBulk'])
            ->can('verify', GraduateProfile::class)->name('graduates.verify');
        Route::whereNumber('profile')->group(function () {
            Route::get('/graduates/{profile}/edit', [GraduateController::class, 'edit'])
                ->can('update', 'profile')->name('graduates.edit');
            Route::put('/graduates/{profile}', [GraduateController::class, 'update'])
                ->can('update', 'profile')->name('graduates.update');
            Route::delete('/graduates/{profile}', [GraduateController::class, 'destroy'])
                ->can('delete', 'profile')->name('graduates.destroy');
        });
    });

    Route::middleware('can:viewAny,'.Report::class)->group(function () {
        $reportNotFound = fn () => redirect()->route('reports')->with('error', 'Report not found.');

        Route::get('/admin/reports', [ReportController::class, 'index'])->name('reports');
        Route::post('/admin/reports', [ReportController::class, 'store'])
            ->can('create', Report::class)->name('reports.store');
        Route::whereNumber('report')->group(function () use ($reportNotFound) {
            Route::get('/admin/reports/{report}', [ReportController::class, 'show'])
                ->can('view', 'report')->missing($reportNotFound)->name('reports.show');
            Route::get('/admin/reports/{report}/export', [ReportController::class, 'export'])
                ->can('view', 'report')->missing($reportNotFound)->name('reports.export');
            Route::delete('/admin/reports/{report}', [ReportController::class, 'destroy'])
                ->can('delete', 'report')->missing($reportNotFound)->name('reports.destroy');
        });
    });

    Route::middleware('can:viewAnalytics')->group(function () {
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
        Route::post('/analytics/snapshot', [AnalyticsController::class, 'storeSnapshot'])
            ->can('create', Report::class)->name('analytics.snapshot');
    });

    Route::get('/profiles/{profile}/picture', ProfilePictureController::class)
        ->whereNumber('profile')->can('view', 'profile')->name('profiles.picture');

    Route::middleware('role:student')->prefix('student')->name('student.')->group(function () {
        Route::middleware('can:manageOwn,'.GraduateProfile::class)->group(function () {
            Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
            Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::delete('/profile/picture', [ProfileController::class, 'destroyPicture'])->name('profile.picture.destroy');
        });
    });

    Route::middleware('role:instructor')->prefix('instructor')->name('instructor.')->group(function () {
        //
    });

    // Old instructor/dashboard.php only redirected to the shared dashboard.
    Route::get('/instructor/dashboard', fn () => redirect()->route('dashboard'))->name('instructor.dashboard');

    // Manage Student Accounts: like the legacy instructor/users.php, Admins may use it too.
    Route::middleware('role:instructor,admin')->prefix('instructor')->name('instructor.')->group(function () {
        Route::get('/', fn () => redirect()->route('dashboard'))->name('index');
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

        Route::get('/mail_test', [MailTestController::class, 'show'])->name('mail-test');
        Route::post('/mail_test', [MailTestController::class, 'send'])->name('mail-test.send');
    });
});
