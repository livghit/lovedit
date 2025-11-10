<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarkReviewedRequest;
use App\Http\Requests\StoreToReviewRequest;
use App\Models\ToReviewList;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ToReviewListController extends Controller
{
    public function index(): Response
    {
        $items = auth()->user()->toReviewLists()->with('book')->pending()->get();

        return Inertia::render('to-review-lists/index', [
            'items' => $items,
        ]);
    }

    public function store(StoreToReviewRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $existing = ToReviewList::where('user_id', auth()->id())
            ->where('book_id', $validated['book_id'])
            ->first();

        if ($existing) {
            return back()->withErrors([
                'book_id' => 'Book is already in your to-review list.',
            ]);
        }

        auth()->user()->toReviewLists()->create([
            'book_id' => $validated['book_id'],
            'added_at' => now(),
        ]);

        return redirect()->route('to-review-lists.index');
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
