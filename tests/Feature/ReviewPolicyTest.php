<?php

use App\Models\Review;
use App\Models\User;
use App\Policies\ReviewPolicy;

describe('ReviewPolicy', function () {
    $policy = new ReviewPolicy;

    describe('view', function () use ($policy) {
        it('allows any user to view any review', function () use ($policy) {
            $user = User::factory()->make();
            $review = Review::factory()->make();

            expect($policy->view($user, $review))->toBeTrue();
        });
    });

    describe('viewAny', function () use ($policy) {
        it('allows any user to view any reviews', function () use ($policy) {
            $user = User::factory()->make();

            expect($policy->viewAny($user))->toBeTrue();
        });
    });

    describe('create', function () use ($policy) {
        it('allows any user to create a review', function () use ($policy) {
            $user = User::factory()->make();

            expect($policy->create($user))->toBeTrue();
        });
    });

    describe('update', function () use ($policy) {
        it('allows user to update their own review', function () use ($policy) {
            $user = User::factory()->make(['id' => 1]);
            $review = Review::factory()->make(['user_id' => 1]);

            expect($policy->update($user, $review))->toBeTrue();
        });

        it('prevents user from updating other user review', function () use ($policy) {
            $user = User::factory()->make(['id' => 1]);
            $review = Review::factory()->make(['user_id' => 2]);

            expect($policy->update($user, $review))->toBeFalse();
        });
    });

    describe('delete', function () use ($policy) {
        it('allows user to delete their own review', function () use ($policy) {
            $user = User::factory()->make(['id' => 1]);
            $review = Review::factory()->make(['user_id' => 1]);

            expect($policy->delete($user, $review))->toBeTrue();
        });

        it('prevents user from deleting other user review', function () use ($policy) {
            $user = User::factory()->make(['id' => 1]);
            $review = Review::factory()->make(['user_id' => 2]);

            expect($policy->delete($user, $review))->toBeFalse();
        });
    });

    describe('restore', function () use ($policy) {
        it('allows user to restore their own review', function () use ($policy) {
            $user = User::factory()->make(['id' => 1]);
            $review = Review::factory()->make(['user_id' => 1]);

            expect($policy->restore($user, $review))->toBeTrue();
        });

        it('prevents user from restoring other user review', function () use ($policy) {
            $user = User::factory()->make(['id' => 1]);
            $review = Review::factory()->make(['user_id' => 2]);

            expect($policy->restore($user, $review))->toBeFalse();
        });
    });

    describe('forceDelete', function () use ($policy) {
        it('allows user to force delete their own review', function () use ($policy) {
            $user = User::factory()->make(['id' => 1]);
            $review = Review::factory()->make(['user_id' => 1]);

            expect($policy->forceDelete($user, $review))->toBeTrue();
        });

        it('prevents user from force deleting other user review', function () use ($policy) {
            $user = User::factory()->make(['id' => 1]);
            $review = Review::factory()->make(['user_id' => 2]);

            expect($policy->forceDelete($user, $review))->toBeFalse();
        });
    });
});
