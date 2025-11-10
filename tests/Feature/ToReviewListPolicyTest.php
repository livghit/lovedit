<?php

use App\Models\ToReviewList;
use App\Models\User;
use App\Policies\ToReviewListPolicy;

describe('ToReviewListPolicy', function () {
    $policy = new ToReviewListPolicy;

    describe('viewAny', function () use ($policy) {
        it('allows any user to view any to-review lists', function () use ($policy) {
            $user = User::factory()->make();

            expect($policy->viewAny($user))->toBeTrue();
        });
    });

    describe('view', function () use ($policy) {
        it('allows user to view their own to-review list item', function () use ($policy) {
            $user = User::factory()->make(['id' => 1]);
            $item = ToReviewList::factory()->make(['user_id' => 1]);

            expect($policy->view($user, $item))->toBeTrue();
        });

        it('prevents user from viewing other user to-review list item', function () use ($policy) {
            $user = User::factory()->make(['id' => 1]);
            $item = ToReviewList::factory()->make(['user_id' => 2]);

            expect($policy->view($user, $item))->toBeFalse();
        });
    });

    describe('create', function () use ($policy) {
        it('allows any user to create a to-review list item', function () use ($policy) {
            $user = User::factory()->make();

            expect($policy->create($user))->toBeTrue();
        });
    });

    describe('update', function () use ($policy) {
        it('allows user to update their own to-review list item', function () use ($policy) {
            $user = User::factory()->make(['id' => 1]);
            $item = ToReviewList::factory()->make(['user_id' => 1]);

            expect($policy->update($user, $item))->toBeTrue();
        });

        it('prevents user from updating other user to-review list item', function () use ($policy) {
            $user = User::factory()->make(['id' => 1]);
            $item = ToReviewList::factory()->make(['user_id' => 2]);

            expect($policy->update($user, $item))->toBeFalse();
        });
    });

    describe('delete', function () use ($policy) {
        it('allows user to delete their own to-review list item', function () use ($policy) {
            $user = User::factory()->make(['id' => 1]);
            $item = ToReviewList::factory()->make(['user_id' => 1]);

            expect($policy->delete($user, $item))->toBeTrue();
        });

        it('prevents user from deleting other user to-review list item', function () use ($policy) {
            $user = User::factory()->make(['id' => 1]);
            $item = ToReviewList::factory()->make(['user_id' => 2]);

            expect($policy->delete($user, $item))->toBeFalse();
        });
    });

    describe('restore', function () use ($policy) {
        it('allows user to restore their own to-review list item', function () use ($policy) {
            $user = User::factory()->make(['id' => 1]);
            $item = ToReviewList::factory()->make(['user_id' => 1]);

            expect($policy->restore($user, $item))->toBeTrue();
        });

        it('prevents user from restoring other user to-review list item', function () use ($policy) {
            $user = User::factory()->make(['id' => 1]);
            $item = ToReviewList::factory()->make(['user_id' => 2]);

            expect($policy->restore($user, $item))->toBeFalse();
        });
    });

    describe('forceDelete', function () use ($policy) {
        it('allows user to force delete their own to-review list item', function () use ($policy) {
            $user = User::factory()->make(['id' => 1]);
            $item = ToReviewList::factory()->make(['user_id' => 1]);

            expect($policy->forceDelete($user, $item))->toBeTrue();
        });

        it('prevents user from force deleting other user to-review list item', function () use ($policy) {
            $user = User::factory()->make(['id' => 1]);
            $item = ToReviewList::factory()->make(['user_id' => 2]);

            expect($policy->forceDelete($user, $item))->toBeFalse();
        });
    });
});
