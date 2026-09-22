<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QrImageController;
use App\Http\Controllers\RedirectController;
use App\Http\Controllers\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
|
| Everything a printed product touches lives here and must never require a
| login. The redirect route is deliberately the shortest path in the app.
|
*/

Route::get('/', function () {
    if ($user = request()->user()) {
        return redirect()->route($user->isStaff() ? 'admin.dashboard' : 'dashboard');
    }

    return view('welcome');
})->name('home');

$prefix = trim((string) config('qr.redirect_prefix', 'q'), '/');

// The link encoded in every QR image. Throttled generously: real scan traffic
// is bursty (a queue at a counter) but this still blocks scripted hammering.
Route::get($prefix.'/{code}', RedirectController::class)
    ->name('qr.redirect')
    ->middleware('throttle:240,1')
    ->where('code', '[A-Za-z0-9]+');

// Printable artwork. Public so print shops and customers can fetch it directly.
Route::get('qr/{code}.{format}', [QrImageController::class, 'show'])
    ->name('qr.image')
    ->where('code', '[A-Za-z0-9]+')
    ->where('format', 'png|svg');

Route::get('qr/{code}/download/{format?}', [QrImageController::class, 'download'])
    ->name('qr.download')
    ->where('code', '[A-Za-z0-9]+')
    ->where('format', 'png|svg');

/*
|--------------------------------------------------------------------------
| Customer dashboard
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', User\DashboardController::class)->name('dashboard');

    Route::get('/qr-codes', [User\QrCodeController::class, 'index'])->name('qr-codes.index');
    // Declared before /qr-codes/{qrCode} so "export" is never read as a code.
    Route::get('/qr-codes/export', [User\QrCodeController::class, 'export'])->name('qr-codes.export');
    Route::get('/qr-codes/{qrCode}', [User\QrCodeController::class, 'show'])->name('qr-codes.show');
    Route::patch('/qr-codes/{qrCode}', [User\QrCodeController::class, 'update'])->name('qr-codes.update');
    Route::post('/qr-codes/{qrCode}/toggle', [User\QrCodeController::class, 'toggle'])->name('qr-codes.toggle');

    Route::get('/analytics', [User\AnalyticsController::class, 'index'])->name('analytics');
    Route::get('/analytics/export', [User\AnalyticsController::class, 'export'])->name('analytics.export');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| Admin area
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'active', 'staff'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::get('/analytics', [Admin\AnalyticsController::class, 'index'])->name('analytics');
        Route::get('/analytics/export', [Admin\AnalyticsController::class, 'export'])->name('analytics.export');
        Route::get('/activity', Admin\ActivityController::class)->name('activity');

        // Customers
        Route::get('/users/export', [Admin\UserController::class, 'export'])->name('users.export');
        Route::post('/users/{user}/toggle', [Admin\UserController::class, 'toggle'])->name('users.toggle');
        Route::post('/users/{user}/reset-password', [Admin\UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::resource('users', Admin\UserController::class);

        // QR inventory
        Route::get('/qr-codes/export', [Admin\QrCodeController::class, 'export'])->name('qr-codes.export');
        Route::get('/qr-codes/download', [Admin\QrCodeController::class, 'downloadZip'])->name('qr-codes.download');
        Route::post('/qr-codes/bulk', [Admin\QrCodeController::class, 'bulk'])->name('qr-codes.bulk');
        Route::post('/qr-codes/{qrCode}/assign', [Admin\QrCodeController::class, 'assign'])->name('qr-codes.assign');
        Route::resource('qr-codes', Admin\QrCodeController::class)
            ->parameters(['qr-codes' => 'qrCode'])
            ->except(['edit']);

        // Batches
        Route::get('/batches/{batch}/download', [Admin\BatchController::class, 'download'])->name('batches.download');
        Route::get('/batches/{batch}/print', [Admin\BatchController::class, 'printSheet'])->name('batches.print');
        Route::resource('batches', Admin\BatchController::class)->except(['edit', 'update']);
    });

require __DIR__.'/auth.php';
