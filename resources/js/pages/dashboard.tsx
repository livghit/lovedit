import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { RatingDistributionChart } from '@/components/ui/rating-distribution-chart';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import React from 'react';

interface Book {
    id: string | number;
    title: string;
    author: string;
    cover_url?: string | null;
}

interface Review {
    id: string | number;
    book_id: string | number;
    rating: number;
    content: string;
    created_at: string;
    book: Book;
}

interface ToReviewItem {
    id: string | number;
    book_id: string | number;
    added_at: string;
    book: Book;
}

interface DashboardProps {
    stats: {
        totalReviews: number;
        averageRating: number;
        ratingDistribution: Record<number, number>;
        pendingBooksCount: number;
    };
    recentActivity: {
        reviews: Review[];
        toReview: ToReviewItem[];
        reviewBooks: Book[];
    };
    insights: {
        highestRatedBook: Review | null;
        mostRecentReviewedBook: Review | null;
        mostFrequentAuthor: { author: string; review_count: number } | null;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

function StarRating({ rating }: { rating: number }) {
    return (
        <div className="flex gap-0.5">
            {[1, 2, 3, 4, 5].map((star) => (
                <span
                    key={star}
                    className={
                        star <= rating ? 'text-yellow-400' : 'text-gray-300'
                    }
                >
                    ★
                </span>
            ))}
        </div>
    );
}

export default function Dashboard({
    stats,
    recentActivity,
    insights,
}: DashboardProps) {
    const [activeTab, setActiveTab] = React.useState<
        'activity' | 'insights' | 'actions'
    >('activity');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold">Dashboard</h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Your reading journey at a glance
                    </p>
                </div>

                {/* Stat Cards Row */}
                <div className="grid gap-4 md:grid-cols-3">
                    {/* Card 1: Total Reviews */}
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Average Rating
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex h-full flex-col">
                            <div className="flex items-end justify-between">
                                <div className="text-3xl leading-none font-bold">
                                    {stats.averageRating.toFixed(1)}
                                    <span className="text-lg text-muted-foreground">
                                        /5
                                    </span>
                                </div>
                                <StarRating
                                    rating={Math.round(stats.averageRating)}
                                />
                            </div>

                            {/* Chart */}
                            <RatingDistributionChart
                                distribution={stats.ratingDistribution}
                            />

                            <div className="mt-auto">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="mt-4 w-full"
                                    asChild
                                >
                                    <Link href="/reviews">View Ratings</Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Card 2: Average Rating */}
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                Total Reviews
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex h-full flex-col">
                            <div className="flex items-start justify-between gap-2">
                                <div>
                                    <div className="text-3xl font-bold">
                                        {stats.totalReviews}
                                    </div>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        {stats.totalReviews === 1
                                            ? 'review'
                                            : 'reviews'}{' '}
                                        written
                                    </p>
                                </div>
                                {recentActivity.reviewBooks?.length > 0 && (
                                    <div
                                        className="relative hidden h-20 w-48 sm:block"
                                        aria-hidden
                                    >
                                        {recentActivity.reviewBooks
                                            .slice(0, 5)
                                            .map((book, i) => (
                                                <div
                                                    key={book.id}
                                                    className="absolute bottom-0 h-20 w-14 overflow-hidden rounded-md shadow-sm ring-1 ring-black/10 dark:ring-white/10"
                                                    style={{
                                                        left: `${i * 26}px`,
                                                        transform: `rotate(${(i - 2) * 3}deg)`,
                                                    }}
                                                    title={book.title}
                                                >
                                                    {book.cover_url ? (
                                                        <img
                                                            src={book.cover_url}
                                                            alt={book.title}
                                                            className="h-full w-full object-cover"
                                                        />
                                                    ) : (
                                                        <div className="flex h-full w-full items-center justify-center bg-muted text-[10px] text-muted-foreground">
                                                            No Cover
                                                        </div>
                                                    )}
                                                </div>
                                            ))}
                                        {/* gradient mask on right to soften edge */}
                                        <div className="pointer-events-none absolute inset-y-0 right-0 w-8 bg-gradient-to-l from-background" />
                                    </div>
                                )}
                            </div>

                            <div className="mt-auto">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="mt-4 w-full"
                                    asChild
                                >
                                    <Link href="/reviews">View All</Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Card 3: Books to Review */}
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                To Review (Pending)
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex h-full flex-col">
                            <div className="text-3xl font-bold">
                                {stats.pendingBooksCount}
                            </div>
                            <p className="mt-2 text-xs text-muted-foreground">
                                {stats.pendingBooksCount === 1
                                    ? 'book'
                                    : 'books'}{' '}
                                waiting
                            </p>
                            <div className="mt-auto">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="mt-3 w-full"
                                    asChild
                                >
                                    <Link href="/to-review-lists">
                                        Browse List
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Tab Navigation */}
                <div className="flex gap-2 border-b">
                    <button
                        onClick={() => setActiveTab('activity')}
                        className={`px-4 py-2 font-medium transition ${
                            activeTab === 'activity'
                                ? 'border-b-2 border-primary text-primary'
                                : 'text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        Recent Activity
                    </button>
                    <button
                        onClick={() => setActiveTab('insights')}
                        className={`px-4 py-2 font-medium transition ${
                            activeTab === 'insights'
                                ? 'border-b-2 border-primary text-primary'
                                : 'text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        Reading Insights
                    </button>
                    <button
                        onClick={() => setActiveTab('actions')}
                        className={`px-4 py-2 font-medium transition ${
                            activeTab === 'actions'
                                ? 'border-b-2 border-primary text-primary'
                                : 'text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        Quick Actions
                    </button>
                </div>

                {/* Tab Content */}
                <div className="space-y-4">
                    {/* Tab 1: Recent Activity */}
                    {activeTab === 'activity' && (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Recent Reviews</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {recentActivity.reviews.length > 0 ? (
                                        recentActivity.reviews.map((review) => (
                                            <div
                                                key={review.id}
                                                className="flex gap-4 border-b pb-4 last:border-0"
                                            >
                                                {review.book?.cover_url && (
                                                    <div className="h-16 w-12 flex-shrink-0 overflow-hidden rounded bg-muted">
                                                        <img
                                                            src={
                                                                review.book
                                                                    .cover_url
                                                            }
                                                            alt={
                                                                review.book
                                                                    .title
                                                            }
                                                            className="h-full w-full object-cover"
                                                        />
                                                    </div>
                                                )}
                                                <div className="min-w-0 flex-1">
                                                    <Link
                                                        href={`/reviews/${review.id}`}
                                                        className="font-semibold hover:underline"
                                                    >
                                                        {review.book?.title}
                                                    </Link>
                                                    <p className="text-xs text-muted-foreground">
                                                        by {review.book?.author}
                                                    </p>
                                                    <div className="mt-1">
                                                        <StarRating
                                                            rating={
                                                                review.rating
                                                            }
                                                        />
                                                    </div>
                                                    <p className="mt-2 line-clamp-2 text-sm text-muted-foreground">
                                                        {review.content}
                                                    </p>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {new Date(
                                                            review.created_at,
                                                        ).toLocaleDateString()}
                                                    </p>
                                                </div>
                                            </div>
                                        ))
                                    ) : (
                                        <p className="text-center text-muted-foreground">
                                            No reviews yet. Start writing!
                                        </p>
                                    )}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        Recently Added to Review List
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {recentActivity.toReview.length > 0 ? (
                                        recentActivity.toReview.map((item) => (
                                            <div
                                                key={item.id}
                                                className="flex gap-4 border-b pb-4 last:border-0"
                                            >
                                                {item.book?.cover_url && (
                                                    <div className="h-16 w-12 flex-shrink-0 overflow-hidden rounded bg-muted">
                                                        <img
                                                            src={
                                                                item.book
                                                                    .cover_url
                                                            }
                                                            alt={
                                                                item.book.title
                                                            }
                                                            className="h-full w-full object-cover"
                                                        />
                                                    </div>
                                                )}
                                                <div className="min-w-0 flex-1">
                                                    <Link
                                                        href={`/books/${item.book?.id}`}
                                                        className="font-semibold hover:underline"
                                                    >
                                                        {item.book?.title}
                                                    </Link>
                                                    <p className="text-xs text-muted-foreground">
                                                        by {item.book?.author}
                                                    </p>
                                                    <p className="mt-2 text-xs text-muted-foreground">
                                                        Added{' '}
                                                        {new Date(
                                                            item.added_at,
                                                        ).toLocaleDateString()}
                                                    </p>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        className="mt-2"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={`/reviews/create?book_id=${item.book_id}`}
                                                        >
                                                            Write Review
                                                        </Link>
                                                    </Button>
                                                </div>
                                            </div>
                                        ))
                                    ) : (
                                        <p className="text-center text-muted-foreground">
                                            No books in review list yet.
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        </>
                    )}

                    {/* Tab 2: Reading Insights */}
                    {activeTab === 'insights' && (
                        <>
                            {/* Highest Rated */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Highest Rated Book</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {insights.highestRatedBook ? (
                                        <div className="flex gap-4">
                                            {insights.highestRatedBook.book
                                                ?.cover_url && (
                                                <div className="h-24 w-16 flex-shrink-0 overflow-hidden rounded bg-muted">
                                                    <img
                                                        src={
                                                            insights
                                                                .highestRatedBook
                                                                .book.cover_url
                                                        }
                                                        alt={
                                                            insights
                                                                .highestRatedBook
                                                                .book.title
                                                        }
                                                        className="h-full w-full object-cover"
                                                    />
                                                </div>
                                            )}
                                            <div className="flex-1">
                                                <Link
                                                    href={`/reviews/${insights.highestRatedBook.id}`}
                                                    className="font-semibold hover:underline"
                                                >
                                                    {
                                                        insights
                                                            .highestRatedBook
                                                            .book?.title
                                                    }
                                                </Link>
                                                <p className="text-sm text-muted-foreground">
                                                    by{' '}
                                                    {
                                                        insights
                                                            .highestRatedBook
                                                            .book?.author
                                                    }
                                                </p>
                                                <div className="mt-2">
                                                    <StarRating
                                                        rating={
                                                            insights
                                                                .highestRatedBook
                                                                .rating
                                                        }
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <p className="text-muted-foreground">
                                            No ratings yet.
                                        </p>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Most Recently Reviewed */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        Most Recently Reviewed
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {insights.mostRecentReviewedBook ? (
                                        <div className="flex gap-4">
                                            {insights.mostRecentReviewedBook
                                                .book?.cover_url && (
                                                <div className="h-24 w-16 flex-shrink-0 overflow-hidden rounded bg-muted">
                                                    <img
                                                        src={
                                                            insights
                                                                .mostRecentReviewedBook
                                                                .book.cover_url
                                                        }
                                                        alt={
                                                            insights
                                                                .mostRecentReviewedBook
                                                                .book.title
                                                        }
                                                        className="h-full w-full object-cover"
                                                    />
                                                </div>
                                            )}
                                            <div className="flex-1">
                                                <Link
                                                    href={`/reviews/${insights.mostRecentReviewedBook.id}`}
                                                    className="font-semibold hover:underline"
                                                >
                                                    {
                                                        insights
                                                            .mostRecentReviewedBook
                                                            .book?.title
                                                    }
                                                </Link>
                                                <p className="text-sm text-muted-foreground">
                                                    by{' '}
                                                    {
                                                        insights
                                                            .mostRecentReviewedBook
                                                            .book?.author
                                                    }
                                                </p>
                                                <p className="mt-2 text-xs text-muted-foreground">
                                                    Reviewed{' '}
                                                    {new Date(
                                                        insights.mostRecentReviewedBook.created_at,
                                                    ).toLocaleDateString()}
                                                </p>
                                            </div>
                                        </div>
                                    ) : (
                                        <p className="text-muted-foreground">
                                            No reviews yet.
                                        </p>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Most Frequent Author */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        Favorite Author (Most Reviewed)
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {insights.mostFrequentAuthor ? (
                                        <div>
                                            <p className="text-lg font-semibold">
                                                {
                                                    insights.mostFrequentAuthor
                                                        .author
                                                }
                                            </p>
                                            <p className="mt-2 text-sm text-muted-foreground">
                                                You've reviewed{' '}
                                                <span className="font-semibold text-foreground">
                                                    {
                                                        insights
                                                            .mostFrequentAuthor
                                                            .review_count
                                                    }
                                                </span>{' '}
                                                {insights.mostFrequentAuthor
                                                    .review_count === 1
                                                    ? 'book'
                                                    : 'books'}{' '}
                                                by this author
                                            </p>
                                        </div>
                                    ) : (
                                        <p className="text-muted-foreground">
                                            No data yet.
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        </>
                    )}

                    {/* Tab 3: Quick Actions */}
                    {activeTab === 'actions' && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Get Started</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <Button className="w-full" size="lg" asChild>
                                    <Link href="/reviews/create">
                                        ✍️ Write a New Review
                                    </Link>
                                </Button>
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    size="lg"
                                    asChild
                                >
                                    <Link href="/books/search">
                                        🔍 Search & Add Books
                                    </Link>
                                </Button>
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    size="lg"
                                    asChild
                                >
                                    <Link href="/to-review-lists">
                                        📚 View To-Review List
                                    </Link>
                                </Button>
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    size="lg"
                                    asChild
                                >
                                    <Link href="/reviews">
                                        👁️ View All Reviews
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
