import { NoReviewsState } from '@/components/EmptyState';
import StarRating from '@/components/StarRating';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';

interface Review {
    id: string | number;
    rating: number;
    content: string;
    book: {
        id: string | number;
        title: string;
        author: string;
        cover_url?: string | null;
    };
    user: {
        id: string | number;
        name: string;
    };
    created_at: string;
}

interface ReviewsIndexProps {
    reviews: Review[];
    currentUserId: string | number;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Reviews',
        href: '/reviews',
    },
];

export default function ReviewsIndex({
    reviews,
    currentUserId,
}: ReviewsIndexProps) {
    const handleDelete = (reviewId: string | number) => {
        if (confirm('Are you sure you want to delete this review?')) {
            router.delete(`/reviews/${reviewId}`);
        }
    };

    const handleEdit = (reviewId: string | number) => {
        router.visit(`/reviews/${reviewId}/edit`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reviews" />

            <div className="flex flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="space-y-2">
                    <h1 className="text-3xl font-bold">My Reviews</h1>
                    <p className="text-sm text-muted-foreground">
                        Share your thoughts on books you've read
                    </p>
                </div>

                <div className="flex justify-end">
                    <Button onClick={() => router.visit('/reviews/create')}>
                        Write a Review
                    </Button>
                </div>

                {reviews.length > 0 ? (
                    <div className="w-full overflow-x-auto">
                        <table className="w-full border-collapse text-sm">
                            <thead>
                                <tr className="border-b">
                                    <th className="py-2 text-left font-medium">
                                        Book
                                    </th>
                                    <th className="py-2 text-left font-medium">
                                        Rating
                                    </th>
                                    <th className="py-2 text-left font-medium">
                                        Excerpt
                                    </th>
                                    <th className="py-2 text-left font-medium">
                                        Date
                                    </th>
                                    <th className="py-2 text-left font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="align-top">
                                {reviews.map((review) => {
                                    const isOwner =
                                        review.user?.id === currentUserId;
                                    const contentPreview = review.content.slice(
                                        0,
                                        120,
                                    );
                                    const isLong = review.content.length > 120;
                                    return (
                                        <tr
                                            key={review.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 pr-4">
                                                <div className="flex items-start gap-3">
                                                    {review.book?.cover_url && (
                                                        <img
                                                            src={
                                                                review.book
                                                                    .cover_url
                                                            }
                                                            alt={
                                                                review.book
                                                                    .title
                                                            }
                                                            className="h-14 w-10 shrink-0 rounded object-cover"
                                                        />
                                                    )}
                                                    <div className="space-y-1">
                                                        <div className="leading-tight font-medium">
                                                            {review.book?.title}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {
                                                                review.book
                                                                    ?.author
                                                            }
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="py-3 pr-4">
                                                <StarRating
                                                    rating={review.rating}
                                                    readOnly
                                                    size="sm"
                                                />
                                            </td>
                                            <td className="max-w-xs py-3 pr-4">
                                                <span className="text-foreground/90">
                                                    {contentPreview}
                                                    {isLong && '...'}
                                                </span>
                                            </td>
                                            <td className="py-3 pr-4 whitespace-nowrap text-muted-foreground">
                                                {new Date(
                                                    review.created_at,
                                                ).toLocaleDateString()}
                                            </td>
                                            <td className="py-3 pr-4">
                                                {isOwner ? (
                                                    <div className="flex items-center gap-2">
                                                        <button
                                                            onClick={() =>
                                                                handleEdit(
                                                                    review.id,
                                                                )
                                                            }
                                                            className="rounded px-2 py-1 text-xs font-medium text-primary hover:bg-primary/10"
                                                        >
                                                            Edit
                                                        </button>
                                                        <button
                                                            onClick={() =>
                                                                handleDelete(
                                                                    review.id,
                                                                )
                                                            }
                                                            className="rounded px-2 py-1 text-xs font-medium text-destructive hover:bg-destructive/10"
                                                        >
                                                            Delete
                                                        </button>
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <NoReviewsState
                        onCreateClick={() => router.visit('/reviews/create')}
                    />
                )}
            </div>
        </AppLayout>
    );
}
