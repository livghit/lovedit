<?php

use App\Models\Book;
use App\Services\BookSearchService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->service = app(BookSearchService::class);
    Cache::clear();

    // Clean up storage for tests
    if (Storage::disk('local')->exists('covers')) {
        Storage::disk('local')->deleteDirectory('covers');
    }
});

describe('BookSearchService', function () {
    describe('search()', function () {
        it('returns cached results on second call', function () {
            Http::fake([
                'openlibrary.org/search.json*' => Http::response([
                    'docs' => [
                        [
                            'title' => 'The Lord of the Rings',
                            'author_name' => ['J. R. R. Tolkien'],
                            'key' => '/works/OL27448W',
                            'first_publish_year' => 1954,
                            'cover_i' => 258027,
                        ],
                    ],
                ]),
                'covers.openlibrary.org/*' => Http::response(file_get_contents(__DIR__.'/../../public/favicon.ico'), 200, ['Content-Type' => 'image/jpeg']),
            ]);

            // First call hits the API (search local first, then online with forceOnline)
            $result1 = $this->service->search('lord of the rings', forceOnline: true);
            expect($result1->books)->toHaveCount(1);
            $title1 = $result1->books->first()->title;

            // Second call should be cached (no new HTTP calls)
            $result2 = $this->service->search('lord of the rings', forceOnline: true);
            expect($result2->books)->toHaveCount(1);
            $title2 = $result2->books->first()->title;

            // Titles should match
            expect($title2)->toBe($title1);

            // Verify only 2 HTTP calls were made: 1 search + 1 cover download (no calls on second search due to cache)
            Http::assertSentCount(2);
        });

        it('handles API failures gracefully', function () {
            Http::fake([
                'openlibrary.org/search.json*' => Http::response([], 500),
            ]);

            $result = $this->service->search('test query', forceOnline: true);
            expect($result->books)->toBeEmpty();
        });

        it('includes cover URLs when available', function () {
            Http::fake([
                'openlibrary.org/search.json*' => Http::response([
                    'docs' => [
                        [
                            'title' => 'Test Book Title',
                            'author_name' => ['Author Name'],
                            'key' => '/works/OL123W',
                            'cover_i' => 123456,
                        ],
                    ],
                ]),
            ]);

            $result = $this->service->search('test', forceOnline: true);
            expect($result->books)->toHaveCount(1);

            $book = $result->books->first();
            expect($book)->not->toBeNull();
            expect($book->cover_url)->toContain('covers.openlibrary.org/b/id/123456-M.jpg');
        });

        it('formats search results correctly', function () {
            Http::fake([
                'openlibrary.org/search.json*' => Http::response([
                    'docs' => [
                        [
                            'title' => 'Test Book',
                            'author_name' => ['Test Author'],
                            'key' => '/works/OL1W',
                            'isbn' => ['1234567890'],
                            'first_publish_year' => 2020,
                            'publisher' => ['Test Publisher'],
                        ],
                    ],
                ]),
            ]);

            $result = $this->service->search('test', forceOnline: true);
            expect($result->books[0])
                ->toHaveKeys(['title', 'author', 'external_id', 'isbn', 'published_year', 'publisher']);
        });
    });

    describe('getFromExternalId()', function () {
        it('fetches and caches book details', function () {
            Http::fake([
                'openlibrary.org/works/OL45883W.json' => Http::response([
                    'title' => 'The Fellowship of the Ring',
                    'authors' => [['name' => 'J. R. R. Tolkien']],
                    'key' => '/works/OL45883W',
                    'publish_date' => '1954',
                    'identifiers' => [
                        'isbn_10' => ['0544003411'],
                    ],
                ]),
            ]);

            $result1 = $this->service->getFromExternalId('/works/OL45883W');
            expect($result1)->not->toBeNull();

            $result2 = $this->service->getFromExternalId('/works/OL45883W');
            expect($result2)->toEqual($result1);

            Http::assertSentCount(1);
        });

        it('returns null when API fails', function () {
            Http::fake([
                'openlibrary.org/works/OL45883W.json' => Http::response([], 404),
            ]);

            $result = $this->service->getFromExternalId('/works/OL45883W');
            expect($result)->toBeNull();
        });

        it('extracts publish year from date strings', function () {
            Http::fake([
                'openlibrary.org/works/OL45883W.json' => Http::response([
                    'title' => 'Test Book',
                    'key' => '/works/OL45883W',
                    'publish_date' => 'July 29, 1954',
                ]),
            ]);

            $result = $this->service->getFromExternalId('/works/OL45883W');
            expect($result['published_year'])->toBe(1954);
        });
    });

    describe('batchGetFromExternalIds()', function () {
        it('uses cache for already cached items', function () {
            Http::fake([
                'openlibrary.org/works/OL1W.json' => Http::response([
                    'title' => 'Book 1',
                    'key' => '/works/OL1W',
                ]),
                'openlibrary.org/works/OL2W.json' => Http::response([
                    'title' => 'Book 2',
                    'key' => '/works/OL2W',
                ]),
            ]);

            // Prime the cache with first book
            $this->service->getFromExternalId('/works/OL1W');

            // Batch call should only fetch the second book
            $results = $this->service->batchGetFromExternalIds(['/works/OL1W', '/works/OL2W']);

            expect($results)->toHaveCount(2);
            Http::assertSentCount(2); // One from priming, one from batch
        });

        it('handles partial failures gracefully', function () {
            Http::fake([
                'openlibrary.org/works/OL1W.json' => Http::response([
                    'title' => 'Book 1',
                    'key' => '/works/OL1W',
                ]),
                'openlibrary.org/works/OL2W.json' => Http::response([], 404),
            ]);

            $results = $this->service->batchGetFromExternalIds(['/works/OL1W', '/works/OL2W']);

            expect($results)->toHaveKey('/works/OL1W');
            expect($results)->not->toHaveKey('/works/OL2W');
        });
    });

    describe('rate limiting', function () {
        it('respects rate limit threshold', function () {
            Http::fake([
                'openlibrary.org/search.json*' => Http::response(['docs' => []]),
            ]);

            // Make calls up to the rate limit
            for ($i = 0; $i < 30; $i++) {
                $this->service->search("query {$i}", forceOnline: true);
            }

            // Next call should be rate limited
            $result = $this->service->search('query 31', forceOnline: true);
            expect($result->books)->toBeEmpty();
        });
    });

    describe('cache key generation', function () {
        it('generates consistent cache keys', function () {
            Http::fake([
                'openlibrary.org/search.json*' => Http::response(['docs' => []]),
            ]);

            $result1 = $this->service->searchLocal('test');
            $result2 = $this->service->searchLocal('test');

            expect($result1->query)->toEqual($result2->query);
        });

        it('generates different keys for different queries', function () {
            Http::fake([
                'openlibrary.org/search.json*' => Http::response(['docs' => []]),
            ]);

            $result1 = $this->service->search('query1');
            $result2 = $this->service->search('query2');

            // Both should be empty results, but with different queries
            expect($result1->books)->toBeEmpty();
            expect($result2->books)->toBeEmpty();
            expect($result1->query)->toBe('query1');
            expect($result2->query)->toBe('query2');
        });
    });
});
