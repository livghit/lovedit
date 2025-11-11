<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        // Stat 1: Total reviews count
        $totalReviews = $user->reviews()->count();

        // Stat 2: Average rating with distribution
        $ratingStats = $user->reviews()
            ->selectRaw('
                ROUND(AVG(rating), 1) as average,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as stars_5,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as stars_4,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as stars_3,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as stars_2,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as stars_1
            ')
            ->first();

        // Stat 3: Pending books to review
        $pendingBooksCount = $user->toReviewLists()->count();

        // Recent reviews (5 most recent)
        $recentReviews = $user->reviews()
            ->with(['book:id,title,author,cover_url'])
            ->latest()
            ->limit(5)
            ->get(['id', 'book_id', 'rating', 'content', 'created_at']);

        // Recent review books (for cover fan)
        $recentReviewBooks = $recentReviews->pluck('book')->filter()->take(5)->values();

        // Recent to-review list additions (5 most recent)
        $recentToReview = $user->toReviewLists()
            ->with(['book:id,title,author,cover_url'])
            ->latest('added_at')
            ->limit(5)
            ->get(['id', 'book_id', 'added_at']);

        // Highest rated book(s)
        $highestRatedBook = $user->reviews()
            ->with(['book:id,title,author,cover_url'])
            ->orderByDesc('rating')
            ->limit(1)
            ->get(['id', 'book_id', 'rating'])
            ->first();

        // Most recently reviewed book
        $mostRecentReviewedBook = $user->reviews()
            ->with(['book:id,title,author,cover_url'])
            ->latest('created_at')
            ->limit(1)
            ->get(['id', 'book_id', 'created_at'])
            ->first();

        // Most frequently reviewed author
        $mostFrequentAuthor = $user->reviews()
            ->join('books', 'reviews.book_id', '=', 'books.id')
            ->selectRaw('books.author, COUNT(*) as review_count')
            ->groupBy('books.author')
            ->orderByDesc('review_count')
            ->limit(1)
            ->get(['books.author', 'review_count'])
            ->first();

        return Inertia::render('dashboard', [
            'stats' => [
                'totalReviews' => $totalReviews,
                'averageRating' => $ratingStats?->average ?? 0,
                'ratingDistribution' => $ratingStats ? [
                    5 => $ratingStats->stars_5,
                    4 => $ratingStats->stars_4,
                    3 => $ratingStats->stars_3,
                    2 => $ratingStats->stars_2,
                    1 => $ratingStats->stars_1,
                ] : [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
                'pendingBooksCount' => $pendingBooksCount,
            ],
            'recentActivity' => [
                'reviews' => $recentReviews,
                'toReview' => $recentToReview,
                'reviewBooks' => $recentReviewBooks,
            ],
            'insights' => [
                'highestRatedBook' => $highestRatedBook,
                'mostRecentReviewedBook' => $mostRecentReviewedBook,
                'mostFrequentAuthor' => $mostFrequentAuthor,
            ],
        ]);
    }
}
