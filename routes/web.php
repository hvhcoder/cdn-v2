<?php

use App\Http\Controllers\AbuseReportController;
use App\Http\Controllers\Admin\AbuseReportController as AdminAbuseReportController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UploadConfigController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController as UserDashboardController;
use App\Http\Controllers\EmbedController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\M3u8Controller;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\VideoFolderController;
use App\Http\Middleware\RateLimitEmbed;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/pricing', PricingController::class)->name('pricing');
Route::get('/m3u8/{video}.m3u8', M3u8Controller::class)->name('m3u8')->middleware(RateLimitEmbed::class.':120');
Route::get('/embed/{video}', EmbedController::class)->name('embed')->middleware(RateLimitEmbed::class.':120');
Route::post('/embed/{video}/report', [AbuseReportController::class, 'store'])->name('embed.report')->middleware('throttle:5,1');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', UserDashboardController::class)->name('dashboard');

    // /videos/create và /videos/presign phải đứng trước /videos/{video}.
    Route::get('/videos', [VideoController::class, 'index'])->name('videos.index');
    Route::get('/videos/create', [VideoController::class, 'create'])->name('videos.create');
    Route::post('/videos/presign', [VideoController::class, 'presign'])->middleware('throttle:30,1')->name('videos.presign');
    Route::post('/videos/move', VideoFolderController::class)->name('videos.move');

    Route::get('/folders', [FolderController::class, 'index'])->name('folders.index');
    Route::post('/folders', [FolderController::class, 'store'])->name('folders.store');
    Route::put('/folders/{folder}', [FolderController::class, 'update'])->name('folders.update');
    Route::delete('/folders/{folder}', [FolderController::class, 'destroy'])->name('folders.destroy');
    Route::get('/folders/{folder}/links', [FolderController::class, 'links'])->name('folders.links');

    Route::get('/videos/{video}', [VideoController::class, 'show'])->name('videos.show');
    Route::get('/videos/{video}/edit', [VideoController::class, 'edit'])->name('videos.edit');
    Route::put('/videos/{video}', [VideoController::class, 'update'])->name('videos.update');
    Route::delete('/videos/{video}', [VideoController::class, 'destroy'])->name('videos.destroy');
    Route::get('/videos/{video}/status', [VideoController::class, 'status'])->name('videos.status');
    Route::post('/videos/{video}/process', [VideoController::class, 'process'])->name('videos.process');
    Route::get('/subscription', [SubscriptionController::class, 'show'])->name('subscription');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::resource('plans', PlanController::class)->except(['show', 'destroy']);
        Route::resource('upload-configs', UploadConfigController::class)->except('show')->parameters(['upload-configs' => 'uploadConfig']);
        Route::put('upload-configs/{uploadConfig}/toggle', [UploadConfigController::class, 'toggle'])->name('upload-configs.toggle');
        Route::put('upload-configs/{uploadConfig}/default', [UploadConfigController::class, 'setDefault'])->name('upload-configs.default');
        Route::post('upload-configs/{uploadConfig}/test', [UploadConfigController::class, 'test'])->name('upload-configs.test');
        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.index');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::delete('settings', [SettingsController::class, 'reset'])->name('settings.reset');
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::put('users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
        Route::post('users/{user}/plan', [UserController::class, 'grantPlan'])->name('users.plan');
        Route::post('users/{user}/ban', [UserController::class, 'ban'])->name('users.ban');
        Route::post('users/{user}/unban', [UserController::class, 'unban'])->name('users.unban');
        Route::get('abuse-reports', [AdminAbuseReportController::class, 'index'])->name('abuse-reports.index');
        Route::put('abuse-reports/{abuseReport}', [AdminAbuseReportController::class, 'update'])->name('abuse-reports.update');
    });
});
