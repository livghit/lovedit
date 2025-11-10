<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchBooksRequest;
use App\Http\Requests\StoreBookRequest;
use App\Models\Book;
use App\Services\BookSearchService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class BookController extends Controller
{
    public function __construct(private BookSearchService $searchService) {}

    public function search(SearchBooksRequest $request): Response
    {
        $validated = $request->validated();
        $query = $validated['query'] ?? null;
        $author = $validated['author'] ?? null;

        if ($query) {
            $results = $this->searchService->search($query, $author);

            return Inertia::render('books/search', [
                'results' => $results,
                'query' => $query,
                'books' => null,
            ]);
        }

        $books = Inertia::scroll(fn () => Book::query()->latest('id')->paginate(12));

        return Inertia::render('books/search', [
            'results' => [],
            'query' => null,
            'books' => $books,
        ]);

    }

    public function show(Book $book): Response
    {
        $bookWithRelations = $book->load('reviews');

        $userReview = auth()->user() ? $bookWithRelations->reviews()->where('user_id', auth()->id())->first() : null;
        $isInToReviewList = auth()->user() ? $book->toReviewLists()->where('user_id', auth()->id())->exists() : false;

        return Inertia::render('books/show', [
            'book' => $bookWithRelations,
            'userReview' => $userReview,
            'isInToReviewList' => $isInToReviewList,
        ]);
    }

    public function store(StoreBookRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (! empty($validated['external_id'])) {
            $existingBook = Book::where('external_id', $validated['external_id'])->first();
            if ($existingBook) {
                return response()->json(['data' => $existingBook], 200);
            }
        }

        $book = Book::create($validated);

        return response()->json([
            'data' => $book,
        ], 201);
    }
}
