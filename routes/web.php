<?php

use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PixelforgeWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Pixelforge has no CSRF token; this route is excluded from CSRF in bootstrap/app.php.
Route::post('webhooks/pixelforge', PixelforgeWebhookController::class)
    ->name('webhooks.pixelforge');

Route::middleware(['auth'])->group(function () {
    Route::get('generate', [GenerationController::class, 'create'])->name('generations.create');
    Route::post('generate', [GenerationController::class, 'store'])->name('generations.store');
    Route::post('generate/estimate', [GenerationController::class, 'estimate'])->name('generations.estimate');

    Route::get('history', [GenerationController::class, 'index'])->name('generations.index');

    Route::get('credits', [CreditController::class, 'index'])->name('credits.index');
    Route::post('credits/purchase', [CreditController::class, 'purchase'])->name('credits.purchase');

    Route::get('admin/report', [ReportController::class, 'index'])
        ->middleware('admin')
        ->name('admin.report');

    Route::redirect('dashboard', '/generate')->name('dashboard');
});

require __DIR__.'/settings.php';
