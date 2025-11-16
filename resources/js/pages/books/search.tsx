import BookCard from '@/components/BookCard';
import { ErrorState, NoResultsState } from '@/components/EmptyState';
import SearchInput from '@/components/SearchInput';
import { LoadingGrid } from '@/components/Skeletons';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Heart } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Book {
    id: string | number;
    title: string;
    author: string;
    cover_url?: string | null;
    isbn?: string;
    external_id?: string;
    ol_work_key?: string;
    publisher?: string;
    publish_date?: number;
}

interface SearchApiResponse {
    is_local: boolean;
    books: Book[];
    has_online_option: boolean;
    query: string;
    total_count: number;
    source: 'local' | 'online';
    message: string;
}

interface Paginator<T> {
    data: T[];
    next_page_url?: string | null;
    prev_page_url?: string | null;
    links?: { url: string | null; label: string; active: boolean }[];
}

interface BooksSearchProps {
    results?: Book[];
    query?: string | null;
    books?: Paginator<Book> | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Books',
        href: '/books/search',
    },
];

export default function BooksSearch({
    results = [],
    query = null,
    books = null,
}: BooksSearchProps) {
    // Ensure local state is always a string
    const [searchQuery, setSearchQuery] = useState<string>(query ?? '');
    const [isLoading, setIsLoading] = useState(false);
    const [searchResults, setSearchResults] = useState<Book[]>(results);
    const [searchMode, setSearchMode] = useState<'local' | 'online' | null>(
        null,
    );
    const [hasOnlineOption, setHasOnlineOption] = useState(false);
    const [searchMessage, setSearchMessage] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [savingBookId, setSavingBookId] = useState<string | number | null>(
        null,
    );
    const [addedBookIds, setAddedBookIds] = useState<Set<string | number>>(
        new Set(),
    );

    // Keep local state in sync when server-provided query changes between visits
    useEffect(() => {
        setSearchQuery(query ?? '');
    }, [query]);

    const performSearch = async (online: boolean = false) => {
        const trimmed = searchQuery.trim();
        if (!trimmed) return;

        setIsLoading(true);
        setError(null);
        setSearchResults([]);

        try {
            const response = await fetch(
                `/api/books/search?q=${encodeURIComponent(trimmed)}&online=${online ? 'true' : 'false'}`,
                {
                    headers: {
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                },
            );

            if (!response.ok) {
                console.error(
                    `Search API failed with status ${response.status}: ${response.statusText}`,
                );
                const text = await response.text();
                console.error('Response body:', text);
                throw new Error(`Search failed with status ${response.status}`);
            }

            const data: SearchApiResponse = await response.json();

            setSearchResults(data.books);
            setSearchMode(data.is_local ? 'local' : 'online');
            setHasOnlineOption(data.has_online_option);
            setSearchMessage(data.message);

            // Auto-trigger online search if local search returned no results
            if (!online && data.books.length === 0 && data.has_online_option) {
                console.log(
                    'No local results found, searching online automatically...',
                );
                // Small delay to prevent jarring UX
                setTimeout(() => {
                    performSearch(true);
                }, 300);
            }
        } catch (err) {
            setError('Failed to search books. Please try again.');
            console.error('Search error:', err);
        } finally {
            if (online || !hasOnlineOption) {
                setIsLoading(false);
            }
        }
    };

    const handleSearch = async () => {
        // Start with local search (online defaults to false)
        await performSearch(false);
    };

    const handleFindOnline = async () => {
        await performSearch(true);
    };

    const handleBookClick = (book: Book) => {
        // Check if this is an online book (has external_id but id is string)
        const isOnlineBook = book.external_id && typeof book.id === 'string';

        if (isOnlineBook) {
            // Save the book first, then navigate
            setSavingBookId(book.id);

            router.post(
                '/books/store-and-view',
                {
                    title: book.title,
                    author: book.author,
                    isbn: book.isbn,
                    cover_url: book.cover_url,
                    external_id: book.external_id,
                    ol_work_key: book.ol_work_key,
                    publisher: book.publisher,
                    publish_date: book.publish_date,
                },
                {
                    preserveScroll: true,
                    onFinish: () => setSavingBookId(null),
                },
            );
        } else {
            // Local book, navigate directly
            router.visit(`/books/${book.id}`);
        }
    };

    const handleAddToReviewList = (e: React.MouseEvent, book: Book) => {
        e.preventDefault();
        e.stopPropagation();

        console.log('Adding book to review list:', book);

        // Check if this is a local book (has numeric ID > 0) or online book
        const isLocalBook = typeof book.id === 'number' && book.id > 0;

        // Use Inertia to post the book data
        router.post(
            '/to-review-lists',
            {
                book_id: isLocalBook ? book.id : undefined,
                title: isLocalBook ? undefined : book.title,
                author: isLocalBook ? undefined : book.author,
                isbn: isLocalBook ? undefined : book.isbn,
                cover_url: isLocalBook ? undefined : book.cover_url,
                external_id: isLocalBook ? undefined : book.external_id,
                ol_work_key: isLocalBook ? undefined : book.ol_work_key,
                publisher: isLocalBook ? undefined : book.publisher,
                publish_date: isLocalBook ? undefined : book.publish_date,
            },
            {
                preserveScroll: true,
                preserveState: true,
                only: [],
                onSuccess: () => {
                    console.log('Book added successfully!');
                    // Add book to the set of added books
                    setAddedBookIds((prev) => new Set(prev).add(book.id));
                },
                onError: (errors) => {
                    console.error('Error adding book:', errors);
                },
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Search Books" />

            <div className="flex flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="space-y-2">
                    <h1 className="text-3xl font-bold">Search Books</h1>
                    <p className="text-sm text-muted-foreground">
                        Find and add books to your collection
                    </p>
                </div>

                <SearchInput
                    value={searchQuery}
                    onChange={setSearchQuery}
                    onSearch={handleSearch}
                    isLoading={isLoading}
                    placeholder="Search by title or author..."
                />

                {error && (
                    <ErrorState
                        title="Search Error"
                        description={error}
                        onRetry={handleSearch}
                    />
                )}

                {isLoading ? (
                    <LoadingGrid count={6} />
                ) : searchResults.length > 0 ? (
                    <>
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <span className="text-sm text-muted-foreground">
                                    Found {searchResults.length} book
                                    {searchResults.length !== 1 ? 's' : ''}
                                </span>
                                {searchMode && (
                                    <span
                                        className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                            searchMode === 'local'
                                                ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                                : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                        }`}
                                    >
                                        {searchMode === 'local'
                                            ? 'Local'
                                            : 'Online'}
                                    </span>
                                )}
                            </div>
                            {hasOnlineOption && (
                                <Button
                                    onClick={handleFindOnline}
                                    variant="outline"
                                    size="sm"
                                    disabled={isLoading}
                                >
                                    Find Online
                                </Button>
                            )}
                        </div>

                        {searchMessage && (
                            <p className="text-xs text-muted-foreground italic">
                                {searchMessage}
                            </p>
                        )}

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {searchResults.map((book, index) => (
                                <BookCard
                                    key={`${book.external_id || book.id}-${index}`}
                                    book={book}
                                    onClick={() => handleBookClick(book)}
                                    isLoading={savingBookId === book.id}
                                    actionButton={{
                                        icon: <Heart className="h-4 w-4" />,
                                        onClick: (e) =>
                                            handleAddToReviewList(e, book),
                                        label: 'Add to review list',
                                        variant: 'ghost',
                                        isActive: addedBookIds.has(book.id),
                                    }}
                                />
                            ))}
                        </div>
                    </>
                ) : searchQuery ? (
                    <div className="flex flex-col items-center gap-4">
                        <NoResultsState />
                        {hasOnlineOption && searchMode !== 'online' && (
                            <Button
                                onClick={handleFindOnline}
                                variant="default"
                                size="lg"
                                disabled={isLoading}
                            >
                                Search Online
                            </Button>
                        )}
                    </div>
                ) : books && books.data.length > 0 ? (
                    <>
                        <div className="text-sm text-muted-foreground">
                            Showing latest books
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {books.data.map((book) => (
                                <BookCard
                                    key={book.id}
                                    book={book}
                                    onClick={() => handleBookClick(book)}
                                />
                            ))}
                        </div>
                        {books.links && books.links.length > 0 && (
                            <div className="mt-6 flex flex-wrap items-center justify-center gap-2">
                                {books.links
                                    .filter(
                                        (l) =>
                                            l.label !== '&laquo; Previous' &&
                                            l.label !== 'Next &raquo;',
                                    )
                                    .map((link, idx) => (
                                        <button
                                            key={idx}
                                            disabled={!link.url}
                                            onClick={() =>
                                                link.url &&
                                                router.visit(link.url)
                                            }
                                            className={`rounded px-3 py-1 text-sm transition-colors ${link.active ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/70'} disabled:opacity-40`}
                                        >
                                            {link.label}
                                        </button>
                                    ))}
                            </div>
                        )}
                    </>
                ) : (
                    <div className="py-12 text-center">
                        <p className="text-sm text-muted-foreground">
                            Enter a book title or author name to search
                        </p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
