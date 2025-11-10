import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface Book {
    id: string | number;
    title: string;
    author: string;
    description?: string;
    cover_url?: string | null;
    isbn?: string;
    published_year?: number;
    publisher?: string;
}

interface BooksShowProps {
    book: Book;
    userReview?: {
        id: string | number;
        rating: number;
    };
    isInToReviewList?: boolean;
}

export default function BooksShow({
    book,
    userReview,
    isInToReviewList = false,
}: BooksShowProps) {
    const [isAddingToList, setIsAddingToList] = useState(false);
    const [isRemovingFromList, setIsRemovingFromList] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Books',
            href: '/books/search',
        },
        {
            title: book.title,
            href: `/books/${book.id}`,
        },
    ];

    const handleAddToReviewList = async () => {
        setIsAddingToList(true);
        try {
            router.post(
                '/to-review-lists',
                { book_id: book.id },
                {
                    onError: () => {
                        setIsAddingToList(false);
                    },
                },
            );
        } catch {
            setIsAddingToList(false);
        }
    };

    const handleRemoveFromReviewList = async () => {
        setIsRemovingFromList(true);
        try {
            router.delete(`/to-review-lists/${book.id}`, {
                onError: () => {
                    setIsRemovingFromList(false);
                },
            });
        } catch {
            setIsRemovingFromList(false);
        }
    };

    const handleWriteReview = () => {
        if (userReview) {
            router.visit(`/reviews/${userReview.id}/edit`);
        } else {
            router.visit(`/reviews/create?book_id=${book.id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={book.title} />

            <div className="flex flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="grid grid-cols-1 gap-6 md:grid-cols-4">
                    {/* Book Cover */}
                    <div className="md:col-span-1">
                        <div className="relative aspect-[2/3] w-full overflow-hidden rounded-lg shadow-lg">
                            <img
                                src={(function () {
                                    const placeholder =
                                        'https://placehold.co/600x900?text=No%20Cover';
                                    const url = book.cover_url ?? '';
                                    if (!url) return placeholder;
                                    if (url.includes('placeholder.com'))
                                        return placeholder;
                                    return url;
                                })()}
                                alt={book.title}
                                className="h-full w-full object-cover"
                                onError={(e) => {
                                    const img =
                                        e.currentTarget as HTMLImageElement;
                                    if ((img as any).dataset.fallbackApplied)
                                        return;
                                    (img as any).dataset.fallbackApplied =
                                        'true';
                                    img.src =
                                        'https://placehold.co/600x900?text=No%20Cover';
                                }}
                            />
                        </div>
                    </div>

                    {/* Book Details */}
                    <div className="space-y-6 md:col-span-3">
                        <div>
                            <h1 className="mb-2 text-4xl font-bold">
                                {book.title}
                            </h1>
                            <p className="text-xl text-muted-foreground">
                                {book.author}
                            </p>
                        </div>

                        {/* Book Metadata */}
                        <Card>
                            <CardContent className="space-y-4 pt-6">
                                {book.isbn && (
                                    <div>
                                        <p className="text-sm font-medium text-muted-foreground">
                                            ISBN
                                        </p>
                                        <p className="text-sm">{book.isbn}</p>
                                    </div>
                                )}
                                {book.publisher && (
                                    <div>
                                        <p className="text-sm font-medium text-muted-foreground">
                                            Publisher
                                        </p>
                                        <p className="text-sm">
                                            {book.publisher}
                                        </p>
                                    </div>
                                )}
                                {book.published_year && (
                                    <div>
                                        <p className="text-sm font-medium text-muted-foreground">
                                            Published
                                        </p>
                                        <p className="text-sm">
                                            {book.published_year}
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Description */}
                        {book.description && (
                            <div>
                                <h2 className="mb-2 text-lg font-semibold">
                                    About
                                </h2>
                                <p className="text-sm leading-relaxed text-foreground/90">
                                    {book.description}
                                </p>
                            </div>
                        )}

                        {/* Actions */}
                        <div className="flex gap-3 pt-4">
                            <Button onClick={handleWriteReview}>
                                {userReview ? 'Edit Review' : 'Write a Review'}
                            </Button>
                            {isInToReviewList ? (
                                <Button
                                    variant="outline"
                                    onClick={handleRemoveFromReviewList}
                                    disabled={isRemovingFromList}
                                >
                                    {isRemovingFromList
                                        ? 'Removing...'
                                        : 'Remove from List'}
                                </Button>
                            ) : (
                                <Button
                                    variant="outline"
                                    onClick={handleAddToReviewList}
                                    disabled={isAddingToList}
                                >
                                    {isAddingToList
                                        ? 'Adding...'
                                        : 'Add to Review List'}
                                </Button>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
