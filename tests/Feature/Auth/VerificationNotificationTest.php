<?php

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

/**
 * The branded override, not Laravel's own VerifyEmail.
 *
 * User::sendEmailVerificationNotification() sends
 * App\Notifications\VerifyEmailNotification, and NotificationFake keys sent
 * notifications by their **exact** class name rather than by `instanceof`, so
 * asserting the parent class here would fail even though the mail was sent.
 * Asserting the subclass is what pins the override in place: swap it back for
 * the framework notification and this test, not a rendering test, is what says
 * so.
 */
test('sends verification notification', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('home'));

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

test('does not send verification notification if email is verified', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('home', absolute: false));

    Notification::assertNothingSent();
});
