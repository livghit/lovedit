<?php

use App\Data\SearchResult;
use App\Models\Book;
use App\Models\User;
use App\Services\BookSearchService;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\mock;

describe('BookController', function () {
    describe('search', function () {
        it('renders the search page without a query', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->get(route('books.search'));

            $response->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('books/search')
                    ->has('results', 0)
                    ->where('query', null)
                    ->has('books.data')
                );
        });

        it('renders results on the search page when a query is provided', function () {
            $user = User::factory()->create();

            $mockBooks = collect([
                (object) [
                    'id' => 1,
                    'title' => 'Test Book',
                    'author' => 'Jane Doe',
                    'external_id' => '/works/OL12345W',
                    'isbn' => '1234567890',
                    'published_year' => 2020,
                    'description' => 'A mocked book',
                    'publisher' => 'Mock Publisher',
                    'cover_url' => 'https://example.com/cover.jpg',
                ],
            ]);

            $mockResults = new SearchResult(
                isLocal: true,
                books: $mockBooks,
                hasOnlineOption: false,
                query: 'laravel',
                totalCount: 1,
                source: 'local',
                message: 'Results from your library'
            );

            mock(BookSearchService::class)
                ->expects('search')
                ->with('laravel', false)
                ->andReturn($mockResults);

            $response = $this->actingAs($user)->get(route('books.search', ['query' => 'laravel']));

            $response->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('books/search')
                    ->has('results')
                    ->where('query', 'laravel')
                    ->where('books', null)
                );
        });

        it('redirects to login when unauthenticated', function () {
            $response = $this->get(route('books.search'));

            $response->assertRedirect(route('login'));
        });

        it('validates query length when provided (min 2)', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->get(route('books.search', ['query' => 'a']));

            $response->assertSessionHasErrors('query');
        });
    });

    describe('show', function () {
        it('renders a book page with its reviews', function () {
            $user = User::factory()->create();
            $book = Book::factory()->has(\App\Models\Review::factory()->count(1), 'reviews')->create();

            $response = $this->actingAs($user)->get(route('books.show', $book));

            $response->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('books/show')
                    ->has('book')
                    ->has('book.reviews', 1)
                );
        });

        it('includes reviews count on the book page', function () {
            $user = User::factory()->create();
            $book = Book::factory()->has(
                \App\Models\Review::factory()->count(2),
                'reviews'
            )->create();

            $response = $this->actingAs($user)->get(route('books.show', $book));

            $response->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('books/show')
                    ->has('book.reviews', 2)
                );
        });

        it('redirects to login when unauthenticated', function () {
            $book = Book::factory()->create();

            $response = $this->get(route('books.show', $book));

            $response->assertRedirect(route('login'));
        });

        it('returns 404 for non-existent book', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->get('/books/999999');

            $response->assertNotFound();
        });
    });

    describe('store', function () {
        it('creates a new book', function () {
            $user = User::factory()->create();
            $bookData = [
                'title' => 'Test Book',
                'author' => 'Test Author',
                'description' => 'A test book',
                'isbn' => '978-3-16-148410-0',
                'cover_url' => 'https://example.com/cover.jpg',
                'external_id' => 'test_external_123',
                'published_year' => 2023,
                'publisher' => 'Test Publisher',
            ];

            $response = $this->actingAs($user)->postJson(route('books.store'), $bookData);

            $response->assertCreated()
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'title',
                        'author',
                        'description',
                        'isbn',
                        'cover_url',
                        'external_id',
                        'published_year',
                        'publisher',
                    ],
                ]);

            $this->assertDatabaseHas('books', [
                'title' => 'Test Book',
                'author' => 'Test Author',
                'external_id' => 'test_external_123',
            ]);
        });

        it('returns existing book if external_id already exists', function () {
            $user = User::factory()->create();
            $existingBook = Book::factory()->create([
                'external_id' => 'existing_external_id',
            ]);
            $bookData = [
                'title' => 'Different Title',
                'author' => 'Different Author',
                'cover_url' => 'https://example.com/cover.jpg',
                'external_id' => 'existing_external_id',
            ];

            $response = $this->actingAs($user)->postJson(route('books.store'), $bookData);

            $response->assertSuccessful()
                ->assertJson([
                    'data' => [
                        'id' => $existingBook->id,
                        'external_id' => 'existing_external_id',
                    ],
                ]);

            expect(Book::where('external_id', 'existing_external_id')->count())->toBe(1);
        });

        it('requires authentication', function () {
            $bookData = [
                'title' => 'Test Book',
                'author' => 'Test Author',
            ];

            $response = $this->postJson(route('books.store'), $bookData);

            $response->assertUnauthorized();
        });

        it('validates required fields', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->postJson(route('books.store'), []);

            $response->assertUnprocessable();
        });
    });

    describe('storeManual', function () {
        it('creates a manual book with only title', function () {
            $user = User::factory()->create();
            $bookData = [
                'title' => 'Manually Added Book',
            ];

            $response = $this->actingAs($user)->post(route('books.store-manual'), $bookData);

            $response->assertRedirect();

            $this->assertDatabaseHas('books', [
                'title' => 'Manually Added Book',
                'is_user_created' => true,
            ]);
        });

        it('creates a manual book with all optional fields', function () {
            $user = User::factory()->create();
            $bookData = [
                'title' => 'Complete Manual Book',
                'author' => 'John Doe',
                'isbn' => '978-3-16-148410-0',
                'cover_url' => 'https://example.com/cover.jpg',
                'publisher' => 'Test Publisher',
                'publish_date' => 2023,
                'description' => 'A detailed description',
            ];

            $response = $this->actingAs($user)->post(route('books.store-manual'), $bookData);

            $response->assertRedirect();

            $this->assertDatabaseHas('books', [
                'title' => 'Complete Manual Book',
                'author' => 'John Doe',
                'isbn' => '978-3-16-148410-0',
                'publisher' => 'Test Publisher',
                'is_user_created' => true,
            ]);
        });

        it('requires authentication for manual book creation', function () {
            $bookData = ['title' => 'Test Book'];

            $response = $this->post(route('books.store-manual'), $bookData);

            $response->assertRedirect(route('login'));
        });

        it('validates title is required for manual book creation', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->post(route('books.store-manual'), []);

            $response->assertSessionHasErrors('title');
        });

        it('validates cover_url must be a valid URL', function () {
            $user = User::factory()->create();
            $bookData = [
                'title' => 'Test Book',
                'cover_url' => 'not-a-valid-url',
            ];

            $response = $this->actingAs($user)->post(route('books.store-manual'), $bookData);

            $response->assertSessionHasErrors('cover_url');
        });

        it('validates publish_date must be a valid year', function () {
            $user = User::factory()->create();
            $bookData = [
                'title' => 'Test Book',
                'publish_date' => 999, // Too early
            ];

            $response = $this->actingAs($user)->post(route('books.store-manual'), $bookData);

            $response->assertSessionHasErrors('publish_date');
        });
    });

    describe('storeAndView', function () {
        it('creates a new book and redirects to book page', function () {
            $user = User::factory()->create();
            $bookData = [
                'title' => 'Test Book',
                'author' => 'Test Author',
                'description' => 'A test book',
                'isbn' => '978-3-16-148410-0',
                'cover_url' => 'https://example.com/cover.jpg',
                'external_id' => 'test_external_123',
                'published_year' => 2023,
                'publisher' => 'Test Publisher',
            ];

            $response = $this->actingAs($user)->post(route('books.store-and-view'), $bookData);

            $response->assertRedirect();

            $this->assertDatabaseHas('books', [
                'title' => 'Test Book',
                'author' => 'Test Author',
                'external_id' => 'test_external_123',
            ]);

            $book = Book::where('external_id', 'test_external_123')->first();
            $response->assertRedirect(route('books.show', $book));
        });

        it('redirects to existing book if external_id already exists', function () {
            $user = User::factory()->create();
            $existingBook = Book::factory()->create([
                'external_id' => 'existing_external_id',
            ]);
            $bookData = [
                'title' => 'Different Title',
                'author' => 'Different Author',
                'cover_url' => 'https://example.com/cover.jpg',
                'external_id' => 'existing_external_id',
            ];

            $response = $this->actingAs($user)->post(route('books.store-and-view'), $bookData);

            $response->assertRedirect(route('books.show', $existingBook));

            expect(Book::where('external_id', 'existing_external_id')->count())->toBe(1);
        });

        it('requires authentication', function () {
            $bookData = [
                'title' => 'Test Book',
                'author' => 'Test Author',
            ];

            $response = $this->post(route('books.store-and-view'), $bookData);

            $response->assertRedirect(route('login'));
        });

        it('validates required fields', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->post(route('books.store-and-view'), []);

            $response->assertSessionHasErrors(['title', 'author']);
        });
    });
});
