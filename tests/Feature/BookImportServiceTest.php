<?php

use App\Models\Book;
use App\Services\BookImportService;

beforeEach(function () {
    $this->service = new BookImportService;
});

describe('BookImportService', function () {
    describe('formatOpenLibraryData()', function () {
        it('formats basic Open Library data correctly', function () {
            $data = [
                'title' => 'The Lord of the Rings',
                'author_name' => ['J. R. R. Tolkien'],
                'key' => '/works/OL27448W',
                'first_publish_year' => 1954,
                'cover_i' => 258027,
            ];

            $result = $this->service->formatOpenLibraryData($data);

            expect($result)->toHaveKeys([
                'title',
                'author',
                'description',
                'published_year',
                'isbn',
                'publisher',
                'external_id',
                'cover_url',
                'ol_cover_id',
                'ol_work_key',
            ]);
            expect($result['title'])->toBe('The Lord of the Rings');
            expect($result['author'])->toBe('J. R. R. Tolkien');
            expect($result['published_year'])->toBe(1954);
            expect($result['external_id'])->toBe('/works/OL27448W');
            expect($result['ol_cover_id'])->toBe(258027);
        });

        it('generates correct cover URL from cover_i', function () {
            $data = [
                'title' => 'Test Book',
                'author_name' => ['Test Author'],
                'key' => '/works/OL1W',
                'cover_i' => 123456,
            ];

            $result = $this->service->formatOpenLibraryData($data);

            expect($result['cover_url'])->toBe('https://covers.openlibrary.org/b/id/123456-M.jpg');
        });

        it('handles missing cover_i gracefully', function () {
            $data = [
                'title' => 'Test Book',
                'author_name' => ['Test Author'],
                'key' => '/works/OL1W',
            ];

            $result = $this->service->formatOpenLibraryData($data);

            expect($result['cover_url'])->toBeNull();
            expect($result['ol_cover_id'])->toBeNull();
        });

        it('extracts ISBN from array', function () {
            $data = [
                'title' => 'Test Book',
                'author_name' => ['Test Author'],
                'key' => '/works/OL1W',
                'isbn' => ['9780123456789', '9780987654321'],
            ];

            $result = $this->service->formatOpenLibraryData($data);

            expect($result['isbn'])->toBe('9780123456789');
        });

        it('extracts publisher from array', function () {
            $data = [
                'title' => 'Test Book',
                'author_name' => ['Test Author'],
                'key' => '/works/OL1W',
                'publisher' => ['Test Publisher', 'Another Publisher'],
            ];

            $result = $this->service->formatOpenLibraryData($data);

            expect($result['publisher'])->toBe('Test Publisher');
        });

        it('handles all optional fields', function () {
            $data = [
                'title' => 'Test Book',
                'author_name' => ['Test Author'],
                'key' => '/works/OL1W',
                'description' => 'A great book',
                'first_publish_year' => 2020,
                'isbn' => ['123456'],
                'publisher' => ['Good Publisher'],
                'cover_i' => 999,
            ];

            $result = $this->service->formatOpenLibraryData($data);

            expect($result['description'])->toBe('A great book');
            expect($result['published_year'])->toBe(2020);
            expect($result['isbn'])->toBe('123456');
            expect($result['publisher'])->toBe('Good Publisher');
        });
    });

    describe('extractAuthor()', function () {
        it('extracts author from author_name array', function () {
            $data = [
                'author_name' => ['J. R. R. Tolkien', 'Christopher Tolkien'],
            ];

            $result = $this->service->extractAuthor($data);

            expect($result)->toBe('J. R. R. Tolkien');
        });

        it('extracts author from authors array with name property', function () {
            $data = [
                'authors' => [
                    ['name' => 'J. K. Rowling'],
                    ['name' => 'Someone Else'],
                ],
            ];

            $result = $this->service->extractAuthor($data);

            expect($result)->toBe('J. K. Rowling');
        });

        it('prefers author_name over authors', function () {
            $data = [
                'author_name' => ['First Author'],
                'authors' => [['name' => 'Second Author']],
            ];

            $result = $this->service->extractAuthor($data);

            expect($result)->toBe('First Author');
        });

        it('returns empty string when no authors found', function () {
            $data = [];

            $result = $this->service->extractAuthor($data);

            expect($result)->toBe('');
        });

        it('returns empty string for empty author arrays', function () {
            $data = [
                'author_name' => [],
                'authors' => [],
            ];

            $result = $this->service->extractAuthor($data);

            expect($result)->toBe('');
        });
    });

    describe('deduplicateTitle()', function () {
        it('removes Wikipedia suffix from titles', function () {
            $title = 'The Hobbit - Wikipedia';

            $result = $this->service->deduplicateTitle($title);

            expect($result)->toBe('The Hobbit');
        });

        it('removes Wikipedia suffix with em dash', function () {
            $title = 'Harry Potter — Wikipedia';

            $result = $this->service->deduplicateTitle($title);

            expect($result)->toBe('Harry Potter');
        });

        it('removes Wikipedia suffix with hyphen', function () {
            $title = 'Dune – Wikipedia';

            $result = $this->service->deduplicateTitle($title);

            expect($result)->toBe('Dune');
        });

        it('preserves titles without Wikipedia suffix', function () {
            $title = 'The Lord of the Rings';

            $result = $this->service->deduplicateTitle($title);

            expect($result)->toBe('The Lord of the Rings');
        });

        it('handles case-insensitive Wikipedia suffix', function () {
            $title = 'Test Book - wikipedia';

            $result = $this->service->deduplicateTitle($title);

            expect($result)->toBe('Test Book');
        });

        it('trims whitespace around result', function () {
            $title = '  Test Book   ';

            $result = $this->service->deduplicateTitle($title);

            expect($result)->toBe('Test Book');
        });
    });

    describe('upsertFromOpenLibrary()', function () {
        it('creates a new book', function () {
            $data = [
                'title' => 'New Book',
                'author' => 'New Author',
                'description' => 'A description',
                'isbn' => '123456789',
                'published_year' => 2020,
                'publisher' => 'Test Publisher',
                'external_id' => '/works/OL1W',
                'cover_url' => 'http://example.com/cover.jpg',
                'ol_work_key' => '/works/OL1W',
                'ol_cover_id' => 123,
            ];

            $book = $this->service->upsertFromOpenLibrary($data);

            expect($book)->toBeInstanceOf(Book::class);
            expect($book->title)->toBe('New Book');
            expect($book->author)->toBe('New Author');
            expect($book->description)->toBe('A description');
            expect($book->isbn)->toBe('123456789');
            expect($book->published_year)->toBe(2020);
            expect($book->publisher)->toBe('Test Publisher');
            expect($book->external_id)->toBe('/works/OL1W');
            expect($book->ol_cover_id)->toBe(123);
            expect($book->is_user_created)->toBeFalse();
            expect($book->discovered_via_search)->toBeFalse();
            expect($book->search_count)->toBe(0);
            expect($book->cover_stored_locally)->toBeFalse();

            $this->assertDatabaseHas('books', [
                'title' => 'New Book',
                'author' => 'New Author',
            ]);
        });

        it('updates existing book by title and author', function () {
            $existing = Book::factory()->create([
                'title' => 'Existing Book',
                'author' => 'Existing Author',
                'isbn' => null,
                'publisher' => null,
            ]);

            $data = [
                'title' => 'Existing Book',
                'author' => 'Existing Author',
                'isbn' => '123456789',
                'publisher' => 'Updated Publisher',
                'published_year' => 2025,
                'description' => 'Updated description',
                'external_id' => '/works/OL999W',
                'cover_url' => 'http://example.com/cover.jpg',
                'ol_work_key' => '/works/OL999W',
                'ol_cover_id' => 999,
            ];

            $book = $this->service->upsertFromOpenLibrary($data);

            expect($book->id)->toBe($existing->id);
            expect($book->isbn)->toBe('123456789');
            expect($book->publisher)->toBe('Updated Publisher');
            expect($book->published_year)->toBe(2025);

            $this->assertDatabaseCount('books', 1);
        });

        it('preserves existing metadata when updating', function () {
            $existing = Book::factory()->create([
                'title' => 'Existing Book',
                'author' => 'Existing Author',
                'description' => 'Original description',
            ]);

            $data = [
                'title' => 'Existing Book',
                'author' => 'Existing Author',
                'description' => null,
                'external_id' => '/works/OL999W',
                'cover_url' => 'http://example.com/cover.jpg',
                'ol_work_key' => '/works/OL999W',
                'ol_cover_id' => null,
            ];

            $book = $this->service->upsertFromOpenLibrary($data);

            expect($book->description)->toBe('Original description');
        });

        it('handles minimal data for new book', function () {
            $data = [
                'title' => 'Minimal Book',
                'author' => 'Minimal Author',
                'description' => null,
                'isbn' => null,
                'published_year' => null,
                'publisher' => null,
                'external_id' => null,
                'cover_url' => null,
                'ol_work_key' => null,
                'ol_cover_id' => null,
            ];

            $book = $this->service->upsertFromOpenLibrary($data);

            expect($book)->toBeInstanceOf(Book::class);
            expect($book->title)->toBe('Minimal Book');
            expect($book->author)->toBe('Minimal Author');
            expect($book->isbn)->toBeNull();
            expect($book->publisher)->toBeNull();
        });

        it('updates last_synced_at on upsert', function () {
            $before = now();

            $data = [
                'title' => 'Book',
                'author' => 'Author',
            ];

            $book = $this->service->upsertFromOpenLibrary($data);

            expect($book->last_synced_at)->not->toBeNull();
            expect($book->last_synced_at->timestamp)->toBeGreaterThanOrEqual($before->timestamp);
        });

        it('sets first_discovered_at for new books', function () {
            $before = now();

            $data = [
                'title' => 'New Book',
                'author' => 'New Author',
            ];

            $book = $this->service->upsertFromOpenLibrary($data);

            expect($book->first_discovered_at)->not->toBeNull();
            expect($book->first_discovered_at->timestamp)->toBeGreaterThanOrEqual($before->timestamp);
        });
    });
});
