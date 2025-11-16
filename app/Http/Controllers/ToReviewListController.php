<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarkReviewedRequest;
use App\Http\Requests\StoreToReviewRequest;
use App\Models\Book;
use App\Models\ToReviewList;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ToReviewListController extends Controller
{
    public function index(): Response
    {
        $items = auth()->user()->toReviewLists()
            ->whereNotExists(function ($q) {
                $q->selectRaw(1)
                    ->from('reviews')
                    ->whereColumn('reviews.book_id', 'to_review_lists.book_id')
                    ->where('reviews.user_id', auth()->id());
            })
            ->with('book')
            ->pending()
            ->get();

        return Inertia::render('to-review-lists/index', [
            'items' => $items,
        ]);
    }

    public function store(StoreToReviewRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $bookId = $validated['book_id'] ?? null;

        // If we have full book data (from online search), save the book first
        if (isset($validated['title'])) {
            // Check if book already exists by external_id
            $book = null;
            if (! empty($validated['external_id'])) {
                $book = Book::where('external_id', $validated['external_id'])->first();
            }

            // Create the book if it doesn't exist
            if (! $book) {
                $book = Book::create($validated);

                // Dispatch background job to fetch work details
                if ($book->ol_work_key) {
                    \App\Jobs\FetchBookWorkDetails::dispatch($book);
                }
            }

            $bookId = $book->id;
        }

        // Validate we have a book_id
        if (! $bookId) {
            return back()->withErrors(['book_id' => 'A book must be selected.']);
        }

        $existing = ToReviewList::where('user_id', auth()->id())
            ->where('book_id', $bookId)
            ->first();

        if ($existing) {
            return back()->with('info', 'Book is already in your review list.');
        }

        auth()->user()->toReviewLists()->create([
            'book_id' => $bookId,
            'added_at' => now(),
        ]);

        return back()->with('success', 'Book added to your review list!');
    }

    public function destroy(ToReviewList $toReviewList): RedirectResponse
    {
        $this->authorize('delete', $toReviewList);

        $toReviewList->delete();

        return redirect()->route('to-review-lists.index');
    }

    public function markReviewed(ToReviewList $toReviewList, MarkReviewedRequest $request): RedirectResponse
    {
        $this->authorize('update', $toReviewList);

        $validated = $request->validated();

        auth()->user()->reviews()->create([
            'book_id' => $toReviewList->book_id,
            'rating' => $validated['rating'],
            'content' => $validated['content'],
        ]);

        $toReviewList->delete();

        return redirect()->route('reviews.index');
    }
}
