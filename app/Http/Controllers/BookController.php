<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchBooksRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\StoreManualBookRequest;
use App\Models\Book;
use App\Services\BookSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
        $forceOnline = (bool) ($validated['online'] ?? false);

        if ($query) {
            $results = $this->searchService->search($query, $forceOnline);

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

        // Check if book already exists by external_id
        if (! empty($validated['external_id'])) {
            $existingBook = Book::where('external_id', $validated['external_id'])->first();
            if ($existingBook) {
                return response()->json(['data' => $existingBook], 200);
            }
        }

        // Create the book with basic info
        $book = Book::create($validated);

        // Dispatch background job to fetch work details (description, subjects, etc.)
        if ($book->ol_work_key) {
            \App\Jobs\FetchBookWorkDetails::dispatch($book);
        }

        return response()->json([
            'data' => $book,
        ], 201);
    }

    public function storeManual(StoreManualBookRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Create book with manual data
        $book = Book::create([
            'title' => $validated['title'],
            'author' => $validated['author'] ?? '',
            'isbn' => $validated['isbn'] ?? null,
            'cover_url' => $validated['cover_url'] ?? null,
            'publisher' => $validated['publisher'] ?? null,
            'publish_date' => $validated['publish_date'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_user_created' => true,
        ]);

        return redirect()->back()->with('success', 'Book created successfully!');
    }

    public function storeAndView(StoreBookRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Check if book already exists by external_id
        if (! empty($validated['external_id'])) {
            $existingBook = Book::where('external_id', $validated['external_id'])->first();
            if ($existingBook) {
                return redirect()->route('books.show', $existingBook);
            }
        }

        // Create the book with basic info
        $book = Book::create($validated);

        // Dispatch background job to fetch work details (description, subjects, etc.)
        if ($book->ol_work_key) {
            \App\Jobs\FetchBookWorkDetails::dispatch($book);
        }

        return redirect()->route('books.show', $book);
    }
}
