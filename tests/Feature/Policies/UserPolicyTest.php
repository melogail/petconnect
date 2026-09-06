<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

describe('view', function () {
    /**
     * A profile is a public page — a shared listing links to its owner and a
     * review is a public statement about a named person — so the guest grant is
     * a decision recorded here rather than the absence of a check in the
     * controller.
     */
    test('a guest may read an active profile', function () {
        expect(Gate::forUser(null)->allows('view', User::factory()->create()))->toBeTrue();
    });

    test('an unverified user may read an active profile', function () {
        expect(User::factory()->unverified()->create()->can('view', User::factory()->create()))->toBeTrue();
    });

    /**
     * There is deliberately no owner carve-out: EnsureAccountIsActive ends a
     * deactivated account's session on its next request, so it can never be the
     * viewer, and a clause for it would be a branch nothing on the web guard
     * can reach.
     */
    test('nobody may read a deactivated profile, the account itself included', function () {
        $deactivated = User::factory()->inactive()->create();

        expect(Gate::forUser(null)->allows('view', $deactivated))->toBeFalse()
            ->and(User::factory()->create()->can('view', $deactivated))->toBeFalse()
            ->and($deactivated->can('view', $deactivated))->toBeFalse();
    });
});

describe('update', function () {
    test('the account holder may edit their own profile', function () {
        $user = User::factory()->create();

        expect($user->can('update', $user))->toBeTrue();
    });

    test('a stranger may not edit somebody else profile', function () {
        expect(User::factory()->create()->can('update', User::factory()->create()))->toBeFalse();
    });

    /**
     * Verification is not required to correct your own record — making it so
     * would trap a user whose email address is the thing they need to fix.
     */
    test('an unverified account holder may still edit their own profile', function () {
        $user = User::factory()->unverified()->create();

        expect($user->can('update', $user))->toBeTrue();
    });
});

describe('delete', function () {
    test('the account holder may delete their own account', function () {
        $user = User::factory()->create();

        expect($user->can('delete', $user))->toBeTrue();
    });

    test('a stranger may not delete somebody else account', function () {
        expect(User::factory()->create()->can('delete', User::factory()->create()))->toBeFalse();
    });

    /**
     * Somebody who never confirmed their email must still be able to remove the
     * account they created; making deletion the one thing an unverified user
     * cannot do would be the wrong way round.
     */
    test('an unverified account holder may still delete their own account', function () {
        $user = User::factory()->unverified()->create();

        expect($user->can('delete', $user))->toBeTrue();
    });
});

/**
 * Liking a profile, which had a flag on the payload for two phases and no route
 * that could flip it until `profile.like` landed.
 *
 * Verification is required here where `update` and `delete` do not ask for it,
 * because the like notifies the person it is about — an unconfirmed address is
 * a fine thing to correct your own record from and a poor thing to send mail
 * on somebody else's behalf from.
 */
describe('like', function () {
    test('a verified user may like an active profile', function () {
        expect(User::factory()->create()->can('like', User::factory()->create()))->toBeTrue();
    });

    /**
     * Liking your own profile is allowed here and produces no notification —
     * LikeObserver skips a self-like. The policy is about who may reach the
     * route, not about taste.
     */
    test('a verified user may like their own profile', function () {
        $user = User::factory()->create();

        expect($user->can('like', $user))->toBeTrue();
    });

    test('an unverified user may not like a profile', function () {
        expect(User::factory()->unverified()->create()->can('like', User::factory()->create()))->toBeFalse();
    });

    /**
     * This re-derives what `view` already decided about a deactivated account:
     * a profile whose page is a 403 must not stay likeable at a guessable
     * sequential id.
     */
    test('nobody may like a deactivated profile', function () {
        expect(User::factory()->create()->can('like', User::factory()->inactive()->create()))->toBeFalse();
    });

    test('a guest may not like a profile', function () {
        expect(Gate::forUser(null)->allows('like', User::factory()->create()))->toBeFalse();
    });
});

/**
 * ProfileResource asks `update` once per rendered profile and would still be
 * free if it asked per row. Every method decides from `is_active` or the primary
 * key, both already on the models in hand, so a check that reached for a
 * relation would be one query per row and nothing else would catch it — Gate
 * calls are invisible to preventLazyLoading reasoning.
 */
test('decides every question without a query', function () {
    $viewer = User::factory()->create();
    $subject = User::factory()->create();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $viewer->can('view', $subject);
    $viewer->can('update', $subject);
    $viewer->can('delete', $subject);
    $viewer->can('like', $subject);

    expect($queries)->toBe(0);
});
