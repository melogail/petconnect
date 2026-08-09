<?php

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;

test('verification email uses the branded petconnect template', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create([
        'name' => 'Alex Rivera',
        'email' => 'alex@example.com',
        'locale' => 'en',
    ]);

    $user->sendEmailVerificationNotification();

    Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use ($user) {
        $mail = $notification->toMail($user);
        $rendered = $mail->render();

        expect($mail->subject)->toBe(__('mail.verify_email.subject', ['app' => config('app.name')], 'en'))
            ->and($mail->view)->toBe('emails.verify-email')
            ->and($rendered)->toContain('Alex Rivera')
            ->and($rendered)->toContain('alex@example.com')
            ->and($rendered)->toContain(__('mail.verify_email.action', [], 'en'))
            ->and($rendered)->toContain(__('mail.verify_email.heading', [], 'en'))
            ->and($rendered)->toContain('#7C3AED')
            ->and($rendered)->toContain(config('app.name'));

        return true;
    });
});

test('verification email respects the user locale', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create([
        'name' => 'سارة',
        'locale' => 'ar',
    ]);

    $user->sendEmailVerificationNotification();

    Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use ($user) {
        $mail = $notification->toMail($user);
        $rendered = $mail->render();

        expect($user->preferredLocale())->toBe('ar')
            ->and($mail->subject)->toBe(__('mail.verify_email.subject', ['app' => config('app.name')], 'ar'))
            ->and($rendered)->toContain('lang="ar"')
            ->and($rendered)->toContain('dir="rtl"')
            ->and($rendered)->toContain(__('mail.verify_email.action', [], 'ar'))
            ->and($rendered)->toContain(__('mail.verify_email.heading', [], 'ar'));

        return true;
    });
});
