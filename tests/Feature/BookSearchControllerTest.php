<?php

use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Facades\Http;

// Helper function to build query string
function buildQueryString(array $params): string
{
    return '?'.http_build_query($params);
}

describe('BookSearchController', function () {
    describe('GET /api/books/search', function () {
        it('requires authentication', function () {
            $response = $this->getJson('/api/books/search'.buildQueryString(['q' => 'test']));

            $response->assertUnauthorized();
        });

        it('requires query parameter', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->getJson('/api/books/search');

            $response->assertUnprocessable();
            $response->assertJsonValidationErrors('q');
        });

        it('searches local database only by default', function () {
            $user = User::factory()->create();
            Book::factory()->create([
                'title' => 'The Lord of the Rings',
                'author' => 'J. R. R. Tolkien',
            ]);

            $response = $this->actingAs($user)->getJson('/api/books/search'.buildQueryString(['q' => 'Lord of the Rings']));

            $response->assertSuccessful();
            $response->assertJsonPath('is_local', true);
            $response->assertJsonPath('source', 'local');
            expect($response->json('books'))->toHaveCount(1);
            expect($response->json('books.0.title'))->toBe('The Lord of the Rings');
        });

        it('returns books matching title', function () {
            $user = User::factory()->create();
            Book::factory()->create(['title' => 'Harry Potter and the Philosopher\'s Stone']);
            Book::factory()->create(['title' => 'Harry Potter and the Chamber of Secrets']);
            Book::factory()->create(['title' => 'The Lord of the Rings']);

            $response = $this->actingAs($user)->getJson('/api/books/search'.buildQueryString(['q' => 'Harry Potter']));

            $response->assertSuccessful();
            expect($response->json('books'))->toHaveCount(2);
        });

        it('returns books matching author', function () {
            $user = User::factory()->create();
            Book::factory()->create(['title' => 'Book 1', 'author' => 'Stephen King']);
            Book::factory()->create(['title' => 'Book 2', 'author' => 'Stephen King']);
            Book::factory()->create(['title' => 'Book 3', 'author' => 'J. K. Rowling']);

            $response = $this->actingAs($user)->getJson('/api/books/search'.buildQueryString(['q' => 'Stephen King']));

            $response->assertSuccessful();
            expect($response->json('books'))->toHaveCount(2);
        });

        it('limits local results to 20 books', function () {
            $user = User::factory()->create();
            Book::factory(30)->create([
                'title' => 'Matching Book',
            ]);

            $response = $this->actingAs($user)->getJson('/api/books/search'.buildQueryString(['q' => 'Matching']));

            $response->assertSuccessful();
            expect($response->json('books'))->toHaveCount(20);
        });

        it('shows online option when no local results', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->getJson('/api/books/search'.buildQueryString(['q' => 'Nonexistent Book']));

            $response->assertSuccessful();
            $response->assertJsonPath('has_online_option', true);
            $response->assertJsonPath('is_local', true);
        });

        it('returns appropriate message for empty local results', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->getJson('/api/books/search'.buildQueryString(['q' => 'Nonexistent']));

            $response->assertSuccessful();
            expect($response->json('message'))->toContain('No local results found');
        });

        it('returns appropriate message for local results', function () {
            $user = User::factory()->create();
            Book::factory()->create(['title' => 'Found Book']);

            $response = $this->actingAs($user)->getJson('/api/books/search'.buildQueryString(['q' => 'Found']));

            $response->assertSuccessful();
            expect($response->json('message'))->toContain('Results from your library');
        });

        it('increments search count for local results', function () {
            $user = User::factory()->create();
            $book = Book::factory()->create(['title' => 'Test Book']);

            expect($book->search_count)->toBe(0);

            $this->actingAs($user)->getJson('/api/books/search'.buildQueryString(['q' => 'Test']));

            expect($book->fresh()->search_count)->toBe(1);
        });

        it('searches online when online parameter is true', function () {
            $user = User::factory()->create();

            Http::fake([
                'openlibrary.org/search.json*' => Http::response([
                    'docs' => [
                        [
                            'title' => 'The Hobbit',
                            'author_name' => ['J. R. R. Tolkien'],
                            'key' => '/works/OL1W',
                            'cover_i' => 123,
                        ],
                    ],
                ]),
            ]);

            $response = $this->actingAs($user)->getJson('/api/books/search'.buildQueryString(['q' => 'hobbit', 'online' => 1]));

            $response->assertSuccessful();
            $response->assertJsonPath('source', 'online');
            expect($response->json('books'))->not->toBeEmpty();
        });

        it('does not save online results to database automatically', function () {
            $user = User::factory()->create();

            Http::fake([
                'openlibrary.org/search.json*' => Http::response([
                    'docs' => [
                        [
                            'title' => 'The Hobbit',
                            'author_name' => ['J. R. R. Tolkien'],
                            'key' => '/works/OL1W',
                            'first_publish_year' => 1937,
                            'cover_i' => 123,
                        ],
                    ],
                ]),
            ]);

            $this->actingAs($user)->getJson('/api/books/search'.buildQueryString(['q' => 'hobbit', 'online' => 1]));

            // Books should NOT be automatically saved during search
            $this->assertDatabaseMissing('books', [
                'title' => 'The Hobbit',
            ]);
        });

        it('returns successful response structure', function () {
            $user = User::factory()->create();
            Book::factory()->create(['title' => 'Test Book']);

            $response = $this->actingAs($user)->getJson('/api/books/search'.buildQueryString(['q' => 'Test']));

            $response->assertSuccessful();
            $response->assertJsonStructure([
                'is_local',
                'books' => [
                    '*' => [
                        'id',
                        'title',
                        'author',
                        'description',
                        'cover_url',
                        'external_id',
                        'published_year',
                        'publisher',
                    ],
                ],
                'has_online_option',
                'query',
                'total_count',
                'source',
                'message',
            ]);
        });

        it('case-insensitive search', function () {
            $user = User::factory()->create();
            Book::factory()->create([
                'title' => 'The Lord of the Rings',
                'author' => 'J. R. R. Tolkien',
            ]);

            $response = $this->actingAs($user)->getJson('/api/books/search'.buildQueryString(['q' => 'lord of the rings']));

            $response->assertSuccessful();
            expect($response->json('books'))->toHaveCount(1);
        });

        it('handles partial word matches', function () {
            $user = User::factory()->create();
            Book::factory()->create(['title' => 'The Hobbit']);
            Book::factory()->create(['title' => 'Hobbits of Middle Earth']);

            $response = $this->actingAs($user)->getJson('/api/books/search'.buildQueryString(['q' => 'hobbit']));

            $response->assertSuccessful();
            expect($response->json('books'))->toHaveCount(2);
        });

        it('handles special characters in search query', function () {
            $user = User::factory()->create();
            Book::factory()->create(['title' => 'Test & Company']);

            $response = $this->actingAs($user)->getJson('/api/books/search'.buildQueryString(['q' => 'Test & Company']));

            $response->assertSuccessful();
            expect($response->json('books'))->toHaveCount(1);
        });
    });
});
