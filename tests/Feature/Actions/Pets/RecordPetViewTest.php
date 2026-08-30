<?php

use App\Actions\Pets\RecordPetView;
use App\Models\Pet;
use App\Models\User;

test('counts a visit from a guest', function () {
    $pet = Pet::factory()->create(['views' => 7]);

    $counted = app(RecordPetView::class)->handle($pet, null, 'session-abc');

    expect($counted)->toBeTrue()
        ->and($pet->fresh()->views)->toBe(8);
});

test('does not count the owner looking at their own listing', function () {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['views' => 7]);

    $counted = app(RecordPetView::class)->handle($pet, $owner, (string) $owner->getKey());

    expect($counted)->toBeFalse()
        ->and($pet->fresh()->views)->toBe(7);
});

test('counts the same visitor only once inside the dedup window', function () {
    config(['petconnect.pets.view_dedup_minutes' => 60]);
    $pet = Pet::factory()->create(['views' => 0]);
    $action = app(RecordPetView::class);

    $action->handle($pet, null, 'session-abc');
    $second = $action->handle($pet, null, 'session-abc');

    expect($second)->toBeFalse()
        ->and($pet->fresh()->views)->toBe(1);
});

test('counts a different visitor inside the same window', function () {
    $pet = Pet::factory()->create(['views' => 0]);
    $action = app(RecordPetView::class);

    $action->handle($pet, null, 'session-abc');
    $action->handle($pet, null, 'session-xyz');

    expect($pet->fresh()->views)->toBe(2);
});

test('counts the same visitor again once the dedup window has passed', function () {
    config(['petconnect.pets.view_dedup_minutes' => 60]);
    $pet = Pet::factory()->create(['views' => 0]);
    $action = app(RecordPetView::class);

    $action->handle($pet, null, 'session-abc');
    $this->travel(61)->minutes();
    $second = $action->handle($pet, null, 'session-abc');

    expect($second)->toBeTrue()
        ->and($pet->fresh()->views)->toBe(2);
});

test('does not age the listing when it counts a view', function () {
    $this->travelTo('2020-03-01 09:00:00');
    $pet = Pet::factory()->create(['views' => 0]);
    $this->travelTo('2026-08-31 09:00:00');

    app(RecordPetView::class)->handle($pet, null, 'session-abc');

    expect($pet->fresh()->updated_at->toDateTimeString())->toBe('2020-03-01 09:00:00');
});
