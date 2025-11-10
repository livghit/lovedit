import { EmptyState } from '@/components/EmptyState';
import ReviewEditor from '@/components/ReviewEditor';
import StarRating from '@/components/StarRating';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, router } from '@inertiajs/react';
import React from 'react';

interface Book {
    id: string | number;
    title: string;
    author: string;
    cover_url?: string | null;
}

interface ReviewFormProps {
    book?: Book; // Provided when editing existing review
    review?: {
        id: string | number;
        book_id: string | number;
        rating: number;
        content: string;
    };
    isEdit?: boolean;
    books?: Book[]; // Provided when creating
    selectedBookId?: number | null;
}

export default function ReviewForm({
    book,
    review,
    isEdit = false,
    books = [],
    selectedBookId = null,
}: ReviewFormProps) {
    // Removed local preview toggle (handled inside editor)
    const [rating, setRating] = React.useState(review?.rating ?? 0);
    const [content, setContent] = React.useState(review?.content ?? '');
    const [bookId, setBookId] = React.useState<string | number | undefined>(
        isEdit ? review?.book_id : (selectedBookId ?? undefined),
    );

    const findById = React.useCallback(
        (id: string | number | undefined) =>
            books.find((b) => String(b.id) === String(id)),
        [books],
    );

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Reviews',
            href: '/reviews',
        },
        {
            title: isEdit ? 'Edit Review' : 'New Review',
            href: isEdit ? `/reviews/${review?.id}/edit` : '/reviews/create',
        },
    ];

    const currentBook = book || findById(bookId);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={isEdit ? 'Edit Review' : 'Write a Review'} />

            <div className="flex flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div>
                    <h1 className="text-3xl font-bold">
                        {isEdit ? 'Edit Review' : 'Write a Review'}
                    </h1>
                    {currentBook && (
                        <p className="mt-2 text-sm text-muted-foreground">
                            {currentBook.title} by {currentBook.author}
                        </p>
                    )}
                </div>

                <Form
                    method={isEdit ? 'patch' : 'post'}
                    action={isEdit ? `/reviews/${review?.id}` : '/reviews'}
                    className="max-w-4xl space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            {/* Book Selection (create only) */}
                            {!isEdit &&
                                (bookId && findById(bookId) ? (
                                    <div className="space-y-2">
                                        <label
                                            className="text-sm font-medium"
                                            htmlFor="book_id"
                                        >
                                            Book
                                        </label>
                                        <div className="flex gap-4 rounded-md border p-4">
                                            <div className="h-24 w-16 overflow-hidden rounded bg-muted">
                                                {(() => {
                                                    const b = findById(bookId);
                                                    const url =
                                                        b?.cover_url ?? '';
                                                    return url ? (
                                                        // eslint-disable-next-line @next/next/no-img-element
                                                        <img
                                                            src={url}
                                                            alt={b?.title}
                                                            className="h-full w-full object-cover"
                                                        />
                                                    ) : (
                                                        <div className="flex h-full w-full items-center justify-center text-xs text-muted-foreground">
                                                            No Cover
                                                        </div>
                                                    );
                                                })()}
                                            </div>
                                            <div className="flex flex-col justify-center">
                                                <p className="text-sm font-semibold">
                                                    {findById(bookId)?.title}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {findById(bookId)?.author}
                                                </p>
                                                <Button
                                                    variant="outline"
                                                    type="button"
                                                    size="sm"
                                                    onClick={() =>
                                                        setBookId(undefined)
                                                    }
                                                    className="mt-2 self-start"
                                                >
                                                    Change
                                                </Button>
                                            </div>
                                        </div>
                                        <input
                                            type="hidden"
                                            name="book_id"
                                            value={bookId ?? ''}
                                        />
                                    </div>
                                ) : (
                                    <div className="space-y-2">
                                        <label
                                            className="text-sm font-medium"
                                            htmlFor="book_id"
                                        >
                                            Book
                                        </label>
                                        {books.length === 0 ? (
                                            <EmptyState
                                                title="No books available"
                                                description="Search for books first before writing a review."
                                                action={{
                                                    label: 'Browse & add books',
                                                    onClick: () =>
                                                        router.visit(
                                                            '/books/search',
                                                        ),
                                                }}
                                                className="rounded-md border"
                                            />
                                        ) : (
                                            <Select
                                                value={bookId?.toString()}
                                                onValueChange={(val) =>
                                                    setBookId(val)
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select a book" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {books.map((b) => (
                                                        <SelectItem
                                                            key={b.id}
                                                            value={b.id.toString()}
                                                        >
                                                            <span className="flex items-center gap-2">
                                                                <span className="h-10 w-6 overflow-hidden rounded bg-muted">
                                                                    {b.cover_url ? (
                                                                        // eslint-disable-next-line @next/next/no-img-element
                                                                        <img
                                                                            src={
                                                                                b.cover_url
                                                                            }
                                                                            alt={
                                                                                b.title
                                                                            }
                                                                            className="h-full w-full object-cover"
                                                                        />
                                                                    ) : (
                                                                        <span className="flex h-full w-full items-center justify-center text-[10px] text-muted-foreground">
                                                                            No
                                                                        </span>
                                                                    )}
                                                                </span>
                                                                <span className="truncate">
                                                                    {b.title} –{' '}
                                                                    {b.author}
                                                                </span>
                                                            </span>
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        )}
                                        {errors.book_id && (
                                            <p className="text-sm text-destructive">
                                                {errors.book_id}
                                            </p>
                                        )}
                                        <input
                                            type="hidden"
                                            name="book_id"
                                            value={bookId ?? ''}
                                        />
                                    </div>
                                ))}

                            {/* Rating */}
                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    Rating
                                </label>
                                <div className="flex items-center gap-4">
                                    <StarRating
                                        rating={rating}
                                        onRatingChange={setRating}
                                    />
                                    <span className="text-sm text-muted-foreground">
                                        {rating > 0
                                            ? `${rating}/5 stars`
                                            : 'Select a rating'}
                                    </span>
                                </div>
                                {errors.rating && (
                                    <p className="text-sm text-destructive">
                                        {errors.rating}
                                    </p>
                                )}
                                <input
                                    type="hidden"
                                    name="rating"
                                    value={rating}
                                />
                            </div>

                            {/* Content Editor */}
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <label
                                        htmlFor="content"
                                        className="text-sm font-medium"
                                    >
                                        Your Review
                                    </label>
                                </div>

                                <ReviewEditor
                                    id="content"
                                    name="content"
                                    value={content}
                                    onChange={setContent}
                                    placeholder="Share your thoughts about this book... Supports markdown formatting."
                                    error={errors.content}
                                    minLength={10}
                                />
                            </div>

                            {/* Hidden book_id field when editing */}
                            {isEdit && book && (
                                <input
                                    type="hidden"
                                    name="book_id"
                                    value={book.id}
                                />
                            )}

                            {/* Actions */}
                            <div className="flex gap-3 pt-4">
                                <Button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        rating === 0 ||
                                        (!isEdit && !bookId) ||
                                        content.trim().length < 10
                                    }
                                >
                                    {processing
                                        ? 'Saving...'
                                        : isEdit
                                          ? 'Update Review'
                                          : 'Publish Review'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => router.visit('/reviews')}
                                    disabled={processing}
                                >
                                    Cancel
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </AppLayout>
    );
}
