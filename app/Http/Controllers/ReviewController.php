<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function index(): Response
    {
        $reviews = auth()->user()->reviews()->with('book')->latest()->get();

        return Inertia::render('reviews/index', [
            'reviews' => $reviews,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('reviews/form');
    }

    public function store(StoreReviewRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $existingReview = Review::where('user_id', auth()->id())
            ->where('book_id', $validated['book_id'])
            ->first();

        if ($existingReview) {
            return back()->withErrors([
                'book_id' => 'You have already reviewed this book.',
            ]);
        }

        auth()->user()->reviews()->create($validated);

        return redirect()->route('reviews.index');
    }

    public function show(Review $review): Response
    {
        $this->authorize('view', $review);

        return Inertia::render('reviews/show', [
            'review' => $review->load('book'),
        ]);
    }

    public function edit(Review $review): Response
    {
        $this->authorize('update', $review);

        return Inertia::render('reviews/form', [
            'review' => $review,
            'book' => $review->book,
            'isEdit' => true,
        ]);
    }

    public function update(Review $review, UpdateReviewRequest $request): RedirectResponse
    {
        $this->authorize('update', $review);

        $review->update($request->validated());

        return redirect()->route('reviews.show', $review);
    }

    public function destroy(Review $review): RedirectResponse
    {
        $this->authorize('delete', $review);

        $review->delete();

        return redirect()->route('reviews.index');
    }
}
