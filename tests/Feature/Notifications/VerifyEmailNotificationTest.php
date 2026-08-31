<?php

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\App;

/**
 * The branded verification mail is rendered from the *recipient's* language,
 * not from the locale of whatever request happened to trigger it: the mail may
 * be queued and rendered long after — and elsewhere than — that request. Every
 * test below therefore pins the application locale to English and asserts on
 * what the recipient gets.
 *
 * `Notification::fake()` is deliberately absent: nothing here is about whether
 * the mail was sent (tests/Feature/Auth/VerificationNotificationTest owns that)
 * and toMail() is called directly, so no mail leaves the process.
 */
beforeEach(function () {
    App::setLocale('en');
});

test('renders the subject and the call to action in the recipient own language', function () {
    $user = User::factory()->create(['locale' => 'ar', 'name' => 'Nadia']);

    $mail = (new VerifyEmailNotification)->toMail($user);

    expect($mail->subject)->toBe('تحقق من بريدك في '.config('app.name'))
        ->and($mail->viewData['action'])->toBe('تحقق من البريد الإلكتروني')
        ->and($mail->viewData['locale'])->toBe('ar');
});

/**
 * `dir` comes from `petconnect.locales.rtl`, so adding an RTL language is a
 * config entry rather than a code change. English must not inherit it.
 */
test('marks an arabic mail right to left and an english one left to right', function () {
    $arabicReader = User::factory()->create(['locale' => 'ar']);
    $englishReader = User::factory()->create(['locale' => 'en']);

    expect((new VerifyEmailNotification)->toMail($arabicReader)->viewData['dir'])->toBe('rtl')
        ->and((new VerifyEmailNotification)->toMail($englishReader)->viewData['dir'])->toBe('ltr');
});

/**
 * `preferredLocale()` filters the stored value against
 * `petconnect.locales.supported`, so a column holding a language that was
 * removed from the whitelist renders in English rather than shipping a mail of
 * raw translation keys.
 */
test('falls back to the application language when the stored locale is not supported', function () {
    $user = User::factory()->create(['locale' => 'de']);

    $mail = (new VerifyEmailNotification)->toMail($user);

    expect($mail->viewData['locale'])->toBe('en')
        ->and($mail->subject)->toBe('Verify your '.config('app.name').' email');
});

/**
 * The mail addresses the recipient by name and the template escapes it. A name
 * is free text on a public form, so this is the one field in the payload an
 * attacker chooses.
 */
test('escapes a name carrying markup in the rendered mail', function () {
    $user = User::factory()->create([
        'locale' => 'en',
        'name' => "O'Reilly <script>alert('xss')</script>",
    ]);

    $content = (new VerifyEmailNotification)->toMail($user)->render();

    expect($content)
        ->toContain('O&#039;Reilly')
        ->not->toContain("<script>alert('xss')</script>");
});
