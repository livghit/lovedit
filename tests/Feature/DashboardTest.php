<?php

use App\Models\Review;
use App\Models\ToReviewList;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(function (\Inertia\Testing\AssertableInertia $page) {
            $page->component('dashboard')
                ->has('stats')
                ->has('stats.totalReviews')
                ->has('stats.averageRating')
                ->has('stats.ratingDistribution')
                ->has('stats.pendingBooksCount')
                ->has('recentActivity')
                ->has('recentActivity.reviews')
                ->has('recentActivity.toReview')
                ->has('recentActivity.reviewBooks')
                ->has('insights')
                ->has('insights.highestRatedBook')
                ->has('insights.mostRecentReviewedBook')
                ->has('insights.mostFrequentAuthor');
        });
});

test('dashboard stats include correct rating distribution and counts', function () {
    $this->actingAs($user = User::factory()->create());

    // Seed reviews with a known distribution for this user
    Review::factory()->for($user)->count(3)->create(['rating' => 5]);
    Review::factory()->for($user)->count(2)->create(['rating' => 4]);
    Review::factory()->for($user)->count(1)->create(['rating' => 3]);
    Review::factory()->for($user)->count(0)->create(['rating' => 2]);
    Review::factory()->for($user)->count(1)->create(['rating' => 1]);

    // Seed pending to-review items
    ToReviewList::factory()->for($user)->count(2)->create();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(function (\Inertia\Testing\AssertableInertia $page) {
            $page->where('stats.ratingDistribution', [
                5 => 3,
                4 => 2,
                3 => 1,
                2 => 0,
                1 => 1,
            ])->where('stats.totalReviews', 7)
                ->where('stats.pendingBooksCount', 2)
                ->where('stats.averageRating', 3.9);
        });
});
