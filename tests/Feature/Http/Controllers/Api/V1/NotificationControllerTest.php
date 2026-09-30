<?php

use App\Models\Pet;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Give the owner one real database notification: a like on their listing,
 * which LikeObserver turns into a ModelLikedNotification.
 */
function notifyOwnerOfLike(User $owner): void
{
    $pet = Pet::factory()->create(['user_id' => $owner->getKey()]);
    $pet->like(User::factory()->create());
}

describe('index', function () {
    test('lists the inbox with the unread count in meta', function () {
        $owner = User::factory()->create();
        notifyOwnerOfLike($owner);
        Sanctum::actingAs($owner);

        $this->getJson(route('api.v1.notifications.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'like')
            ->assertJsonPath('data.0.read', false)
            ->assertJsonPath('meta.unread_count', 1);
    });

    test('requires a token', function () {
        $this->getJson(route('api.v1.notifications.index'))->assertUnauthorized();
    });
});

describe('writes', function () {
    test('marks one, then all, as read and clears the inbox', function () {
        $owner = User::factory()->create();
        notifyOwnerOfLike($owner);
        notifyOwnerOfLike($owner);
        Sanctum::actingAs($owner);

        $first = $this->getJson(route('api.v1.notifications.index'))->json('data.0.id');

        $this->postJson(route('api.v1.notifications.read', $first))
            ->assertOk()
            ->assertJsonPath('read', true);

        expect($owner->unreadNotifications()->count())->toBe(1);

        $this->postJson(route('api.v1.notifications.read-all'))->assertNoContent();
        expect($owner->unreadNotifications()->count())->toBe(0);

        $this->deleteJson(route('api.v1.notifications.destroy-all'))->assertNoContent();
        expect($owner->notifications()->count())->toBe(0);
    });

    test('cannot mark another account\'s notification as read', function () {
        $owner = User::factory()->create();
        notifyOwnerOfLike($owner);
        $foreign = $owner->notifications()->firstOrFail()->getKey();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.notifications.read', $foreign))->assertNotFound();
    });
});
