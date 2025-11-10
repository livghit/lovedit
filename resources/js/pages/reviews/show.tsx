import MarkdownRenderer from '@/components/MarkdownRenderer';
import StarRating from '@/components/StarRating';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';

interface Book {
    id: string | number;
    title: string;
    author: string;
    cover_url?: string | null;
}

interface ReviewShowProps {
    review: {
        id: string | number;
        rating: number;
        content: string;
        user_id: string | number;
        book: Book;
        created_at: string;
        updated_at: string;
    };
}

export default function ReviewShow({ review }: ReviewShowProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Reviews',
            href: '/reviews',
        },
        {
            title: review.book.title,
            href: `/reviews/${review.id}`,
        },
    ];

    const handleDelete = () => {
        if (confirm('Are you sure you want to delete this review?')) {
            router.delete(`/reviews/${review.id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Review: ${review.book.title}`} />

            <div className="flex flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                {/* Header */}
                <div className="space-y-4">
                    <div>
                        <h1 className="text-3xl font-bold">
                            {review.book.title}
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            by {review.book.author}
                        </p>
                    </div>

                    {/* Rating */}
                    <div className="flex items-center gap-4">
                        <div className="flex items-center gap-2">
                            <StarRating rating={review.rating} readOnly />
                            <span className="text-sm font-medium">
                                {review.rating}/5
                            </span>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {new Date(review.created_at).toLocaleDateString()}
                        </p>
                    </div>
                </div>

                {/* Review Content */}
                <div className="max-w-4xl space-y-4 rounded-lg bg-card p-6">
                    <MarkdownRenderer content={review.content} />
                </div>

                {/* Actions */}
                <div className="flex gap-3">
                    <Button
                        variant="outline"
                        onClick={() =>
                            router.visit(`/reviews/${review.id}/edit`)
                        }
                    >
                        Edit Review
                    </Button>
                    <Button variant="destructive" onClick={handleDelete}>
                        Delete Review
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() => router.visit('/reviews')}
                    >
                        Back to Reviews
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
