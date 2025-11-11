<?php

use App\Data\SearchResult;

describe('SearchResult', function () {
    describe('constructor', function () {
        it('can be instantiated with required parameters', function () {
            $books = collect([]);
            $result = new SearchResult(
                isLocal: true,
                books: $books,
                hasOnlineOption: true,
            );

            expect($result->isLocal)->toBeTrue();
            expect($result->books)->toBe($books);
            expect($result->hasOnlineOption)->toBeTrue();
            expect($result->query)->toBeNull();
            expect($result->totalCount)->toBe(0);
            expect($result->source)->toBe('local');
            expect($result->message)->toBeNull();
        });

        it('can be instantiated with all parameters', function () {
            $books = collect([]);
            $result = new SearchResult(
                isLocal: false,
                books: $books,
                hasOnlineOption: false,
                query: 'test query',
                totalCount: 5,
                source: 'online',
                message: 'Search completed',
            );

            expect($result->isLocal)->toBeFalse();
            expect($result->books)->toBe($books);
            expect($result->hasOnlineOption)->toBeFalse();
            expect($result->query)->toBe('test query');
            expect($result->totalCount)->toBe(5);
            expect($result->source)->toBe('online');
            expect($result->message)->toBe('Search completed');
        });
    });

    describe('local() factory', function () {
        it('creates a local search result with books', function () {
            $books = collect([
                (object) ['id' => 1, 'title' => 'Book 1'],
                (object) ['id' => 2, 'title' => 'Book 2'],
            ]);

            $result = SearchResult::local($books, 'test query');

            expect($result->isLocal)->toBeTrue();
            expect($result->books)->toHaveCount(2);
            expect($result->hasOnlineOption)->toBeFalse();
            expect($result->query)->toBe('test query');
            expect($result->totalCount)->toBe(2);
            expect($result->source)->toBe('local');
            expect($result->message)->toContain('Results from your library');
        });

        it('creates a local search result with empty books and online option', function () {
            $books = collect([]);

            $result = SearchResult::local($books, 'test query');

            expect($result->isLocal)->toBeTrue();
            expect($result->books)->toBeEmpty();
            expect($result->hasOnlineOption)->toBeTrue();
            expect($result->query)->toBe('test query');
            expect($result->totalCount)->toBe(0);
            expect($result->source)->toBe('local');
            expect($result->message)->toContain('No local results found');
        });

        it('uses custom message when provided', function () {
            $books = collect([]);
            $customMessage = 'Custom message';

            $result = SearchResult::local($books, 'test query', $customMessage);

            expect($result->message)->toBe($customMessage);
        });
    });

    describe('online() factory', function () {
        it('creates an online search result with books', function () {
            $books = collect([
                (object) ['id' => 1, 'title' => 'Book 1'],
            ]);

            $result = SearchResult::online($books, 'test query', 1);

            expect($result->isLocal)->toBeFalse();
            expect($result->books)->toHaveCount(1);
            expect($result->hasOnlineOption)->toBeFalse();
            expect($result->query)->toBe('test query');
            expect($result->totalCount)->toBe(1);
            expect($result->source)->toBe('online');
            expect($result->message)->toContain('Results from Open Library');
        });

        it('uses totalCount parameter when provided', function () {
            $books = collect([
                (object) ['id' => 1, 'title' => 'Book 1'],
            ]);

            $result = SearchResult::online($books, 'test query', 100);

            expect($result->totalCount)->toBe(100);
        });

        it('falls back to books count when totalCount is zero', function () {
            $books = collect([
                (object) ['id' => 1, 'title' => 'Book 1'],
                (object) ['id' => 2, 'title' => 'Book 2'],
            ]);

            $result = SearchResult::online($books, 'test query', 0);

            expect($result->totalCount)->toBe(2);
        });

        it('uses custom message when provided', function () {
            $books = collect([]);
            $customMessage = 'Custom online message';

            $result = SearchResult::online($books, 'test query', 0, $customMessage);

            expect($result->message)->toBe($customMessage);
        });
    });

    describe('toArray()', function () {
        it('converts search result to array with empty books', function () {
            $result = SearchResult::local(collect([]), 'test query');
            $array = $result->toArray();

            expect($array)->toHaveKeys([
                'is_local',
                'books',
                'has_online_option',
                'query',
                'total_count',
                'source',
                'message',
            ]);
            expect($array['is_local'])->toBeTrue();
            expect($array['books'])->toBeArray();
            expect($array['books'])->toBeEmpty();
            expect($array['has_online_option'])->toBeTrue();
            expect($array['query'])->toBe('test query');
            expect($array['total_count'])->toBe(0);
            expect($array['source'])->toBe('local');
            expect($array['message'])->not->toBeEmpty();
        });

        it('converts search result with books to array', function () {
            $book = new stdClass;
            $book->id = 1;
            $book->title = 'Test Book';
            $book->author = 'Test Author';
            $book->description = 'Test Description';
            $book->cover_url = 'http://example.com/cover.jpg';
            $book->external_id = 'external_123';
            $book->published_year = 2020;
            $book->publisher = 'Test Publisher';

            $result = SearchResult::online(collect([$book]), 'test');
            $array = $result->toArray();

            expect($array['books'])->toHaveCount(1);
            expect($array['books'][0])->toHaveKeys([
                'id',
                'title',
                'author',
                'description',
                'cover_url',
                'external_id',
                'published_year',
                'publisher',
            ]);
            expect($array['books'][0]['id'])->toBe(1);
            expect($array['books'][0]['title'])->toBe('Test Book');
            expect($array['books'][0]['author'])->toBe('Test Author');
        });

        it('handles multiple books in array conversion', function () {
            $books = collect();
            for ($i = 1; $i <= 3; $i++) {
                $book = new stdClass;
                $book->id = $i;
                $book->title = "Book {$i}";
                $book->author = "Author {$i}";
                $book->description = null;
                $book->cover_url = null;
                $book->external_id = "ext_{$i}";
                $book->published_year = 2020 + $i;
                $book->publisher = null;
                $books->push($book);
            }

            $result = SearchResult::online($books, 'test');
            $array = $result->toArray();

            expect($array['books'])->toHaveCount(3);
            expect($array['books'][0]['title'])->toBe('Book 1');
            expect($array['books'][1]['title'])->toBe('Book 2');
            expect($array['books'][2]['title'])->toBe('Book 3');
        });

        it('preserves snake_case keys in array output', function () {
            $result = SearchResult::local(collect([]), 'test');
            $array = $result->toArray();

            expect($array)->toHaveKeys([
                'is_local',
                'has_online_option',
                'total_count',
            ]);
            expect($array)->not->toHaveKeys([
                'isLocal',
                'hasOnlineOption',
                'totalCount',
            ]);
        });
    });
});
