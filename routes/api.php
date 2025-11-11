<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/books/search', [\App\Http\Controllers\Api\BookSearchController::class, 'search'])
        ->name('api.books.search');
});
