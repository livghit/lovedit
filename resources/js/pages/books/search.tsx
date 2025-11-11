import BookCard from '@/components/BookCard';
import { ErrorState, NoResultsState } from '@/components/EmptyState';
import SearchInput from '@/components/SearchInput';
import { LoadingGrid } from '@/components/Skeletons';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface Book {
    id: string | number;
    title: string;
    author: string;
    cover_url?: string | null;
    isbn?: string;
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
                throw new Error('Search failed');
            }

            const data: SearchApiResponse = await response.json();

            setSearchResults(data.books);
            setSearchMode(data.is_local ? 'local' : 'online');
            setHasOnlineOption(data.has_online_option);
            setSearchMessage(data.message);
        } catch (err) {
            setError('Failed to search books. Please try again.');
            console.error('Search error:', err);
        } finally {
            setIsLoading(false);
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
        router.visit(`/books/${book.id}`);
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
                            {searchResults.map((book) => (
                                <BookCard
                                    key={book.id}
                                    book={book}
                                    onClick={() => handleBookClick(book)}
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
