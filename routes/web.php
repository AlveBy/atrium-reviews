<?php

declare(strict_types=1);

use Atrium\Reviews\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::localized(function (): void {
        $prefix = trim((string) config('atrium-reviews.prefix', 'reviews'), '/');

        Route::get($prefix, [ReviewController::class, 'index'])->name('atrium.reviews.index');
        Route::post($prefix, [ReviewController::class, 'store'])->name('atrium.reviews.store');
    });
});
