<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:web'])->group(function () {
    Route::match(['get', 'post'], '/books/search', [\App\Http\Controllers\Api\BookSearchController::class, 'search'])
        ->name('api.books.search');
});
