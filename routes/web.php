<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

// Cover serving route (public, no auth required)
Route::get('/covers/{externalId}', [\App\Http\Controllers\CoverController::class, 'show'])
    ->name('covers.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

    // Books routes
    Route::get('books/search', [\App\Http\Controllers\BookController::class, 'search'])->name('books.search');
    Route::get('books/{book}', [\App\Http\Controllers\BookController::class, 'show'])->name('books.show');
    Route::post('books', [\App\Http\Controllers\BookController::class, 'store'])->name('books.store');

    // Reviews routes
    Route::get('reviews', [\App\Http\Controllers\ReviewController::class, 'index'])->name('reviews.index');
    Route::get('reviews/create', [\App\Http\Controllers\ReviewController::class, 'create'])->name('reviews.create');
    Route::get('reviews/{review}', [\App\Http\Controllers\ReviewController::class, 'show'])->name('reviews.show');
    Route::get('reviews/{review}/edit', [\App\Http\Controllers\ReviewController::class, 'edit'])->name('reviews.edit');
    Route::post('reviews', [\App\Http\Controllers\ReviewController::class, 'store'])->name('reviews.store');
    Route::patch('reviews/{review}', [\App\Http\Controllers\ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('reviews/{review}', [\App\Http\Controllers\ReviewController::class, 'destroy'])->name('reviews.destroy');

    // To Review Lists routes
    Route::get('to-review-lists', [\App\Http\Controllers\ToReviewListController::class, 'index'])->name('to-review-lists.index');
    Route::post('to-review-lists', [\App\Http\Controllers\ToReviewListController::class, 'store'])->name('to-review-lists.store');
    Route::delete('to-review-lists/{toReviewList}', [\App\Http\Controllers\ToReviewListController::class, 'destroy'])->name('to-review-lists.destroy');
    Route::post('to-review-lists/{toReviewList}/mark-reviewed', [\App\Http\Controllers\ToReviewListController::class, 'markReviewed'])->name('to-review-lists.mark-reviewed');
});

require __DIR__.'/settings.php';
