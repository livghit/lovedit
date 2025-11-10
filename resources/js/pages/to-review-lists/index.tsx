import {
    destroy,
    markReviewed,
} from '@/actions/App/Http/Controllers/ToReviewListController';
import BookCard from '@/components/BookCard';
import { NoToReviewState } from '@/components/EmptyState';
import StarRating from '@/components/StarRating';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';

interface Book {
    id: string | number;
    title: string;
    author: string;
    cover_url?: string | null;
    isbn?: string;
}

interface ToReviewListItem {
    id: string | number;
    book: Book;
    added_at: string;
}

interface ToReviewListIndexProps {
    items: ToReviewListItem[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'To-Review List',
        href: '/to-review-lists',
    },
];

import React from 'react';
export default function ToReviewListIndex({ items }: ToReviewListIndexProps) {
    const handleRemove = (itemId: string | number) => {
        if (confirm('Remove this book from your to-review list?')) {
            router.delete(destroy.url(Number(itemId)));
        }
    };

    const [openId, setOpenId] = React.useState<string | number | null>(null);
    const [rating, setRating] = React.useState<number>(0);
    const [content, setContent] = React.useState<string>('');

    const resetForm = () => {
        setRating(0);
        setContent('');
    };

    const handleMarkReviewed = (itemId: string | number) => {
        router.post(
            markReviewed.url(Number(itemId)),
            {
                rating,
                content,
            },
            {
                onSuccess: () => {
                    setOpenId(null);
                    resetForm();
                },
                onError: () => {
                    // Keep dialog open; errors will be shown via page props after Inertia re-render
                },
            },
        );
    };

    const handleViewBook = (book: Book) => {
        router.visit(`/books/${book.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="To-Review List" />

            <div className="flex flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="space-y-2">
                    <h1 className="text-3xl font-bold">To-Review List</h1>
                    <p className="text-sm text-muted-foreground">
                        {items.length} book{items.length !== 1 ? 's' : ''}{' '}
                        waiting for your review
                    </p>
                </div>

                <div className="flex justify-end">
                    <Button
                        variant="outline"
                        onClick={() => router.visit('/books/search')}
                    >
                        Add More Books
                    </Button>
                </div>

                {items.length > 0 ? (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {items.map((item) => (
                            <div key={item.id} className="group relative">
                                <BookCard
                                    book={item.book}
                                    onClick={() => handleViewBook(item.book)}
                                />

                                {/* Actions Overlay */}
                                <div className="absolute inset-0 flex items-center justify-center gap-2 rounded-lg bg-black/50 p-2 opacity-0 transition-opacity group-hover:opacity-100">
                                    <Dialog
                                        open={openId === item.id}
                                        onOpenChange={(open) => {
                                            if (open) {
                                                setOpenId(item.id);
                                            } else {
                                                setOpenId(null);
                                                resetForm();
                                            }
                                        }}
                                    >
                                        <DialogTrigger asChild>
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                onClick={() =>
                                                    setOpenId(item.id)
                                                }
                                            >
                                                Review
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Quick Review
                                                </DialogTitle>
                                                <DialogDescription>
                                                    {item.book.title} by{' '}
                                                    {item.book.author}
                                                </DialogDescription>
                                            </DialogHeader>
                                            <div className="space-y-4">
                                                <div className="space-y-2">
                                                    <label className="text-sm font-medium">
                                                        Rating
                                                    </label>
                                                    <StarRating
                                                        rating={rating}
                                                        onRatingChange={
                                                            setRating
                                                        }
                                                    />
                                                    <p className="text-xs text-muted-foreground">
                                                        {rating > 0
                                                            ? `${rating}/5 stars`
                                                            : 'Select a rating'}
                                                    </p>
                                                </div>
                                                <div className="space-y-2">
                                                    <label
                                                        htmlFor="content"
                                                        className="text-sm font-medium"
                                                    >
                                                        Review
                                                    </label>
                                                    <Textarea
                                                        id="content"
                                                        value={content}
                                                        onChange={(e) =>
                                                            setContent(
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Share a brief review (min 10 characters)"
                                                        className="min-h-[140px]"
                                                    />
                                                    <p className="text-xs text-muted-foreground">
                                                        Minimum 10 characters.
                                                    </p>
                                                </div>
                                            </div>
                                            <DialogFooter>
                                                <DialogClose asChild>
                                                    <Button
                                                        variant="outline"
                                                        type="button"
                                                    >
                                                        Cancel
                                                    </Button>
                                                </DialogClose>
                                                <Button
                                                    type="button"
                                                    disabled={
                                                        rating < 1 ||
                                                        content.trim().length <
                                                            10
                                                    }
                                                    onClick={() =>
                                                        handleMarkReviewed(
                                                            item.id,
                                                        )
                                                    }
                                                >
                                                    Publish Review
                                                </Button>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>
                                    <Button
                                        size="sm"
                                        variant="destructive"
                                        onClick={() => handleRemove(item.id)}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>

                                {/* Date Added */}
                                <p className="mt-2 text-center text-xs text-muted-foreground">
                                    Added{' '}
                                    {new Date(
                                        item.added_at,
                                    ).toLocaleDateString()}
                                </p>
                            </div>
                        ))}
                    </div>
                ) : (
                    <NoToReviewState
                        onAddClick={() => router.visit('/books/search')}
                    />
                )}
            </div>
        </AppLayout>
    );
}
