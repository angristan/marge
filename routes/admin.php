<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CommentsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SetupController;
use App\Http\Middleware\AdminAuthenticated;
use App\Http\Middleware\RedirectIfSetupRequired;
use Illuminate\Support\Facades\Route;

// Setup (no auth required, but redirects if already setup)
Route::middleware([RedirectIfSetupRequired::class])->group(function (): void {
    Route::get('/setup', [SetupController::class, 'show'])->name('admin.setup');
    Route::post('/setup', [SetupController::class, 'store'])->name('admin.setup.store');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('admin.login');
    Route::post('/login', [AuthController::class, 'login'])->name('admin.login.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');

// Protected admin routes
Route::middleware([RedirectIfSetupRequired::class, AdminAuthenticated::class])->group(function (): void {
    Route::get('/', DashboardController::class)->name('admin.dashboard');

    // Comments
    Route::get('/comments', [CommentsController::class, 'index'])->name('admin.comments.index');
    Route::get('/comments/{comment}', [CommentsController::class, 'show'])->name('admin.comments.show');
    Route::post('/comments/{comment}/approve', [CommentsController::class, 'approve'])->name('admin.comments.approve');
    Route::post('/comments/{comment}/spam', [CommentsController::class, 'spam'])->name('admin.comments.spam');
    Route::delete('/comments/{comment}', [CommentsController::class, 'destroy'])->name('admin.comments.destroy');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('admin.settings.index');
    Route::post('/settings', [SettingsController::class, 'update'])->name('admin.settings.update');
});
