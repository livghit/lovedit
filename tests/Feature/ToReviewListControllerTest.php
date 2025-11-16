<?php

use App\Models\Book;
use App\Models\Review;
use App\Models\ToReviewList;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

describe('ToReviewListController', function () {
    describe('index', function () {
        it('returns pending to-review items for authenticated user', function () {
            $user = User::factory()->create();
            $books = Book::factory()->count(3)->create();
            $items = [];
            foreach ($books as $book) {
                $items[] = ToReviewList::factory()->create([
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                ]);
            }

            $response = $this->actingAs($user)->get(route('to-review-lists.index'));

            $response->assertSuccessful();
        });

        it('only returns to-review items for authenticated user', function () {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();
            $books1 = Book::factory()->count(2)->create();
            $books2 = Book::factory()->count(3)->create();

            foreach ($books1 as $book) {
                ToReviewList::factory()->create(['user_id' => $user1->id, 'book_id' => $book->id]);
            }
            foreach ($books2 as $book) {
                ToReviewList::factory()->create(['user_id' => $user2->id, 'book_id' => $book->id]);
            }

            $response = $this->actingAs($user1)->get(route('to-review-lists.index'));

            $response->assertSuccessful();
        });

        it('excludes books already reviewed by the user', function () {
            $user = User::factory()->create();
            $bookReviewed = Book::factory()->create();
            $bookPending = Book::factory()->create();

            // User has an existing review for one book
            Review::factory()->create([
                'user_id' => $user->id,
                'book_id' => $bookReviewed->id,
            ]);

            // Both books are in to-review list
            ToReviewList::factory()->create([
                'user_id' => $user->id,
                'book_id' => $bookReviewed->id,
            ]);
            ToReviewList::factory()->create([
                'user_id' => $user->id,
                'book_id' => $bookPending->id,
            ]);

            $response = $this->actingAs($user)->get(route('to-review-lists.index'));

            $response->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('to-review-lists/index')
                    ->has('items', 1)
                    ->where('items.0.book.id', $bookPending->id)
                );
        });

        it('returns items in pending order (newest first)', function () {
            $user = User::factory()->create();
            $book1 = Book::factory()->create();
            $book2 = Book::factory()->create();
            $oldItem = ToReviewList::factory()->create([
                'user_id' => $user->id,
                'book_id' => $book1->id,
                'added_at' => now()->subDays(5),
            ]);
            $newItem = ToReviewList::factory()->create([
                'user_id' => $user->id,
                'book_id' => $book2->id,
                'added_at' => now(),
            ]);

            $response = $this->actingAs($user)->get(route('to-review-lists.index'));

            $response->assertSuccessful();
        });

        it('requires authentication', function () {
            $response = $this->get(route('to-review-lists.index'));

            $response->assertRedirect(route('login'));
        });
    });

    describe('store', function () {
        it('adds a book to to-review list for authenticated user', function () {
            $user = User::factory()->create();
            $book = Book::factory()->create();

            $response = $this->actingAs($user)->post(route('to-review-lists.store'), [
                'book_id' => $book->id,
            ]);

            $response->assertRedirect();
            $response->assertSessionHas('success');

            $this->assertDatabaseHas('to_review_lists', [
                'user_id' => $user->id,
                'book_id' => $book->id,
            ]);
        });

        it('prevents adding duplicate books to to-review list', function () {
            $user = User::factory()->create();
            $book = Book::factory()->create();
            ToReviewList::factory()->create([
                'user_id' => $user->id,
                'book_id' => $book->id,
            ]);

            $response = $this->actingAs($user)->post(route('to-review-lists.store'), [
                'book_id' => $book->id,
            ]);

            $response->assertRedirect();
            $response->assertSessionHas('info');
        });

        it('requires authentication', function () {
            $book = Book::factory()->create();

            $response = $this->post(route('to-review-lists.store'), [
                'book_id' => $book->id,
            ]);

            $response->assertRedirect(route('login'));
        });

        it('validates book_id or title is required', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->post(route('to-review-lists.store'), []);

            $response->assertSessionHasErrors(['book_id', 'title']);
        });

        it('creates book from online search data when adding to review list', function () {
            $user = User::factory()->create();

            $bookData = [
                'title' => 'The Great Gatsby',
                'author' => 'F. Scott Fitzgerald',
                'isbn' => '9780743273565',
                'external_id' => 'OL12345M',
                'ol_work_key' => '/works/OL468516W',
                'cover_url' => 'https://covers.openlibrary.org/b/id/12345-L.jpg',
                'publisher' => 'Scribner',
                'publish_date' => 1925,
            ];

            $response = $this->actingAs($user)->post(route('to-review-lists.store'), $bookData);

            $response->assertRedirect();
            $response->assertSessionHas('success');

            // Verify book was created
            $this->assertDatabaseHas('books', [
                'title' => 'The Great Gatsby',
                'author' => 'F. Scott Fitzgerald',
                'external_id' => 'OL12345M',
            ]);

            // Verify it was added to to-review list
            $book = Book::where('external_id', 'OL12345M')->first();
            $this->assertDatabaseHas('to_review_lists', [
                'user_id' => $user->id,
                'book_id' => $book->id,
            ]);
        });

        it('uses existing book when external_id matches', function () {
            $user = User::factory()->create();
            $existingBook = Book::factory()->create([
                'external_id' => 'OL12345M',
                'title' => 'Existing Book',
            ]);

            $bookData = [
                'title' => 'The Great Gatsby',
                'author' => 'F. Scott Fitzgerald',
                'cover_url' => 'https://covers.openlibrary.org/b/id/12345-M.jpg',
                'external_id' => 'OL12345M',
            ];

            $response = $this->actingAs($user)->post(route('to-review-lists.store'), $bookData);

            $response->assertRedirect();
            $response->assertSessionHas('success');

            // Should use the existing book, not create a new one
            $this->assertEquals(1, Book::where('external_id', 'OL12345M')->count());

            // Should be added to the to-review list with the existing book
            $this->assertDatabaseHas('to_review_lists', [
                'user_id' => $user->id,
                'book_id' => $existingBook->id,
            ]);
        });
    });

    describe('destroy', function () {
        it('allows user to remove book from their to-review list', function () {
            $user = User::factory()->create();
            $item = ToReviewList::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->delete(route('to-review-lists.destroy', $item));

            $response->assertRedirect(route('to-review-lists.index'));
            $this->assertDatabaseMissing('to_review_lists', ['id' => $item->id]);
        });

        it('prevents user from removing other user items', function () {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();
            $item = ToReviewList::factory()->create(['user_id' => $user1->id]);

            $response = $this->actingAs($user2)->delete(route('to-review-lists.destroy', $item));

            $response->assertForbidden();
            $this->assertDatabaseHas('to_review_lists', ['id' => $item->id]);
        });

        it('requires authentication', function () {
            $item = ToReviewList::factory()->create();

            $response = $this->delete(route('to-review-lists.destroy', $item));

            $response->assertRedirect(route('login'));
        });
    });

    describe('markReviewed', function () {
        it('creates a review and removes from to-review list', function () {
            $user = User::factory()->create();
            $item = ToReviewList::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->post(route('to-review-lists.mark-reviewed', $item), [
                'rating' => 5,
                'content' => 'Excellent book!',
            ]);

            $response->assertRedirect(route('reviews.index'));

            // Verify review was created
            $this->assertDatabaseHas('reviews', [
                'user_id' => $user->id,
                'book_id' => $item->book_id,
                'rating' => 5,
                'content' => 'Excellent book!',
            ]);

            // Verify item was removed from to-review list
            $this->assertDatabaseMissing('to_review_lists', ['id' => $item->id]);
        });

        it('prevents user from marking reviewed on other user items', function () {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();
            $item = ToReviewList::factory()->create(['user_id' => $user1->id]);

            $response = $this->actingAs($user2)->post(route('to-review-lists.mark-reviewed', $item), [
                'rating' => 3,
                'content' => 'Not a bad book at all',
            ]);

            $response->assertForbidden();
        });

        it('requires authentication', function () {
            $item = ToReviewList::factory()->create();

            $response = $this->post(route('to-review-lists.mark-reviewed', $item), [
                'rating' => 5,
                'content' => 'Good',
            ]);

            $response->assertRedirect(route('login'));
        });

        it('validates required fields', function () {
            $user = User::factory()->create();
            $item = ToReviewList::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->post(route('to-review-lists.mark-reviewed', $item), []);

            $response->assertSessionHasErrors(['rating', 'content']);
        });

        it('validates rating is between 1 and 5', function () {
            $user = User::factory()->create();
            $item = ToReviewList::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->post(route('to-review-lists.mark-reviewed', $item), [
                'rating' => 10,
                'content' => 'Invalid rating',
            ]);

            $response->assertSessionHasErrors('rating');
        });
    });
});
