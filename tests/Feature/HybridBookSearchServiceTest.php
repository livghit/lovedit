<?php

use App\Models\Book;
use App\Services\BookSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(BookSearchService::class);
    Cache::clear();
});

describe('BookSearchService - Hybrid Search', function () {
    describe('search()', function () {
        it('searches locally by default', function () {
            $book = Book::factory()->create(['title' => 'Local Book']);

            $result = $this->service->search('Local');

            expect($result->isLocal)->toBeTrue();
            expect($result->books)->toHaveCount(1);
            expect($result->books->first()->title)->toBe('Local Book');
        });

        it('shows online option when no local results', function () {
            $result = $this->service->search('Nonexistent');

            expect($result->isLocal)->toBeTrue();
            expect($result->hasOnlineOption)->toBeTrue();
            expect($result->books)->toBeEmpty();
        });

        it('searches online when forceOnline is true', function () {
            Http::fake([
                'openlibrary.org/search.json*' => Http::response([
                    'docs' => [
                        [
                            'title' => 'Online Book',
                            'author_name' => ['Author'],
                            'key' => '/works/OL1W',
                            'cover_i' => 123,
                        ],
                    ],
                ]),
            ]);

            $result = $this->service->search('Online', true);

            expect($result->isLocal)->toBeFalse();
            expect($result->source)->toBe('online');
        });

        it('prefers local results over online option', function () {
            Book::factory()->create(['title' => 'Local Result']);

            $result = $this->service->search('Local');

            expect($result->isLocal)->toBeTrue();
            expect($result->hasOnlineOption)->toBeFalse();
        });
    });

    describe('searchLocal()', function () {
        it('returns search result object', function () {
            Book::factory()->create(['title' => 'Test Book']);

            $result = $this->service->searchLocal('Test');

            expect($result->isLocal)->toBeTrue();
            expect($result->source)->toBe('local');
            expect($result->hasOnlineOption)->toBeFalse();
        });

        it('finds books by title', function () {
            Book::factory()->create(['title' => 'The Lord of the Rings']);
            Book::factory()->create(['title' => 'Other Book']);

            $result = $this->service->searchLocal('Lord');

            expect($result->books)->toHaveCount(1);
            expect($result->books->first()->title)->toBe('The Lord of the Rings');
        });

        it('finds books by author', function () {
            Book::factory()->create(['author' => 'Stephen King']);
            Book::factory()->create(['author' => 'Other Author']);

            $result = $this->service->searchLocal('Stephen King');

            expect($result->books)->toHaveCount(1);
            expect($result->books->first()->author)->toBe('Stephen King');
        });

        it('returns empty collection with online option when no results', function () {
            $result = $this->service->searchLocal('Nonexistent');

            expect($result->books)->toBeEmpty();
            expect($result->hasOnlineOption)->toBeTrue();
        });

        it('increments search count for found books', function () {
            $book = Book::factory()->create(['title' => 'Popular Book', 'search_count' => 0]);

            $this->service->searchLocal('Popular');

            expect($book->fresh()->search_count)->toBe(1);
        });

        it('limits results to 20 books', function () {
            Book::factory(30)->create(['title' => 'Matching']);

            $result = $this->service->searchLocal('Matching');

            expect($result->books)->toHaveCount(20);
        });

        it('includes appropriate message', function () {
            Book::factory()->create(['title' => 'Book']);

            $result = $this->service->searchLocal('Book');

            expect($result->message)->toContain('Results from your library');
        });

        it('sets query in result', function () {
            $result = $this->service->searchLocal('test query');

            expect($result->query)->toBe('test query');
        });
    });

    describe('searchOnline()', function () {
        it('returns online search result', function () {
            Http::fake([
                'openlibrary.org/search.json*' => Http::response(['docs' => []]),
            ]);

            $result = $this->service->searchOnline('test');

            expect($result->isLocal)->toBeFalse();
            expect($result->source)->toBe('online');
        });

        it('fetches from Open Library API', function () {
            Http::fake([
                'openlibrary.org/search.json*' => Http::response([
                    'docs' => [
                        [
                            'title' => 'The Hobbit',
                            'author_name' => ['Tolkien'],
                            'key' => '/works/OL1W',
                            'cover_i' => 123456,
                        ],
                    ],
                ]),
            ]);

            $result = $this->service->searchOnline('hobbit');

            expect($result->books)->toHaveCount(1);
            expect($result->books->first()->title)->toBe('The Hobbit');

            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'openlibrary.org/search.json')
                    && $request->url() === 'https://openlibrary.org/search.json?q=hobbit&limit=20';
            });
        });

        it('caches online search results', function () {
            Http::fake([
                'openlibrary.org/search.json*' => Http::response([
                    'docs' => [
                        [
                            'title' => 'Test Book',
                            'author_name' => ['Author'],
                            'key' => '/works/OL1W',
                            'cover_i' => 123,
                        ],
                    ],
                ]),
            ]);

            $result1 = $this->service->searchOnline('test');
            $result2 = $this->service->searchOnline('test');

            expect($result1->books->first()->title)->toBe($result2->books->first()->title);
            Http::assertSentCount(1); // Only one search API call (work details are fetched in background job)
        });

        it('does not save results to database automatically', function () {
            Http::fake([
                'openlibrary.org/search.json*' => Http::response([
                    'docs' => [
                        [
                            'title' => 'Unsaved Book',
                            'author_name' => ['Save Author'],
                            'key' => '/works/OL999W',
                            'first_publish_year' => 2020,
                            'cover_i' => 123,
                        ],
                    ],
                ]),
            ]);

            $this->service->searchOnline('saved');

            // Books should NOT be automatically saved during search
            $this->assertDatabaseMissing('books', [
                'title' => 'Unsaved Book',
            ]);
        });

        it('handles API errors gracefully', function () {
            Http::fake([
                'openlibrary.org/search.json*' => Http::response([], 500),
            ]);

            $result = $this->service->searchOnline('test');

            expect($result->books)->toBeEmpty();
        });

        it('returns appropriate online message', function () {
            Http::fake([
                'openlibrary.org/search.json*' => Http::response(['docs' => []]),
            ]);

            $result = $this->service->searchOnline('test');

            expect($result->message)->toContain('Results from Open Library');
        });

        it('returns search results without saving to database', function () {
            Http::fake([
                'openlibrary.org/search.json*' => Http::response([
                    'docs' => [
                        [
                            'title' => 'Unsaved Book',
                            'author_name' => ['Author'],
                            'key' => '/works/OL1W',
                            'cover_i' => 123,
                        ],
                    ],
                ]),
            ]);

            $result = $this->service->searchOnline('test');

            // Results should be returned
            expect($result->books)->toHaveCount(1);
            expect($result->books->first()->title)->toBe('Unsaved Book');

            // But book should NOT be saved to database
            $this->assertDatabaseMissing('books', [
                'title' => 'Unsaved Book',
            ]);
        });
    });

    describe('saveSearchResults()', function () {
        it('saves collection of API results', function () {
            $results = collect([
                [
                    'title' => 'Book 1',
                    'author_name' => ['Author 1'],
                    'key' => '/works/OL1W',
                    'cover_i' => 123,
                    'cover_url' => 'http://example.com/cover.jpg',
                ],
            ]);

            $this->service->saveSearchResults($results);

            $this->assertDatabaseHas('books', [
                'title' => 'Book 1',
                'author' => 'Author 1',
            ]);
        });

        it('handles multiple results', function () {
            $results = collect([
                [
                    'title' => 'Book 1',
                    'author_name' => ['Author'],
                    'key' => '/works/OL1W',
                    'cover_i' => 1,
                    'cover_url' => 'url1',
                ],
                [
                    'title' => 'Book 2',
                    'author_name' => ['Author'],
                    'key' => '/works/OL2W',
                    'cover_i' => 2,
                    'cover_url' => 'url2',
                ],
            ]);

            $this->service->saveSearchResults($results);

            $this->assertDatabaseCount('books', 2);
        });

        it('creates sync batch', function () {
            $results = collect([
                [
                    'title' => 'Book',
                    'author_name' => ['Author'],
                    'key' => '/works/OL1W',
                    'cover_i' => 1,
                    'cover_url' => 'url',
                ],
            ]);

            $this->service->saveSearchResults($results);

            $this->assertDatabaseHas('sync_batches', [
                'type' => 'manual_search',
                'status' => 'completed',
            ]);
        });

        it('handles save errors gracefully', function () {
            $results = collect([
                [
                    'title' => 'Valid Book',
                    'author_name' => ['Author'],
                    'key' => '/works/OL1W',
                    'cover_i' => 1,
                    'cover_url' => 'url',
                ],
                // Invalid data without required fields
                [
                    'cover_i' => 2,
                ],
            ]);

            // Should not throw exception
            $this->service->saveSearchResults($results);

            // At least the valid one should be saved
            $this->assertDatabaseHas('books', [
                'title' => 'Valid Book',
            ]);
        });

        it('skips books without covers', function () {
            $results = collect([
                [
                    'title' => 'Book With Cover',
                    'author_name' => ['Author 1'],
                    'key' => '/works/OL1W',
                    'cover_i' => 123,
                    'cover_url' => 'url',
                ],
                [
                    'title' => 'Book Without Cover',
                    'author_name' => ['Author 2'],
                    'key' => '/works/OL2W',
                    // No cover_i field
                ],
            ]);

            $this->service->saveSearchResults($results);

            // Only book with cover should be saved
            $this->assertDatabaseHas('books', [
                'title' => 'Book With Cover',
            ]);

            $this->assertDatabaseMissing('books', [
                'title' => 'Book Without Cover',
            ]);

            $this->assertDatabaseCount('books', 1);
        });
    });

    describe('rate limiting', function () {
        it('respects rate limit', function () {
            Http::fake([
                'openlibrary.org/search.json*' => Http::response(['docs' => []]),
            ]);

            // Make 30 calls (at limit)
            for ($i = 0; $i < 30; $i++) {
                $this->service->searchOnline("query{$i}");
            }

            // 31st call should hit rate limit
            $result = $this->service->searchOnline('query31');
            expect($result->books)->toBeEmpty();

            // Should have made exactly 30 API calls
            Http::assertSentCount(30);
        });
    });

    describe('cache', function () {
        it('caches search results for 72 hours', function () {
            Http::fake([
                'openlibrary.org/search.json*' => Http::response([
                    'docs' => [
                        [
                            'title' => 'Cached Book',
                            'author_name' => ['Author'],
                            'key' => '/works/OL1W',
                            'cover_i' => 123,
                        ],
                    ],
                ]),
            ]);

            // First call hits API
            $result1 = $this->service->searchOnline('cached');

            // Second call should be cached
            $result2 = $this->service->searchOnline('cached');

            expect($result1->books->first()->title)->toBe($result2->books->first()->title);
            Http::assertSentCount(1); // Only one search call (work details are fetched in background job)
        });
    });
});
